<?php

namespace App\Console\Commands;

use App\Events\BazaarDataUpdated;
use App\Models\BazaarItem;
use App\Models\PriceHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchBazaarData extends Command
{
    protected $signature = 'bazaar:fetch';

    protected $description = 'Fetch latest Bazaar data from the Hypixel API and store it in the database';

    /**
     * Hypixel API endpoint (no key required for bazaar).
     */
    private const API_URL = 'https://api.hypixel.net/v2/skyblock/bazaar';

    /**
     * Maximum retries on rate-limit (HTTP 429) or server errors.
     */
    private const MAX_RETRIES = 3;

    public function handle(): int
    {
        $this->info('Fetching Bazaar data from Hypixel API…');

        $response = $this->fetchWithRetry();

        if ($response === null) {
            $this->error('Failed to fetch Bazaar data after ' . self::MAX_RETRIES . ' retries.');
            return self::FAILURE;
        }

        if (! $response->successful() || ! $response->json('success')) {
            $this->error('API returned an unsuccessful response: ' . $response->status());
            Log::error('Bazaar API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return self::FAILURE;
        }

        $products = $response->json('products', []);
        $this->info('Received ' . count($products) . ' products. Processing…');

        $now           = now();
        $processed     = 0;
        $broadcastBatch = [];

        foreach ($products as $productId => $data) {
            $sellSummary = $data['sell_summary'] ?? [];
            $buySummary  = $data['buy_summary']  ?? [];
            $quickStatus = $data['quick_status'] ?? [];

            // Top-of-book prices
            $topSellPrice = isset($sellSummary[0]) ? (float) $sellSummary[0]['pricePerUnit'] : 0;
            $topBuyPrice  = isset($buySummary[0])  ? (float) $buySummary[0]['pricePerUnit']  : 0;

            // Volumes & order counts from quick_status
            $sellVolume     = (int) ($quickStatus['sellVolume']     ?? 0);
            $buyVolume      = (int) ($quickStatus['buyVolume']      ?? 0);
            $sellOrders     = (int) ($quickStatus['sellOrders']     ?? 0);
            $buyOrders      = (int) ($quickStatus['buyOrders']      ?? 0);
            $sellMovingWeek = (float) ($quickStatus['sellMovingWeek'] ?? 0);
            $buyMovingWeek  = (float) ($quickStatus['buyMovingWeek']  ?? 0);

            // ---- Margin & Velocity calculations ----
            // Margin = spread between instant-buy (top buy order) and instant-sell (top sell order)
            // Positive margin means profit when buy-ordering then sell-ordering.
            $margin        = $topBuyPrice - $topSellPrice;
            $marginPercent = $topSellPrice > 0
                ? round(($margin / $topSellPrice) * 100, 2)
                : 0;

            // Velocity = average items traded per hour over the last 7 days
            $hoursInWeek  = 7 * 24;
            $sellVelocity = $sellMovingWeek > 0 ? round($sellMovingWeek / $hoursInWeek, 2) : 0;
            $buyVelocity  = $buyMovingWeek  > 0 ? round($buyMovingWeek  / $hoursInWeek, 2) : 0;

            // Readable name: ENCHANTED_DIAMOND_BLOCK → Enchanted Diamond Block
            $name = $this->humanise($productId);

            // Upsert the bazaar item
            $item = BazaarItem::updateOrCreate(
                ['product_id' => $productId],
                [
                    'name'             => $name,
                    'sell_price'       => $topSellPrice,
                    'buy_price'        => $topBuyPrice,
                    'sell_volume'      => $sellVolume,
                    'buy_volume'       => $buyVolume,
                    'sell_orders'      => $sellOrders,
                    'buy_orders'       => $buyOrders,
                    'sell_moving_week' => $sellMovingWeek,
                    'buy_moving_week'  => $buyMovingWeek,
                    'last_updated'     => $now,
                ]
            );

            // Record a price-history snapshot
            PriceHistory::create([
                'bazaar_item_id' => $item->id,
                'sell_price'     => $topSellPrice,
                'buy_price'      => $topBuyPrice,
                'sell_volume'    => $sellVolume,
                'buy_volume'     => $buyVolume,
                'recorded_at'    => $now,
            ]);

            // Collect data for broadcast
            $broadcastBatch[$productId] = [
                'id'              => $item->id,
                'product_id'      => $productId,
                'name'            => $name,
                'sell_price'      => $topSellPrice,
                'buy_price'       => $topBuyPrice,
                'sell_volume'     => $sellVolume,
                'buy_volume'      => $buyVolume,
                'sell_orders'     => $sellOrders,
                'buy_orders'      => $buyOrders,
                'sell_moving_week' => $sellMovingWeek,
                'buy_moving_week'  => $buyMovingWeek,
            ];

            $processed++;
        }

        // Broadcast live update to all connected clients
        if (! empty($broadcastBatch)) {
            BazaarDataUpdated::dispatch($broadcastBatch);
        }

        $this->info("Done. Processed {$processed} products.");

        return self::SUCCESS;
    }

    /**
     * Fetch with exponential back-off on 429 / 5xx.
     */
    private function fetchWithRetry(): ?\Illuminate\Http\Client\Response
    {
        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $response = Http::timeout(15)
                    ->connectTimeout(10)
                    ->get(self::API_URL);

                // If rate-limited, back off and retry
                if ($response->status() === 429) {
                    $retryAfter = (int) $response->header('Retry-After', 2);
                    $wait = max($retryAfter, pow(2, $attempt + 1));
                    $this->warn("Rate-limited (429). Retrying in {$wait}s… (attempt " . ($attempt + 1) . ')');
                    Log::warning('Bazaar API rate-limited', ['retry_after' => $wait]);
                    sleep($wait);
                    $attempt++;
                    continue;
                }

                // On server error, retry with back-off
                if ($response->serverError()) {
                    $wait = pow(2, $attempt + 1);
                    $this->warn("Server error ({$response->status()}). Retrying in {$wait}s…");
                    sleep($wait);
                    $attempt++;
                    continue;
                }

                return $response;
            } catch (\Exception $e) {
                $wait = pow(2, $attempt + 1);
                $this->warn("HTTP exception: {$e->getMessage()}. Retrying in {$wait}s…");
                Log::error('Bazaar API exception', ['exception' => $e->getMessage()]);
                sleep($wait);
                $attempt++;
            }
        }

        return null;
    }

    /**
     * Turn UPPER_SNAKE_CASE product IDs into Title Case names.
     */
    private function humanise(string $productId): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $productId)));
    }
}
