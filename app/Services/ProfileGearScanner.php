<?php

namespace App\Services;

/**
 * Collects and classifies gear from every item container on a transformed SkyBlock profile.
 */
class ProfileGearScanner
{
    /** skyblock_id => enchanted bazaar product id */
    private const CROP_PRODUCT_BY_HOE = [
        'COCO_CHOPPER' => 'ENCHANTED_COCOA',
        'FUNGI_CUTTER' => 'ENCHANTED_BROWN_MUSHROOM',
        'CACTUS_KNIFE' => 'ENCHANTED_CACTUS_GREEN',
        'MELON_DICER' => 'ENCHANTED_MELON',
        'PUMPKIN_DICER' => 'ENCHANTED_PUMPKIN',
        'THEORETICAL_HOE_POTATO' => 'ENCHANTED_POTATO',
        'THEORETICAL_HOE_CARROT' => 'ENCHANTED_CARROT',
        'THEORETICAL_HOE_WHEAT' => 'ENCHANTED_WHEAT',
        'THEORETICAL_HOE_CANE' => 'ENCHANTED_SUGAR',
        'THEORETICAL_HOE_WARTS' => 'ENCHANTED_NETHER_STALK',
        'FIELD_MOUSE' => 'ENCHANTED_WHEAT',
    ];

    /** Partial skyblock_id / display name hints → crop product */
    private const HOE_ID_PATTERNS = [
        'WART' => 'ENCHANTED_NETHER_STALK',
        'WHEAT' => 'ENCHANTED_WHEAT',
        'CARROT' => 'ENCHANTED_CARROT',
        'POTATO' => 'ENCHANTED_POTATO',
        'MELON' => 'ENCHANTED_MELON',
        'PUMPKIN' => 'ENCHANTED_PUMPKIN',
        'CACTUS' => 'ENCHANTED_CACTUS_GREEN',
        'COCOA' => 'ENCHANTED_COCOA',
        'SUGAR' => 'ENCHANTED_SUGAR',
        'CANE' => 'ENCHANTED_SUGAR',
        'MUSHROOM' => 'ENCHANTED_BROWN_MUSHROOM',
    ];

    /** enchanted product => [raw bazaar id, crops per enchanted block] */
    private const CROP_ECONOMICS = [
        'ENCHANTED_WHEAT' => ['raw' => 'WHEAT', 'ratio' => 160],
        'ENCHANTED_CARROT' => ['raw' => 'CARROT', 'ratio' => 160],
        'ENCHANTED_POTATO' => ['raw' => 'POTATO', 'ratio' => 160],
        'ENCHANTED_NETHER_STALK' => ['raw' => 'NETHER_STALK', 'ratio' => 160],
        'ENCHANTED_MELON' => ['raw' => 'MELON', 'ratio' => 160],
        'ENCHANTED_PUMPKIN' => ['raw' => 'PUMPKIN', 'ratio' => 160],
        'ENCHANTED_SUGAR' => ['raw' => 'SUGAR', 'ratio' => 160],
        'ENCHANTED_CACTUS_GREEN' => ['raw' => 'CACTUS', 'ratio' => 160],
        'ENCHANTED_COCOA' => ['raw' => 'COCOA', 'ratio' => 160],
        'ENCHANTED_RED_MUSHROOM' => ['raw' => 'RED_MUSHROOM', 'ratio' => 160],
        'ENCHANTED_BROWN_MUSHROOM' => ['raw' => 'BROWN_MUSHROOM', 'ratio' => 160],
    ];

    private const RARITY_SPEED = [
        'COMMON' => 0.0,
        'UNCOMMON' => 0.15,
        'RARE' => 0.35,
        'EPIC' => 0.55,
        'LEGENDARY' => 0.85,
        'MYTHIC' => 1.1,
        'DIVINE' => 1.25,
        'SPECIAL' => 1.0,
        'VERY_SPECIAL' => 1.0,
    ];

    private const FARMING_ARMOR_HINTS = [
        'SORROW' => ['ff' => 40, 'speed' => 0.3],
        'RANCHER' => ['ff' => 25, 'speed' => 0.2],
        'MELON' => ['crop' => 'ENCHANTED_MELON', 'ff' => 15],
        'PUMPKIN' => ['crop' => 'ENCHANTED_PUMPKIN', 'ff' => 15],
        'CACTUS' => ['crop' => 'ENCHANTED_CACTUS_GREEN', 'ff' => 15],
        'FERMENTO' => ['ff' => 30, 'speed' => 0.25],
        'CROPIE' => ['ff' => 20, 'speed' => 0.15],
        'SQUASH' => ['ff' => 35, 'speed' => 0.2],
    ];

    private const FARMING_PET_HINTS = [
        'ELEPHANT' => ['ff' => 25],
        'RABBIT' => ['ff' => 15, 'speed' => 0.1],
        'MOOSHROOM' => ['ff' => 20],
        'BEE' => ['ff' => 10],
    ];

    /**
     * @return array{
     *   all_items: list<array<string, mixed>>,
     *   farming: array<string, mixed>,
     *   mining: array<string, mixed>,
     *   foraging: array<string, mixed>,
     *   fishing: array<string, mixed>,
     *   combat: array<string, mixed>
     * }
     */
    public function scan(array $profileData): array
    {
        $items = $this->collectAllItems($profileData);

        return [
            'all_items' => $items,
            'farming' => $this->scanFarming($items, $profileData),
            'mining' => $this->scanMining($items),
            'foraging' => $this->scanForaging($items),
            'fishing' => $this->scanFishing($items, $profileData),
            'combat' => $this->scanCombat($items, $profileData),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function collectAllItems(array $profileData): array
    {
        $out = [];

        $this->appendItems($out, $profileData['armor'] ?? []);
        $this->appendItems($out, $profileData['equipment'] ?? []);
        $this->appendItems($out, $profileData['weapons'] ?? []);
        $this->appendItems($out, $profileData['accessories'] ?? []);
        $this->appendItems($out, $profileData['inventory'] ?? []);
        $this->appendItems($out, $profileData['personal_vault'] ?? []);
        $this->appendItems($out, $profileData['fishing_bag'] ?? []);
        $this->appendItems($out, $profileData['potion_bag'] ?? []);
        $this->appendItems($out, $profileData['quiver'] ?? []);
        $this->appendItems($out, $profileData['talisman_bag'] ?? []);

        foreach ($profileData['enderchest'] ?? [] as $page) {
            if (is_array($page) && isset($page['items'])) {
                $this->appendItems($out, $page['items']);
            }
        }

        foreach ($profileData['storage'] ?? [] as $backpack) {
            if (is_array($backpack) && isset($backpack['items'])) {
                $this->appendItems($out, $backpack['items']);
            }
        }

        foreach ($profileData['wardrobe'] ?? [] as $set) {
            $this->appendItems($out, is_array($set) ? $set : []);
        }

        foreach ($profileData['museum'] ?? [] as $section) {
            if (! is_array($section)) {
                continue;
            }
            foreach ($section as $entry) {
                if (is_array($entry) && isset($entry['items'])) {
                    $this->appendItems($out, $entry['items']);
                }
            }
        }

        $this->appendItems($out, $profileData['rift_inventory'] ?? []);
        foreach ($profileData['rift_enderchest'] ?? [] as $page) {
            if (is_array($page) && isset($page['items'])) {
                $this->appendItems($out, $page['items']);
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function scanFarming(array $items, array $profileData): array
    {
        $hoes = [];
        foreach ($items as $item) {
            if ($this->isFarmingTool($item)) {
                $hoes[] = $this->describeFarmingTool($item);
            }
        }

        usort($hoes, static fn (array $a, array $b): int => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        $armorSets = [];
        $armorFfBonus = 0.0;
        $armorSpeedBonus = 0.0;
        $armorCropHint = null;

        foreach ($items as $item) {
            $hint = $this->matchFarmingArmor($item);
            if ($hint === null) {
                continue;
            }
            $armorSets[$hint['set']] = true;
            $armorFfBonus += $hint['ff'];
            $armorSpeedBonus += $hint['speed'];
            if ($hint['crop'] !== null) {
                $armorCropHint = $hint['crop'];
            }
        }

        $petFfBonus = 0.0;
        $petSpeedBonus = 0.0;
        $recommendedPetName = null;
        $petProfile = app(PetProfileDataService::class);
        $bestPetScore = -1.0;

        foreach ($profileData['pets']['pets'] ?? [] as $pet) {
            if (! is_array($pet)) {
                continue;
            }
            $enriched = isset($pet['profile']) ? $pet : $petProfile->enrich($pet);
            $petHint = $this->matchFarmingPet(
                (string) ($enriched['name'] ?? $enriched['type'] ?? ''),
                (string) ($enriched['type'] ?? '')
            );
            if ($petHint === null) {
                continue;
            }

            $profile = $enriched['profile'] ?? [];
            $level = (int) ($profile['level'] ?? $enriched['level']['level'] ?? 0);
            $maxLevel = (int) ($profile['maxLevel'] ?? 100);
            $levelFactor = $level < 1 ? 0.1 : min(1.0, $level / max(1, $maxLevel));
            $ffFromProfile = (float) (($profile['stats']['FmFrt']['value'] ?? 0));
            $score = ($petHint['ff'] + $petHint['speed']) * $levelFactor
                + $ffFromProfile * 0.05
                + $petProfile->scoreForActivity($enriched, 'farming') * 0.02;

            if ($score > $bestPetScore) {
                $bestPetScore = $score;
                $recommendedPetName = (string) ($enriched['name'] ?? $enriched['type'] ?? '');
                $petFfBonus = $petHint['ff'] * $levelFactor + min($ffFromProfile, 200);
                $petSpeedBonus = $petHint['speed'] * $levelFactor;
            }
        }

        $bestHoe = $hoes[0] ?? null;
        $recommendedCrop = $bestHoe['crop_product_id'] ?? $armorCropHint ?? 'ENCHANTED_WHEAT';

        return [
            'hoes' => $hoes,
            'best_hoe' => $bestHoe,
            'armor_sets' => array_keys($armorSets),
            'armor_ff_bonus' => $armorFfBonus,
            'armor_speed_bonus' => $armorSpeedBonus,
            'pet_ff_bonus' => $petFfBonus,
            'pet_speed_bonus' => $petSpeedBonus,
            'active_pet' => $recommendedPetName,
            'recommended_crop_product_id' => $recommendedCrop,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function scanMining(array $items): array
    {
        $picks = [];
        foreach ($items as $item) {
            if (! $this->isMiningTool($item)) {
                continue;
            }
            $id = strtoupper((string) ($item['skyblock_id'] ?? ''));
            $name = (string) ($item['name'] ?? '');
            $rarity = (string) ($item['rarity'] ?? 'COMMON');
            $score = ($this->raritySpeedBonus($rarity) + 1.0) * 10;
            if (str_contains($id, 'DIVAN') || str_contains($id, 'GEMSTONE')) {
                $score += 25;
            }
            if (str_contains($id, 'MITHRIL') || str_contains($name, 'Mithril')) {
                $score += 8;
            }
            $picks[] = [
                'name' => $name,
                'skyblock_id' => $id,
                'rarity' => $rarity,
                'score' => $score,
            ];
        }
        usort($picks, static fn (array $a, array $b): int => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return ['pickaxes' => $picks, 'best_pickaxe' => $picks[0] ?? null];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function scanForaging(array $items): array
    {
        $axes = [];
        foreach ($items as $item) {
            if (! $this->isForagingTool($item)) {
                continue;
            }
            $rarity = (string) ($item['rarity'] ?? 'COMMON');
            $axes[] = [
                'name' => (string) ($item['name'] ?? ''),
                'skyblock_id' => (string) ($item['skyblock_id'] ?? ''),
                'rarity' => $rarity,
                'score' => ($this->raritySpeedBonus($rarity) + 1.0) * 10,
            ];
        }
        usort($axes, static fn (array $a, array $b): int => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return ['axes' => $axes, 'best_axe' => $axes[0] ?? null];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function scanFishing(array $items, array $profileData): array
    {
        $rods = [];
        foreach ($items as $item) {
            if (! $this->isFishingTool($item)) {
                continue;
            }
            $rarity = (string) ($item['rarity'] ?? 'COMMON');
            $rods[] = [
                'name' => (string) ($item['name'] ?? ''),
                'skyblock_id' => (string) ($item['skyblock_id'] ?? ''),
                'rarity' => $rarity,
                'score' => ($this->raritySpeedBonus($rarity) + 1.0) * 10,
            ];
        }
        usort($rods, static fn (array $a, array $b): int => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return ['rods' => $rods, 'best_rod' => $rods[0] ?? null];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function scanCombat(array $items, array $profileData): array
    {
        $weapons = [];
        foreach ($profileData['weapons'] ?? [] as $w) {
            if (is_array($w)) {
                $weapons[] = $w;
            }
        }
        foreach ($items as $item) {
            if ($this->isCombatWeapon($item)) {
                $weapons[] = $item;
            }
        }

        return ['weapons' => $weapons];
    }

    /**
     * @param  list<array<string, mixed>>  $bucket
     * @param  array<int, array<string, mixed>|null>|list<array<string, mixed>|null>  $items
     */
    private function appendItems(array &$bucket, array $items): void
    {
        foreach ($items as $item) {
            if (! is_array($item) || empty($item['name'])) {
                continue;
            }
            $bucket[] = $item;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isFarmingTool(array $item): bool
    {
        $id = strtoupper((string) ($item['skyblock_id'] ?? ''));
        $name = strtoupper((string) ($item['name'] ?? ''));
        $category = strtoupper((string) ($item['category'] ?? ''));

        if ($category === 'HOE' || str_contains($category, 'FARMING')) {
            return true;
        }

        foreach (['HOE', 'CHOPPER', 'KNIFE', 'CUTTER', 'DICER'] as $needle) {
            if (str_contains($id, $needle) || str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function describeFarmingTool(array $item): array
    {
        $id = strtoupper((string) ($item['skyblock_id'] ?? ''));
        $name = (string) ($item['name'] ?? 'Hoe');
        $rarity = (string) ($item['rarity'] ?? 'COMMON');
        $crop = self::CROP_PRODUCT_BY_HOE[$id] ?? $this->inferCropFromId($id, $name);
        $speed = 3.2 + $this->raritySpeedBonus($rarity);
        $ffFromItem = $this->statFromItem($item, ['FmFrt', 'Farming Fortune']);
        $score = $speed * 8 + $ffFromItem + ($crop !== null ? 12 : 0);

        return [
            'name' => $name,
            'skyblock_id' => $id,
            'rarity' => $rarity,
            'crop_product_id' => $crop,
            'breaks_per_second' => round($speed, 2),
            'bonus_farming_fortune' => (int) round($ffFromItem),
            'score' => $score,
        ];
    }

    private function inferCropFromId(string $id, string $name): ?string
    {
        $haystack = $id.' '.$name;
        foreach (self::HOE_ID_PATTERNS as $needle => $crop) {
            if (str_contains($haystack, $needle)) {
                return $crop;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isMiningTool(array $item): bool
    {
        $id = strtoupper((string) ($item['skyblock_id'] ?? ''));
        $name = strtoupper((string) ($item['name'] ?? ''));
        $category = strtoupper((string) ($item['category'] ?? ''));

        return $category === 'PICKAXE'
            || str_contains($id, 'PICKAXE')
            || str_contains($id, 'DRILL')
            || str_contains($name, 'PICKAXE')
            || str_contains($name, 'DRILL');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isForagingTool(array $item): bool
    {
        $id = strtoupper((string) ($item['skyblock_id'] ?? ''));
        $category = strtoupper((string) ($item['category'] ?? ''));

        return $category === 'AXE' || str_contains($id, '_AXE') || str_contains($id, 'FORAGING');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isFishingTool(array $item): bool
    {
        $id = strtoupper((string) ($item['skyblock_id'] ?? ''));
        $category = strtoupper((string) ($item['category'] ?? ''));

        return $category === 'FISHING ROD' || str_contains($id, 'ROD') || str_contains($id, 'FISHING');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isCombatWeapon(array $item): bool
    {
        $category = strtoupper((string) ($item['category'] ?? ''));

        return in_array($category, ['SWORD', 'BOW', 'WAND', 'DUNGEON SWORD', 'DUNGEON BOW'], true);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{set: string, ff: float, speed: float, crop: ?string}|null
     */
    private function matchFarmingArmor(array $item): ?array
    {
        $id = strtoupper((string) ($item['skyblock_id'] ?? ''));
        $name = strtoupper((string) ($item['name'] ?? ''));

        foreach (self::FARMING_ARMOR_HINTS as $key => $bonus) {
            if (str_contains($id, $key) || str_contains($name, $key)) {
                return [
                    'set' => $key,
                    'ff' => (float) ($bonus['ff'] ?? 0),
                    'speed' => (float) ($bonus['speed'] ?? 0),
                    'crop' => $bonus['crop'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * @return array{ff: float, speed: float}|null
     */
    private function matchFarmingPet(string $name, string $type): ?array
    {
        $haystack = strtoupper($name.' '.$type);
        foreach (self::FARMING_PET_HINTS as $key => $bonus) {
            if (str_contains($haystack, $key)) {
                return [
                    'ff' => (float) ($bonus['ff'] ?? 0),
                    'speed' => (float) ($bonus['speed'] ?? 0),
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $keys
     */
    private function statFromItem(array $item, array $keys): float
    {
        $stats = $item['stats'] ?? [];
        if (! is_array($stats)) {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($keys as $key) {
            if (isset($stats[$key]['value'])) {
                $sum += (float) $stats[$key]['value'];
            }
        }

        return $sum;
    }

    private function raritySpeedBonus(string $rarity): float
    {
        return self::RARITY_SPEED[strtoupper($rarity)] ?? 0.0;
    }

    /**
     * @return array<string, array{raw: string, ratio: int}>
     */
    public static function cropEconomics(): array
    {
        return self::CROP_ECONOMICS;
    }
}
