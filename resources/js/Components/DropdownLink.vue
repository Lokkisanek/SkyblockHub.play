<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="block w-full px-4 py-2 text-start text-xs leading-5 text-neutral hover:bg-surface-500 hover:text-white focus:bg-surface-500 focus:outline-none"
    >
        <slot />
    </Link>
</template>
