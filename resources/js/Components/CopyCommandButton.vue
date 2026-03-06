<script setup>
import { ref } from 'vue';

const props = defineProps({
    productId: {
        type: String,
        required: true,
    },
});

const copied = ref(false);
const mode = ref('buy'); // 'buy' or 'sell'

function toggleMode() {
    mode.value = mode.value === 'buy' ? 'sell' : 'buy';
    copied.value = false;
}

function command() {
    if (mode.value === 'buy') {
        return `/bz buy ${props.productId}`;
    }
    return `/bz sell ${props.productId}`;
}

async function copyCommand() {
    try {
        await navigator.clipboard.writeText(command());
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // Fallback for non-HTTPS / older browsers
        const textarea = document.createElement('textarea');
        textarea.value = command();
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    }
}
</script>

<template>
    <div class="inline-flex items-center gap-0">
        <!-- Mode toggle -->
        <button
            @click="toggleMode"
            class="px-1.5 py-0.5 text-[10px] font-medium border border-border rounded-none"
            :class="mode === 'buy'
                ? 'bg-surface-700 text-rarity-uncommon border-r-0'
                : 'bg-surface-700 text-loss border-r-0'"
        >
            {{ mode === 'buy' ? 'BUY' : 'SELL' }}
        </button>

        <!-- Copy button: flat gray → flat green on click -->
        <button
            @click="copyCommand"
            class="px-2 py-0.5 text-[10px] font-medium border border-border rounded-none transition-colors duration-0"
            :class="copied
                ? 'bg-[#2a3a2a] text-profit border-profit'
                : 'bg-surface-700 text-neutral hover:text-white'"
        >
            {{ copied ? 'Copied!' : 'Copy' }}
        </button>
    </div>
</template>
