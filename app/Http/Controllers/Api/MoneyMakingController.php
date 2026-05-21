<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BazaarPrice;
use App\Services\MoneyMakingEstimatorService;
use App\Services\MoneyMakingMayorBoostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MoneyMakingController extends Controller
{
    private const BAZAAR_CACHE_SECONDS = 420;

    public function __construct(
        private readonly HypixelProfileController $profileController,
        private readonly MoneyMakingEstimatorService $estimator,
        private readonly MoneyMakingMayorBoostService $mayorBoosts,
    ) {}

    /**
     * GET /api/v1/money-making/{username}
     */
    public function show(Request $request, string $username): JsonResponse
    {
        if (! preg_match('/^[A-Za-z0-9_]{1,16}$/', $username)) {
            return response()->json(['error' => 'Invalid Minecraft username.'], 422);
        }

        $profileResponse = $this->profileController->profile($request, $username);
        $payload = $profileResponse->getData(true);

        if ($profileResponse->getStatusCode() !== 200) {
            return response()->json(
                is_array($payload) ? $payload : ['error' => 'Profile lookup failed.'],
                $profileResponse->getStatusCode()
            );
        }

        $data = is_array($payload) ? ($payload['data'] ?? null) : null;
        if (! is_array($data)) {
            return response()->json(['error' => 'Unexpected profile response.'], 502);
        }

        $profiles = $data['profiles'] ?? [];
        if ($profiles === []) {
            $message = is_array($payload) ? (string) ($payload['error'] ?? 'Live profile data is temporarily unavailable.') : 'Profile unavailable.';

            return response()->json(['error' => $message], 503);
        }

        $selectedKey = null;
        foreach ($profiles as $key => $row) {
            if (is_array($row) && ! empty($row['selected'])) {
                $selectedKey = (string) $key;
                break;
            }
        }
        if ($selectedKey === null) {
            $selectedKey = (string) array_key_first($profiles);
        }

        $profileRow = $profiles[$selectedKey] ?? null;
        $profileData = is_array($profileRow) ? ($profileRow['data'] ?? []) : [];
        if ($profileData === []) {
            return response()->json(['error' => 'Selected profile has no data.'], 404);
        }

        $resolvedUsername = (string) ($data['username'] ?? $username);
        $prices = $this->cachedBazaarInstasellPrices();
        $mayor = $this->mayorBoosts->resolve();
        $summary = $this->estimator->buildProfileSummary($resolvedUsername, $profileData);
        $methods = $this->estimator->estimate($profileData, $prices, $mayor);

        return response()->json([
            'profile' => $summary,
            'profile_id' => $selectedKey,
            'cute_name' => is_array($profileRow) ? ($profileRow['cute_name'] ?? null) : null,
            'methods' => $methods,
            'profile_source' => $payload['source'] ?? null,
            'bazaar_cache_ttl_seconds' => self::BAZAAR_CACHE_SECONDS,
        ]);
    }

    /**
     * @return array<string, float>
     */
    private function cachedBazaarInstasellPrices(): array
    {
        $ids = MoneyMakingEstimatorService::bazaarProductIds();
        sort($ids);
        $cacheKey = 'money_making:bazaar:'.md5(implode(',', $ids));

        /** @var array<string, float> */
        return Cache::remember($cacheKey, self::BAZAAR_CACHE_SECONDS, function () use ($ids) {
            return BazaarPrice::query()
                ->whereIn('product_id', $ids)
                ->pluck('sell_price', 'product_id')
                ->map(static fn ($v): float => (float) $v)
                ->all();
        });
    }
}
