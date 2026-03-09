<script setup>
import { ref, computed, onMounted, provide } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { preloadAllTextures, setEnabledPacks, getSkinUrl, getHeadUrl, getRarityColor, getItemTextureUrl, RARITY_COLORS, SKILL_ICONS, SLAYER_ICONS, CLASS_ICONS } from '@/utils/textures';
import ItemSlot from '@/Components/SkyBlock/ItemSlot.vue';
import InventoryGrid from '@/Components/SkyBlock/InventoryGrid.vue';
import PackSelector from '@/Components/SkyBlock/PackSelector.vue';
import PlayerModel from '@/Components/SkyBlock/PlayerModel.vue';

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
const expandedRiftEnderPage = ref(null); // index of opened rift enderchest page

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
const secondarySkillNames = ['alchemy', 'carpentry', 'taming', 'runecrafting', 'social', 'hunting'];

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
        case 'rift_enderchest':  return [];  // handled separately with pages
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
const riftEnderchestPages = computed(() => {
    const raw = currentData.value?.rift_enderchest;
    if (!raw || !Array.isArray(raw) || raw.length === 0) return [];
    // Split flat array into pages of 45 slots (5 rows × 9 cols)
    const slotsPerPage = 45;
    const pages = [];
    for (let i = 0; i < raw.length; i += slotsPerPage) {
        const pageSlots = raw.slice(i, i + slotsPerPage);
        pages.push({
            page: pages.length,
            items: pageSlots,
            count: pageSlots.filter(s => s !== null).length,
        });
    }
    return pages;
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

function skillBarClass(skill) {
    if (skill.level >= skill.maxLevel) return 'skill-bar-fill-max';
    if (skill.level >= 50) return 'skill-bar-fill-gold';
    return '';
}

function skillLevelClass(skill) {
    if (skill.level >= skill.maxLevel) return 'skill-level-max';
    if (skill.level >= 50) return 'skill-level-gold';
    return '';
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

// ── Dungeon helpers ──
function formatDungeonXP(level) {
    if (!level) return '0 XP';
    if (level.level >= (level.maxLevel || 50)) return fNum(level.xp) + ' XP';
    return fNum(level.xpCurrent) + ' / ' + fNum(level.xpForNext) + ' XP';
}

function formatDungeonClassXP(cls) {
    if (!cls) return '0 XP';
    if (cls.level >= (cls.maxLevel || 50)) return fNum(cls.xp) + ' XP';
    return fNum(cls.xpCurrent) + ' / ' + fNum(cls.xpForNext) + ' XP';
}

function dungeonLevelClass(level) {
    if (!level) return '';
    if (level.level >= (level.maxLevel || 50)) return 'skill-level-max';
    if (level.level >= 40) return 'skill-level-gold';
    return '';
}

function dungeonBarClass(level) {
    if (!level) return '';
    if (level.level >= (level.maxLevel || 50)) return 'skill-bar-fill-max';
    if (level.level >= 40) return 'skill-bar-fill-gold';
    return '';
}

function dungeonClassLevelClass(cls, name) {
    if (!cls) return '';
    const selected = currentData.value?.dungeons?.selected_class === name;
    if (cls.level >= (cls.maxLevel || 50)) return 'skill-level-max';
    if (cls.level >= 40) return 'skill-level-gold';
    if (selected) return 'skill-level-selected';
    return '';
}

function dungeonClassBarClass(cls) {
    if (!cls) return '';
    if (cls.level >= (cls.maxLevel || 50)) return 'skill-bar-fill-max';
    if (cls.level >= 40) return 'skill-bar-fill-gold';
    return '';
}

const allClassesMaxed = computed(() => {
    const classes = currentData.value?.dungeons?.classes;
    if (!classes) return false;
    return Object.values(classes).every(c => c.level >= (c.maxLevel || 50));
});

function formatStatName(key) {
    return key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function formatFloorStat(key, val) {
    if (key.startsWith('fastest_time')) {
        const totalSec = val / 1000;
        const min = Math.floor(totalSec / 60);
        const sec = (totalSec % 60).toFixed(1);
        return `${min}:${sec.padStart(4, '0')}`;
    }
    if (typeof val === 'number') return val.toLocaleString();
    return val;
}

function formatElapsed(ms) {
    const totalSec = ms / 1000;
    const min = Math.floor(totalSec / 60);
    const sec = Math.floor(totalSec % 60);
    return `${String(min).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
}

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
                            <!-- Left: 3D Player Model (interactive, SkyCrypt-style) -->
                            <div class="hidden lg:block w-52 shrink-0">
                                <div class="sticky top-20">
                                    <PlayerModel :uuid="profileData?.uuid" :width="208" :height="400" />
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
                                    @click="activeInventorySubTab = subTab.id; expandedBackpack = null; expandedEnderPage = null; expandedRiftEnderPage = null"
                                    class="inv-tab"
                                    :class="{ 'active-tab': activeInventorySubTab === subTab.id }">
                                    <img v-if="subTab.icon" :src="subTab.icon" class="inv-tab-icon" loading="lazy" />
                                    <span>{{ subTab.name }}</span>
                                </button>
                            </div>

                            <!-- BACKPACK / STORAGE sub-tab -->
                            <div v-if="activeInventorySubTab === 'backpack'">
                                <div v-if="backpackStorage.length > 0">
                                    <!-- Backpack cards (always visible) -->
                                    <div class="storage-cards">
                                        <button v-for="(bp, idx) in backpackStorage" :key="idx"
                                                class="storage-card"
                                                :class="{ 'storage-card-active': expandedBackpack === idx }"
                                                @click="expandedBackpack = expandedBackpack === idx ? null : idx">
                                            <img v-if="getItemTextureUrl(bp.icon)"
                                                 :src="getItemTextureUrl(bp.icon)"
                                                 class="storage-card-img" loading="lazy" draggable="false" />
                                            <div class="storage-card-info">
                                                <span class="storage-card-name">{{ bp.icon?.name || 'Backpack ' + (bp.slot + 1) }}</span>
                                                <span class="storage-card-count">{{ bp.count }} items</span>
                                            </div>
                                            <span class="storage-card-toggle">{{ expandedBackpack === idx ? '▲' : '▼' }}</span>
                                        </button>
                                    </div>

                                    <!-- Expanded backpack contents (below cards) -->
                                    <div v-if="expandedBackpack !== null" class="storage-expanded">
                                        <InventoryGrid :items="backpackStorage[expandedBackpack]?.items ?? []" />
                                    </div>
                                </div>
                                <div v-else class="text-neutral text-sm py-8 text-center">
                                    No backpack data available.
                                </div>
                            </div>

                            <!-- ENDERCHEST sub-tab (pages) -->
                            <div v-else-if="activeInventorySubTab === 'enderchest'">
                                <div v-if="enderchestPages.length > 0">
                                    <!-- Enderchest page cards (always visible) -->
                                    <div class="storage-cards">
                                        <button v-for="(page, idx) in enderchestPages" :key="idx"
                                                class="storage-card"
                                                :class="{ 'storage-card-active': expandedEnderPage === idx }"
                                                @click="expandedEnderPage = expandedEnderPage === idx ? null : idx">
                                            <img src="/img/textures/ender_chest.png"
                                                 class="storage-card-img" loading="lazy" draggable="false" />
                                            <div class="storage-card-info">
                                                <span class="storage-card-name">Page {{ idx + 1 }}</span>
                                                <span class="storage-card-count">{{ page.count }} items</span>
                                            </div>
                                            <span class="storage-card-toggle">{{ expandedEnderPage === idx ? '▲' : '▼' }}</span>
                                        </button>
                                    </div>

                                    <!-- Expanded enderchest page contents (below cards) -->
                                    <div v-if="expandedEnderPage !== null" class="storage-expanded">
                                        <InventoryGrid :items="enderchestPages[expandedEnderPage]?.items ?? []" />
                                    </div>
                                </div>
                                <div v-else class="text-neutral text-sm py-8 text-center">
                                    No ender chest data available.
                                </div>
                            </div>

                            <!-- RIFT ENDERCHEST sub-tab (pages) -->
                            <div v-else-if="activeInventorySubTab === 'rift_enderchest'">
                                <div v-if="riftEnderchestPages.length > 0">
                                    <!-- Rift enderchest page cards (always visible) -->
                                    <div class="storage-cards">
                                        <button v-for="(page, idx) in riftEnderchestPages" :key="idx"
                                                class="storage-card"
                                                :class="{ 'storage-card-active': expandedRiftEnderPage === idx }"
                                                @click="expandedRiftEnderPage = expandedRiftEnderPage === idx ? null : idx">
                                            <img src="/img/textures/ender_chest.png"
                                                 class="storage-card-img" loading="lazy" draggable="false" />
                                            <div class="storage-card-info">
                                                <span class="storage-card-name">Page {{ idx + 1 }}</span>
                                                <span class="storage-card-count">{{ page.count }} items</span>
                                            </div>
                                            <span class="storage-card-toggle">{{ expandedRiftEnderPage === idx ? '▲' : '▼' }}</span>
                                        </button>
                                    </div>

                                    <!-- Expanded rift enderchest page contents -->
                                    <div v-if="expandedRiftEnderPage !== null" class="storage-expanded">
                                        <InventoryGrid :items="riftEnderchestPages[expandedRiftEnderPage]?.items ?? []" />
                                    </div>
                                </div>
                                <div v-else class="text-neutral text-sm py-8 text-center">
                                    No rift ender chest data available.
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
                            <div class="hidden lg:block w-52 shrink-0">
                                <div class="sticky top-20">
                                    <PlayerModel :uuid="profileData?.uuid" :width="208" :height="400" />
                                </div>
                            </div>

                            <div class="flex-1 min-w-0">
                                <!-- SkyBlock Level bar -->
                                <div v-if="currentData?.skyblock_level" class="skill-row mb-4">
                                    <div class="skill-label">
                                        <span class="skill-icon">✫</span>
                                        <span class="skill-name" style="color: #FFAA00">Level</span>
                                        <span class="skill-level" style="color: #FFAA00">{{ currentData.skyblock_level.level }}</span>
                                    </div>
                                    <div class="skill-bar-track">
                                        <div class="skill-bar-fill skill-bar-fill-gold"
                                             :style="{ width: (currentData.skyblock_level.progress * 100) + '%' }"></div>
                                        <span class="skill-bar-text">
                                            {{ currentData.skyblock_level.xpCurrent }} / {{ currentData.skyblock_level.xpForNext }} XP
                                        </span>
                                    </div>
                                </div>

                                <!-- Skills 2-column grid (SkyCrypt-style) -->
                                <div class="skills-grid">
                                    <div v-for="skill in leftSkills" :key="skill.name" class="skill-row">
                                        <div class="skill-label">
                                            <span class="skill-icon">{{ SKILL_ICONS[skill.name] || '❓' }}</span>
                                            <span class="skill-name">{{ capitalize(skill.name) }}</span>
                                            <span class="skill-level" :class="skillLevelClass(skill)">{{ skill.level >= skill.maxLevel ? skill.maxLevel : skill.level }}</span>
                                        </div>
                                        <div class="skill-bar-track">
                                            <div class="skill-bar-fill"
                                                 :class="skillBarClass(skill)"
                                                 :style="{ width: (skill.level >= skill.maxLevel ? 100 : skill.progress * 100) + '%' }"></div>
                                            <span class="skill-bar-text">{{ formatXP(skill) }}</span>
                                        </div>
                                    </div>
                                    <div v-for="skill in rightSkills" :key="skill.name" class="skill-row">
                                        <div class="skill-label">
                                            <span class="skill-icon">{{ SKILL_ICONS[skill.name] || '❓' }}</span>
                                            <span class="skill-name">{{ capitalize(skill.name) }}</span>
                                            <span class="skill-level" :class="skillLevelClass(skill)">{{ skill.level >= skill.maxLevel ? skill.maxLevel : skill.level }}</span>
                                        </div>
                                        <div class="skill-bar-track">
                                            <div class="skill-bar-fill"
                                                 :class="skillBarClass(skill)"
                                                 :style="{ width: (skill.level >= skill.maxLevel ? 100 : skill.progress * 100) + '%' }"></div>
                                            <span class="skill-bar-text">{{ formatXP(skill) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Player Stats (SkyCrypt-style stat grid) -->
                                <div v-if="currentData?.player_stats?.length" class="stats-section">
                                    <div class="stats-grid">
                                        <span v-for="stat in currentData.player_stats" :key="stat.name"
                                              class="stat-chip"
                                              :style="{ '--stat-color': stat.color }">
                                            <span class="stat-icon">{{ stat.icon }}</span>
                                            <span class="stat-name">{{ stat.name }}</span>
                                            <span class="stat-value">{{ stat.value.toLocaleString() }}{{ stat.suffix }}</span>
                                        </span>
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
                            <!-- ── Catacombs + Classes skill bars (SkyCrypt-style) ── -->
                            <div class="dungeon-skill-bars">
                                <!-- Catacombs main level -->
                                <div class="skill-row">
                                    <div class="skill-label">
                                        <span class="skill-icon">💀</span>
                                        <span class="skill-name">Catacombs</span>
                                        <span class="skill-level" :class="dungeonLevelClass(currentData.dungeons.catacombs?.level)">
                                            {{ currentData.dungeons.catacombs?.level?.level ?? 0 }}
                                        </span>
                                    </div>
                                    <div class="skill-bar-track">
                                        <div class="skill-bar-fill"
                                             :class="dungeonBarClass(currentData.dungeons.catacombs?.level)"
                                             :style="{ width: ((currentData.dungeons.catacombs?.level?.progress ?? 0) * 100) + '%' }"></div>
                                        <span class="skill-bar-text">{{ formatDungeonXP(currentData.dungeons.catacombs?.level) }}</span>
                                    </div>
                                </div>

                                <!-- Class skill bars -->
                                <template v-for="(cls, name) in currentData.dungeons.classes" :key="name">
                                    <div class="skill-row">
                                        <div class="skill-label">
                                            <span class="skill-icon">{{ CLASS_ICONS[name] || '🎮' }}</span>
                                            <span class="skill-name">{{ capitalize(name) }}</span>
                                            <span class="skill-level" :class="dungeonClassLevelClass(cls, name)">
                                                {{ cls.level }}
                                            </span>
                                        </div>
                                        <div class="skill-bar-track">
                                            <div class="skill-bar-fill"
                                                 :class="dungeonClassBarClass(cls)"
                                                 :style="{ width: (cls.level >= 50 ? 100 : cls.progress * 100) + '%' }"></div>
                                            <span class="skill-bar-text">{{ formatDungeonClassXP(cls) }}</span>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- ── Dungeon summary info ── -->
                            <div class="dungeon-summary">
                                <div class="dungeon-summary-row">
                                    <span class="dungeon-stat-label">Selected Class:</span>
                                    <span class="dungeon-stat-value">{{ capitalize(currentData.dungeons.selected_class || 'none') }}</span>
                                </div>
                                <div class="dungeon-summary-row">
                                    <span class="dungeon-stat-label" :class="{ 'text-gold': allClassesMaxed }">Class Average:</span>
                                    <span class="dungeon-stat-value" :class="{ 'text-gold': allClassesMaxed }">{{ currentData.dungeons.class_average?.toFixed(2) ?? '0.00' }}</span>
                                </div>
                                <div class="dungeon-summary-row">
                                    <span class="dungeon-stat-label" :class="{ 'text-gold': currentData.dungeons.highest_floor === 7 }">Highest Floor Beaten (Normal):</span>
                                    <span class="dungeon-stat-value" :class="{ 'text-gold': currentData.dungeons.highest_floor === 7 }">{{ currentData.dungeons.highest_floor !== null ? currentData.dungeons.highest_floor : '—' }}</span>
                                </div>
                                <div v-if="currentData.dungeons.highest_master !== null" class="dungeon-summary-row">
                                    <span class="dungeon-stat-label" :class="{ 'text-gold': currentData.dungeons.highest_master === 7 }">Highest Floor Beaten (Master):</span>
                                    <span class="dungeon-stat-value" :class="{ 'text-gold': currentData.dungeons.highest_master === 7 }">{{ currentData.dungeons.highest_master }}</span>
                                </div>
                                <div class="dungeon-summary-row">
                                    <span class="dungeon-stat-label">Secrets Found:</span>
                                    <span class="dungeon-stat-value">{{ (currentData.dungeons.secrets_found ?? 0).toLocaleString() }}</span>
                                    <span class="dungeon-stat-note">({{ currentData.dungeons.secrets_per_run ?? 0 }} S/R)</span>
                                </div>
                            </div>

                            <!-- ── Normal Catacombs floors ── -->
                            <div v-if="currentData.dungeons.floors?.length" class="dungeon-floors-section">
                                <h3 class="dungeon-section-title">Catacombs</h3>
                                <div class="dungeon-floor-grid">
                                    <div v-for="floor in currentData.dungeons.floors" :key="'f'+floor.index" class="dungeon-floor-card">
                                        <div class="dungeon-floor-header">
                                            <span class="dungeon-floor-name">{{ floor.name.toUpperCase() }}</span>
                                        </div>
                                        <div class="dungeon-floor-body">
                                            <!-- Floor Stats -->
                                            <details v-if="Object.keys(floor.stats).length">
                                                <summary class="dungeon-details-toggle">Floor Stats</summary>
                                                <div class="dungeon-details-content">
                                                    <div v-for="(val, key) in floor.stats" :key="key" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ formatStatName(key) }}:</span>
                                                        <span class="dungeon-detail-value">{{ formatFloorStat(key, val) }}</span>
                                                    </div>
                                                    <div v-if="floor.most_damage" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">Most Damage:</span>
                                                        <span class="dungeon-detail-value">{{ fNum(floor.most_damage.value) }} <span class="dungeon-stat-note">({{ capitalize(floor.most_damage.class) }})</span></span>
                                                    </div>
                                                </div>
                                            </details>
                                            <!-- Best Run -->
                                            <details v-if="floor.best_run">
                                                <summary class="dungeon-details-toggle">Best Run</summary>
                                                <div class="dungeon-details-content">
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">Grade:</span>
                                                        <span class="dungeon-detail-value dungeon-grade" :class="'grade-' + floor.best_run.grade?.replace('+','plus')">{{ floor.best_run.grade }}</span>
                                                    </div>
                                                    <div v-if="floor.best_run.timestamp" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">Timestamp:</span>
                                                        <span class="dungeon-detail-value">{{ timeAgo(floor.best_run.timestamp) }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">Score Exploration:</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.score_exploration }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">Score Speed:</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.score_speed }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">Score Skill:</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.score_skill }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">Score Bonus:</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.score_bonus }}</span>
                                                    </div>
                                                    <div v-if="floor.best_run.dungeon_class" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">Dungeon Class:</span>
                                                        <span class="dungeon-detail-value">{{ capitalize(floor.best_run.dungeon_class) }}</span>
                                                    </div>
                                                    <div v-if="floor.best_run.elapsed_time" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">Elapsed Time:</span>
                                                        <span class="dungeon-detail-value">{{ formatElapsed(floor.best_run.elapsed_time) }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">Damage Dealt:</span>
                                                        <span class="dungeon-detail-value">{{ fNum(floor.best_run.damage_dealt) }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">Deaths:</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.deaths }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">Mobs Killed:</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.mobs_killed }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">Secrets Found:</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.secrets_found }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">Damage Mitigated:</span>
                                                        <span class="dungeon-detail-value">{{ fNum(floor.best_run.damage_mitigated) }}</span>
                                                    </div>
                                                </div>
                                            </details>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Master Catacombs floors ── -->
                            <div v-if="currentData.dungeons.master_floors?.length" class="dungeon-floors-section">
                                <h3 class="dungeon-section-title">Master Catacombs</h3>
                                <div class="dungeon-floor-grid">
                                    <div v-for="floor in currentData.dungeons.master_floors" :key="'m'+floor.index" class="dungeon-floor-card dungeon-floor-master">
                                        <div class="dungeon-floor-header">
                                            <span class="dungeon-floor-name">{{ floor.name.toUpperCase() }}</span>
                                        </div>
                                        <div class="dungeon-floor-body">
                                            <details v-if="Object.keys(floor.stats).length">
                                                <summary class="dungeon-details-toggle">Floor Stats</summary>
                                                <div class="dungeon-details-content">
                                                    <div v-for="(val, key) in floor.stats" :key="key" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ formatStatName(key) }}:</span>
                                                        <span class="dungeon-detail-value">{{ formatFloorStat(key, val) }}</span>
                                                    </div>
                                                    <div v-if="floor.most_damage" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">Most Damage:</span>
                                                        <span class="dungeon-detail-value">{{ fNum(floor.most_damage.value) }} <span class="dungeon-stat-note">({{ capitalize(floor.most_damage.class) }})</span></span>
                                                    </div>
                                                </div>
                                            </details>
                                            <details v-if="floor.best_run">
                                                <summary class="dungeon-details-toggle">Best Run</summary>
                                                <div class="dungeon-details-content">
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">Grade:</span>
                                                        <span class="dungeon-detail-value dungeon-grade" :class="'grade-' + floor.best_run.grade?.replace('+','plus')">{{ floor.best_run.grade }}</span>
                                                    </div>
                                                    <div v-if="floor.best_run.timestamp" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">Timestamp:</span>
                                                        <span class="dungeon-detail-value">{{ timeAgo(floor.best_run.timestamp) }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">Score Exploration:</span><span class="dungeon-detail-value">{{ floor.best_run.score_exploration }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">Score Speed:</span><span class="dungeon-detail-value">{{ floor.best_run.score_speed }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">Score Skill:</span><span class="dungeon-detail-value">{{ floor.best_run.score_skill }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">Score Bonus:</span><span class="dungeon-detail-value">{{ floor.best_run.score_bonus }}</span></div>
                                                    <div v-if="floor.best_run.dungeon_class" class="dungeon-detail-row"><span class="dungeon-detail-label">Dungeon Class:</span><span class="dungeon-detail-value">{{ capitalize(floor.best_run.dungeon_class) }}</span></div>
                                                    <div v-if="floor.best_run.elapsed_time" class="dungeon-detail-row"><span class="dungeon-detail-label">Elapsed Time:</span><span class="dungeon-detail-value">{{ formatElapsed(floor.best_run.elapsed_time) }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">Damage Dealt:</span><span class="dungeon-detail-value">{{ fNum(floor.best_run.damage_dealt) }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">Deaths:</span><span class="dungeon-detail-value">{{ floor.best_run.deaths }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">Mobs Killed:</span><span class="dungeon-detail-value">{{ floor.best_run.mobs_killed }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">Secrets Found:</span><span class="dungeon-detail-value">{{ floor.best_run.secrets_found }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">Damage Mitigated:</span><span class="dungeon-detail-value">{{ fNum(floor.best_run.damage_mitigated) }}</span></div>
                                                </div>
                                            </details>
                                        </div>
                                    </div>
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
