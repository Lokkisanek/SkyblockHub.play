<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SkyCryptProxyController extends Controller
{
    /**
     * SkyCrypt (shiiyu.moe) API base URL.
     */
    private const BASE_URL = 'https://sky.shiiyu.moe/api/v2/profile';

    /**
     * Cache TTL in seconds (5 minutes).
     */
    private const CACHE_TTL = 300;

    /**
     * Max retries on rate-limit / server error.
     */
    private const MAX_RETRIES = 3;

    /**
     * Proxy a profile request through Redis cache.
     *
     * GET /api/skycrypt/{username}
     */
    public function profile(string $username): JsonResponse
    {
        // Sanitise the username — alphanumeric + underscore, 1-16 chars
        if (! preg_match('/^[A-Za-z0-9_]{1,16}$/', $username)) {
            return response()->json([
                'error' => 'Invalid Minecraft username.',
            ], 422);
        }

        $cacheKey = 'skycrypt:profile:' . strtolower($username);

        // Attempt to serve from configured cache store first (use default store)
        $cacheStore = config('cache.default', 'file');
        $cached = Cache::store($cacheStore)->get($cacheKey);

        if ($cached !== null) {
            return response()->json([
                'source' => 'cache',
                'data'   => $cached,
            ]);
        }

        // Fetch from SkyCrypt API with retry logic
        $data = $this->fetchWithRetry($username);

        if ($data === null) {
            return response()->json([
                'error' => 'Failed to fetch profile data from SkyCrypt. Try again later.',
            ], 502);
        }

        // Store in configured cache store for 5 minutes
        Cache::store($cacheStore)->put($cacheKey, $data, self::CACHE_TTL);

        return response()->json([
            'source' => 'api',
            'data'   => $data,
        ]);
    }

    /**
     * Fetch with exponential back-off on 429 / 5xx.
     */
    private function fetchWithRetry(string $username): ?array
    {
        $url     = self::BASE_URL . '/' . urlencode($username);
        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $response = Http::timeout(20)
                    ->connectTimeout(10)
                    ->withHeaders([
                        'Accept'     => 'application/json',
                        'User-Agent' => 'SkyblockHub/1.0',
                    ])
                    ->get($url);

                // Rate-limited — respect Retry-After header
                if ($response->status() === 429) {
                    $retryAfter = (int) $response->header('Retry-After', 3);
                    $wait = max($retryAfter, pow(2, $attempt + 1));
                    Log::warning('SkyCrypt rate-limited', [
                        'username'    => $username,
                        'retry_after' => $wait,
                        'attempt'     => $attempt + 1,
                    ]);
                    sleep($wait);
                    $attempt++;
                    continue;
                }

                // Server error — retry
                if ($response->serverError()) {
                    $wait = pow(2, $attempt + 1);
                    Log::warning('SkyCrypt server error', [
                        'status'  => $response->status(),
                        'attempt' => $attempt + 1,
                    ]);
                    sleep($wait);
                    $attempt++;
                    continue;
                }

                // 404 — profile not found, don't retry
                if ($response->status() === 404) {
                    return null;
                }

                // Any other non-success status
                if (! $response->successful()) {
                    Log::error('SkyCrypt unexpected status', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                    return null;
                }

                return $response->json();
            } catch (\Exception $e) {
                $wait = pow(2, $attempt + 1);
                Log::error('SkyCrypt HTTP exception', [
                    'username'  => $username,
                    'exception' => $e->getMessage(),
                    'attempt'   => $attempt + 1,
                ]);
                sleep($wait);
                $attempt++;
            }
        }

        Log::error('SkyCrypt fetch failed after retries', ['username' => $username]);
        return null;
    }
}
