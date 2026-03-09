<?php

namespace App\Utils;

/**
 * Parses Hypixel SkyBlock item data from NBT into a frontend-friendly format.
 *
 * Extracts item names, lore (with Minecraft color codes → HTML), rarity,
 * skull textures, enchantments, and other metadata.
 */
class ItemParser
{
    /** Rarity order for sorting (higher = rarer). */
    private const RARITY_ORDER = [
        'COMMON'       => 0,
        'UNCOMMON'     => 1,
        'RARE'         => 2,
        'EPIC'         => 3,
        'LEGENDARY'    => 4,
        'MYTHIC'       => 5,
        'DIVINE'       => 6,
        'SPECIAL'      => 7,
        'VERY_SPECIAL' => 8,
    ];

    /** Minecraft color code → hex color for HTML rendering. */
    private const MC_COLORS = [
        '0' => '#000000', '1' => '#0000AA', '2' => '#00AA00', '3' => '#00AAAA',
        '4' => '#AA0000', '5' => '#AA00AA', '6' => '#FFAA00', '7' => '#AAAAAA',
        '8' => '#555555', '9' => '#5555FF', 'a' => '#55FF55', 'b' => '#55FFFF',
        'c' => '#FF5555', 'd' => '#FF55FF', 'e' => '#FFFF55', 'f' => '#FFFFFF',
    ];

    /** Rarity strings that appear in lore (ordered most specific → least). */
    private const RARITIES = [
        'VERY SPECIAL', 'SPECIAL', 'DIVINE', 'MYTHIC',
        'LEGENDARY', 'EPIC', 'RARE', 'UNCOMMON', 'COMMON',
    ];

    /**
     * Comprehensive Minecraft 1.8.9 numeric item IDs → item texture name.
     * Matches SkyCrypt's minecraft-data approach.
     * Format: id => name  OR  "id:damage" => name (for damage variants)
     */
    private const MC_ITEM_TEXTURES = [
        // Blocks
        1   => 'stone',
        2   => 'grass_block',
        3   => 'dirt',
        4   => 'cobblestone',
        5   => 'oak_planks',
        6   => 'oak_sapling',
        7   => 'bedrock',
        12  => 'sand',
        13  => 'gravel',
        14  => 'gold_ore',
        15  => 'iron_ore',
        16  => 'coal_ore',
        17  => 'oak_log',
        18  => 'oak_leaves',
        19  => 'sponge',
        20  => 'glass',
        21  => 'lapis_ore',
        22  => 'lapis_block',
        24  => 'sandstone',
        30  => 'cobweb',
        35  => 'white_wool',
        37  => 'dandelion',
        38  => 'poppy',
        39  => 'brown_mushroom',
        40  => 'red_mushroom',
        41  => 'gold_block',
        42  => 'iron_block',
        44  => 'stone_slab',
        45  => 'bricks',
        46  => 'tnt',
        47  => 'bookshelf',
        48  => 'mossy_cobblestone',
        49  => 'obsidian',
        50  => 'torch',
        52  => 'spawner',
        54  => 'chest',
        56  => 'diamond_ore',
        57  => 'diamond_block',
        58  => 'crafting_table',
        60  => 'farmland',
        61  => 'furnace',
        65  => 'ladder',
        66  => 'rail',
        69  => 'lever',
        73  => 'redstone_ore',
        76  => 'redstone_torch',
        79  => 'ice',
        80  => 'snow_block',
        81  => 'cactus',
        82  => 'clay',
        84  => 'jukebox',
        86  => 'pumpkin',
        87  => 'netherrack',
        88  => 'soul_sand',
        89  => 'glowstone',
        91  => 'jack_o_lantern',
        95  => 'white_stained_glass',
        98  => 'stone_bricks',
        102 => 'glass_pane',
        103 => 'melon',
        106 => 'vine',
        110 => 'mycelium',
        111 => 'lily_pad',
        112 => 'nether_bricks',
        120 => 'end_portal_frame',
        121 => 'end_stone',
        122 => 'dragon_egg',
        129 => 'emerald_ore',
        130 => 'ender_chest',
        133 => 'emerald_block',
        137 => 'command_block',
        138 => 'beacon',
        152 => 'redstone_block',
        153 => 'nether_quartz_ore',
        155 => 'quartz_block',
        159 => 'white_terracotta',
        160 => 'white_stained_glass_pane',
        165 => 'slime_block',
        168 => 'prismarine',
        169 => 'sea_lantern',
        170 => 'hay_block',
        172 => 'terracotta',
        173 => 'coal_block',
        174 => 'packed_ice',

        // Tools
        256 => 'iron_shovel',
        257 => 'iron_pickaxe',
        258 => 'iron_axe',
        259 => 'flint_and_steel',
        260 => 'apple',
        261 => 'bow',
        262 => 'arrow',
        263 => 'coal',
        264 => 'diamond',
        265 => 'iron_ingot',
        266 => 'gold_ingot',
        267 => 'iron_sword',
        268 => 'wooden_sword',
        269 => 'wooden_shovel',
        270 => 'wooden_pickaxe',
        271 => 'wooden_axe',
        272 => 'stone_sword',
        273 => 'stone_shovel',
        274 => 'stone_pickaxe',
        275 => 'stone_axe',
        276 => 'diamond_sword',
        277 => 'diamond_shovel',
        278 => 'diamond_pickaxe',
        279 => 'diamond_axe',
        280 => 'stick',
        281 => 'bowl',
        283 => 'golden_sword',
        284 => 'golden_shovel',
        285 => 'golden_pickaxe',
        286 => 'golden_axe',
        287 => 'string',
        288 => 'feather',
        289 => 'gunpowder',
        290 => 'wooden_hoe',
        291 => 'stone_hoe',
        292 => 'iron_hoe',
        293 => 'diamond_hoe',
        294 => 'golden_hoe',
        295 => 'wheat_seeds',
        296 => 'wheat',
        297 => 'bread',

        // Armor
        298 => 'leather_helmet',
        299 => 'leather_chestplate',
        300 => 'leather_leggings',
        301 => 'leather_boots',
        302 => 'chainmail_helmet',
        303 => 'chainmail_chestplate',
        304 => 'chainmail_leggings',
        305 => 'chainmail_boots',
        306 => 'iron_helmet',
        307 => 'iron_chestplate',
        308 => 'iron_leggings',
        309 => 'iron_boots',
        310 => 'diamond_helmet',
        311 => 'diamond_chestplate',
        312 => 'diamond_leggings',
        313 => 'diamond_boots',
        314 => 'golden_helmet',
        315 => 'golden_chestplate',
        316 => 'golden_leggings',
        317 => 'golden_boots',

        // Items
        318 => 'flint',
        319 => 'raw_porkchop',
        320 => 'cooked_porkchop',
        322 => 'golden_apple',
        323 => 'sign',
        324 => 'oak_door',
        325 => 'bucket',
        328 => 'minecart',
        329 => 'saddle',
        330 => 'iron_door',
        331 => 'redstone',
        332 => 'snowball',
        334 => 'leather',
        336 => 'brick',
        337 => 'clay_ball',
        338 => 'sugar_cane',
        339 => 'paper',
        340 => 'book',
        341 => 'slime_ball',
        344 => 'egg',
        345 => 'compass',
        346 => 'fishing_rod',
        347 => 'clock',
        348 => 'glowstone_dust',
        349 => 'raw_fish',
        350 => 'cooked_fish',
        351 => 'ink_sac',
        352 => 'bone',
        353 => 'sugar',
        354 => 'cake',
        355 => 'bed',
        356 => 'redstone_repeater',
        357 => 'cookie',
        358 => 'filled_map',
        359 => 'shears',
        360 => 'melon_slice',
        361 => 'pumpkin_seeds',
        362 => 'melon_seeds',
        363 => 'raw_beef',
        364 => 'cooked_beef',
        365 => 'raw_chicken',
        366 => 'cooked_chicken',
        367 => 'rotten_flesh',
        368 => 'ender_pearl',
        369 => 'blaze_rod',
        370 => 'ghast_tear',
        371 => 'gold_nugget',
        372 => 'nether_wart',
        373 => 'potion',
        374 => 'glass_bottle',
        375 => 'spider_eye',
        376 => 'fermented_spider_eye',
        377 => 'blaze_powder',
        378 => 'magma_cream',
        379 => 'brewing_stand',
        380 => 'cauldron',
        381 => 'ender_eye',
        382 => 'glistering_melon_slice',
        384 => 'bottle_o_enchanting',
        385 => 'fire_charge',
        386 => 'writable_book',
        387 => 'written_book',
        388 => 'emerald',
        389 => 'item_frame',
        390 => 'flower_pot',
        391 => 'carrot',
        392 => 'potato',
        393 => 'baked_potato',
        394 => 'poisonous_potato',
        395 => 'empty_map',
        396 => 'golden_carrot',
        397 => 'skull',
        399 => 'nether_star',
        400 => 'pumpkin_pie',
        401 => 'firework_rocket',
        402 => 'firework_star',
        403 => 'enchanted_book',
        404 => 'redstone_comparator',
        405 => 'nether_brick',
        406 => 'quartz',
        407 => 'tnt_minecart',
        408 => 'hopper_minecart',
        409 => 'prismarine_shard',
        410 => 'prismarine_crystals',
        411 => 'raw_rabbit',
        412 => 'cooked_rabbit',
        413 => 'rabbit_stew',
        414 => 'rabbit_foot',
        415 => 'rabbit_hide',
        416 => 'armor_stand',
        417 => 'iron_horse_armor',
        418 => 'golden_horse_armor',
        419 => 'diamond_horse_armor',
        420 => 'lead',
        421 => 'name_tag',
        422 => 'command_block_minecart',
        423 => 'raw_mutton',
        424 => 'cooked_mutton',
        425 => 'banner',
        427 => 'spruce_door',
        428 => 'birch_door',
        429 => 'jungle_door',
        430 => 'acacia_door',
        431 => 'dark_oak_door',
        2256 => 'music_disc_13',
        2257 => 'music_disc_cat',
    ];

    /**
     * Damage variants for dye colors and similar.
     */
    private const MC_DAMAGE_VARIANTS = [
        '349:1'  => 'raw_salmon',
        '349:2'  => 'clownfish',
        '349:3'  => 'pufferfish',
        '350:1'  => 'cooked_salmon',
        '263:1'  => 'charcoal',
        '6:1'    => 'spruce_sapling',
        '6:2'    => 'birch_sapling',
        '6:3'    => 'jungle_sapling',
        '6:4'    => 'acacia_sapling',
        '6:5'    => 'dark_oak_sapling',
        '351:1'  => 'red_dye',
        '351:2'  => 'green_dye',
        '351:3'  => 'cocoa_beans',
        '351:4'  => 'lapis_lazuli',
        '351:5'  => 'purple_dye',
        '351:6'  => 'cyan_dye',
        '351:7'  => 'light_gray_dye',
        '351:8'  => 'gray_dye',
        '351:9'  => 'pink_dye',
        '351:10' => 'lime_dye',
        '351:11' => 'yellow_dye',
        '351:12' => 'light_blue_dye',
        '351:13' => 'magenta_dye',
        '351:14' => 'orange_dye',
        '351:15' => 'bone_meal',
        '322:1'  => 'enchanted_golden_apple',
        '397:1'  => 'wither_skeleton_skull',
        '397:2'  => 'zombie_head',
        '397:3'  => 'player_head',
        '397:4'  => 'creeper_head',
    ];

    /** Potion damage → color mapping (like SkyCrypt). */
    private const POTION_COLORS = [
        '0066ff', '7f00ff', '4c9331', '993300', '0000ff',
        'ff0000', '808080', '1f1f9b', '804000', 'ff0000',
        '00ff00', 'ffff00', '00ccff', 'ff00ff', 'ff6600', 'ffffff',
    ];

    // ═══════════════════════════════════════════════════════════════════
    //  Public API
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Parse inventory base64 data into an array of items.
     * Returns only non-empty slots.
     */
    public static function parseInventory(?string $base64): array
    {
        if (empty($base64)) {
            return [];
        }

        $nbt = NbtParser::parseBase64Gzip($base64);
        if ($nbt === null || ! isset($nbt['i']) || ! is_array($nbt['i'])) {
            return [];
        }

        $items = [];
        foreach ($nbt['i'] as $slot => $itemNbt) {
            if (! is_array($itemNbt) || empty($itemNbt)) {
                continue;
            }
            $item = self::parseItem($itemNbt, $slot);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Parse inventory base64 data preserving slot positions (null for empty).
     */
    public static function parseInventoryKeepSlots(?string $base64): array
    {
        if (empty($base64)) {
            return [];
        }

        $nbt = NbtParser::parseBase64Gzip($base64);
        if ($nbt === null || ! isset($nbt['i']) || ! is_array($nbt['i'])) {
            return [];
        }

        $items = [];
        foreach ($nbt['i'] as $slot => $itemNbt) {
            if (! is_array($itemNbt) || empty($itemNbt) || ! isset($itemNbt['id'])) {
                $items[] = null;
            } else {
                $items[] = self::parseItem($itemNbt, $slot);
            }
        }

        return $items;
    }

    /**
     * Get rarity sort order (higher = rarer).
     */
    public static function rarityOrder(string $rarity): int
    {
        return self::RARITY_ORDER[$rarity] ?? 0;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Item parsing
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Parse a single NBT item compound into frontend-friendly format.
     */
    private static function parseItem(array $nbt, int $slot): ?array
    {
        if (! isset($nbt['id'])) {
            return null;
        }

        $tag             = $nbt['tag'] ?? [];
        $display         = $tag['display'] ?? [];
        $extraAttributes = $tag['ExtraAttributes'] ?? [];

        // ── Name ─────────────────────────────────────────────────
        $rawName = self::sanitizeUtf8($display['Name'] ?? '');
        $name    = self::stripColorCodes($rawName);
        if (empty(trim($name))) {
            return null;
        }

        // ── Lore ─────────────────────────────────────────────────
        $rawLore = $display['Lore'] ?? [];
        if (! is_array($rawLore)) {
            $rawLore = [];
        }
        $rawLore  = array_map([self::class, 'sanitizeUtf8'], $rawLore);
        $lore     = array_map([self::class, 'stripColorCodes'], $rawLore);
        $loreHtml = array_map([self::class, 'colorCodeToHtml'], $rawLore);

        // ── Rarity ───────────────────────────────────────────────
        $rarity = self::extractRarity($rawLore);

        // ── Item category (from last lore line) ──────────────────
        $category = self::extractCategory($rawLore);

        // ── IDs ──────────────────────────────────────────────────
        $skyblockId  = $extraAttributes['id'] ?? null;
        $minecraftId = is_int($nbt['id']) ? $nbt['id'] : (is_string($nbt['id']) ? $nbt['id'] : 0);
        $damage      = $nbt['Damage'] ?? 0;
        $count       = $nbt['Count'] ?? 1;

        // ── Skull texture ────────────────────────────────────────
        $textureHash = self::extractSkullTexture($tag);

        // ── Enchantments ─────────────────────────────────────────
        $enchantments = [];
        if (isset($extraAttributes['enchantments']) && is_array($extraAttributes['enchantments'])) {
            $enchantments = $extraAttributes['enchantments'];
        }

        // ── Other attributes ─────────────────────────────────────
        $reforge       = $extraAttributes['modifier'] ?? null;
        $stars         = $extraAttributes['upgrade_level'] ?? $extraAttributes['dungeon_item_level'] ?? 0;
        $recombobulated = isset($extraAttributes['rarity_upgrades']) && $extraAttributes['rarity_upgrades'] > 0;
        $hotPotatoCount = ($extraAttributes['hot_potato_count'] ?? 0);

        // ── Leather armor color ──────────────────────────────────
        $color = null;
        if (isset($display['color']) && is_int($display['color'])) {
            $c     = $display['color'];
            $color = sprintf('#%02x%02x%02x', ($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF);
        }

        // ── Texture path (like SkyCrypt) ─────────────────────────
        $texturePath = self::resolveTexturePath($minecraftId, $damage, $textureHash, $color, $extraAttributes);

        // ── Item stats from lore ─────────────────────────────────
        $stats = self::extractStats($rawLore);

        return [
            'slot'           => $slot,
            'name'           => $name,
            'skyblock_id'    => $skyblockId,
            'minecraft_id'   => $minecraftId,
            'damage'         => $damage,
            'count'          => $count,
            'rarity'         => $rarity,
            'category'       => $category,
            'lore'           => $lore,
            'lore_html'      => $loreHtml,
            'texture_hash'   => $textureHash,
            'texture_path'   => $texturePath,
            'enchantments'   => $enchantments,
            'reforge'        => $reforge,
            'stars'          => $stars,
            'recombobulated' => $recombobulated,
            'hot_potato'     => $hotPotatoCount,
            'color'          => $color,
            'stats'          => $stats,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Extraction helpers
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Extract skull texture hash from item NBT.
     * Path: tag → SkullOwner → Properties → textures[0] → Value (base64 JSON with URL).
     */
    private static function extractSkullTexture(array $tag): ?string
    {
        $textures = $tag['SkullOwner']['Properties']['textures'] ?? null;
        if (! $textures || ! is_array($textures) || empty($textures)) {
            return null;
        }

        $first = $textures[0] ?? null;
        if (! is_array($first) || ! isset($first['Value'])) {
            return null;
        }

        $decoded = @base64_decode($first['Value'], true);
        if (! $decoded) {
            return null;
        }

        $json = @json_decode($decoded, true);
        if (! $json) {
            return null;
        }

        $url = $json['textures']['SKIN']['url'] ?? null;
        if (! $url) {
            return null;
        }

        // Extract hash: http://textures.minecraft.net/texture/{hash}
        if (preg_match('/\/texture\/([a-f0-9]+)$/i', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract rarity from lore lines.
     * The last non-empty lore line typically contains "LEGENDARY SWORD", "EPIC ACCESSORY", etc.
     */
    private static function extractRarity(array $rawLore): string
    {
        for ($i = count($rawLore) - 1; $i >= max(0, count($rawLore) - 5); $i--) {
            $line  = self::stripColorCodes($rawLore[$i] ?? '');
            $upper = strtoupper(trim($line));

            foreach (self::RARITIES as $rarity) {
                if (str_contains($upper, $rarity)) {
                    return str_replace(' ', '_', $rarity);
                }
            }
        }

        return 'COMMON';
    }

    /**
     * Extract item category from the last lore line.
     * e.g. "LEGENDARY SWORD" → "SWORD", "EPIC HELMET" → "HELMET"
     */
    private static function extractCategory(array $rawLore): ?string
    {
        for ($i = count($rawLore) - 1; $i >= max(0, count($rawLore) - 3); $i--) {
            $line  = self::stripColorCodes($rawLore[$i] ?? '');
            $upper = strtoupper(trim($line));

            // Remove rarity prefix and any ✦ symbols or extra markers
            $cleaned = preg_replace('/^(VERY SPECIAL|SPECIAL|DIVINE|MYTHIC|LEGENDARY|EPIC|RARE|UNCOMMON|COMMON)\s*/i', '', $upper);
            $cleaned = preg_replace('/[✦⚚◆]+/', '', $cleaned);
            $cleaned = trim($cleaned);

            $categories = [
                'SWORD', 'BOW', 'WAND', 'AXE', 'PICKAXE', 'SHOVEL', 'HOE',
                'FISHING ROD', 'FISHING WEAPON',
                'HELMET', 'CHESTPLATE', 'LEGGINGS', 'BOOTS',
                'NECKLACE', 'CLOAK', 'BELT', 'GLOVES', 'BRACELET',
                'ACCESSORY', 'HATCESSORY', 'DUNGEON ACCESSORY',
                'DRILL', 'GAUNTLET', 'LONGSWORD',
                'DEPLOYABLE', 'COSMETIC', 'TRAVEL SCROLL', 'REFORGE STONE',
                'PET ITEM', 'POWER STONE',
            ];

            foreach ($categories as $cat) {
                if (str_contains($cleaned, $cat)) {
                    return $cat;
                }
            }
        }

        return null;
    }

    /**
     * Extract stat bonuses from lore lines.
     * Matches patterns like "§7Damage: §c+100" or "§7Critical Chance: §c+15%"
     */
    private static function extractStats(array $rawLore): array
    {
        $stats = [];

        foreach ($rawLore as $line) {
            $clean = self::stripColorCodes($line);

            // Match: "StatName: +/-123.4" or "StatName: +/-123.4%"
            if (preg_match('/^([A-Za-z ]+):\s*([+-]?[\d,.]+)(%?)/', trim($clean), $m)) {
                $statName = trim($m[1]);
                $value    = (float) str_replace(',', '', $m[2]);
                $isPercent = $m[3] === '%';

                // Normalize stat names to abbreviations
                $key = self::statAbbreviation($statName);
                if ($key) {
                    $stats[$key] = [
                        'value'   => $value,
                        'percent' => $isPercent,
                    ];
                }
            }
        }

        return $stats;
    }

    /**
     * Map full stat name to abbreviation (like SkyCrypt).
     */
    private static function statAbbreviation(string $name): ?string
    {
        return match (strtolower(trim($name))) {
            'damage'                  => 'Dmg',
            'strength'                => 'Str',
            'critical chance'         => 'CC',
            'crit chance'             => 'CC',
            'critical damage'         => 'CD',
            'crit damage'             => 'CD',
            'attack speed'            => 'AS',
            'health'                  => 'HP',
            'defense'                 => 'Def',
            'speed'                   => 'Spd',
            'intelligence'            => 'Int',
            'magic find'              => 'MF',
            'pet luck'                => 'PL',
            'true defense'            => 'TD',
            'ferocity'                => 'FS',
            'ability damage'          => 'AD',
            'sea creature chance'     => 'SCC',
            'health regen'            => 'HPR',
            'vitality'                => 'Vit',
            'mending'                 => 'Mnd',
            'bonus attack speed'      => 'AS',
            'mining speed'            => 'MnSpd',
            'mining fortune'          => 'MnFrt',
            'farming fortune'         => 'FmFrt',
            'foraging fortune'        => 'FgFrt',
            'pristine'               => 'Prs',
            'fishing speed'           => 'FshSpd',
            'bonus pest chance'       => 'BPC',
            default                   => null,
        };
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Texture path resolution (like SkyCrypt)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Resolve the texture path for an item (mirrors SkyCrypt's processing.js logic).
     *
     * Returns a path string that the frontend can resolve to a CDN URL:
     *   - "/head/{uuid}" for skull items
     *   - "/leather/{type}/{color}" for leather armor
     *   - "/potion/{normal|splash}/{color}" for potions
     *   - "/item/{name}" for vanilla MC items
     */
    private static function resolveTexturePath(int $minecraftId, int $damage, ?string $textureHash, ?string $color, array $extraAttributes): ?string
    {
        // Skull items — most SB custom items use skull textures
        if ($textureHash) {
            return "/head/{$textureHash}";
        }

        // Leather armor (IDs 298-301): colored texture
        if ($minecraftId >= 298 && $minecraftId <= 301) {
            $type  = ['helmet', 'chestplate', 'leggings', 'boots'][$minecraftId - 298];
            $hex   = $color ? ltrim($color, '#') : '955e3b';
            return "/leather/{$type}/{$hex}";
        }

        // Potions (ID 373)
        if ($minecraftId === 373) {
            $potionColor = self::POTION_COLORS[$damage % 16] ?? '0000ff';
            $type = ($damage & 16384) ? 'splash' : 'normal';
            return "/potion/{$type}/{$potionColor}";
        }

        // Check damage variants first (dye colors, special items)
        $damageKey = "{$minecraftId}:{$damage}";
        if (isset(self::MC_DAMAGE_VARIANTS[$damageKey])) {
            return '/item/' . self::MC_DAMAGE_VARIANTS[$damageKey];
        }

        // Standard item ID lookup
        if (isset(self::MC_ITEM_TEXTURES[$minecraftId])) {
            return '/item/' . self::MC_ITEM_TEXTURES[$minecraftId];
        }

        // Enchanted book special case
        if ($minecraftId === 403) {
            return '/item/enchanted_book';
        }

        return null;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Text processing
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Strip Minecraft color/formatting codes (§X).
     */
    private static function stripColorCodes(string $text): string
    {
        return preg_replace('/§[0-9a-fk-or]/i', '', $text);
    }

    /**
     * Ensure a string is valid UTF-8 (NBT can contain Java Modified UTF-8).
     */
    private static function sanitizeUtf8(mixed $text): string
    {
        if (! is_string($text)) {
            return '';
        }
        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }
        // Re-encode to drop invalid sequences
        return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    /**
     * Convert Minecraft color codes to inline HTML spans.
     */
    private static function colorCodeToHtml(string $text): string
    {
        $result       = '';
        $currentColor = '#AAAAAA';
        $bold         = false;
        $italic       = false;
        $underline    = false;
        $strike       = false;
        $buffer       = '';

        $i   = 0;
        $len = strlen($text);

        while ($i < $len) {
            if ($text[$i] === '§' && $i + 1 < $len) {
                // Flush buffer
                if ($buffer !== '') {
                    $result .= self::makeSpan($buffer, $currentColor, $bold, $italic, $underline, $strike);
                    $buffer = '';
                }

                $code = strtolower($text[$i + 1]);

                if (isset(self::MC_COLORS[$code])) {
                    $currentColor = self::MC_COLORS[$code];
                    $bold = $italic = $underline = $strike = false;
                } elseif ($code === 'l') {
                    $bold = true;
                } elseif ($code === 'o') {
                    $italic = true;
                } elseif ($code === 'n') {
                    $underline = true;
                } elseif ($code === 'm') {
                    $strike = true;
                } elseif ($code === 'r') {
                    $currentColor = '#AAAAAA';
                    $bold = $italic = $underline = $strike = false;
                }

                $i += 2;
            } else {
                $buffer .= htmlspecialchars($text[$i], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $i++;
            }
        }

        // Flush remaining
        if ($buffer !== '') {
            $result .= self::makeSpan($buffer, $currentColor, $bold, $italic, $underline, $strike);
        }

        return $result;
    }

    private static function makeSpan(string $text, string $color, bool $bold, bool $italic, bool $underline, bool $strike): string
    {
        $style = "color:{$color};";
        if ($bold) $style .= 'font-weight:bold;';
        if ($italic) $style .= 'font-style:italic;';

        $deco = [];
        if ($underline) $deco[] = 'underline';
        if ($strike) $deco[] = 'line-through';
        if ($deco) $style .= 'text-decoration:' . implode(' ', $deco) . ';';

        return "<span style=\"{$style}\">{$text}</span>";
    }
}
