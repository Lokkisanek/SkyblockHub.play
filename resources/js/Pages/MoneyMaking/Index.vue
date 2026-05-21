<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ItemSlot from '@/Components/SkyBlock/ItemSlot.vue';
import { useI18n } from '@/strings/useI18n';
import { preloadAllTextures, setEnabledPacks } from '@/utils/textures';
import { loadEnabledPacks } from '@/utils/profileViewerSettings';

const { t } = useI18n();

const props = defineProps({
    minecraftUsername: { type: String, default: null },
});

const username = ref(props.minecraftUsername || '');
const loading = ref(false);
const error = ref('');
const result = ref(null);

let activeController = null;
let fetchSeq = 0;

const coinFmt = new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 });

const petTierColors = {
    COMMON: '#AAAAAA',
    UNCOMMON: '#55FF55',
    RARE: '#5555FF',
    EPIC: '#AA00AA',
    LEGENDARY: '#FFAA00',
    MYTHIC: '#FF55FF',
};

function formatCoins(n) {
    const v = Number(n);
    if (!Number.isFinite(v)) return '0';
    return coinFmt.format(v);
}

function reqStatChipStyle(label) {
    if (String(label).startsWith('Mayor')) {
        return { '--stat-color': '#FFAA00' };
    }
    return { '--stat-color': '#AAAAAA' };
}

function petLevelStyle(tier) {
    return { color: petTierColors[tier] || '#AAAAAA' };
}

const showHeroLoading = computed(() => loading.value && !result.value);

async function runAnalysis() {
    const name = username.value.trim();
    if (!name) {
        error.value = t('moneyMaking.errorEmptyUsername');
        return;
    }

    if (activeController) {
        activeController.abort();
    }
    const seq = ++fetchSeq;
    const controller = new AbortController();
    activeController = controller;

    loading.value = true;
    error.value = '';
    result.value = null;

    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('username', name);
        window.history.replaceState({}, '', url);
    }

    try {
        const res = await fetch(`/api/v1/money-making/${encodeURIComponent(name)}`, {
            signal: controller.signal,
        });
        const json = await res.json();

        if (seq !== fetchSeq) return;

        if (!res.ok) {
            error.value = json.error || t('moneyMaking.errorGeneric');
            return;
        }

        result.value = json;
    } catch (e) {
        if (seq !== fetchSeq) return;
        if (e?.name === 'AbortError') {
            error.value = t('moneyMaking.errorAborted');
        } else {
            error.value = t('moneyMaking.errorNetwork');
        }
    } finally {
        if (seq === fetchSeq) {
            loading.value = false;
            activeController = null;
        }
    }
}

watch(
    () => props.minecraftUsername,
    (v) => {
        if (v && typeof v === 'string') {
            username.value = v;
        }
    },
);

onMounted(async () => {
    await setEnabledPacks(loadEnabledPacks());
    preloadAllTextures();
    if (props.minecraftUsername) {
        runAnalysis();
    }
});
</script>

<template>
    <AuthenticatedLayout>
        <div class="money-page relative z-20 py-4 pb-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <header class="mb-6 text-left sm:mb-8">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-profit/90">
                        {{ t('moneyMaking.kicker') }}
                    </p>
                    <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                        {{ t('moneyMaking.heading') }}
                    </h1>
                </header>

                <div class="z-10 mb-3 flex w-full justify-center">
                    <div
                        class="w-full max-w-2xl rounded-2xl border border-border/80 bg-surface-900/75 p-3 shadow-[0_16px_40px_rgba(0,0,0,0.35)] backdrop-blur-sm"
                    >
                        <form class="flex flex-col gap-2 sm:flex-row sm:items-center" @submit.prevent="runAnalysis">
                            <label class="sr-only" for="mm-username">{{ t('moneyMaking.usernameLabel') }}</label>
                            <div class="relative flex-1">
                                <svg
                                    class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M8.5 3a5.5 5.5 0 104.35 8.87l2.64 2.64a1 1 0 001.42-1.42l-2.64-2.64A5.5 5.5 0 008.5 3zm-3.5 5.5a3.5 3.5 0 117 0 3.5 3.5 0 01-7 0z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <input
                                    id="mm-username"
                                    v-model="username"
                                    type="text"
                                    autocomplete="username"
                                    maxlength="16"
                                    :placeholder="t('moneyMaking.usernamePlaceholder')"
                                    :disabled="loading"
                                    class="w-full rounded-xl border border-border/80 bg-surface-800/80 py-3 pl-11 pr-4 text-sm text-white placeholder:text-neutral/80 transition focus:border-profit/70 focus:outline-none focus:ring-2 focus:ring-profit/25"
                                    @keyup.enter="runAnalysis"
                                />
                            </div>
                            <button
                                type="submit"
                                :disabled="loading"
                                class="inline-flex h-[46px] items-center justify-center rounded-xl border border-profit/35 bg-profit/20 px-6 text-sm font-semibold text-profit transition hover:bg-profit/30 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ loading ? t('moneyMaking.loading') : t('moneyMaking.analyze') }}
                            </button>
                        </form>
                    </div>
                </div>

                <div
                    v-if="showHeroLoading"
                    class="mx-auto mb-4 flex w-full max-w-2xl items-center justify-center gap-3 text-neutral"
                    role="status"
                    aria-live="polite"
                >
                    <span class="h-5 w-5 shrink-0 animate-spin rounded-full border-2 border-neutral/40 border-t-profit" />
                    <span class="text-sm text-neutral">{{ t('moneyMaking.heroLoadingHint') }}</span>
                </div>

                <div
                    v-if="error"
                    role="alert"
                    class="mx-auto mb-6 w-full max-w-2xl rounded-lg border border-loss/50 bg-loss/10 px-4 py-3 text-center text-sm text-loss"
                >
                    {{ error }}
                </div>

                <template v-if="result?.profile">
                    <section
                        class="mb-6 flex items-center justify-center gap-3 rounded-2xl border border-border/80 bg-surface-900/75 px-4 py-4 sm:px-6"
                    >
                        <div
                            class="h-12 w-12 shrink-0 overflow-hidden rounded-lg border border-border/80 bg-surface-800"
                        >
                            <img
                                v-if="result.profile.username"
                                :src="`https://mc-heads.net/avatar/${encodeURIComponent(result.profile.username)}/48`"
                                :alt="result.profile.username"
                                class="h-12 w-12"
                                loading="lazy"
                            />
                        </div>
                        <div class="text-center sm:text-left">
                            <h2 class="text-lg font-bold text-white">{{ result.profile.username }}</h2>
                            <p v-if="result.cute_name" class="text-xs text-neutral">{{ result.cute_name }}</p>
                            <p v-if="result.profile.items_scanned" class="mt-0.5 text-[11px] text-neutral/80">
                                {{ t('moneyMaking.itemsScanned') }}: {{ result.profile.items_scanned }}
                            </p>
                        </div>
                    </section>

                    <section v-if="result?.methods?.length">
                        <h2 class="stat-header mb-4">{{ t('moneyMaking.resultsHeading') }}</h2>

                        <ol class="space-y-6">
                            <li
                                v-for="(m, idx) in result.methods"
                                :key="m.id"
                                class="mm-method-card rounded-2xl border border-border/80 bg-surface-900/75 p-4 sm:p-6"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="mb-2 flex flex-wrap items-center gap-2">
                                            <span
                                                class="rounded border border-profit/30 bg-profit/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-profit"
                                            >
                                                #{{ idx + 1 }}
                                            </span>
                                            <h3 class="text-base font-semibold text-white sm:text-lg">{{ m.name }}</h3>
                                            <span
                                                v-if="m.mayor_multiplier > 1.001"
                                                class="rounded border border-amber-500/35 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-200"
                                            >
                                                {{ t('moneyMaking.mayorApplied') }}
                                                +{{ Math.round((m.mayor_multiplier - 1) * 1000) / 10 }}%
                                            </span>
                                        </div>
                                        <p class="mb-3 text-2xl font-bold tabular-nums text-profit sm:text-3xl">
                                            {{ formatCoins(m.coins_per_hour) }}
                                            <span class="text-sm font-medium text-neutral">{{
                                                t('moneyMaking.perHour')
                                            }}</span>
                                        </p>
                                        <p class="mb-4 text-sm leading-relaxed text-neutral">{{ m.summary }}</p>

                                        <div v-if="m.mayor_boosts?.length" class="mb-3 text-xs text-amber-100/90">
                                            <span
                                                v-for="(mb, bi) in m.mayor_boosts"
                                                :key="bi"
                                                class="mr-2 inline-block rounded border border-amber-500/25 bg-amber-500/10 px-2 py-0.5"
                                            >
                                                {{ mb.label }}: {{ mb.detail }}
                                            </span>
                                        </div>

                                        <template v-if="m.loadout">
                                            <div v-if="m.loadout.play_tip" class="mb-4">
                                                <h4
                                                    class="mb-1 text-[11px] font-semibold uppercase tracking-wider text-profit/90"
                                                >
                                                    {{ t('moneyMaking.whatToDo') }}
                                                </h4>
                                                <p class="text-sm leading-relaxed text-white/85">
                                                    {{ m.loadout.play_tip }}
                                                </p>
                                            </div>

                                            <div
                                                v-if="m.loadout.sections?.length"
                                                class="mb-4 border-t border-border/60 pt-4"
                                            >
                                                <h4
                                                    class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-neutral"
                                                >
                                                    {{ t('moneyMaking.recommendedGear') }}
                                                </h4>
                                                <div class="mm-loadout-row">
                                                    <template
                                                        v-for="(section, si) in m.loadout.sections"
                                                        :key="m.id + '-sec-' + si"
                                                    >
                                                        <div
                                                            v-if="section.layout === 'gear'"
                                                            class="mm-loadout-block mm-gear-block"
                                                        >
                                                            <p class="stat-header mb-2">{{ section.title }}</p>
                                                            <div class="mm-gear-columns">
                                                                <div
                                                                    v-if="section.armor_items?.length"
                                                                    class="wardrobe-set"
                                                                >
                                                                    <ItemSlot
                                                                        v-for="(piece, pi) in section.armor_items"
                                                                        :key="'gear-armor-' + pi"
                                                                        :item="piece"
                                                                        class="piece"
                                                                    />
                                                                </div>
                                                                <div
                                                                    v-if="section.equipment_items?.length"
                                                                    class="wardrobe-set"
                                                                >
                                                                    <ItemSlot
                                                                        v-for="(item, ei) in section.equipment_items"
                                                                        :key="'gear-eq-' + ei"
                                                                        :item="item"
                                                                        class="piece"
                                                                    />
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div
                                                            v-else-if="section.layout === 'armor_stack' && section.items?.length"
                                                            class="mm-loadout-block"
                                                        >
                                                            <p class="stat-header mb-2">{{ section.title }}</p>
                                                            <div class="wardrobe-set">
                                                                <ItemSlot
                                                                    v-for="(piece, pi) in section.items"
                                                                    :key="'armor-' + pi"
                                                                    :item="piece"
                                                                    class="piece"
                                                                />
                                                            </div>
                                                        </div>

                                                        <div
                                                            v-else-if="section.layout === 'items_row' && section.items?.length"
                                                            class="mm-loadout-block"
                                                        >
                                                            <p class="stat-header mb-2">{{ section.title }}</p>
                                                            <div class="pieces">
                                                                <ItemSlot
                                                                    v-for="(tool, ti) in section.items"
                                                                    :key="'tool-' + ti"
                                                                    :item="tool"
                                                                    class="piece"
                                                                />
                                                            </div>
                                                        </div>

                                                        <div
                                                            v-else-if="section.layout === 'pet' && section.item"
                                                            class="mm-loadout-block"
                                                        >
                                                            <p class="stat-header mb-2">{{ section.title }}</p>
                                                            <div class="pets-grid-item">
                                                                <ItemSlot :item="section.item" class="piece" />
                                                                <span
                                                                    v-if="section.subtitle"
                                                                    class="pets-level-label"
                                                                    :style="petLevelStyle(section.tier)"
                                                                >
                                                                    {{ section.subtitle }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>

                                            <ul
                                                v-if="m.loadout.notes?.length"
                                                class="mb-4 list-disc space-y-1 pl-5 text-xs text-amber-100/90"
                                            >
                                                <li v-for="(note, ni) in m.loadout.notes" :key="'note-' + ni">
                                                    <span class="font-semibold text-amber-200/80"
                                                        >{{ t('moneyMaking.loadoutNote') }}:</span
                                                    >
                                                    {{ note }}
                                                </li>
                                            </ul>
                                        </template>

                                        <h4 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-neutral">
                                            {{ t('moneyMaking.methodFactors') }}
                                        </h4>
                                        <div v-if="m.required_stats?.length" class="stats-grid ps-stats-grid">
                                            <span
                                                v-for="(rs, ri) in m.required_stats"
                                                :key="ri"
                                                class="stat-chip"
                                                :style="reqStatChipStyle(rs.label)"
                                            >
                                                <span class="stat-name">{{ rs.label }}</span>
                                                <span class="stat-value">{{ rs.value }}</span>
                                            </span>
                                        </div>
                                    </div>

                                    <div
                                        v-if="m.youtube"
                                        class="w-full shrink-0 rounded-xl border border-border/80 bg-surface-800/60 p-3 sm:max-w-xs lg:w-72"
                                    >
                                        <a
                                            :href="m.youtube.watch_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="group block"
                                        >
                                            <div class="relative aspect-video overflow-hidden rounded-lg bg-black/50">
                                                <img
                                                    :src="m.youtube.thumbnail_url"
                                                    :alt="m.youtube.title"
                                                    class="h-full w-full object-cover opacity-90 transition group-hover:opacity-100"
                                                    loading="lazy"
                                                />
                                                <span
                                                    class="absolute inset-0 flex items-center justify-center bg-black/30 transition group-hover:bg-black/20"
                                                >
                                                    <span
                                                        class="flex h-12 w-12 items-center justify-center rounded-full bg-red-600/95 text-white shadow-lg"
                                                    >
                                                        <svg
                                                            class="ms-0.5 h-5 w-5"
                                                            viewBox="0 0 24 24"
                                                            fill="currentColor"
                                                            aria-hidden="true"
                                                        >
                                                            <path d="M8 5v14l11-7z" />
                                                        </svg>
                                                    </span>
                                                </span>
                                            </div>
                                            <p class="mt-2 line-clamp-2 text-xs font-medium leading-snug text-white/85">
                                                {{ m.youtube.title }}
                                            </p>
                                        </a>
                                        <a
                                            :href="m.youtube.watch_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="mt-3 flex w-full items-center justify-center rounded-lg border border-border/80 bg-surface-700/50 py-2 text-xs font-semibold text-white transition hover:bg-surface-600/60"
                                        >
                                            {{ t('moneyMaking.watchTutorial') }}
                                        </a>
                                    </div>
                                </div>
                            </li>
                        </ol>
                    </section>

                    <p
                        v-if="result?.bazaar_cache_ttl_seconds"
                        class="mt-8 text-center text-[11px] text-neutral/80"
                    >
                        {{ t('moneyMaking.bazaarCacheNote', { seconds: result.bazaar_cache_ttl_seconds }) }}
                    </p>

                    <p class="mt-6 rounded-lg border border-border/60 bg-surface-800/40 px-4 py-3 text-xs text-neutral">
                        <span class="font-semibold text-white/70">{{ t('moneyMaking.futureIdeasTitle') }}:</span>
                        {{ t('moneyMaking.futureIdeas') }}
                    </p>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.money-page {
    min-height: calc(100vh - 3.5rem);
}

.mm-gear-columns {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 1.25rem;
}

.mm-method-card {
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
}

.mm-loadout-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 1.25rem 1.75rem;
}

.mm-loadout-block {
    flex-shrink: 0;
}
</style>
