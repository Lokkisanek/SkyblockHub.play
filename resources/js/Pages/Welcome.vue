<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/strings/useI18n';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import WelcomeModuleIcon from '@/Components/WelcomeModuleIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { trackFunnelEvent } from '@/lib/funnelAnalytics';

const { t } = useI18n();

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    socialProofMetrics: { type: Object, default: () => ({}) },
    canRegister: { type: Boolean, default: false },
    laravelVersion: { type: String, required: false },
    phpVersion: { type: String, required: false },
});

const page = usePage();
const isTestingAdmin = computed(() => Boolean(page.props.auth?.testing_admin));

const authUser = computed(() => page.props.auth?.user ?? null);
const isLoggedIn = computed(() => Boolean(authUser.value));

const welcomeDisplayName = computed(() => {
    const u = authUser.value;
    if (!u) return '';

    return u.minecraft_username || u.discord_username || u.name || '';
});

const onboarding = computed(() => page.props.onboarding ?? null);
const showContinueSetup = computed(() => isLoggedIn.value && onboarding.value?.show === true);

const heroSubtitleKey = computed(() => (isLoggedIn.value ? 'welcome.loggedInSubtitle' : 'welcome.subtitle'));
const searchHelperKey = computed(() => (isLoggedIn.value ? 'welcome.loggedInSearchHelper' : 'welcome.searchHelper'));
const modulesTitleKey = computed(() => (isLoggedIn.value ? 'welcome.loggedInFreeTitle' : 'welcome.freeTitle'));
const modulesSubtitleKey = computed(() => (isLoggedIn.value ? 'welcome.loggedInCoreModules' : 'welcome.coreModules'));

const searchUsername = ref('');
const searchError = ref('');
const siteOrigin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost:8000';
const canonicalUrl = `${siteOrigin}/`;
const pageTitle = 'Hypixel SkyBlock Tools - Bazaar Flips, NPC Arbitrage, Profiles';
const pageDescription = 'SkyblockHub is a clean Hypixel SkyBlock dashboard for Bazaar flips, NPC arbitrage, profile analysis, mayor perks, and event timing.';
const currentYear = new Date().getFullYear();

const featureCards = computed(() => [
    {
        id: 'bazaar',
        title: t('welcome.features.bazaarTitle'),
        subtitle: t('welcome.features.bazaarSub'),
        description: t('welcome.features.bazaarDesc'),
        iconId: 'bazaar',
        routeName: 'bazaar',
        accent: 'emerald',
    },
    {
        id: 'npc',
        title: t('welcome.features.npcTitle'),
        subtitle: t('welcome.features.npcSub'),
        description: t('welcome.features.npcDesc'),
        iconId: 'npc',
        routeName: 'npc-flips',
        accent: 'amber',
    },
    {
        id: 'profiles',
        title: t('welcome.features.profileTitle'),
        subtitle: t('welcome.features.profileSub'),
        description: t('welcome.features.profileDesc'),
        iconId: 'profiles',
        routeName: 'profile-stats',
        accent: 'emerald',
    },
    {
        id: 'events',
        title: t('welcome.features.eventTitle'),
        subtitle: t('welcome.features.eventSub'),
        description: t('welcome.features.eventDesc'),
        iconId: 'events',
        routeName: 'event-timer',
        accent: 'indigo',
    },
    {
        id: 'mayors',
        title: t('welcome.features.mayorTitle'),
        subtitle: t('welcome.features.mayorSub'),
        description: t('welcome.features.mayorDesc'),
        iconId: 'mayors',
        routeName: 'mayors',
        accent: 'indigo',
    },
]);

const featureCardsById = computed(() => Object.fromEntries(
    featureCards.value.map((card) => [card.id, card]),
));

const socialMetrics = ref({
    active_online: Number(props.socialProofMetrics?.active_online || 0),
    tracked_flips: Number(props.socialProofMetrics?.tracked_flips || 0),
    profiles_loaded: Number(props.socialProofMetrics?.profiles_loaded || 0),
});

let socialMetricsInterval = null;

function formatMetric(value) {
    const n = Number(value || 0);

    if (n >= 1000000) {
        const v = (n / 1000000).toFixed(1).replace('.0', '');

        return `${v}M+`;
    }

    if (n >= 1000) {
        const v = (n / 1000).toFixed(1).replace('.0', '');

        return `${v}K+`;
    }

    return `${n.toLocaleString('en-US')}+`;
}

async function refreshSocialMetrics() {
    try {
        const response = await fetch('/api/social-proof-metrics', {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            return;
        }

        const payload = await response.json();

        if (!payload?.metrics) {
            return;
        }

        socialMetrics.value = {
            active_online: Number(payload.metrics.active_online || 0),
            tracked_flips: Number(payload.metrics.tracked_flips || 0),
            profiles_loaded: Number(payload.metrics.profiles_loaded || 0),
        };
    } catch (_) {
        // Keep last known values when refresh fails.
    }
}

onMounted(() => {
    refreshSocialMetrics();
    socialMetricsInterval = window.setInterval(refreshSocialMetrics, 60000);
});

onBeforeUnmount(() => {
    if (socialMetricsInterval !== null) {
        window.clearInterval(socialMetricsInterval);
    }
});

const socialStats = computed(() => [
    {
        value: formatMetric(socialMetrics.value.active_online),
        label: t('welcome.social.stats.playersLabel'),
    },
    {
        value: formatMetric(socialMetrics.value.tracked_flips),
        label: t('welcome.social.stats.flipsLabel'),
    },
    {
        value: formatMetric(socialMetrics.value.profiles_loaded),
        label: t('welcome.social.stats.profilesLabel'),
    },
]);

function submitSearch() {
    searchError.value = '';
    const username = searchUsername.value.trim();

    if (!username) {
        searchError.value = t('welcome.searchEmpty');

        return;
    }

    let profileUrl;

    try {
        profileUrl = route('profile-stats');
    } catch {
        searchError.value = t('welcome.searchFailed');

        return;
    }

    router.get(profileUrl, { username }, {
        onError: () => {
            searchError.value = t('welcome.searchFailed');
        },
    });
}

function clearSearchError() {
    if (searchError.value) {
        searchError.value = '';
    }
}

function trackLandingCta(cta) {
    trackFunnelEvent('landing_cta_click', {
        cta,
        is_guest: Boolean(props.canLogin),
    }, {
        path: '/',
    });
}

function cardAccentClass(accent) {
    if (accent === 'emerald') return 'card-accent-emerald';
    if (accent === 'amber') return 'card-accent-amber';
    return 'card-accent-indigo';
}
</script>

<template>
    <Head>
        <title>{{ pageTitle }}</title>
        <meta head-key="description" name="description" :content="pageDescription" />
        <meta head-key="robots" name="robots" content="index,follow" />
        <link head-key="canonical" rel="canonical" :href="canonicalUrl" />
        <meta head-key="og:title" property="og:title" :content="`${pageTitle} - SkyblockHub`" />
        <meta head-key="og:description" property="og:description" :content="pageDescription" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:image" property="og:image" :content="`${siteOrigin}/img/logo-white.webp`" />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="`${pageTitle} - SkyblockHub`" />
        <meta head-key="twitter:description" name="twitter:description" :content="pageDescription" />
    </Head>

    <AuthenticatedLayout>
        <div class="pt-16 pb-28 sm:pt-20 sm:pb-32 lg:pt-24 lg:pb-40">
            <div class="mx-auto max-w-7xl space-y-16 px-4 sm:space-y-20 sm:px-6 lg:space-y-24 lg:px-8">
                <section class="animate-rise-up animate-delay-1">
                    <div v-if="isLoggedIn" class="mx-auto mb-8 max-w-2xl text-center">
                        <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-profit/80">{{ t('welcome.loggedInKicker') }}</p>
                        <p v-if="welcomeDisplayName" class="mt-2 text-xl font-semibold tracking-tight text-white sm:text-2xl">
                            {{ welcomeDisplayName }}
                        </p>
                    </div>

                    <h1 class="mx-auto max-w-6xl text-center text-6xl font-black leading-[1.02] tracking-tight text-white sm:text-7xl lg:text-8xl">
                        {{ $t('welcome.hero') }}
                    </h1>

                    <p class="mx-auto mt-8 max-w-3xl text-center text-lg leading-relaxed text-white/80 sm:mt-10 sm:text-xl lg:text-2xl">
                        {{ t(heroSubtitleKey) }}
                    </p>

                    <div class="mx-auto mt-10 w-full max-w-3xl rounded-2xl border border-border/80 bg-surface-900/75 p-3 shadow-[0_16px_40px_rgba(0,0,0,0.35)] backdrop-blur-sm sm:mt-12">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <div class="flex-1">
                                <input
                                    v-model="searchUsername"
                                    type="text"
                                    :placeholder="$t('welcome.searchPlaceholder')"
                                    class="w-full rounded-xl border border-border/80 bg-surface-800/80 px-4 py-3 text-base text-white placeholder:text-neutral/80 transition focus:border-profit/70 focus:outline-none focus:ring-2 focus:ring-profit/25"
                                    autocomplete="username"
                                    :aria-invalid="Boolean(searchError)"
                                    :aria-describedby="searchError ? 'welcome-search-error' : undefined"
                                    @keyup.enter="submitSearch"
                                    @input="clearSearchError"
                                />
                            </div>
                            <button
                                type="button"
                                @click="submitSearch"
                                class="inline-flex h-[46px] items-center justify-center rounded-xl border border-profit/35 bg-profit/20 px-6 text-base font-semibold text-profit transition hover:bg-profit/30 hover:text-white"
                            >
                                {{ $t('welcome.searchProfile') }}
                            </button>
                        </div>
                    </div>
                    <p
                        v-if="searchError"
                        id="welcome-search-error"
                        role="alert"
                        class="mt-2 text-center text-sm text-amber-200/95"
                    >
                        {{ searchError }}
                    </p>
                    <p class="mt-3 text-center text-sm text-white/65">{{ t(searchHelperKey) }}</p>

                    <!-- One secondary CTA above the fold: Discord (guests) or Dashboard (signed in). Join server = text link. -->
                    <div class="mt-10 flex flex-col items-center gap-4 sm:flex-row sm:flex-wrap sm:justify-center sm:gap-x-8 sm:gap-y-4">
                        <template v-if="canLogin">
                            <a
                                :href="route('auth.discord', { redirect: page.url || '/' })"
                                @click="trackLandingCta('discord_login')"
                                class="inline-flex items-center justify-center gap-2.5 rounded-xl border border-[#5865F2]/50 bg-[#5865F2] px-6 py-3 text-sm font-bold text-white shadow-[0_4px_20px_rgba(88,101,242,0.3)] transition hover:bg-[#4752C4]"
                            >
                                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.318 4.369A19.791 19.791 0 0 0 15.432 2.85a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.1 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
                                {{ $t('welcome.loginDiscord') }}
                            </a>
                            <a
                                href="https://discord.gg/TkavZSfUAd"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 text-sm font-medium text-white/55 underline decoration-white/20 underline-offset-4 transition hover:text-white/90 hover:decoration-white/40"
                                @click="trackLandingCta('discord_invite_link')"
                            >
                                {{ $t('welcome.joinDiscordServer') }}
                                <svg class="h-3.5 w-3.5 shrink-0 opacity-70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-1.5 0V7.56l-8.22 8.22a.75.75 0 0 1-1.06-1.06l8.22-8.22H5a.75.75 0 0 1-.75-.75z" clip-rule="evenodd" /></svg>
                            </a>
                        </template>
                        <template v-else>
                            <Link
                                :href="route('dashboard')"
                                @click="trackLandingCta('open_dashboard')"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-profit/40 bg-profit/15 px-6 py-3 text-sm font-semibold text-profit transition hover:bg-profit/30 hover:text-white"
                            >
                                {{ $t('welcome.openDashboard') }}
                            </Link>
                            <Link
                                v-if="showContinueSetup"
                                :href="route('dashboard')"
                                @click="trackLandingCta('continue_setup')"
                                class="text-sm font-medium text-white/55 underline decoration-white/25 underline-offset-4 transition hover:text-emerald-200/90 hover:decoration-emerald-400/50"
                            >
                                {{ $t('welcome.continueSetup') }}
                            </Link>
                        </template>
                    </div>
                </section>

                <section class="animate-rise-up animate-delay-3">
                    <p class="mb-8 text-center text-sm font-semibold leading-snug text-white/70 sm:mb-10 sm:text-base">
                        <span class="text-emerald-200/90">{{ $t('welcome.social.trustLine') }}</span>
                    </p>

                    <div class="grid gap-10 md:grid-cols-3 md:gap-12">
                        <article
                            v-for="item in socialStats"
                            :key="item.label"
                            class="rounded-2xl border border-border/80 bg-surface-900/75 p-5 text-center shadow-[0_16px_40px_rgba(0,0,0,0.35)] backdrop-blur-sm"
                        >
                            <p class="text-3xl font-black text-white">{{ item.value }}</p>
                            <p class="mt-2 text-xs font-semibold uppercase tracking-[0.16em] text-white/55">{{ item.label }}</p>
                        </article>
                    </div>
                </section>

                <section class="animate-rise-up animate-delay-4">
                    <div class="mb-10 text-center sm:mb-14">
                        <h2 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ t(modulesTitleKey) }}</h2>
                        <p class="mx-auto mt-4 max-w-lg text-sm leading-relaxed text-white/55 sm:mt-5 sm:text-base">{{ t(modulesSubtitleKey) }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-7 lg:grid-cols-[2fr_1fr] lg:gap-8">
                        <!-- Left column: 2 large cards -->
                        <div class="grid grid-cols-1 gap-7 lg:gap-8">
                            <Link
                                v-if="featureCardsById.bazaar"
                                :key="featureCardsById.bazaar.id"
                                :href="route(featureCardsById.bazaar.routeName)"
                                class="feature-card group flex min-h-0 flex-col rounded-2xl border border-border/80 bg-surface-900/75 p-6 shadow-[0_16px_40px_rgba(0,0,0,0.35),inset_0_1px_0_0_rgba(255,255,255,0.05)] backdrop-blur-sm hover:border-white/[0.14] hover:bg-surface-900/92 focus:outline-none focus-visible:ring-2 focus-visible:ring-profit/35 sm:p-8"
                                :class="[cardAccentClass(featureCardsById.bazaar.accent), 'animate-rise-up animate-delay-card-1']"
                            >
                                <div class="flex flex-1 flex-col gap-5 sm:flex-row sm:gap-7">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-2xl text-white shadow-[0_0_0_1px_rgba(255,255,255,0.02)] sm:h-14 sm:w-14 sm:text-[1.65rem]">
                                        <WelcomeModuleIcon :name="featureCardsById.bazaar.iconId" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-xl font-bold tracking-tight text-white sm:text-2xl">{{ featureCardsById.bazaar.title }}</h3>
                                        <p class="mt-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-white/55">{{ featureCardsById.bazaar.subtitle }}</p>
                                        <p class="mt-3 text-sm leading-snug text-white/72 sm:text-[15px] sm:leading-relaxed">{{ featureCardsById.bazaar.description }}</p>
                                    </div>
                                </div>

                                <div class="feature-card-cta mt-6 border-t border-white/[0.08] pt-4 text-xs font-semibold uppercase tracking-[0.12em] text-white/45 transition group-hover:border-profit/20 group-hover:text-profit sm:mt-7 sm:pt-5">
                                    <span class="inline-flex items-center gap-1.5">
                                        {{ $t('welcome.openModule') }}
                                        <svg class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
                                    </span>
                                </div>
                            </Link>

                            <Link
                                v-if="featureCardsById.profiles"
                                :key="featureCardsById.profiles.id"
                                :href="route(featureCardsById.profiles.routeName)"
                                class="feature-card group flex min-h-0 flex-col rounded-2xl border border-border/80 bg-surface-900/75 p-6 shadow-[0_16px_40px_rgba(0,0,0,0.35),inset_0_1px_0_0_rgba(255,255,255,0.05)] backdrop-blur-sm hover:border-white/[0.14] hover:bg-surface-900/92 focus:outline-none focus-visible:ring-2 focus-visible:ring-profit/35 sm:p-8"
                                :class="[cardAccentClass(featureCardsById.profiles.accent), 'animate-rise-up animate-delay-card-4']"
                            >
                                <div class="flex flex-1 flex-col gap-5 sm:flex-row sm:gap-7">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-2xl text-white shadow-[0_0_0_1px_rgba(255,255,255,0.02)] sm:h-14 sm:w-14 sm:text-[1.65rem]">
                                        <WelcomeModuleIcon :name="featureCardsById.profiles.iconId" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-xl font-bold tracking-tight text-white sm:text-2xl">{{ featureCardsById.profiles.title }}</h3>
                                        <p class="mt-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-white/55">{{ featureCardsById.profiles.subtitle }}</p>
                                        <p class="mt-3 text-sm leading-snug text-white/72 sm:text-[15px] sm:leading-relaxed">{{ featureCardsById.profiles.description }}</p>
                                    </div>
                                </div>

                                <div class="feature-card-cta mt-6 border-t border-white/[0.08] pt-4 text-xs font-semibold uppercase tracking-[0.12em] text-white/45 transition group-hover:border-profit/20 group-hover:text-profit sm:mt-7 sm:pt-5">
                                    <span class="inline-flex items-center gap-1.5">
                                        {{ $t('welcome.openModule') }}
                                        <svg class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
                                    </span>
                                </div>
                            </Link>
                        </div>

                        <!-- Right column: 3 smaller cards -->
                        <div class="grid grid-cols-1 gap-7 lg:gap-8">
                            <Link
                                v-if="featureCardsById.npc"
                                :key="featureCardsById.npc.id"
                                :href="route(featureCardsById.npc.routeName)"
                                class="feature-card group flex min-h-0 flex-col rounded-2xl border border-border/80 bg-surface-900/75 p-5 shadow-[0_16px_40px_rgba(0,0,0,0.35),inset_0_1px_0_0_rgba(255,255,255,0.05)] backdrop-blur-sm hover:border-white/[0.14] hover:bg-surface-900/92 focus:outline-none focus-visible:ring-2 focus-visible:ring-profit/35 sm:p-6"
                                :class="[cardAccentClass(featureCardsById.npc.accent), 'animate-rise-up animate-delay-card-2']"
                            >
                                <div class="flex flex-1 gap-3.5 sm:gap-4">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-lg text-white shadow-[0_0_0_1px_rgba(255,255,255,0.02)] sm:h-12 sm:w-12 sm:text-xl">
                                        <WelcomeModuleIcon :name="featureCardsById.npc.iconId" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-base font-bold leading-tight tracking-tight text-white">{{ featureCardsById.npc.title }}</h3>
                                        <p class="mt-1.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-white/50">{{ featureCardsById.npc.subtitle }}</p>
                                        <p class="mt-2 text-sm leading-snug text-white/70 line-clamp-3">{{ featureCardsById.npc.description }}</p>
                                    </div>
                                </div>
                                <div class="feature-card-cta mt-4 border-t border-white/[0.08] pt-3.5 text-[11px] font-semibold uppercase tracking-[0.12em] text-white/45 transition group-hover:border-profit/20 group-hover:text-profit sm:mt-5 sm:pt-4">
                                    <span class="inline-flex items-center gap-1.5">
                                        {{ $t('welcome.openModule') }}
                                        <svg class="h-3 w-3 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
                                    </span>
                                </div>
                            </Link>

                            <Link
                                v-if="featureCardsById.events"
                                :key="featureCardsById.events.id"
                                :href="route(featureCardsById.events.routeName)"
                                class="feature-card group flex min-h-0 flex-col rounded-2xl border border-border/80 bg-surface-900/75 p-5 shadow-[0_16px_40px_rgba(0,0,0,0.35),inset_0_1px_0_0_rgba(255,255,255,0.05)] backdrop-blur-sm hover:border-white/[0.14] hover:bg-surface-900/92 focus:outline-none focus-visible:ring-2 focus-visible:ring-profit/35 sm:p-6"
                                :class="[cardAccentClass(featureCardsById.events.accent), 'animate-rise-up animate-delay-card-3']"
                            >
                                <div class="flex flex-1 gap-3.5 sm:gap-4">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-lg text-white shadow-[0_0_0_1px_rgba(255,255,255,0.02)] sm:h-12 sm:w-12 sm:text-xl">
                                        <WelcomeModuleIcon :name="featureCardsById.events.iconId" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-base font-bold leading-tight tracking-tight text-white">{{ featureCardsById.events.title }}</h3>
                                        <p class="mt-1.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-white/50">{{ featureCardsById.events.subtitle }}</p>
                                        <p class="mt-2 text-sm leading-snug text-white/70 line-clamp-3">{{ featureCardsById.events.description }}</p>
                                    </div>
                                </div>
                                <div class="feature-card-cta mt-4 border-t border-white/[0.08] pt-3.5 text-[11px] font-semibold uppercase tracking-[0.12em] text-white/45 transition group-hover:border-profit/20 group-hover:text-profit sm:mt-5 sm:pt-4">
                                    <span class="inline-flex items-center gap-1.5">
                                        {{ $t('welcome.openModule') }}
                                        <svg class="h-3 w-3 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
                                    </span>
                                </div>
                            </Link>

                            <Link
                                v-if="featureCardsById.mayors"
                                :key="featureCardsById.mayors.id"
                                :href="route(featureCardsById.mayors.routeName)"
                                class="feature-card group flex min-h-0 flex-col rounded-2xl border border-border/80 bg-surface-900/75 p-5 shadow-[0_16px_40px_rgba(0,0,0,0.35),inset_0_1px_0_0_rgba(255,255,255,0.05)] backdrop-blur-sm hover:border-white/[0.14] hover:bg-surface-900/92 focus:outline-none focus-visible:ring-2 focus-visible:ring-profit/35 sm:p-6"
                                :class="[cardAccentClass(featureCardsById.mayors.accent), 'animate-rise-up animate-delay-card-5']"
                            >
                                <div class="flex flex-1 gap-3.5 sm:gap-4">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-lg text-white shadow-[0_0_0_1px_rgba(255,255,255,0.02)] sm:h-12 sm:w-12 sm:text-xl">
                                        <WelcomeModuleIcon :name="featureCardsById.mayors.iconId" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-base font-bold leading-tight tracking-tight text-white">{{ featureCardsById.mayors.title }}</h3>
                                        <p class="mt-1.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-white/50">{{ featureCardsById.mayors.subtitle }}</p>
                                        <p class="mt-2 text-sm leading-snug text-white/70 line-clamp-3">{{ featureCardsById.mayors.description }}</p>
                                    </div>
                                </div>
                                <div class="feature-card-cta mt-4 border-t border-white/[0.08] pt-3.5 text-[11px] font-semibold uppercase tracking-[0.12em] text-white/45 transition group-hover:border-profit/20 group-hover:text-profit sm:mt-5 sm:pt-4">
                                    <span class="inline-flex items-center gap-1.5">
                                        {{ $t('welcome.openModule') }}
                                        <svg class="h-3 w-3 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
                                    </span>
                                </div>
                            </Link>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <footer class="footer-wrapper relative mt-16 sm:mt-20 lg:mt-24">
            <div class="slime-glow-footer-container">
                <div class="slime-glow-footer"></div>
            </div>

            <div class="relative z-10 mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-14 lg:px-8">
                <div class="grid grid-cols-2 gap-8 sm:grid-cols-4">
                    <div class="col-span-2 sm:col-span-1">
                        <Link :href="route('dashboard')" class="flex items-center gap-2 text-sm font-bold tracking-wide text-white">
                            <ApplicationLogo tone="light" class="h-7 w-7 shrink-0" />
                            <span>SkyblockHub</span>
                        </Link>
                        <p class="mt-2 text-xs leading-relaxed text-white/30">{{ $t('welcome.footer.tagline') }}</p>
                    </div>

                    <div>
                        <h3 class="text-[10px] font-bold uppercase tracking-widest text-white/40">{{ $t('welcome.footer.modules') }}</h3>
                        <ul class="mt-3 space-y-2">
                            <li><Link :href="route('bazaar')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.bazaarFlips') }}</Link></li>
                            <li><Link :href="route('npc-flips')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.npcArbitrage') }}</Link></li>
                            <li><Link :href="route('event-timer')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.eventTimer') }}</Link></li>
                            <li><Link :href="route('mayors')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.mayorIntel') }}</Link></li>
                            <li><Link :href="route('profile-stats')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.profileStats') }}</Link></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-[10px] font-bold uppercase tracking-widest text-white/40">{{ $t('welcome.footer.project') }}</h3>
                        <ul class="mt-3 space-y-2">
                            <li><Link :href="route('about')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.about') }}</Link></li>
                            <li v-if="isTestingAdmin"><Link :href="route('admin.index')" class="text-xs text-white/35 transition hover:text-white">Admin</Link></li>
                            <li><a href="https://github.com/Lokkisanek/SkyblockHub.play" target="_blank" rel="noopener noreferrer" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.github') }}</a></li>
                            <li><a href="https://buymeacoffee.com/lokkisan" target="_blank" rel="noopener noreferrer" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.patreon') }}</a></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-[10px] font-bold uppercase tracking-widest text-white/40">{{ $t('welcome.footer.legal') }}</h3>
                        <ul class="mt-3 space-y-2">
                            <li><Link :href="route('privacy')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.privacyPolicy') }}</Link></li>
                            <li><Link :href="route('terms')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.terms') }}</Link></li>
                        </ul>
                    </div>
                </div>

                <div class="mt-8 flex flex-col items-center justify-between gap-3 border-t border-white/[0.06] pt-6 sm:flex-row">
                    <p class="text-[11px] text-white/25">{{ t('welcome.footer.copyright', { year: currentYear }) }}</p>
                    <p class="text-[11px] text-white/20">{{ $t('welcome.footer.notAffiliated') }}</p>
                </div>
            </div>
        </footer>
    </AuthenticatedLayout>
</template>

<style scoped>
.footer-wrapper {
    background: linear-gradient(180deg, rgba(16, 16, 16, 0.95) 0%, rgba(16, 16, 16, 0.88) 100%);
    backdrop-filter: blur(16px);
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    z-index: 10;
}

.slime-glow-footer-container {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    overflow: hidden;
    pointer-events: none;
    z-index: 1;
}

.slime-glow-footer {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 120px;
    height: 50px;
    border-radius: 4px;
    background: rgba(93, 211, 93, 0.08);
    filter: blur(35px);
    pointer-events: none;
    animation: footerSlimeDrift 40s ease-in-out infinite, footerSlimeBounce 5s ease-in-out infinite;
}

.slime-glow-footer::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 60px;
    height: 40px;
    border-radius: 3px;
    background: rgba(61, 168, 61, 0.06);
    filter: blur(20px);
    animation: footerSlimePulse 6s ease-in-out infinite;
}

@keyframes footerSlimeDrift {
    0%, 100% { left: 75%; }
    33% { left: 40%; }
    66% { left: 10%; }
}

@keyframes footerSlimeBounce {
    0%, 100% { transform: translateY(4px); }
    50% { transform: translateY(-6px); }
}

@keyframes footerSlimePulse {
    0%, 100% { opacity: 0.5; }
    50% { opacity: 1; }
}

.animate-rise-up {
    opacity: 0;
    transform: translateY(26px);
    animation: riseUpIn 620ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
    will-change: transform, opacity;
}

.animate-delay-1 { animation-delay: 80ms; }
.animate-delay-2 { animation-delay: 170ms; }
.animate-delay-3 { animation-delay: 260ms; }
.animate-delay-4 { animation-delay: 320ms; }
.animate-delay-5 { animation-delay: 380ms; }
.animate-delay-card-1 { animation-delay: 360ms; }
.animate-delay-card-2 { animation-delay: 420ms; }
.animate-delay-card-3 { animation-delay: 480ms; }
.animate-delay-card-4 { animation-delay: 540ms; }
.animate-delay-card-5 { animation-delay: 600ms; }
.animate-delay-card-6 { animation-delay: 660ms; }

@keyframes riseUpIn {
    0% {
        opacity: 0;
        transform: translateY(26px) scale(0.99);
    }
    65% {
        opacity: 1;
        transform: translateY(-3px) scale(1);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .animate-rise-up {
        opacity: 1;
        transform: none;
        animation: none;
    }

    .feature-card:hover {
        transform: none;
    }
}

.feature-card {
    transition: transform 160ms ease, border-color 160ms ease, border-left-color 160ms ease, background-color 160ms ease;
}

.feature-card:hover {
    transform: translateY(-2px);
}

.card-accent-emerald {
    border-left: 3px solid rgba(16, 185, 129, 0.5);
}

.card-accent-emerald:hover {
    border-left-color: rgba(16, 185, 129, 0.85);
}

.card-accent-amber {
    border-left: 3px solid rgba(251, 191, 36, 0.5);
}

.card-accent-amber:hover {
    border-left-color: rgba(251, 191, 36, 0.88);
}

.card-accent-indigo {
    border-left: 3px solid rgba(129, 140, 248, 0.55);
}

.card-accent-indigo:hover {
    border-left-color: rgba(165, 180, 252, 0.9);
}

</style>
