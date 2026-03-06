<script setup>
import { ref } from 'vue';

const props = defineProps({
    targetId: {
        type: Number,
        required: true,
    },
    initialScore: {
        type: Number,
        default: 0,
    },
});

const score = ref(props.initialScore);
const myVote = ref(0); // -1, 0, or 1
const loading = ref(false);

async function vote(value) {
    if (loading.value) return;
    loading.value = true;

    try {
        const res = await fetch('/api/karma/vote', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                target_id: props.targetId,
                value: value,
            }),
        });

        if (res.ok) {
            const data = await res.json();
            score.value = data.karma_score;
            myVote.value = data.my_vote;
        }
    } catch (e) {
        // silently fail
    } finally {
        loading.value = false;
    }
}

function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    if (match) return decodeURIComponent(match[2]);
    return '';
}
</script>

<template>
    <div class="inline-flex items-center gap-0">
        <!-- Upvote arrow -->
        <button
            @click="vote(1)"
            :disabled="loading"
            class="px-1 py-0 text-sm leading-none select-none"
            :class="myVote === 1 ? 'text-profit' : 'text-neutral hover:text-profit'"
        >▲</button>

        <!-- Score -->
        <span
            class="text-xs font-mono min-w-[20px] text-center"
            :class="score > 0 ? 'text-profit' : score < 0 ? 'text-loss' : 'text-neutral'"
        >{{ score }}</span>

        <!-- Downvote arrow -->
        <button
            @click="vote(-1)"
            :disabled="loading"
            class="px-1 py-0 text-sm leading-none select-none"
            :class="myVote === -1 ? 'text-loss' : 'text-neutral hover:text-loss'"
        >▼</button>
    </div>
</template>
