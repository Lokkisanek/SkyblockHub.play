<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GuidesLayout from '@/Components/Guides/GuidesLayout.vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/strings/useI18n';

const { t } = useI18n();

defineProps({
    guideGroups: {
        type: Array,
        default: () => [],
    },
    guideExternalTools: {
        type: Array,
        default: () => [],
    },
    guideSearchIndex: {
        type: Array,
        default: () => [],
    },
    quickLinks: {
        type: Array,
        default: () => [],
    },
    seo: {
        type: Object,
        default: () => ({}),
    },
});
</script>

<template>
    <AuthenticatedLayout>
        <GuidesLayout
            :guide-groups="guideGroups"
            :guide-external-tools="guideExternalTools"
            :guide-search-index="guideSearchIndex"
        >
            <div class="guides-index">
                <p class="guides-eyebrow">Hypixel SkyBlock</p>
                <div class="guides-title-row">
                    <div>
                        <h1 class="guides-page-title">SkyBlock Guides</h1>
                        <p class="guides-lead">
                            Practical Hypixel SkyBlock guides, checklists, and reference tables.
                            Pick a topic or add a guide for admin review.
                        </p>
                    </div>
                    <Link :href="route('guides.submit')" class="guides-action-btn guides-action-btn--primary">
                        <span aria-hidden="true">+</span>
                        Add guide
                    </Link>
                </div>

                <nav class="guides-topics-bar" aria-label="All topics">
                    <a href="#getting-started" class="guides-pill">Start here</a>
                    <Link
                        v-for="topic in guideGroups.flatMap((group) => group.topics)"
                        :key="topic.slug"
                        :href="route('guides.show', topic.slug)"
                        class="guides-pill"
                    >
                        {{ topic.title }}
                    </Link>
                </nav>

                <section
                    v-for="group in guideGroups"
                    :key="group.id"
                    class="guides-topic-group"
                >
                    <h2 class="guides-group-title">{{ group.label }}</h2>
                    <ul class="guides-card-list">
                        <li v-for="topic in group.topics" :key="topic.slug">
                            <Link :href="route('guides.show', topic.slug)" class="guides-card">
                                <span class="guides-card-name">{{ topic.title }}</span>
                                <span class="guides-card-desc">{{ topic.description }}</span>
                                <span class="guides-card-action">Open →</span>
                            </Link>
                        </li>
                    </ul>
                </section>

                <section id="getting-started" class="guides-start">
                    <h2 class="guides-group-title">Start here</h2>
                    <p class="guides-start-sub">Common quick checks and where to go.</p>
                    <ul class="guides-start-list">
                        <li v-for="item in quickLinks" :key="item.url">
                            <span class="guides-start-prompt">{{ item.prompt }}</span>
                            <a
                                :href="item.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="guides-ext-link"
                            >
                                {{ item.label }} ↗
                            </a>
                        </li>
                    </ul>
                </section>
            </div>
        </GuidesLayout>
    </AuthenticatedLayout>
</template>
