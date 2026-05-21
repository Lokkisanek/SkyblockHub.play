<script setup>
import { ref, computed, watch, onMounted, provide, nextTick } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/strings/useI18n';
import { preloadAllTextures, setEnabledPacks, getSkinUrl, getHeadUrl, getRarityColor, getItemTextureUrl, RARITY_COLORS, SKILL_ICONS, SLAYER_ICONS, CLASS_ICONS } from '@/utils/textures';
import { DASHBOARD_SLAYER_ICON_ITEMS } from '@/lib/dashboardProfileIcons';
import ItemSlot from '@/Components/SkyBlock/ItemSlot.vue';
import InventoryGrid from '@/Components/SkyBlock/InventoryGrid.vue';
import PackSelector from '@/Components/SkyBlock/PackSelector.vue';
import ProfilePlayerPreview from '@/Components/SkyBlock/ProfilePlayerPreview.vue';
import { loadEnabledPacks, loadPerformanceMode } from '@/utils/profileViewerSettings';
import McText from '@/Components/SkyBlock/McText.vue';
import { skillAvgColorCode } from '@/utils/minecraftColors';

const { t } = useI18n();

const props = defineProps({
    minecraftUsername: { type: String, default: null },
});

const username = ref(props.minecraftUsername || '');
const profileData = ref(null);
const loading = ref(false);
const error = ref('');
const selectedProfile = ref(null);

const hasLoadedProfile = computed(
    () => !!(profileData.value && selectedProfile.value && currentProfile.value && !loading.value)
);

/** Frosted scrim fades in together with the 1s spacer / search-bar move (same duration as layout transition). */
const profileStatsScrimEntered = ref(false);

function revealProfileStatsScrim() {
    if (typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        profileStatsScrimEntered.value = true;
        return;
    }
    nextTick(() => {
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                profileStatsScrimEntered.value = true;
            });
        });
    });
}

watch(hasLoadedProfile, (loaded) => {
    if (!loaded) {
        profileStatsScrimEntered.value = false;
        return;
    }
    revealProfileStatsScrim();
});

const showHeroLoading = computed(() => loading.value && !profileData.value);
const activeTab = ref('gear');
let activeFetchController = null;
let fetchSequence = 0;

/* ── Tab definitions ─────────────────────────────────────── */
const tabs = computed(() => [
    { id: 'gear',        name: t('profileStats.tabGear') },
    { id: 'accessories', name: t('profileStats.tabAccessories') },
    { id: 'inventory',   name: t('profileStats.tabInventory') },
    { id: 'pets',        name: t('profileStats.tabPets') },
    { id: 'skills',      name: t('profileStats.tabSkills') },
    { id: 'dungeons',    name: t('profileStats.tabDungeons') },
    { id: 'slayer',      name: t('profileStats.tabSlayer') },
    { id: 'collections', name: t('profileStats.tabCollections') },
]);

/* ── Inventory sub-tabs with SkyBlock UI style icons ────────── */
const inventorySubTabs = computed(() => {
    const headIcon = profileData.value?.uuid
        ? `https://mc-heads.net/avatar/${profileData.value.uuid}/32`
        : null;
    return [
        { id: 'inv',              name: t('profileStats.subInventory'),      icon: headIcon },
        { id: 'backpack',         name: t('profileStats.subBackpack'),       icon: '/img/textures/chest.png' },
        { id: 'enderchest',       name: t('profileStats.subEnderchest'),     icon: '/img/textures/ender_chest.png' },
        { id: 'personal_vault',   name: t('profileStats.subPersonalVault'),   icon: '/img/textures/chest.png' },
        { id: 'talisman_bag',     name: t('profileStats.subTalismanBag'),     icon: '/img/textures/ender_eye.png' },
        { id: 'potion_bag',       name: t('profileStats.subPotionBag'),       icon: '/img/textures/potion_bottle_drinkable.png' },
        { id: 'fishing_bag',      name: t('profileStats.subFishingBag'),      icon: '/img/textures/fishing_rod_uncast.png' },
        { id: 'quiver',           name: t('profileStats.subQuiver'),          icon: '/img/textures/arrow.png' },
        { id: 'museum',           name: t('profileStats.subMuseum'),          icon: '/img/textures/gold_ingot.png' },
        { id: 'rift_inventory',   name: t('profileStats.subRiftInventory'),   icon: '/img/textures/ender_pearl.png' },
        { id: 'rift_enderchest',  name: t('profileStats.subRiftEnderchest'),  icon: '/img/textures/ender_chest.png' },
    ];
});

const activeInventorySubTab = ref('inv');
const expandedBackpack = ref(null);   // index of opened backpack (null = all collapsed)
const expandedEnderPage = ref(null);  // index of opened enderchest page
const expandedRiftEnderPage = ref(null); // index of opened rift enderchest page

/* ── Pets collapsible state ────────────────────────────────── */
const showMorePets = ref(false);
const showMissingPets = ref(false);

/* ── Pets computed ─────────────────────────────────────────── */
const activePet = computed(() => currentData.value?.pets?.pets?.find(p => p.active) ?? null);
const displayUniquePets = computed(() => {
    const unique = currentData.value?.pets?.uniquePets ?? [];
    return unique.filter(p => !p.active);
});

/* ── Texture pack version key ──────────────────────────────── */
const textureVersion = ref(0);
const performanceMode = ref(loadPerformanceMode());
provide('textureVersion', textureVersion);
provide('profilePerformanceMode', performanceMode);

async function onPacksChanged(packIds) {
    await setEnabledPacks(packIds);
    textureVersion.value++;
}

function onPerformanceChanged(enabled) {
    performanceMode.value = enabled;
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

/* ── API fetch (Hypixel-backed profile JSON) ─────────────── */
async function fetchProfile() {
    const name = username.value.trim();
    if (!name) return;

    if (activeFetchController) {
        activeFetchController.abort();
    }

    const requestId = ++fetchSequence;
    const controller = new AbortController();
    activeFetchController = controller;
    const timeoutId = setTimeout(() => controller.abort(), 25000);

    loading.value = true;
    error.value = '';
    profileData.value = null;
    selectedProfile.value = null;

    try {
        const res = await fetch(`/api/profile/minecraft/${encodeURIComponent(name)}`, {
            signal: controller.signal,
        });
        const json = await res.json();

        if (requestId !== fetchSequence) {
            return;
        }

        if (!res.ok) {
            error.value = json.error || t('profileStats.fetchFailed');
            return;
        }

        const profiles = json.data?.profiles ?? {};
        const keys = Object.keys(profiles);

        if (keys.length === 0) {
            error.value = json.error || t('profileStats.fetchFailed');
            profileData.value = null;
            selectedProfile.value = null;
            return;
        }

        profileData.value = json.data;

        const sel = keys.find((k) => profiles[k].selected) || keys[0];
        if (sel) selectedProfile.value = sel;
    } catch (e) {
        if (requestId !== fetchSequence) {
            return;
        }

        if (e?.name === 'AbortError') {
            error.value = t('profileStats.requestTimeout');
        } else {
            error.value = t('profileStats.networkError');
        }
    } finally {
        clearTimeout(timeoutId);

        if (requestId === fetchSequence) {
            loading.value = false;
            activeFetchController = null;
        }
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

// ── Rank display helpers ─────────────────────────────────
const rankPlusText = computed(() => {
    const prefix = profileData.value?.rank?.prefix;
    if (!prefix || !profileData.value?.rank?.plusColor) return '';
    const match = prefix.match(/(\+{1,2})/);
    return match ? match[1] : '';
});

const rankTextBefore = computed(() => {
    const prefix = profileData.value?.rank?.prefix;
    if (!prefix) return '';
    const plusMatch = prefix.match(/(\+{1,2})/);
    if (!plusMatch) return prefix + ' ';
    return prefix.substring(0, plusMatch.index);
});

const rankTextAfter = computed(() => {
    const prefix = profileData.value?.rank?.prefix;
    if (!prefix) return '';
    const plusMatch = prefix.match(/(\+{1,2})/);
    if (!plusMatch) return '';
    const afterPlus = prefix.substring(plusMatch.index + plusMatch[1].length);
    return afterPlus ? afterPlus + ' ' : '] ';
});

// ── Skills ──────────────────────────────────────────────
const mainSkillNames = ['farming', 'mining', 'combat', 'foraging', 'fishing', 'enchanting'];
const secondarySkillNames = ['alchemy', 'carpentry', 'taming', 'runecrafting', 'social', 'hunting'];

const allSkills = computed(() => {
    if (!currentData.value?.skills) return [];
    return [...mainSkillNames, ...secondarySkillNames]
        .filter(n => currentData.value.skills[n])
        .map(n => ({ name: n, ...currentData.value.skills[n] }));
});

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
/** Talisman bag headline stats (server denominators + full accessory_bag_storage). */
const accessoryDisplay = computed(() => {
    const items = currentData.value?.accessories ?? [];
    const summary = currentData.value?.accessory_summary;
    const bag = currentData.value?.accessory_bag_storage ?? {};

    const ids = new Set();
    let recombedLocal = 0;
    for (const item of items) {
        if (item?.skyblock_id) ids.add(item.skyblock_id);
        if (item?.recombobulated) recombedLocal++;
    }

    const unique = typeof summary?.unique === 'number' ? summary.unique : ids.size;
    const uniqueMax = typeof summary?.unique_max === 'number' ? summary.unique_max : null;
    const recombobulated = typeof summary?.recombobulated === 'number' ? summary.recombobulated : recombedLocal;
    const recombobMax = typeof summary?.recombobulatable_max === 'number' ? summary.recombobulatable_max : null;

    const uniquePct =
        uniqueMax != null && uniqueMax > 0 ? Math.round((unique / uniqueMax) * 100) : null;

    const rawMp = bag?.highest_magical_power;
    const mpNum = rawMp === null || rawMp === undefined || rawMp === '' ? NaN : Number(rawMp);
    const magicalPower = Number.isFinite(mpNum) ? mpNum : null;

    const rawPower = bag?.selected_power;
    const selectedPower =
        typeof rawPower === 'string' && rawPower.length > 0 ? capitalize(rawPower) : null;

    return {
        unique,
        uniqueMax,
        uniquePct,
        recombobulated,
        recombobMax,
        selectedPower,
        magicalPower,
    };
});

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

/* ── Slayer computed data (new structure: { slayers, total_slayer_xp, total_coins_spent }) ── */
const slayerData = computed(() => currentData.value?.slayers ?? { slayers: {}, total_slayer_xp: 0, total_coins_spent: 0 });

/* ── Collections computed data ── */
const collectionsData = computed(() => currentData.value?.collections ?? { categories: {}, totalCollections: 0, maxedCollections: 0 });

const ROMAN = ['', 'I', 'II', 'III', 'IV', 'V'];
function romanNumeral(n) { return ROMAN[n] || String(n); }

const COLLECTION_CATEGORY_ICONS = {
    FARMING: '🌾', MINING: '⛏️', COMBAT: '⚔️', FORAGING: '🌲', FISHING: '🎣', RIFT: '🌀', BOSS: '💀',
};

function slayerMaxTier(key) {
    return key === 'vampire' ? 5 : 5;
}

function formatSlayerXP(slayer) {
    const lvl = slayer.level;
    if (!lvl) return `0 ${t('profileStats.xp')}`;
    if (lvl.currentLevel >= lvl.maxLevel) return `${Number(lvl.xp).toLocaleString()} ${t('profileStats.xp')}`;
    return `${Number(lvl.xpCurrent).toLocaleString()} / ${Number(lvl.xpForNext).toLocaleString()} ${t('profileStats.xp')}`;
}

function slayerTextureUrl(key) {
    const item = DASHBOARD_SLAYER_ICON_ITEMS[key];
    return item ? getItemTextureUrl(item) : null;
}

function slayerBarFillClass(slayer) {
    const lvl = slayer.level;
    if (lvl?.currentLevel >= lvl?.maxLevel) return 'slayer-xp-bar-fill--max';
    return '';
}

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

/** In-game level caps (API may still expose a higher maxLevel). */
const SKILL_LEVEL_CAPS = {
    alchemy: 50,
};

function skillLevelCap(skill) {
    return SKILL_LEVEL_CAPS[skill.name] ?? skill.maxLevel;
}

function skillIsMaxed(skill) {
    return skill.level >= skillLevelCap(skill);
}

function skillBarProgress(skill) {
    if (skillIsMaxed(skill)) return 1;
    return skill.progress ?? 0;
}

function formatXP(skill) {
    if (skillIsMaxed(skill)) return `${fNum(skill.xp)} ${t('profileStats.xp')}`;
    return `${fNum(skill.xpCurrent)} / ${fNum(skill.xpForNext)} ${t('profileStats.xp')}`;
}

/** Vanilla `/item/...` paths for Profile Stats skill icons (ring). */
const SKILL_TEXTURE_PATHS = {
    farming: '/item/golden_hoe',
    mining: '/item/iron_pickaxe',
    combat: '/item/diamond_sword',
    foraging: '/item/diamond_axe',
    fishing: '/item/fishing_rod_uncast',
    enchanting: '/item/book_enchanted',
    alchemy: '/item/brewing_stand',
    carpentry: '/item/iron_axe',
    taming: '/item/bone',
    runecrafting: '/item/ender_eye',
    social: '/item/cake',
    hunting: '/item/feather',
};

const SB_LEVEL_TEXTURE_ITEM = { texture_path: '/item/experience_bottle' };

/** Dungeon class / catacombs icons (2-col grid, screenshot layout). */
const DUNGEON_TEXTURE_PATHS = {
    catacombs: '/item/skull',
    healer: '/item/potion',
    mage: '/item/blaze_rod',
    berserk: '/item/diamond_sword',
    archer: '/item/bow',
    tank: '/item/iron_chestplate',
};

/** Row-major 2-column order: L Catacombs, Berserk, Mage | R Archer, Healer, Tank */
const DUNGEON_DISPLAY_ORDER = ['catacombs', 'archer', 'berserk', 'healer', 'mage', 'tank'];

function skillTextureItem(skillName) {
    const path = SKILL_TEXTURE_PATHS[skillName];
    return path ? { texture_path: path } : null;
}

function skillTextureUrl(skillName) {
    return getItemTextureUrl(skillTextureItem(skillName));
}

/** Row accent matches pill + icon ring (green default, orange max, gold SB level). */
function skillRowAccentClass(skill) {
    if (skillIsMaxed(skill)) return 'ps-skill-row--max';
    return '';
}

function dungeonRowAccentClass(row) {
    if (row.level >= row.maxLevel) return 'ps-skill-row--max';
    return '';
}

function formatSkyblockLevelXP(sb) {
    if (!sb) return '';
    return `${fNum(sb.xpCurrent)} / ${fNum(sb.xpForNext)} ${t('profileStats.xp')}`;
}

function timeAgo(ts) {
    if (!ts) return '—';
    const ms = Date.now() - ts;
    const years = Math.floor(ms / (365.25 * 24 * 60 * 60 * 1000));
    if (years >= 1) return years > 1 ? t('profileStats.yearsAgo', { count: years }) : t('profileStats.yearAgo', { count: years });
    const months = Math.floor(ms / (30.44 * 24 * 60 * 60 * 1000));
    if (months >= 1) return months > 1 ? t('profileStats.monthsAgo', { count: months }) : t('profileStats.monthAgo', { count: months });
    const days = Math.floor(ms / (24 * 60 * 60 * 1000));
    if (days >= 1) return days > 1 ? t('profileStats.daysAgo', { count: days }) : t('profileStats.dayAgo', { count: days });
    const hours = Math.floor(ms / (60 * 60 * 1000));
    return hours > 1 ? t('profileStats.hoursAgo', { count: hours }) : t('profileStats.hourAgo', { count: hours });
}

function capitalize(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
function petName(t) { return t.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, c => c.toUpperCase()); }

const PET_STAT_ABBREV = {
    'Fishing Speed': 'FS',
    'Sea Creature Chance': 'SCC',
    'Strength': 'Str',
    'Farming Fortune': 'FmFrt',
    'Mining Fortune': 'MnFrt',
    'Mining Speed': 'MnSpd',
    'Foraging Fortune': 'FgFrt',
    'Magic Find': 'MF',
    'Critical Chance': 'CC',
    'Critical Damage': 'CD',
    'Defense': 'Def',
    'Speed': 'Spd',
};

function petBonusLine(pet) {
    const stats = pet?.profile?.stats;
    if (!stats || typeof stats !== 'object') return null;
    const parts = [];
    for (const [label, stat] of Object.entries(stats)) {
        if (!stat?.value) continue;
        const ab = PET_STAT_ABBREV[label] || label;
        const v = stat.percent ? `${stat.value}%` : stat.value;
        parts.push(`${v} ${ab}`);
    }
    return parts.length ? parts.join(' // ') : null;
}

/** Minimal item shape for {@link getItemTextureUrl} from API `texture_path`. */
function collectionTextureItem(item) {
    return item?.texture_path ? { texture_path: item.texture_path } : null;
}

// ── Dungeon helpers ──
function formatDungeonXP(level) {
    if (!level) return `0 ${t('profileStats.xp')}`;
    if (level.level >= (level.maxLevel || 50)) return `${fNum(level.xp)} ${t('profileStats.xp')}`;
    return `${fNum(level.xpCurrent)} / ${fNum(level.xpForNext)} ${t('profileStats.xp')}`;
}

function formatDungeonClassXP(cls) {
    if (!cls) return `0 ${t('profileStats.xp')}`;
    if (cls.level >= (cls.maxLevel || 50)) return `${fNum(cls.xp)} ${t('profileStats.xp')}`;
    return `${fNum(cls.xpCurrent)} / ${fNum(cls.xpForNext)} ${t('profileStats.xp')}`;
}

function dungeonTextureUrl(key) {
    const path = DUNGEON_TEXTURE_PATHS[key];
    return path ? getItemTextureUrl({ texture_path: path }) : null;
}

const dungeonDisplayRows = computed(() => {
    const dungeons = currentData.value?.dungeons;
    if (!dungeons) return [];

    return DUNGEON_DISPLAY_ORDER.flatMap((key) => {
        if (key === 'catacombs') {
            const level = dungeons.catacombs?.level;
            if (!level) return [];
            const maxLevel = level.maxLevel || 50;
            const lvl = level.level ?? 0;
            return [{
                key,
                name: t('profileStats.catacombs'),
                level: lvl,
                maxLevel,
                progress: level.progress ?? 0,
                xpLabel: formatDungeonXP(level),
            }];
        }

        const cls = dungeons.classes?.[key];
        if (!cls) return [];
        const maxLevel = cls.maxLevel || 50;
        return [{
            key,
            name: capitalize(key),
            level: cls.level,
            maxLevel,
            progress: cls.progress ?? 0,
            xpLabel: formatDungeonClassXP(cls),
        }];
    });
});

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
    await setEnabledPacks(loadEnabledPacks());
    preloadAllTextures();
    if (username.value) fetchProfile();
});
</script>

<template>
    <Head :title="t('profileStats.title')" />

    <AuthenticatedLayout>
        <!-- Frosted scrim over wallpaper: must live here (page wraps layout — inject from layout never ran). -->
        <div
            aria-hidden="true"
            class="profile-stats-bg-scrim pointer-events-none fixed inset-0 z-[15]"
            :class="[
                hasLoadedProfile ? 'profile-stats-bg-scrim--loaded' : 'profile-stats-bg-scrim--hero',
                profileStatsScrimEntered && 'profile-stats-bg-scrim--visible',
            ]"
        />
        <div class="relative z-20" :class="[hasLoadedProfile ? 'py-4' : '', performanceMode && 'profile-stats--perf']">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex w-full flex-col">
                    <!-- Top spacer: height animates (flex-grow does not interpolate in browsers). -->
                    <div
                        aria-hidden="true"
                        class="shrink-0 overflow-hidden transition-[height,opacity] duration-1000 ease-out motion-reduce:transition-none"
                        :class="
                            hasLoadedProfile
                                ? 'pointer-events-none h-0 opacity-0'
                                : 'h-[min(36vh,300px)] opacity-100'
                        "
                    />

                    <div class="z-10 flex w-full shrink-0 justify-center" :class="hasLoadedProfile ? 'mb-8' : ''">
                        <div class="w-full max-w-2xl rounded-2xl border border-border/80 bg-surface-900/75 p-3 shadow-[0_16px_40px_rgba(0,0,0,0.35)] backdrop-blur-sm">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <div class="relative flex-1">
                                    <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M8.5 3a5.5 5.5 0 104.35 8.87l2.64 2.64a1 1 0 001.42-1.42l-2.64-2.64A5.5 5.5 0 008.5 3zm-3.5 5.5a3.5 3.5 0 117 0 3.5 3.5 0 01-7 0z" clip-rule="evenodd" />
                                    </svg>
                                    <input
                                        v-model="username"
                                        type="text"
                                        :placeholder="t('profileStats.searchPlaceholder')"
                                        class="w-full rounded-xl border border-border/80 bg-surface-800/80 py-3 pl-11 pr-4 text-sm text-white placeholder:text-neutral/80 transition focus:border-profit/70 focus:outline-none focus:ring-2 focus:ring-profit/25"
                                        @keyup.enter="fetchProfile"
                                    />
                                </div>
                                <button
                                    type="button"
                                    @click="fetchProfile"
                                    :disabled="loading"
                                    class="inline-flex h-[46px] items-center justify-center rounded-xl border border-profit/35 bg-profit/20 px-6 text-sm font-semibold text-profit transition hover:bg-profit/30 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {{ loading ? t('profileStats.loading') : t('profileStats.search') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="error"
                        class="mx-auto mb-4 w-full max-w-2xl shrink-0 rounded-lg border border-loss/50 bg-loss/10 px-4 py-3 text-center text-sm text-loss"
                        :class="hasLoadedProfile ? '' : 'mt-3'"
                    >
                        {{ error }}
                    </div>

                    <div
                        v-if="!hasLoadedProfile"
                        class="flex shrink-0 flex-col items-center justify-center gap-4 py-6"
                        :class="showHeroLoading ? 'min-h-[4.5rem]' : 'min-h-0'"
                    >
                        <div v-if="showHeroLoading" class="flex items-center gap-3 text-neutral" role="status" aria-live="polite">
                            <span class="h-5 w-5 shrink-0 animate-spin rounded-full border-2 border-neutral/40 border-t-profit" />
                            <span class="text-sm text-neutral">{{ t('profileStats.fetchingProfile') }}</span>
                        </div>
                    </div>

                    <div
                        aria-hidden="true"
                        class="shrink-0 overflow-hidden transition-[height,opacity] duration-1000 ease-out motion-reduce:transition-none"
                        :class="
                            hasLoadedProfile
                                ? 'pointer-events-none h-0 opacity-0'
                                : 'h-[min(36vh,300px)] opacity-100'
                        "
                    />

                    <!-- ════════════════════════════════════════════════════════ -->
                    <!--  PROFILE CONTENT                                        -->
                    <!-- ════════════════════════════════════════════════════════ -->
                    <div v-if="hasLoadedProfile" class="w-full">

                    <p
                        v-if="currentData?.networth?.pricing_mode === 'bazaar_fallback'"
                        class="mb-3 rounded-lg border border-amber-500/35 bg-amber-500/10 px-3 py-2 text-xs leading-relaxed text-amber-100/95"
                        role="status"
                    >
                        <span>{{ t('profileStats.networthFallbackNotice') }}</span>
                        <span
                            v-if="currentData.networth.pricing_failure_reason"
                            class="mt-1.5 block font-mono text-[11px] text-amber-200/95"
                        >
                            {{ currentData.networth.pricing_failure_reason }}
                            <template v-if="currentData.networth.pricing_failure_detail">
                                — {{ currentData.networth.pricing_failure_detail }}
                            </template>
                        </span>
                    </p>

                    <!-- ═══ STATS SUMMARY BAR ═══ -->
                    <dl v-if="currentData" class="profile-stat-strip profile-stat-strip--summary">
                        <div class="profile-stat-cell">
                            <dt>{{ t('profileStats.joined') }}</dt>
                            <dd><McText :text="`§f${timeAgo(currentData.first_join)}`" /></dd>
                        </div>
                        <div class="profile-stat-cell">
                            <dt>{{ t('profileStats.purse') }}</dt>
                            <dd>
                                <McText :text="`§6${fNum(currentData.networth?.purse)}§7 ${t('profileStats.coins')}`" />
                            </dd>
                        </div>
                        <div class="profile-stat-cell">
                            <dt>{{ t('profileStats.bank') }}</dt>
                            <dd>
                                <McText :text="`§6${fNum(currentData.networth?.bank)}§7 ${t('profileStats.coins')}`" />
                            </dd>
                        </div>
                        <div class="profile-stat-cell">
                            <dt>{{ t('profileStats.skillAvg') }}</dt>
                            <dd>
                                <McText
                                    :text="`§${skillAvgColorCode(currentData.average_skill_level)}${currentData.average_skill_level}`"
                                />
                            </dd>
                        </div>
                        <div class="profile-stat-cell">
                            <dt>{{ t('profileStats.fairySouls') }}</dt>
                            <dd>
                                <McText :text="`§d${currentData.fairy_souls ?? '—'}§8 / §f267`" />
                            </dd>
                        </div>
                        <div class="profile-stat-cell">
                            <dt>{{ t('profileStats.networth') }}</dt>
                            <dd><McText :text="`§6${fNum(currentData.networth?.networth)}`" /></dd>
                        </div>
                    </dl>

                    <!-- ═══ PROFILE SELECTOR + HEADER ═══ -->
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                        <div class="profile-pill-group" role="tablist" aria-label="Profiles">
                            <button
                                v-for="(profile, key) in profileData.profiles"
                                :key="key"
                                type="button"
                                @click="selectedProfile = key"
                                class="profile-pill-btn"
                                :class="{ 'profile-pill-btn--active': selectedProfile === key }"
                            >
                                <span>{{ profile.cute_name || key }}</span>
                                <span v-if="profile.selected" class="profile-pill-dot" aria-hidden="true" />
                            </button>
                        </div>
                        <div class="flex shrink-0 items-center gap-2 sm:justify-end">
                            <PackSelector
                                @update:packs="onPacksChanged"
                                @update:performance="onPerformanceChanged"
                            />
                        </div>
                    </div>

                    <!-- ═══ MAIN TAB NAVIGATION (SkyBlock UI style underline) ═══ -->
                    <div class="flex border-b border-border mb-6 overflow-x-auto overscroll-x-contain -mx-1 px-1 sm:mx-0 sm:px-0">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            @click="activeTab = tab.id"
                            class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider whitespace-nowrap border-b-2 transition"
                            :class="activeTab === tab.id
                                ? 'border-profit text-profit'
                                : 'border-transparent text-neutral hover:text-white'"
                        >
                            {{ tab.name }}
                        </button>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  GEAR TAB (SkyBlock UI style)                         -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'gear'">
                        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
                            <!-- Desktop: sticky 3D model -->
                            <div class="hidden lg:block w-52 shrink-0">
                                <div class="sticky top-20">
                                    <div class="player-name-rank">
                                        <template v-if="profileData?.rank?.prefix">
                                            <span class="rank-prefix" :style="{ color: profileData.rank.color }">
                                                {{ rankTextBefore }}<!--
                                            --><span v-if="rankPlusText" class="rank-plus" :style="{ color: profileData.rank.plusColor }">{{ rankPlusText }}</span><!--
                                            --><span v-if="rankTextAfter" :style="{ color: profileData.rank.color }">{{ rankTextAfter }}</span>
                                            </span>
                                            <span class="player-username" :style="{ color: profileData.rank.color }">{{ profileData.username }}</span>
                                        </template>
                                        <span v-else class="player-username" style="color: #AAAAAA">{{ profileData?.username }}</span>
                                    </div>
                                    <ProfilePlayerPreview :uuid="profileData?.uuid" :width="208" :height="400" />
                                </div>
                            </div>

                            <!-- Mobile / tablet: compact model above gear -->
                            <div class="flex flex-col items-center lg:hidden">
                                <div class="player-name-rank">
                                    <template v-if="profileData?.rank?.prefix">
                                        <span class="rank-prefix" :style="{ color: profileData.rank.color }">
                                            {{ rankTextBefore }}<!--
                                        --><span v-if="rankPlusText" class="rank-plus" :style="{ color: profileData.rank.plusColor }">{{ rankPlusText }}</span><!--
                                        --><span v-if="rankTextAfter" :style="{ color: profileData.rank.color }">{{ rankTextAfter }}</span>
                                        </span>
                                        <span class="player-username" :style="{ color: profileData.rank.color }">{{ profileData.username }}</span>
                                    </template>
                                    <span v-else class="player-username" style="color: #AAAAAA">{{ profileData?.username }}</span>
                                </div>
                                <ProfilePlayerPreview :uuid="profileData?.uuid" :width="144" :height="280" />
                            </div>

                            <div class="flex-1 min-w-0 space-y-8 lg:space-y-10">

                                <!-- ARMOR -->
                                <section v-if="currentData?.armor?.length">
                                    <h3 class="stat-header">{{ t('profileStats.armor') }}</h3>
                                    <div v-if="currentData.armor.some(a => a)" class="pieces">
                                        <ItemSlot v-for="(item, i) in [...currentData.armor].reverse()" :key="'a'+i" :item="item" />
                                    </div>
                                    <div v-if="Object.keys(armorStats).length" class="mt-3 text-xs font-semibold">
                                        <span class="text-neutral">{{ t('profileStats.bonus') }}</span>
                                        <template v-for="(stat, key, idx) in armorStats" :key="key">
                                            <span v-if="idx > 0" class="text-neutral opacity-50"> // </span>
                                            <span :style="{ color: getStatColor(key) }">{{ formatStat(key, stat) }}</span>
                                        </template>
                                    </div>
                                </section>

                                <!-- EQUIPMENT -->
                                <section v-if="currentData?.equipment?.length">
                                    <h3 class="stat-header">{{ t('profileStats.equipment') }}</h3>
                                    <div class="pieces">
                                        <ItemSlot v-for="(item, i) in [...currentData.equipment].reverse()" :key="'e'+i" :item="item" />
                                    </div>
                                    <div v-if="Object.keys(equipmentStats).length" class="mt-3 text-xs font-semibold">
                                        <span class="text-neutral">{{ t('profileStats.bonus') }}</span>
                                        <template v-for="(stat, key, idx) in equipmentStats" :key="key">
                                            <span v-if="idx > 0" class="text-neutral opacity-50"> // </span>
                                            <span :style="{ color: getStatColor(key) }">{{ formatStat(key, stat) }}</span>
                                        </template>
                                    </div>
                                </section>

                                <!-- WARDROBE -->
                                <section v-if="currentData?.wardrobe?.length">
                                    <h3 class="stat-header">{{ t('profileStats.wardrobe') }}</h3>
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
                                    <h3 class="stat-header">{{ t('profileStats.weapons') }}</h3>
                                    <div v-if="activeWeapon" class="mb-3 text-sm font-semibold">
                                        <span class="text-neutral">{{ t('profileStats.activeWeapon') }}</span>
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
                                    {{ t('profileStats.noGearData') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  ACCESSORIES TAB                                    -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'accessories'">
                        <div v-if="currentData?.accessories?.length">
                            <dl class="profile-stat-strip profile-stat-strip--accessories">
                                <div class="profile-stat-cell">
                                    <dt>{{ t('profileStats.uniqueAccessories') }}</dt>
                                    <dd>
                                        <span>{{ accessoryDisplay.unique }}</span>
                                        <template v-if="accessoryDisplay.uniqueMax != null">
                                            <span class="profile-stat-muted"> / </span>
                                            <span>{{ accessoryDisplay.uniqueMax }}</span>
                                            <span v-if="accessoryDisplay.uniquePct != null" class="profile-stat-pct">
                                                ({{ accessoryDisplay.uniquePct }}%)
                                            </span>
                                        </template>
                                    </dd>
                                </div>
                                <div class="profile-stat-cell">
                                    <dt>{{ t('profileStats.recombobulated') }}</dt>
                                    <dd>
                                        <span>{{ accessoryDisplay.recombobulated }}</span>
                                        <template v-if="accessoryDisplay.recombobMax != null">
                                            <span class="profile-stat-muted"> / </span>
                                            <span>{{ accessoryDisplay.recombobMax }}</span>
                                        </template>
                                    </dd>
                                </div>
                                <div v-if="accessoryDisplay.selectedPower" class="profile-stat-cell">
                                    <dt>{{ t('profileStats.selectedPower') }}</dt>
                                    <dd class="profile-stat-dd-profit">{{ accessoryDisplay.selectedPower }}</dd>
                                </div>
                                <div v-if="accessoryDisplay.magicalPower != null" class="profile-stat-cell">
                                    <dt>{{ t('profileStats.magicalPower') }}</dt>
                                    <dd class="profile-stat-dd-mythic">{{ accessoryDisplay.magicalPower }}</dd>
                                </div>
                            </dl>

                            <h3 class="stat-header">{{ t('profileStats.activeAccessories') }}</h3>
                            <div class="pieces">
                                <ItemSlot v-for="(item, i) in currentData.accessories" :key="i" :item="item" />
                            </div>
                        </div>
                        <div v-else class="text-neutral text-sm py-8 text-center">
                            {{ t('profileStats.noAccessoryData') }}
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  INVENTORY TAB (SkyBlock UI style with sub-tabs)      -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'inventory'" class="profile-inventory">
                        <!-- Inventory sub-tab headers -->
                        <div class="profile-pill-group mb-4" role="tablist" :aria-label="t('profileStats.tabInventory')">
                            <button
                                v-for="subTab in inventorySubTabs"
                                :key="subTab.id"
                                type="button"
                                role="tab"
                                :aria-selected="activeInventorySubTab === subTab.id"
                                @click="activeInventorySubTab = subTab.id; expandedBackpack = null; expandedEnderPage = null; expandedRiftEnderPage = null"
                                class="profile-pill-btn profile-pill-btn--icon"
                                :class="{ 'profile-pill-btn--active': activeInventorySubTab === subTab.id }"
                            >
                                <img v-if="subTab.icon" :src="subTab.icon" class="profile-pill-icon" loading="lazy" alt="" />
                                <span>{{ subTab.name }}</span>
                            </button>
                        </div>

                        <!-- BACKPACK / STORAGE sub-tab -->
                        <div v-if="activeInventorySubTab === 'backpack'">
                                <div v-if="backpackStorage.length > 0">
                                    <!-- Backpack cards (always visible) -->
                                    <div class="storage-cards">
                                        <button
                                            v-for="(bp, idx) in backpackStorage"
                                            :key="idx"
                                            type="button"
                                            class="storage-card"
                                                :class="{ 'storage-card-active': expandedBackpack === idx }"
                                                @click="expandedBackpack = expandedBackpack === idx ? null : idx">
                                            <img v-if="getItemTextureUrl(bp.icon)"
                                                 :src="getItemTextureUrl(bp.icon)"
                                                 class="storage-card-img" loading="lazy" draggable="false" />
                                            <div class="storage-card-info">
                                                <span class="storage-card-name">{{ bp.icon?.name || `${t('profileStats.backpack')} ${bp.slot + 1}` }}</span>
                                                <span class="storage-card-count">{{ bp.count }} {{ t('profileStats.items') }}</span>
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
                                    {{ t('profileStats.noBackpackData') }}
                                </div>
                            </div>

                            <!-- ENDERCHEST sub-tab (pages) -->
                            <div v-else-if="activeInventorySubTab === 'enderchest'">
                                <div v-if="enderchestPages.length > 0">
                                    <!-- Enderchest page cards (always visible) -->
                                    <div class="storage-cards">
                                        <button
                                            v-for="(page, idx) in enderchestPages"
                                            :key="idx"
                                            type="button"
                                            class="storage-card"
                                                :class="{ 'storage-card-active': expandedEnderPage === idx }"
                                                @click="expandedEnderPage = expandedEnderPage === idx ? null : idx">
                                            <img src="/img/textures/ender_chest.png"
                                                 class="storage-card-img" loading="lazy" draggable="false" />
                                            <div class="storage-card-info">
                                                <span class="storage-card-name">{{ t('profileStats.page') }} {{ idx + 1 }}</span>
                                                <span class="storage-card-count">{{ page.count }} {{ t('profileStats.items') }}</span>
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
                                    {{ t('profileStats.noEnderChestData') }}
                                </div>
                            </div>

                            <!-- RIFT ENDERCHEST sub-tab (pages) -->
                            <div v-else-if="activeInventorySubTab === 'rift_enderchest'">
                                <div v-if="riftEnderchestPages.length > 0">
                                    <!-- Rift enderchest page cards (always visible) -->
                                    <div class="storage-cards">
                                        <button
                                            v-for="(page, idx) in riftEnderchestPages"
                                            :key="idx"
                                            type="button"
                                            class="storage-card"
                                                :class="{ 'storage-card-active': expandedRiftEnderPage === idx }"
                                                @click="expandedRiftEnderPage = expandedRiftEnderPage === idx ? null : idx">
                                            <img src="/img/textures/ender_chest.png"
                                                 class="storage-card-img" loading="lazy" draggable="false" />
                                            <div class="storage-card-info">
                                                <span class="storage-card-name">{{ t('profileStats.page') }} {{ idx + 1 }}</span>
                                                <span class="storage-card-count">{{ page.count }} {{ t('profileStats.items') }}</span>
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
                                    {{ t('profileStats.noRiftEnderChestData') }}
                                </div>
                            </div>

                            <!-- MUSEUM sub-tab -->
                            <div v-else-if="activeInventorySubTab === 'museum'">
                                <div v-if="museumData.items?.length > 0 || museumData.special?.length > 0">
                                    <!-- Museum summary -->
                                    <div class="mb-4 space-y-1 text-sm">
                                        <div>
                                            <span class="text-neutral">{{ t('profileStats.museumValue') }}</span>
                                            <span class="text-legendary font-bold">{{ fNum(museumData.value) }} {{ t('profileStats.coins') }}</span>
                                        </div>
                                        <div>
                                            <span class="text-neutral">{{ t('profileStats.appraisal') }}</span>
                                            <span :class="museumData.appraisal ? 'text-profit' : 'text-loss'">
                                                {{ museumData.appraisal ? t('profileStats.unlocked') : t('profileStats.locked') }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-neutral">{{ t('profileStats.itemsDonated') }}</span>
                                            <span class="text-white font-bold">{{ museumData.items?.length ?? 0 }}</span>
                                        </div>
                                        <div v-if="museumData.special?.length > 0">
                                            <span class="text-neutral">{{ t('profileStats.specialItems') }}</span>
                                            <span class="text-white font-bold">{{ museumData.special.length }}</span>
                                        </div>
                                    </div>

                                    <!-- Museum items grid -->
                                    <div v-if="museumData.items?.length > 0">
                                        <h4 class="text-white text-sm font-bold mb-2">{{ t('profileStats.donatedItems') }}</h4>
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
                                        <h4 class="text-white text-sm font-bold mb-2">{{ t('profileStats.specialItemsTitle') }}</h4>
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
                                    {{ t('profileStats.noMuseumData') }}
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
                                        {{ t('profileStats.inventoryApiDisabled') }}
                                    </template>
                                    <template v-else>
                                        {{ t('profileStats.noInventoryData') }}
                                    </template>
                                </div>
                            </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  PETS TAB                                          -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'pets'">
                        <template v-if="currentData?.pets?.pets?.length > 0">
                            <!-- ── Stats header ─────────────────────────── -->
                            <dl class="profile-stat-strip profile-stat-strip--pets">
                                <div class="profile-stat-cell">
                                    <dt>{{ t('profileStats.uniquePets') }}</dt>
                                    <dd>{{ currentData.pets.amount }} / {{ currentData.pets.total }}</dd>
                                </div>
                                <div class="profile-stat-cell">
                                    <dt>{{ t('profileStats.uniquePetSkins') }}</dt>
                                    <dd>{{ currentData.pets.amountSkins }}</dd>
                                </div>
                                <div class="profile-stat-cell profile-stat-cell--wide">
                                    <dt>{{ t('profileStats.petScore') }}</dt>
                                    <dd>
                                        {{ currentData.pets.petScore?.total ?? 0 }}
                                        <span class="profile-stat-meta">
                                            (+{{ currentData.pets.petScore?.magicFind ?? 0 }} ✯ {{ t('profileStats.magicFind') }})
                                        </span>
                                    </dd>
                                </div>
                                <div class="profile-stat-cell">
                                    <dt>{{ t('profileStats.totalCandiesUsed') }}</dt>
                                    <dd>{{ (currentData.pets.totalCandy ?? 0).toLocaleString() }}</dd>
                                </div>
                                <div class="profile-stat-cell">
                                    <dt>{{ t('profileStats.totalPetXP') }}</dt>
                                    <dd>{{ fNum(currentData.pets.totalPetXp ?? 0) }}</dd>
                                </div>
                            </dl>

                            <!-- ── Active Pet ───────────────────────────── -->
                            <template v-if="activePet">
                                <h3 class="pets-section-title">{{ t('profileStats.activePet') }}</h3>
                                <div class="pets-active-section">
                                    <div class="pets-active-item">
                                        <ItemSlot :item="activePet" />
                                        <div class="pets-active-info">
                                            <span class="pets-active-name" :style="{ color: petTierColors[activePet.tier] || '#AAAAAA' }">
                                                {{ activePet.name }}
                                            </span>
                                            <p v-if="petBonusLine(activePet)" class="pets-active-bonus text-neutral text-sm">
                                                {{ t('profileStats.petBonus') }}: {{ petBonusLine(activePet) }}
                                            </p>
                                            <dl v-if="activePet.profile" class="pets-active-meta">
                                                <template v-if="activePet.profile.isMaxLevel">
                                                    <div class="pets-meta-row">
                                                        <dt>{{ t('profileStats.petMaxLevel') }}</dt>
                                                    </div>
                                                </template>
                                                <div v-else-if="activePet.profile.level" class="pets-meta-row">
                                                    <dt>{{ t('profileStats.level') }}</dt>
                                                    <dd>{{ activePet.profile.level }} / {{ activePet.profile.maxLevel }}</dd>
                                                </div>
                                                <div v-if="activePet.profile.heldItem?.name" class="pets-meta-row">
                                                    <dt>{{ t('profileStats.petHeldItem') }}</dt>
                                                    <dd>{{ activePet.profile.heldItem.name }}</dd>
                                                </div>
                                                <div v-if="activePet.profile.candyUsed > 0" class="pets-meta-row">
                                                    <dt>{{ t('profileStats.petCandy') }}</dt>
                                                    <dd>{{ activePet.profile.candyUsed }}</dd>
                                                </div>
                                                <div v-if="activePet.profile.perks?.length" class="pets-meta-row">
                                                    <dt>{{ t('profileStats.petPerks') }}</dt>
                                                    <dd>{{ activePet.profile.perks.map((p) => p.name).join(', ') }}</dd>
                                                </div>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- ── Other Pets (unique, not active) ──────── -->
                            <h3 class="pets-section-title">{{ t('profileStats.pets') }}</h3>
                            <div class="pets-grid">
                                <div v-for="(pet, i) in displayUniquePets" :key="'u-'+i" class="pets-grid-item">
                                    <ItemSlot :item="pet" />
                                    <span class="pets-level-label" :style="{ color: petTierColors[pet.tier] || '#AAAAAA' }">
                                        {{ t('profileStats.level') }} {{ pet.level?.level ?? '?' }}
                                    </span>
                                </div>
                            </div>

                            <!-- ── Show More Pets (duplicates) ──────────── -->
                            <template v-if="currentData.pets.otherPets?.length > 0">
                                <div class="pets-collapsible">
                                    <button
                                        type="button"
                                        class="pets-collapsible-btn"
                                        :aria-expanded="showMorePets"
                                        @click="showMorePets = !showMorePets"
                                    >
                                        <span class="pets-collapsible-chevron" :class="{ 'pets-collapsible-chevron--open': showMorePets }" aria-hidden="true" />
                                        <span class="pets-collapsible-label">{{ t('profileStats.showMorePets') }}</span>
                                        <span class="pets-collapsible-count">{{ currentData.pets.otherPets.length }}</span>
                                    </button>
                                    <div v-if="showMorePets" class="pets-grid" style="margin-top: 8px;">
                                        <div v-for="(pet, i) in currentData.pets.otherPets" :key="'o-'+i" class="pets-grid-item">
                                            <ItemSlot :item="pet" />
                                            <span class="pets-level-label" :style="{ color: petTierColors[pet.tier] || '#AAAAAA' }">
                                                {{ t('profileStats.level') }} {{ pet.level?.level ?? '?' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- ── Missing Pets ─────────────────────────── -->
                            <template v-if="currentData.pets.missing?.length > 0">
                                <div class="pets-collapsible">
                                    <button
                                        type="button"
                                        class="pets-collapsible-btn"
                                        :aria-expanded="showMissingPets"
                                        @click="showMissingPets = !showMissingPets"
                                    >
                                        <span class="pets-collapsible-chevron" :class="{ 'pets-collapsible-chevron--open': showMissingPets }" aria-hidden="true" />
                                        <span class="pets-collapsible-label">{{ t('profileStats.missingPets') }}</span>
                                        <span class="pets-collapsible-count">{{ currentData.pets.missing.length }}</span>
                                    </button>
                                    <div v-if="showMissingPets" class="pets-grid pets-missing-grid" style="margin-top: 8px;">
                                        <div v-for="(pet, i) in currentData.pets.missing" :key="'m-'+i" class="pets-grid-item pets-missing-item">
                                            <ItemSlot :item="pet" />
                                            <span class="pets-level-label" style="color: #555;">
                                                {{ pet.name }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </template>
                        <div v-else class="text-neutral text-sm py-8 text-center">{{ t('profileStats.noPetData') }}</div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  SKILLS TAB                                        -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'skills'">
                        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
                            <div class="hidden lg:block w-52 shrink-0">
                                <div class="sticky top-20">
                                    <div class="player-name-rank">
                                        <template v-if="profileData?.rank?.prefix">
                                            <span class="rank-prefix" :style="{ color: profileData.rank.color }">
                                                {{ rankTextBefore }}<!--
                                            --><span v-if="rankPlusText" class="rank-plus" :style="{ color: profileData.rank.plusColor }">{{ rankPlusText }}</span><!--
                                            --><span v-if="rankTextAfter" :style="{ color: profileData.rank.color }">{{ rankTextAfter }}</span>
                                            </span>
                                            <span class="player-username" :style="{ color: profileData.rank.color }">{{ profileData.username }}</span>
                                        </template>
                                        <span v-else class="player-username" style="color: #AAAAAA">{{ profileData?.username }}</span>
                                    </div>
                                    <ProfilePlayerPreview :uuid="profileData?.uuid" :width="208" :height="400" />
                                </div>
                            </div>

                            <div class="flex flex-col items-center lg:hidden">
                                <div class="player-name-rank">
                                    <template v-if="profileData?.rank?.prefix">
                                        <span class="rank-prefix" :style="{ color: profileData.rank.color }">
                                            {{ rankTextBefore }}<!--
                                        --><span v-if="rankPlusText" class="rank-plus" :style="{ color: profileData.rank.plusColor }">{{ rankPlusText }}</span><!--
                                        --><span v-if="rankTextAfter" :style="{ color: profileData.rank.color }">{{ rankTextAfter }}</span>
                                        </span>
                                        <span class="player-username" :style="{ color: profileData.rank.color }">{{ profileData.username }}</span>
                                    </template>
                                    <span v-else class="player-username" style="color: #AAAAAA">{{ profileData?.username }}</span>
                                </div>
                                <ProfilePlayerPreview :uuid="profileData?.uuid" :width="144" :height="280" />
                            </div>

                            <div class="flex-1 min-w-0 ps-skills-panel">
                                <!-- SkyBlock Level (full width) -->
                                <div
                                    v-if="currentData?.skyblock_level"
                                    class="ps-skill-row ps-skill-row--level ps-skill-row--gold"
                                >
                                    <div class="ps-skill-row__label">
                                        {{ t('profileStats.level') }}
                                        <span class="ps-skill-row__lvl">{{ currentData.skyblock_level.level }}</span>
                                    </div>
                                    <div class="ps-skill-row__bar-row">
                                        <div class="ps-skill-icon-ring">
                                            <img
                                                v-if="getItemTextureUrl(SB_LEVEL_TEXTURE_ITEM)"
                                                :src="getItemTextureUrl(SB_LEVEL_TEXTURE_ITEM)"
                                                class="ps-skill-icon-img"
                                                alt=""
                                                loading="lazy"
                                                draggable="false"
                                            />
                                            <span v-else class="ps-skill-icon-emoji" aria-hidden="true">✫</span>
                                        </div>
                                        <div class="ps-skill-pill">
                                            <div
                                                class="ps-skill-pill-fill"
                                                :style="{ width: (currentData.skyblock_level.progress * 100) + '%' }"
                                            />
                                            <span class="ps-skill-pill-xp">{{ formatSkyblockLevelXP(currentData.skyblock_level) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="ps-skill-bars-grid">
                                    <div
                                        v-for="skill in allSkills"
                                        :key="skill.name"
                                        class="ps-skill-row"
                                        :class="skillRowAccentClass(skill)"
                                    >
                                        <div class="ps-skill-row__label">
                                            {{ capitalize(skill.name) }}
                                            <span class="ps-skill-row__lvl">{{ skillIsMaxed(skill) ? skillLevelCap(skill) : skill.level }}</span>
                                        </div>
                                        <div class="ps-skill-row__bar-row">
                                            <div class="ps-skill-icon-ring">
                                                <img
                                                    v-if="skillTextureUrl(skill.name)"
                                                    :src="skillTextureUrl(skill.name)"
                                                    class="ps-skill-icon-img"
                                                    alt=""
                                                    loading="lazy"
                                                    draggable="false"
                                                />
                                                <span v-else class="ps-skill-icon-emoji" aria-hidden="true">{{ SKILL_ICONS[skill.name] || '❓' }}</span>
                                            </div>
                                            <div class="ps-skill-pill">
                                                <div
                                                    class="ps-skill-pill-fill"
                                                    :style="{ width: (skillBarProgress(skill) * 100) + '%' }"
                                                />
                                                <span class="ps-skill-pill-xp">{{ formatXP(skill) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Player Stats -->
                                <div v-if="currentData?.player_stats?.length" class="stats-section">
                                    <div class="stats-grid ps-stats-grid">
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
                    <!--  SLAYER TAB  (SkyBlock UI style)                      -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'slayer'">
                        <div v-if="slayerData && Object.keys(slayerData.slayers).length > 0">
                            <!-- Total Slayer XP -->
                            <div class="mb-4 text-sm text-neutral">
                                {{ t('profileStats.totalSlayerXP') }} <b class="text-white">{{ Number(slayerData.total_slayer_xp).toLocaleString() }}</b>
                            </div>

                            <div class="slayer-cards-grid">
                                <div
                                    v-for="(slayer, key) in slayerData.slayers"
                                    :key="key"
                                    class="slayer-card"
                                >
                                    <div class="slayer-header">
                                        <img
                                            v-if="slayerTextureUrl(key)"
                                            :src="slayerTextureUrl(key)"
                                            class="slayer-header-icon"
                                            alt=""
                                            loading="lazy"
                                            draggable="false"
                                        />
                                        <span v-else class="slayer-header-emoji" aria-hidden="true">{{ SLAYER_ICONS[key] || '💀' }}</span>
                                        <span class="slayer-boss-name">{{ slayer.name }}</span>
                                    </div>

                                    <div class="slayer-body">
                                        <div class="slayer-tiers">
                                            <div
                                                v-for="tier in slayerMaxTier(key)"
                                                :key="tier"
                                                class="slayer-tier-col"
                                            >
                                                <span class="slayer-tier-label">{{ t('profileStats.tier') }} {{ romanNumeral(tier) }}</span>
                                                <span class="slayer-tier-value">{{ (slayer.kills?.[tier] ?? 0).toLocaleString() }}</span>
                                            </div>
                                            <div class="slayer-tier-col">
                                                <span class="slayer-tier-label">{{ t('profileStats.total') }}</span>
                                                <span class="slayer-tier-value">{{ (slayer.total_kills ?? 0).toLocaleString() }}</span>
                                            </div>
                                        </div>

                                        <div class="slayer-level-label">
                                            {{ capitalize(key) }} {{ t('profileStats.level') }} {{ slayer.level?.currentLevel ?? 0 }}
                                        </div>
                                    </div>

                                    <div class="slayer-xp-bar-track">
                                        <div
                                            class="slayer-xp-bar-fill"
                                            :class="slayerBarFillClass(slayer)"
                                            :style="{
                                                width: (
                                                    slayer.level?.currentLevel >= slayer.level?.maxLevel
                                                        ? 100
                                                        : (slayer.level?.progress ?? 0) * 100
                                                ) + '%',
                                            }"
                                        />
                                        <span class="slayer-xp-bar-text">{{ formatSlayerXP(slayer) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-neutral text-sm py-8 text-center">{{ t('profileStats.noSlayerData') }}</div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  DUNGEONS TAB                                      -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'dungeons'">
                        <div v-if="currentData?.dungeons && Object.keys(currentData.dungeons).length > 0" class="ps-skills-panel">
                            <!-- ── Dungeon summary info ── -->
                            <dl class="profile-stat-strip profile-stat-strip--summary profile-stat-strip--dungeons">
                                <div class="profile-stat-cell">
                                    <dt>{{ t('profileStats.selectedClass') }}</dt>
                                    <dd>{{ capitalize(currentData.dungeons.selected_class || t('profileStats.none')) }}</dd>
                                </div>
                                <div class="profile-stat-cell">
                                    <dt :class="{ 'text-gold': allClassesMaxed }">{{ t('profileStats.classAverage') }}</dt>
                                    <dd :class="{ 'text-gold': allClassesMaxed }">{{ currentData.dungeons.class_average?.toFixed(2) ?? '0.00' }}</dd>
                                </div>
                                <div class="profile-stat-cell">
                                    <dt :class="{ 'text-gold': currentData.dungeons.highest_floor === 7 }">{{ t('profileStats.highestFloorNormal') }}</dt>
                                    <dd :class="{ 'text-gold': currentData.dungeons.highest_floor === 7 }">
                                        {{ currentData.dungeons.highest_floor !== null ? currentData.dungeons.highest_floor : '—' }}
                                    </dd>
                                </div>
                                <div v-if="currentData.dungeons.highest_master !== null" class="profile-stat-cell">
                                    <dt :class="{ 'text-gold': currentData.dungeons.highest_master === 7 }">{{ t('profileStats.highestFloorMaster') }}</dt>
                                    <dd :class="{ 'text-gold': currentData.dungeons.highest_master === 7 }">{{ currentData.dungeons.highest_master }}</dd>
                                </div>
                                <div class="profile-stat-cell">
                                    <dt>{{ t('profileStats.secretsFound') }}</dt>
                                    <dd>
                                        {{ (currentData.dungeons.secrets_found ?? 0).toLocaleString() }}
                                        <span class="profile-stat-meta">
                                            ({{ currentData.dungeons.secrets_per_run ?? 0 }} {{ t('profileStats.secretsPerRun') }})
                                        </span>
                                    </dd>
                                </div>
                            </dl>

                            <!-- ── Catacombs + class levels (2-col grid, screenshot layout) ── -->
                            <div v-if="dungeonDisplayRows.length" class="ps-skill-bars-grid ps-skill-bars-grid--dungeons">
                                <div
                                    v-for="row in dungeonDisplayRows"
                                    :key="row.key"
                                    class="ps-skill-row"
                                    :class="dungeonRowAccentClass(row)"
                                >
                                    <div class="ps-skill-row__label">
                                        {{ row.name }}
                                        <span class="ps-skill-row__lvl">{{ row.level >= row.maxLevel ? row.maxLevel : row.level }}</span>
                                    </div>
                                    <div class="ps-skill-row__bar-row">
                                        <div class="ps-skill-icon-ring">
                                            <img
                                                v-if="dungeonTextureUrl(row.key)"
                                                :src="dungeonTextureUrl(row.key)"
                                                class="ps-skill-icon-img"
                                                alt=""
                                                loading="lazy"
                                                draggable="false"
                                            />
                                            <span v-else class="ps-skill-icon-emoji" aria-hidden="true">{{ CLASS_ICONS[row.key] || '💀' }}</span>
                                        </div>
                                        <div class="ps-skill-pill">
                                            <div
                                                class="ps-skill-pill-fill"
                                                :style="{
                                                    width: (row.level >= row.maxLevel ? 100 : row.progress * 100) + '%',
                                                }"
                                            />
                                            <span class="ps-skill-pill-xp">{{ row.xpLabel }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Normal Catacombs floors ── -->
                            <div v-if="currentData.dungeons.floors?.length" class="dungeon-floors-section">
                                <h3 class="dungeon-section-title">{{ t('profileStats.catacombs') }}</h3>
                                <div class="dungeon-floor-grid">
                                    <div v-for="floor in currentData.dungeons.floors" :key="'f'+floor.index" class="dungeon-floor-card">
                                        <div class="dungeon-floor-header">
                                            <span class="dungeon-floor-name">{{ floor.name.toUpperCase() }}</span>
                                        </div>
                                        <div class="dungeon-floor-body">
                                            <!-- Floor Stats -->
                                            <details v-if="Object.keys(floor.stats).length">
                                                <summary class="dungeon-details-toggle">{{ t('profileStats.floorStats') }}</summary>
                                                <div class="dungeon-details-content">
                                                    <div v-for="(val, key) in floor.stats" :key="key" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ formatStatName(key) }}:</span>
                                                        <span class="dungeon-detail-value">{{ formatFloorStat(key, val) }}</span>
                                                    </div>
                                                    <div v-if="floor.most_damage" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.mostDamage') }}</span>
                                                        <span class="dungeon-detail-value">{{ fNum(floor.most_damage.value) }} <span class="dungeon-stat-note">({{ capitalize(floor.most_damage.class) }})</span></span>
                                                    </div>
                                                </div>
                                            </details>
                                            <!-- Best Run -->
                                            <details v-if="floor.best_run">
                                                <summary class="dungeon-details-toggle">{{ t('profileStats.bestRun') }}</summary>
                                                <div class="dungeon-details-content">
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.grade') }}</span>
                                                        <span class="dungeon-detail-value dungeon-grade" :class="'grade-' + floor.best_run.grade?.replace('+','plus')">{{ floor.best_run.grade }}</span>
                                                    </div>
                                                    <div v-if="floor.best_run.timestamp" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.timestamp') }}</span>
                                                        <span class="dungeon-detail-value">{{ timeAgo(floor.best_run.timestamp) }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.scoreExploration') }}</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.score_exploration }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.scoreSpeed') }}</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.score_speed }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.scoreSkill') }}</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.score_skill }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.scoreBonus') }}</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.score_bonus }}</span>
                                                    </div>
                                                    <div v-if="floor.best_run.dungeon_class" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.dungeonClass') }}</span>
                                                        <span class="dungeon-detail-value">{{ capitalize(floor.best_run.dungeon_class) }}</span>
                                                    </div>
                                                    <div v-if="floor.best_run.elapsed_time" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.elapsedTime') }}</span>
                                                        <span class="dungeon-detail-value">{{ formatElapsed(floor.best_run.elapsed_time) }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.damageDealt') }}</span>
                                                        <span class="dungeon-detail-value">{{ fNum(floor.best_run.damage_dealt) }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.deaths') }}</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.deaths }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.mobsKilled') }}</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.mobs_killed }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.secretsFound') }}</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.secrets_found }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.damageMitigated') }}</span>
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
                                <h3 class="dungeon-section-title">{{ t('profileStats.masterCatacombs') }}</h3>
                                <div class="dungeon-floor-grid">
                                    <div v-for="floor in currentData.dungeons.master_floors" :key="'m'+floor.index" class="dungeon-floor-card dungeon-floor-master">
                                        <div class="dungeon-floor-header">
                                            <span class="dungeon-floor-name">{{ floor.name.toUpperCase() }}</span>
                                        </div>
                                        <div class="dungeon-floor-body">
                                            <details v-if="Object.keys(floor.stats).length">
                                                <summary class="dungeon-details-toggle">{{ t('profileStats.floorStats') }}</summary>
                                                <div class="dungeon-details-content">
                                                    <div v-for="(val, key) in floor.stats" :key="key" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ formatStatName(key) }}:</span>
                                                        <span class="dungeon-detail-value">{{ formatFloorStat(key, val) }}</span>
                                                    </div>
                                                    <div v-if="floor.most_damage" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.mostDamage') }}</span>
                                                        <span class="dungeon-detail-value">{{ fNum(floor.most_damage.value) }} <span class="dungeon-stat-note">({{ capitalize(floor.most_damage.class) }})</span></span>
                                                    </div>
                                                </div>
                                            </details>
                                            <details v-if="floor.best_run">
                                                <summary class="dungeon-details-toggle">{{ t('profileStats.bestRun') }}</summary>
                                                <div class="dungeon-details-content">
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.grade') }}</span>
                                                        <span class="dungeon-detail-value dungeon-grade" :class="'grade-' + floor.best_run.grade?.replace('+','plus')">{{ floor.best_run.grade }}</span>
                                                    </div>
                                                    <div v-if="floor.best_run.timestamp" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.timestamp') }}</span>
                                                        <span class="dungeon-detail-value">{{ timeAgo(floor.best_run.timestamp) }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.scoreExploration') }}</span><span class="dungeon-detail-value">{{ floor.best_run.score_exploration }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.scoreSpeed') }}</span><span class="dungeon-detail-value">{{ floor.best_run.score_speed }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.scoreSkill') }}</span><span class="dungeon-detail-value">{{ floor.best_run.score_skill }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.scoreBonus') }}</span><span class="dungeon-detail-value">{{ floor.best_run.score_bonus }}</span></div>
                                                    <div v-if="floor.best_run.dungeon_class" class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.dungeonClass') }}</span><span class="dungeon-detail-value">{{ capitalize(floor.best_run.dungeon_class) }}</span></div>
                                                    <div v-if="floor.best_run.elapsed_time" class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.elapsedTime') }}</span><span class="dungeon-detail-value">{{ formatElapsed(floor.best_run.elapsed_time) }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.damageDealt') }}</span><span class="dungeon-detail-value">{{ fNum(floor.best_run.damage_dealt) }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.deaths') }}</span><span class="dungeon-detail-value">{{ floor.best_run.deaths }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.mobsKilled') }}</span><span class="dungeon-detail-value">{{ floor.best_run.mobs_killed }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.secretsFound') }}</span><span class="dungeon-detail-value">{{ floor.best_run.secrets_found }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.damageMitigated') }}</span><span class="dungeon-detail-value">{{ fNum(floor.best_run.damage_mitigated) }}</span></div>
                                                </div>
                                            </details>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-neutral text-sm py-8 text-center">{{ t('profileStats.noDungeonData') }}</div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  COLLECTIONS TAB                                   -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'collections'">
                        <div v-if="collectionsData && Object.keys(collectionsData.categories).length > 0">
                            <!-- Maxed summary -->
                            <div class="mb-4 text-sm text-neutral">
                                {{ t('profileStats.maxedCollections') }} <b class="text-white">{{ collectionsData.maxedCollections }}</b> / {{ collectionsData.totalCollections }}
                            </div>

                            <!-- Category sections -->
                            <div class="space-y-5">
                                <div v-for="(cat, catId) in collectionsData.categories" :key="catId" class="collection-category">
                                    <!-- Category header -->
                                    <div class="collection-category-header">
                                        <span class="text-base">{{ COLLECTION_CATEGORY_ICONS[catId] || '📦' }}</span>
                                        <span class="collection-category-name">{{ cat.name }}</span>
                                        <span v-if="cat.maxedTiers >= cat.totalTiers" class="collection-max-badge">{{ t('profileStats.maxLabel') }}</span>
                                        <span v-else class="collection-category-count">({{ cat.maxedTiers }} / {{ cat.totalTiers }} {{ t('profileStats.maxShort') }})</span>
                                    </div>

                                    <!-- Collection items flow -->
                                    <div class="collection-items-grid">
                                        <div v-for="item in cat.collections" :key="item.id"
                                             class="collection-row-card"
                                             :class="{ 'collection-row-card--locked': !item.unlocked || item.amount === 0 }">
                                            <div class="collection-row-icon-wrap"
                                                 :class="{ 'collection-row-icon-wrap--muted': !item.unlocked || item.amount === 0 }">
                                                <img v-if="getItemTextureUrl(collectionTextureItem(item))"
                                                     :src="getItemTextureUrl(collectionTextureItem(item))"
                                                     class="collection-row-icon"
                                                     alt=""
                                                     loading="lazy"
                                                     draggable="false" />
                                                <div v-else class="collection-row-icon collection-row-icon--placeholder" aria-hidden="true" />
                                            </div>
                                            <div class="collection-row-text">
                                                <div class="collection-row-titleline">
                                                    <span class="collection-row-name"
                                                          :class="(item.unlocked && item.amount > 0) ? 'collection-row-name--active' : 'collection-row-name--muted'">
                                                        {{ item.name }}
                                                    </span>
                                                    <span class="collection-row-tiernum"
                                                          :class="(item.unlocked && item.amount > 0) ? 'collection-row-tiernum--active' : 'collection-row-tiernum--muted'">
                                                        {{ item.tier }}
                                                    </span>
                                                </div>
                                                <div class="collection-row-amountline">
                                                    <span class="collection-row-amount-label">{{ t('profileStats.collectionAmount') }}</span>
                                                    <span class="collection-row-amount-value"
                                                          :class="{ 'collection-row-amount-value--muted': !item.unlocked || item.amount === 0 }">
                                                        {{ Number(item.amount ?? 0).toLocaleString() }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-neutral text-sm py-8 text-center">{{ t('profileStats.noCollectionData') }}</div>
                    </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Strong blur + light tint; explicit -webkit- for Safari. */
.profile-stats-bg-scrim {
    opacity: 0;
    -webkit-backdrop-filter: blur(20px);
    backdrop-filter: blur(20px);
    transition:
        opacity 1000ms ease-out,
        background-color 1000ms ease-out;
}

.profile-stats-bg-scrim--visible {
    opacity: 1;
}

.profile-stats-bg-scrim--hero {
    background-color: rgba(0, 0, 0, 0.14);
}

.profile-stats-bg-scrim--loaded {
    background-color: rgba(0, 0, 0, 0.055);
}

@media (prefers-reduced-motion: reduce) {
    .profile-stats-bg-scrim {
        transition: background-color 0.2s ease;
        -webkit-backdrop-filter: blur(14px);
        backdrop-filter: blur(14px);
    }
}
</style>
