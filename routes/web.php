<?php

use App\Http\Controllers\AdminGuideSubmissionController;
use App\Http\Controllers\AdminGuildCrawlController;
use App\Http\Controllers\AdminOperationsController;
use App\Http\Controllers\AdminTrustIndexSubmissionController;
use App\Http\Controllers\AnaliticsController;
use App\Http\Controllers\Api\SocialProofMetricsController;
use App\Http\Controllers\BazaarController;
use App\Http\Controllers\BinSniperController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\CraftingArbitrageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DungeonPartyController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\FunnelAnalyticsController;
use App\Http\Controllers\GuidesController;
use App\Http\Controllers\LeaderboardsController;
use App\Http\Controllers\MayorController;
use App\Http\Controllers\MoneyMakingPageController;
use App\Http\Controllers\NpcFlipsController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileStatsController;
use App\Http\Controllers\TrustIndexController;
use App\Services\SocialProofMetricsService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $socialProofMetrics = app(SocialProofMetricsService::class)->getMetrics();

    return Inertia::render('Welcome', [
        'canLogin' => ! auth()->check(),
        'socialProofMetrics' => $socialProofMetrics,
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

/** Hypixel developer dashboard: human-readable ownership + operator Minecraft name. */
Route::view('/hypixel-developer-verification', 'hypixel-developer-verification')->name('hypixel.developer-verification');

/** If Hypixel asks for a token file, set HYPIXEL_SITE_VERIFICATION in .env (same value as meta tag). */
Route::get('/hypixel-verification.txt', function () {
    $token = config('hypixel.site_verification.meta_content');

    if ($token === '') {
        abort(404);
    }

    return response($token, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('hypixel.verification-file');

Route::get('/api/social-proof-metrics', SocialProofMetricsController::class)
    ->middleware('throttle:60,1')
    ->name('api.social-proof.metrics');

Route::post('/cookie-consent', [CookieConsentController::class, 'store'])->name('cookie-consent.store');
Route::post('/analytics/funnel-event', [FunnelAnalyticsController::class, 'store'])
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->middleware('throttle:120,1')
    ->name('analytics.funnel-event');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/visit/{minecraftUuid}', [DashboardController::class, 'visit'])->name('dashboard.visit');
Route::post('/dashboard/save', [DashboardController::class, 'save'])->middleware('auth')->name('dashboard.save');
Route::get('/dashboard/info', fn () => redirect()->route('dashboard'))->name('dashboard.info');

Route::get('/leaderboards/info', function () {
    return Inertia::render('LeaderboardsInfo');
})->name('leaderboards.info');

Route::get('/bazaar', [BazaarController::class, 'index'])->name('bazaar');
Route::get('/npc-flips', [NpcFlipsController::class, 'index'])->name('npc-flips');
Route::get('/profile-stats', [ProfileStatsController::class, 'index'])->name('profile-stats');
Route::get('/event-timer', [EventsController::class, 'index'])->name('event-timer');
Route::get('/mayors', [MayorController::class, 'index'])->name('mayors');
Route::get('/trust-index', [TrustIndexController::class, 'index'])->name('trust-index');
Route::get('/trust-index/report', [TrustIndexController::class, 'createReport'])->name('trust-index.report');
Route::get('/trust-index/appeal', [TrustIndexController::class, 'createAppeal'])->name('trust-index.appeal');
Route::get('/trust-index/lookup', [TrustIndexController::class, 'lookup'])
    ->middleware('throttle:60,1')
    ->name('trust-index.lookup');
Route::post('/trust-index/report', [TrustIndexController::class, 'storeReport'])
    ->middleware('throttle:6,1')
    ->name('trust-index.report.store');
Route::post('/trust-index/appeal', [TrustIndexController::class, 'storeAppeal'])
    ->middleware('throttle:6,1')
    ->name('trust-index.appeal.store');
Route::get('/guides', [GuidesController::class, 'index'])->name('guides');
Route::get('/guides/submit', [GuidesController::class, 'createSubmission'])->name('guides.submit');
Route::post('/guides/submissions', [GuidesController::class, 'storeSubmission'])->middleware('throttle:8,1')->name('guides.submissions.store');
Route::get('/guides/{slug}/suggest-edit', [GuidesController::class, 'suggestEdit'])->name('guides.suggest-edit');
Route::post('/guides/{slug}/suggest-edit', [GuidesController::class, 'storeEditSuggestion'])->middleware('throttle:8,1')->name('guides.suggest-edit.store');
Route::get('/guides/{slug}', [GuidesController::class, 'show'])->name('guides.show');
Route::get('/leaderboards', [LeaderboardsController::class, 'index'])->name('leaderboards');

Route::get('/about', function () {
    return Inertia::render('About');
})->name('about');

Route::get('/privacy', function () {
    return Inertia::render('Privacy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('Terms');
})->name('terms');

Route::get('/pricing', function () {
    return redirect()->to('https://buymeacoffee.com/lokkisan');
})->name('pricing');
Route::get('/analytics', function () {
    return redirect()->route('admin.index');
})->name('analytics.redirect');

Route::middleware('auth')->group(function () {
    Route::post('/onboarding/complete-step', [OnboardingController::class, 'completeStep'])->name('onboarding.complete-step');
    Route::post('/onboarding/dismiss', [OnboardingController::class, 'dismiss'])->name('onboarding.dismiss');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('testing.admin')->group(function () {
        Route::get('/admin', [AnaliticsController::class, 'index'])->name('admin.index');
        Route::get('/analitics', fn () => redirect()->route('admin.index'))->name('analitics.index');
        Route::get('/admin/guides/submissions', [AdminGuideSubmissionController::class, 'index'])->name('admin.guides.submissions');
        Route::get('/admin/guides/submissions/{submission}', [AdminGuideSubmissionController::class, 'show'])->name('admin.guides.submissions.show');
        Route::patch('/admin/guides/submissions/{submission}', [AdminGuideSubmissionController::class, 'update'])->name('admin.guides.submissions.update');
        Route::post('/admin/guides/submissions/{submission}/approve', [AdminGuideSubmissionController::class, 'approve'])->name('admin.guides.submissions.approve');
        Route::post('/admin/guides/submissions/{submission}/reject', [AdminGuideSubmissionController::class, 'reject'])->name('admin.guides.submissions.reject');

        Route::get('/admin/trust-index/submissions', [AdminTrustIndexSubmissionController::class, 'index'])->name('admin.trust-index.submissions');
        Route::get('/admin/trust-index/submissions/{submission}', [AdminTrustIndexSubmissionController::class, 'show'])->name('admin.trust-index.submissions.show');
        Route::post('/admin/trust-index/submissions/{submission}/approve', [AdminTrustIndexSubmissionController::class, 'approve'])->name('admin.trust-index.submissions.approve');
        Route::post('/admin/trust-index/submissions/{submission}/reject', [AdminTrustIndexSubmissionController::class, 'reject'])->name('admin.trust-index.submissions.reject');

        Route::get('/admin/guild-crawl/status', [AdminGuildCrawlController::class, 'status'])
            ->name('admin.guild-crawl.status');
        Route::post('/admin/guild-crawl/start', [AdminGuildCrawlController::class, 'start'])
            ->name('admin.guild-crawl.start');
        Route::post('/admin/guild-crawl/cancel', [AdminGuildCrawlController::class, 'cancel'])
            ->name('admin.guild-crawl.cancel');

        Route::post('/admin/operations/refresh-hypixel', [AdminOperationsController::class, 'refreshHypixelHealth'])
            ->name('admin.operations.refresh-hypixel');

        Route::get('/dungeon-party', [DungeonPartyController::class, 'index'])->name('dungeon-party');
        Route::post('/dungeon-party', [DungeonPartyController::class, 'store'])->name('dungeon-party.store');
        Route::delete('/dungeon-party', [DungeonPartyController::class, 'destroy'])->name('dungeon-party.destroy');

        // Portfolio Tracker
        Route::get('/portfolio', [PortfolioController::class, 'index'])->name('portfolio');
        Route::post('/portfolio', [PortfolioController::class, 'store'])->name('portfolio.store');
        Route::post('/portfolio/sell', [PortfolioController::class, 'sell'])->name('portfolio.sell');
        Route::delete('/portfolio', [PortfolioController::class, 'destroy'])->name('portfolio.destroy');

        Route::get('/money-making', MoneyMakingPageController::class)->name('money-making');

        // Crafting Arbitrage
        Route::get('/crafting', [CraftingArbitrageController::class, 'index'])->name('crafting');

        // Lowest BIN Sniper
        Route::get('/bin-sniper', [BinSniperController::class, 'index'])->name('bin-sniper');
        Route::post('/bin-sniper/alert', [BinSniperController::class, 'storeAlert'])->name('bin-sniper.alert.store');
        Route::delete('/bin-sniper/alert', [BinSniperController::class, 'destroyAlert'])->name('bin-sniper.alert.destroy');
        Route::patch('/bin-sniper/alert', [BinSniperController::class, 'toggleAlert'])->name('bin-sniper.alert.toggle');
    });
});

// SEO Routes
Route::get('/sitemap.xml', function () {
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
    $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL;

    $urls = [
        ['url' => url('/'), 'changefreq' => 'weekly', 'priority' => '1.0'],
        ['url' => url('/dashboard'), 'changefreq' => 'daily', 'priority' => '0.9'],
        ['url' => url('/bazaar'), 'changefreq' => 'daily', 'priority' => '0.9'],
        ['url' => url('/npc-flips'), 'changefreq' => 'daily', 'priority' => '0.8'],
        ['url' => url('/profile-stats'), 'changefreq' => 'weekly', 'priority' => '0.8'],
        ['url' => url('/event-timer'), 'changefreq' => 'daily', 'priority' => '0.8'],
        ['url' => url('/mayors'), 'changefreq' => 'daily', 'priority' => '0.75'],
        ['url' => url('/guides'), 'changefreq' => 'weekly', 'priority' => '0.8'],
        ['url' => url('/trust-index'), 'changefreq' => 'weekly', 'priority' => '0.7'],
        ['url' => url('/leaderboards'), 'changefreq' => 'weekly', 'priority' => '0.7'],
        ['url' => url('/leaderboards/info'), 'changefreq' => 'monthly', 'priority' => '0.5'],
        ['url' => url('/about'), 'changefreq' => 'monthly', 'priority' => '0.5'],
        ['url' => url('/privacy'), 'changefreq' => 'yearly', 'priority' => '0.3'],
        ['url' => url('/terms'), 'changefreq' => 'yearly', 'priority' => '0.3'],
    ];

    foreach (\App\Models\Guide::published()->orderBy('slug')->pluck('slug') as $slug) {
        $urls[] = [
            'url' => route('guides.show', $slug),
            'changefreq' => 'monthly',
            'priority' => '0.6',
        ];
    }

    foreach ($urls as $page) {
        $sitemap .= '  <url>'.PHP_EOL;
        $sitemap .= '    <loc>'.$page['url'].'</loc>'.PHP_EOL;
        $sitemap .= '    <changefreq>'.$page['changefreq'].'</changefreq>'.PHP_EOL;
        $sitemap .= '    <priority>'.$page['priority'].'</priority>'.PHP_EOL;
        $sitemap .= '  </url>'.PHP_EOL;
    }

    $sitemap .= '</urlset>';

    return response($sitemap, 200, ['Content-Type' => 'application/xml']);
})->name('sitemap');

require __DIR__.'/auth.php';
