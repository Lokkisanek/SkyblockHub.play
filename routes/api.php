<?php

use App\Http\Controllers\Api\BazaarController as ApiBazaarController;
use App\Http\Controllers\Api\BinSniperAnalysisController;
use App\Http\Controllers\Api\DashboardSnapshotController;
use App\Http\Controllers\Api\HypixelProfileController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\MoneyMakingController;
use App\Http\Controllers\CraftingArbitrageController;
use App\Http\Controllers\KarmaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/v1/dashboard/snapshot', DashboardSnapshotController::class)
    ->middleware('throttle:60,1')
    ->name('api.dashboard.snapshot');

/*
|--------------------------------------------------------------------------
| Minecraft profile (Hypixel API → frontend shape)
|--------------------------------------------------------------------------
*/
Route::get('/profile/minecraft/{username}', [HypixelProfileController::class, 'profile'])
    ->middleware('throttle:30,1')
    ->where('username', '[A-Za-z0-9_]{1,16}')
    ->name('api.profile.minecraft');

Route::get('/v1/money-making/{username}', [MoneyMakingController::class, 'show'])
    ->middleware(['web', 'auth', 'testing.admin', 'throttle:24,1'])
    ->where('username', '[A-Za-z0-9_]{1,16}')
    ->name('api.v1.money-making');

/*
|--------------------------------------------------------------------------
| Karma Voting
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/karma/vote', [KarmaController::class, 'vote'])->name('api.karma.vote');
    Route::get('/karma/{targetId}', [KarmaController::class, 'status'])->name('api.karma.status');
});

/*
|--------------------------------------------------------------------------
| Bazaar Intelligence API
|--------------------------------------------------------------------------
*/
Route::prefix('v1/bazaar')->group(function () {
    Route::get('/live', [ApiBazaarController::class, 'live']);
    Route::get('/arbitrage/recipes', [ApiBazaarController::class, 'recipeArbitrage']);
    Route::get('/history/{productId}', [ApiBazaarController::class, 'history']);
});

/*
|--------------------------------------------------------------------------
| Crafting Arbitrage API
|--------------------------------------------------------------------------
*/
Route::get('/arbitrage/crafting', [CraftingArbitrageController::class, 'api']);

/*
|--------------------------------------------------------------------------
| BIN Sniper Advanced Analysis API
|--------------------------------------------------------------------------
*/
Route::post('/bin-sniper/analyze', [BinSniperAnalysisController::class, 'analyze'])
    ->middleware('throttle:30,1')
    ->name('api.bin-sniper.analyze');

/*
|--------------------------------------------------------------------------
| Leaderboard API
|--------------------------------------------------------------------------
*/
Route::get('/v1/leaderboards/lookup', [LeaderboardController::class, 'lookup'])
    ->middleware('throttle:30,1')
    ->name('api.leaderboards.lookup');

Route::get('/v1/leaderboards', [LeaderboardController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('api.leaderboards.index');
