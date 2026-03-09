<script setup>
/**
 * PackSelector — SkyCrypt-style resource pack toggle modal.
 * Shows available texture packs with toggle switches.
 * Persists selection in localStorage.
 */
import { ref, onMounted, watch } from 'vue';

const emit = defineEmits(['update:packs']);

const STORAGE_KEY = 'skyblock_texture_packs';

// Available packs (loaded from config.json)
const packs = ref([]);
const showModal = ref(false);
const enabledPacks = ref([]);

// Default enabled packs (FurfSky Reborn first, higher priority)
const DEFAULT_PACKS = ['FURFSKY_REBORN'];

const PACK_CONFIGS = [
    {
        id: 'FURFSKY_REBORN',
        folder: 'FurfSky_Reborn',
        name: 'FurfSky Reborn',
        version: 'v1.9.0',
        author: 'The Reborn Team',
        priority: 250,
    },
    {
        id: 'HYPIXELPLUS',
        folder: 'Hypixel_Plus',
        name: 'Hypixel Plus',
        version: 'v0.23.4',
        author: 'ic22487',
        priority: 125,
    },
];

function loadPreferences() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            enabledPacks.value = JSON.parse(stored);
        } else {
            enabledPacks.value = [...DEFAULT_PACKS];
        }
    } catch {
        enabledPacks.value = [...DEFAULT_PACKS];
    }
}

function savePreferences() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(enabledPacks.value));
    emit('update:packs', enabledPacks.value);
}

function togglePack(packId) {
    const idx = enabledPacks.value.indexOf(packId);
    if (idx >= 0) {
        enabledPacks.value.splice(idx, 1);
    } else {
        enabledPacks.value.push(packId);
    }
    savePreferences();
}

function isEnabled(packId) {
    return enabledPacks.value.includes(packId);
}

onMounted(() => {
    packs.value = PACK_CONFIGS;
    loadPreferences();
    emit('update:packs', enabledPacks.value);
});
</script>

<template>
    <!-- Toggle button -->
    <button
        @click="showModal = !showModal"
        class="p-1.5 rounded border border-border text-neutral hover:text-white hover:border-border-light transition relative"
        title="Texture Packs"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
    </button>

    <!-- Modal overlay -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-[9998] flex items-start justify-center pt-24" @click.self="showModal = false">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showModal = false"></div>

            <!-- Modal -->
            <div class="relative z-10 w-full max-w-md bg-surface-900 border border-border rounded-lg shadow-2xl overflow-hidden">
                <!-- Tab bar (like SkyCrypt) -->
                <div class="flex border-b border-border bg-surface-800">
                    <div class="flex-1 flex items-center justify-center gap-1.5 py-2.5 text-sm font-semibold text-profit border-b-2 border-profit cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                        </svg>
                        Packs
                    </div>
                </div>

                <!-- Content -->
                <div class="p-5">
                    <div class="mb-4">
                        <h3 class="text-sm font-bold text-white flex items-center gap-1.5 mb-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-profit" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                            </svg>
                            Packs
                        </h3>
                        <p class="text-xs text-neutral leading-relaxed">
                            Resource packs change the textures of items, mobs and other elements in SkyCrypt.
                        </p>
                        <p class="text-xs text-neutral leading-relaxed mt-1">
                            You can enable or disable as many packs as you want, but their preference order can't be changed.
                        </p>
                    </div>

                    <!-- Pack list -->
                    <div class="space-y-2">
                        <div
                            v-for="pack in packs"
                            :key="pack.id"
                            class="flex items-center gap-3 p-3 rounded-lg border transition-colors"
                            :class="isEnabled(pack.id)
                                ? 'border-profit/30 bg-profit/5'
                                : 'border-border bg-surface-800'"
                        >
                            <!-- Pack icon -->
                            <img
                                :src="`/resourcepacks/${pack.folder}/pack.png`"
                                :alt="pack.name"
                                class="w-10 h-10 rounded image-rendering-pixelated"
                                loading="lazy"
                            />

                            <!-- Pack info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-sm font-bold text-profit">{{ pack.name }}</span>
                                    <span class="text-[10px] text-neutral">{{ pack.version }}</span>
                                </div>
                                <div class="text-[11px] text-neutral">
                                    by {{ pack.author }}
                                </div>
                            </div>

                            <!-- Toggle switch -->
                            <button
                                @click="togglePack(pack.id)"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
                                :class="isEnabled(pack.id) ? 'bg-profit' : 'bg-surface-600'"
                            >
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="isEnabled(pack.id) ? 'translate-x-6' : 'translate-x-1'"
                                ></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.image-rendering-pixelated {
    image-rendering: pixelated;
    image-rendering: -moz-crisp-edges;
}
</style>
