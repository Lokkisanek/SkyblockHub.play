<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from '@/strings/useI18n';
import SeoHead from '@/Components/SeoHead.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AuthSlidePanel from '@/Components/AuthSlidePanel.vue';
import CookieConsent from '@/Components/CookieConsent.vue';
import OnboardingChecklist from '@/Components/OnboardingChecklist.vue';
import SurveyPopup from '@/Components/SurveyPopup.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const { t } = useI18n();

const showingNavigationDropdown = ref(false);
const showAuthPanel = ref(false);
const authNotice = ref('');
const page = usePage();
const onboarding = computed(() => page.props.onboarding ?? null);

const user = computed(() => page.props.auth?.user ?? null);
const isTestingAdmin = computed(() => Boolean(page.props.auth?.testing_admin));
const experimentalModules = computed(() => page.props.navigation?.experimental_modules ?? {});
const canAccessCrafting = computed(() => isTestingAdmin.value && Boolean(experimentalModules.value.crafting));
const canAccessDungeonParty = computed(() => isTestingAdmin.value && Boolean(experimentalModules.value.dungeon_party));
const canAccessPortfolio = computed(() => isTestingAdmin.value && Boolean(experimentalModules.value.portfolio));
const canAccessBinSniper = computed(() => isTestingAdmin.value && Boolean(experimentalModules.value.bin_sniper));

const displayName = computed(() => {
    if (!user.value) return '';
    return user.value.minecraft_username || user.value.discord_username || user.value.name || 'Profile';
});

const mcAvatarUrl = computed(() => {
    const username = user.value?.minecraft_username;
    if (!username) return null;
    return `https://mc-heads.net/avatar/${encodeURIComponent(username)}/32`;
});

function isActive(routeName) {
    return route().current(routeName);
}

function isBazaarActive() {
    return route().current('bazaar') || route().current('npc-flips') || (canAccessCrafting.value && route().current('crafting'));
}

function isMiscActive() {
    return route().current('event-timer')
        || route().current('mayors')
        || route().current('guides')
        || route().current('guides.show')
        || route().current('trust-index')
        || route().current('trust-index.report')
        || route().current('trust-index.appeal');
}

function isAdminSectionActive() {
    return isActive('admin.index')
        || route().current('admin.guides.submissions*')
        || route().current('admin.trust-index.submissions*');
}

function logout() {
    router.post(route('logout'));
}

function stripAuthQueryFromUrl() {
    if (typeof window === 'undefined') return;

    const params = new URLSearchParams(window.location.search);
    const hadAuthParams = params.has('auth') || params.has('reason') || params.has('plan');
    if (!hadAuthParams) return;

    params.delete('auth');
    params.delete('reason');
    params.delete('plan');

    const query = params.toString();
    const nextUrl = `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`;
    window.history.replaceState({}, '', nextUrl);
}

function closeAuthPanel() {
    showAuthPanel.value = false;
    authNotice.value = '';

    // If auth panel was opened via URL params, ensure they're removed so refresh doesn't re-open it.
    stripAuthQueryFromUrl();
}

function maybeOpenAuthPanelFromUrl() {
    if (typeof window === 'undefined') return;
    if (user.value) return;

    const params = new URLSearchParams(window.location.search);
    if (params.get('auth') !== '1') return;

    showAuthPanel.value = true;

    // Clean up URL so refresh/back doesn't keep re-opening the panel.
    stripAuthQueryFromUrl();
}

onMounted(() => {
    maybeOpenAuthPanelFromUrl();
});

watch(
    () => page.url,
    () => {
        maybeOpenAuthPanelFromUrl();
    },
);
</script>

<template>
    <SeoHead />
    <div>
        <div class="min-h-screen bg-surface-900" style="background-image: url('/background.webp'); background-size: cover; background-position: center; background-attachment: fixed;">
            <nav class="nav-wrapper relative">
                <!-- Slime glow -->
                <div class="slime-glow-container">
                    <div class="slime-glow"></div>
                </div>

                <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="relative flex h-14 items-center">

                        <!-- Left: Logo -->
                        <div class="relative z-10 flex shrink-0 items-center">
                            <Link href="/" class="flex items-center gap-2">
                                <ApplicationLogo tone="light" class="h-7 w-7 shrink-0" />
                                <span class="text-sm font-bold tracking-wide text-white">SkyblockHub</span>
                            </Link>
                        </div>

                        <!-- Center: Navigation -->
                        <div class="absolute left-1/2 top-1/2 hidden -translate-x-1/2 -translate-y-1/2 items-center gap-1 md:flex">
                                <Link
                                    :href="route('dashboard')"
                                    class="nav-link"
                                    :class="{ active: isActive('dashboard') }"
                                >
                                    {{ $t('nav.dashboard') }}
                                </Link>

                                <Dropdown align="left" width="48">
                                    <template #trigger>
                                        <button
                                            class="nav-link inline-flex items-center gap-1"
                                            :class="{ active: isBazaarActive() }"
                                        >
                                            {{ $t('nav.bazaar') }}
                                            <svg class="h-3 w-3 opacity-50" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('bazaar')">{{ $t('nav.flips') }}</DropdownLink>
                                        <DropdownLink :href="route('npc-flips')">{{ $t('nav.npcFlips') }}</DropdownLink>
                                        <DropdownLink v-if="canAccessCrafting" :href="route('crafting')">{{ $t('nav.craftingFlips') }}</DropdownLink>
                                    </template>
                                </Dropdown>

                                <Link
                                    :href="route('profile-stats')"
                                    class="nav-link"
                                    :class="{ active: isActive('profile-stats') }"
                                >
                                    {{ $t('nav.profileStats') }}
                                </Link>

                                <Dropdown align="left" width="48">
                                    <template #trigger>
                                        <button
                                            class="nav-link inline-flex items-center gap-1"
                                            :class="{ active: isMiscActive() }"
                                        >
                                            {{ $t('nav.misc') }}
                                            <svg class="h-3 w-3 opacity-50" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('mayors')">{{ $t('nav.mayors') }}</DropdownLink>
                                        <DropdownLink :href="route('event-timer')">{{ $t('nav.eventTimer') }}</DropdownLink>
                                        <DropdownLink :href="route('guides')">{{ $t('nav.guides') }}</DropdownLink>
                                        <DropdownLink :href="route('trust-index')">{{ $t('nav.trustIndex') }}</DropdownLink>
                                    </template>
                                </Dropdown>

                                <Link
                                    :href="route('leaderboards')"
                                    class="nav-link"
                                    :class="{ active: isActive('leaderboards') }"
                                >
                                    {{ $t('nav.leaderboards') }}
                                </Link>

                                <Dropdown v-if="isTestingAdmin" align="left" width="80">
                                    <template #trigger>
                                        <button
                                            type="button"
                                            class="nav-link inline-flex items-center gap-1"
                                            :class="{ active: isAdminSectionActive() }"
                                        >
                                            {{ $t('nav.admin') }}
                                            <svg class="h-3 w-3 opacity-50" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('admin.index')">{{ $t('nav.adminOperations') }}</DropdownLink>
                                        <DropdownLink :href="route('admin.guides.submissions')">{{ $t('nav.adminGuideQueue') }}</DropdownLink>
                                        <DropdownLink :href="route('admin.trust-index.submissions')">{{ $t('nav.adminTrustQueue') }}</DropdownLink>
                                    </template>
                                </Dropdown>

                                <template v-if="canAccessDungeonParty || canAccessPortfolio || canAccessBinSniper">
                                    <Link v-if="canAccessDungeonParty" :href="route('dungeon-party')" class="nav-link" :class="{ active: isActive('dungeon-party') }">
                                        {{ $t('nav.partyFinder') }}
                                    </Link>
                                    <Link v-if="canAccessPortfolio" :href="route('portfolio')" class="nav-link" :class="{ active: isActive('portfolio') }">
                                        {{ $t('nav.portfolio') }}
                                    </Link>
                                    <Link v-if="canAccessBinSniper" :href="route('bin-sniper')" class="nav-link" :class="{ active: isActive('bin-sniper') }">
                                        {{ $t('nav.binSniper') }}
                                    </Link>
                                </template>
                            </div>

                        <!-- Right: Auth -->
                        <div class="relative z-10 ms-auto hidden items-center gap-4 md:flex">
                            <!-- GitHub Star -->
                            <a
                                href="https://github.com/Lokkisanek/SkyblockHub.play"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 text-xs font-medium text-white/50 transition hover:text-white"
                            >
                                {{ $t('nav.starOnGithub') }}
                                <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                </svg>
                            </a>

                            <!-- Auth: Login/Register or Profile -->
                            <template v-if="user">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button class="flex items-center gap-2 text-xs font-medium text-white/70 transition hover:text-white">
                                            <div
                                                v-if="mcAvatarUrl"
                                                class="h-6 w-6 overflow-hidden rounded border border-white/10 bg-surface-800"
                                                aria-hidden="true"
                                            >
                                                <img :src="mcAvatarUrl" :alt="displayName" class="h-6 w-6" loading="lazy" decoding="async" />
                                            </div>
                                            <div
                                                v-else
                                                class="flex h-6 w-6 items-center justify-center rounded bg-gradient-to-br from-purple-400 to-indigo-500 text-[10px] font-bold uppercase text-white"
                                                aria-hidden="true"
                                            >
                                                {{ displayName.charAt(0) }}
                                            </div>
                                            {{ displayName }}
                                        </button>
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')">{{ $t('nav.settings') }}</DropdownLink>
                                        <button
                                            class="block w-full px-4 py-2 text-start text-xs leading-5 text-neutral hover:bg-surface-500 hover:text-white focus:bg-surface-500 focus:outline-none"
                                            @click="logout"
                                        >
                                            {{ $t('nav.logOut') }}
                                        </button>
                                    </template>
                                </Dropdown>
                            </template>
                            <template v-else>
                                <button
                                    @click="showAuthPanel = true"
                                    class="text-xs font-medium text-white transition hover:text-white/70"
                                >
                                    {{ $t('nav.login') }}
                                </button>
                            </template>
                        </div>

                        <!-- Hamburger (mobile) -->
                        <div class="relative z-10 ms-auto flex items-center md:hidden">
                            <button
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center rounded-lg p-2 text-neutral transition hover:bg-white/10 hover:text-white focus:outline-none"
                            >
                                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                    <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mobile Navigation Menu -->
                <div
                    :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }"
                    class="relative z-10 border-t border-white/10 md:hidden"
                >
                    <div class="space-y-1 px-4 pb-4 pt-3">
                        <Link :href="route('dashboard')" class="mobile-link" :class="{ 'mobile-link-active': isActive('dashboard') }">
                            {{ $t('nav.dashboard') }}
                        </Link>
                        <div class="px-2 pb-1 pt-3 text-[10px] font-semibold uppercase tracking-widest text-neutral">{{ $t('nav.bazaar') }}</div>
                        <Link :href="route('bazaar')" class="mobile-link ps-6" :class="{ 'mobile-link-active': isActive('bazaar') }">
                            {{ $t('nav.flips') }}
                        </Link>
                        <Link :href="route('npc-flips')" class="mobile-link ps-6" :class="{ 'mobile-link-active': isActive('npc-flips') }">
                            {{ $t('nav.npcFlips') }}
                        </Link>
                        <Link v-if="canAccessCrafting" :href="route('crafting')" class="mobile-link ps-6" :class="{ 'mobile-link-active': isActive('crafting') }">
                            {{ $t('nav.craftingFlips') }}
                        </Link>
                        <Link :href="route('profile-stats')" class="mobile-link" :class="{ 'mobile-link-active': isActive('profile-stats') }">
                            {{ $t('nav.profileStats') }}
                        </Link>
                        <div class="px-2 pb-1 pt-3 text-[10px] font-semibold uppercase tracking-widest text-neutral">{{ $t('nav.misc') }}</div>
                        <Link :href="route('mayors')" class="mobile-link ps-6" :class="{ 'mobile-link-active': isActive('mayors') }">
                            {{ $t('nav.mayors') }}
                        </Link>
                        <Link :href="route('event-timer')" class="mobile-link ps-6" :class="{ 'mobile-link-active': isActive('event-timer') }">
                            {{ $t('nav.eventTimer') }}
                        </Link>
                        <Link :href="route('guides')" class="mobile-link ps-6" :class="{ 'mobile-link-active': isActive('guides') || route().current('guides.show') }">
                            {{ $t('nav.guides') }}
                        </Link>
                        <Link :href="route('trust-index')" class="mobile-link ps-6" :class="{ 'mobile-link-active': isActive('trust-index') }">
                            {{ $t('nav.trustIndex') }}
                        </Link>
                        <Link :href="route('leaderboards')" class="mobile-link" :class="{ 'mobile-link-active': isActive('leaderboards') }">
                            {{ $t('nav.leaderboards') }}
                        </Link>
                        <Dropdown v-if="isTestingAdmin" align="left" width="80" class="block w-full">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="mobile-link flex w-full items-center justify-between gap-2 text-start"
                                    :class="{ 'mobile-link-active': isAdminSectionActive() }"
                                >
                                    <span>{{ $t('nav.admin') }}</span>
                                    <svg class="h-3 w-3 shrink-0 opacity-50" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </template>
                            <template #content>
                                <DropdownLink :href="route('admin.index')">{{ $t('nav.adminOperations') }}</DropdownLink>
                                <DropdownLink :href="route('admin.guides.submissions')">{{ $t('nav.adminGuideQueue') }}</DropdownLink>
                                <DropdownLink :href="route('admin.trust-index.submissions')">{{ $t('nav.adminTrustQueue') }}</DropdownLink>
                            </template>
                        </Dropdown>
                        <template v-if="canAccessDungeonParty || canAccessPortfolio || canAccessBinSniper">
                            <Link v-if="canAccessDungeonParty" :href="route('dungeon-party')" class="mobile-link" :class="{ 'mobile-link-active': isActive('dungeon-party') }">{{ $t('nav.partyFinder') }}</Link>
                            <Link v-if="canAccessPortfolio" :href="route('portfolio')" class="mobile-link" :class="{ 'mobile-link-active': isActive('portfolio') }">{{ $t('nav.portfolio') }}</Link>
                            <Link v-if="canAccessBinSniper" :href="route('bin-sniper')" class="mobile-link" :class="{ 'mobile-link-active': isActive('bin-sniper') }">{{ $t('nav.binSniper') }}</Link>
                        </template>

                        <!-- Mobile auth -->
                        <div class="mt-3 flex items-center gap-3 border-t border-white/10 pt-3">
                            <template v-if="user">
                                <div v-if="mcAvatarUrl" class="h-6 w-6 overflow-hidden rounded border border-white/10 bg-surface-800" aria-hidden="true">
                                    <img :src="mcAvatarUrl" :alt="displayName" class="h-6 w-6" loading="lazy" decoding="async" />
                                </div>
                                <span class="text-xs text-white">{{ displayName }}</span>
                                <button @click="logout" class="text-xs text-neutral hover:text-white">{{ $t('nav.logOut') }}</button>
                            </template>
                            <template v-else>
                                <button @click="showAuthPanel = true" class="text-xs font-medium text-purple-400 hover:text-white">{{ $t('nav.login') }}</button>
                            </template>
                        </div>
                    </div>
                </div>
            </nav>

            <OnboardingChecklist v-if="user && onboarding?.show" :onboarding="onboarding" />

            <!-- Page Content -->
            <main>
                <slot />
            </main>

            <!-- Auth Slide Panel -->
            <AuthSlidePanel
                :show="showAuthPanel"
                :notice="authNotice"
                @close="closeAuthPanel"
            />

            <!-- Cookie Consent -->
            <CookieConsent />

            <!-- Survey Popup -->
            <SurveyPopup />
        </div>
    </div>
</template>

<style scoped>
/* ── Nav wrapper ── */
.nav-wrapper {
    position: relative;
    z-index: 50;
    background: linear-gradient(180deg, rgba(16, 16, 16, 0.95) 0%, rgba(16, 16, 16, 0.88) 100%);
    backdrop-filter: blur(16px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

/* ── Slime glow (square shape) ── */
.slime-glow-container {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    overflow: hidden;
    pointer-events: none;
    z-index: 1;
}

.slime-glow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 120px;
    height: 50px;
    border-radius: 4px;
    background: rgba(93, 211, 93, 0.08);
    filter: blur(35px);
    pointer-events: none;
    animation: slimeGlowDrift 35s ease-in-out infinite, slimeGlowBounce 4s ease-in-out infinite;
}

.slime-glow::after {
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
    animation: slimeGlowPulse 6s ease-in-out infinite;
}

@keyframes slimeGlowDrift {
    0%, 100% { left: 8%; }
    33%      { left: 45%; }
    66%      { left: 75%; }
}

@keyframes slimeGlowBounce {
    0%, 100% { transform: translateY(4px); }
    50%      { transform: translateY(-6px); }
}

@keyframes slimeGlowPulse {
    0%, 100% { opacity: 0.5; }
    50%      { opacity: 1; }
}

/* ── Desktop nav links ── */
.nav-link {
    position: relative;
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 500;
    color: #aaa;
    border-radius: 6px;
    transition: color 0.2s, background 0.2s;
    text-decoration: none;
    white-space: nowrap;
}

.nav-link:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.06);
}

.nav-link.active {
    color: #fff;
}

/* ── Mobile nav links ── */
.mobile-link {
    display: block;
    padding: 8px 12px;
    font-size: 13px;
    font-weight: 500;
    color: #aaa;
    border-radius: 6px;
    transition: color 0.15s, background 0.15s;
}

.mobile-link:hover,
.mobile-link-active {
    color: #fff;
    background: rgba(255, 255, 255, 0.06);
}

</style>
