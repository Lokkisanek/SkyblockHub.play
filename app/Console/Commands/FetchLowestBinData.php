<?php

namespace App\Console\Commands;

use App\Models\BinSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchLowestBinData extends Command
{
    protected $signature = 'bin:fetch';

    protected $description = 'Fetch lowest BIN auction data from the Hypixel API';

    public function handle(): int
    {
        $this->info('Fetching lowest BIN data…');

        try {
            $response = $this->fetchWithRetry('https://api.hypixel.net/v2/skyblock/auctions?page=0');

            if (!$response->successful()) {
                $this->error('API returned status '.$response->status());
                return self::FAILURE;
            }

            $data = $response->json();
            $totalPages = $data['totalPages'] ?? 1;
            $allAuctions = $data['auctions'] ?? [];

            // Fetch remaining pages (limit to first 5 pages to avoid rate limits)
            $pagesToFetch = min($totalPages, 5);
            for ($page = 1; $page < $pagesToFetch; $page++) {
                $pageResponse = $this->fetchWithRetry("https://api.hypixel.net/v2/skyblock/auctions?page={$page}");
                if ($pageResponse->successful()) {
                    $pageData = $pageResponse->json();
                    $allAuctions = array_merge($allAuctions, $pageData['auctions'] ?? []);
                }
                usleep(200_000); // 200ms between pages
            }

            // Filter BIN auctions only
            $binAuctions = collect($allAuctions)->filter(function ($auction) {
                return ($auction['bin'] ?? false) === true
                    && !($auction['claimed'] ?? false);
            });

            // Group by item name, take lowest price per item
            $lowestBins = $binAuctions
                ->groupBy('item_name')
                ->map(function ($group) {
                    return $group->sortBy('starting_bid')->first();
                });

            $now = now();
            $inserted = 0;

            foreach ($lowestBins as $itemName => $auction) {
                BinSnapshot::updateOrCreate(
                    ['auction_uuid' => $auction['uuid']],
                    [
                        'item_name'       => $itemName,
                        'price'           => $auction['starting_bid'],
                        'tier'            => $auction['tier'] ?? null,
                        'seller_username' => null, // UUID only in API
                        'ends_at'         => isset($auction['end']) ? \Carbon\Carbon::createFromTimestampMs($auction['end']) : null,
                        'recorded_at'     => $now,
                    ]
                );
                $inserted++;
            }

            // Prune old snapshots (keep last 2 hours)
            BinSnapshot::where('recorded_at', '<', now()->subHours(2))->delete();

            $this->info("Processed {$inserted} lowest BIN items.");
            return self::SUCCESS;

        } catch (\Exception $e) {
            Log::error('bin:fetch failed', ['error' => $e->getMessage()]);
            $this->error('Failed: '.$e->getMessage());
            return self::FAILURE;
        }
    }

    private function fetchWithRetry(string $url, int $maxRetries = 3): \Illuminate\Http\Client\Response
    {
        $attempt = 0;
        while (true) {
            $attempt++;
            $response = Http::timeout(30)->get($url);

            if ($response->successful()) {
                return $response;
            }

            if ($attempt >= $maxRetries || !in_array($response->status(), [429, 502, 503, 504])) {
                return $response;
            }

            $wait = (int) pow(2, $attempt);
            $this->warn("Retry {$attempt}/{$maxRetries} after {$wait}s…");
            sleep($wait);
        }
    }
}
