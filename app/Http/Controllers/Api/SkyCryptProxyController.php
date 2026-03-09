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

        // ── Transform into front-end format ──────────────────────────
        $data = $this->transformProfiles($rawProfiles, $uuid, $username);

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
    private function transformProfiles(array $rawProfiles, string $uuid, string $username): array
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
                    'networth'   => $this->parseNetworth($member, $profile),
                    'pets'       => $this->parsePets($member),
                    'armor'      => $this->parseArmor($member),
                    'equipment'  => $this->parseEquipment($member),
                    'wardrobe'   => $this->parseWardrobe($member),
                    'weapons'    => $this->parseWeapons($member),
                    'accessories'=> $this->parseAccessories($member),
                    'talisman_bag'   => $this->parseBagContents($member, 'talisman_bag'),
                    'inventory'  => $this->parsePlayerInventory($member),
                    'enderchest' => $this->parseEnderChest($member),
                    'personal_vault' => $this->parsePersonalVault($member),
                    'fishing_bag'    => $this->parseBagContents($member, 'fishing_bag'),
                    'potion_bag'     => $this->parseBagContents($member, 'potion_bag'),
                    'quiver'         => $this->parseBagContents($member, 'quiver'),
                    'candy_bag'      => $this->parseCandyBag($member, $profile),
                    'storage'        => $this->parseBackpackStorage($member),
                    'museum'         => $this->parseMuseum($member, $profile),
                    'rift_inventory' => $this->parseRiftInventory($member),
                    'rift_enderchest'=> $this->parseRiftEnderchest($member),
                    'accessory_bag_storage' => $this->parseAccessoryBagStorage($member),
                    'wardrobe_slot' => $member['inventory']['wardrobe_equipped_slot'] ?? null,
                    'inv_disabled'  => empty($member['inventory']['inv_contents']['data'] ?? null),
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
            'enchanting', 'alchemy', 'carpentry', 'taming', 'runecrafting', 'social',
        ];

        foreach ($skillNames as $name) {
            $key = 'SKILL_' . strtoupper($name);
            $xp  = $experience[$key] ?? 0;

            $maxLevel = in_array($name, ['runecrafting', 'social']) ? 25 : 60;
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
                'progress' => $classDetail['progress'],
            ];
        }

        return [
            'catacombs' => [
                'level' => [
                    'level'     => $detail['level'],
                    'xp'        => $xp,
                    'xpCurrent' => $detail['xpCurrent'],
                    'xpForNext' => $detail['xpForNext'],
                    'progress'  => $detail['progress'],
                ],
            ],
            'secrets_found'  => $dungeons['secrets'] ?? 0,
            'classes'        => $classes,
            'selected_class' => $dungeons['selected_dungeon_class'] ?? null,
        ];
    }

    // ─── Networth (basic: purse + bank) ──────────────────────────────

    private function parseNetworth(array $member, array $profile): array
    {
        $purse = $member['currencies']['coin_purse'] ?? 0;
        $bank  = $profile['banking']['balance'] ?? 0;

        return [
            'networth' => $purse + $bank,
            'purse'    => $purse,
            'bank'     => $bank,
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
}
