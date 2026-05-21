<?php

namespace App\Services;

use Illuminate\Http\Request;

class SeoService
{
    private const DEFAULT_TITLE = 'SkyblockHub — Hypixel SkyBlock Intelligence';

    private const DEFAULT_DESCRIPTION = 'SkyblockHub is a Hypixel SkyBlock intelligence dashboard for Bazaar flips, NPC arbitrage, profile stats, money-making estimates, mayor tracking, guides, and event timers.';

    public static function getPageSeo(Request $request): array
    {
        $routeName = $request->route()?->getName();
        $path = trim($request->path(), '/');

        if ($routeName === 'guides.show' || $routeName === 'guides.submit' || $routeName === 'guides.suggest-edit') {
            return self::defaults();
        }

        $byRoute = self::seoByRouteName($routeName, $request);
        if ($byRoute !== null) {
            return $byRoute;
        }

        return self::seoByPath($path, $request);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function seoByRouteName(?string $routeName, Request $request): ?array
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        if (self::isNoIndexRoute($routeName)) {
            return self::merge([
                'robots' => 'noindex, nofollow',
                'canonical' => $request->url(),
            ]);
        }

        return match ($routeName) {
            'dashboard' => self::merge([
                'title' => 'Dashboard — SkyblockHub',
                'description' => 'Your personalized SkyBlock dashboard: profile snapshot, mayor perks, and quick access to Bazaar, NPC flips, and more.',
                'canonical' => route('dashboard'),
            ]),
            'bazaar' => self::merge([
                'title' => 'Bazaar Flips — SkyblockHub',
                'description' => 'Find profitable Hypixel SkyBlock Bazaar flips with filters, margins, and insta-buy/sell prices.',
                'canonical' => route('bazaar'),
            ]),
            'npc-flips' => self::merge([
                'title' => 'NPC Flips — SkyblockHub',
                'description' => 'Discover NPC arbitrage: buy from Bazaar and sell to NPCs (or the reverse) with live profit margins.',
                'canonical' => route('npc-flips'),
            ]),
            'profile-stats' => self::merge([
                'title' => 'Profile Stats — SkyblockHub',
                'description' => 'View Hypixel SkyBlock profile stats, gear, pets, net worth, skills, and inventories for any player.',
                'canonical' => route('profile-stats'),
            ]),
            'money-making' => self::merge([
                'title' => 'Money Making Methods — SkyblockHub',
                'description' => 'Enter your Minecraft username to see the best money-making methods for your gear, pets, and skills with coins/hour estimates.',
                'canonical' => route('money-making'),
                'robots' => 'noindex, nofollow',
            ]),
            'event-timer' => self::merge([
                'title' => 'Event Timer — SkyblockHub',
                'description' => 'Live SkyBlock event countdowns: mayor elections, Dungeons, Spooky, Jerry\'s Workshop, and more.',
                'canonical' => route('event-timer'),
            ]),
            'mayors' => self::merge([
                'title' => 'Mayor & Perks — SkyblockHub',
                'description' => 'Current Hypixel SkyBlock mayor, perks, and election cycle — plan your activities around active bonuses.',
                'canonical' => route('mayors'),
            ]),
            'leaderboards' => self::merge([
                'title' => 'Leaderboards — SkyblockHub',
                'description' => 'Hypixel SkyBlock leaderboards for skills, net worth, slayers, dungeons, and more.',
                'canonical' => route('leaderboards'),
            ]),
            'leaderboards.info' => self::merge([
                'title' => 'How Leaderboards Work — SkyblockHub',
                'description' => 'Learn how SkyblockHub leaderboard data is collected and how rankings are calculated.',
                'canonical' => route('leaderboards.info'),
            ]),
            'trust-index' => self::merge([
                'title' => 'Scammer List — SkyblockHub',
                'description' => 'Search players against the community scammer list and read common SkyBlock trade and co-op scams.',
                'canonical' => route('trust-index'),
            ]),
            'trust-index.report' => self::merge([
                'title' => 'Report a Scam — SkyblockHub',
                'description' => 'Submit a scam report with evidence for the SkyblockHub community scammer list.',
                'canonical' => route('trust-index.report'),
                'robots' => 'noindex, follow',
            ]),
            'trust-index.appeal' => self::merge([
                'title' => 'Appeal Listing — SkyblockHub',
                'description' => 'Appeal a wrongful entry on the SkyblockHub community scammer list.',
                'canonical' => route('trust-index.appeal'),
                'robots' => 'noindex, follow',
            ]),
            'guides' => self::merge([
                'title' => 'SkyBlock Guides — SkyblockHub',
                'description' => 'Practical Hypixel SkyBlock guides: progression, skills, dungeons, mining, farming, money making, events, and mods.',
                'canonical' => route('guides'),
            ]),
            'about' => self::merge([
                'title' => 'About — SkyblockHub',
                'description' => 'About SkyblockHub — who built it, why it exists, and how to get in touch.',
                'canonical' => route('about'),
            ]),
            'privacy' => self::merge([
                'title' => 'Privacy Policy — SkyblockHub',
                'description' => 'SkyblockHub privacy policy: what data we collect, cookies, and third-party services.',
                'canonical' => route('privacy'),
            ]),
            'terms' => self::merge([
                'title' => 'Terms of Service — SkyblockHub',
                'description' => 'SkyblockHub terms of service and usage guidelines.',
                'canonical' => route('terms'),
            ]),
            'crafting' => self::merge([
                'title' => 'Crafting Arbitrage — SkyblockHub',
                'description' => 'Find profitable Hypixel SkyBlock crafting flips from Bazaar ingredient costs.',
                'canonical' => route('crafting'),
                'robots' => 'noindex, follow',
            ]),
            'portfolio' => self::merge([
                'title' => 'Portfolio Tracker — SkyblockHub',
                'description' => 'Track your SkyBlock Bazaar portfolio, buys, sells, and profit over time.',
                'canonical' => route('portfolio'),
                'robots' => 'noindex, follow',
            ]),
            'bin-sniper' => self::merge([
                'title' => 'BIN Sniper — SkyblockHub',
                'description' => 'Lowest BIN sniper alerts for Hypixel SkyBlock Auction House deals.',
                'canonical' => route('bin-sniper'),
                'robots' => 'noindex, follow',
            ]),
            'dungeon-party' => self::merge([
                'title' => 'Dungeon Party Finder — SkyblockHub',
                'description' => 'Find or host Hypixel SkyBlock dungeon party listings.',
                'canonical' => route('dungeon-party'),
                'robots' => 'noindex, follow',
            ]),
            'hypixel.developer-verification' => self::merge([
                'title' => 'Hypixel Developer Verification — SkyblockHub',
                'description' => 'SkyblockHub ownership verification for the Hypixel developer dashboard.',
                'robots' => 'noindex, nofollow',
            ]),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function seoByPath(string $path, Request $request): array
    {
        if ($path === '') {
            return self::merge([
                'title' => self::DEFAULT_TITLE,
                'description' => self::DEFAULT_DESCRIPTION,
                'ogTitle' => 'SkyblockHub — Hypixel SkyBlock Tools',
                'ogDescription' => 'Bazaar flips, NPC arbitrage, profile stats, money-making calculator, mayor perks, and event timers in one place.',
                'canonical' => url('/'),
            ]);
        }

        if (str_starts_with($path, 'admin') || str_starts_with($path, 'analitics')) {
            return self::merge([
                'robots' => 'noindex, nofollow',
                'canonical' => $request->url(),
            ]);
        }

        return self::defaults();
    }

    private static function isNoIndexRoute(string $routeName): bool
    {
        foreach (['login', 'register', 'password.', 'verification.', 'profile.edit', 'profile.update', 'profile.destroy', 'admin.', 'analitics.'] as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function merge(array $overrides): array
    {
        $base = self::defaults();
        $merged = array_merge($base, $overrides);

        $merged['ogTitle'] = $overrides['ogTitle'] ?? $merged['title'];
        $merged['ogDescription'] = $overrides['ogDescription'] ?? $merged['description'];

        if (empty($merged['canonical'])) {
            $merged['canonical'] = $overrides['canonical'] ?? null;
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'title' => self::DEFAULT_TITLE,
            'description' => self::DEFAULT_DESCRIPTION,
            'ogTitle' => self::DEFAULT_TITLE,
            'ogDescription' => self::DEFAULT_DESCRIPTION,
            'ogImage' => url('/img/logo-white.webp'),
            'ogType' => 'website',
            'canonical' => null,
            'robots' => 'index, follow',
        ];
    }
}
