<script setup>
import { ref, computed, onMounted, provide } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { preloadAllTextures, setEnabledPacks, getSkinUrl, getHeadUrl, getRarityColor, RARITY_COLORS, SKILL_ICONS, SLAYER_ICONS, CLASS_ICONS } from '@/utils/textures';
import ItemSlot from '@/Components/SkyBlock/ItemSlot.vue';
import InventoryGrid from '@/Components/SkyBlock/InventoryGrid.vue';
import PackSelector from '@/Components/SkyBlock/PackSelector.vue';

const props = defineProps({
    minecraftUsername: { type: String, default: null },
});

const username = ref(props.minecraftUsername || '');
const profileData = ref(null);
const loading = ref(false);
const error = ref('');
const selectedProfile = ref(null);
const activeTab = ref('gear');

/* ── Tab definitions (matching SkyCrypt) ─────────────────── */
const tabs = [
    { id: 'gear',        name: 'Gear' },
    { id: 'inventory',   name: 'Inventory' },
    { id: 'pets',        name: 'Pets' },
    { id: 'skills',      name: 'Skills' },
    { id: 'dungeons',    name: 'Dungeons' },
    { id: 'slayer',      name: 'Slayer' },
    { id: 'collections', name: 'Collections' },
    { id: 'misc',        name: 'Misc' },
];

/* ── Inventory sub-tabs with SkyCrypt-style icons ────────── */
const inventorySubTabs = computed(() => {
    const headIcon = profileData.value?.uuid
        ? `https://mc-heads.net/avatar/${profileData.value.uuid}/24`
        : null;
    return [
        { id: 'inv',              name: 'Inventory',        icon: headIcon },
        { id: 'backpack',         name: 'Backpack',         icon: '/img/textures/chest.png' },
        { id: 'enderchest',       name: 'Enderchest',       icon: '/img/textures/ender_chest.png' },
        { id: 'personal_vault',   name: 'Personal Vault',   icon: '/img/textures/chest.png' },
        { id: 'talisman_bag',     name: 'Talisman Bag',     icon: '/img/textures/ender_eye.png' },
        { id: 'potion_bag',       name: 'Potion Bag',       icon: '/img/textures/potion_bottle_drinkable.png' },
        { id: 'fishing_bag',      name: 'Fishing Bag',      icon: '/img/textures/fishing_rod_uncast.png' },
        { id: 'quiver',           name: 'Quiver',           icon: '/img/textures/arrow.png' },
        { id: 'museum',           name: 'Museum',           icon: '/img/textures/gold_ingot.png' },
        { id: 'rift_inventory',   name: 'Rift Inventory',   icon: '/img/textures/ender_pearl.png' },
        { id: 'rift_enderchest',  name: 'Rift Enderchest',  icon: '/img/textures/ender_chest.png' },
    ];
});

const activeInventorySubTab = ref('inv');
const expandedBackpack = ref(null);   // index of opened backpack (null = all collapsed)
const expandedEnderPage = ref(null);  // index of opened enderchest page

/* ── Texture pack version key ──────────────────────────────── */
const textureVersion = ref(0);
provide('textureVersion', textureVersion);

async function onPacksChanged(packIds) {
    await setEnabledPacks(packIds);
    textureVersion.value++;
}

const petTierColors = {
    COMMON:    '#AAAAAA',
    UNCOMMON:  '#55FF55',
    RARE:      '#5555FF',
    EPIC:      '#AA00AA',
    LEGENDARY: '#FFAA00',
    MYTHIC:    '#FF55FF',
};

/* ── API fetch ───────────────────────────────────────────── */
async function fetchProfile() {
    const name = username.value.trim();
    if (!name) return;

    loading.value = true;
    error.value = '';
    profileData.value = null;
    selectedProfile.value = null;

    try {
        const res = await fetch(`/api/skycrypt/${encodeURIComponent(name)}`);
        const json = await res.json();

        if (!res.ok) {
            error.value = json.error || 'Failed to fetch profile.';
            return;
        }

        profileData.value = json.data;

        const profiles = json.data?.profiles ?? {};
        const keys = Object.keys(profiles);
        const sel = keys.find(k => profiles[k].selected) || keys[0];
        if (sel) selectedProfile.value = sel;
    } catch (e) {
        error.value = 'Network error. Try again.';
    } finally {
        loading.value = false;
    }
}

/* ── Computed ─────────────────────────────────────────────── */
const currentProfile = computed(() => {
    if (!profileData.value || !selectedProfile.value) return null;
    return profileData.value.profiles?.[selectedProfile.value];
});

const currentData = computed(() => currentProfile.value?.data ?? null);

const skinUrl = computed(() => getSkinUrl(profileData.value?.uuid));
const headUrl = computed(() => getHeadUrl(profileData.value?.uuid, 64));

// ── Skills ──────────────────────────────────────────────
const mainSkillNames = ['farming', 'mining', 'combat', 'foraging', 'fishing', 'enchanting'];
const secondarySkillNames = ['alchemy', 'carpentry', 'taming', 'runecrafting', 'social'];

const allSkills = computed(() => {
    if (!currentData.value?.skills) return [];
    return [...mainSkillNames, ...secondarySkillNames]
        .filter(n => currentData.value.skills[n])
        .map(n => ({ name: n, ...currentData.value.skills[n] }));
});

const leftSkills = computed(() => allSkills.value.filter((_, i) => i % 2 === 0));
const rightSkills = computed(() => allSkills.value.filter((_, i) => i % 2 === 1));

// ── Gear: Armor stats sum ───────────────────────────────
const armorStats = computed(() => {
    const armor = currentData.value?.armor ?? [];
    const totals = {};
    for (const item of armor) {
        if (!item?.stats) continue;
        for (const [key, stat] of Object.entries(item.stats)) {
            if (!totals[key]) totals[key] = { value: 0, percent: stat.percent };
            totals[key].value += stat.value;
        }
    }
    return totals;
});

const equipmentStats = computed(() => {
    const equip = currentData.value?.equipment ?? [];
    const totals = {};
    for (const item of equip) {
        if (!item?.stats) continue;
        for (const [key, stat] of Object.entries(item.stats)) {
            if (!totals[key]) totals[key] = { value: 0, percent: stat.percent };
            totals[key].value += stat.value;
        }
    }
    return totals;
});

// ── Accessories ─────────────────────────────────────────
const accessoryStats = computed(() => {
    const items = currentData.value?.accessories ?? [];
    const unique = new Set();
    let recombed = 0;
    for (const item of items) {
        if (item?.skyblock_id) unique.add(item.skyblock_id);
        if (item?.recombobulated) recombed++;
    }
    return {
        total: items.length,
        unique: unique.size,
        recombobulated: recombed,
    };
});

const accessoryBag = computed(() => currentData.value?.accessory_bag_storage ?? {});

// ── Active weapon ───────────────────────────────────────
const activeWeapon = computed(() => {
    const weapons = currentData.value?.weapons ?? [];
    return weapons.length > 0 ? weapons[0] : null;
});

// ── Inventory sub-tab data ──────────────────────────────
const inventorySubTabData = computed(() => {
    if (!currentData.value) return [];
    const tab = activeInventorySubTab.value;
    switch (tab) {
        case 'inv':              return currentData.value.inventory ?? [];
        case 'enderchest':       return [];  // handled separately with pages
        case 'personal_vault':   return currentData.value.personal_vault ?? [];
        case 'talisman_bag':     return currentData.value.talisman_bag ?? [];
        case 'potion_bag':       return currentData.value.potion_bag ?? [];
        case 'fishing_bag':      return currentData.value.fishing_bag ?? [];
        case 'quiver':           return currentData.value.quiver ?? [];
        case 'backpack':         return [];  // handled separately
        case 'museum':           return [];  // handled separately
        case 'rift_inventory':   return currentData.value.rift_inventory ?? [];
        case 'rift_enderchest':  return currentData.value.rift_enderchest ?? [];
        default:                 return [];
    }
});

const backpackStorage = computed(() => currentData.value?.storage ?? []);
const enderchestPages = computed(() => {
    const raw = currentData.value?.enderchest;
    if (!raw || !Array.isArray(raw)) return [];
    // Defensive: if the API returned a flat array of items instead of pages, skip
    if (raw.length > 0 && !('items' in raw[0])) return [];
    return raw;
});
const museumData = computed(() => currentData.value?.museum ?? {});

/* ── Formatting helpers ──────────────────────────────────── */
function fNum(num, decimals = 2) {
    if (num === null || num === undefined) return '—';
    const abs = Math.abs(num);
    if (abs >= 1e9) return (num / 1e9).toFixed(decimals) + 'B';
    if (abs >= 1e6) return (num / 1e6).toFixed(decimals) + 'M';
    if (abs >= 1e3) return (num / 1e3).toFixed(decimals) + 'K';
    if (Number.isInteger(num)) return num.toLocaleString();
    return num.toFixed(decimals);
}

function formatXP(skill) {
    if (skill.level >= skill.maxLevel) return fNum(skill.xp) + ' XP';
    return fNum(skill.xpCurrent) + ' / ' + fNum(skill.xpForNext) + ' XP';
}

function timeAgo(ts) {
    if (!ts) return '—';
    const ms = Date.now() - ts;
    const years = Math.floor(ms / (365.25 * 24 * 60 * 60 * 1000));
    if (years >= 1) return `${years} year${years > 1 ? 's' : ''} ago`;
    const months = Math.floor(ms / (30.44 * 24 * 60 * 60 * 1000));
    if (months >= 1) return `${months} month${months > 1 ? 's' : ''} ago`;
    const days = Math.floor(ms / (24 * 60 * 60 * 1000));
    if (days >= 1) return `${days} day${days > 1 ? 's' : ''} ago`;
    const hours = Math.floor(ms / (60 * 60 * 1000));
    return `${hours} hour${hours > 1 ? 's' : ''} ago`;
}

function capitalize(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
function petName(t) { return t.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, c => c.toUpperCase()); }

function formatStat(key, stat) {
    const v = stat.value % 1 === 0 ? stat.value : stat.value.toFixed(1);
    return `${v}${stat.percent ? '%' : ''} ${key}`;
}

const statColors = {
    CC: '#FF5555', CD: '#FF5555', Str: '#FF5555', Dmg: '#FF5555',
    HP: '#FF5555', HPR: '#55FF55', Def: '#55FF55', TD: '#FFFFFF',
    Int: '#55FFFF', Spd: '#FFFFFF', AS: '#FFFF55', FS: '#FF5555',
    MF: '#55FFFF', SCC: '#55FFFF', PL: '#FF55FF',
};

function getStatColor(key) { return statColors[key] ?? '#AAAAAA'; }

onMounted(async () => {
    preloadAllTextures();
    if (username.value) fetchProfile();
});
</script>

<template>
    <Head title="Profile Stats" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Profile Stats</h2>
        </template>

        <div class="py-4">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <!-- ═══ SEARCH BAR ═══ -->
                <div class="mb-6 flex items-center gap-2">
                    <input v-model="username" type="text" placeholder="Search player…"
                        class="bg-surface-800 border border-border rounded px-4 py-2 text-sm text-white placeholder-neutral focus:outline-none focus:border-profit w-72"
                        @keyup.enter="fetchProfile" />
                    <button @click="fetchProfile" :disabled="loading"
                        class="px-4 py-2 text-sm font-medium bg-profit/20 border border-profit/30 text-profit hover:bg-profit/30 rounded disabled:opacity-50 transition">
                        {{ loading ? 'Loading…' : 'Search' }}
                    </button>
                </div>

                <!-- Error -->
                <div v-if="error" class="mb-4 border border-loss/50 bg-loss/10 text-loss text-sm px-4 py-3 rounded">
                    {{ error }}
                </div>

                <!-- Loading spinner -->
                <div v-if="loading" class="flex items-center gap-3 text-neutral">
                    <div class="animate-spin h-5 w-5 border-2 border-neutral border-t-profit rounded-full"></div>
                    <span class="text-sm">Fetching profile data…</span>
                </div>

                <!-- ════════════════════════════════════════════════════════ -->
                <!--  PROFILE CONTENT                                        -->
                <!-- ════════════════════════════════════════════════════════ -->
                <div v-if="profileData && !loading && currentProfile">

                    <!-- ═══ STATS SUMMARY BAR ═══ -->
                    <div v-if="currentData" class="border border-profit/30 bg-profit/5 rounded px-4 py-2 mb-4">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                            <span class="text-neutral">Joined: <b class="text-white">{{ timeAgo(currentData.first_join) }}</b></span>
                            <span class="text-border-light">·</span>
                            <span class="text-neutral">Purse: <b class="text-rarity-legendary">{{ fNum(currentData.networth?.purse) }} Coins</b></span>
                            <span class="text-border-light">·</span>
                            <span class="text-neutral">Bank: <b class="text-rarity-legendary">{{ fNum(currentData.networth?.bank) }} Coins</b></span>
                            <span class="text-border-light">·</span>
                            <span class="text-neutral">Skill Avg: <b class="text-white">{{ currentData.average_skill_level }}</b></span>
                            <span class="text-border-light">·</span>
                            <span class="text-neutral">Fairy Souls: <b class="text-white">{{ currentData.fairy_souls ?? '—' }} / 267</b></span>
                            <span class="text-border-light">·</span>
                            <span class="text-neutral">Networth: <b class="text-rarity-legendary">{{ fNum(currentData.networth?.networth) }}</b></span>
                        </div>
                    </div>

                    <!-- ═══ PROFILE SELECTOR + HEADER ═══ -->
                    <div class="mb-3 flex items-center gap-4 flex-wrap">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <button v-for="(profile, key) in profileData.profiles" :key="key"
                                @click="selectedProfile = key"
                                class="px-3 py-1 text-xs rounded border transition"
                                :class="selectedProfile === key
                                    ? 'border-profit text-profit bg-profit/10'
                                    : 'border-border text-neutral hover:text-white hover:border-border-light'">
                                {{ profile.cute_name || key }}
                                <span v-if="profile.selected" class="ml-1 text-[10px] text-profit">●</span>
                            </button>
                        </div>
                        <div class="flex items-center gap-1.5 ml-auto">
                            <PackSelector @update:packs="onPacksChanged" />
                            <a :href="`https://sky.shiiyu.moe/stats/${profileData.username}/${currentProfile.cute_name}`"
                               target="_blank"
                               class="px-2.5 py-1 text-[11px] rounded border border-border text-neutral hover:text-white transition">
                                SkyCrypt ↗
                            </a>
                        </div>
                    </div>

                    <!-- ═══ MAIN TAB NAVIGATION (SkyCrypt-style underline) ═══ -->
                    <div class="flex border-b border-border mb-6 overflow-x-auto">
                        <button v-for="tab in tabs" :key="tab.id"
                            @click="activeTab = tab.id"
                            class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider whitespace-nowrap border-b-2 transition"
                            :class="activeTab === tab.id
                                ? 'border-profit text-profit'
                                : 'border-transparent text-neutral hover:text-white'">
                            {{ tab.name }}
                        </button>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  GEAR TAB (SkyCrypt-style)                         -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'gear'">
                        <div class="flex gap-8">
                            <!-- Left: Player Skin (fixed position like SkyCrypt) -->
                            <div class="hidden lg:block w-44 shrink-0">
                                <div class="sticky top-20">
                                    <img :src="skinUrl" alt="Player Skin" class="w-full h-auto drop-shadow-xl" loading="lazy" />
                                </div>
                            </div>

                            <!-- Right: Gear sections -->
                            <div class="flex-1 min-w-0 space-y-10">

                                <!-- ARMOR -->
                                <section v-if="currentData?.armor?.length">
                                    <h3 class="stat-header">Armor</h3>
                                    <div v-if="currentData.armor.some(a => a)" class="pieces">
                                        <ItemSlot v-for="(item, i) in [...currentData.armor].reverse()" :key="'a'+i" :item="item" />
                                    </div>
                                    <div v-if="Object.keys(armorStats).length" class="mt-3 text-xs font-semibold">
                                        <span class="text-neutral">Bonus: </span>
                                        <template v-for="(stat, key, idx) in armorStats" :key="key">
                                            <span v-if="idx > 0" class="text-neutral opacity-50"> // </span>
                                            <span :style="{ color: getStatColor(key) }">{{ formatStat(key, stat) }}</span>
                                        </template>
                                    </div>
                                </section>

                                <!-- EQUIPMENT -->
                                <section v-if="currentData?.equipment?.length">
                                    <h3 class="stat-header">Equipment</h3>
                                    <div class="pieces">
                                        <ItemSlot v-for="(item, i) in [...currentData.equipment].reverse()" :key="'e'+i" :item="item" />
                                    </div>
                                    <div v-if="Object.keys(equipmentStats).length" class="mt-3 text-xs font-semibold">
                                        <span class="text-neutral">Bonus: </span>
                                        <template v-for="(stat, key, idx) in equipmentStats" :key="key">
                                            <span v-if="idx > 0" class="text-neutral opacity-50"> // </span>
                                            <span :style="{ color: getStatColor(key) }">{{ formatStat(key, stat) }}</span>
                                        </template>
                                    </div>
                                </section>

                                <!-- WARDROBE -->
                                <section v-if="currentData?.wardrobe?.length">
                                    <h3 class="stat-header">Wardrobe</h3>
                                    <div class="wardrobe">
                                        <div v-for="(set, si) in currentData.wardrobe" :key="si"
                                             class="wardrobe-set"
                                             :class="{ 'ring-2 ring-profit/40 rounded': currentData.wardrobe_slot === si + 1 }">
                                            <template v-for="(item, ri) in set" :key="ri">
                                                <ItemSlot v-if="item" :item="item" />
                                                <div v-else class="armor-placeholder">
                                                    <div class="placeholder-icon"></div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </section>

                                <!-- WEAPONS -->
                                <section v-if="currentData?.weapons?.length">
                                    <h3 class="stat-header">Weapons</h3>
                                    <div v-if="activeWeapon" class="mb-3 text-sm font-semibold">
                                        <span class="text-neutral">Active Weapon: </span>
                                        <span :style="{ color: getRarityColor(activeWeapon.rarity) }">
                                            {{ activeWeapon.name }}
                                        </span>
                                    </div>
                                    <div class="pieces">
                                        <ItemSlot v-for="(item, i) in currentData.weapons" :key="'w'+i" :item="item" />
                                    </div>
                                </section>

                                <!-- No gear fallback -->
                                <div v-if="!currentData?.armor?.length && !currentData?.equipment?.length && !currentData?.weapons?.length"
                                     class="text-neutral text-sm py-8 text-center">
                                    No gear data available. The player's API settings may not expose inventory data.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  INVENTORY TAB (SkyCrypt-style with sub-tabs)      -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'inventory'">
                        <div class="inventory-container">
                            <!-- SkyCrypt-style inventory header tabs -->
                            <div class="inv-tabs">
                                <button v-for="subTab in inventorySubTabs" :key="subTab.id"
                                    @click="activeInventorySubTab = subTab.id; expandedBackpack = null; expandedEnderPage = null"
                                    class="inv-tab"
                                    :class="{ 'active-tab': activeInventorySubTab === subTab.id }">
                                    <img v-if="subTab.icon" :src="subTab.icon" class="inv-tab-icon" loading="lazy" />
                                    <span>{{ subTab.name }}</span>
                                </button>
                            </div>

                            <!-- BACKPACK / STORAGE sub-tab -->
                            <div v-if="activeInventorySubTab === 'backpack'">
                                <div v-if="backpackStorage.length > 0">
                                    <!-- Backpack icon grid (9-column grid of backpack icons, click to expand) -->
                                    <div v-if="expandedBackpack === null" class="inventory-grid">
                                        <div v-for="(bp, idx) in backpackStorage" :key="idx"
                                             class="backpack-slot"
                                             @click="expandedBackpack = idx">
                                            <ItemSlot :item="bp.icon" />
                                        </div>
                                    </div>

                                    <!-- Expanded: single backpack contents in MC 9-column grid -->
                                    <div v-else>
                                        <button class="flex items-center gap-2 mb-3 text-sm text-profit hover:text-white transition"
                                                @click="expandedBackpack = null">
                                            <span>←</span>
                                            <span class="font-bold">Back to Storage</span>
                                        </button>
                                        <div class="flex items-center gap-3 mb-3">
                                            <div class="w-8 h-8 flex-shrink-0">
                                                <ItemSlot :item="backpackStorage[expandedBackpack]?.icon" />
                                            </div>
                                            <span class="text-sm font-bold text-white">
                                                {{ backpackStorage[expandedBackpack]?.icon?.name || 'Backpack ' + (backpackStorage[expandedBackpack]?.slot + 1) }}
                                            </span>
                                            <span class="text-[11px] text-neutral ml-auto">{{ backpackStorage[expandedBackpack]?.count }} items</span>
                                        </div>
                                        <InventoryGrid :items="backpackStorage[expandedBackpack]?.items ?? []" />
                                    </div>
                                </div>
                                <div v-else class="text-neutral text-sm py-8 text-center">
                                    No backpack data available.
                                </div>
                            </div>

                            <!-- ENDERCHEST sub-tab (pages like backpacks) -->
                            <div v-else-if="activeInventorySubTab === 'enderchest'">
                                <div v-if="enderchestPages.length > 0">
                                    <!-- Ender chest page icon grid -->
                                    <div v-if="expandedEnderPage === null" class="inventory-grid">
                                        <div v-for="(page, idx) in enderchestPages" :key="idx"
                                             class="backpack-slot"
                                             @click="expandedEnderPage = idx">
                                            <div class="piece piece-bg-rare">
                                                <div class="piece-hover-overlay"></div>
                                                <img src="/img/textures/ender_chest.png"
                                                     class="piece-icon" loading="lazy" draggable="false" />
                                                <span v-if="page?.count > 0" class="piece-count">{{ page.count }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Expanded: single enderchest page contents -->
                                    <div v-else>
                                        <button class="flex items-center gap-2 mb-3 text-sm text-profit hover:text-white transition"
                                                @click="expandedEnderPage = null">
                                            <span>←</span>
                                            <span class="font-bold">Back to Ender Chest</span>
                                        </button>
                                        <div class="flex items-center gap-3 mb-3">
                                            <img src="/img/textures/ender_chest.png"
                                                 class="w-8 h-8" style="image-rendering: pixelated" />
                                            <span class="text-sm font-bold text-white">
                                                Ender Chest Page {{ expandedEnderPage + 1 }}
                                            </span>
                                            <span class="text-[11px] text-neutral ml-auto">{{ enderchestPages[expandedEnderPage]?.count ?? 0 }} items</span>
                                        </div>
                                        <InventoryGrid :items="enderchestPages[expandedEnderPage]?.items ?? []" />
                                    </div>
                                </div>
                                <div v-else class="text-neutral text-sm py-8 text-center">
                                    No ender chest data available.
                                </div>
                            </div>

                            <!-- MUSEUM sub-tab -->
                            <div v-else-if="activeInventorySubTab === 'museum'">
                                <div v-if="museumData.items?.length > 0 || museumData.special?.length > 0">
                                    <!-- Museum summary -->
                                    <div class="mb-4 space-y-1 text-sm">
                                        <div>
                                            <span class="text-neutral">Museum Value: </span>
                                            <span class="text-legendary font-bold">{{ fNum(museumData.value) }} Coins</span>
                                        </div>
                                        <div>
                                            <span class="text-neutral">Appraisal: </span>
                                            <span :class="museumData.appraisal ? 'text-profit' : 'text-loss'">
                                                {{ museumData.appraisal ? 'Unlocked' : 'Locked' }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-neutral">Items Donated: </span>
                                            <span class="text-white font-bold">{{ museumData.items?.length ?? 0 }}</span>
                                        </div>
                                        <div v-if="museumData.special?.length > 0">
                                            <span class="text-neutral">Special Items: </span>
                                            <span class="text-white font-bold">{{ museumData.special.length }}</span>
                                        </div>
                                    </div>

                                    <!-- Museum items grid -->
                                    <div v-if="museumData.items?.length > 0">
                                        <h4 class="text-white text-sm font-bold mb-2">Donated Items</h4>
                                        <div class="inventory-grid">
                                            <template v-for="(mItem, idx) in museumData.items" :key="'museum-'+idx">
                                                <template v-for="(item, jdx) in (mItem.data ?? [])" :key="'mi-'+idx+'-'+jdx">
                                                    <ItemSlot :item="item" />
                                                </template>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Special items grid -->
                                    <div v-if="museumData.special?.length > 0" class="mt-4">
                                        <h4 class="text-white text-sm font-bold mb-2">Special Items</h4>
                                        <div class="inventory-grid">
                                            <template v-for="(mItem, idx) in museumData.special" :key="'special-'+idx">
                                                <template v-for="(item, jdx) in (mItem.data ?? [])" :key="'si-'+idx+'-'+jdx">
                                                    <ItemSlot :item="item" />
                                                </template>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-neutral text-sm py-8 text-center">
                                    No museum data available.
                                </div>
                            </div>

                            <!-- Regular inventory sub-tabs (9-column grid) -->
                            <div v-else>
                                <div v-if="inventorySubTabData.length > 0">
                                    <InventoryGrid
                                        :items="inventorySubTabData"
                                        :show-hotbar="activeInventorySubTab === 'inv'" />
                                </div>
                                <div v-else class="text-neutral text-sm py-8 text-center">
                                    <template v-if="currentData?.inv_disabled">
                                        Inventory API is disabled for this player.
                                    </template>
                                    <template v-else>
                                        No data available for this inventory type.
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  ACCESSORIES TAB (SkyCrypt-style)                  -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'accessories'">
                        <div v-if="currentData?.accessories?.length">
                            <!-- Summary stats -->
                            <div class="mb-6 space-y-1 text-sm font-semibold">
                                <div>
                                    <span class="text-neutral">Unique Accessories: </span>
                                    <span class="text-white">{{ accessoryStats.unique }} / {{ accessoryStats.total }}</span>
                                </div>
                                <div>
                                    <span class="text-neutral">Recombobulated: </span>
                                    <span class="text-white">{{ accessoryStats.recombobulated }} / {{ accessoryStats.total }}</span>
                                </div>
                                <div v-if="accessoryBag.selected_power">
                                    <span class="text-neutral">Selected Power: </span>
                                    <span class="text-profit">{{ capitalize(accessoryBag.selected_power) }}</span>
                                </div>
                                <div v-if="accessoryBag.highest_magical_power">
                                    <span class="text-neutral">Magical Power: </span>
                                    <span class="text-rarity-mythic">{{ accessoryBag.highest_magical_power }}</span>
                                </div>
                            </div>

                            <!-- Active Accessories (SkyCrypt "pieces" style) -->
                            <h3 class="stat-header">Active Accessories</h3>
                            <div class="pieces">
                                <ItemSlot v-for="(item, i) in currentData.accessories" :key="i" :item="item" />
                            </div>
                        </div>
                        <div v-else class="text-neutral text-sm py-8 text-center">
                            No accessory data available.
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  PETS TAB                                          -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'pets'">
                        <div v-if="currentData?.pets?.length > 0"
                             class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                            <div v-for="(pet, i) in currentData.pets" :key="i"
                                 class="border bg-surface-800 rounded p-3 relative"
                                 :style="{ borderColor: petTierColors[pet.tier] || '#303030' }">
                                <div v-if="pet.active"
                                     class="absolute top-1 right-1 bg-profit/20 text-profit text-[10px] px-1.5 py-0.5 rounded">
                                    ACTIVE
                                </div>
                                <h4 class="text-xs font-bold text-center truncate"
                                    :style="{ color: petTierColors[pet.tier] }">
                                    {{ petName(pet.type) }}
                                </h4>
                                <div class="text-[10px] text-center uppercase mt-0.5"
                                     :style="{ color: petTierColors[pet.tier] }">
                                    {{ pet.tier }}
                                </div>
                                <div class="text-[10px] text-center text-neutral font-mono mt-1">
                                    {{ fNum(pet.xp) }} XP
                                </div>
                                <div v-if="pet.heldItem" class="text-[10px] text-center text-rarity-uncommon mt-1 truncate">
                                    {{ pet.heldItem.replace(/_/g, ' ') }}
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-neutral text-sm py-8 text-center">No pet data available.</div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  SKILLS TAB                                        -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'skills'">
                        <div class="flex gap-8">
                            <div class="hidden lg:block w-44 shrink-0">
                                <div class="sticky top-20">
                                    <img :src="skinUrl" alt="Player Skin" class="w-full h-auto drop-shadow-xl" loading="lazy" />
                                </div>
                            </div>

                            <div class="flex-1 min-w-0">
                                <!-- SkyBlock Level bar -->
                                <div v-if="currentData?.skyblock_level" class="mb-6">
                                    <div class="flex items-center gap-3">
                                        <span class="text-rarity-legendary font-bold text-lg flex items-center gap-1">
                                            ✫ Level {{ currentData.skyblock_level.level }}
                                        </span>
                                        <div class="flex-1 relative h-3 bg-surface-700 rounded-full overflow-hidden">
                                            <div class="absolute h-full bg-gradient-to-r from-cyan-500 to-cyan-300 rounded-full transition-all duration-500"
                                                 :style="{ width: (currentData.skyblock_level.progress * 100) + '%' }"></div>
                                        </div>
                                        <span class="text-xs text-neutral whitespace-nowrap">
                                            {{ currentData.skyblock_level.xpCurrent }} / {{ currentData.skyblock_level.xpForNext }} XP
                                        </span>
                                    </div>
                                </div>

                                <!-- Skills 2-column grid -->
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-1.5">
                                    <div class="space-y-1.5">
                                        <div v-for="skill in leftSkills" :key="skill.name" class="flex items-center gap-2">
                                            <span class="text-sm w-32 shrink-0 flex items-center gap-1">
                                                <span>{{ SKILL_ICONS[skill.name] || '❓' }}</span>
                                                <span class="text-white capitalize">{{ skill.name }}</span>
                                                <span class="text-profit font-bold ml-auto">{{ skill.level }}</span>
                                            </span>
                                            <div class="flex-1 relative h-5 bg-surface-700 rounded overflow-hidden">
                                                <div class="absolute h-full bg-profit/40 rounded transition-all duration-500"
                                                     :style="{ width: (skill.progress * 100) + '%' }"></div>
                                                <span class="absolute inset-0 flex items-center justify-end pr-2 text-[11px] text-neutral font-mono">
                                                    {{ formatXP(skill) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="space-y-1.5">
                                        <div v-for="skill in rightSkills" :key="skill.name" class="flex items-center gap-2">
                                            <span class="text-sm w-32 shrink-0 flex items-center gap-1">
                                                <span>{{ SKILL_ICONS[skill.name] || '❓' }}</span>
                                                <span class="text-white capitalize">{{ skill.name }}</span>
                                                <span class="text-profit font-bold ml-auto">{{ skill.level }}</span>
                                            </span>
                                            <div class="flex-1 relative h-5 bg-surface-700 rounded overflow-hidden">
                                                <div class="absolute h-full bg-profit/40 rounded transition-all duration-500"
                                                     :style="{ width: (skill.progress * 100) + '%' }"></div>
                                                <span class="absolute inset-0 flex items-center justify-end pr-2 text-[11px] text-neutral font-mono">
                                                    {{ formatXP(skill) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  SLAYER TAB                                        -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'slayer'">
                        <div v-if="currentData?.slayers && Object.keys(currentData.slayers).length > 0"
                             class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div v-for="(slayer, name) in currentData.slayers" :key="name"
                                 class="border border-border bg-surface-800 rounded p-4">
                                <h3 class="text-white font-bold capitalize mb-2 text-sm flex items-center gap-2">
                                    <span class="text-lg">{{ SLAYER_ICONS[name] || '💀' }}</span>
                                    {{ name }}
                                </h3>
                                <div class="flex items-end justify-between mb-2">
                                    <span class="text-3xl font-bold text-rarity-epic">{{ slayer.level?.currentLevel ?? 0 }}</span>
                                    <span class="text-xs text-neutral">/ {{ slayer.level?.maxLevel ?? 9 }}</span>
                                </div>
                                <div class="text-xs text-neutral font-mono">{{ fNum(slayer.xp) }} XP</div>
                            </div>
                        </div>
                        <div v-else class="text-neutral text-sm py-8 text-center">No slayer data available.</div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  DUNGEONS TAB                                      -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'dungeons'">
                        <div v-if="currentData?.dungeons && Object.keys(currentData.dungeons).length > 0">
                            <div class="border border-border bg-surface-800 rounded p-4 mb-4 max-w-lg">
                                <h3 class="text-white font-bold text-sm mb-3">Catacombs</h3>
                                <div class="flex items-end gap-3 mb-3">
                                    <span class="text-4xl font-bold text-rarity-rare">{{ currentData.dungeons.catacombs?.level?.level ?? 0 }}</span>
                                    <span class="text-neutral text-xs mb-1">Level</span>
                                </div>
                                <div class="relative h-4 bg-surface-700 rounded-full overflow-hidden mb-2">
                                    <div class="absolute h-full bg-rarity-rare/40 rounded-full"
                                         :style="{ width: ((currentData.dungeons.catacombs?.level?.progress ?? 0) * 100) + '%' }"></div>
                                </div>
                                <div class="text-[11px] text-neutral font-mono flex justify-between">
                                    <span>{{ fNum(currentData.dungeons.catacombs?.level?.xpCurrent ?? 0) }} / {{ fNum(currentData.dungeons.catacombs?.level?.xpForNext ?? 0) }}</span>
                                    <span>Total: {{ fNum(currentData.dungeons.catacombs?.level?.xp ?? 0) }} XP</span>
                                </div>
                                <div class="mt-3 flex items-center gap-2 text-xs">
                                    <span class="text-neutral">Secrets Found:</span>
                                    <span class="text-white font-bold font-mono">{{ (currentData.dungeons.secrets_found ?? 0).toLocaleString() }}</span>
                                </div>
                            </div>

                            <h3 class="text-white font-bold text-sm mb-2">Classes</h3>
                            <div v-if="currentData.dungeons.classes"
                                 class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                                <div v-for="(cls, name) in currentData.dungeons.classes" :key="name"
                                     class="border border-border bg-surface-800 rounded p-3"
                                     :class="{ 'ring-1 ring-profit/50': currentData.dungeons.selected_class === name }">
                                    <h4 class="text-white capitalize text-xs font-bold mb-1 flex items-center gap-1">
                                        <span>{{ CLASS_ICONS[name] || '🎮' }}</span>
                                        {{ name }}
                                        <span v-if="currentData.dungeons.selected_class === name" class="text-profit text-[10px]"> ●</span>
                                    </h4>
                                    <span class="text-lg font-bold text-rarity-rare">{{ cls.level }}</span>
                                    <div class="relative h-2 bg-surface-700 rounded-full overflow-hidden mt-1">
                                        <div class="absolute h-full bg-rarity-rare/40 rounded-full"
                                             :style="{ width: (cls.progress * 100) + '%' }"></div>
                                    </div>
                                    <div class="text-[10px] text-neutral font-mono mt-1">{{ fNum(cls.xp) }} XP</div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-neutral text-sm py-8 text-center">No dungeon data available.</div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  COMING SOON TABS                                  -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="['collections', 'misc'].includes(activeTab)"
                         class="border border-border bg-surface-800 rounded p-8 text-center">
                        <p class="text-neutral text-sm">{{ capitalize(activeTab) }} — Coming soon</p>
                        <p class="text-neutral/50 text-xs mt-1">This section is under development.</p>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
