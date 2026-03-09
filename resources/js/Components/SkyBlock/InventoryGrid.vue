<script setup>
/**
 * InventoryGrid — renders a 9-column MC-style inventory grid.
 *
 * Props:
 *   items     — flat array of items (nulls = empty slots)
 *   showHotbar — if true, reorder to show main inventory (9-35) then separator then hotbar (0-8)
 *   columns   — override column count (default 9)
 */
import ItemSlot from '@/Components/SkyBlock/ItemSlot.vue';

const props = defineProps({
    items:      { type: Array, default: () => [] },
    showHotbar: { type: Boolean, default: false },
    columns:    { type: Number, default: 9 },
});

/*
 * MC inventory layout:
 *   Slots 0-8   = hotbar (bottom row)
 *   Slots 9-35  = main inventory (3 rows)
 *
 * SkyCrypt renders main inventory first, then a separator, then hotbar.
 */
</script>

<template>
    <div class="inventory-grid" :style="{ gridTemplateColumns: `repeat(${columns}, calc(var(--inv-w) * ${(1/columns).toFixed(4)}))` }">
        <template v-if="showHotbar && items.length >= 36">
            <!-- Main inventory: slots 9-35 (3 rows × 9) -->
            <template v-for="i in 27" :key="'main-' + i">
                <ItemSlot :item="items[i + 8]" />
            </template>

            <!-- Separator between main inventory and hotbar -->
            <hr />

            <!-- Hotbar: slots 0-8 -->
            <template v-for="i in 9" :key="'hot-' + i">
                <ItemSlot :item="items[i - 1]" />
            </template>
        </template>

        <!-- Simple grid (no hotbar separation) -->
        <template v-else>
            <ItemSlot v-for="(item, idx) in items" :key="idx" :item="item" />
        </template>
    </div>
</template>
