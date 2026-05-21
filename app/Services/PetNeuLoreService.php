<?php

namespace App\Services;

use App\Utils\ItemParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Builds in-game-style pet tooltips using NotEnoughUpdates data (same approach as SkyCrypt):
 * item lore templates + level-interpolated stats from petnums.json.
 */
class PetNeuLoreService
{
    private const NEU_ITEMS_BASE = 'https://raw.githubusercontent.com/NotEnoughUpdates/NotEnoughUpdates-REPO/master/items/';

    private const TIER_INDEX = [
        'COMMON' => 0,
        'UNCOMMON' => 1,
        'RARE' => 2,
        'EPIC' => 3,
        'LEGENDARY' => 4,
        'MYTHIC' => 5,
    ];

    private const STAT_LABELS = [
        'STRENGTH' => 'Strength',
        'DEFENSE' => 'Defense',
        'HEALTH' => 'Health',
        'SPEED' => 'Speed',
        'INTELLIGENCE' => 'Intelligence',
        'CRITICAL_CHANCE' => 'Critical Chance',
        'CRITICAL_DAMAGE' => 'Critical Damage',
        'ATTACK_SPEED' => 'Attack Speed',
        'MAGIC_FIND' => 'Magic Find',
        'PET_LUCK' => 'Pet Luck',
        'TRUE_DEFENSE' => 'True Defense',
        'FEROCITY' => 'Ferocity',
        'SEA_CREATURE_CHANCE' => 'Sea Creature Chance',
        'FISHING_SPEED' => 'Fishing Speed',
        'MINING_SPEED' => 'Mining Speed',
        'MINING_FORTUNE' => 'Mining Fortune',
        'FARMING_FORTUNE' => 'Farming Fortune',
        'FORAGING_FORTUNE' => 'Foraging Fortune',
        'PRISTINE' => 'Pristine',
        'BONUS_PEST_CHANCE' => 'Bonus Pest Chance',
        'HEAT_RESISTANCE' => 'Heat Resistance',
    ];

    private const PERCENT_STAT_KEYS = [
        'SEA_CREATURE_CHANCE',
        'CRITICAL_CHANCE',
        'CRITICAL_DAMAGE',
        'BONUS_PEST_CHANCE',
    ];

    /** @var array<string, mixed>|null */
    private ?array $petNums = null;

    /**
     * @param  array<string, mixed>  $pet
     * @return array{lore_html: list<string>, stats: array<string, array{value: float, percent: bool}>, pet_category: ?string}
     */
    public function build(array $pet): array
    {
        $type = strtoupper((string) ($pet['type'] ?? 'UNKNOWN'));
        $tier = strtoupper((string) ($pet['tier'] ?? 'COMMON'));
        $level = (int) ($pet['level']['level'] ?? $pet['level'] ?? 1);
        $maxLevel = $type === 'GOLDEN_DRAGON' ? 200 : 100;

        $tierIndex = self::TIER_INDEX[$tier] ?? 0;
        $template = $this->loadItemTemplate($type, $tierIndex);

        if ($template === null) {
            return ['lore_html' => [], 'stats' => [], 'pet_category' => null];
        }

        $nums = $this->interpolateNums($type, $tier, $level, $maxLevel);
        if ($nums === null) {
            return ['lore_html' => [], 'stats' => [], 'pet_category' => null];
        }

        $lore = [];
        foreach ($template['lore'] as $line) {
            $lore[] = $this->replacePlaceholders((string) $line, $level, $nums);
        }

        $lore = $this->appendHeldItemLines($lore, (string) ($pet['heldItem'] ?? ''));
        $lore = array_merge($lore, $this->appendMetaLines($pet, $level, $maxLevel));

        return [
            'lore_html' => array_map([ItemParser::class, 'colorCodeToHtml'], $lore),
            'stats' => $this->statsFromNums($nums['statNums']),
            'pet_category' => $this->petCategoryFromLore($template['lore']),
        ];
    }

    /**
     * @return array{statNums: array<string, float>, otherNums: list<float>}|null
     */
    private function interpolateNums(string $type, string $tier, int $level, int $maxLevel): ?array
    {
        $petNums = $this->loadPetNums();
        $entry = $petNums[$type][$tier] ?? null;
        if (! is_array($entry)) {
            return null;
        }

        $at1 = $entry['1'] ?? null;
        $atMax = $entry[(string) $maxLevel] ?? $entry['100'] ?? null;
        if (! is_array($at1) || ! is_array($atMax)) {
            return null;
        }

        $ratio = $maxLevel > 0 ? min(1.0, max(0.0, $level / $maxLevel)) : 0.0;

        $statNums = [];
        foreach ($at1['statNums'] ?? [] as $key => $min) {
            $max = $atMax['statNums'][$key] ?? $min;
            $statNums[$key] = (float) $min + ((float) $max - (float) $min) * $ratio;
        }

        $otherNums = [];
        $othersMin = $at1['otherNums'] ?? [];
        $othersMax = $atMax['otherNums'] ?? [];
        $count = max(count($othersMin), count($othersMax));
        for ($i = 0; $i < $count; $i++) {
            $min = (float) ($othersMin[$i] ?? 0);
            $max = (float) ($othersMax[$i] ?? $min);
            $otherNums[] = $min + ($max - $min) * $ratio;
        }

        return ['statNums' => $statNums, 'otherNums' => $otherNums];
    }

    /**
     * @param  array<string, float>  $statNums
     * @return array<string, array{value: float, percent: bool}>
     */
    private function statsFromNums(array $statNums): array
    {
        $out = [];
        foreach ($statNums as $key => $value) {
            $label = self::STAT_LABELS[$key] ?? ucwords(strtolower(str_replace('_', ' ', $key)));
            $out[$label] = [
                'value' => round($value, 2),
                'percent' => in_array($key, self::PERCENT_STAT_KEYS, true),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $lore
     */
    private function replacePlaceholders(string $line, int $level, array $nums): string
    {
        $line = str_replace('{LVL}', (string) $level, $line);

        foreach ($nums['statNums'] as $key => $value) {
            $line = str_replace('{'.$key.'}', $this->formatNum($value), $line);
        }

        foreach ($nums['otherNums'] as $i => $value) {
            $line = str_replace('{'.$i.'}', $this->formatNum($value), $line);
        }

        return $line;
    }

    private function formatNum(float $value): string
    {
        if (abs($value - round($value)) < 0.05) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }

    /**
     * @param  list<string>  $lore
     * @return list<string>
     */
    private function appendHeldItemLines(array $lore, string $heldItemId): array
    {
        if ($heldItemId === '') {
            return $lore;
        }

        $held = $this->loadHeldItemTemplate($heldItemId);
        if ($held === null) {
            return $lore;
        }

        $mcColors = [
            'COMMON' => '§f', 'UNCOMMON' => '§a', 'RARE' => '§9',
            'EPIC' => '§5', 'LEGENDARY' => '§6', 'MYTHIC' => '§d',
        ];
        $heldConfig = config('pet_held_items.'.$heldItemId);
        $heldTier = is_array($heldConfig) ? ($heldConfig['tier'] ?? 'COMMON') : 'COMMON';
        $heldColor = $mcColors[$heldTier] ?? '§f';
        $heldName = $held['display_name'] ?? $heldItemId;

        $lore[] = '';
        $lore[] = "§7Held Item: {$heldColor}{$heldName}";

        foreach ($held['effect_lines'] as $effectLine) {
            $lore[] = $effectLine;
        }

        return $lore;
    }

    /**
     * @param  array<string, mixed>  $pet
     * @return list<string>
     */
    private function appendMetaLines(array $pet, int $level, int $maxLevel): array
    {
        $lines = [];
        $levelData = $pet['level'] ?? [];
        $xp = (float) ($pet['xp'] ?? 0);

        if ($level < $maxLevel && isset($levelData['progress'])) {
            $progress = (float) ($levelData['progress'] ?? 0);
            $lines[] = '';
            $lines[] = '§7Progress to Level '.($level + 1).': §e'.round($progress * 100, 1).'%';
            if (isset($levelData['xpCurrent'], $levelData['xpForNext'])) {
                $lines[] = '§e'.$this->shortNum((float) $levelData['xpCurrent'])
                    .'§6/§e'.$this->shortNum((float) $levelData['xpForNext']);
            }
        }

        $totalToMax = $this->totalXpToMaxLevel($pet);
        if ($totalToMax > 0 && $xp > 0) {
            $pct = round(min(100, ($xp / $totalToMax) * 100), 2);
            $lines[] = '§7Total XP: §e'.$this->shortNum($xp).' §8/ §e'.$this->shortNum($totalToMax).' §7('.$pct.'%)';
        }

        $candy = (int) ($pet['candyUsed'] ?? 0);
        if ($candy > 0) {
            $lines[] = '§7Candy Used: §d'.$candy.' §8/ §d10';
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $pet
     */
    private function totalXpToMaxLevel(array $pet): float
    {
        $tier = strtoupper((string) ($pet['tier'] ?? 'COMMON'));
        $type = strtoupper((string) ($pet['type'] ?? ''));
        $maxLevel = $type === 'GOLDEN_DRAGON' ? 200 : 100;

        $offsets = config('data.neu_pets.pet_rarity_offset', [
            'COMMON' => 0, 'UNCOMMON' => 6, 'RARE' => 11, 'EPIC' => 16, 'LEGENDARY' => 20, 'MYTHIC' => 20,
        ]);
        $levels = config('data.neu_pets.pet_levels', []);

        if ($levels === []) {
            return 0.0;
        }

        $offset = $offsets[$tier] ?? 0;
        $slice = array_slice($levels, $offset, max(0, $maxLevel - 1));
        $total = array_sum($slice);

        if ($type === 'GOLDEN_DRAGON') {
            $custom = config('data.neu_pets.custom_pet_leveling.GOLDEN_DRAGON.pet_levels', []);
            if ($custom !== []) {
                return (float) array_sum($custom);
            }
        }

        return (float) $total;
    }

    private function shortNum(float $n): string
    {
        if ($n >= 1_000_000) {
            return round($n / 1_000_000, 2).'M';
        }
        if ($n >= 1_000) {
            return round($n / 1_000, 1).'K';
        }

        return number_format($n, 0, '.', ',');
    }

    /**
     * @param  list<string>  $lore
     */
    private function petCategoryFromLore(array $lore): ?string
    {
        foreach ($lore as $line) {
            if (preg_match('/§8(.+)/', $line, $m) && ! str_contains($line, 'Consumed')) {
                return trim($m[1]);
            }
        }

        return null;
    }

    /**
     * @return array{lore: list<string>, display_name: ?string}|null
     */
    private function loadItemTemplate(string $type, int $tierIndex): ?array
    {
        $cacheKey = "neu_pet_item:{$type}:{$tierIndex}";

        return Cache::remember($cacheKey, 86400 * 7, function () use ($type, $tierIndex) {
            $url = self::NEU_ITEMS_BASE.$type.';'.$tierIndex.'.json';
            try {
                $resp = Http::timeout(8)->get($url);
                if (! $resp->successful()) {
                    return null;
                }
                $data = $resp->json();
                if (! is_array($data) || empty($data['lore'])) {
                    return null;
                }

                return [
                    'lore' => array_values($data['lore']),
                    'display_name' => $data['displayname'] ?? null,
                ];
            } catch (\Throwable $e) {
                Log::debug('PetNeuLore: item template fetch failed', ['type' => $type, 'tier' => $tierIndex, 'error' => $e->getMessage()]);

                return null;
            }
        });
    }

    /**
     * @return array{display_name: string, effect_lines: list<string>}|null
     */
    private function loadHeldItemTemplate(string $heldItemId): ?array
    {
        $cacheKey = 'neu_pet_held:'.$heldItemId;

        return Cache::remember($cacheKey, 86400 * 7, function () use ($heldItemId) {
            $candidates = [$heldItemId];
            if (! str_starts_with($heldItemId, 'PET_ITEM_')) {
                $candidates[] = 'PET_ITEM_'.$heldItemId;
            }

            foreach ($candidates as $id) {
                try {
                    $resp = Http::timeout(8)->get(self::NEU_ITEMS_BASE.$id.'.json');
                    if (! $resp->successful()) {
                        continue;
                    }
                    $data = $resp->json();
                    if (! is_array($data)) {
                        continue;
                    }

                    $effectLines = [];
                    foreach ($data['lore'] ?? [] as $line) {
                        $line = (string) $line;
                        if (str_contains($line, 'Grants') || str_contains($line, 'grants')) {
                            $effectLines[] = $line;
                        }
                    }

                    $display = ItemParser::stripColorCodes((string) ($data['displayname'] ?? $heldItemId));

                    return [
                        'display_name' => $display,
                        'effect_lines' => $effectLines,
                    ];
                } catch (\Throwable) {
                    continue;
                }
            }

            $items = config('pet_held_items.'.$heldItemId);
            if (is_array($items)) {
                return [
                    'display_name' => $items['name'] ?? $heldItemId,
                    'effect_lines' => [],
                ];
            }

            return null;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function loadPetNums(): array
    {
        if ($this->petNums === null) {
            $path = config_path('data/neu_petnums.json');
            $raw = is_file($path) ? file_get_contents($path) : false;
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            $this->petNums = is_array($decoded) ? $decoded : [];
        }

        return $this->petNums;
    }
}
