<?php

/**
 * Extra utility items per money-making method (inventory only — no catalog stubs).
 * Main weapon / hoe / pick / rod comes from the player's gear separately.
 *
 * match: substrings for skyblock_id / item name (uppercase)
 * classes: optional dungeon class filter (archer, mage, berserk, healer, tank)
 */
return [
    'farming' => [
        ['match' => ['PERSONAL_COMPACTOR'], 'priority' => 70],
        ['match' => ['PERSONAL_DELETOR'], 'priority' => 68],
    ],
    'garden' => [
        ['match' => ['PERSONAL_COMPACTOR'], 'priority' => 70],
    ],
    'mining' => [
        ['match' => ['GEMSTONE_GAUNTLET'], 'priority' => 85],
        ['match' => ['FLARED_GAUNTLET'], 'priority' => 72],
    ],
    'zealots' => [
        ['match' => ['ASPECT_OF_THE_END', 'STARRED_ASPECT_OF_THE_END'], 'priority' => 92],
        ['match' => ['ASPECT_OF_THE_VOID', 'STARRED_ASPECT_OF_THE_VOID'], 'priority' => 90],
    ],
    'dungeons' => [
        ['match' => ['ASPECT_OF_THE_VOID', 'STARRED_ASPECT_OF_THE_VOID'], 'priority' => 90],
        ['match' => ['ASPECT_OF_THE_END', 'STARRED_ASPECT_OF_THE_END'], 'priority' => 88],
        ['match' => ['BONZO_STAFF', 'STARRED_BONZO'], 'priority' => 80, 'classes' => ['mage', 'healer']],
        ['match' => ['SPIRIT_SCEPTRE'], 'priority' => 78, 'classes' => ['mage', 'healer']],
        ['match' => ['SHADOW_FURY'], 'priority' => 75, 'classes' => ['berserk']],
    ],
    'slayer' => [
        ['match' => ['ASPECT_OF_THE_VOID', 'STARRED_ASPECT_OF_THE_VOID'], 'priority' => 88],
        ['match' => ['ASPECT_OF_THE_END', 'STARRED_ASPECT_OF_THE_END'], 'priority' => 86],
        ['match' => ['BONZO_STAFF', 'STARRED_BONZO'], 'priority' => 80],
    ],
    'foraging' => [],
    'fishing' => [],
];
