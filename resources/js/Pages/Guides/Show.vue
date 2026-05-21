<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GuidesLayout from '@/Components/Guides/GuidesLayout.vue';
import GuidesContent from '@/Components/Guides/GuidesContent.vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/strings/useI18n';

const { t } = useI18n();

const props = defineProps({
    guide: {
        type: Object,
        required: true,
    },
    patches: {
        type: Array,
        default: () => [],
    },
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
    seo: {
        type: Object,
        default: () => ({}),
    },
});

</script>

<template>
    <AuthenticatedLayout>
        <GuidesLayout
            v-if="guide"
            :current-slug="guide.slug"
            :guide-groups="guideGroups"
            :guide-external-tools="guideExternalTools"
            :guide-search-index="guideSearchIndex"
        >
            <article class="guides-article">
                <nav class="guides-breadcrumb" aria-label="Breadcrumb">
                    <Link :href="route('guides')">Guides</Link>
                    <span aria-hidden="true">/</span>
                    <span>{{ guide.title }}</span>
                </nav>

                <div class="guides-title-row">
                    <div>
                        <h1 class="guides-page-title">{{ guide.title }}</h1>
                        <p v-if="guide.lastUpdated" class="guides-meta">
                            Last updated: {{ guide.lastUpdated }}
                        </p>
                    </div>
                    <Link :href="route('guides.suggest-edit', guide.slug)" class="guides-action-btn guides-action-btn--subtle">
                        Suggest improvement
                    </Link>
                </div>

                <GuidesContent :sections="guide.sections" />

                <section v-if="guide.slug === 'news' && patches.length" class="guides-patches">
                    <h2 class="guides-section-title">Latest patch notes</h2>
                    <p class="guides-patches-note">From Hypixel Forums · refreshes hourly</p>
                    <ul class="guides-patch-list">
                        <li v-for="patch in patches" :key="patch.url" class="guides-patch-item">
                            <a
                                :href="patch.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="guides-patch-link"
                            >
                                {{ patch.title }} ↗
                            </a>
                            <span v-if="patch.date" class="guides-patch-date">{{ patch.date }}</span>
                            <p v-if="patch.preview" class="guides-patch-preview">{{ patch.preview }}</p>
                        </li>
                    </ul>
                </section>

                <section v-if="guide.usefulLinks?.length" class="guides-useful">
                    <h2 class="guides-section-title">References</h2>
                    <ul class="guides-useful-list">
                        <li v-for="link in guide.usefulLinks" :key="link.url">
                            <a
                                :href="link.url"
                                :target="link.external ? '_blank' : undefined"
                                :rel="link.external ? 'noopener noreferrer' : undefined"
                                class="guides-ext-link"
                            >
                                {{ link.label }}
                                <span v-if="link.external" aria-hidden="true">↗</span>
                            </a>
                        </li>
                    </ul>
                </section>
            </article>
        </GuidesLayout>
    </AuthenticatedLayout>
</template>
