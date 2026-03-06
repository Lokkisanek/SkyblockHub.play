<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    snapshots: Object,
    alerts: Array,
    filters: Object,
});

const search = ref(props.filters.search || '');
const tier = ref(props.filters.tier || '');
const sort = ref(props.filters.sort || 'price');
const direction = ref(props.filters.direction || 'asc');

let searchTimeout = null;
watch(search, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});

watch(tier, () => applyFilters());

function applyFilters() {
    router.get(route('bin-sniper'), {
        search: search.value || undefined,
        tier: tier.value || undefined,
        sort: sort.value,
        direction: direction.value,
    }, { preserveState: true, preserveScroll: true });
}

function toggleSort(key) {
    if (sort.value === key) {
        direction.value = direction.value === 'asc' ? 'desc' : 'asc';
    } else {
        sort.value = key;
        direction.value = key === 'item_name' ? 'asc' : 'asc';
    }
    applyFilters();
}

function sortIcon(key) {
    if (sort.value !== key) return '';
    return direction.value === 'asc' ? '▲' : '▼';
}

const showAlertForm = ref(false);
const alertForm = useForm({
    item_name: '',
    threshold_price: '',
});

function submitAlert() {
    alertForm.post(route('bin-sniper.alert.store'), {
        onSuccess: () => {
            alertForm.reset();
            showAlertForm.value = false;
        },
    });
}

function deleteAlert(id) {
    router.delete(route('bin-sniper.alert.destroy'), {
        data: { id },
        preserveScroll: true,
    });
}

function toggleAlert(id) {
    router.patch(route('bin-sniper.alert.toggle'), { id }, {
        preserveScroll: true,
    });
}

function fmt(n) {
    if (n === null || n === undefined) return '—';
    return Number(n).toLocaleString('en-US', { maximumFractionDigits: 1 });
}

function tierColor(t) {
    const map = {
        COMMON: 'text-rarity-common',
        UNCOMMON: 'text-rarity-uncommon',
        RARE: 'text-rarity-rare',
        EPIC: 'text-rarity-epic',
        LEGENDARY: 'text-rarity-legendary',
        MYTHIC: 'text-rarity-mythic',
        DIVINE: 'text-rarity-divine',
        SPECIAL: 'text-rarity-mythic',
        VERY_SPECIAL: 'text-rarity-divine',
        SUPREME: 'text-rarity-legendary',
    };
    return map[t] || 'text-neutral';
}

function tierLabel(t) {
    if (!t) return '—';
    return t.charAt(0) + t.slice(1).toLowerCase().replace(/_/g, ' ');
}

function timeAgo(dateStr) {
    if (!dateStr) return '—';
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return mins + 'm ago';
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return hrs + 'h ago';
    return Math.floor(hrs / 24) + 'd ago';
}

function timeUntil(dateStr) {
    if (!dateStr) return '—';
    const diff = new Date(dateStr).getTime() - Date.now();
    if (diff <= 0) return 'ended';
    const mins = Math.floor(diff / 60000);
    if (mins < 60) return mins + 'm';
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return hrs + 'h ' + (mins % 60) + 'm';
    return Math.floor(hrs / 24) + 'd ' + (hrs % 24) + 'h';
}

// Check which alerts are currently triggered
const triggeredAlerts = computed(() => {
    if (!props.alerts.length) return new Set();
    const s = new Set();
    for (const alert of props.alerts) {
        if (!alert.is_active) continue;
        const match = props.snapshots.data?.find(
            snap => snap.item_name.toLowerCase() === alert.item_name.toLowerCase() && parseFloat(snap.price) <= parseFloat(alert.threshold_price)
        );
        if (match) s.add(alert.id);
    }
    return s;
});
</script>

<template>
    <Head title="Lowest BIN Sniper" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Lowest BIN Sniper</h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="flex flex-wrap gap-3 mb-4 items-center">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search items…"
                    class="bg-surface-800 border border-border text-xs text-white px-3 py-1.5 placeholder-neutral focus:outline-none focus:border-border-light w-56"
                />
                <select
                    v-model="tier"
                    class="bg-surface-800 border border-border text-xs text-white px-3 py-1.5 focus:outline-none focus:border-border-light"
                >
                    <option value="">All Tiers</option>
                    <option value="COMMON">Common</option>
                    <option value="UNCOMMON">Uncommon</option>
                    <option value="RARE">Rare</option>
                    <option value="EPIC">Epic</option>
                    <option value="LEGENDARY">Legendary</option>
                    <option value="MYTHIC">Mythic</option>
                    <option value="DIVINE">Divine</option>
                </select>
                <div class="ml-auto">
                    <button
                        @click="showAlertForm = !showAlertForm"
                        class="px-3 py-1 text-xs font-medium bg-surface-700 border border-border text-white hover:bg-surface-600"
                    >
                        {{ showAlertForm ? 'Cancel' : '+ Set Alert' }}
                    </button>
                </div>
            </div>

            <!-- Alert Form -->
            <div v-if="showAlertForm" class="bg-surface-800 border border-border p-4 mb-4">
                <div class="text-[10px] uppercase text-neutral tracking-wider mb-3">New Price Alert</div>
                <form @submit.prevent="submitAlert" class="flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="text-[10px] text-neutral block mb-1">Item Name</label>
                        <input
                            v-model="alertForm.item_name"
                            type="text"
                            placeholder="e.g. Hyperion"
                            class="bg-surface-700 border border-border text-xs text-white px-2 py-1.5 placeholder-neutral focus:outline-none focus:border-border-light w-48"
                        />
                    </div>
                    <div>
                        <label class="text-[10px] text-neutral block mb-1">Threshold Price</label>
                        <input
                            v-model="alertForm.threshold_price"
                            type="number"
                            step="1"
                            placeholder="Max price"
                            class="bg-surface-700 border border-border text-xs text-white px-2 py-1.5 placeholder-neutral focus:outline-none focus:border-border-light w-36"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="alertForm.processing || !alertForm.item_name || !alertForm.threshold_price"
                        class="bg-surface-600 border border-border text-xs text-white px-3 py-1.5 hover:bg-surface-500 disabled:opacity-40"
                    >
                        Create Alert
                    </button>
                </form>
            </div>

            <!-- Active Alerts -->
            <div v-if="alerts.length" class="bg-surface-800 border border-border p-3 mb-4">
                <div class="text-[10px] uppercase text-neutral tracking-wider mb-2">Your Alerts</div>
                <div class="flex flex-wrap gap-2">
                    <div
                        v-for="alert in alerts"
                        :key="alert.id"
                        class="flex items-center gap-2 border px-2 py-1 text-xs"
                        :class="[
                            triggeredAlerts.has(alert.id) ? 'border-profit bg-profit/10 text-profit' : 'border-border text-neutral',
                            !alert.is_active ? 'opacity-40' : '',
                        ]"
                    >
                        <span class="text-white">{{ alert.item_name }}</span>
                        <span>≤ {{ fmt(alert.threshold_price) }}</span>
                        <span v-if="triggeredAlerts.has(alert.id)" class="text-[10px] font-bold text-profit">TRIGGERED</span>
                        <button @click="toggleAlert(alert.id)" class="hover:text-white text-[10px]">
                            {{ alert.is_active ? 'Pause' : 'Resume' }}
                        </button>
                        <button @click="deleteAlert(alert.id)" class="text-loss hover:text-white text-[10px]">×</button>
                    </div>
                </div>
            </div>

            <!-- BIN Listings Table -->
            <div class="bg-surface-800 border border-border overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-border text-[10px] uppercase text-neutral tracking-wider">
                            <th class="text-left px-3 py-2 cursor-pointer hover:text-white" @click="toggleSort('item_name')">
                                Item {{ sortIcon('item_name') }}
                            </th>
                            <th class="text-left px-3 py-2 cursor-pointer hover:text-white" @click="toggleSort('tier')">
                                Tier {{ sortIcon('tier') }}
                            </th>
                            <th class="text-right px-3 py-2 cursor-pointer hover:text-white" @click="toggleSort('price')">
                                Lowest BIN {{ sortIcon('price') }}
                            </th>
                            <th class="text-right px-3 py-2 cursor-pointer hover:text-white" @click="toggleSort('ends_at')">
                                Ends In {{ sortIcon('ends_at') }}
                            </th>
                            <th class="text-right px-3 py-2 cursor-pointer hover:text-white" @click="toggleSort('recorded_at')">
                                Seen {{ sortIcon('recorded_at') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="snap in snapshots.data"
                            :key="snap.id"
                            class="border-b border-border/50 hover:bg-surface-700"
                        >
                            <td class="px-3 py-2 text-white whitespace-nowrap font-medium">{{ snap.item_name }}</td>
                            <td class="px-3 py-2 whitespace-nowrap" :class="tierColor(snap.tier)">{{ tierLabel(snap.tier) }}</td>
                            <td class="px-3 py-2 text-right text-rarity-legendary font-medium">{{ fmt(snap.price) }}</td>
                            <td class="px-3 py-2 text-right text-neutral">{{ timeUntil(snap.ends_at) }}</td>
                            <td class="px-3 py-2 text-right text-neutral">{{ timeAgo(snap.recorded_at) }}</td>
                        </tr>
                        <tr v-if="!snapshots.data?.length">
                            <td colspan="5" class="px-3 py-6 text-center text-neutral">
                                No BIN data yet. Run <code class="text-white">php artisan bin:fetch</code> to load auction data.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="snapshots.links && snapshots.last_page > 1" class="flex justify-center gap-1 mt-3">
                <template v-for="link in snapshots.links" :key="link.label">
                    <button
                        v-if="link.url"
                        @click="router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                        class="px-2 py-1 text-[10px] border"
                        :class="link.active ? 'bg-surface-600 border-border-light text-white' : 'bg-surface-800 border-border text-neutral hover:text-white'"
                        v-html="link.label"
                    ></button>
                    <span
                        v-else
                        class="px-2 py-1 text-[10px] text-neutral/40"
                        v-html="link.label"
                    ></span>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
