<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    approvals: Array,
});

const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head title="Approvals" />
    <PortalLayout>
        <h1 class="text-2xl font-semibold tracking-tight">Pending Approvals</h1>
        <p class="mt-1 text-sm text-slate-500">Review creatives waiting for your decision.</p>

        <div v-if="approvals.length" class="mt-6 grid gap-4">
            <Link
                v-for="a in approvals"
                :key="a.id"
                :href="route('portal.approvals.show', a.id)"
                class="rounded-2xl border border-slate-200 bg-white p-4 hover:border-teal-300"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="text-lg font-semibold">{{ a.content.title }}</div>
                        <div class="mt-1 text-sm text-slate-500 capitalize">
                            {{ label(a.content.platform) }} · {{ a.content.campaign?.name || '—' }} · Rev {{ a.revision_number }}
                        </div>
                    </div>
                    <StatusBadge :status="a.is_overdue ? 'overdue' : 'pending'" />
                </div>
                <p class="mt-3 line-clamp-2 text-sm text-slate-600">{{ a.content.caption || 'No caption' }}</p>
            </Link>
        </div>
        <EmptyState v-else class="mt-8" title="You're all caught up." description="No content needs your approval." />
    </PortalLayout>
</template>
