<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @php
            $__hypixelSiteVerify = trim((string) config('hypixel.site_verification.meta_content'));
        @endphp
        @if($__hypixelSiteVerify !== '')
            <meta name="{{ e(config('hypixel.site_verification.meta_name')) }}" content="{{ e($__hypixelSiteVerify) }}">
        @endif
        <script>
            window.__SKYBLOCKHUB_CONFIG__ = {
                broadcastingEnabled: @json(config('broadcasting.default') === 'reverb'),
            };
        </script>

        <title inertia>SkyblockHub — Hypixel SkyBlock Intelligence</title>
        <meta name="description" inertia content="SkyblockHub is a Hypixel SkyBlock intelligence dashboard for Bazaar flips, NPC arbitrage, profile stats, money-making, mayor tracking, and event timers.">
        <meta name="theme-color" content="#0f172a">
        <meta name="application-name" content="SkyblockHub">
        <meta name="robots" inertia content="index, follow">
        <meta name="language" content="English">
        <link rel="canonical" inertia href="">

        <meta property="og:site_name" content="SkyblockHub">
        <meta property="og:type" inertia content="website">
        <meta property="og:title" inertia content="SkyblockHub — Hypixel SkyBlock Intelligence">
        <meta property="og:description" inertia content="SkyblockHub is a Hypixel SkyBlock intelligence dashboard for Bazaar flips, NPC arbitrage, profile stats, money-making, mayor tracking, and event timers.">
        <meta property="og:image" inertia content="{{ url('/img/logo-white.webp') }}">
        <meta property="og:url" inertia content="">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" inertia content="SkyblockHub — Hypixel SkyBlock Intelligence">
        <meta name="twitter:description" inertia content="SkyblockHub is a Hypixel SkyBlock intelligence dashboard for Bazaar flips, NPC arbitrage, profile stats, money-making, mayor tracking, and event timers.">
        <meta name="twitter:image" inertia content="{{ url('/img/logo-white.webp') }}">

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
        <link rel="icon" href="{{ asset('favicon.webp') }}" type="image/webp" sizes="32x32">
        <link rel="icon" href="{{ asset('img/logo-white.webp') }}" type="image/webp" sizes="512x512">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.webp') }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @unless(app()->runningUnitTests())
            @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @endunless
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
