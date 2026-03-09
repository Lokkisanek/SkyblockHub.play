<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Utils\ItemParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches SkyBlock profile data directly from the Hypixel API (v2)
 * and transforms it into the format expected by the frontend.
 *
 * Logic mirrors how SkyCrypt (github.com/SkyCryptWebsite/SkyCrypt) works:
 *   1. Resolve username → UUID via Mojang API
 *   2. Call https://api.hypixel.net/v2/skyblock/profiles?key=…&uuid=…
 *   3. Parse skills, slayers, dungeons from raw member data
 *   4. Cache the transformed result for 5 minutes
 */
class SkyCryptProxyController extends Controller
{
    /** Cache TTL in seconds (5 minutes). */
    private const CACHE_TTL = 300;

    /** Max retries on rate-limit / server error. */
    private const MAX_RETRIES = 3;

    // ─── Skill XP tables (from SkyCrypt constants) ───────────────────
    private const SKILL_XP_TABLE = [
        0, 50, 175, 375, 675, 1175, 1925, 2925, 4425, 6425,
        9925, 14925, 22425, 32425, 47425, 67425, 97425, 147425, 222425, 322425,
        522425, 822425, 1222425, 1722425, 2322425, 3022425, 3822425, 4722425, 5722425, 6822425,
        8022425, 9322425, 10722425, 12222425, 13822425, 15522425, 17322425, 19222425, 21222425, 23322425,
        25522425, 27822425, 30222425, 32722425, 35322425, 38072425, 40972425, 44072425, 47472425, 51172425,
        55172425, 59472425, 64072425, 68972425, 74172425, 79672425, 85472425, 91572425, 97972425, 104672425,
        111672425,
    ];

    private const DUNGEON_XP_TABLE = [
        0, 50, 125, 235, 395, 625, 955, 1425, 2095, 3045,
        4385, 6275, 8940, 12700, 17960, 25340, 35640, 50040, 70040, 97640,
        135640, 188140, 259640, 356640, 488640, 668640, 911640, 1239640, 1684640, 2284640,
        3084640, 4149640, 5559640, 7459640, 9959640, 13259640, 17559640, 23159640, 30359640, 39559640,
        51559640, 66559640, 85559640, 109559640, 139559640, 177559640, 225559640, 285559640, 360559640, 453559640,
    ];

    private const SLAYER_XP_TABLE = [
        'zombie'    => [0, 5, 15, 200, 1000, 5000, 20000, 100000, 400000, 1000000],
        'spider'    => [0, 5, 25, 200, 1000, 5000, 20000, 100000, 400000, 1000000],
        'wolf'      => [0, 10, 30, 250, 1500, 5000, 20000, 100000, 400000, 1000000],
        'enderman'  => [0, 10, 30, 250, 1500, 5000, 20000, 100000, 400000, 1000000],
        'blaze'     => [0, 10, 30, 250, 1500, 5000, 20000, 100000, 400000, 1000000],
        'vampire'   => [0, 20, 75, 240, 840, 2400],
    ];

    /**
     * GET /api/skycrypt/{username}
     */
    public function profile(string $username): JsonResponse
    {
        if (! preg_match('/^[A-Za-z0-9_]{1,16}$/', $username)) {
            return response()->json(['error' => 'Invalid Minecraft username.'], 422);
        }

        $cacheKey = 'skycrypt:profile:' . strtolower($username);

        // ── Try cache ────────────────────────────────────────────────
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            return response()->json(['source' => 'cache', 'data' => $cached]);
        }

        // ── Resolve UUID via Mojang ──────────────────────────────────
        $uuid = $this->getUuidFromMojang($username);
        if ($uuid === null) {
            return response()->json(['error' => 'Player not found (Mojang lookup failed).'], 404);
        }

        // ── Fetch profiles from Hypixel API v2 ──────────────────────
        $apiKey = env('HYPIXEL_API_KEY', '');
        if (empty($apiKey)) {
            return response()->json(['error' => 'Hypixel API key not configured.'], 500);
        }

        $rawProfiles = $this->fetchHypixelProfiles($uuid, $apiKey);
        if ($rawProfiles === null) {
            return response()->json([
                'error' => 'Failed to fetch profile data from Hypixel API. Try again later.',
            ], 502);
        }

        if (empty($rawProfiles)) {
            return response()->json(['error' => 'Player has no SkyBlock profiles.'], 404);
        }

        // ── Fetch museum data for each profile ───────────────────────
        $museumDataByProfile = [];
        foreach ($rawProfiles as $profile) {
            $profileId = $profile['profile_id'] ?? null;
            if (! $profileId) continue;
            $museumDataByProfile[$profileId] = $this->fetchMuseumData($profileId, $uuid, $apiKey);
        }

        // ── Transform into front-end format ──────────────────────────
        $data = $this->transformProfiles($rawProfiles, $uuid, $username, $museumDataByProfile);

        // ── Sanitize: ensure all strings are valid UTF-8 for JSON ────
        $data = $this->sanitizeForJson($data);

        // ── Cache result ─────────────────────────────────────────────
        $this->cachePut($cacheKey, $data);

        return response()->json(['source' => 'api', 'data' => $data]);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Hypixel API
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Call Hypixel v2/skyblock/profiles with retry logic.
     * Returns the array of profiles or null on failure.
     */
    private function fetchHypixelProfiles(string $uuid, string $apiKey): ?array
    {
        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $response = Http::timeout(20)
                    ->connectTimeout(10)
                    ->acceptJson()
                    ->withHeaders(['User-Agent' => 'SkyblockHub/1.0'])
                    ->get('https://api.hypixel.net/v2/skyblock/profiles', [
                        'key'  => $apiKey,
                        'uuid' => $uuid,
                    ]);

                if ($response->status() === 429) {
                    $wait = max((int) $response->header('Retry-After', 3), pow(2, $attempt + 1));
                    Log::warning('Hypixel rate-limited', ['uuid' => $uuid, 'wait' => $wait, 'attempt' => $attempt + 1]);
                    sleep($wait);
                    $attempt++;
                    continue;
                }

                if ($response->serverError()) {
                    $wait = pow(2, $attempt + 1);
                    Log::warning('Hypixel server error', ['status' => $response->status(), 'attempt' => $attempt + 1]);
                    sleep($wait);
                    $attempt++;
                    continue;
                }

                if (! $response->successful()) {
                    Log::error('Hypixel unexpected status', ['status' => $response->status(), 'body' => $response->body()]);
                    return null;
                }

                $json = $response->json();
                if (($json['success'] ?? false) !== true) {
                    Log::error('Hypixel API returned success=false', ['cause' => $json['cause'] ?? 'unknown']);
                    return null;
                }

                return $json['profiles'] ?? [];

            } catch (\Exception $e) {
                $wait = pow(2, $attempt + 1);
                Log::error('Hypixel HTTP exception', ['uuid' => $uuid, 'exception' => $e->getMessage(), 'attempt' => $attempt + 1]);
                sleep($wait);
                $attempt++;
            }
        }

        Log::error('Hypixel fetch failed after retries', ['uuid' => $uuid]);
        return null;
    }

    /**
     * Fetch museum data from Hypixel API v2 for networth calculation.
     * Returns the member's museum data or null on failure.
     */
    private function fetchMuseumData(string $profileId, string $uuid, string $apiKey): ?array
    {
        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'SkyblockHub/1.0'])
                ->get('https://api.hypixel.net/v2/skyblock/museum', [
                    'key'     => $apiKey,
                    'profile' => $profileId,
                ]);

            if (! $response->successful()) {
                Log::warning('Museum API failed', ['profile' => $profileId, 'status' => $response->status()]);
                return null;
            }

            $json = $response->json();
            if (($json['success'] ?? false) !== true) {
                return null;
            }

            return $json['members'][$uuid] ?? null;
        } catch (\Exception $e) {
            Log::warning('Museum API exception', ['profile' => $profileId, 'exception' => $e->getMessage()]);
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Data transformation (mirrors SkyCrypt logic)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Transform raw Hypixel profiles response into the structure expected
     * by the Vue frontend:
     *
     *  {
     *    profiles: {
     *      "<profile_id>": {
     *        cute_name: "Strawberry",
     *        selected: true,
     *        game_mode: "normal",
     *        data: { skills: {…}, slayers: {…}, dungeons: {…}, networth: {…} }
     *      }
     *    }
     *  }
     */
    private function transformProfiles(array $rawProfiles, string $uuid, string $username, array $museumDataByProfile = []): array
    {
        $profiles = [];

        foreach ($rawProfiles as $profile) {
            $profileId = $profile['profile_id'] ?? null;
            if (! $profileId) continue;

            $member = $profile['members'][$uuid] ?? null;
            if (! $member) continue;

            $skills = $this->parseSkills($member);

            // Average skill level (exclude runecrafting and social, like SkyCrypt)
            $countable = array_filter($skills, fn($v, $k) => !in_array($k, ['runecrafting', 'social']), ARRAY_FILTER_USE_BOTH);
            $avgSkillLevel = count($countable) > 0
                ? round(array_sum(array_column($countable, 'level')) / count($countable), 2)
                : 0;

            // ── Calculate networth via SkyHelper-Networth ────────────
            $museumData   = $museumDataByProfile[$profileId] ?? null;
            $bankBalance  = $profile['banking']['balance'] ?? 0;
            $networthData = $this->calculateNetworth($member, $museumData, $bankBalance);

            // Extract price maps for injecting into parsed items
            $pricesByUuid = $networthData['itemPricesByUuid'] ?? [];
            $pricesById   = $networthData['itemPricesById'] ?? [];

            // Remove raw price maps from the networth response sent to frontend
            unset($networthData['itemPricesByUuid'], $networthData['itemPricesById']);

            // Parse all inventory sections first
            $armor      = $this->parseArmor($member);
            $equipment  = $this->parseEquipment($member);
            $wardrobe   = $this->parseWardrobe($member);
            $weapons    = $this->parseWeapons($member);
            $accessories= $this->parseAccessories($member);
            $inventory  = $this->parsePlayerInventory($member);
            $enderchest = $this->parseEnderChest($member);
            $personalVault = $this->parsePersonalVault($member);
            $fishingBag = $this->parseBagContents($member, 'fishing_bag');
            $potionBag  = $this->parseBagContents($member, 'potion_bag');
            $quiver     = $this->parseBagContents($member, 'quiver');
            $storage    = $this->parseBackpackStorage($member);

            // Inject item values into all flat item arrays
            $armor      = $this->injectItemValues($armor, $pricesByUuid, $pricesById);
            $equipment  = $this->injectItemValues($equipment, $pricesByUuid, $pricesById);
            $weapons    = $this->injectItemValues($weapons, $pricesByUuid, $pricesById);
            $accessories= $this->injectItemValues($accessories, $pricesByUuid, $pricesById);
            $inventory  = $this->injectItemValues($inventory, $pricesByUuid, $pricesById);
            $enderchest = $this->injectItemValuesNested($enderchest, $pricesByUuid, $pricesById);
            $personalVault = $this->injectItemValues($personalVault, $pricesByUuid, $pricesById);
            $fishingBag = $this->injectItemValues($fishingBag, $pricesByUuid, $pricesById);
            $potionBag  = $this->injectItemValues($potionBag, $pricesByUuid, $pricesById);
            $quiver     = $this->injectItemValues($quiver, $pricesByUuid, $pricesById);
            $wardrobe   = $this->injectItemValuesWardrobe($wardrobe, $pricesByUuid, $pricesById);
            $storage    = $this->injectItemValuesStorage($storage, $pricesByUuid, $pricesById);

            $profiles[$profileId] = [
                'cute_name' => $profile['cute_name'] ?? 'Unknown',
                'selected'  => $profile['selected'] ?? false,
                'game_mode' => $profile['game_mode'] ?? 'normal',
                'data'      => [
                    'skyblock_level'      => $this->parseSkyblockLevel($member),
                    'fairy_souls'         => $member['fairy_soul']['total_collected'] ?? $member['fairy_exchanges'] ?? 0,
                    'first_join'          => $member['profile']['first_join'] ?? null,
                    'average_skill_level' => $avgSkillLevel,
                    'skills'     => $skills,
                    'slayers'    => $this->parseSlayers($member),
                    'dungeons'   => $this->parseDungeons($member),
                    'networth'   => $networthData,
                    'pets'       => $this->parsePets($member),
                    'armor'      => $armor,
                    'equipment'  => $equipment,
                    'wardrobe'   => $wardrobe,
                    'weapons'    => $weapons,
                    'accessories'=> $accessories,
                    'talisman_bag'   => $this->parseBagContents($member, 'talisman_bag'),
                    'inventory'  => $inventory,
                    'enderchest' => $enderchest,
                    'personal_vault' => $personalVault,
                    'fishing_bag'    => $fishingBag,
                    'potion_bag'     => $potionBag,
                    'quiver'         => $quiver,
                    'candy_bag'      => $this->parseCandyBag($member, $profile),
                    'storage'        => $storage,
                    'museum'         => $this->parseMuseum($member, $profile),
                    'rift_inventory' => $this->parseRiftInventory($member),
                    'rift_enderchest'=> $this->parseRiftEnderchest($member),
                    'accessory_bag_storage' => $this->parseAccessoryBagStorage($member),
                    'wardrobe_slot' => $member['inventory']['wardrobe_equipped_slot'] ?? null,
                    'inv_disabled'  => empty($member['inventory']['inv_contents']['data'] ?? null),
                    'player_stats'  => $this->calculatePlayerStats($member, $skills, $armor, $equipment, $accessories),
                ],
            ];
        }

        return [
            'uuid'     => $uuid,
            'username' => $username,
            'profiles' => $profiles,
        ];
    }

    // ─── Skills ──────────────────────────────────────────────────────

    private function parseSkills(array $member): array
    {
        $skills = [];

        $experience = $member['player_data']['experience'] ?? [];

        $skillNames = [
            'farming', 'mining', 'combat', 'foraging', 'fishing',
            'enchanting', 'alchemy', 'carpentry', 'taming', 'runecrafting', 'social', 'hunting',
        ];

        foreach ($skillNames as $name) {
            $key = 'SKILL_' . strtoupper($name);
            $xp  = $experience[$key] ?? 0;

            $maxLevel = in_array($name, ['runecrafting', 'social', 'hunting']) ? 25 : 60;
            $detail   = $this->xpToLevelDetailed($xp, self::SKILL_XP_TABLE, $maxLevel);

            $skills[$name] = [
                'level'     => $detail['level'],
                'maxLevel'  => $maxLevel,
                'xp'        => $xp,
                'xpCurrent' => $detail['xpCurrent'],
                'xpForNext' => $detail['xpForNext'],
                'progress'  => $detail['progress'],
            ];
        }

        return $skills;
    }

    // ─── Slayers ─────────────────────────────────────────────────────

    private function parseSlayers(array $member): array
    {
        $slayers = [];

        $slayerBosses = $member['slayer']['slayer_bosses'] ?? $member['slayer_bosses'] ?? [];

        foreach ($slayerBosses as $name => $data) {
            if (! is_array($data)) continue;

            $xp = $data['xp'] ?? 0;

            $table    = self::SLAYER_XP_TABLE[$name] ?? self::SLAYER_XP_TABLE['zombie'];
            $maxLevel = count($table) - 1;
            $level    = 0;

            for ($i = $maxLevel; $i >= 0; $i--) {
                if ($xp >= $table[$i]) {
                    $level = $i;
                    break;
                }
            }

            $slayers[$name] = [
                'level' => [
                    'currentLevel' => $level,
                    'maxLevel'     => $maxLevel,
                ],
                'xp' => $xp,
            ];
        }

        return $slayers;
    }

    // ─── Dungeons ────────────────────────────────────────────────────

    private const FLOOR_NAMES = [
        'catacombs_0' => 'Entrance',
        'catacombs_1' => 'Floor 1', 'catacombs_2' => 'Floor 2', 'catacombs_3' => 'Floor 3',
        'catacombs_4' => 'Floor 4', 'catacombs_5' => 'Floor 5', 'catacombs_6' => 'Floor 6',
        'catacombs_7' => 'Floor 7',
        'master_catacombs_1' => 'Floor 1', 'master_catacombs_2' => 'Floor 2',
        'master_catacombs_3' => 'Floor 3', 'master_catacombs_4' => 'Floor 4',
        'master_catacombs_5' => 'Floor 5', 'master_catacombs_6' => 'Floor 6',
        'master_catacombs_7' => 'Floor 7',
    ];

    private const DUNGEON_STAT_KEYS = [
        'times_played', 'tier_completions', 'milestone_completions',
        'mobs_killed', 'best_score', 'watcher_kills',
        'most_mobs_killed', 'fastest_time', 'fastest_time_s', 'fastest_time_s_plus',
        'most_healing',
    ];

    private function parseDungeons(array $member): array
    {
        $dungeons = $member['dungeons'] ?? null;
        if (! $dungeons || empty($dungeons['dungeon_types'])) {
            return [];
        }

        $catacombs = $dungeons['dungeon_types']['catacombs'] ?? [];
        $xp        = $catacombs['experience'] ?? 0;
        $detail    = $this->xpToLevelDetailed($xp, self::DUNGEON_XP_TABLE, 50);

        // Class levels
        $classes     = [];
        $classNames  = ['healer', 'mage', 'berserk', 'archer', 'tank'];
        $playerClass = $dungeons['player_classes'] ?? [];

        foreach ($classNames as $cn) {
            $classXp     = $playerClass[$cn]['experience'] ?? 0;
            $classDetail = $this->xpToLevelDetailed($classXp, self::DUNGEON_XP_TABLE, 50);
            $classes[$cn] = [
                'level'    => $classDetail['level'],
                'xp'       => $classXp,
                'xpCurrent'=> $classDetail['xpCurrent'],
                'xpForNext'=> $classDetail['xpForNext'],
                'progress' => $classDetail['progress'],
                'maxLevel' => 50,
            ];
        }

        // Class average
        $classLevels = array_column($classes, 'level');
        $classAvg = count($classLevels) > 0 ? array_sum($classLevels) / count($classLevels) : 0;

        // Parse floors (normal catacombs)
        $normalFloors = $this->parseDungeonFloors($catacombs, 'catacombs');

        // Parse master catacombs
        $masterCata   = $dungeons['dungeon_types']['master_catacombs'] ?? [];
        $masterFloors = $this->parseDungeonFloors($masterCata, 'master_catacombs');

        // Highest floor beaten
        $highestNormal = $catacombs['highest_tier_completed'] ?? null;
        $highestMaster = $masterCata['highest_tier_completed'] ?? null;

        // Total completions for S/R calculation
        $totalCompletions = 0;
        foreach ($normalFloors as $f) { $totalCompletions += $f['stats']['tier_completions'] ?? 0; }
        foreach ($masterFloors as $f) { $totalCompletions += $f['stats']['tier_completions'] ?? 0; }

        $secretsFound = $dungeons['secrets'] ?? 0;
        $secretsPerRun = $totalCompletions > 0 ? round($secretsFound / $totalCompletions, 2) : 0;

        return [
            'catacombs' => [
                'level' => [
                    'level'     => $detail['level'],
                    'xp'        => $xp,
                    'xpCurrent' => $detail['xpCurrent'],
                    'xpForNext' => $detail['xpForNext'],
                    'progress'  => $detail['progress'],
                    'maxLevel'  => 50,
                ],
            ],
            'secrets_found'     => $secretsFound,
            'secrets_per_run'   => $secretsPerRun,
            'classes'           => $classes,
            'class_average'     => round($classAvg, 2),
            'selected_class'    => $dungeons['selected_dungeon_class'] ?? null,
            'highest_floor'     => $highestNormal,
            'highest_master'    => $highestMaster,
            'floors'            => $normalFloors,
            'master_floors'     => $masterFloors,
        ];
    }

    private function parseDungeonFloors(array $dungeonType, string $prefix): array
    {
        $floors = [];

        // Collect all floor indices
        $floorIndices = [];
        foreach (self::DUNGEON_STAT_KEYS as $statKey) {
            if (isset($dungeonType[$statKey]) && is_array($dungeonType[$statKey])) {
                foreach (array_keys($dungeonType[$statKey]) as $idx) {
                    if (is_numeric($idx)) $floorIndices[(int)$idx] = true;
                }
            }
        }
        // Also check best_runs
        if (isset($dungeonType['best_runs']) && is_array($dungeonType['best_runs'])) {
            foreach (array_keys($dungeonType['best_runs']) as $idx) {
                if (is_numeric($idx)) $floorIndices[(int)$idx] = true;
            }
        }
        // Also check most_damage_* keys
        foreach ($dungeonType as $key => $val) {
            if (str_starts_with($key, 'most_damage_') && is_array($val)) {
                foreach (array_keys($val) as $idx) {
                    if (is_numeric($idx)) $floorIndices[(int)$idx] = true;
                }
            }
        }

        ksort($floorIndices);

        foreach (array_keys($floorIndices) as $floorIdx) {
            $floorId = "{$prefix}_{$floorIdx}";
            $name    = self::FLOOR_NAMES[$floorId] ?? "Floor $floorIdx";

            // Gather stats
            $stats = [];
            foreach (self::DUNGEON_STAT_KEYS as $statKey) {
                $val = $dungeonType[$statKey][$floorIdx] ?? null;
                if ($val !== null) {
                    $stats[$statKey] = $val;
                }
            }

            // Most damage (find best across all classes)
            $mostDamage = null;
            foreach ($dungeonType as $key => $val) {
                if (str_starts_with($key, 'most_damage_') && is_array($val) && isset($val[$floorIdx])) {
                    $className = str_replace('most_damage_', '', $key);
                    if ($mostDamage === null || $val[$floorIdx] > $mostDamage['value']) {
                        $mostDamage = ['class' => $className, 'value' => $val[$floorIdx]];
                    }
                }
            }

            // Best run (last entry in best_runs array — the overall best)
            $bestRun = null;
            if (isset($dungeonType['best_runs'][$floorIdx]) && is_array($dungeonType['best_runs'][$floorIdx])) {
                $runs = $dungeonType['best_runs'][$floorIdx];
                $run = end($runs);
                if ($run) {
                    $bestRun = [
                        'timestamp'       => $run['timestamp'] ?? null,
                        'score_exploration'=> $run['score_exploration'] ?? 0,
                        'score_speed'      => $run['score_speed'] ?? 0,
                        'score_skill'      => $run['score_skill'] ?? 0,
                        'score_bonus'      => $run['score_bonus'] ?? 0,
                        'dungeon_class'    => $run['dungeon_class'] ?? null,
                        'elapsed_time'     => $run['elapsed_time'] ?? null,
                        'damage_dealt'     => $run['damage_dealt'] ?? 0,
                        'deaths'           => $run['deaths'] ?? 0,
                        'mobs_killed'      => $run['mobs_killed'] ?? 0,
                        'secrets_found'    => $run['secrets_found'] ?? 0,
                        'damage_mitigated' => $run['damage_mitigated'] ?? 0,
                    ];
                    // Calculate grade
                    $totalScore = ($bestRun['score_exploration'] + $bestRun['score_speed'] + $bestRun['score_skill'] + $bestRun['score_bonus']);
                    $bestRun['grade'] = $this->calcDungeonGrade($totalScore);
                }
            }

            $floors[] = [
                'index'       => $floorIdx,
                'name'        => $name,
                'stats'       => $stats,
                'most_damage' => $mostDamage,
                'best_run'    => $bestRun,
            ];
        }

        return $floors;
    }

    private function calcDungeonGrade(int $score): string
    {
        if ($score >= 300) return 'S+';
        if ($score >= 270) return 'S';
        if ($score >= 240) return 'A';
        if ($score >= 175) return 'B';
        if ($score >= 110) return 'C';
        if ($score >= 60)  return 'D';
        return 'F';
    }

    // ─── Networth (via SkyHelper-Networth Node.js) ─────────────────

    /**
     * Calculate networth by invoking the SkyHelper-Networth Node.js script.
     * Falls back to basic purse + bank if the script fails.
     */
    private function calculateNetworth(array $member, ?array $museumData, float $bankBalance): array
    {
        $purse = $member['currencies']['coin_purse'] ?? 0;

        // Prepare input for Node.js script
        $input = json_encode([
            'profileData' => $member,
            'museumData'  => $museumData,
            'bankBalance' => $bankBalance,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($input === false) {
            Log::warning('Networth: failed to encode input JSON');
            return $this->fallbackNetworth($purse, $bankBalance);
        }

        $scriptPath = base_path('scripts/networth.cjs');
        $nodePath   = 'node';

        // Call the Node.js script via subprocess
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = @proc_open(
            [$nodePath, $scriptPath],
            $descriptors,
            $pipes,
            base_path()
        );

        if (! is_resource($process)) {
            Log::warning('Networth: failed to start Node.js process');
            return $this->fallbackNetworth($purse, $bankBalance);
        }

        // Write input and close stdin
        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        // Read output (with timeout)
        stream_set_timeout($pipes[1], 30);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            Log::warning('Networth: Node.js script failed', [
                'exitCode' => $exitCode,
                'stderr'   => mb_substr($stderr, 0, 500),
            ]);
            return $this->fallbackNetworth($purse, $bankBalance);
        }

        $result = @json_decode($stdout, true);
        if (! $result || ! isset($result['networth'])) {
            Log::warning('Networth: invalid JSON output from Node.js', [
                'stdout' => mb_substr($stdout, 0, 500),
            ]);
            return $this->fallbackNetworth($purse, $bankBalance);
        }

        return [
            'networth'             => $result['networth'] ?? 0,
            'unsoulboundNetworth'  => $result['unsoulboundNetworth'] ?? 0,
            'purse'                => $result['purse'] ?? $purse,
            'bank'                 => $result['bank'] ?? $bankBalance,
            'personalBank'         => $result['personalBank'] ?? 0,
            'noInventory'          => $result['noInventory'] ?? false,
            'categories'           => $result['categories'] ?? [],
            'itemPricesByUuid'     => $result['itemPricesByUuid'] ?? [],
            'itemPricesById'       => $result['itemPricesById'] ?? [],
        ];
    }

    /**
     * Fallback networth when Node.js calculation fails.
     */
    private function fallbackNetworth(float $purse, float $bank): array
    {
        return [
            'networth'             => $purse + $bank,
            'unsoulboundNetworth'  => $purse + $bank,
            'purse'                => $purse,
            'bank'                 => $bank,
            'personalBank'         => 0,
            'noInventory'          => false,
            'categories'           => [],
            'itemPricesByUuid'     => [],
            'itemPricesById'       => [],
        ];
    }

    // ─── SkyBlock Level ────────────────────────────────────────────────

    private function parseSkyblockLevel(array $member): array
    {
        $xp = $member['leveling']['experience'] ?? 0;
        $level     = (int) floor($xp / 100);
        $xpCurrent = fmod($xp, 100);
        $xpForNext = 100;
        $progress  = $xpCurrent / $xpForNext;

        return [
            'level'     => $level,
            'xpCurrent' => round($xpCurrent, 2),
            'xpForNext' => $xpForNext,
            'progress'  => round($progress, 4),
            'totalXp'   => $xp,
        ];
    }

    // ─── Inventory / Items ─────────────────────────────────────────────

    /**
     * Inject item values from SkyHelper-Networth into parsed items.
     * Matches by UUID first (unique per item), then falls back to skyblock_id.
     * Also appends "Item Value" lore lines to item tooltips like SkyCrypt.
     */
    private function injectItemValues(array $items, array &$pricesByUuid, array &$pricesById): array
    {
        foreach ($items as &$item) {
            if ($item === null) continue;

            $uuid       = $item['uuid'] ?? null;
            $skyblockId = $item['skyblock_id'] ?? null;
            $priceEntry = null;

            // Try UUID match first (most reliable)
            if ($uuid !== null && isset($pricesByUuid[$uuid])) {
                $priceEntry = $pricesByUuid[$uuid];
                unset($pricesByUuid[$uuid]); // consumed
            }
            // Fallback: match by skyblock_id
            elseif ($skyblockId !== null && isset($pricesById[$skyblockId]) && !empty($pricesById[$skyblockId])) {
                $priceEntry = array_shift($pricesById[$skyblockId]);
            }

            if ($priceEntry !== null) {
                $price     = $priceEntry['price'] ?? 0;
                $soulbound = $priceEntry['soulbound'] ?? false;

                $item['item_value']     = $price;
                $item['item_soulbound'] = $soulbound;

                // Append Item Value to lore_html (like SkyCrypt)
                if ($price > 0 && isset($item['lore_html']) && is_array($item['lore_html'])) {
                    $item['lore_html'][] = ''; // empty separator line
                    $formattedFull  = number_format($price);
                    $formattedShort = ItemParser::formatNumberPublic($price);

                    if ($soulbound) {
                        $item['lore_html'][] = ItemParser::colorCodeToHtml(
                            "§7Item Value: §6{$formattedFull} Coins §7(§6{$formattedShort}§7)"
                        );
                        $item['lore_html'][] = ItemParser::colorCodeToHtml(
                            "§8(Soulbound)"
                        );
                    } else {
                        $item['lore_html'][] = ItemParser::colorCodeToHtml(
                            "§7Item Value: §6{$formattedFull} Coins §7(§6{$formattedShort}§7)"
                        );
                    }
                }
            }
        }
        unset($item);

        return $items;
    }

    /**
     * Inject item values into enderchest pages (nested structure with 'items' arrays).
     */
    private function injectItemValuesNested(array $pages, array &$pricesByUuid, array &$pricesById): array
    {
        foreach ($pages as &$page) {
            if (isset($page['items']) && is_array($page['items'])) {
                $page['items'] = $this->injectItemValues($page['items'], $pricesByUuid, $pricesById);
            }
        }
        unset($page);
        return $pages;
    }

    /**
     * Inject item values into wardrobe sets (array of sets, each set is array of 4 items).
     */
    private function injectItemValuesWardrobe(array $sets, array &$pricesByUuid, array &$pricesById): array
    {
        foreach ($sets as &$set) {
            if (is_array($set)) {
                $set = $this->injectItemValues($set, $pricesByUuid, $pricesById);
            }
        }
        unset($set);
        return $sets;
    }

    /**
     * Inject item values into backpack storage (array of slots with 'items' and 'icon').
     */
    private function injectItemValuesStorage(array $storage, array &$pricesByUuid, array &$pricesById): array
    {
        foreach ($storage as &$slot) {
            if (isset($slot['items']) && is_array($slot['items'])) {
                $slot['items'] = $this->injectItemValues($slot['items'], $pricesByUuid, $pricesById);
            }
            if (isset($slot['icon']) && is_array($slot['icon'])) {
                $one = [$slot['icon']];
                $one = $this->injectItemValues($one, $pricesByUuid, $pricesById);
                $slot['icon'] = $one[0];
            }
        }
        unset($slot);
        return $storage;
    }

    private function parseArmor(array $member): array
    {
        $data = $member['inventory']['inv_armor']['data'] ?? null;
        if (! $data) return [];

        $items = ItemParser::parseInventoryKeepSlots($data);
        // Minecraft stores armor boots-first, reverse to helm→boots
        return array_values(array_reverse($items));
    }

    private function parseEquipment(array $member): array
    {
        $data = $member['inventory']['equipment_contents']['data'] ?? null;
        if (! $data) return [];

        return ItemParser::parseInventory($data);
    }

    private function parseWardrobe(array $member): array
    {
        $data = $member['inventory']['wardrobe_contents']['data'] ?? null;
        if (! $data) return [];

        $allItems = ItemParser::parseInventoryKeepSlots($data);
        $total    = count($allItems);
        if ($total === 0) return [];

        // Wardrobe layout: each page has 9 columns × 4 rows.
        // Row 0 (slots 0-8) = helmets, Row 1 (9-17) = chests,
        // Row 2 (18-26) = legs, Row 3 (27-35) = boots.
        // Page 2 starts at slot 36, etc.
        $slotsPerPage = 36;
        $cols         = 9;
        $sets         = [];

        $pages = (int) ceil($total / $slotsPerPage);

        for ($page = 0; $page < $pages; $page++) {
            $base = $page * $slotsPerPage;
            for ($col = 0; $col < $cols; $col++) {
                $set = [];
                for ($row = 0; $row < 4; $row++) {
                    $idx   = $base + ($row * $cols) + $col;
                    $set[] = $allItems[$idx] ?? null;
                }
                // Only include sets that have at least one item
                if (array_filter($set, fn($i) => $i !== null)) {
                    $sets[] = $set;
                }
            }
        }

        return $sets;
    }

    private function parseWeapons(array $member): array
    {
        $data = $member['inventory']['inv_contents']['data'] ?? null;
        if (! $data) return [];

        $items   = ItemParser::parseInventory($data);
        $weapons = array_filter($items, fn($item) => self::isWeapon($item));

        // Sort by rarity descending
        usort($weapons, fn($a, $b) =>
            ItemParser::rarityOrder($b['rarity']) <=> ItemParser::rarityOrder($a['rarity'])
        );

        return array_values($weapons);
    }

    private function parseAccessories(array $member): array
    {
        $data = $member['inventory']['bag_contents']['talisman_bag']['data'] ?? null;
        if (! $data) return [];

        return ItemParser::parseInventory($data);
    }

    private function parsePlayerInventory(array $member): array
    {
        $data = $member['inventory']['inv_contents']['data'] ?? null;
        if (! $data) return [];

        return ItemParser::parseInventoryKeepSlots($data);
    }

    private function parseAccessoryBagStorage(array $member): array
    {
        $abs = $member['accessory_bag_storage'] ?? [];
        return [
            'selected_power'      => $abs['selected_power'] ?? null,
            'highest_magical_power'=> $abs['highest_magical_power'] ?? null,
            'tuning'              => $abs['tuning'] ?? null,
        ];
    }

    // ─── Ender Chest ──────────────────────────────────────────────────

    private function parseEnderChest(array $member): array
    {
        $data = $member['inventory']['ender_chest_contents']['data'] ?? null;
        if (! $data) return [];

        $allSlots = ItemParser::parseInventoryKeepSlots($data);
        if (empty($allSlots)) return [];

        // Split into pages of 45 slots (5 rows × 9 cols, like a double chest)
        $slotsPerPage = 45;
        $pages = array_chunk($allSlots, $slotsPerPage);
        $result = [];

        foreach ($pages as $i => $pageSlots) {
            $result[] = [
                'page'  => $i,
                'items' => $pageSlots,
                'count' => count(array_filter($pageSlots, fn($s) => $s !== null)),
            ];
        }

        return $result;
    }

    // ─── Personal Vault ───────────────────────────────────────────────

    private function parsePersonalVault(array $member): array
    {
        $data = $member['inventory']['personal_vault_contents']['data'] ?? null;
        if (! $data) return [];

        return ItemParser::parseInventoryKeepSlots($data);
    }

    // ─── Bag Contents (fishing bag, potion bag, quiver) ───────────────

    private function parseBagContents(array $member, string $bagName): array
    {
        $data = $member['inventory']['bag_contents'][$bagName]['data'] ?? null;
        if (! $data) return [];

        return ItemParser::parseInventoryKeepSlots($data);
    }

    // ─── Candy Bag (shared inventory) ─────────────────────────────────

    private function parseCandyBag(array $member, array $profile): array
    {
        $data = $profile['shared_inventory']['candy_inventory_contents']['data'] ?? null;
        if (! $data) return [];

        return ItemParser::parseInventory($data);
    }

    // ─── Backpack Storage (like SkyCrypt's storage) ───────────────────

    private function parseBackpackStorage(array $member): array
    {
        $backpackContents = $member['inventory']['backpack_contents'] ?? null;
        $backpackIcons    = $member['inventory']['backpack_icons'] ?? null;

        if (! $backpackContents || ! is_array($backpackContents)) {
            return [];
        }

        $storageSize = max(18, count($backpackContents));
        $storage     = [];

        for ($slot = 0; $slot < $storageSize; $slot++) {
            $slotKey = (string) $slot;

            if (! isset($backpackContents[$slotKey])) {
                continue;
            }

            $icon  = null;
            $items = [];

            // Parse the backpack icon (the backpack item itself)
            if (isset($backpackIcons[$slotKey]['data'])) {
                $iconItems = ItemParser::parseInventory($backpackIcons[$slotKey]['data']);
                $icon = $iconItems[0] ?? null;
            }

            // Parse the backpack contents (keep empty slots for MC grid)
            if (isset($backpackContents[$slotKey]['data'])) {
                $items = ItemParser::parseInventoryKeepSlots($backpackContents[$slotKey]['data']);
            }

            $storage[] = [
                'slot'  => $slot,
                'icon'  => $icon,
                'items' => $items,
                'count' => count(array_filter($items, fn($i) => $i !== null)),
            ];
        }

        return $storage;
    }

    // ─── Museum ───────────────────────────────────────────────────────

    private function parseMuseum(array $member, array $profile): array
    {
        $museum = $profile['museum'] ?? $member['museum'] ?? null;
        if (! $museum) return [];

        $result = [
            'value'     => $museum['value'] ?? 0,
            'appraisal' => $museum['appraisal'] ?? false,
            'items'     => [],
            'special'   => [],
        ];

        // Parse donated items
        if (isset($museum['items']) && is_array($museum['items'])) {
            foreach ($museum['items'] as $id => $data) {
                $item = [
                    'id'           => $id,
                    'donated_time' => $data['donated_time'] ?? null,
                    'borrowing'    => $data['borrowing'] ?? false,
                ];

                if (isset($data['items']['data'])) {
                    $parsed = ItemParser::parseInventory($data['items']['data']);
                    $item['data'] = $parsed;
                }

                $result['items'][] = $item;
            }
        }

        // Parse special items
        if (isset($museum['special']) && is_array($museum['special'])) {
            foreach ($museum['special'] as $id => $data) {
                $item = [
                    'id'           => $id,
                    'donated_time' => $data['donated_time'] ?? null,
                    'borrowing'    => $data['borrowing'] ?? false,
                ];

                if (isset($data['items']['data'])) {
                    $parsed = ItemParser::parseInventory($data['items']['data']);
                    $item['data'] = $parsed;
                }

                $result['special'][] = $item;
            }
        }

        return $result;
    }

    // ─── Rift Inventory ───────────────────────────────────────────────

    private function parseRiftInventory(array $member): array
    {
        $data = $member['rift']['inventory']['inv_contents']['data'] ?? null;
        if (! $data) return [];

        return ItemParser::parseInventoryKeepSlots($data);
    }

    // ─── Rift Ender Chest ─────────────────────────────────────────────

    private function parseRiftEnderchest(array $member): array
    {
        $data = $member['rift']['inventory']['ender_chest_contents']['data'] ?? null;
        if (! $data) return [];

        return ItemParser::parseInventoryKeepSlots($data);
    }

    private static function isWeapon(array $item): bool
    {
        $cat = $item['category'] ?? null;
        if ($cat && in_array($cat, ['SWORD', 'BOW', 'WAND', 'AXE', 'LONGSWORD', 'FISHING WEAPON', 'DRILL', 'GAUNTLET'])) {
            return true;
        }

        // Fallback: check SkyBlock ID for weapon keywords
        $id = strtolower($item['skyblock_id'] ?? '');
        foreach (['sword', 'bow', 'katana', 'blade', 'scythe', 'staff', 'wand', 'aurora'] as $kw) {
            if (str_contains($id, $kw)) return true;
        }

        return false;
    }

    // ─── Pets ─────────────────────────────────────────────────────────

    private function parsePets(array $member): array
    {
        $pets   = $member['pets_data']['pets'] ?? [];
        $result = [];

        foreach ($pets as $pet) {
            $result[] = [
                'type'      => $pet['type'] ?? 'UNKNOWN',
                'tier'      => $pet['tier'] ?? 'COMMON',
                'xp'        => $pet['exp'] ?? 0,
                'active'    => $pet['active'] ?? false,
                'heldItem'  => $pet['heldItem'] ?? null,
                'skin'      => $pet['skin'] ?? null,
                'candyUsed' => $pet['candyUsed'] ?? 0,
            ];
        }

        // Active first, then by XP desc
        usort($result, function ($a, $b) {
            if ($a['active'] && ! $b['active']) return -1;
            if (! $a['active'] && $b['active']) return 1;
            return $b['xp'] <=> $a['xp'];
        });

        return $result;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Detailed XP → level with progress info (for progress bars).
     */
    private function xpToLevelDetailed(float $xp, array $table, int $maxLevel): array
    {
        $level = 0;
        $cap   = min($maxLevel, count($table) - 1);

        for ($i = $cap; $i >= 0; $i--) {
            if ($xp >= $table[$i]) {
                $level = $i;
                break;
            }
        }

        if ($level >= $cap) {
            return [
                'level'     => $level,
                'xpCurrent' => round($xp - ($table[$level] ?? 0), 2),
                'xpForNext' => 0,
                'progress'  => 1,
            ];
        }

        $xpCurrent = $xp - $table[$level];
        $xpForNext = ($table[$level + 1] ?? $table[$level]) - $table[$level];
        $progress  = $xpForNext > 0 ? min($xpCurrent / $xpForNext, 1) : 1;

        return [
            'level'     => $level,
            'xpCurrent' => round($xpCurrent, 2),
            'xpForNext' => round($xpForNext, 2),
            'progress'  => round($progress, 4),
        ];
    }

    /**
     * Convert XP to level using an XP table (simple integer return).
     */
    private function xpToLevel(float $xp, array $table, int $maxLevel): int
    {
        $level = 0;
        $cap   = min($maxLevel, count($table) - 1);

        for ($i = $cap; $i >= 0; $i--) {
            if ($xp >= $table[$i]) {
                $level = $i;
                break;
            }
        }

        return $level;
    }

    /**
     * Resolve Minecraft username → UUID via Mojang API.
     */
    private function getUuidFromMojang(string $username): ?string
    {
        try {
            $resp = Http::timeout(10)->get(
                'https://api.mojang.com/users/profiles/minecraft/' . urlencode($username)
            );

            if (! $resp->successful()) {
                return null;
            }

            return $resp->json('id') ?: null;
        } catch (\Exception $e) {
            Log::warning('Mojang API exception', [
                'username'  => $username,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ─── Cache helpers ───────────────────────────────────────────────

    private function cacheGet(string $key): mixed
    {
        $store = config('cache.default', 'file');
        try {
            return Cache::store($store)->get($key);
        } catch (\Throwable $e) {
            Log::warning('Cache get failed, trying file store', ['exception' => $e->getMessage()]);
            try {
                return Cache::store('file')->get($key);
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function cachePut(string $key, mixed $value): void
    {
        $store = config('cache.default', 'file');
        try {
            Cache::store($store)->put($key, $value, self::CACHE_TTL);
        } catch (\Throwable $e) {
            Log::warning('Cache put failed, trying file store', ['exception' => $e->getMessage()]);
            try {
                Cache::store('file')->put($key, $value, self::CACHE_TTL);
            } catch (\Throwable) {
                // silently fail
            }
        }
    }

    /**
     * Recursively sanitize all strings in an array to valid UTF-8
     * so json_encode won't throw "Malformed UTF-8" errors.
     */
    private function sanitizeForJson(mixed $data): mixed
    {
        if (is_string($data)) {
            if (mb_check_encoding($data, 'UTF-8')) {
                return $data;
            }
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }

        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $safeKey = is_string($key) ? $this->sanitizeForJson($key) : $key;
                $result[$safeKey] = $this->sanitizeForJson($value);
            }
            return $result;
        }

        return $data;
    }

    // ─── Player Stats (SkyCrypt-style aggregation) ──────────────────

    /**
     * Calculate total player stats from base, skills, fairy souls, and equipped gear.
     * Mirrors SkyCrypt's stat aggregation approach.
     */
    private function calculatePlayerStats(array $member, array $skills, array $armor, array $equipment, array $accessories): array
    {
        // Base stats every player starts with
        $stats = [
            'Health'            => 100,
            'Defense'           => 0,
            'Strength'          => 0,
            'Speed'             => 100,
            'Critical Chance'   => 30,
            'Critical Damage'   => 50,
            'Intelligence'      => 0,
            'Attack Speed'      => 0,
            'Ability Damage'    => 0,
            'Magic Find'        => 0,
            'Pet Luck'          => 0,
            'True Defense'      => 0,
            'Ferocity'          => 0,
            'Sea Creature Chance'=> 20,
            'Health Regen'      => 100,
            'Vitality'          => 0,
            'Mending'           => 0,
            'Fishing Speed'     => 0,
            'Mining Speed'      => 0,
            'Mining Fortune'    => 0,
            'Farming Fortune'   => 0,
            'Foraging Fortune'  => 0,
        ];

        // ── Skill bonuses (simplified SkyCrypt logic) ──
        $skillStatBonuses = [
            'farming'     => ['Health' => 2, 'Farming Fortune' => 4],
            'mining'      => ['Defense' => 1, 'Mining Fortune' => 4, 'Mining Speed' => 20],
            'combat'      => ['Critical Chance' => 0.5, 'Critical Damage' => 0],  // CD comes from separate table
            'foraging'    => ['Strength' => 1, 'Foraging Fortune' => 4],
            'fishing'     => ['Health' => 2, 'Sea Creature Chance' => 0.2],
            'enchanting'  => ['Intelligence' => 1],
            'alchemy'     => ['Intelligence' => 1],
            'taming'      => ['Pet Luck' => 1],
            'carpentry'   => [],
            'runecrafting' => [],
            'social'      => [],
        ];

        foreach ($skills as $name => $skillData) {
            $level = $skillData['level'] ?? 0;
            if (!isset($skillStatBonuses[$name])) continue;

            foreach ($skillStatBonuses[$name] as $statName => $perLevel) {
                $stats[$statName] = ($stats[$statName] ?? 0) + ($perLevel * $level);
            }
        }

        // Combat special: +1 CD per level from 1-50
        $combatLevel = $skills['combat']['level'] ?? 0;
        $stats['Critical Damage'] += $combatLevel;

        // ── Fairy soul bonuses (exchanges) ──
        $fairySouls = $member['fairy_soul']['total_collected'] ?? $member['fairy_exchanges'] ?? 0;
        $exchanges  = (int) floor($fairySouls / 5);

        // Each exchange gives: +3 HP, +1 Def, +1 Str, +1 Spd (approximately, simplified)
        $stats['Health']   += $exchanges * 3;
        $stats['Defense']  += $exchanges * 1;
        $stats['Strength'] += $exchanges * 1;
        $stats['Speed']    += (int) floor($exchanges * 0.5);

        // ── Item stats from armor, equipment, accessories ──
        $this->addItemStatsToTotal($stats, $armor);
        $this->addItemStatsToTotal($stats, $equipment);
        $this->addItemStatsToTotal($stats, $accessories);

        // Convert to frontend format with icons and colors
        return $this->formatPlayerStats($stats);
    }

    /**
     * Sum item stats into total stats array.
     */
    private function addItemStatsToTotal(array &$totals, array $items): void
    {
        // Mapping from abbreviation back to full stat name
        $abbrevToFull = [
            'HP'    => 'Health',
            'Def'   => 'Defense',
            'Str'   => 'Strength',
            'Spd'   => 'Speed',
            'CC'    => 'Critical Chance',
            'CD'    => 'Critical Damage',
            'Int'   => 'Intelligence',
            'AS'    => 'Attack Speed',
            'AD'    => 'Ability Damage',
            'MF'    => 'Magic Find',
            'PL'    => 'Pet Luck',
            'TD'    => 'True Defense',
            'FS'    => 'Ferocity',
            'SCC'   => 'Sea Creature Chance',
            'HPR'   => 'Health Regen',
            'Vit'   => 'Vitality',
            'Mnd'   => 'Mending',
            'FshSpd'=> 'Fishing Speed',
            'MnSpd' => 'Mining Speed',
            'MnFrt' => 'Mining Fortune',
            'FmFrt' => 'Farming Fortune',
            'FgFrt' => 'Foraging Fortune',
            'Dmg'   => 'Damage',
        ];

        foreach ($items as $item) {
            if (!$item || !isset($item['stats'])) continue;
            foreach ($item['stats'] as $abbrev => $stat) {
                $fullName = $abbrevToFull[$abbrev] ?? null;
                if (!$fullName) continue;
                if (!isset($totals[$fullName])) $totals[$fullName] = 0;
                $totals[$fullName] += $stat['value'] ?? 0;
            }
        }
    }

    /**
     * Format stats for frontend display with SkyCrypt-style icons and colors.
     */
    private function formatPlayerStats(array $stats): array
    {
        $statConfig = [
            'Health'             => ['icon' => '❤', 'color' => '#FF5555'],
            'Defense'            => ['icon' => '🛡', 'color' => '#55FF55'],
            'Strength'           => ['icon' => '💪', 'color' => '#FF5555'],
            'Speed'              => ['icon' => '✈', 'color' => '#FFFFFF'],
            'Critical Chance'    => ['icon' => '☠', 'color' => '#FF5555', 'suffix' => '%'],
            'Critical Damage'    => ['icon' => '☠', 'color' => '#FF5555', 'suffix' => '%'],
            'Intelligence'       => ['icon' => '✨', 'color' => '#55FFFF'],
            'Attack Speed'       => ['icon' => '⚡', 'color' => '#FFFF55', 'suffix' => '%'],
            'Ability Damage'     => ['icon' => '🔥', 'color' => '#FF5555', 'suffix' => '%'],
            'Magic Find'         => ['icon' => '⭐', 'color' => '#55FFFF'],
            'Pet Luck'           => ['icon' => '♣', 'color' => '#FF55FF'],
            'True Defense'       => ['icon' => '◎', 'color' => '#FFFFFF'],
            'Ferocity'           => ['icon' => '⚔', 'color' => '#FF5555'],
            'Sea Creature Chance'=> ['icon' => '🌊', 'color' => '#55FFFF', 'suffix' => '%'],
            'Health Regen'       => ['icon' => '❤', 'color' => '#FF5555'],
            'Vitality'           => ['icon' => '🍀', 'color' => '#FF55FF'],
            'Mending'            => ['icon' => '❤', 'color' => '#55FF55'],
            'Fishing Speed'      => ['icon' => '🎣', 'color' => '#55FFFF'],
            'Mining Speed'       => ['icon' => '⛏', 'color' => '#FFFF55'],
            'Mining Fortune'     => ['icon' => '⛏', 'color' => '#FFAA00'],
            'Farming Fortune'    => ['icon' => '🌾', 'color' => '#FFAA00'],
            'Foraging Fortune'   => ['icon' => '🌲', 'color' => '#FFAA00'],
        ];

        $result = [];
        foreach ($stats as $name => $value) {
            $value = (int) round($value);
            if ($value === 0 && !in_array($name, ['Health', 'Defense', 'Speed', 'Critical Chance', 'Critical Damage'])) {
                continue;  // Skip zero-value non-essential stats
            }
            $cfg = $statConfig[$name] ?? ['icon' => '•', 'color' => '#AAAAAA'];
            $result[] = [
                'name'   => $name,
                'value'  => $value,
                'icon'   => $cfg['icon'],
                'color'  => $cfg['color'],
                'suffix' => $cfg['suffix'] ?? '',
            ];
        }
        return $result;
    }
}
