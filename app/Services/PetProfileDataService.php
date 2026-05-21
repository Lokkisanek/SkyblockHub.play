<?php

namespace App\Services;

use App\Utils\ItemParser;

/**
 * Enriches Hypixel pet instances with structured profile data (level, held item, perks, stats)
 * and scores pets for money-making activities.
 */
class PetProfileDataService
{
    public function __construct(
        private PetNeuLoreService $neuLore,
    ) {}

    private const TIER_SCORE = [
        'COMMON' => 0,
        'UNCOMMON' => 1,
        'RARE' => 2,
        'EPIC' => 3,
        'LEGENDARY' => 4,
        'MYTHIC' => 5,
    ];

    private const MIN_RECOMMENDATION_SCORE = 28.0;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $catalog = null;

    /** @var array<string, array{name: string, tier: string}>|null */
    private ?array $heldItems = null;

    /**
     * @param  array<string, mixed>  $pet  Partial pet row from {@see HypixelProfileController::parsePets()}
     * @return array<string, mixed>
     */
    public function enrich(array $pet): array
    {
        $type = strtoupper((string) ($pet['type'] ?? 'UNKNOWN'));
        $tier = strtoupper((string) ($pet['tier'] ?? 'COMMON'));
        $level = (int) ($pet['level']['level'] ?? $pet['level'] ?? 0);
        $maxLevel = $type === 'GOLDEN_DRAGON' ? 200 : 100;
        $catalog = $this->catalogEntry($type);

        $held = $this->resolveHeldItem((string) ($pet['heldItem'] ?? ''));
        $unlockedPerks = $this->unlockedPerks($catalog['perks'] ?? [], $level);

        $neu = $this->neuLore->build($pet);
        $stats = $neu['stats'] !== []
            ? $neu['stats']
            : $this->scaleStats($catalog['stats_at_max'] ?? [], $level, $maxLevel);

        $pet['profile'] = [
            'type' => $type,
            'displayType' => ucwords(strtolower(str_replace('_', ' ', $type))),
            'tier' => $tier,
            'level' => $level,
            'maxLevel' => $maxLevel,
            'isMaxLevel' => $level >= $maxLevel,
            'xp' => (float) ($pet['xp'] ?? 0),
            'active' => (bool) ($pet['active'] ?? false),
            'skin' => $pet['skin'] ?? null,
            'candyUsed' => (int) ($pet['candyUsed'] ?? 0),
            'heldItem' => $held,
            'stats' => $stats,
            'perks' => $unlockedPerks,
            'activities' => $catalog['activities'] ?? [],
            'petCategory' => $neu['pet_category'] ?? null,
        ];

        $pet['lore_html'] = $neu['lore_html'] !== []
            ? $neu['lore_html']
            : $this->buildLoreHtml($pet, $tier);

        return $pet;
    }

    /**
     * @param  list<array<string, mixed>>  $pets
     */
    public function bestForActivity(array $pets, string $activity): ?array
    {
        $activity = $activity === 'garden' ? 'farming' : $activity;

        $bestPet = null;
        $bestScore = -1.0;

        foreach ($pets as $pet) {
            if (! is_array($pet)) {
                continue;
            }

            $enriched = isset($pet['profile']) ? $pet : $this->enrich($pet);
            $score = $this->scoreForActivity($enriched, $activity);
            if ($score <= 0) {
                continue;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestPet = $enriched;
            }
        }

        if ($bestScore < self::MIN_RECOMMENDATION_SCORE) {
            return null;
        }

        return $bestPet;
    }

    /**
     * @param  array<string, mixed>  $pet  Must include profile (call enrich first)
     */
    public function scoreForActivity(array $pet, string $activity): float
    {
        $activity = $activity === 'garden' ? 'farming' : $activity;
        $profile = $pet['profile'] ?? [];
        $activities = $profile['activities'] ?? [];
        $baseScore = (float) ($activities[$activity] ?? 0);

        if ($baseScore <= 0) {
            return 0.0;
        }

        $type = strtoupper((string) ($profile['type'] ?? $pet['type'] ?? ''));
        $level = (int) ($profile['level'] ?? $pet['level']['level'] ?? 0);
        $maxLevel = (int) ($profile['maxLevel'] ?? ($type === 'GOLDEN_DRAGON' ? 200 : 100));
        $levelRatio = $level < 1 ? 0.0 : min(1.0, $level / $maxLevel);
        $levelFactor = pow($levelRatio, 1.35);

        $tier = strtoupper((string) ($profile['tier'] ?? $pet['tier'] ?? 'COMMON'));
        $tierBonus = (self::TIER_SCORE[$tier] ?? 0) * 2.0;

        $statBonus = $this->statBonusForActivity($profile['stats'] ?? [], $activity) * $levelFactor;
        $heldBonus = $this->heldItemBonus($profile['heldItem'] ?? null, $activity);
        $perkBonus = min(8.0, count($profile['perks'] ?? []) * 2.0);
        $candyPenalty = min(12.0, (int) ($profile['candyUsed'] ?? 0) * 2.5);

        return ($baseScore * 10.0 * $levelFactor) + $tierBonus + $statBonus + $heldBonus + $perkBonus - $candyPenalty;
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogEntry(string $type): array
    {
        $catalog = $this->loadCatalog();

        return $catalog[$type] ?? ['activities' => [], 'stats_at_max' => [], 'perks' => []];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadCatalog(): array
    {
        if ($this->catalog === null) {
            $this->catalog = config('skyblock_pet_profiles', []);
        }

        return $this->catalog;
    }

    /**
     * @return array<string, array{name: string, tier: string}>
     */
    private function loadHeldItems(): array
    {
        if ($this->heldItems === null) {
            $this->heldItems = config('pet_held_items', []);
        }

        return $this->heldItems;
    }

    /**
     * @return array{id: ?string, name: ?string, tier: ?string, bonuses: list<string>}|null
     */
    private function resolveHeldItem(string $heldItemId): ?array
    {
        if ($heldItemId === '') {
            return null;
        }

        $items = $this->loadHeldItems();
        $info = $items[$heldItemId] ?? null;
        $name = $info['name'] ?? ucwords(strtolower(str_replace('_', ' ', $heldItemId)));
        $tier = $info['tier'] ?? 'COMMON';

        return [
            'id' => $heldItemId,
            'name' => $name,
            'tier' => $tier,
            'bonuses' => $this->heldItemBonuses($heldItemId),
        ];
    }

    /**
     * @return list<string>
     */
    private function heldItemBonuses(string $heldItemId): array
    {
        $id = strtoupper($heldItemId);
        $bonuses = [];

        if (str_contains($id, 'COMBAT_SKILL')) {
            $bonuses[] = 'Combat XP boost';
        }
        if (str_contains($id, 'FARMING_SKILL')) {
            $bonuses[] = 'Farming XP boost';
        }
        if (str_contains($id, 'MINING_SKILL')) {
            $bonuses[] = 'Mining XP boost';
        }
        if (str_contains($id, 'FISHING_SKILL')) {
            $bonuses[] = 'Fishing XP boost';
        }
        if (str_contains($id, 'FORAGING_SKILL')) {
            $bonuses[] = 'Foraging XP boost';
        }
        if (str_contains($id, 'BIG_TEETH') || str_contains($id, 'SHARPENED_CLAWS') || str_contains($id, 'IRON_CLAWS')) {
            $bonuses[] = 'Combat stats';
        }
        if (str_contains($id, 'LUCKY_CLOVER')) {
            $bonuses[] = 'Magic Find';
        }
        if (str_contains($id, 'TIER_BOOST')) {
            $bonuses[] = 'Tier boost';
        }
        if (str_contains($id, 'CROCHET_TIGER')) {
            $bonuses[] = 'Combat stats';
        }
        if (str_contains($id, 'REAPER_GEM')) {
            $bonuses[] = 'Slayer damage';
        }

        return $bonuses;
    }

    /**
     * @param  array<string, int|float>  $statsAtMax
     * @return array<string, array{value: float, percent: bool}>
     */
    private function scaleStats(array $statsAtMax, int $level, int $maxLevel): array
    {
        if ($statsAtMax === [] || $level < 1) {
            return [];
        }

        $ratio = min(1.0, $level / max(1, $maxLevel));
        $out = [];
        foreach ($statsAtMax as $key => $value) {
            $scaled = round((float) $value * $ratio, 1);
            if ($scaled <= 0) {
                continue;
            }
            $out[$key] = [
                'value' => $scaled,
                'percent' => in_array($key, ['CC', 'CD', 'SCC', 'MF'], true),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{min_level: int, name: string, description: string}>  $perks
     * @return list<array{min_level: int, name: string, description: string}>
     */
    private function unlockedPerks(array $perks, int $level): array
    {
        $out = [];
        foreach ($perks as $perk) {
            if (! is_array($perk)) {
                continue;
            }
            if ($level >= (int) ($perk['min_level'] ?? 1)) {
                $out[] = $perk;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, array{value: float, percent: bool}>  $stats
     */
    private function statBonusForActivity(array $stats, string $activity): float
    {
        $weights = match ($activity) {
            'farming', 'garden' => ['Farming Fortune' => 0.15, 'Sea Creature Chance' => 0.05],
            'mining' => ['Mining Fortune' => 0.15, 'Mining Speed' => 0.08, 'Pristine' => 0.08],
            'fishing' => ['Sea Creature Chance' => 0.2, 'Fishing Speed' => 0.1, 'Ferocity' => 0.08],
            'foraging' => ['Foraging Fortune' => 0.15, 'Speed' => 0.05],
            'dungeon', 'combat' => ['Strength' => 0.12, 'Critical Chance' => 0.1, 'Critical Damage' => 0.1, 'Magic Find' => 0.06],
            default => [],
        };

        $sum = 0.0;
        foreach ($weights as $key => $weight) {
            if (isset($stats[$key]['value'])) {
                $sum += (float) $stats[$key]['value'] * $weight;
            }
        }

        return min(15.0, $sum);
    }

    /**
     * @param  array{id: ?string, name: ?string, tier: ?string, bonuses: list<string>}|null  $held
     */
    private function heldItemBonus(?array $held, string $activity): float
    {
        if ($held === null || empty($held['id'])) {
            return 0.0;
        }

        $id = strtoupper((string) $held['id']);
        $skillNeedles = match ($activity) {
            'farming', 'garden' => ['FARMING_SKILL', 'FARMING'],
            'mining' => ['MINING_SKILL', 'MINING'],
            'fishing' => ['FISHING_SKILL', 'FISHING'],
            'foraging' => ['FORAGING_SKILL', 'FORAGING'],
            'dungeon', 'combat' => ['COMBAT_SKILL', 'COMBAT'],
            default => [],
        };

        foreach ($skillNeedles as $needle) {
            if (str_contains($id, $needle)) {
                return 10.0;
            }
        }

        foreach ($held['bonuses'] ?? [] as $bonus) {
            if ($activity === 'combat' || $activity === 'dungeon') {
                if (str_contains(strtolower($bonus), 'combat') || str_contains(strtolower($bonus), 'slayer')) {
                    return 6.0;
                }
            }
        }

        if (str_contains($id, 'LUCKY_CLOVER') || str_contains($id, 'TIER_BOOST')) {
            return 4.0;
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $pet
     * @return list<string>
     */
    private function buildLoreHtml(array $pet, string $tier): array
    {
        $mcColors = [
            'COMMON' => '§f',
            'UNCOMMON' => '§a',
            'RARE' => '§9',
            'EPIC' => '§5',
            'LEGENDARY' => '§6',
            'MYTHIC' => '§d',
        ];
        $colorCode = $mcColors[$tier] ?? '§f';
        $profile = $pet['profile'] ?? [];
        $level = (int) ($profile['level'] ?? 0);
        $maxLevel = (int) ($profile['maxLevel'] ?? 100);
        $levelData = $pet['level'] ?? [];

        $lore = [''];

        if ($level < $maxLevel && isset($levelData['progress'])) {
            $progress = (float) ($levelData['progress'] ?? 0);
            $lore[] = "§7Progress to Level {$level} §8→ §7".($level + 1).': §e'.round($progress * 100, 1).'%';
            if (isset($levelData['xpCurrent'], $levelData['xpForNext'])) {
                $lore[] = '§e'.number_format($levelData['xpCurrent']).'§6/§e'.number_format($levelData['xpForNext']);
            }
        } else {
            $lore[] = '§bMAX LEVEL';
        }

        foreach ($profile['stats'] ?? [] as $key => $stat) {
            if (! is_array($stat)) {
                continue;
            }
            $v = $stat['value'] % 1 === 0 ? (int) $stat['value'] : $stat['value'];
            $suffix = ! empty($stat['percent']) ? '%' : '';
            $lore[] = '§7'.$key.': §a+'.$v.$suffix;
        }

        foreach ($profile['perks'] ?? [] as $perk) {
            if (! is_array($perk) || empty($perk['name'])) {
                continue;
            }
            $lore[] = '';
            $lore[] = '§6'.$perk['name'];
            if (! empty($perk['description'])) {
                $lore[] = '§7'.$perk['description'];
            }
        }

        $held = $profile['heldItem'] ?? null;
        if (is_array($held) && ! empty($held['name'])) {
            $itemColor = $mcColors[$held['tier'] ?? 'COMMON'] ?? '§f';
            $lore[] = '';
            $lore[] = "§7Held Item: {$itemColor}{$held['name']}";
        }

        if ((int) ($profile['candyUsed'] ?? 0) > 0) {
            $lore[] = '';
            $lore[] = '§7Candy Used: §d'.$profile['candyUsed'];
        }

        $lore[] = '';
        $lore[] = "{$colorCode}§l{$tier} PET";

        return array_map([ItemParser::class, 'colorCodeToHtml'], $lore);
    }
}
