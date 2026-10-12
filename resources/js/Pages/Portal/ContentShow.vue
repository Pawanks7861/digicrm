<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ item: Object });

const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head :title="item.title" />
    <PortalLayout>
        <Link :href="route('portal.content')" class="text-sm text-teal-700 hover:underline">← Content</Link>
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-semibold">{{ item.title }}</h1>
            <StatusBadge :status="item.status" />
        </div>
        <div class="mt-2 text-sm text-slate-500 capitalize">{{ label(item.platform) }} · {{ item.campaign?.name }}</div>

        <div class="mt-6 space-y-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <div><span class="text-slate-400">Caption</span><p class="mt-1 whitespace-pre-wrap">{{ item.caption || '—' }}</p></div>
            <div><span class="text-slate-400">Hashtags</span><p class="mt-1">{{ item.hashtags || '—' }}</p></div>
            <a v-if="item.published_url" :href="item.published_url" target="_blank" rel="noopener" class="inline-block text-teal-700 hover:underline">Open Post</a>
        </div>

        <div v-if="item.comments?.length" class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
            <h3 class="text-sm font-semibold">Comments</h3>
            <div v-for="c in item.comments" :key="c.id" class="mt-2 border-t border-slate-100 pt-2 text-sm">
                <div class="text-xs text-slate-400">{{ c.user }} · {{ new Date(c.created_at).toLocaleString() }}</div>
                <p class="mt-1">{{ c.comment }}</p>
            </div>
        </div>
    </PortalLayout>
</template>
