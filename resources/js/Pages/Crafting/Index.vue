<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    recipes: Array,
    categories: Array,
});

const search = ref('');
const categoryFilter = ref('');
const sortKey = ref('profit');
const sortDir = ref('desc');
const expandedRecipe = ref(null);

function toggleExpand(id) {
    expandedRecipe.value = expandedRecipe.value === id ? null : id;
}

const filteredRecipes = computed(() => {
    let items = [...props.recipes];

    if (search.value) {
        const q = search.value.toLowerCase();
        items = items.filter(r => r.result_item_name.toLowerCase().includes(q));
    }

    if (categoryFilter.value) {
        items = items.filter(r => r.category === categoryFilter.value);
    }

    items.sort((a, b) => {
        let av = a[sortKey.value] ?? 0;
        let bv = b[sortKey.value] ?? 0;
        if (typeof av === 'string') av = av.toLowerCase();
        if (typeof bv === 'string') bv = bv.toLowerCase();
        if (av < bv) return sortDir.value === 'asc' ? -1 : 1;
        if (av > bv) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });

    return items;
});

function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = key === 'result_item_name' ? 'asc' : 'desc';
    }
}

function sortIcon(key) {
    if (sortKey.value !== key) return '';
    return sortDir.value === 'asc' ? '▲' : '▼';
}

function fmt(n) {
    if (n === null || n === undefined) return '—';
    return Number(n).toLocaleString('en-US', { maximumFractionDigits: 1 });
}

function pnlClass(v) {
    if (v > 0) return 'text-profit';
    if (v < 0) return 'text-loss';
    return 'text-neutral';
}

const profitableCount = computed(() => filteredRecipes.value.filter(r => r.profit > 0).length);
</script>

<template>
    <Head title="Craft-to-Bazaar Arbitrage" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Craft-to-Bazaar Arbitrage</h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
            <!-- Summary -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
                <div class="bg-surface-800 border border-border p-3">
                    <div class="text-[10px] uppercase text-neutral tracking-wider">Total Recipes</div>
                    <div class="text-base font-semibold text-white mt-1">{{ filteredRecipes.length }}</div>
                </div>
                <div class="bg-surface-800 border border-border p-3">
                    <div class="text-[10px] uppercase text-neutral tracking-wider">Profitable</div>
                    <div class="text-base font-semibold text-profit mt-1">{{ profitableCount }}</div>
                </div>
                <div class="bg-surface-800 border border-border p-3 hidden sm:block">
                    <div class="text-[10px] uppercase text-neutral tracking-wider">Best Margin</div>
                    <div class="text-base font-semibold text-profit mt-1">
                        {{ filteredRecipes.length ? fmt(Math.max(...filteredRecipes.map(r => r.margin))) + '%' : '—' }}
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3 mb-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search recipes…"
                    class="bg-surface-800 border border-border text-xs text-white px-3 py-1.5 placeholder-neutral focus:outline-none focus:border-border-light w-48"
                />
                <select
                    v-model="categoryFilter"
                    class="bg-surface-800 border border-border text-xs text-white px-3 py-1.5 focus:outline-none focus:border-border-light"
                >
                    <option value="">All Categories</option>
                    <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                </select>
            </div>

            <!-- Recipes Table -->
            <div class="bg-surface-800 border border-border overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-border text-[10px] uppercase text-neutral tracking-wider">
                            <th class="text-left px-3 py-2 cursor-pointer hover:text-white" @click="toggleSort('result_item_name')">
                                Result {{ sortIcon('result_item_name') }}
                            </th>
                            <th class="text-left px-3 py-2">Category</th>
                            <th class="text-right px-3 py-2 cursor-pointer hover:text-white" @click="toggleSort('craft_cost')">
                                Craft Cost {{ sortIcon('craft_cost') }}
                            </th>
                            <th class="text-right px-3 py-2 cursor-pointer hover:text-white" @click="toggleSort('sell_price')">
                                Sell Price {{ sortIcon('sell_price') }}
                            </th>
                            <th class="text-right px-3 py-2 cursor-pointer hover:text-white" @click="toggleSort('profit')">
                                Profit {{ sortIcon('profit') }}
                            </th>
                            <th class="text-right px-3 py-2 cursor-pointer hover:text-white" @click="toggleSort('margin')">
                                Margin {{ sortIcon('margin') }}
                            </th>
                            <th class="text-center px-3 py-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="recipe in filteredRecipes" :key="recipe.result_item_id">
                            <tr
                                class="border-b border-border/50 hover:bg-surface-700 cursor-pointer"
                                @click="toggleExpand(recipe.result_item_id)"
                            >
                                <td class="px-3 py-2 text-white whitespace-nowrap font-medium">
                                    {{ recipe.result_item_name }}
                                    <span v-if="recipe.result_quantity > 1" class="text-neutral ml-1">×{{ recipe.result_quantity }}</span>
                                </td>
                                <td class="px-3 py-2 text-neutral">{{ recipe.category || '—' }}</td>
                                <td class="px-3 py-2 text-right text-neutral">{{ fmt(recipe.craft_cost) }}</td>
                                <td class="px-3 py-2 text-right text-white">{{ fmt(recipe.sell_price) }}</td>
                                <td class="px-3 py-2 text-right font-medium" :class="pnlClass(recipe.profit)">
                                    {{ recipe.profit >= 0 ? '+' : '' }}{{ fmt(recipe.profit) }}
                                </td>
                                <td class="px-3 py-2 text-right" :class="pnlClass(recipe.margin)">
                                    {{ recipe.margin >= 0 ? '+' : '' }}{{ recipe.margin.toFixed(1) }}%
                                </td>
                                <td class="px-3 py-2 text-center text-neutral">
                                    <span class="text-[10px]">{{ expandedRecipe === recipe.result_item_id ? '▼' : '▶' }}</span>
                                </td>
                            </tr>
                            <!-- Expanded: Ingredient grid -->
                            <tr v-if="expandedRecipe === recipe.result_item_id">
                                <td colspan="7" class="p-0">
                                    <div class="bg-surface-900 border-t border-border px-4 py-3">
                                        <div class="text-[10px] uppercase text-neutral tracking-wider mb-2">Ingredients</div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                            <div
                                                v-for="(ing, idx) in recipe.ingredients"
                                                :key="idx"
                                                class="border border-border bg-surface-800 px-3 py-2 flex items-center justify-between"
                                            >
                                                <div>
                                                    <span class="text-white text-xs">{{ ing.name }}</span>
                                                    <span class="text-neutral text-[10px] ml-1">×{{ ing.quantity }}</span>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-xs text-neutral">{{ fmt(ing.unit_price) }}/ea</div>
                                                    <div class="text-xs text-white font-medium">{{ fmt(ing.total_cost) }}</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="!recipe.all_available" class="mt-2 text-[10px] text-loss">
                                            ⚠ Some ingredients have no Bazaar listing — prices may be inaccurate.
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!filteredRecipes.length">
                            <td colspan="7" class="px-3 py-6 text-center text-neutral">No recipes found. Run <code class="text-white">php artisan recipes:seed</code> to load data.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
