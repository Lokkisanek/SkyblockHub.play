/**
 * Texture utility for SkyBlock items / skills.
 *
 * Uses multiple texture sources with fallback chain:
 *   1. Local textures:  /img/textures/{name}.png (MC 1.8 item textures)
 *   2. Skull textures:  https://mc-heads.net/head/{hash}/64
 *   3. Player skins:    https://mc-heads.net/body/{uuid}
 *   4. Player heads:    https://mc-heads.net/avatar/{uuid}
 */

/* ── Rarity → color ──────────────────────────────────── */
export const RARITY_COLORS = {
    COMMON:       '#AAAAAA',
    UNCOMMON:     '#55FF55',
    RARE:         '#5555FF',
    EPIC:         '#AA00AA',
    LEGENDARY:    '#FFAA00',
    MYTHIC:       '#FF55FF',
    DIVINE:       '#55FFFF',
    SPECIAL:      '#FF5555',
    VERY_SPECIAL: '#FF5555',
};

export const RARITY_BG = {
    COMMON:       'rgba(170,170,170,0.10)',
    UNCOMMON:     'rgba(85,255,85,0.10)',
    RARE:         'rgba(85,85,255,0.10)',
    EPIC:         'rgba(170,0,170,0.10)',
    LEGENDARY:    'rgba(255,170,0,0.10)',
    MYTHIC:       'rgba(255,85,255,0.10)',
    DIVINE:       'rgba(85,255,255,0.10)',
    SPECIAL:      'rgba(255,85,85,0.10)',
    VERY_SPECIAL: 'rgba(255,85,85,0.10)',
};

/* ── Skill icons ─────────────────────────────────────── */
export const SKILL_ICONS = {
    farming:      '🌾',
    mining:       '⛏️',
    combat:       '⚔️',
    foraging:     '🌲',
    fishing:      '🎣',
    enchanting:   '📖',
    alchemy:      '⚗️',
    carpentry:    '🪓',
    taming:       '🐾',
    runecrafting: '🔮',
    social:       '🎉',
    hunting:      '🪶',
};

/* ── Slayer icons ────────────────────────────────────── */
export const SLAYER_ICONS = {
    zombie:   '🧟',
    spider:   '🕷️',
    wolf:     '🐺',
    enderman: '🟣',
    blaze:    '🔥',
    vampire:  '🧛',
};

/* ── Dungeon class icons ─────────────────────────────── */
export const CLASS_ICONS = {
    healer:  '💚',
    mage:    '🔮',
    berserk: '⚔️',
    archer:  '🏹',
    tank:    '🛡️',
};

/**
 * Map our backend item names → local MC 1.8 texture file names.
 * MC 1.8 uses "gold_" not "golden_", "wood_" not "wooden_", etc.
 */
const TEXTURE_NAME_MAP = {
    // Swords
    golden_sword: 'gold_sword', wooden_sword: 'wood_sword',
    // Pickaxes
    golden_pickaxe: 'gold_pickaxe', wooden_pickaxe: 'wood_pickaxe',
    // Axes
    golden_axe: 'gold_axe', wooden_axe: 'wood_axe',
    // Shovels
    golden_shovel: 'gold_shovel', wooden_shovel: 'wood_shovel',
    // Hoes
    golden_hoe: 'gold_hoe', wooden_hoe: 'wood_hoe',
    // Armor (gold → gold)
    golden_helmet: 'gold_helmet', golden_chestplate: 'gold_chestplate',
    golden_leggings: 'gold_leggings', golden_boots: 'gold_boots',
    // Horse armor
    golden_horse_armor: 'gold_horse_armor',
    // Gold food
    golden_apple: 'apple_golden', golden_carrot: 'carrot_golden',
    // Fishing rod
    fishing_rod: 'fishing_rod_uncast',
    // Bow
    bow: 'bow_standby',
    // Food
    raw_porkchop: 'porkchop_raw', cooked_porkchop: 'porkchop_cooked',
    raw_beef: 'beef_raw', cooked_beef: 'beef_cooked',
    raw_chicken: 'chicken_raw', cooked_chicken: 'chicken_cooked',
    raw_fish: 'fish_cod_raw', cooked_fish: 'fish_cod_cooked',
    raw_salmon: 'fish_salmon_raw', cooked_salmon: 'fish_salmon_cooked',
    clownfish: 'fish_clownfish_raw', pufferfish: 'fish_pufferfish_raw',
    raw_rabbit: 'rabbit_raw', cooked_rabbit: 'rabbit_cooked',
    raw_mutton: 'mutton_raw', cooked_mutton: 'mutton_cooked',
    baked_potato: 'potato_baked',
    melon_slice: 'melon',
    // Enchanted book
    enchanted_book: 'book_enchanted',
    // Seeds
    wheat_seeds: 'seeds_wheat', pumpkin_seeds: 'seeds_pumpkin', melon_seeds: 'seeds_melon',
    // Bottles
    glass_bottle: 'potion_bottle_empty',
    potion: 'potion_bottle_drinkable',
    bottle_o_enchanting: 'experience_bottle',
    glistering_melon_slice: 'melon_speckled',
    // Maps
    empty_map: 'map_empty', filled_map: 'map_filled',
    // Redstone
    redstone: 'redstone_dust',
    redstone_repeater: 'repeater',
    redstone_comparator: 'comparator',
    // Books
    book: 'book_normal',
    writable_book: 'book_writable',
    written_book: 'book_written',
    // Misc
    bucket: 'bucket_empty',
    minecart: 'minecart_normal',
    sugar_cane: 'reeds',
    slime_ball: 'slimeball',
    fire_charge: 'fireball',
    firework_rocket: 'fireworks',
    firework_star: 'fireworks_charge',
    nether_brick: 'netherbrick',
    lead: 'lead',
    armor_stand: 'wooden_armorstand',
    fermented_spider_eye: 'spider_eye_fermented',
    poisonous_potato: 'potato_poisonous',
    oak_door: 'door_wood', iron_door: 'door_iron',
    // Dyes
    ink_sac: 'dye_powder_black',
    red_dye: 'dye_powder_red',
    green_dye: 'dye_powder_green',
    cocoa_beans: 'dye_powder_brown',
    lapis_lazuli: 'dye_powder_blue',
    purple_dye: 'dye_powder_purple',
    cyan_dye: 'dye_powder_cyan',
    light_gray_dye: 'dye_powder_silver',
    gray_dye: 'dye_powder_gray',
    pink_dye: 'dye_powder_pink',
    lime_dye: 'dye_powder_lime',
    yellow_dye: 'dye_powder_yellow',
    light_blue_dye: 'dye_powder_light_blue',
    magenta_dye: 'dye_powder_magenta',
    orange_dye: 'dye_powder_orange',
    bone_meal: 'dye_powder_white',
    // Skulls
    skull: 'skull_skeleton',
    wither_skeleton_skull: 'skull_wither',
    zombie_head: 'skull_zombie', player_head: 'skull_char', creeper_head: 'skull_creeper',
};

/* ── Resource Pack System ─────────────────────────────── */

/**
 * Pack index storage. Each entry: { id, index (json data), priority }
 * Loaded on demand from /resourcepacks/{folder}/index.json
 */
const loadedPacks = new Map();  // packId → index data
let enabledPackIds = [];        // ordered list of enabled pack IDs

const PACK_FOLDERS = {
    FURFSKY_REBORN: 'FurfSky_Reborn',
    HYPIXELPLUS:    'Hypixel_Plus',
};

/**
 * Load a pack's index.json and cache it.
 */
async function loadPackIndex(packId) {
    if (loadedPacks.has(packId)) return loadedPacks.get(packId);

    const folder = PACK_FOLDERS[packId];
    if (!folder) return null;

    try {
        const res = await fetch(`/resourcepacks/${folder}/index.json`);
        if (!res.ok) return null;
        const data = await res.json();
        loadedPacks.set(packId, data);
        return data;
    } catch {
        return null;
    }
}

/**
 * Set enabled packs and preload their indices.
 * Called by PackSelector when packs change.
 */
export async function setEnabledPacks(packIds) {
    enabledPackIds = [...packIds];
    // Sort by priority (highest first)
    const packPriorities = { FURFSKY_REBORN: 250, HYPIXELPLUS: 125 };
    enabledPackIds.sort((a, b) => (packPriorities[b] ?? 0) - (packPriorities[a] ?? 0));

    // Preload all enabled pack indices in parallel
    await Promise.all(enabledPackIds.map(id => loadPackIndex(id)));
}

/**
 * Try to resolve an item texture from resource packs.
 * Checks: skyblock_id (exact) → skyblock_id (patterns) → item_id:damage
 * Returns the pack texture path (e.g. /resourcepacks/FurfSky_Reborn/assets/...png) or null.
 */
function resolvePackTexture(item) {
    if (enabledPackIds.length === 0) return null;

    for (const packId of enabledPackIds) {
        const index = loadedPacks.get(packId);
        if (!index) continue;

        // 1. Exact skyblock_id match (most common, covers ~90% of SkyBlock items)
        if (item.skyblock_id && index.skyblock_id?.[item.skyblock_id]) {
            return index.skyblock_id[item.skyblock_id].path;
        }

        // 2. Item ID + damage match (for vanilla items with pack textures)
        if (item.minecraft_id !== undefined && item.minecraft_id !== null) {
            const key = `${item.minecraft_id}:${item.damage ?? 0}`;
            if (index.item_id?.[key]) {
                return index.item_id[key].path;
            }
            // Try without damage
            const key0 = `${item.minecraft_id}:0`;
            if (item.damage !== 0 && index.item_id?.[key0]) {
                return index.item_id[key0].path;
            }
        }

        // 3. Skull texture value match
        if (item.texture_hash && index.texture_value) {
            // Check if any texture_value entry matches our hash
            for (const [value, entry] of Object.entries(index.texture_value)) {
                if (value === item.texture_hash) {
                    return entry.path;
                }
            }
        }
    }

    return null;
}

/* ── Player textures (via mc-heads.net) ──────────────── */

export function getSkinUrl(uuid) {
    if (!uuid) return '';
    return `https://mc-heads.net/body/${uuid}/256`;
}

export function getHeadUrl(uuid, size = 32) {
    if (!uuid) return '';
    return `https://mc-heads.net/avatar/${uuid}/${size}`;
}

/* ── Item textures ───────────────────────────────────── */

/**
 * Get vanilla texture URL from backend texture_path.
 */
function getVanillaTextureUrl(item) {
    if (!item?.texture_path) return null;

    const path = item.texture_path;

    // Skull heads: /head/{hash}
    if (path.startsWith('/head/')) {
        const hash = path.substring(6);
        return `https://mc-heads.net/head/${hash}/64`;
    }

    // Vanilla items: /item/{name} → local texture file
    if (path.startsWith('/item/')) {
        const name = path.substring(6);
        const textureName = TEXTURE_NAME_MAP[name] || name;
        return `/img/textures/${textureName}.png`;
    }

    // Leather armor: /leather/{type}/{color}
    if (path.startsWith('/leather/')) {
        const parts = path.split('/');
        const type = parts[2];
        return `/img/textures/leather_${type}.png`;
    }

    // Potions: /potion/{type}/{color}
    if (path.startsWith('/potion/')) {
        const parts = path.split('/');
        const type = parts[2];
        return type === 'splash'
            ? '/img/textures/potion_bottle_splash.png'
            : '/img/textures/potion_bottle_drinkable.png';
    }

    return null;
}

/**
 * Get a renderable texture URL for an item.
 * Resolution order:
 *   1. Resource pack textures (FurfSky Reborn, Hypixel Plus, etc.)
 *   2. Vanilla texture from backend texture_path
 *   3. Skull texture fallback via texture_hash
 */
export function getItemTextureUrl(item) {
    if (!item) return null;

    // 1. Try resource packs first (if any enabled)
    const packTexture = resolvePackTexture(item);
    if (packTexture) return packTexture;

    // 2. Vanilla texture resolution
    const vanillaUrl = getVanillaTextureUrl(item);
    if (vanillaUrl) return vanillaUrl;

    // 3. Legacy fallback: skull items with texture_hash only
    if (item.texture_hash) {
        return `https://mc-heads.net/head/${item.texture_hash}/64`;
    }

    return null;
}

/**
 * Get the rarity border/text color.
 */
export function getRarityColor(rarity) {
    return RARITY_COLORS[rarity] ?? '#AAAAAA';
}

export function getRarityBg(rarity) {
    return RARITY_BG[rarity] ?? 'rgba(170,170,170,0.10)';
}

/**
 * Preload enabled resource pack indices.
 */
export async function preloadAllTextures() {
    // Load default packs from localStorage
    const STORAGE_KEY = 'skyblock_texture_packs';
    let packIds;
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        packIds = stored ? JSON.parse(stored) : ['FURFSKY_REBORN'];
    } catch {
        packIds = ['FURFSKY_REBORN'];
    }
    await setEnabledPacks(packIds);
    return true;
}
