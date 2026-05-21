<?php

namespace App\Services;

/**
 * Profile-aware coins/hour estimates using full inventory scan + bazaar prices.
 */
class MoneyMakingEstimatorService
{
    /** Hard ceiling so bad price data cannot show billions/hr */
    private const ABSOLUTE_MAX_COINS_PER_HOUR = 180_000_000;

    public function __construct(
        private readonly ProfileGearScanner $gearScanner,
        private readonly MoneyMakingMayorBoostService $mayorBoosts,
        private readonly MoneyMakingMethodGearPresenter $gearPresenter,
    ) {}

    /** @var array<string, array{id: string, title: string}> */
    private const TUTORIALS = [
        'farming' => [
            'id' => 'deKPdlAR8no',
            'title' => 'The BEST Farming Progression Guide For 2025 | Hypixel Skyblock',
        ],
        'mining' => [
            'id' => 'nXYrX33aoAc',
            'title' => 'How Much Money Does Mining Make In 2025 (Every Method) | Hypixel Skyblock',
        ],
        'zealots' => [
            'id' => 'YVIHL58OX2U',
            'title' => 'Guide on How To Grind Summoning Eyes Efficient | Hypixel Skyblock',
        ],
        'dungeons' => [
            'id' => 'nhlTEb0LU7c',
            'title' => 'The Best Dungeon Profit Guide You Need! Hypixel Skyblock',
        ],
        'slayer' => [
            'id' => 'h4-5EUvK6ec',
            'title' => 'The COMPLETE SLAYER GUIDE for EARLY GAME Players! | Hypixel Skyblock',
        ],
        'foraging' => [
            'id' => 'WGLfiOOJ500',
            'title' => 'The ULTIMATE Foraging Progression Guide! | Hypixel Skyblock',
        ],
        'fishing' => [
            'id' => 'CrMGxhTCZ_s',
            'title' => 'The FULLY COMPREHENSIVE Guide to Fishing Progression! (Hypixel Skyblock) *2025*',
        ],
        'garden' => [
            'id' => '4Gb3B8R3X44',
            'title' => 'The Best Crops To Farm | Hypixel Skyblock',
        ],
    ];

    private const ROUGH_GEMS = [
        'ROUGH_JADE_GEM',
        'ROUGH_SAPPHIRE_GEM',
        'ROUGH_RUBY_GEM',
        'ROUGH_AMETHYST_GEM',
        'ROUGH_TOPAZ_GEM',
        'ROUGH_JASPER_GEM',
        'ROUGH_OPAL_GEM',
        'ROUGH_ONYX_GEM',
        'ROUGH_AQUAMARINE_GEM',
        'ROUGH_CITRINE_GEM',
        'ROUGH_AMBER_GEM',
    ];

    private const FORAGING_LOGS = [
        'ENCHANTED_OAK_LOG' => 'ENCHANTED_OAK_LOG',
        'ENCHANTED_SPRUCE_LOG' => 'ENCHANTED_SPRUCE_LOG',
        'ENCHANTED_BIRCH_LOG' => 'ENCHANTED_BIRCH_LOG',
        'ENCHANTED_DARK_OAK_LOG' => 'ENCHANTED_DARK_OAK_LOG',
        'ENCHANTED_ACACIA_LOG' => 'ENCHANTED_ACACIA_LOG',
        'ENCHANTED_JUNGLE_LOG' => 'ENCHANTED_JUNGLE_LOG',
    ];

    /**
     * @return list<string>
     */
    public static function bazaarProductIds(): array
    {
        $rawIds = [];
        foreach (ProfileGearScanner::cropEconomics() as $meta) {
            $rawIds[] = $meta['raw'];
        }

        return array_values(array_unique(array_merge(
            array_keys(ProfileGearScanner::cropEconomics()),
            $rawIds,
            array_keys(self::FORAGING_LOGS),
            self::ROUGH_GEMS,
            ['SUMMONING_EYE', 'ENCHANTED_RAW_FISH', 'ENCHANTED_PRAWN', 'ENCHANTED_SHARK_FIN'],
        )));
    }

    /**
     * @param  array<string, float>  $prices
     * @param  array<string, mixed>  $mayorBoosts
     * @return list<array<string, mixed>>
     */
    public function estimate(array $profileData, array $prices, array $mayorBoosts): array
    {
        $prices = $this->adjustPricesForBazaarTax($prices, $mayorBoosts);
        $gear = $this->gearScanner->scan($profileData);
        $stats = $this->statMapFromPlayerStats($profileData['player_stats'] ?? []);
        $skills = $profileData['skills'] ?? [];

        $farmingLvl = (int) ($skills['farming']['level'] ?? 0);
        $miningLvl = (int) ($skills['mining']['level'] ?? 0);
        $combatLvl = (int) ($skills['combat']['level'] ?? 0);
        $foragingLvl = (int) ($skills['foraging']['level'] ?? 0);
        $fishingLvl = (int) ($skills['fishing']['level'] ?? 0);

        $ff = (float) ($stats['Farming Fortune'] ?? 0);
        $mnSpd = (float) ($stats['Mining Speed'] ?? 0);
        $mnFort = (float) ($stats['Mining Fortune'] ?? 0);
        $fgFort = (float) ($stats['Foraging Fortune'] ?? 0);
        $str = (float) ($stats['Strength'] ?? 0);
        $spd = (float) ($stats['Speed'] ?? 0);
        $scc = (float) ($stats['Sea Creature Chance'] ?? 0);
        $fshSpd = (float) ($stats['Fishing Speed'] ?? 0);

        $dungeons = $profileData['dungeons'] ?? [];
        $cataLvl = (int) ($dungeons['catacombs']['level']['level'] ?? 0);

        $slayers = $profileData['slayers']['slayers'] ?? [];
        $slayerLevels = [];
        foreach ($slayers as $row) {
            if (is_array($row) && isset($row['level']['currentLevel'])) {
                $slayerLevels[] = (int) $row['level']['currentLevel'];
            }
        }
        $avgSlayer = count($slayerLevels) > 0 ? array_sum($slayerLevels) / count($slayerLevels) : 0;
        $totalSlayerXp = (float) ($profileData['slayers']['total_slayer_xp'] ?? 0);

        $itemsScanned = count($gear['all_items']);
        $methods = [];

        $methods[] = $this->buildFarming($gear['farming'], $ff, $farmingLvl, $prices, $itemsScanned, $mayorBoosts);
        $methods[] = $this->buildGardenVisitors($gear['farming'], $ff, $farmingLvl, $mayorBoosts);
        $methods[] = $this->buildMining($gear['mining'], $mnSpd, $mnFort, $miningLvl, $prices, $mayorBoosts);
        $methods[] = $this->buildZealots($gear['combat'], $str, $spd, $combatLvl, $prices, $mayorBoosts);
        $methods[] = $this->buildDungeons($cataLvl, $avgSlayer, $gear, $mayorBoosts);
        $methods[] = $this->buildSlayer($avgSlayer, $totalSlayerXp, $combatLvl, $gear['combat'], $mayorBoosts);
        $methods[] = $this->buildForaging($gear['foraging'], $fgFort, $foragingLvl, $prices, $mayorBoosts);
        $methods[] = $this->buildFishing($gear['fishing'], $scc, $fshSpd, $fishingLvl, $prices, $mayorBoosts);

        usort($methods, static fn (array $a, array $b): int => ($b['coins_per_hour'] ?? 0) <=> ($a['coins_per_hour'] ?? 0));

        return $this->attachLoadouts($methods, $profileData, $gear);
    }

    /**
     * @param  list<array<string, mixed>>  $methods
     * @param  array<string, mixed>  $profileData
     * @param  array<string, mixed>  $gear
     * @return list<array<string, mixed>>
     */
    private function attachLoadouts(array $methods, array $profileData, array $gear): array
    {
        $farmingCrop = 'ENCHANTED_WHEAT';
        foreach ($methods as $row) {
            if (($row['id'] ?? '') === 'farming' && ! empty($row['best_crop_product_id'])) {
                $farmingCrop = (string) $row['best_crop_product_id'];
                break;
            }
        }

        $dungeons = $profileData['dungeons'] ?? [];
        $cataLvl = (int) ($dungeons['catacombs']['level']['level'] ?? 0);

        $out = [];
        foreach ($methods as $method) {
            $id = (string) ($method['id'] ?? '');
            $meta = [];
            if ($id === 'farming' || $id === 'garden') {
                $meta['best_crop_product_id'] = $method['best_crop_product_id'] ?? $farmingCrop;
            }
            if ($id === 'dungeons') {
                $meta['catacombs_level'] = $cataLvl;
            }

            $method['loadout'] = $this->gearPresenter->forMethod($id, $profileData, $gear, $meta);
            unset($method['gear'], $method['best_crop_product_id']);

            $out[] = $method;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildProfileSummary(string $username, array $profileData): array
    {
        $gear = $this->gearScanner->scan($profileData);
        $itemsScanned = count($gear['all_items']);

        $activePetItem = null;
        foreach ($profileData['pets']['pets'] ?? [] as $pet) {
            if (is_array($pet) && ! empty($pet['active'])) {
                $activePetItem = $pet;
                break;
            }
        }

        $weaponItem = null;
        $weapons = $profileData['weapons'] ?? [];
        if (is_array($weapons) && $weapons !== []) {
            $first = $weapons[0] ?? null;
            $weaponItem = is_array($first) ? $this->itemForApi($first) : null;
        }

        $armorItems = [];
        foreach ($profileData['armor'] ?? [] as $piece) {
            $apiItem = is_array($piece) ? $this->itemForApi($piece) : null;
            if ($apiItem !== null) {
                $armorItems[] = $apiItem;
            }
        }

        return [
            'username' => $username,
            'active_pet' => $activePetItem,
            'weapon' => $weaponItem,
            'armor' => $armorItems,
            'farming_tool' => $this->resolveHoeItem($gear['farming'], $gear['all_items']),
            'items_scanned' => $itemsScanned,
        ];
    }

    /**
     * @param  array<string, mixed>  $farmingGear
     * @param  list<array<string, mixed>>  $allItems
     */
    private function resolveHoeItem(array $farmingGear, array $allItems): ?array
    {
        $best = $farmingGear['best_hoe'] ?? null;
        if (! is_array($best)) {
            return null;
        }

        $skyblockId = strtoupper((string) ($best['skyblock_id'] ?? ''));
        $name = (string) ($best['name'] ?? '');

        foreach ($allItems as $item) {
            if (! is_array($item)) {
                continue;
            }
            if ($skyblockId !== '' && strtoupper((string) ($item['skyblock_id'] ?? '')) === $skyblockId) {
                return $this->itemForApi($item);
            }
            if ($name !== '' && (string) ($item['name'] ?? '') === $name) {
                return $this->itemForApi($item);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function itemForApi(array $item): ?array
    {
        if (empty($item['name'])) {
            return null;
        }

        $payload = [
            'name' => $item['name'],
            'rarity' => $item['rarity'] ?? strtolower((string) ($item['tier'] ?? 'COMMON')),
            'texture_path' => $item['texture_path'] ?? null,
            'skyblock_id' => $item['skyblock_id'] ?? null,
            'count' => (int) ($item['count'] ?? 1),
            'stars' => (int) ($item['stars'] ?? 0),
            'color' => $item['color'] ?? null,
        ];

        if (! empty($item['lore_html']) && is_array($item['lore_html'])) {
            $payload['lore_html'] = $item['lore_html'];
        }
        if (! empty($item['stats']) && is_array($item['stats'])) {
            $payload['stats'] = $item['stats'];
        }
        if (array_key_exists('reforge', $item)) {
            $payload['reforge'] = $item['reforge'];
        }
        if (array_key_exists('recombobulated', $item)) {
            $payload['recombobulated'] = $item['recombobulated'];
        }
        if (isset($item['item_value'])) {
            $payload['item_value'] = $item['item_value'];
        }
        if (! empty($item['enchantments']) && is_array($item['enchantments'])) {
            $payload['enchantments'] = $item['enchantments'];
        }
        if (! empty($item['category'])) {
            $payload['category'] = $item['category'];
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $playerStats
     * @return array<string, float>
     */
    private function statMapFromPlayerStats(array $playerStats): array
    {
        $map = [];
        foreach ($playerStats as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = $row['name'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }
            $map[$name] = (float) ($row['value'] ?? 0);
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $farmingGear
     * @param  array<string, float>  $prices
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $mayorBoosts
     */
    private function buildFarming(
        array $farmingGear,
        float $farmingFortune,
        int $farmingLevel,
        array $prices,
        int $itemsScanned,
        array $mayorBoosts,
    ): array {
        $petFf = (float) ($farmingGear['pet_ff_bonus'] ?? 0);
        $effectiveFf = min(900.0, $farmingFortune + $petFf);

        $hoe = $farmingGear['best_hoe'] ?? null;
        $bps = 2.6 + $farmingLevel * 0.035;
        if (is_array($hoe)) {
            $bps = max($bps, (float) ($hoe['breaks_per_second'] ?? $bps));
            $effectiveFf += (float) ($hoe['bonus_farming_fortune'] ?? 0);
        }
        $bps += (float) ($farmingGear['armor_speed_bonus'] ?? 0) * 1.8;
        $bps += (float) ($farmingGear['pet_speed_bonus'] ?? 0) * 1.8;
        $bps = min(8.5, $bps);

        $cropCandidates = $this->farmingCropCandidates($farmingGear, $prices);

        $bestCoins = 0.0;
        $bestCrop = 'ENCHANTED_WHEAT';
        $bestUnitValue = 0.0;

        foreach ($cropCandidates as $cropProductId) {
            $unitValue = $this->cropUnitSellValue($cropProductId, $prices);
            if ($unitValue <= 0) {
                continue;
            }
            $dropsPerBreak = 1.0 * (1.0 + $effectiveFf / 100.0);
            $perHour = $bps * 3600.0 * $dropsPerBreak * $unitValue;
            if ($perHour > $bestCoins) {
                $bestCoins = $perHour;
                $bestCrop = $cropProductId;
                $bestUnitValue = $unitValue;
            }
        }

        $bestCoins = $this->applyMayor($this->capCoins($bestCoins, $farmingLevel, 'farming'), 'farming', $mayorBoosts);

        $required = [
            ['label' => 'Farming Fortune', 'value' => (int) round($farmingFortune)],
            ['label' => 'Farming level', 'value' => $farmingLevel],
            ['label' => 'Break speed (model)', 'value' => round($bps, 1).'/s'],
            ['label' => 'Recommended crop', 'value' => $this->friendlyCropName($bestCrop)],
        ];

        if (is_array($hoe) && ! empty($hoe['name'])) {
            $required[] = ['label' => 'Best hoe (inventory)', 'value' => (string) $hoe['name']];
        }
        if (! empty($farmingGear['armor_sets'])) {
            $required[] = ['label' => 'Farming armor detected', 'value' => implode(', ', $farmingGear['armor_sets'])];
        }
        if (! empty($farmingGear['active_pet'])) {
            $required[] = ['label' => 'Recommended pet', 'value' => (string) $farmingGear['active_pet']];
        }
        $required[] = ['label' => 'Value per crop (model)', 'value' => (int) round($bestUnitValue).' coins'];
        $required = $this->appendMayorStats($required, 'farming', $mayorBoosts);

        return $this->methodRow(
            id: 'farming',
            name: 'Farming (Garden crops)',
            coinsPerHour: $bestCoins,
            summary: 'Scans '.$itemsScanned.' inventory items for hoes, farming armor, and pets; models garden breaks/s, Farming Fortune, bazaar crop value, and active mayor boosts.',
            required: $required,
            mayorBoosts: $mayorBoosts,
            activity: 'farming',
            extra: [
                'best_crop_product_id' => $bestCrop,
                'gear' => $farmingGear,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $farmingGear
     * @return list<string>
     */
    private function farmingCropCandidates(array $farmingGear, array $prices): array
    {
        $candidates = [];

        if (! empty($farmingGear['recommended_crop_product_id'])) {
            $candidates[] = $farmingGear['recommended_crop_product_id'];
        }
        if (is_array($farmingGear['best_hoe'] ?? null) && ! empty($farmingGear['best_hoe']['crop_product_id'])) {
            $candidates[] = $farmingGear['best_hoe']['crop_product_id'];
        }

        foreach (array_keys(ProfileGearScanner::cropEconomics()) as $cropId) {
            if (($prices[$cropId] ?? 0) > 0) {
                $candidates[] = $cropId;
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * Coins earned per raw crop unit (enchanted price / 160 vs raw insta-sell).
     */
    private function cropUnitSellValue(string $enchantedProductId, array $prices): float
    {
        $meta = ProfileGearScanner::cropEconomics()[$enchantedProductId] ?? null;
        if ($meta === null) {
            return (float) ($prices[$enchantedProductId] ?? 0);
        }

        $enchanted = (float) ($prices[$enchantedProductId] ?? 0);
        $raw = (float) ($prices[$meta['raw']] ?? 0);
        $ratio = max(1, (int) $meta['ratio']);
        $perCropEnchanted = $enchanted > 0 ? $enchanted / $ratio : 0.0;

        return max($raw, $perCropEnchanted);
    }

    private function friendlyCropName(string $productId): string
    {
        return ucwords(strtolower(str_replace(['ENCHANTED_', '_'], ['', ' '], $productId)));
    }

    /**
     * @param  array<string, mixed>  $farmingGear
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $mayorBoosts
     */
    private function buildGardenVisitors(array $farmingGear, float $farmingFortune, int $farmingLevel, array $mayorBoosts): array
    {
        $hoeScore = is_array($farmingGear['best_hoe'] ?? null) ? (float) ($farmingGear['best_hoe']['score'] ?? 0) : 0;
        $armorSets = count($farmingGear['armor_sets'] ?? []);
        $base = 80_000.0 + $farmingLevel * 28_000.0 + min(400.0, $farmingFortune) * 900.0 + $hoeScore * 2_500.0 + $armorSets * 40_000.0;
        $base = $this->applyMayor($this->capCoins($base, $farmingLevel, 'garden'), 'garden', $mayorBoosts);

        $required = [
            ['label' => 'Farming Fortune', 'value' => (int) round($farmingFortune)],
            ['label' => 'Farming level', 'value' => $farmingLevel],
        ];
        if ($armorSets > 0) {
            $required[] = ['label' => 'Garden armor sets', 'value' => (string) $armorSets];
        }
        $required = $this->appendMayorStats($required, 'garden', $mayorBoosts);

        return $this->methodRow(
            id: 'garden',
            name: 'Garden visitors & sales',
            coinsPerHour: $base,
            summary: 'Visitor/compost income scaled from farming level, fortune, garden gear, and mayor (e.g. Finnegan).',
            required: $required,
            mayorBoosts: $mayorBoosts,
            activity: 'garden',
        );
    }

    /**
     * @param  array<string, mixed>  $miningGear
     * @param  array<string, float>  $prices
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $mayorBoosts
     */
    private function buildMining(array $miningGear, float $miningSpeed, float $miningFortune, int $miningLevel, array $prices, array $mayorBoosts): array
    {
        $pick = $miningGear['best_pickaxe'] ?? null;
        $toolScore = is_array($pick) ? (float) ($pick['score'] ?? 0) : 0;

        $gemPrices = [];
        foreach (self::ROUGH_GEMS as $id) {
            if (($prices[$id] ?? 0) > 0) {
                $gemPrices[] = $prices[$id];
            }
        }
        $avgGem = count($gemPrices) > 0 ? array_sum($gemPrices) / count($gemPrices) : 0.0;

        $speedFactor = min(2.2, max(0.35, $miningSpeed / 500.0));
        $fortMult = 1.0 + min(600.0, $miningFortune) / 100.0;
        $levelBoost = 1.0 + $miningLevel * 0.01;
        $toolBoost = 1.0 + $toolScore / 40.0;

        $roughPerHour = 28.0 * $speedFactor * $fortMult * $levelBoost * $toolBoost;
        $coins = $this->applyMayor($this->capCoins($roughPerHour * $avgGem, $miningLevel, 'mining'), 'mining', $mayorBoosts);

        $required = [
            ['label' => 'Mining Speed', 'value' => (int) round($miningSpeed)],
            ['label' => 'Mining Fortune', 'value' => (int) round($miningFortune)],
            ['label' => 'Mining level', 'value' => $miningLevel],
        ];
        if (is_array($pick) && ! empty($pick['name'])) {
            $required[] = ['label' => 'Best pickaxe (inventory)', 'value' => (string) $pick['name']];
        }
        $required = $this->appendMayorStats($required, 'mining', $mayorBoosts);

        return $this->methodRow(
            id: 'mining',
            name: 'Mining (gemstones)',
            coinsPerHour: $coins,
            summary: 'Inventory pickaxe/drill, mining stats, rough gem bazaar prices, and mayor (e.g. Cole Mining Fiesta).',
            required: $required,
            mayorBoosts: $mayorBoosts,
            activity: 'mining',
            extra: ['gear' => $miningGear],
        );
    }

    /**
     * @param  array<string, float>  $prices
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $mayorBoosts
     */
    private function buildZealots(array $combatGear, float $strength, float $speed, int $combatLevel, array $prices, array $mayorBoosts): array
    {
        $weapon = null;
        foreach ($combatGear['weapons'] ?? [] as $w) {
            if (is_array($w) && ! empty($w['name'])) {
                $weapon = (string) $w['name'];
                break;
            }
        }

        $eyePrice = $prices['SUMMONING_EYE'] ?? 0.0;
        $killsPerHour = min(120.0, 32.0 + $combatLevel * 0.9 + $strength / 200.0 + $speed / 30.0);
        $dropChance = 1.0 / 420.0;
        $eyesPerHour = $killsPerHour * $dropChance;
        $coins = $this->applyMayor($this->capCoins($eyesPerHour * $eyePrice, $combatLevel, 'combat'), 'zealots', $mayorBoosts);

        $required = [
            ['label' => 'Combat level', 'value' => $combatLevel],
            ['label' => 'Strength', 'value' => (int) round($strength)],
            ['label' => 'Speed', 'value' => (int) round($speed)],
            ['label' => 'Summoning Eye price', 'value' => $eyePrice > 0 ? (int) round($eyePrice).' coins' : 'n/a'],
        ];
        if ($weapon !== null) {
            $required[] = ['label' => 'Weapon', 'value' => $weapon];
        }
        $required = $this->appendMayorStats($required, 'zealots', $mayorBoosts);

        return $this->methodRow(
            id: 'zealots',
            name: 'Combat — End zealots',
            coinsPerHour: $coins,
            summary: 'Zealot farming from combat gear, eye bazaar price, and mayor (e.g. Diana ritual).',
            required: $required,
            mayorBoosts: $mayorBoosts,
            activity: 'zealots',
        );
    }

    /**
     * @param  array<string, mixed>  $gear
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $mayorBoosts
     */
    private function buildDungeons(int $catacombsLevel, float $avgSlayerLevel, array $gear, array $mayorBoosts): array
    {
        $runsPerHour = min(5.0, 1.0 + $catacombsLevel / 18.0);
        $profitPerRun = 180_000.0 + $catacombsLevel * 75_000.0 + $avgSlayerLevel * 10_000.0;
        $coins = $this->applyMayor($this->capCoins($runsPerHour * $profitPerRun, $catacombsLevel, 'dungeons'), 'dungeons', $mayorBoosts);

        $required = $this->appendMayorStats([
            ['label' => 'Catacombs level', 'value' => $catacombsLevel],
            ['label' => 'Avg slayer tier', 'value' => (int) round($avgSlayerLevel)],
            ['label' => 'Items scanned', 'value' => count($gear['all_items'] ?? [])],
        ], 'dungeons', $mayorBoosts);

        return $this->methodRow(
            id: 'dungeons',
            name: 'Catacombs dungeons',
            coinsPerHour: $coins,
            summary: 'Dungeon profit/run from Catacombs level, slayer tiers, and mayor (e.g. Paul, Derpy).',
            required: $required,
            mayorBoosts: $mayorBoosts,
            activity: 'dungeons',
        );
    }

    /**
     * @param  array<string, mixed>  $combatGear
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $mayorBoosts
     */
    private function buildSlayer(float $avgSlayerLevel, float $totalSlayerXp, int $combatLevel, array $combatGear, array $mayorBoosts): array
    {
        $weaponBonus = count($combatGear['weapons'] ?? []) > 0 ? 120_000.0 : 0.0;
        $coins = 300_000.0 + $avgSlayerLevel * 70_000.0 + min(8_000_000.0, $totalSlayerXp / 900.0) + $combatLevel * 8_000.0 + $weaponBonus;
        $coins = $this->applyMayor($this->capCoins($coins, (int) round($avgSlayerLevel * 10 + $combatLevel), 'slayer'), 'slayer', $mayorBoosts);

        $required = $this->appendMayorStats([
            ['label' => 'Avg slayer tier', 'value' => (int) round($avgSlayerLevel)],
            ['label' => 'Total slayer XP', 'value' => (int) round($totalSlayerXp)],
            ['label' => 'Combat level', 'value' => $combatLevel],
        ], 'slayer', $mayorBoosts);

        return $this->methodRow(
            id: 'slayer',
            name: 'Slayer bosses',
            coinsPerHour: $coins,
            summary: 'Slayer income from tiers, XP, combat weapons in inventory, and mayor (e.g. Aatrox).',
            required: $required,
            mayorBoosts: $mayorBoosts,
            activity: 'slayer',
        );
    }

    /**
     * @param  array<string, mixed>  $foragingGear
     * @param  array<string, float>  $prices
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $mayorBoosts
     */
    private function buildForaging(array $foragingGear, float $foragingFortune, int $foragingLevel, array $prices, array $mayorBoosts): array
    {
        $axe = $foragingGear['best_axe'] ?? null;
        $bps = min(7.0, 2.4 + $foragingLevel * 0.03 + (is_array($axe) ? (float) ($axe['score'] ?? 0) / 25.0 : 0));
        $mult = 1.0 + min(500.0, $foragingFortune) / 100.0;

        $best = 0.0;
        foreach (self::FORAGING_LOGS as $productId) {
            $sell = $prices[$productId] ?? 0.0;
            if ($sell <= 0) {
                continue;
            }
            $perHour = $bps * 3600.0 * $mult * ($sell / 160.0);
            $best = max($best, $perHour);
        }
        $best = $this->applyMayor($this->capCoins($best, $foragingLevel, 'foraging'), 'foraging', $mayorBoosts);

        $required = [
            ['label' => 'Foraging Fortune', 'value' => (int) round($foragingFortune)],
            ['label' => 'Foraging level', 'value' => $foragingLevel],
        ];
        if (is_array($axe) && ! empty($axe['name'])) {
            $required[] = ['label' => 'Best axe (inventory)', 'value' => (string) $axe['name']];
        }
        $required = $this->appendMayorStats($required, 'foraging', $mayorBoosts);

        return $this->methodRow(
            id: 'foraging',
            name: 'Foraging',
            coinsPerHour: $best,
            summary: 'Foraging from fortune, level, axe in inventory, bazaar logs, and mayor bazaar buffs.',
            required: $required,
            mayorBoosts: $mayorBoosts,
            activity: 'foraging',
            extra: ['gear' => $foragingGear],
        );
    }

    /**
     * @param  array<string, mixed>  $fishingGear
     * @param  array<string, float>  $prices
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $mayorBoosts
     */
    private function buildFishing(array $fishingGear, float $scc, float $fishingSpeed, int $fishingLevel, array $prices, array $mayorBoosts): array
    {
        $rod = $fishingGear['best_rod'] ?? null;
        $rodBoost = is_array($rod) ? 1.0 + (float) ($rod['score'] ?? 0) / 50.0 : 1.0;

        $fishPrice = max(
            (float) ($prices['ENCHANTED_SHARK_FIN'] ?? 0) / 160.0,
            (float) ($prices['ENCHANTED_PRAWN'] ?? 0) / 160.0,
            (float) ($prices['ENCHANTED_RAW_FISH'] ?? 0) / 160.0,
        );

        $catches = (380.0 + $fishingLevel * 12.0 + $fishingSpeed / 8.0) * $rodBoost;
        $treasureMult = 1.0 + min(120.0, $scc) / 100.0 * 0.45;
        $coins = $this->applyMayor($this->capCoins($catches * $treasureMult * max(80.0, $fishPrice), $fishingLevel, 'fishing'), 'fishing', $mayorBoosts);

        $required = [
            ['label' => 'Fishing level', 'value' => $fishingLevel],
            ['label' => 'Fishing Speed', 'value' => (int) round($fishingSpeed)],
            ['label' => 'Sea Creature Chance', 'value' => round($scc, 1).'%'],
        ];
        if (is_array($rod) && ! empty($rod['name'])) {
            $required[] = ['label' => 'Best rod (inventory)', 'value' => (string) $rod['name']];
        }
        $required = $this->appendMayorStats($required, 'fishing', $mayorBoosts);

        return $this->methodRow(
            id: 'fishing',
            name: 'Fishing',
            coinsPerHour: $coins,
            summary: 'Fishing rod from inventory, sea creature stats, fish bazaar prices, and mayor (e.g. Marina).',
            required: $required,
            mayorBoosts: $mayorBoosts,
            activity: 'fishing',
            extra: ['gear' => $fishingGear],
        );
    }

    /**
     * @param  array<string, float>  $prices
     * @param  array<string, mixed>  $mayorBoosts
     * @return array<string, float>
     */
    private function adjustPricesForBazaarTax(array $prices, array $mayorBoosts): array
    {
        $baselineTax = 0.0125;
        $tax = (float) ($mayorBoosts['bazaar_instant_sell_tax_rate'] ?? $baselineTax);
        $factor = (1.0 - $tax) / (1.0 - $baselineTax);

        if (abs($factor - 1.0) < 0.0001) {
            return $prices;
        }

        $adjusted = [];
        foreach ($prices as $id => $value) {
            $adjusted[$id] = (float) $value * $factor;
        }

        return $adjusted;
    }

    /**
     * @param  array<string, mixed>  $mayorBoosts
     */
    private function applyMayor(float $coins, string $activity, array $mayorBoosts): float
    {
        return $coins * $this->mayorBoosts->multiplierFor($activity, $mayorBoosts);
    }

    /**
     * @param  list<array{label: string, value: int|string}>  $required
     * @param  array<string, mixed>  $mayorBoosts
     * @return list<array{label: string, value: int|string}>
     */
    private function appendMayorStats(array $required, string $activity, array $mayorBoosts): array
    {
        $mult = $this->mayorBoosts->multiplierFor($activity, $mayorBoosts);
        if ($mult > 1.001) {
            $required[] = [
                'label' => 'Mayor ('.($mayorBoosts['name'] ?? 'Unknown').')',
                'value' => $this->mayorBoosts->formatMultiplierPercent($mult),
            ];
        }

        return $required;
    }

    private function capCoins(float $coins, int $progressionLevel, string $activity): float
    {
        $tierCap = match ($activity) {
            'farming' => match (true) {
                $progressionLevel >= 45 => 95_000_000,
                $progressionLevel >= 30 => 42_000_000,
                $progressionLevel >= 15 => 14_000_000,
                default => 5_000_000,
            },
            'garden' => match (true) {
                $progressionLevel >= 40 => 35_000_000,
                $progressionLevel >= 25 => 18_000_000,
                default => 8_000_000,
            },
            'mining' => match (true) {
                $progressionLevel >= 40 => 55_000_000,
                $progressionLevel >= 25 => 28_000_000,
                default => 10_000_000,
            },
            'combat', 'slayer', 'dungeons' => match (true) {
                $progressionLevel >= 35 => 40_000_000,
                $progressionLevel >= 20 => 18_000_000,
                default => 8_000_000,
            },
            'foraging', 'fishing' => match (true) {
                $progressionLevel >= 35 => 25_000_000,
                $progressionLevel >= 20 => 12_000_000,
                default => 5_000_000,
            },
            default => 20_000_000,
        };

        return min(self::ABSOLUTE_MAX_COINS_PER_HOUR, max(0.0, $coins), (float) $tierCap);
    }

    /**
     * @param  list<array{label: string, value: int|string}>  $required
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    /**
     * @param  list<array{label: string, value: int|string}>  $required
     * @param  array<string, mixed>  $mayorBoosts
     * @param  array<string, mixed>  $extra
     */
    private function methodRow(
        string $id,
        string $name,
        float $coinsPerHour,
        string $summary,
        array $required,
        array $mayorBoosts = [],
        string $activity = '',
        array $extra = [],
    ): array {
        $tutorial = self::TUTORIALS[$id] ?? null;
        $youtube = null;
        if (is_array($tutorial)) {
            $vid = $tutorial['id'];
            $youtube = [
                'video_id' => $vid,
                'title' => $tutorial['title'],
                'watch_url' => 'https://www.youtube.com/watch?v='.$vid,
                'thumbnail_url' => 'https://i.ytimg.com/vi/'.$vid.'/mqdefault.jpg',
            ];
        }

        $mayorMultiplier = $activity !== ''
            ? $this->mayorBoosts->multiplierFor($activity, $mayorBoosts)
            : 1.0;

        return array_merge([
            'id' => $id,
            'name' => $name,
            'coins_per_hour' => (int) round(max(0.0, $coinsPerHour)),
            'summary' => $summary,
            'required_stats' => $required,
            'mayor_multiplier' => round($mayorMultiplier, 4),
            'mayor_boosts' => $activity !== '' ? $this->mayorBoosts->boostsForActivity($activity, $mayorBoosts) : [],
            'youtube' => $youtube,
        ], $extra);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSkillsSummary(array $skills): array
    {
        $keys = ['farming', 'mining', 'combat', 'foraging', 'fishing', 'enchanting'];
        $out = [];
        foreach ($keys as $key) {
            if (! isset($skills[$key]['level'])) {
                continue;
            }
            $out[$key] = (int) $skills[$key]['level'];
        }

        return $out;
    }
}
