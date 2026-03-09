<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Parses resource pack CIT (Custom Item Textures) .properties files
 * and builds a JSON index mapping skyblock_id → texture path.
 *
 * Mirrors SkyCrypt's custom-resources.js logic.
 */
class BuildPackIndex extends Command
{
    protected $signature = 'pack:build-index';
    protected $description = 'Parse resource pack .properties files and build JSON texture indices';

    /**
     * MC 1.8.9 item name → numeric ID map (subset for item matching).
     */
    private const MC_ITEMS = [
        'wooden_sword' => 268, 'stone_sword' => 272, 'iron_sword' => 267,
        'golden_sword' => 283, 'diamond_sword' => 276,
        'wooden_shovel' => 269, 'stone_shovel' => 273, 'iron_shovel' => 256,
        'golden_shovel' => 284, 'diamond_shovel' => 277,
        'wooden_pickaxe' => 270, 'stone_pickaxe' => 274, 'iron_pickaxe' => 257,
        'golden_pickaxe' => 285, 'diamond_pickaxe' => 278,
        'wooden_axe' => 271, 'stone_axe' => 275, 'iron_axe' => 258,
        'golden_axe' => 286, 'diamond_axe' => 279,
        'wooden_hoe' => 290, 'stone_hoe' => 291, 'iron_hoe' => 292,
        'golden_hoe' => 294, 'diamond_hoe' => 293,
        'bow' => 261, 'arrow' => 262, 'fishing_rod' => 346,
        'leather_helmet' => 298, 'leather_chestplate' => 299,
        'leather_leggings' => 300, 'leather_boots' => 301,
        'chainmail_helmet' => 302, 'chainmail_chestplate' => 303,
        'chainmail_leggings' => 304, 'chainmail_boots' => 305,
        'iron_helmet' => 306, 'iron_chestplate' => 307,
        'iron_leggings' => 308, 'iron_boots' => 309,
        'diamond_helmet' => 310, 'diamond_chestplate' => 311,
        'diamond_leggings' => 312, 'diamond_boots' => 313,
        'golden_helmet' => 314, 'golden_chestplate' => 315,
        'golden_leggings' => 316, 'golden_boots' => 317,
        'skull' => 397, 'flint_and_steel' => 259, 'shears' => 359,
        'compass' => 345, 'clock' => 347,
        'apple' => 260, 'golden_apple' => 322, 'bread' => 297,
        'coal' => 263, 'diamond' => 264, 'iron_ingot' => 265,
        'gold_ingot' => 266, 'stick' => 280, 'bowl' => 281,
        'string' => 287, 'feather' => 288, 'gunpowder' => 289,
        'wheat_seeds' => 295, 'wheat' => 296,
        'raw_porkchop' => 319, 'cooked_porkchop' => 320,
        'bone' => 352, 'sugar' => 353, 'cake' => 354,
        'cookie' => 357, 'melon_slice' => 360,
        'pumpkin_seeds' => 361, 'melon_seeds' => 362,
        'raw_beef' => 363, 'cooked_beef' => 364,
        'raw_chicken' => 365, 'cooked_chicken' => 366,
        'rotten_flesh' => 367, 'ender_pearl' => 368,
        'blaze_rod' => 369, 'ghast_tear' => 370,
        'gold_nugget' => 371, 'nether_wart' => 372,
        'potion' => 373, 'glass_bottle' => 374,
        'spider_eye' => 375, 'fermented_spider_eye' => 376,
        'blaze_powder' => 377, 'magma_cream' => 378,
        'ender_eye' => 381, 'golden_carrot' => 396,
        'emerald' => 388, 'carrot' => 391, 'potato' => 392,
        'nether_star' => 399, 'pumpkin_pie' => 400,
        'enchanted_book' => 403, 'book' => 340,
        'writable_book' => 386, 'paper' => 339,
        'slime_ball' => 341, 'egg' => 344,
        'saddle' => 329, 'name_tag' => 421, 'lead' => 420,
        'dye' => 351, 'redstone' => 331, 'glowstone_dust' => 348,
        'quartz' => 406, 'prismarine_shard' => 409,
        'prismarine_crystals' => 410,
        'rabbit_foot' => 414, 'rabbit_hide' => 415,
        'leather' => 334, 'flint' => 318, 'snowball' => 332,
        'bucket' => 325, 'map' => 358, 'filled_map' => 358,
        'firework_rocket' => 401, 'firework_star' => 402,
        'tripwire_hook' => 131, 'banner' => 425,
        'spawn_egg' => 383,
    ];

    public function handle(): int
    {
        $packsDir = public_path('resourcepacks');

        if (! is_dir($packsDir)) {
            $this->error("No resourcepacks directory found at {$packsDir}");
            return 1;
        }

        $packDirs = array_filter(glob($packsDir . '/*'), 'is_dir');

        foreach ($packDirs as $packDir) {
            $configFile = $packDir . '/config.json';
            if (! file_exists($configFile)) {
                continue;
            }

            $config = json_decode(file_get_contents($configFile), true);
            $packId = $config['id'] ?? basename($packDir);
            $this->info("Processing pack: {$config['name']} ({$packId})");

            $citDir = $packDir . '/assets/minecraft/mcpatcher/cit';
            if (! is_dir($citDir)) {
                $this->warn("  No CIT directory found, skipping.");
                continue;
            }

            $index = $this->buildPackIndex($packDir, $citDir, $config);

            $indexFile = $packDir . '/index.json';
            file_put_contents($indexFile, json_encode($index, JSON_UNESCAPED_SLASHES));

            $this->info("  Indexed {$index['_stats']['total_textures']} textures → {$indexFile}");
        }

        $this->info('Done!');
        return 0;
    }

    private function buildPackIndex(string $packDir, string $citDir, array $config): array
    {
        $index = [
            'id'       => $config['id'],
            'name'     => $config['name'],
            'version'  => $config['version'] ?? '',
            'author'   => $config['author'] ?? '',
            'priority' => $config['priority'] ?? 0,
            // Mapping: skyblock_id (exact) → relative texture path
            'skyblock_id' => [],
            // Mapping: skyblock_id (pattern/regex) → [{pattern, path, weight}]
            'skyblock_id_patterns' => [],
            // Mapping: "itemId:damage" → relative texture path
            'item_id' => [],
            // Mapping: skull texture value → relative texture path
            'texture_value' => [],
            // Mapping: head entries (id 397) with damage → relative texture path
            'heads' => [],
            // Leather armor entries with overlay textures
            'leather' => [],
        ];

        $propertiesFiles = $this->findFiles($citDir, '*.properties');
        $stats = ['total_textures' => 0, 'skyblock_id_exact' => 0, 'skyblock_id_pattern' => 0, 'item_id' => 0, 'heads' => 0];

        foreach ($propertiesFiles as $propFile) {
            $entry = $this->parsePropertiesFile($propFile, $packDir);
            if (! $entry || ! $entry['texture_path']) {
                continue;
            }

            $stats['total_textures']++;

            // Determine the skyblock_id match
            $skyblockId = $entry['skyblock_id'] ?? null;
            $skyblockIdPattern = $entry['skyblock_id_pattern'] ?? null;
            $itemId = $entry['item_id'] ?? null;
            $damage = $entry['damage'] ?? 0;
            $weight = $entry['weight'] ?? 0;
            $texturePath = $entry['texture_path'];
            $textureValueMatch = $entry['texture_value'] ?? null;

            // Leather armor (has overlay texture)
            if ($entry['leather'] ?? false) {
                $index['leather'][] = [
                    'skyblock_id' => $skyblockId,
                    'skyblock_id_pattern' => $skyblockIdPattern,
                    'base'    => $entry['leather']['base'] ?? $texturePath,
                    'overlay' => $entry['leather']['overlay'] ?? $texturePath,
                    'weight'  => $weight,
                    'extra_match' => $entry['extra_match'] ?? null,
                ];
                continue;
            }

            // Exact skyblock_id match (most common)
            if ($skyblockId) {
                $existing = $index['skyblock_id'][$skyblockId] ?? null;
                $existingWeight = $existing['weight'] ?? -1;

                if ($weight > $existingWeight) {
                    // If there's an extra_match (lore-based), store as pattern
                    if (isset($entry['extra_match'])) {
                        $index['skyblock_id_patterns'][] = [
                            'skyblock_id' => $skyblockId,
                            'path'        => $texturePath,
                            'weight'      => $weight,
                            'match'       => $entry['extra_match'],
                        ];
                        $stats['skyblock_id_pattern']++;
                    } else {
                        $index['skyblock_id'][$skyblockId] = [
                            'path'   => $texturePath,
                            'weight' => $weight,
                        ];
                        $stats['skyblock_id_exact']++;
                    }
                }
                continue;
            }

            // Pattern skyblock_id match
            if ($skyblockIdPattern) {
                $index['skyblock_id_patterns'][] = [
                    'pattern' => $skyblockIdPattern,
                    'path'    => $texturePath,
                    'weight'  => $weight,
                    'match'   => $entry['extra_match'] ?? null,
                ];
                $stats['skyblock_id_pattern']++;
                continue;
            }

            // Skull texture value match
            if ($textureValueMatch) {
                $index['texture_value'][$textureValueMatch] = [
                    'path'   => $texturePath,
                    'weight' => $weight,
                ];
                continue;
            }

            // Item ID match (non-skull)
            if ($itemId !== null && $itemId !== 397) {
                $key = "{$itemId}:{$damage}";
                $existing = $index['item_id'][$key] ?? null;
                $existingWeight = $existing['weight'] ?? -1;

                if ($weight > $existingWeight) {
                    $index['item_id'][$key] = [
                        'path'   => $texturePath,
                        'weight' => $weight,
                    ];
                    $stats['item_id']++;
                }
                continue;
            }

            // Head (skull) entries by damage
            if ($itemId === 397) {
                $key = "397:{$damage}";
                $index['heads'][$key] = [
                    'path'   => $texturePath,
                    'weight' => $weight,
                ];
                $stats['heads']++;
            }
        }

        $index['_stats'] = $stats;

        return $index;
    }

    private function parsePropertiesFile(string $file, string $packDir): ?array
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $lines = preg_split('/\r?\n/', $content);
        $props = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }
            $key = substr($line, 0, $eqPos);
            $value = substr($line, $eqPos + 1);
            $props[$key] = $value;
        }

        if (empty($props)) {
            return null;
        }

        // Skip non-item types
        if (isset($props['type']) && $props['type'] !== 'item') {
            return null;
        }

        $dir = dirname($file);
        $baseName = pathinfo($file, PATHINFO_FILENAME);

        // Resolve texture file path
        $texturePath = null;

        // Check for texture.bow_standby (special case for bows)
        if (isset($props['texture.bow_standby'])) {
            $texturePath = $this->resolveTextureTo($dir, $props['texture.bow_standby'], $packDir);
        }
        // Check for explicit texture property
        elseif (isset($props['texture'])) {
            $texturePath = $this->resolveTextureTo($dir, $props['texture'], $packDir);
        }
        // Default: same name as .properties file
        else {
            $texturePath = $this->resolveTextureTo($dir, $baseName, $packDir);
        }

        if (! $texturePath) {
            return null;
        }

        $entry = [
            'texture_path' => $texturePath,
            'weight'       => (int) ($props['weight'] ?? 0),
        ];

        // Parse item ID
        if (isset($props['items']) || isset($props['matchItems'])) {
            $itemName = str_replace('minecraft:', '', $props['items'] ?? $props['matchItems'] ?? '');
            $itemName = trim($itemName);
            $entry['item_id'] = self::MC_ITEMS[$itemName] ?? null;
        }

        // Parse damage
        if (isset($props['damage'])) {
            $entry['damage'] = (int) $props['damage'];
        }

        // Parse skyblock_id from nbt.ExtraAttributes.id
        if (isset($props['nbt.ExtraAttributes.id'])) {
            $raw = $props['nbt.ExtraAttributes.id'];

            if (str_starts_with($raw, 'regex:') || str_starts_with($raw, 'iregex:')) {
                // Regex pattern — store as pattern
                $entry['skyblock_id_pattern'] = $raw;
            } elseif (str_starts_with($raw, 'pattern:') || str_starts_with($raw, 'ipattern:')) {
                // Glob pattern — convert to regex
                $entry['skyblock_id_pattern'] = $raw;
            } else {
                // Exact match
                $entry['skyblock_id'] = $raw;
            }
        }

        // Parse skull texture value
        if (isset($props['nbt.SkullOwner.Properties.textures.0.Value'])) {
            $entry['texture_value'] = $props['nbt.SkullOwner.Properties.textures.0.Value'];
        }

        // Check for extra matching criteria (lore, display name, etc.)
        $extraMatches = [];
        foreach ($props as $key => $value) {
            if ($key === 'nbt.ExtraAttributes.id' || $key === 'nbt.SkullOwner.Properties.textures.0.Value') {
                continue;
            }
            if (str_starts_with($key, 'nbt.')) {
                $extraMatches[] = ['key' => substr($key, 4), 'value' => $value];
            }
        }
        if (! empty($extraMatches)) {
            $entry['extra_match'] = $extraMatches;
        }

        // Leather armor detection
        $leatherKeys = array_filter(array_keys($props), fn ($k) => str_contains($k, 'texture.leather_'));
        if (! empty($leatherKeys)) {
            $leather = [];
            foreach ($leatherKeys as $lk) {
                if (str_contains($lk, '_overlay')) {
                    $leather['overlay'] = $this->resolveTextureTo($dir, $props[$lk], $packDir);
                } else {
                    $leather['base'] = $this->resolveTextureTo($dir, $props[$lk], $packDir);
                }
            }
            if (! empty($leather)) {
                $entry['leather'] = $leather;
                // If base and overlay are the same, use the overlay as the main texture
                $entry['texture_path'] = $leather['overlay'] ?? $leather['base'] ?? $texturePath;
            }
        }

        return $entry;
    }

    /**
     * Resolve a texture reference to a relative URL path from public/.
     */
    private function resolveTextureTo(string $propDir, string $ref, string $packDir): ?string
    {
        if (! str_ends_with($ref, '.png')) {
            $ref .= '.png';
        }

        // Resolve relative to properties file directory
        $absPath = realpath($propDir . '/' . $ref);
        if (! $absPath || ! file_exists($absPath)) {
            // Try without directory (just filename)
            $absPath = $propDir . DIRECTORY_SEPARATOR . basename($ref);
            if (! file_exists($absPath)) {
                return null;
            }
            $absPath = realpath($absPath);
        }

        // Make path relative to public/
        $publicDir = public_path();
        $relativePath = str_replace('\\', '/', substr($absPath, strlen($publicDir)));

        // Ensure starts with /
        if (! str_starts_with($relativePath, '/')) {
            $relativePath = '/' . $relativePath;
        }

        return $relativePath;
    }

    private function findFiles(string $dir, string $pattern): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (fnmatch($pattern, $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
