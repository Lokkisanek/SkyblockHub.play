<?php

namespace App\Http\Controllers;

use App\Models\BazaarItem;
use App\Models\PortfolioItem;
use App\Models\PriceHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $openPositions = PortfolioItem::where('user_id', $user->id)
            ->whereNull('sold_at')
            ->orderByDesc('purchased_at')
            ->get();

        $closedPositions = PortfolioItem::where('user_id', $user->id)
            ->whereNotNull('sold_at')
            ->orderByDesc('sold_at')
            ->limit(50)
            ->get();

        // Fetch current Bazaar prices for open positions
        $productIds = $openPositions->pluck('product_id')->unique()->toArray();
        $currentPrices = BazaarItem::whereIn('product_id', $productIds)
            ->pluck('sell_price', 'product_id')
            ->toArray();

        // Chart data: daily portfolio value over last 30 days
        $chartData = $this->buildChartData($user->id);

        // Summary stats
        $totalInvested = $openPositions->sum(fn ($p) => $p->buy_price * $p->quantity);
        $totalCurrentValue = $openPositions->sum(function ($p) use ($currentPrices) {
            $price = $currentPrices[$p->product_id] ?? $p->buy_price;
            return $price * $p->quantity;
        });
        $realisedPnl = $closedPositions->sum(fn ($p) => ($p->sold_price - $p->buy_price) * $p->quantity);

        // Available Bazaar items for the "add position" dropdown
        $bazaarItems = BazaarItem::orderBy('name')
            ->select('product_id', 'name', 'sell_price', 'buy_price')
            ->get();

        return Inertia::render('Portfolio/Index', [
            'openPositions'    => $openPositions,
            'closedPositions'  => $closedPositions,
            'currentPrices'    => $currentPrices,
            'chartData'        => $chartData,
            'totalInvested'    => round($totalInvested, 2),
            'totalCurrentValue' => round($totalCurrentValue, 2),
            'realisedPnl'      => round($realisedPnl, 2),
            'bazaarItems'      => $bazaarItems,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id'   => 'required|string|max:100',
            'product_name' => 'required|string|max:255',
            'buy_price'    => 'required|numeric|min:0.01',
            'quantity'     => 'required|integer|min:1',
            'purchased_at' => 'nullable|date',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['purchased_at'] = $validated['purchased_at'] ?? now();

        PortfolioItem::create($validated);

        return back()->with('success', 'Position added.');
    }

    public function sell(Request $request)
    {
        $validated = $request->validate([
            'id'         => 'required|integer',
            'sold_price' => 'required|numeric|min:0',
        ]);

        $item = PortfolioItem::where('id', $validated['id'])
            ->where('user_id', $request->user()->id)
            ->whereNull('sold_at')
            ->firstOrFail();

        $item->update([
            'sold_price' => $validated['sold_price'],
            'sold_at'    => now(),
        ]);

        return back()->with('success', 'Position closed.');
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer',
        ]);

        PortfolioItem::where('id', $validated['id'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return back()->with('success', 'Position deleted.');
    }

    /**
     * Build chart data: daily portfolio value over last 30 days.
     */
    private function buildChartData(int $userId): array
    {
        $positions = PortfolioItem::where('user_id', $userId)->get();

        if ($positions->isEmpty()) {
            return ['labels' => [], 'invested' => [], 'value' => []];
        }

        $productIds = $positions->pluck('product_id')->unique()->toArray();

        // Get daily price snapshots for the last 30 days
        $days = collect();
        for ($i = 29; $i >= 0; $i--) {
            $days->push(now()->subDays($i)->startOfDay());
        }

        $labels = [];
        $investedSeries = [];
        $valueSeries = [];

        foreach ($days as $day) {
            $labels[] = $day->format('M d');

            // Positions that were open on this day
            $openOnDay = $positions->filter(function ($p) use ($day) {
                $bought = $p->purchased_at->startOfDay();
                $sold = $p->sold_at ? $p->sold_at->startOfDay() : now()->addDay();
                return $bought->lte($day) && $sold->gt($day);
            });

            $invested = $openOnDay->sum(fn ($p) => $p->buy_price * $p->quantity);
            $investedSeries[] = round($invested, 2);

            // Get closest price snapshot for each product_id on that day
            $value = 0;
            foreach ($openOnDay as $p) {
                $snapshot = PriceHistory::where('bazaar_item_id', function ($q) use ($p) {
                    $q->select('id')
                        ->from('bazaar_items')
                        ->where('product_id', $p->product_id)
                        ->limit(1);
                })
                    ->where('recorded_at', '<=', $day->copy()->endOfDay())
                    ->orderByDesc('recorded_at')
                    ->first();

                $price = $snapshot ? $snapshot->sell_price : $p->buy_price;
                $value += $price * $p->quantity;
            }
            $valueSeries[] = round($value, 2);
        }

        return [
            'labels'   => $labels,
            'invested' => $investedSeries,
            'value'    => $valueSeries,
        ];
    }
}
