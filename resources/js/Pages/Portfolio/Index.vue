<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler);

const props = defineProps({
    openPositions: Array,
    closedPositions: Array,
    currentPrices: Object,
    chartData: Object,
    totalInvested: Number,
    totalCurrentValue: Number,
    realisedPnl: Number,
    bazaarItems: Array,
});

const tab = ref('open'); // 'open' | 'closed'
const showAddForm = ref(false);
const searchBazaar = ref('');

const form = useForm({
    product_id: '',
    product_name: '',
    buy_price: '',
    quantity: 1,
    purchased_at: '',
});

const sellForm = useForm({
    id: null,
    sold_price: '',
});

const filteredBazaarItems = computed(() => {
    if (!searchBazaar.value) return props.bazaarItems.slice(0, 20);
    const q = searchBazaar.value.toLowerCase();
    return props.bazaarItems.filter(i => i.name.toLowerCase().includes(q)).slice(0, 20);
});

function selectBazaarItem(item) {
    form.product_id = item.product_id;
    form.product_name = item.name;
    form.buy_price = item.buy_price;
    searchBazaar.value = item.name;
}

function submitAdd() {
    form.post(route('portfolio.store'), {
        onSuccess: () => {
            form.reset();
            showAddForm.value = false;
            searchBazaar.value = '';
        },
    });
}

function sellPosition(item) {
    const currentPrice = props.currentPrices[item.product_id] ?? item.buy_price;
    sellForm.id = item.id;
    sellForm.sold_price = currentPrice;
    sellForm.post(route('portfolio.sell'), {
        onSuccess: () => sellForm.reset(),
    });
}

function deletePosition(id) {
    router.delete(route('portfolio.destroy'), {
        data: { id },
        preserveScroll: true,
    });
}

const unrealisedPnl = computed(() => {
    return props.totalCurrentValue - props.totalInvested;
});

function fmt(n) {
    if (n === null || n === undefined) return '—';
    return Number(n).toLocaleString('en-US', { maximumFractionDigits: 1 });
}

function pnlClass(v) {
    if (v > 0) return 'text-profit';
    if (v < 0) return 'text-loss';
    return 'text-neutral';
}

// Chart config — flat colors, no gradient fills
const chartConfig = computed(() => ({
    data: {
        labels: props.chartData.labels || [],
        datasets: [
            {
                label: 'Portfolio Value',
                data: props.chartData.value || [],
                borderColor: '#55FF55',
                backgroundColor: 'transparent',
                borderWidth: 2,
                pointRadius: 0,
                pointHoverRadius: 4,
                tension: 0.3,
                fill: false,
            },
            {
                label: 'Invested',
                data: props.chartData.invested || [],
                borderColor: '#AAAAAA',
                backgroundColor: 'transparent',
                borderWidth: 1,
                borderDash: [4, 4],
                pointRadius: 0,
                pointHoverRadius: 4,
                tension: 0.3,
                fill: false,
            },
        ],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                labels: { color: '#AAAAAA', font: { family: 'Inter', size: 11 } },
            },
            tooltip: {
                backgroundColor: '#1a1a20',
                titleColor: '#d1d1d1',
                bodyColor: '#d1d1d1',
                borderColor: '#303030',
                borderWidth: 1,
                cornerRadius: 2,
                callbacks: {
                    label: (ctx) => `${ctx.dataset.label}: ${fmt(ctx.parsed.y)} coins`,
                },
            },
        },
        scales: {
            x: {
                ticks: { color: '#AAAAAA', font: { family: 'Inter', size: 10 } },
                grid: { color: '#232328' },
            },
            y: {
                ticks: {
                    color: '#AAAAAA',
                    font: { family: 'Inter', size: 10 },
                    callback: (v) => fmt(v),
                },
                grid: { color: '#232328' },
            },
        },
    },
}));
</script>

<template>
    <Head title="Portfolio Tracker" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Investment Portfolio</h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
            <!-- Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                <div class="bg-surface-800 border border-border p-3">
                    <div class="text-[10px] uppercase text-neutral tracking-wider">Invested</div>
                    <div class="text-base font-semibold text-white mt-1">{{ fmt(totalInvested) }}</div>
                </div>
                <div class="bg-surface-800 border border-border p-3">
                    <div class="text-[10px] uppercase text-neutral tracking-wider">Current Value</div>
                    <div class="text-base font-semibold text-white mt-1">{{ fmt(totalCurrentValue) }}</div>
                </div>
                <div class="bg-surface-800 border border-border p-3">
                    <div class="text-[10px] uppercase text-neutral tracking-wider">Unrealised P&L</div>
                    <div class="text-base font-semibold mt-1" :class="pnlClass(unrealisedPnl)">
                        {{ unrealisedPnl >= 0 ? '+' : '' }}{{ fmt(unrealisedPnl) }}
                    </div>
                </div>
                <div class="bg-surface-800 border border-border p-3">
                    <div class="text-[10px] uppercase text-neutral tracking-wider">Realised P&L</div>
                    <div class="text-base font-semibold mt-1" :class="pnlClass(realisedPnl)">
                        {{ realisedPnl >= 0 ? '+' : '' }}{{ fmt(realisedPnl) }}
                    </div>
                </div>
            </div>

            <!-- Chart -->
            <div class="bg-surface-800 border border-border p-4 mb-4" v-if="chartData.labels && chartData.labels.length > 0">
                <div class="text-[10px] uppercase text-neutral tracking-wider mb-3">30-Day Portfolio Value</div>
                <div class="h-56">
                    <Line :data="chartConfig.data" :options="chartConfig.options" />
                </div>
            </div>

            <!-- Actions Bar -->
            <div class="flex items-center justify-between mb-3">
                <div class="flex gap-2">
                    <button
                        @click="tab = 'open'"
                        class="px-3 py-1 text-xs font-medium border"
                        :class="tab === 'open' ? 'bg-surface-600 border-border-light text-white' : 'bg-surface-800 border-border text-neutral hover:text-white'"
                    >
                        Open Positions
                    </button>
                    <button
                        @click="tab = 'closed'"
                        class="px-3 py-1 text-xs font-medium border"
                        :class="tab === 'closed' ? 'bg-surface-600 border-border-light text-white' : 'bg-surface-800 border-border text-neutral hover:text-white'"
                    >
                        Closed Positions
                    </button>
                </div>
                <button
                    @click="showAddForm = !showAddForm"
                    class="px-3 py-1 text-xs font-medium bg-surface-700 border border-border text-white hover:bg-surface-600"
                >
                    + Add Position
                </button>
            </div>

            <!-- Add Position Form -->
            <div v-if="showAddForm" class="bg-surface-800 border border-border p-4 mb-4">
                <div class="text-[10px] uppercase text-neutral tracking-wider mb-3">New Position</div>
                <form @submit.prevent="submitAdd" class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                    <div class="relative sm:col-span-2">
                        <input
                            v-model="searchBazaar"
                            type="text"
                            placeholder="Search Bazaar item…"
                            class="w-full bg-surface-700 border border-border text-xs text-white px-2 py-1.5 placeholder-neutral focus:outline-none focus:border-border-light"
                        />
                        <div
                            v-if="searchBazaar && filteredBazaarItems.length"
                            class="absolute z-10 mt-0.5 w-full bg-surface-700 border border-border max-h-40 overflow-y-auto"
                        >
                            <button
                                v-for="item in filteredBazaarItems"
                                :key="item.product_id"
                                type="button"
                                @click="selectBazaarItem(item)"
                                class="block w-full text-left px-2 py-1 text-xs text-neutral hover:text-white hover:bg-surface-600"
                            >
                                {{ item.name }} — {{ fmt(item.buy_price) }}
                            </button>
                        </div>
                    </div>
                    <input
                        v-model="form.buy_price"
                        type="number"
                        step="0.01"
                        placeholder="Buy Price"
                        class="bg-surface-700 border border-border text-xs text-white px-2 py-1.5 placeholder-neutral focus:outline-none focus:border-border-light"
                    />
                    <input
                        v-model="form.quantity"
                        type="number"
                        min="1"
                        placeholder="Qty"
                        class="bg-surface-700 border border-border text-xs text-white px-2 py-1.5 placeholder-neutral focus:outline-none focus:border-border-light"
                    />
                    <button
                        type="submit"
                        :disabled="form.processing || !form.product_id"
                        class="bg-surface-600 border border-border text-xs text-white px-3 py-1.5 hover:bg-surface-500 disabled:opacity-40"
                    >
                        Add
                    </button>
                </form>
            </div>

            <!-- Open Positions Table -->
            <div v-if="tab === 'open'" class="bg-surface-800 border border-border overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-border text-[10px] uppercase text-neutral tracking-wider">
                            <th class="text-left px-3 py-2">Item</th>
                            <th class="text-right px-3 py-2">Qty</th>
                            <th class="text-right px-3 py-2">Buy Price</th>
                            <th class="text-right px-3 py-2">Current</th>
                            <th class="text-right px-3 py-2">P&L</th>
                            <th class="text-right px-3 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="pos in openPositions"
                            :key="pos.id"
                            class="border-b border-border/50 hover:bg-surface-700"
                        >
                            <td class="px-3 py-2 text-white whitespace-nowrap">{{ pos.product_name }}</td>
                            <td class="px-3 py-2 text-right text-neutral">{{ fmt(pos.quantity) }}</td>
                            <td class="px-3 py-2 text-right text-neutral">{{ fmt(pos.buy_price) }}</td>
                            <td class="px-3 py-2 text-right text-white">{{ fmt(currentPrices[pos.product_id] ?? pos.buy_price) }}</td>
                            <td class="px-3 py-2 text-right font-medium" :class="pnlClass(((currentPrices[pos.product_id] ?? pos.buy_price) - pos.buy_price) * pos.quantity)">
                                {{ (((currentPrices[pos.product_id] ?? pos.buy_price) - pos.buy_price) * pos.quantity) >= 0 ? '+' : '' }}{{ fmt(((currentPrices[pos.product_id] ?? pos.buy_price) - pos.buy_price) * pos.quantity) }}
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <button @click="sellPosition(pos)" class="text-profit hover:underline mr-2">Sell</button>
                                <button @click="deletePosition(pos.id)" class="text-loss hover:underline">Del</button>
                            </td>
                        </tr>
                        <tr v-if="!openPositions.length">
                            <td colspan="6" class="px-3 py-6 text-center text-neutral">No open positions. Add one above.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Closed Positions Table -->
            <div v-if="tab === 'closed'" class="bg-surface-800 border border-border overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-border text-[10px] uppercase text-neutral tracking-wider">
                            <th class="text-left px-3 py-2">Item</th>
                            <th class="text-right px-3 py-2">Qty</th>
                            <th class="text-right px-3 py-2">Buy</th>
                            <th class="text-right px-3 py-2">Sell</th>
                            <th class="text-right px-3 py-2">P&L</th>
                            <th class="text-right px-3 py-2">Closed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="pos in closedPositions"
                            :key="pos.id"
                            class="border-b border-border/50 hover:bg-surface-700"
                        >
                            <td class="px-3 py-2 text-white whitespace-nowrap">{{ pos.product_name }}</td>
                            <td class="px-3 py-2 text-right text-neutral">{{ fmt(pos.quantity) }}</td>
                            <td class="px-3 py-2 text-right text-neutral">{{ fmt(pos.buy_price) }}</td>
                            <td class="px-3 py-2 text-right text-white">{{ fmt(pos.sold_price) }}</td>
                            <td class="px-3 py-2 text-right font-medium" :class="pnlClass((pos.sold_price - pos.buy_price) * pos.quantity)">
                                {{ ((pos.sold_price - pos.buy_price) * pos.quantity) >= 0 ? '+' : '' }}{{ fmt((pos.sold_price - pos.buy_price) * pos.quantity) }}
                            </td>
                            <td class="px-3 py-2 text-right text-neutral">{{ new Date(pos.sold_at).toLocaleDateString() }}</td>
                        </tr>
                        <tr v-if="!closedPositions.length">
                            <td colspan="6" class="px-3 py-6 text-center text-neutral">No closed positions yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
