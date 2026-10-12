<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ rows: Array, from: String, to: String, thresholds: Object });

const fmt = (min) => {
    const m = Number(min || 0);
    const h = Math.floor(Math.abs(m) / 60);
    const r = Math.abs(m) % 60;
    return `${m < 0 ? '-' : ''}${h}h ${r}m`;
};
</script>

<template>
    <Head title="Team Workload" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold">Team Workload</h1>
            <p class="mt-1 text-sm text-slate-500">{{ from }} → {{ to }} · Planned allocation vs capacity</p>
        </div>
        <div v-if="rows?.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Capacity</th>
                        <th class="px-4 py-3">Allocated</th>
                        <th class="px-4 py-3">Remaining</th>
                        <th class="px-4 py-3">Utilization</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Tasks</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="r in rows" :key="r.user.id">
                        <td class="px-4 py-3 font-medium">{{ r.user.name }}</td>
                        <td class="px-4 py-3">{{ fmt(r.available_minutes) }}</td>
                        <td class="px-4 py-3">{{ fmt(r.allocated_minutes) }}</td>
                        <td class="px-4 py-3" :class="r.remaining_minutes < 0 ? 'text-rose-700' : ''">{{ fmt(r.remaining_minutes) }}</td>
                        <td class="px-4 py-3">{{ r.planned_utilization }}%</td>
                        <td class="px-4 py-3"><StatusBadge :status="r.status" /></td>
                        <td class="px-4 py-3 text-xs">{{ r.active_tasks }} active · {{ r.overdue_tasks }} overdue</td>
                        <td class="px-4 py-3 text-right"><Link :href="route('workload.show', r.user.id)" class="text-teal-700 hover:underline">Detail</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="No workload allocated for this period." />
    </AuthenticatedLayout>
</template>
