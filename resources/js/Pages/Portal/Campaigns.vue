<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ campaigns: Array });
const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head title="Campaigns" />
    <PortalLayout>
        <h1 class="text-2xl font-semibold tracking-tight">Campaigns</h1>
        <div v-if="campaigns.length" class="mt-6 space-y-3">
            <div v-for="c in campaigns" :key="c.id" class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="font-semibold">{{ c.name }}</div>
                        <div class="text-xs text-slate-400">{{ c.code }}</div>
                    </div>
                    <StatusBadge :status="c.status" />
                </div>
                <p class="mt-2 text-sm text-slate-600">{{ c.objective || '—' }}</p>
                <div class="mt-2 text-xs text-slate-500">
                    {{ c.start_date || '—' }} → {{ c.end_date || '—' }} · <span class="capitalize">{{ label(c.campaign_type) }}</span>
                    · {{ c.deliverables_count }} deliverables
                </div>
            </div>
        </div>
        <EmptyState v-else class="mt-8" title="No campaigns yet." />
    </PortalLayout>
</template>
