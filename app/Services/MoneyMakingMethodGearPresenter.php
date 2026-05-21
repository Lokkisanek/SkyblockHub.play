<?php

namespace App\Services;

/**
 * Builds per-method recommended gear layouts for the Money Making UI.
 */
class MoneyMakingMethodGearPresenter
{
    private const MAX_OWNED_EXTRAS = 3;

    /** Mobility / teleport items — never shown as main weapon. */
    private const MOBILITY_KEYWORDS = [
        'ASPECT_OF_THE_VOID',
        'ASPECT_OF_THE_END',
        'STARRED_ASPECT_OF_THE',
        'ETHERWARP',
        'ETHER_TRANSMISSION',
        'SPIRIT_LEAP',
    ];

    /** Preferred main-weapon patterns per dungeon class (inventory match). */
    private const DUNGEON_CLASS_WEAPON_KEYWORDS = [
        'archer' => ['SHORTBOW', 'LONGBOW', 'BOW', 'JUJU', 'TERMINATOR', 'MACHINE_GUN', 'SOULS_REBOUND', 'LAST_BREATH', 'VENOM', 'ARTISANAL', 'MOSQUITO', 'SAUERKRAUT'],
        'mage' => ['HYPERION', 'ASTRAEA', 'SCYLLA', 'VALKYRIE', 'STAFF', 'SCEPTRE', 'WAND', 'BONZO', 'FIRE_FREEZE', 'MAGMA', 'VOODOO', 'FROZEN_SCYTHE'],
        'berserk' => ['HYPERION', 'VALKYRIE', 'SCYLLA', 'ASTRAEA', 'LIVID', 'VIPER', 'GIANT', 'FEL_SWORD', 'DARK_CLAYMORE', 'ATOMINIZER', 'BLADE', 'CLAYMORE', 'SCYTHE', 'AXE_OF_THE_SHREDDED', 'VOIDEDGE'],
        'healer' => ['HYPERION', 'STAFF', 'SCEPTRE', 'WAND', 'MENDER', 'BONZO', 'SPIRIT_SCEPTRE', 'WAND_OF'],
        'tank' => ['HYPERION', 'SHADOW_FURY', 'GIANT', 'ADAPTIVE', 'TANK'],
    ];
    public function __construct(
        private readonly PetProfileDataService $petProfile,
    ) {}
    /** Armor set keyword => priority score per activity. */
    private const ARMOR_SCORES_BY_CATEGORY = [
        'farming' => [
            'FERMENTO' => 100,
            'SQUASH' => 95,
            'SORROW' => 90,
            'CROPIE' => 85,
            'RANCHER' => 80,
            'MELON' => 70,
            'PUMPKIN' => 70,
            'CACTUS' => 70,
        ],
        'mining' => [
            'HEAT' => 100,
            'DIVAN' => 95,
            'GLACITE' => 90,
            'MINER' => 85,
            'MITHRIL' => 80,
            'SORROW' => 75,
        ],
        'dungeon' => [
            'NECRON' => 100,
            'STORM' => 98,
            'GOLDOR' => 95,
            'MAXOR' => 93,
            'FINAL' => 88,
            'SHADOW' => 85,
            'ADAPTIVE' => 72,
            'WITHER' => 68,
            'HOLLOW' => 65,
        ],
        'combat' => [
            'CRIMSON' => 100,
            'AURORA' => 99,
            'TERROR' => 98,
            'NECRON' => 92,
            'STORM' => 90,
            'SHADOW' => 85,
            'FINAL' => 82,
            'STRONG' => 75,
            'SUPERIOR' => 72,
            'UNSTABLE' => 65,
        ],
        'foraging' => [
            'MANGROVE' => 100,
            'FOREST' => 90,
            'LEAFLET' => 80,
            'ROSETTA' => 75,
        ],
        'fishing' => [
            'SHARK' => 100,
            'SPONGE' => 95,
            'RADIOACTIVE' => 90,
            'SALMON' => 85,
            'DIVER' => 80,
        ],
    ];

    private const ARMOR_CATEGORIES = [
        'HELMET', 'CHESTPLATE', 'LEGGINGS', 'BOOTS',
        'DUNGEON HELMET', 'DUNGEON CHESTPLATE', 'DUNGEON LEGGINGS', 'DUNGEON BOOTS',
    ];

    /** Equipment keyword => priority score per activity. */
    private const EQUIPMENT_SCORES_BY_CATEGORY = [
        'farming' => [
            'CROPIE' => 90,
            'RANCHER' => 85,
            'FERMENTO' => 80,
            'SQUASH' => 75,
            'CONTAGION' => 65,
            'BELT' => 60,
            'CLOAK' => 60,
            'NECKLACE' => 60,
        ],
        'mining' => [
            'HEAT' => 95,
            'MITHRIL' => 90,
            'MINER' => 88,
            'GLACITE' => 85,
            'DIVAN' => 82,
            'BELT' => 65,
            'CLOAK' => 65,
            'NECKLACE' => 65,
        ],
        'dungeon' => [
            'IMPLOSION' => 100,
            'MOLTEN' => 95,
            'CONTAGION' => 92,
            'VANQUISHED' => 88,
            'GHAST' => 85,
            'BELT' => 70,
            'CLOAK' => 70,
            'NECKLACE' => 70,
        ],
        'combat' => [
            'IMPLOSION' => 100,
            'MOLTEN' => 95,
            'CONTAGION' => 92,
            'VANQUISHED' => 88,
            'BELT' => 75,
            'CLOAK' => 75,
            'NECKLACE' => 75,
        ],
        'foraging' => [
            'MANGROVE' => 95,
            'FOREST' => 90,
            'LEAFLET' => 85,
            'BELT' => 65,
            'CLOAK' => 65,
            'NECKLACE' => 65,
        ],
        'fishing' => [
            'SHARK' => 100,
            'SPONGE' => 95,
            'RADIOACTIVE' => 90,
            'SALMON' => 85,
            'DIVER' => 80,
            'BELT' => 70,
            'CLOAK' => 70,
            'NECKLACE' => 70,
        ],
    ];

    private const TIER_SCORE = [
        'COMMON' => 0,
        'UNCOMMON' => 1,
        'RARE' => 2,
        'EPIC' => 3,
        'LEGENDARY' => 4,
        'MYTHIC' => 5,
    ];

    /**
     * @param  array<string, mixed>  $profileData
     * @param  array<string, mixed>  $gearScan  Full {@see ProfileGearScanner::scan()} result
     * @param  array<string, mixed>  $methodMeta  Method-specific keys (crop, hoe, pickaxe, etc.)
     * @return array{play_tip: string, sections: list<array<string, mixed>>, notes: list<string>}
     */
    public function forMethod(string $methodId, array $profileData, array $gearScan, array $methodMeta = []): array
    {
        return match ($methodId) {
            'farming' => $this->farming($profileData, $gearScan, $methodMeta),
            'garden' => $this->garden($profileData, $gearScan, $methodMeta),
            'mining' => $this->mining($profileData, $gearScan, $methodMeta),
            'zealots' => $this->zealots($profileData, $gearScan, $methodMeta),
            'dungeons' => $this->dungeons($profileData, $gearScan, $methodMeta),
            'slayer' => $this->slayer($profileData, $gearScan, $methodMeta),
            'foraging' => $this->foraging($profileData, $gearScan, $methodMeta),
            'fishing' => $this->fishing($profileData, $gearScan, $methodMeta),
            default => ['play_tip' => '', 'sections' => [], 'notes' => []],
        };
    }

    /**
     * @param  array<string, mixed>  $profileData
     * @param  array<string, mixed>  $gearScan
     * @param  array<string, mixed>  $meta
     * @return array{play_tip: string, sections: list<array<string, mixed>>, notes: list<string>}
     */
    private function farming(array $profileData, array $gearScan, array $meta): array
    {
        $crop = $this->friendlyCrop((string) ($meta['best_crop_product_id'] ?? 'ENCHANTED_WHEAT'));
        $hoeItem = $this->resolveHoeItem($gearScan, $profileData);
        $gear = $this->resolveGear($profileData, $gearScan, 'farming');
        $pet = $this->bestPetForCategory($profileData, 'farming');

        $sections = [];
        $this->appendGearSection($sections, $gear);
        $this->appendMethodToolsSection($sections, 'farming', $profileData, $gearScan, $hoeItem);
        if ($pet !== null) {
            $sections[] = $this->petSection($pet);
        }

        $notes = [];
        if ($hoeItem === null) {
            $notes[] = 'No farming hoe found in inventory — equip your best crop hoe before farming.';
        }
        if ($gear['armor'] === [] && $gear['equipment'] === []) {
            $notes[] = 'No dedicated farming gear found in inventory or wardrobe — consider Fermento, Squash, or Sorrow.';
        }

        return [
            'play_tip' => "Farm {$crop} in your Garden with the hoe below. Break crops, compress to enchanted blocks, and insta-sell on the Bazaar for the modeled coins/hour.",
            'sections' => $sections,
            'notes' => $notes,
        ];
    }

    /**
     * @param  array<string, mixed>  $profileData
     * @param  array<string, mixed>  $gearScan
     * @param  array<string, mixed>  $meta
     * @return array{play_tip: string, sections: list<array<string, mixed>>, notes: list<string>}
     */
    private function garden(array $profileData, array $gearScan, array $meta): array
    {
        $farming = $this->farming($profileData, $gearScan, $meta);
        $farming['play_tip'] = 'Complete Garden visitors, sell crops, and maintain pests/compost. Use the same farming setup — visitors scale with Farming level and fortune.';

        return $farming;
    }

    /**
     * @param  array<string, mixed>  $profileData
     * @param  array<string, mixed>  $gearScan
     * @param  array<string, mixed>  $meta
     * @return array{play_tip: string, sections: list<array<string, mixed>>, notes: list<string>}
     */
    private function mining(array $profileData, array $gearScan, array $meta): array
    {
        $pick = $gearScan['mining']['best_pickaxe'] ?? null;
        $pickItem = $this->findItemInProfile($profileData, $gearScan['all_items'] ?? [], is_array($pick) ? $pick : null);
        $gear = $this->resolveGear($profileData, $gearScan, 'mining');
        $pet = $this->bestPetForCategory($profileData, 'mining');

        $sections = [];
        $this->appendGearSection($sections, $gear);
        $this->appendMethodToolsSection($sections, 'mining', $profileData, $gearScan, $pickItem);
        if ($pet !== null) {
            $sections[] = $this->petSection($pet);
        }

        $notes = [];
        if ($pickItem === null) {
            $notes[] = 'No pickaxe or drill found in your inventories — equip a Crystal Hollows or Dwarven Mines setup.';
        }

        return [
            'play_tip' => 'Mine gemstones or ores in Crystal Hollows / Glacite with mining fortune gear. Sell rough gems on the Bazaar.',
            'sections' => $sections,
            'notes' => $notes,
        ];
    }

    /**
     * @param  array<string, mixed>  $profileData
     * @param  array<string, mixed>  $gearScan
     * @param  array<string, mixed>  $meta
     * @return array{play_tip: string, sections: list<array<string, mixed>>, notes: list<string>}
     */
    private function zealots(array $profileData, array $gearScan, array $meta): array
    {
        $gear = $this->resolveGear($profileData, $gearScan, 'combat');
        $pet = $this->bestPetForCategory($profileData, 'combat');

        $sections = [];
        $this->appendGearSection($sections, $gear);
        $this->appendMethodToolsSection($sections, 'zealots', $profileData, $gearScan);
        if ($pet !== null) {
            $sections[] = $this->petSection($pet);
        }

        $mainWeapon = $this->resolveMainWeapon('zealots', $profileData, $gearScan);

        return [
            'play_tip' => 'Grind End island zealots for Summoning Eyes. Use strong combat damage, speed, and sell eyes on Bazaar or AH.',
            'sections' => $sections,
            'notes' => $mainWeapon === null ? ['No combat weapon found in your inventories.'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $profileData
     * @param  array<string, mixed>  $gearScan
     * @param  array<string, mixed>  $meta
     * @return array{play_tip: string, sections: list<array<string, mixed>>, notes: list<string>}
     */
    private function dungeons(array $profileData, array $gearScan, array $meta): array
    {
        $gear = $this->resolveGear($profileData, $gearScan, 'dungeon');
        $pet = $this->bestPetForCategory($profileData, 'combat');

        $sections = [];
        $this->appendGearSection($sections, $gear);
        $this->appendMethodToolsSection($sections, 'dungeons', $profileData, $gearScan);
        if ($pet !== null) {
            $sections[] = $this->petSection($pet);
        }

        $cata = (int) ($meta['catacombs_level'] ?? 0);

        return [
            'play_tip' => "Run Catacombs floors (F{$cata}+) with secrets and chest loot. Sell valuable drops on AH — profit scales with class level and run speed.",
            'sections' => $sections,
            'notes' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $profileData
     * @param  array<string, mixed>  $gearScan
     * @param  array<string, mixed>  $meta
     * @return array{play_tip: string, sections: list<array<string, mixed>>, notes: list<string>}
     */
    private function slayer(array $profileData, array $gearScan, array $meta): array
    {
        $gear = $this->resolveGear($profileData, $gearScan, 'combat');
        $pet = $this->bestPetForCategory($profileData, 'combat');

        $sections = [];
        $this->appendGearSection($sections, $gear);
        $this->appendMethodToolsSection($sections, 'slayer', $profileData, $gearScan);
        if ($pet !== null) {
            $sections[] = $this->petSection($pet);
        }

        return [
            'play_tip' => 'Farm Slayer bosses at your unlocked tier. Focus on drop value and recipe unlocks — combat DPS and pet matter most.',
            'sections' => $sections,
            'notes' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $profileData
     * @param  array<string, mixed>  $gearScan
     * @param  array<string, mixed>  $meta
     * @return array{play_tip: string, sections: list<array<string, mixed>>, notes: list<string>}
     */
    private function foraging(array $profileData, array $gearScan, array $meta): array
    {
        $axe = $gearScan['foraging']['best_axe'] ?? null;
        $axeItem = $this->findItemInProfile($profileData, $gearScan['all_items'] ?? [], is_array($axe) ? $axe : null);
        $gear = $this->resolveGear($profileData, $gearScan, 'foraging');
        $pet = $this->bestPetForCategory($profileData, 'foraging');

        $sections = [];
        $this->appendGearSection($sections, $gear);
        $this->appendMethodToolsSection($sections, 'foraging', $profileData, $gearScan, $axeItem);
        if ($pet !== null) {
            $sections[] = $this->petSection($pet);
        }

        return [
            'play_tip' => 'Chop trees in Galatea / Park with foraging fortune. Sell enchanted logs on the Bazaar.',
            'sections' => $sections,
            'notes' => $axeItem === null ? ['No foraging axe found in your inventories.'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $profileData
     * @param  array<string, mixed>  $gearScan
     * @param  array<string, mixed>  $meta
     * @return array{play_tip: string, sections: list<array<string, mixed>>, notes: list<string>}
     */
    private function fishing(array $profileData, array $gearScan, array $meta): array
    {
        $rod = $gearScan['fishing']['best_rod'] ?? null;
        $rodItem = $this->findItemInProfile($profileData, $gearScan['all_items'] ?? [], is_array($rod) ? $rod : null);
        $gear = $this->resolveGear($profileData, $gearScan, 'fishing');
        $pet = $this->bestPetForCategory($profileData, 'fishing');

        $sections = [];
        $this->appendGearSection($sections, $gear);
        $this->appendMethodToolsSection($sections, 'fishing', $profileData, $gearScan, $rodItem);
        if ($pet !== null) {
            $sections[] = $this->petSection($pet);
        }

        return [
            'play_tip' => 'Fish in Crimson Isle / Hub with sea creature chance gear. Kill sea creatures and sell drops (shark fins, etc.).',
            'sections' => $sections,
            'notes' => $rodItem === null ? ['No fishing rod found in your inventories.'] : [],
        ];
    }

    /**
     * @return array{armor: list<array<string, mixed>>, equipment: list<array<string, mixed>>}
     */
    private function resolveGear(array $profileData, array $gearScan, string $category): array
    {
        return [
            'armor' => $this->bestArmorForCategory($profileData, $gearScan, $category),
            'equipment' => $this->bestEquipmentForCategory($profileData, $gearScan, $category),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @param  array{armor: list<array<string, mixed>>, equipment: list<array<string, mixed>>}  $gear
     */
    private function appendGearSection(array &$sections, array $gear): void
    {
        if ($gear['armor'] === [] && $gear['equipment'] === []) {
            return;
        }

        $sections[] = $this->gearSection($gear['armor'], $gear['equipment']);
    }

    /**
     * @param  list<array<string, mixed>>  $armor
     * @param  list<array<string, mixed>>  $equipment
     * @return array<string, mixed>
     */
    private function gearSection(array $armor, array $equipment): array
    {
        $armorApi = array_values(array_filter(array_map([$this, 'itemForApi'], $armor)));
        $equipmentApi = array_values(array_filter(array_map([$this, 'itemForApi'], $equipment)));

        return [
            'id' => 'gear',
            'title' => 'Gear',
            'layout' => 'gear',
            'armor_items' => $armorApi,
            'equipment_items' => $equipmentApi,
        ];
    }

    /**
     * @param  array<string, mixed>  $profileData
     * @param  array<string, mixed>  $gearScan
     * @return list<array<string, mixed>>
     */
    private function bestEquipmentForCategory(array $profileData, array $gearScan, string $category): array
    {
        $scores = self::EQUIPMENT_SCORES_BY_CATEGORY[$category] ?? [];
        $bySlot = [];
        $seen = [];

        $sources = $this->collectEquipmentPieces($gearScan['all_items'] ?? []);

        foreach ($sources as $piece) {
            if (! is_array($piece) || empty($piece['name'])) {
                continue;
            }

            $uuid = (string) ($piece['uuid'] ?? $piece['name']);
            if (isset($seen[$uuid])) {
                continue;
            }
            $seen[$uuid] = true;

            $matchScore = $scores !== [] ? $this->pieceArmorMatchScore($piece, $scores) : 0.0;
            if ($scores !== [] && $matchScore <= 0) {
                continue;
            }

            $slot = $this->equipmentSlotType($piece);
            $pieceScore = $matchScore
                + (int) ($piece['stars'] ?? 0) * 2
                + (self::TIER_SCORE[strtoupper((string) ($piece['rarity'] ?? 'COMMON'))] ?? 0);

            if (! isset($bySlot[$slot]) || $pieceScore > ($bySlot[$slot]['score'] ?? 0)) {
                $bySlot[$slot] = ['piece' => $piece, 'score' => $pieceScore];
            }
        }

        if ($bySlot === []) {
            return $this->bestEquipmentFromInventory($profileData, $gearScan);
        }

        return $this->orderEquipmentPieces($bySlot);
    }

    /**
     * @param  array<string, mixed>  $profileData
     * @param  array<string, mixed>  $gearScan
     * @return list<array<string, mixed>>
     */
    private function bestEquipmentFromInventory(array $profileData, array $gearScan): array
    {
        $bySlot = [];
        $seen = [];

        foreach ($this->collectEquipmentPieces($gearScan['all_items'] ?? []) as $piece) {
            if (! is_array($piece) || empty($piece['name'])) {
                continue;
            }

            $uuid = (string) ($piece['uuid'] ?? $piece['name']);
            if (isset($seen[$uuid])) {
                continue;
            }
            $seen[$uuid] = true;

            $slot = $this->equipmentSlotType($piece);
            $pieceScore = (int) ($piece['stars'] ?? 0) * 2
                + (self::TIER_SCORE[strtoupper((string) ($piece['rarity'] ?? 'COMMON'))] ?? 0);

            if (! isset($bySlot[$slot]) || $pieceScore > ($bySlot[$slot]['score'] ?? 0)) {
                $bySlot[$slot] = ['piece' => $piece, 'score' => $pieceScore];
            }
        }

        if ($bySlot !== []) {
            return $this->orderEquipmentPieces($bySlot);
        }

        return $this->equippedEquipment($profileData);
    }

    /**
     * @param  array<string, array{piece: array<string, mixed>, score: float}>  $bySlot
     * @return list<array<string, mixed>>
     */
    private function orderEquipmentPieces(array $bySlot): array
    {
        $slotOrder = ['gauntlet', 'belt', 'cloak', 'necklace', 'other'];
        $pieces = [];
        foreach ($slotOrder as $slot) {
            if (isset($bySlot[$slot])) {
                $pieces[] = $bySlot[$slot]['piece'];
            }
        }

        return $pieces;
    }

    /**
     * @param  list<array<string, mixed>>  $allItems
     * @return list<array<string, mixed>>
     */
    private function collectEquipmentPieces(array $allItems): array
    {
        $out = [];
        foreach ($allItems as $item) {
            if (! is_array($item) || empty($item['name']) || ! $this->isEquipmentPiece($item)) {
                continue;
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isEquipmentPiece(array $item): bool
    {
        if ($this->isWeaponLikeItem($item)) {
            return false;
        }

        $category = strtoupper((string) ($item['category'] ?? ''));
        $id = strtoupper((string) ($item['skyblock_id'] ?? ''));
        $name = strtoupper((string) ($item['name'] ?? ''));

        foreach (['NECKLACE', 'CLOAK', 'BELT', 'GLOVES', 'BRACELET'] as $slotCategory) {
            if ($category === $slotCategory || str_contains($category, $slotCategory)) {
                return true;
            }
        }

        foreach (['NECKLACE', 'CLOAK', 'BELT', 'GLOVES', 'BRACELET', 'POWER_WITHIN'] as $needle) {
            if (str_contains($id, $needle)) {
                return true;
            }
        }

        // Glove-slot equipment (e.g. Gauntlet of Contagion), not mining gauntlets
        if (str_contains($name, 'GAUNTLET OF ') || str_contains($id, '_GLOVES')) {
            return true;
        }

        return false;
    }

    /**
     * Mining/combat gauntlets (Gemstone Gauntlet, etc.) are weapons — not equipment slots.
     *
     * @param  array<string, mixed>  $item
     */
    private function isWeaponLikeItem(array $item): bool
    {
        $category = strtoupper((string) ($item['category'] ?? ''));
        if (in_array($category, ['SWORD', 'BOW', 'WAND', 'AXE', 'LONGSWORD', 'FISHING WEAPON', 'DRILL', 'GAUNTLET', 'PICKAXE', 'HOE'], true)) {
            return true;
        }

        $id = strtoupper((string) ($item['skyblock_id'] ?? ''));
        foreach (['GEMSTONE_GAUNTLET', 'FLARED_GAUNTLET', 'MITHRIL_GAUNTLET', '_DRILL', '_PICKAXE'] as $needle) {
            if (str_contains($id, $needle)) {
                return true;
            }
        }

        $stats = $item['stats'] ?? [];
        if (is_array($stats) && (isset($stats['MnSpd']) || isset($stats['MnFrt']) || isset($stats['Prs']))) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $piece
     */
    private function equipmentSlotType(array $piece): string
    {
        $category = strtoupper((string) ($piece['category'] ?? ''));
        $id = strtoupper((string) ($piece['skyblock_id'] ?? ''));
        $name = strtoupper((string) ($piece['name'] ?? ''));

        if (str_contains($category, 'GLOVES') || str_contains($id, 'GLOVES') || str_contains($name, 'GAUNTLET OF ')) {
            return 'gauntlet';
        }
        if (str_contains($category, 'BELT') || str_contains($id, 'BELT') || str_contains($name, 'BELT')) {
            return 'belt';
        }
        if (str_contains($category, 'CLOAK') || str_contains($id, 'CLOAK') || str_contains($name, 'CLOAK')) {
            return 'cloak';
        }
        if (str_contains($category, 'NECKLACE') || str_contains($id, 'NECKLACE') || str_contains($name, 'NECKLACE')) {
            return 'necklace';
        }

        return 'other';
    }

    /**
     * @param  array<string, mixed>  $profileData
     * @return list<array<string, mixed>>
     */
    private function equippedEquipment(array $profileData): array
    {
        $out = [];
        foreach ($profileData['equipment'] ?? [] as $piece) {
            if (is_array($piece) && ! empty($piece['name'])) {
                $out[] = $piece;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @param  array<string, mixed>|null  $primaryTool  Raw inventory item (hoe / pick / rod / axe) for skill methods
     */
    private function appendMethodToolsSection(
        array &$sections,
        string $methodId,
        array $profileData,
        array $gearScan,
        ?array $primaryTool = null,
    ): void {
        $items = [];
        $dungeonClass = null;

        if (in_array($methodId, ['zealots', 'dungeons', 'slayer'], true)) {
            $dungeonClass = $methodId === 'dungeons' ? $this->dungeonClass($profileData) : null;
            $main = $this->resolveMainWeapon($methodId, $profileData, $gearScan, $dungeonClass);
            if ($main !== null) {
                $items[] = $main;
            }
            $mainKey = $main !== null ? $this->toolDedupeKey($main) : null;
            foreach ($this->resolveOwnedExtras($methodId, $profileData, $gearScan, $dungeonClass, $mainKey) as $extra) {
                $items[] = $extra;
            }
        } else {
            $primaryApi = null;
            if (is_array($primaryTool)) {
                $primaryApi = $this->itemForApi($primaryTool);
            }
            if ($primaryApi !== null) {
                $items[] = $primaryApi;
            }
            $mainKey = $primaryApi !== null ? $this->toolDedupeKey($primaryApi) : null;
            foreach ($this->resolveOwnedExtras($methodId, $profileData, $gearScan, null, $mainKey) as $extra) {
                $items[] = $extra;
            }
        }

        if ($items === []) {
            return;
        }

        $title = match ($methodId) {
            'farming', 'garden' => 'Tools',
            'mining' => 'Pickaxe / drill & extras',
            'foraging' => 'Foraging axe',
            'fishing' => 'Fishing rod',
            'dungeons' => $dungeonClass !== null
                ? 'Main weapon ('.ucfirst($dungeonClass).') & extras'
                : 'Main weapon & extras',
            'zealots', 'slayer' => 'Main weapon & extras',
            default => 'Weapons & tools',
        };

        $sections[] = $this->toolSection($title, $items);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveMainWeapon(
        string $methodId,
        array $profileData,
        array $gearScan,
        ?string $dungeonClass = null,
    ): ?array {
        $candidates = array_values(array_filter(
            $this->collectCombatWeapons($profileData, $gearScan),
            fn (array $item): bool => ! $this->isMobilityWeapon($item),
        ));

        if ($dungeonClass !== null && $dungeonClass !== '') {
            $classFiltered = array_values(array_filter(
                $candidates,
                fn (array $item): bool => $this->weaponMatchesDungeonClass($item, $dungeonClass),
            ));
            if ($classFiltered !== []) {
                $candidates = $classFiltered;
            }
        }

        if ($candidates === []) {
            return null;
        }

        $best = $this->pickBestScoredItem($candidates);

        return $best !== null ? $this->itemForApi($best) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveOwnedExtras(
        string $methodId,
        array $profileData,
        array $gearScan,
        ?string $dungeonClass,
        ?string $excludeDedupeKey,
    ): array {
        $catalog = config("money_making_method_items.{$methodId}", []);
        if (! is_array($catalog) || $catalog === []) {
            return [];
        }

        $allItems = $gearScan['all_items'] ?? [];
        $resolved = [];
        $seen = [];

        foreach ($catalog as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $allowedClasses = $entry['classes'] ?? null;
            if (is_array($allowedClasses) && $dungeonClass !== null && $dungeonClass !== '') {
                if (! in_array($dungeonClass, array_map('strtolower', $allowedClasses), true)) {
                    continue;
                }
            }

            $owned = $this->findBestCatalogMatch($allItems, $entry);
            if ($owned === null) {
                continue;
            }

            $api = $this->itemForApi($owned);
            if ($api === null) {
                continue;
            }

            $key = $this->toolDedupeKey($api);
            if ($key === $excludeDedupeKey || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $resolved[] = $api;

            if (count($resolved) >= self::MAX_OWNED_EXTRAS) {
                break;
            }
        }

        return $resolved;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectCombatWeapons(array $profileData, array $gearScan): array
    {
        $out = [];
        $seen = [];

        foreach ($profileData['weapons'] ?? [] as $weapon) {
            if (! is_array($weapon) || empty($weapon['name'])) {
                continue;
            }
            $key = $this->toolDedupeKey($weapon);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $weapon;
        }

        foreach ($gearScan['combat']['weapons'] ?? [] as $weapon) {
            if (! is_array($weapon) || empty($weapon['name'])) {
                continue;
            }
            $key = $this->toolDedupeKey($weapon);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $weapon;
        }

        return $out;
    }

    private function dungeonClass(array $profileData): ?string
    {
        $selected = strtolower((string) ($profileData['dungeons']['selected_class'] ?? ''));
        if ($selected !== '') {
            return $selected;
        }

        $classes = $profileData['dungeons']['classes'] ?? [];
        if (! is_array($classes) || $classes === []) {
            return null;
        }

        $bestName = null;
        $bestLevel = -1;
        foreach ($classes as $name => $data) {
            if (! is_array($data)) {
                continue;
            }
            $level = (int) ($data['level'] ?? 0);
            if ($level > $bestLevel) {
                $bestLevel = $level;
                $bestName = strtolower((string) $name);
            }
        }

        return $bestName;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isMobilityWeapon(array $item): bool
    {
        $haystack = strtoupper((string) (($item['skyblock_id'] ?? '').' '.($item['name'] ?? '')));
        foreach (self::MOBILITY_KEYWORDS as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function weaponMatchesDungeonClass(array $item, string $class): bool
    {
        $class = strtolower($class);
        $haystack = strtoupper((string) (($item['skyblock_id'] ?? '').' '.($item['name'] ?? '')));
        $category = strtoupper((string) ($item['category'] ?? ''));

        if ($class === 'archer') {
            if (str_contains($category, 'BOW') || $category === 'BOW') {
                return true;
            }

            return $this->haystackMatchesAny($haystack, self::DUNGEON_CLASS_WEAPON_KEYWORDS['archer'] ?? []);
        }

        $patterns = self::DUNGEON_CLASS_WEAPON_KEYWORDS[$class] ?? [];

        return $patterns !== [] && $this->haystackMatchesAny($haystack, $patterns);
    }

    /**
     * @param  list<string>  $needles
     */
    private function haystackMatchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function pickBestScoredItem(array $items): ?array
    {
        $best = null;
        $bestScore = -1.0;

        foreach ($items as $item) {
            $score = (int) ($item['stars'] ?? 0) * 15
                + (self::TIER_SCORE[strtoupper((string) ($item['rarity'] ?? 'COMMON'))] ?? 0) * 4;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        return $best;
    }

    /**
     * @param  list<array<string, mixed>>  $allItems
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    private function findBestCatalogMatch(array $allItems, array $entry): ?array
    {
        $keywords = $entry['match'] ?? [];
        if (! is_array($keywords) || $keywords === []) {
            return null;
        }

        $priority = (float) ($entry['priority'] ?? 50);
        $best = null;
        $bestScore = -1.0;

        foreach ($allItems as $item) {
            if (! is_array($item) || empty($item['name'])) {
                continue;
            }

            $haystack = strtoupper((string) (($item['skyblock_id'] ?? '').' '.($item['name'] ?? '')));
            $matched = false;
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($haystack, strtoupper((string) $keyword))) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                continue;
            }

            $score = $priority
                + (int) ($item['stars'] ?? 0) * 12
                + (self::TIER_SCORE[strtoupper((string) ($item['rarity'] ?? 'COMMON'))] ?? 0) * 3;

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function toolDedupeKey(array $item): string
    {
        $id = strtoupper((string) ($item['skyblock_id'] ?? ''));
        if ($id !== '') {
            foreach (['STARRED_', 'HOT_', 'FIRED_', 'REFINED_'] as $prefix) {
                if (str_starts_with($id, $prefix)) {
                    $id = substr($id, strlen($prefix));
                }
            }

            return $id;
        }

        $name = preg_replace('/§./', '', strtoupper((string) ($item['name'] ?? ''))) ?? '';

        return $name !== '' ? $name : spl_object_hash((object) $item);
    }

    /**
     * @param  list<array<string, mixed>|null>  $items
     * @return array<string, mixed>
     */
    private function toolSection(string $title, array $items): array
    {
        return [
            'id' => 'tool',
            'title' => $title,
            'layout' => 'items_row',
            'items' => array_values(array_filter(array_map(
                fn ($i) => is_array($i) ? $this->itemForApi($i) : null,
                $items
            ))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function petSection(array $pet): array
    {
        $profile = $pet['profile'] ?? [];
        $level = (int) ($profile['level'] ?? $pet['level']['level'] ?? $pet['level'] ?? 0);
        $subtitleParts = [];
        if ($level > 0) {
            $subtitleParts[] = ($profile['isMaxLevel'] ?? false) ? "Level {$level} (max)" : "Level {$level}";
        }
        if (! empty($profile['heldItem']['name'])) {
            $subtitleParts[] = (string) $profile['heldItem']['name'];
        }

        return [
            'id' => 'pet',
            'title' => 'Recommended pet',
            'layout' => 'pet',
            'item' => $this->itemForApi($pet) ?? $pet,
            'subtitle' => $subtitleParts !== [] ? implode(' · ', $subtitleParts) : null,
            'tier' => (string) ($pet['tier'] ?? 'COMMON'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bestArmorForCategory(array $profileData, array $gearScan, string $category): array
    {
        $scores = self::ARMOR_SCORES_BY_CATEGORY[$category] ?? [];
        if ($scores === []) {
            return [];
        }

        $candidates = [];

        foreach ($profileData['wardrobe'] ?? [] as $set) {
            if (! is_array($set)) {
                continue;
            }
            $pieces = array_values(array_filter($set, static fn ($piece) => is_array($piece) && ! empty($piece['name'])));
            if ($pieces === []) {
                continue;
            }
            $setScore = $this->scoreArmorSet($pieces, $scores);
            if ($setScore > 0) {
                $candidates[] = ['pieces' => $pieces, 'score' => $setScore];
            }
        }

        foreach ($this->groupArmorBySet($this->collectArmorPieces($gearScan['all_items'] ?? []), $scores) as $group) {
            $setScore = $this->scoreArmorSet($group['pieces'], $scores);
            if ($setScore > 0) {
                $candidates[] = ['pieces' => $group['pieces'], 'score' => $setScore];
            }
        }

        if ($candidates === []) {
            return [];
        }

        usort($candidates, static fn (array $a, array $b): int => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return $this->orderArmorPieces($candidates[0]['pieces']);
    }

    /**
     * @param  list<array<string, mixed>>  $allItems
     * @return list<array<string, mixed>>
     */
    private function collectArmorPieces(array $allItems): array
    {
        $out = [];
        foreach ($allItems as $item) {
            if (! is_array($item) || empty($item['name']) || ! $this->isArmorPiece($item)) {
                continue;
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isArmorPiece(array $item): bool
    {
        $category = strtoupper((string) ($item['category'] ?? ''));
        foreach (self::ARMOR_CATEGORIES as $armorCategory) {
            if ($category === $armorCategory || str_contains($category, $armorCategory)) {
                return true;
            }
        }

        $id = strtoupper((string) ($item['skyblock_id'] ?? ''));
        foreach (['_HELMET', '_CHESTPLATE', '_LEGGINGS', '_BOOTS'] as $suffix) {
            if (str_contains($id, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $armorPieces
     * @param  array<string, int>  $scores
     * @return list<array{pieces: list<array<string, mixed>>, setKey: string}>
     */
    private function groupArmorBySet(array $armorPieces, array $scores): array
    {
        $bySet = [];

        foreach ($armorPieces as $piece) {
            $matchScore = $this->pieceArmorMatchScore($piece, $scores);
            if ($matchScore <= 0) {
                continue;
            }

            $setKey = $this->armorSetKey($piece);
            if ($setKey === null || $setKey === '') {
                continue;
            }

            $slot = $this->armorSlotType($piece);
            $pieceScore = $matchScore
                + (int) ($piece['stars'] ?? 0) * 2
                + (self::TIER_SCORE[strtoupper((string) ($piece['rarity'] ?? 'COMMON'))] ?? 0);

            if (! isset($bySet[$setKey][$slot]) || $pieceScore > ($bySet[$setKey][$slot]['score'] ?? 0)) {
                $bySet[$setKey][$slot] = ['piece' => $piece, 'score' => $pieceScore];
            }
        }

        $groups = [];
        foreach ($bySet as $setKey => $slots) {
            $pieces = array_map(static fn (array $slot): array => $slot['piece'], array_values($slots));
            $groups[] = ['pieces' => $pieces, 'setKey' => (string) $setKey];
        }

        return $groups;
    }

    /**
     * @param  list<array<string, mixed>>  $pieces
     * @param  array<string, int>  $scores
     */
    private function scoreArmorSet(array $pieces, array $scores): float
    {
        if ($pieces === []) {
            return 0.0;
        }

        $total = 0.0;
        $maxPieceScore = 0.0;
        foreach ($pieces as $piece) {
            $pieceScore = $this->pieceArmorMatchScore($piece, $scores);
            $maxPieceScore = max($maxPieceScore, $pieceScore);
            $total += $pieceScore;
            $total += (int) ($piece['stars'] ?? 0) * 1.5;
            $total += (self::TIER_SCORE[strtoupper((string) ($piece['rarity'] ?? 'COMMON'))] ?? 0) * 0.5;
        }

        $completeness = match (count($pieces)) {
            4 => 40.0,
            3 => 25.0,
            2 => 10.0,
            default => 0.0,
        };

        return ($maxPieceScore * 10) + $total + $completeness;
    }

    /**
     * @param  array<string, mixed>  $piece
     * @param  array<string, int>  $scores
     */
    private function pieceArmorMatchScore(array $piece, array $scores): float
    {
        $haystack = strtoupper((string) (($piece['skyblock_id'] ?? '').' '.($piece['name'] ?? '')));
        $best = 0.0;
        foreach ($scores as $keyword => $priority) {
            if (str_contains($haystack, $keyword)) {
                $best = max($best, (float) $priority);
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $piece
     */
    private function armorSetKey(array $piece): ?string
    {
        $id = strtoupper((string) ($piece['skyblock_id'] ?? ''));
        foreach (['_HELMET', '_CHESTPLATE', '_LEGGINGS', '_BOOTS'] as $suffix) {
            if (str_contains($id, $suffix)) {
                return str_replace($suffix, '', $id);
            }
        }

        $name = preg_replace('/§./', '', strtoupper((string) ($piece['name'] ?? ''))) ?? '';
        if (preg_match("/^([A-Z0-9']+)(?:'S)?\\s/", $name, $matches) === 1) {
            return str_replace("'S", '', $matches[1]);
        }

        return $id !== '' ? $id : null;
    }

    /**
     * @param  array<string, mixed>  $piece
     */
    private function armorSlotType(array $piece): string
    {
        $category = strtoupper((string) ($piece['category'] ?? ''));
        $id = strtoupper((string) ($piece['skyblock_id'] ?? ''));

        if (str_contains($category, 'HELMET') || str_contains($id, 'HELMET')) {
            return 'helmet';
        }
        if (str_contains($category, 'CHEST') || str_contains($id, 'CHESTPLATE')) {
            return 'chestplate';
        }
        if (str_contains($category, 'LEGG') || str_contains($id, 'LEGGINGS')) {
            return 'leggings';
        }
        if (str_contains($category, 'BOOT') || str_contains($id, 'BOOTS')) {
            return 'boots';
        }

        return 'other';
    }

    /**
     * @param  list<array<string, mixed>>  $pieces
     * @return list<array<string, mixed>>
     */
    private function orderArmorPieces(array $pieces): array
    {
        $filled = array_values(array_filter($pieces, static fn ($p) => is_array($p) && ! empty($p['name'])));
        $slotOrder = ['helmet' => 0, 'chestplate' => 1, 'leggings' => 2, 'boots' => 3, 'other' => 4];

        usort($filled, function (array $a, array $b) use ($slotOrder): int {
            return ($slotOrder[$this->armorSlotType($a)] ?? 9) <=> ($slotOrder[$this->armorSlotType($b)] ?? 9);
        });

        return $filled;
    }

    private function bestPetForCategory(array $profileData, string $category): ?array
    {
        return $this->petProfile->bestForActivity($profileData['pets']['pets'] ?? [], $category);
    }

    /**
     * @param  array<string, mixed>  $gearScan
     */
    private function resolveHoeItem(array $gearScan, array $profileData): ?array
    {
        $best = $gearScan['farming']['best_hoe'] ?? null;
        if (! is_array($best)) {
            return null;
        }

        $item = $this->findItemInProfile($profileData, $gearScan['all_items'] ?? [], $best);

        return $item !== null ? $this->itemForApi($item) : null;
    }

    /**
     * @param  list<array<string, mixed>>  $allItems
     * @param  array<string, mixed>|null  $descriptor
     * @return array<string, mixed>|null
     */
    private function findItemInProfile(array $profileData, array $allItems, ?array $descriptor): ?array
    {
        if ($descriptor === null) {
            return null;
        }

        $skyblockId = strtoupper((string) ($descriptor['skyblock_id'] ?? ''));
        $name = (string) ($descriptor['name'] ?? '');

        $best = null;
        $bestScore = -1;

        foreach ($allItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $matches = false;
            if ($skyblockId !== '' && strtoupper((string) ($item['skyblock_id'] ?? '')) === $skyblockId) {
                $matches = true;
            } elseif ($name !== '' && (string) ($item['name'] ?? '') === $name) {
                $matches = true;
            }

            if (! $matches) {
                continue;
            }

            $score = (int) ($item['stars'] ?? 0) * 10;
            if (! empty($item['lore_html']) && is_array($item['lore_html'])) {
                $score += 5;
            }
            if (isset($item['item_value'])) {
                $score += 1;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        return $best;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function itemForApi(?array $item): ?array
    {
        if ($item === null || empty($item['name'])) {
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
            'tier' => $item['tier'] ?? null,
            'level' => $item['level'] ?? null,
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

    private function friendlyCrop(string $productId): string
    {
        return ucwords(strtolower(str_replace(['ENCHANTED_', '_'], ['', ' '], $productId)));
    }
}
