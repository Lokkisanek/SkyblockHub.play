<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->handle($request = \Illuminate\Http\Request::capture());

// Get cached data
$cache = \Illuminate\Support\Facades\Cache::get('skycrypt_data_ef83cf5a97ea45da92e99f90b3d31dd0');
if (!$cache) {
    echo "No cached data, trying other keys...\n";
    // List any keys that might exist
    exit;
}

$profile = json_decode($cache, true);
$uuid = array_key_first($profile['members']);
$pets = $profile['members'][$uuid]['pets_data']['pets'] ?? [];
echo "Raw pets count: " . count($pets) . "\n\n";

foreach ($pets as $pet) {
    $type = $pet['type'] ?? 'UNKNOWN';
    $tier = $pet['tier'] ?? 'COMMON';
    $skin = $pet['skin'] ?? null;
    $active = $pet['active'] ?? false;
    
    echo ($active ? '* ' : '  ') . $type . ' (' . $tier . ')';
    if ($skin) echo " SKIN=" . $skin;
    echo "\n";
}

// Now test parsePets
echo "\n--- Parsed pets texture_path check ---\n";
$controller = new \App\Http\Controllers\Api\SkyCryptProxyController();
$method = new ReflectionMethod($controller, 'parsePets');
$method->setAccessible(true);
$parsed = $method->invoke($controller, $pets);

echo "Total parsed: " . count($parsed['pets']) . "\n";
echo "Missing: " . count($parsed['missing']) . "\n\n";

echo "Pets with NULL texture_path:\n";
foreach ($parsed['pets'] as $pet) {
    if (!$pet['texture_path']) {
        echo "  !! " . $pet['type'] . " (" . $pet['tier'] . ") - NO TEXTURE\n";
    }
}

echo "\nAll pet textures:\n";
foreach ($parsed['pets'] as $pet) {
    $status = $pet['texture_path'] ? '✓' : '✗';
    echo "  {$status} {$pet['type']} ({$pet['tier']}) => " . ($pet['texture_path'] ?? 'NULL') . "\n";
}
