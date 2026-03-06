<?php

namespace App\Http\Controllers;

use App\Models\BinAlert;
use App\Models\BinSnapshot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BinSniperController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $sort = $request->input('sort', 'price');
        $direction = $request->input('direction', 'asc');
        $tier = $request->input('tier', '');

        $query = BinSnapshot::query();

        if ($search) {
            $query->where('item_name', 'ilike', "%{$search}%");
        }

        if ($tier) {
            $query->where('tier', $tier);
        }

        // Only show the latest snapshot per item (lowest BIN)
        $query->whereIn('id', function ($sub) {
            $sub->selectRaw('MIN(id)')
                ->from('bin_snapshots')
                ->groupBy('item_name');
        });

        $allowedSorts = ['item_name', 'price', 'tier', 'ends_at', 'recorded_at'];
        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $direction === 'desc' ? 'desc' : 'asc');
        }

        $snapshots = $query->paginate(50)->withQueryString();

        // User alerts
        $alerts = [];
        if ($request->user()) {
            $alerts = BinAlert::where('user_id', $request->user()->id)
                ->orderByDesc('created_at')
                ->get();
        }

        return Inertia::render('BinSniper/Index', [
            'snapshots' => $snapshots,
            'alerts'    => $alerts,
            'filters'   => [
                'search'    => $search,
                'sort'      => $sort,
                'direction' => $direction,
                'tier'      => $tier,
            ],
        ]);
    }

    public function storeAlert(Request $request)
    {
        $validated = $request->validate([
            'item_name'       => 'required|string|max:255',
            'threshold_price' => 'required|numeric|min:1',
        ]);

        BinAlert::create([
            'user_id'         => $request->user()->id,
            'item_name'       => $validated['item_name'],
            'threshold_price' => $validated['threshold_price'],
        ]);

        return back()->with('success', 'Alert created.');
    }

    public function destroyAlert(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer',
        ]);

        BinAlert::where('id', $validated['id'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return back()->with('success', 'Alert removed.');
    }

    public function toggleAlert(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer',
        ]);

        $alert = BinAlert::where('id', $validated['id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $alert->update(['is_active' => !$alert->is_active]);

        return back()->with('success', 'Alert updated.');
    }
}
