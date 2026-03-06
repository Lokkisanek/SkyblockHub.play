<?php

use App\Http\Controllers\BazaarController;
use App\Http\Controllers\BinSniperController;
use App\Http\Controllers\CraftingArbitrageController;
use App\Http\Controllers\DungeonPartyController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileStatsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/bazaar', [BazaarController::class, 'index'])->name('bazaar');
    Route::get('/profile-stats', [ProfileStatsController::class, 'index'])->name('profile-stats');

    Route::get('/dungeon-party', [DungeonPartyController::class, 'index'])->name('dungeon-party');
    Route::post('/dungeon-party', [DungeonPartyController::class, 'store'])->name('dungeon-party.store');
    Route::delete('/dungeon-party', [DungeonPartyController::class, 'destroy'])->name('dungeon-party.destroy');

    // Portfolio Tracker
    Route::get('/portfolio', [PortfolioController::class, 'index'])->name('portfolio');
    Route::post('/portfolio', [PortfolioController::class, 'store'])->name('portfolio.store');
    Route::post('/portfolio/sell', [PortfolioController::class, 'sell'])->name('portfolio.sell');
    Route::delete('/portfolio', [PortfolioController::class, 'destroy'])->name('portfolio.destroy');

    // Crafting Arbitrage
    Route::get('/crafting', [CraftingArbitrageController::class, 'index'])->name('crafting');

    // Lowest BIN Sniper
    Route::get('/bin-sniper', [BinSniperController::class, 'index'])->name('bin-sniper');
    Route::post('/bin-sniper/alert', [BinSniperController::class, 'storeAlert'])->name('bin-sniper.alert.store');
    Route::delete('/bin-sniper/alert', [BinSniperController::class, 'destroyAlert'])->name('bin-sniper.alert.destroy');
    Route::patch('/bin-sniper/alert', [BinSniperController::class, 'toggleAlert'])->name('bin-sniper.alert.toggle');
});

require __DIR__.'/auth.php';
