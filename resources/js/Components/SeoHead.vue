<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    title: { type: String, default: null },
    description: { type: String, default: null },
    robots: { type: String, default: null },
});

const page = usePage();

const seo = computed(() => {
    const shared = page.props.seo ?? {};

    return {
        title: props.title ?? shared.title ?? 'SkyblockHub',
        description: props.description ?? shared.description ?? '',
        ogTitle: shared.ogTitle ?? props.title ?? shared.title ?? 'SkyblockHub',
        ogDescription: shared.ogDescription ?? props.description ?? shared.description ?? '',
        ogImage: shared.ogImage ?? '/img/logo-white.webp',
        ogType: shared.ogType ?? 'website',
        canonical: shared.canonical ?? null,
        robots: props.robots ?? shared.robots ?? 'index, follow',
    };
});

const absoluteUrl = (path) => {
    if (!path) {
        return '';
    }
    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }
    const origin = typeof window !== 'undefined' ? window.location.origin : '';
    return `${origin}${path.startsWith('/') ? path : `/${path}`}`;
};

const canonicalUrl = computed(() => {
    if (seo.value.canonical) {
        return absoluteUrl(seo.value.canonical);
    }
    if (typeof window !== 'undefined') {
        return window.location.href.split('#')[0].split('?')[0];
    }

    return '';
});

const ogImageUrl = computed(() => absoluteUrl(seo.value.ogImage));
</script>

<template>
    <Head>
        <title head-key="title">{{ seo.title }}</title>
        <meta head-key="description" name="description" :content="seo.description" />
        <meta head-key="robots" name="robots" :content="seo.robots" />
        <link v-if="canonicalUrl" head-key="canonical" rel="canonical" :href="canonicalUrl" />

        <meta head-key="og:site_name" property="og:site_name" content="SkyblockHub" />
        <meta head-key="og:type" property="og:type" :content="seo.ogType" />
        <meta head-key="og:title" property="og:title" :content="seo.ogTitle" />
        <meta head-key="og:description" property="og:description" :content="seo.ogDescription" />
        <meta head-key="og:image" property="og:image" :content="ogImageUrl" />
        <meta v-if="canonicalUrl" head-key="og:url" property="og:url" :content="canonicalUrl" />

        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="seo.ogTitle" />
        <meta head-key="twitter:description" name="twitter:description" :content="seo.ogDescription" />
        <meta head-key="twitter:image" name="twitter:image" :content="ogImageUrl" />
    </Head>
</template>
