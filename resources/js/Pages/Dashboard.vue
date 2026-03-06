<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-sm font-semibold text-white uppercase tracking-wide">
                Dashboard
            </h2>
        </template>

        <div class="py-4">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="border border-border bg-surface-800">
                    <div class="p-4 text-xs text-neutral">
                        You're logged in. Use the navigation above to access the Bazaar or look up profile stats.
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
