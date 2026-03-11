<script setup>
/**
 * ItemSlot — renders a single SkyBlock item as a SkyCrypt-style "piece":
 *   - Rarity-colored background
 *   - Centered pixelated item texture with drop-shadow
 *   - Animated shine overlay for common→legendary
 *   - White hover overlay
 *   - MC-style lore tooltip (SkyCrypt rendering with CSS variables)
 *   - Click-to-pin tooltip for text selection / copying
 */
import { computed, ref, watch, inject, nextTick, onBeforeUnmount } from 'vue';
import { getItemTextureUrl, getColoredLeatherUrl } from '@/utils/textures';

const props = defineProps({
    item: { type: Object, default: null },
});

const showTooltip = ref(false);
const pinned      = ref(false);
const tooltipPos  = ref({ x: 0, y: 0 });
const tooltipRef  = ref(null);
const textureVersion = inject('textureVersion', ref(0));

const textureUrl = computed(() => {
    void textureVersion.value;
    return getItemTextureUrl(props.item);
});

/* Leather armor: canvas-colored texture (SkyCrypt approach) */
const coloredLeatherUrl = ref(null);

watch(() => [props.item?.texture_path, props.item?.color], () => {
    const path = props.item?.texture_path;
    if (!path || !path.startsWith('/leather/')) {
        coloredLeatherUrl.value = null;
        return;
    }
    const parts = path.split('/');
    const type = parts[2];
    const color = parts[3] ? '#' + parts[3] : (props.item?.color || null);
    if (!type || !color) {
        coloredLeatherUrl.value = null;
        return;
    }
    getColoredLeatherUrl(type, color).then(url => {
        coloredLeatherUrl.value = url;
    });
}, { immediate: true });

/* Final display URL: colored leather > pack/vanilla texture */
const displayUrl = computed(() => coloredLeatherUrl.value || textureUrl.value);

/* Rarity → CSS class for background */
const rarityBgClass = computed(() => {
    if (!props.item?.rarity) return '';
    return 'piece-bg-' + props.item.rarity.toLowerCase().replace(/ /g, '_');
});

/* Rarity → foreground CSS class */
const rarityFgClass = computed(() => {
    if (!props.item?.rarity) return '';
    return 'piece-fg-' + props.item.rarity.toLowerCase().replace(/ /g, '_');
});

/* Shine animation for common → legendary (index 0–4, matching SkyCrypt) */
const RARITY_ORDER = ['common','uncommon','rare','epic','legendary','mythic','divine','special','very_special'];
const showShine = computed(() => {
    if (!props.item?.rarity) return false;
    const idx = RARITY_ORDER.indexOf(props.item.rarity.toLowerCase());
    return idx >= 0 && idx <= 4;
});

function onMouseEnter(e) {
    if (props.item && !pinned.value) {
        showTooltip.value = true;
        updatePos(e);
    }
}

function onMouseMove(e) {
    if (!pinned.value) updatePos(e);
}

function onMouseLeave() {
    if (!pinned.value) showTooltip.value = false;
}

/**
 * Position the tooltip relative to cursor.
 * Decides whether to show below or above the cursor based on viewport space.
 */
function updatePos(e) {
    const tooltipW = 380;   // max-width of .mc-lore-panel
    const margin   = 14;

    // Horizontal: prefer right of cursor, flip left if overflowing
    let x = e.clientX + margin;
    if (x + tooltipW > window.innerWidth) x = e.clientX - tooltipW - margin;

    // Vertical: measure actual tooltip height if available, else estimate
    const el = tooltipRef.value;
    const tooltipH = el ? el.offsetHeight : 400;

    let y;
    const spaceBelow = window.innerHeight - e.clientY - margin;
    const spaceAbove = e.clientY - margin;

    if (spaceBelow >= tooltipH || spaceBelow >= spaceAbove) {
        // Show below cursor (default)
        y = e.clientY + margin;
        // Clamp so bottom doesn't overflow
        if (y + tooltipH > window.innerHeight) y = window.innerHeight - tooltipH - 4;
    } else {
        // Show above cursor
        y = e.clientY - tooltipH - margin;
    }

    tooltipPos.value = { x: Math.max(4, x), y: Math.max(4, y) };
}

/* Click to pin/unpin tooltip for text selection */
function onItemClick(e) {
    if (!props.item) return;

    if (pinned.value) {
        // Unpin
        pinned.value = false;
        showTooltip.value = false;
        document.removeEventListener('mousedown', onOutsideClick, true);
        document.removeEventListener('keydown', onEscKey, true);
    } else {
        // Pin at current position
        showTooltip.value = true;
        updatePos(e);
        nextTick(() => {
            pinned.value = true;
            document.addEventListener('mousedown', onOutsideClick, true);
            document.addEventListener('keydown', onEscKey, true);
        });
    }
}

function onOutsideClick(e) {
    // If click is inside the pinned tooltip, allow it (for text selection)
    if (tooltipRef.value && tooltipRef.value.contains(e.target)) return;
    pinned.value = false;
    showTooltip.value = false;
    document.removeEventListener('mousedown', onOutsideClick, true);
    document.removeEventListener('keydown', onEscKey, true);
}

function onEscKey(e) {
    if (e.key === 'Escape') {
        pinned.value = false;
        showTooltip.value = false;
        document.removeEventListener('mousedown', onOutsideClick, true);
        document.removeEventListener('keydown', onEscKey, true);
    }
}

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onOutsideClick, true);
    document.removeEventListener('keydown', onEscKey, true);
});
</script>

<template>
    <!-- Empty slot -->
    <div v-if="!item" class="inventory-slot"></div>

    <!-- Item piece -->
    <div v-else
         class="piece"
         :class="[rarityBgClass]"
         tabindex="0"
         @mouseenter="onMouseEnter"
         @mousemove="onMouseMove"
         @mouseleave="onMouseLeave"
         @click.stop="onItemClick">

        <!-- Shine overlay (common → legendary) -->
        <div v-if="showShine" class="piece-shine"></div>

        <!-- Hover overlay -->
        <div class="piece-hover-overlay"></div>

        <!-- Item texture -->
        <img v-if="displayUrl"
             :src="displayUrl"
             :alt="item.name"
             class="piece-icon"
             loading="lazy"
             draggable="false" />

        <!-- Leather color swatch fallback -->
        <div v-else-if="item.color"
             class="piece-icon-color"
             :style="{ backgroundColor: item.color }">
        </div>

        <!-- Generic fallback -->
        <div v-else class="piece-icon-fallback">
            {{ item.name?.charAt(0) || '?' }}
        </div>

        <!-- Count badge -->
        <span v-if="item.count > 1" class="piece-count">{{ item.count }}</span>

        <!-- Stars badge -->
        <span v-if="item.stars > 0" class="piece-stars">
            {{ '✪'.repeat(Math.min(item.stars, 5)) }}{{ item.stars > 5 ? '+' : '' }}
        </span>

        <!-- MC-style lore tooltip (SkyCrypt-style, teleported to body) -->
        <Teleport to="body">
            <div v-if="showTooltip"
                 ref="tooltipRef"
                 class="mc-lore-panel"
                 :class="{ 'mc-lore-pinned': pinned }"
                 :style="{ left: tooltipPos.x + 'px', top: tooltipPos.y + 'px' }">
                <!-- Item name header with rarity background & icon -->
                <div class="mc-lore-name" :class="rarityBgClass">
                    <div v-if="displayUrl" class="mc-lore-name-icon">
                        <img :src="displayUrl"
                             :alt="item.name"
                             class="mc-lore-name-icon-img"
                             draggable="false" />
                    </div>
                    <span class="mc-lore-name-text" :class="rarityFgClass">{{ item.name }}</span>
                </div>
                <!-- Lore body rendered with MC color CSS variables -->
                <div class="mc-lore-body">
                    <div v-for="(line, i) in (item.lore_html || [])"
                         :key="i"
                         class="mc-lore-line"
                         v-html="line">
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
