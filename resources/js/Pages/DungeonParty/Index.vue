<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import KarmaVoter from '@/Components/KarmaVoter.vue';

const props = defineProps({
    listings: Object,
    myListing: Object,
    filters: Object,
});

const floors = ['F1','F2','F3','F4','F5','F6','F7','M1','M2','M3','M4','M5','M6','M7'];
const classes = ['Healer','Berserker','Mage','Archer','Tank'];

const filterFloor = ref(props.filters.floor || '');
const filterClass = ref(props.filters.class || '');

const showForm = ref(false);

const form = useForm({
    floor: 'F7',
    class: 'Healer',
    catacombs_level: 30,
    note: '',
});

function applyFilters() {
    router.get(route('dungeon-party'), {
        floor: filterFloor.value || undefined,
        class: filterClass.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function submit() {
    form.post(route('dungeon-party.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        },
    });
}

function removeListing() {
    router.delete(route('dungeon-party.destroy'), {
        preserveScroll: true,
    });
}

function timeAgo(dateStr) {
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

function classColor(cls) {
    const map = {
        'Healer': 'text-rarity-uncommon',
        'Mage': 'text-rarity-rare',
        'Berserker': 'text-loss',
        'Archer': 'text-rarity-legendary',
        'Tank': 'text-neutral',
    };
    return map[cls] || 'text-white';
}
</script>

<template>
    <Head title="Party Finder" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Dungeon Party Finder</h2>
                <button
                    v-if="!myListing"
                    @click="showForm = !showForm"
                    class="px-3 py-1 text-xs font-medium border border-border bg-surface-700 text-neutral hover:text-white rounded-none"
                >
                    {{ showForm ? 'Cancel' : '+ List Yourself' }}
                </button>
                <button
                    v-else
                    @click="removeListing"
                    class="px-3 py-1 text-xs font-medium border border-loss text-loss hover:bg-surface-700 rounded-none"
                >
                    Remove My Listing
                </button>
            </div>
        </template>

        <div class="py-4">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <!-- Create Listing Form -->
                <div v-if="showForm && !myListing" class="mb-4 border border-border bg-surface-800 p-4">
                    <div class="text-[10px] uppercase tracking-wider text-neutral mb-3">Create Listing</div>
                    <form @submit.prevent="submit" class="grid grid-cols-2 md:grid-cols-5 gap-3">
                        <div>
                            <label class="block text-[10px] text-neutral uppercase mb-1">Floor</label>
                            <select
                                v-model="form.floor"
                                class="w-full bg-surface-700 border border-border rounded-none px-2 py-1 text-xs text-white focus:outline-none"
                            >
                                <option v-for="f in floors" :key="f" :value="f">{{ f }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] text-neutral uppercase mb-1">Class</label>
                            <select
                                v-model="form.class"
                                class="w-full bg-surface-700 border border-border rounded-none px-2 py-1 text-xs text-white focus:outline-none"
                            >
                                <option v-for="c in classes" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] text-neutral uppercase mb-1">Cata Level</label>
                            <input
                                v-model.number="form.catacombs_level"
                                type="number"
                                min="0"
                                max="100"
                                class="w-full bg-surface-700 border border-border rounded-none px-2 py-1 text-xs text-white focus:outline-none"
                            />
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-[10px] text-neutral uppercase mb-1">Note</label>
                            <input
                                v-model="form.note"
                                type="text"
                                maxlength="255"
                                placeholder="e.g. S+ runs only"
                                class="w-full bg-surface-700 border border-border rounded-none px-2 py-1 text-xs text-white placeholder-neutral focus:outline-none"
                            />
                        </div>
                        <div class="flex items-end">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full px-3 py-1.5 text-xs font-medium border border-border bg-surface-600 text-white hover:bg-surface-500 rounded-none disabled:opacity-50"
                            >
                                Submit
                            </button>
                        </div>
                    </form>
                    <div v-if="form.errors && Object.keys(form.errors).length" class="mt-2 text-xs text-loss">
                        <div v-for="(msg, key) in form.errors" :key="key">{{ msg }}</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="mb-3 flex items-center gap-2">
                    <select
                        v-model="filterFloor"
                        @change="applyFilters"
                        class="bg-surface-800 border border-border rounded-none px-2 py-1 text-xs text-white focus:outline-none"
                    >
                        <option value="">All Floors</option>
                        <option v-for="f in floors" :key="f" :value="f">{{ f }}</option>
                    </select>
                    <select
                        v-model="filterClass"
                        @change="applyFilters"
                        class="bg-surface-800 border border-border rounded-none px-2 py-1 text-xs text-white focus:outline-none"
                    >
                        <option value="">All Classes</option>
                        <option v-for="c in classes" :key="c" :value="c">{{ c }}</option>
                    </select>
                </div>

                <!-- Listings Table -->
                <div class="overflow-x-auto border border-border">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-surface-700 text-neutral uppercase tracking-wider">
                                <th class="px-3 py-2 text-left border-b border-border">Player</th>
                                <th class="px-3 py-2 text-left border-b border-border">Floor</th>
                                <th class="px-3 py-2 text-left border-b border-border">Class</th>
                                <th class="px-3 py-2 text-right border-b border-border">Cata Lvl</th>
                                <th class="px-3 py-2 text-left border-b border-border">Note</th>
                                <th class="px-3 py-2 text-right border-b border-border">Posted</th>
                                <th class="px-3 py-2 text-center border-b border-border">Karma</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="listing in listings.data"
                                :key="listing.id"
                                class="border-b border-border hover:bg-surface-700"
                            >
                                <td class="px-3 py-1.5 text-white font-medium whitespace-nowrap">
                                    {{ listing.user?.discord_username || listing.user?.name || '—' }}
                                </td>
                                <td class="px-3 py-1.5 text-white font-mono">
                                    {{ listing.floor }}
                                </td>
                                <td class="px-3 py-1.5 font-medium" :class="classColor(listing.class)">
                                    {{ listing.class }}
                                </td>
                                <td class="px-3 py-1.5 text-right text-rarity-divine font-mono">
                                    {{ listing.catacombs_level }}
                                </td>
                                <td class="px-3 py-1.5 text-neutral max-w-[200px] truncate">
                                    {{ listing.note || '—' }}
                                </td>
                                <td class="px-3 py-1.5 text-right text-neutral whitespace-nowrap">
                                    {{ timeAgo(listing.created_at) }}
                                </td>
                                <td class="px-3 py-1.5 text-center">
                                    <KarmaVoter
                                        :target-id="listing.user?.id"
                                        :initial-score="listing.user?.karma_score ?? 0"
                                    />
                                </td>
                            </tr>
                            <tr v-if="listings.data.length === 0">
                                <td colspan="7" class="px-3 py-6 text-center text-neutral">
                                    No players looking for a party right now.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="listings.links && listings.links.length > 3" class="mt-3 flex items-center gap-1">
                    <template v-for="link in listings.links" :key="link.label">
                        <button
                            v-if="link.url"
                            @click="router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                            class="px-2 py-1 text-xs border border-border"
                            :class="link.active ? 'bg-surface-500 text-white' : 'bg-surface-800 text-neutral hover:text-white'"
                            v-html="link.label"
                        />
                        <span v-else class="px-2 py-1 text-xs text-neutral" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
