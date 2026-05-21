<?php

/**
 * Per-pet activity fit, max-level stat hints, and perk unlocks for profile + money-making scoring.
 *
 * activities: money-making method id => priority (0–100)
 * stats_at_max: stat abbreviations at level 100 (scaled by level in PetProfileDataService)
 * perks: unlock at min_level
 */
return [
    'ELEPHANT' => [
        'activities' => ['farming' => 100, 'garden' => 100],
        'stats_at_max' => ['FmFrt' => 180],
        'perks' => [
            ['min_level' => 1, 'name' => 'Stomp', 'description' => 'Grants Farming Fortune while in Garden.'],
        ],
    ],
    'MOOSHROOM' => [
        'activities' => ['farming' => 90, 'garden' => 90],
        'stats_at_max' => ['FmFrt' => 140, 'SCC' => 15],
        'perks' => [
            ['min_level' => 1, 'name' => 'Mushroom Eater', 'description' => 'Boosts mushroom crop fortune.'],
        ],
    ],
    'RABBIT' => [
        'activities' => ['farming' => 80, 'garden' => 80],
        'stats_at_max' => ['FmFrt' => 100, 'Spd' => 20],
    ],
    'BEE' => [
        'activities' => ['farming' => 70, 'garden' => 70],
        'stats_at_max' => ['FmFrt' => 80, 'SCC' => 10],
    ],
    'GLACITE_GOLEM' => [
        'activities' => ['mining' => 100],
        'stats_at_max' => ['MnFrt' => 80, 'MnSpd' => 50],
    ],
    'MITHRIL_GOLEM' => [
        'activities' => ['mining' => 95],
        'stats_at_max' => ['MnFrt' => 60, 'Prs' => 10],
    ],
    'ARMADILLO' => [
        'activities' => ['mining' => 90],
        'stats_at_max' => ['MnFrt' => 70, 'MnSpd' => 30],
    ],
    'MOLE' => [
        'activities' => ['mining' => 85],
        'stats_at_max' => ['MnSpd' => 40],
    ],
    'BAL' => [
        'activities' => ['mining' => 75, 'combat' => 70, 'foraging' => 80, 'fishing' => 80],
        'stats_at_max' => ['Str' => 25, 'Spd' => 10],
    ],
    'SILVERFISH' => [
        'activities' => ['mining' => 65],
        'stats_at_max' => ['MnSpd' => 25],
    ],
    'WITHER_SKELETON' => [
        'activities' => ['combat' => 100, 'dungeon' => 100],
        'stats_at_max' => ['Str' => 50, 'CC' => 30, 'CD' => 80],
        'perks' => [
            ['min_level' => 1, 'name' => 'Wither Blood', 'description' => 'Reduces wither damage taken.'],
            ['min_level' => 100, 'name' => 'Death Touch', 'description' => 'Grants combat stats in Dungeons.'],
        ],
    ],
    'LION' => [
        'activities' => ['combat' => 95, 'dungeon' => 90],
        'stats_at_max' => ['Str' => 40, 'CC' => 25, 'CD' => 60],
        'perks' => [
            ['min_level' => 1, 'name' => 'First Pounce', 'description' => 'First hit deals bonus damage.'],
        ],
    ],
    'GRIFFIN' => [
        'activities' => ['combat' => 94, 'dungeon' => 88],
        'stats_at_max' => ['Str' => 35, 'MF' => 30, 'CD' => 50],
        'perks' => [
            ['min_level' => 1, 'name' => 'Odyssey', 'description' => 'Magic find and coin bonus on rare drops.'],
            ['min_level' => 100, 'name' => 'King of Kings', 'description' => 'Large combat stat boost at max level.'],
        ],
    ],
    'ENDERMAN' => [
        'activities' => ['combat' => 90, 'dungeon' => 88],
        'stats_at_max' => ['Str' => 45, 'CC' => 20],
        'perks' => [
            ['min_level' => 1, 'name' => 'Dimensional Warp', 'description' => 'Teleport and combat utility.'],
        ],
    ],
    'BLAZE' => [
        'activities' => ['combat' => 88, 'dungeon' => 85],
        'stats_at_max' => ['Str' => 50, 'Int' => 80],
    ],
    'GOLDEN_DRAGON' => [
        'activities' => ['combat' => 85, 'dungeon' => 82],
        'stats_at_max' => ['Str' => 60, 'HP' => 200],
    ],
    'YETI' => [
        'activities' => ['combat' => 83, 'fishing' => 100],
        'stats_at_max' => ['Str' => 30, 'SCC' => 25],
    ],
    'TARANTULA' => [
        'activities' => ['combat' => 80],
        'stats_at_max' => ['Str' => 35, 'CC' => 15],
    ],
    'SCATHA' => [
        'activities' => ['combat' => 78, 'mining' => 70],
        'stats_at_max' => ['MnFrt' => 40, 'MF' => 20],
    ],
    'TIGER' => [
        'activities' => ['combat' => 72],
        'stats_at_max' => ['CC' => 40, 'CD' => 40],
    ],
    'SPIRIT' => [
        'activities' => ['combat' => 68],
        'stats_at_max' => ['Spd' => 30],
    ],
    'WOLF' => [
        'activities' => ['combat' => 65],
        'stats_at_max' => ['Str' => 20, 'Spd' => 15],
    ],
    'NECRON' => [
        'activities' => ['dungeon' => 100, 'combat' => 92],
        'stats_at_max' => ['Str' => 50, 'CC' => 35],
    ],
    'STORM' => [
        'activities' => ['dungeon' => 98, 'combat' => 90],
        'stats_at_max' => ['Int' => 100, 'CC' => 30],
    ],
    'GOLDOR' => [
        'activities' => ['dungeon' => 95],
        'stats_at_max' => ['HP' => 300, 'Def' => 150],
    ],
    'MAXOR' => [
        'activities' => ['dungeon' => 93],
        'stats_at_max' => ['Spd' => 80],
    ],
    'CRIMSON' => [
        'activities' => ['combat' => 100, 'dungeon' => 85],
        'stats_at_max' => ['Str' => 55, 'CC' => 25],
    ],
    'AURORA' => [
        'activities' => ['combat' => 99],
        'stats_at_max' => ['Int' => 120],
    ],
    'TERROR' => [
        'activities' => ['combat' => 98],
        'stats_at_max' => ['Str' => 50, 'CC' => 30],
    ],
    'SHADOW' => [
        'activities' => ['combat' => 85, 'dungeon' => 85],
        'stats_at_max' => ['CC' => 45, 'CD' => 70],
    ],
    'FERMENTO' => [
        'activities' => ['farming' => 100, 'garden' => 100],
        'stats_at_max' => ['FmFrt' => 200],
    ],
    'SQUASH' => [
        'activities' => ['farming' => 95, 'garden' => 95],
        'stats_at_max' => ['FmFrt' => 170],
    ],
    'SORROW' => [
        'activities' => ['farming' => 90, 'garden' => 90, 'mining' => 75],
        'stats_at_max' => ['FmFrt' => 120, 'Spd' => 25],
    ],
    'CROPIE' => [
        'activities' => ['farming' => 85, 'garden' => 85],
        'stats_at_max' => ['FmFrt' => 100],
    ],
    'BABY_YETI' => [
        'activities' => ['fishing' => 100],
        'stats_at_max' => ['SCC' => 30, 'FS' => 20],
    ],
    'BLUE_WHALE' => [
        'activities' => ['fishing' => 95],
        'stats_at_max' => ['HP' => 200],
    ],
    'DOLPHIN' => [
        'activities' => ['fishing' => 90],
        'stats_at_max' => ['FshSpd' => 50],
    ],
    'MEGALODON' => [
        'activities' => ['fishing' => 88],
        'stats_at_max' => ['Str' => 40, 'SCC' => 15],
    ],
    'SHARK' => [
        'activities' => ['fishing' => 100],
        'stats_at_max' => ['Str' => 50, 'SCC' => 20],
    ],
    'SPONGE' => [
        'activities' => ['fishing' => 95],
        'stats_at_max' => ['SCC' => 25],
    ],
    'MONKEY' => [
        'activities' => ['foraging' => 100],
        'stats_at_max' => ['FgFrt' => 100, 'Spd' => 20],
    ],
    'OCELOT' => [
        'activities' => ['foraging' => 90],
        'stats_at_max' => ['FgFrt' => 60, 'Spd' => 30],
    ],
    'PHOENIX' => [
        'activities' => ['foraging' => 70],
        'stats_at_max' => ['Str' => 25, 'Int' => 50],
    ],
    'MANGROVE' => [
        'activities' => ['foraging' => 100],
        'stats_at_max' => ['FgFrt' => 120],
    ],
    'HEAT' => [
        'activities' => ['mining' => 100],
        'stats_at_max' => ['MnFrt' => 90],
    ],
    'DIVAN' => [
        'activities' => ['mining' => 95],
        'stats_at_max' => ['MnFrt' => 100, 'Prs' => 15],
    ],
];
