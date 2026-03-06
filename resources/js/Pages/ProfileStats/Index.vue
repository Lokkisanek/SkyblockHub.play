<script setup>
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    minecraftUsername: {
        type: String,
        default: null,
    },
});

const username = ref(props.minecraftUsername || '');
const profileData = ref(null);
const loading = ref(false);
const error = ref('');
const selectedProfile = ref(null);

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

        // Auto-select first profile
        const profiles = json.data?.profiles ?? {};
        const keys = Object.keys(profiles);
        if (keys.length > 0) {
            selectedProfile.value = keys[0];
        }
    } catch (e) {
        error.value = 'Network error. Try again.';
    } finally {
        loading.value = false;
    }
}

function selectProfile(key) {
    selectedProfile.value = key;
}

function currentProfile() {
    if (!profileData.value || !selectedProfile.value) return null;
    return profileData.value.profiles?.[selectedProfile.value];
}

function formatStat(val) {
    if (val === null || val === undefined) return '—';
    if (typeof val === 'number') return val.toLocaleString('en-US');
    return String(val);
}

function skillEntries(profile) {
    if (!profile?.data?.skills) return [];
    const skills = profile.data.skills;
    return Object.entries(skills).map(([name, data]) => ({
        name: name.replace(/_/g, ' '),
        level: data?.level ?? 0,
        xp: data?.xp ?? 0,
    }));
}

function slayerEntries(profile) {
    if (!profile?.data?.slayers) return [];
    const slayers = profile.data.slayers;
    return Object.entries(slayers).map(([name, data]) => ({
        name: name.replace(/_/g, ' '),
        level: data?.level?.currentLevel ?? 0,
        xp: data?.xp ?? 0,
    }));
}

function dungeonStats(profile) {
    const dungeon = profile?.data?.dungeons;
    if (!dungeon) return null;
    return {
        catacombsLevel: dungeon.catacombs?.level?.level ?? 0,
        catacombsXp: dungeon.catacombs?.level?.xp ?? 0,
        secrets: dungeon.secrets_found ?? 0,
    };
}

function networth(profile) {
    return profile?.data?.networth?.networth ?? null;
}

onMounted(() => {
    if (username.value) {
        fetchProfile();
    }
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
                <!-- Username Input -->
                <div class="mb-4 flex items-center gap-2">
                    <input
                        v-model="username"
                        type="text"
                        placeholder="Minecraft username"
                        class="bg-surface-800 border border-border rounded-none px-3 py-1.5 text-xs text-white placeholder-neutral focus:outline-none focus:border-border-light w-56"
                        @keyup.enter="fetchProfile"
                    />
                    <button
                        @click="fetchProfile"
                        :disabled="loading"
                        class="px-3 py-1.5 text-xs font-medium border border-border bg-surface-700 text-neutral hover:text-white hover:bg-surface-500 rounded-none disabled:opacity-50"
                    >
                        {{ loading ? 'Loading…' : 'Lookup' }}
                    </button>
                </div>

                <!-- Error -->
                <div v-if="error" class="mb-4 border border-loss bg-surface-800 text-loss text-xs px-3 py-2">
                    {{ error }}
                </div>

                <!-- Loading -->
                <div v-if="loading" class="text-neutral text-xs">Fetching profile data…</div>

                <!-- Profile Data -->
                <div v-if="profileData && !loading">
                    <!-- Profile Selector -->
                    <div class="mb-4 flex items-center gap-1">
                        <button
                            v-for="(profile, key) in profileData.profiles"
                            :key="key"
                            @click="selectProfile(key)"
                            class="px-3 py-1 text-xs border border-border rounded-none"
                            :class="selectedProfile === key ? 'bg-surface-500 text-white' : 'bg-surface-800 text-neutral hover:text-white'"
                        >
                            {{ profile.cute_name || key }}
                        </button>
                    </div>

                    <!-- Selected Profile Stats -->
                    <div v-if="currentProfile()" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <!-- Networth -->
                        <div class="border border-border bg-surface-800 p-3">
                            <h3 class="text-[10px] uppercase tracking-wider text-neutral mb-2">Networth</h3>
                            <div class="text-lg font-bold text-rarity-legendary font-mono">
                                {{ networth(currentProfile()) !== null ? formatStat(Math.round(networth(currentProfile()))) : '—' }}
                            </div>
                        </div>

                        <!-- Skills -->
                        <div class="border border-border bg-surface-800 p-3">
                            <h3 class="text-[10px] uppercase tracking-wider text-neutral mb-2">Skills</h3>
                            <table class="w-full text-xs">
                                <tbody>
                                    <tr v-for="skill in skillEntries(currentProfile())" :key="skill.name" class="border-b border-border last:border-0">
                                        <td class="py-0.5 text-white capitalize">{{ skill.name }}</td>
                                        <td class="py-0.5 text-right text-rarity-uncommon font-mono">Lvl {{ skill.level }}</td>
                                    </tr>
                                    <tr v-if="skillEntries(currentProfile()).length === 0">
                                        <td colspan="2" class="py-1 text-neutral">No skill data</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Slayers -->
                        <div class="border border-border bg-surface-800 p-3">
                            <h3 class="text-[10px] uppercase tracking-wider text-neutral mb-2">Slayers</h3>
                            <table class="w-full text-xs">
                                <tbody>
                                    <tr v-for="slayer in slayerEntries(currentProfile())" :key="slayer.name" class="border-b border-border last:border-0">
                                        <td class="py-0.5 text-white capitalize">{{ slayer.name }}</td>
                                        <td class="py-0.5 text-right text-rarity-epic font-mono">Lvl {{ slayer.level }}</td>
                                        <td class="py-0.5 text-right text-neutral font-mono">{{ formatStat(slayer.xp) }} XP</td>
                                    </tr>
                                    <tr v-if="slayerEntries(currentProfile()).length === 0">
                                        <td colspan="3" class="py-1 text-neutral">No slayer data</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Dungeons -->
                        <div class="border border-border bg-surface-800 p-3">
                            <h3 class="text-[10px] uppercase tracking-wider text-neutral mb-2">Dungeons</h3>
                            <template v-if="dungeonStats(currentProfile())">
                                <div class="space-y-1 text-xs">
                                    <div class="flex justify-between">
                                        <span class="text-neutral">Catacombs Level</span>
                                        <span class="text-rarity-rare font-mono">{{ dungeonStats(currentProfile()).catacombsLevel }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-neutral">Catacombs XP</span>
                                        <span class="text-white font-mono">{{ formatStat(dungeonStats(currentProfile()).catacombsXp) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-neutral">Secrets Found</span>
                                        <span class="text-white font-mono">{{ formatStat(dungeonStats(currentProfile()).secrets) }}</span>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="text-xs text-neutral">No dungeon data</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
