<script setup>
/**
 * ItemSlot — renders a single SkyBlock item as a SkyCrypt-style "piece":
 *   - Rarity-colored background
 *   - Centered pixelated item texture with drop-shadow
 *   - Animated shine overlay for common→legendary
 *   - White hover overlay
 *   - MC-style lore tooltip (teleported to body)
 */
import { computed, ref, inject } from 'vue';
import { getItemTextureUrl } from '@/utils/textures';

const props = defineProps({
    item: { type: Object, default: null },
});

const showTooltip = ref(false);
const tooltipPos  = ref({ x: 0, y: 0 });
const textureVersion = inject('textureVersion', ref(0));

const textureUrl = computed(() => {
    void textureVersion.value;
    return getItemTextureUrl(props.item);
});

/* Rarity → CSS class for background */
const rarityBgClass = computed(() => {
    if (!props.item?.rarity) return '';
    return 'piece-bg-' + props.item.rarity.toLowerCase().replace(/ /g, '_');
});

/* Shine animation for common → legendary (index 0–4, matching SkyCrypt) */
const RARITY_ORDER = ['common','uncommon','rare','epic','legendary','mythic','divine','special','very_special'];
const showShine = computed(() => {
    if (!props.item?.rarity) return false;
    const idx = RARITY_ORDER.indexOf(props.item.rarity.toLowerCase());
    return idx >= 0 && idx <= 4;
});

/* Rarity name color (MC §-code colors) */
const RARITY_COLORS = {
    common: '#ffffff', uncommon: '#55ff55', rare: '#5555ff', epic: '#aa00aa',
    legendary: '#ffaa00', mythic: '#ff55ff', divine: '#55ffff',
    special: '#ff5555', very_special: '#ff5555',
};
const nameColor = computed(() => {
    if (!props.item?.rarity) return '#ffffff';
    return RARITY_COLORS[props.item.rarity.toLowerCase()] || '#ffffff';
});

function onMouseEnter(e) { if (props.item) { showTooltip.value = true; updatePos(e); } }
function onMouseMove(e)  { updatePos(e); }
function onMouseLeave()  { showTooltip.value = false; }
function updatePos(e) {
    let x = e.clientX + 14;
    let y = e.clientY + 14;
    if (x + 370 > window.innerWidth)  x = e.clientX - 370 - 10;
    if (y + 400 > window.innerHeight) y = window.innerHeight - 400 - 10;
    tooltipPos.value = { x: Math.max(4, x), y: Math.max(4, y) };
}
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
         @mouseleave="onMouseLeave">

        <!-- Shine overlay (common → legendary) -->
        <div v-if="showShine" class="piece-shine"></div>

        <!-- Hover overlay -->
        <div class="piece-hover-overlay"></div>

        <!-- Item texture -->
        <img v-if="textureUrl"
             :src="textureUrl"
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

        <!-- MC-style lore tooltip (teleported to body) -->
        <Teleport to="body">
            <div v-if="showTooltip"
                 class="mc-lore-panel"
                 :style="{ left: tooltipPos.x + 'px', top: tooltipPos.y + 'px' }">
                <div class="mc-lore-name" :class="rarityBgClass">
                    <span :style="{ color: nameColor }">{{ item.name }}</span>
                </div>
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
