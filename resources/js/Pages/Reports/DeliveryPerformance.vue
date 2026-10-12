<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    on_time: Object,
    approvals: Object,
    delays: Object,
    from: String,
    to: String,
});
const hours = (sec) => `${(Number(sec || 0) / 3600).toFixed(1)}h`;
</script>

<template>
    <Head title="Delivery Performance" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold">Delivery Performance</h1>
            <p class="mt-1 text-sm text-slate-500">{{ from }} → {{ to }} · on-time uses client_due_date only</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border bg-white p-5">
                <div class="text-xs uppercase text-slate-400">On-Time Delivery</div>
                <div class="mt-2 text-2xl font-semibold">
                    <template v-if="on_time?.with_due_date">
                        {{ on_time.on_time }} / {{ on_time.with_due_date }}
                        <span class="text-base text-slate-500">({{ on_time.rate }}%)</span>
                    </template>
                    <template v-else>—</template>
                </div>
            </div>
            <div class="rounded-xl border bg-white p-5">
                <div class="text-xs uppercase text-slate-400">Late</div>
                <div class="mt-2 text-2xl font-semibold text-rose-700">{{ on_time?.late ?? 0 }}</div>
            </div>
            <div class="rounded-xl border bg-white p-5">
                <div class="text-xs uppercase text-slate-400">Avg Approval Response</div>
                <div class="mt-2 text-2xl font-semibold">
                    {{ approvals?.average_seconds != null ? hours(approvals.average_seconds) : '—' }}
                </div>
            </div>
        </div>

        <h2 class="mt-8 text-sm font-semibold">Delay by Category</h2>
        <ul v-if="delays?.by_reason?.length" class="mt-2 space-y-2">
            <li v-for="r in delays.by_reason" :key="r.key" class="flex justify-between rounded-xl border bg-white px-4 py-3 text-sm">
                <span class="capitalize">{{ r.key?.replaceAll('_', ' ') }}</span>
                <span>{{ r.count }} · {{ hours(r.seconds) }}</span>
            </li>
        </ul>
        <EmptyState v-else class="mt-4" title="No delay data for this range." />
    </AuthenticatedLayout>
</template>
