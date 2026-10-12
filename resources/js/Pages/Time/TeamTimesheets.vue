<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ rows: Array, from: String, to: String });

const fmt = (min) => {
    const m = Number(min || 0);
    const h = Math.floor(m / 60);
    const r = m % 60;
    return `${h}h ${r}m`;
};
</script>

<template>
    <Head title="Team Timesheets" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold">Team Timesheets</h1>
            <p class="mt-1 text-sm text-slate-500">{{ from }} → {{ to }}</p>
        </div>
        <div v-if="rows?.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Capacity</th>
                        <th class="px-4 py-3">Logged</th>
                        <th class="px-4 py-3">Billable</th>
                        <th class="px-4 py-3">Actual %</th>
                        <th class="px-4 py-3">Billable %</th>
                        <th class="px-4 py-3">Planned %</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="r in rows" :key="r.user.id">
                        <td class="px-4 py-3 font-medium">{{ r.user.name }}</td>
                        <td class="px-4 py-3">{{ fmt(r.available_minutes) }}</td>
                        <td class="px-4 py-3">{{ fmt(r.logged_minutes) }}</td>
                        <td class="px-4 py-3">{{ fmt(r.billable_minutes) }}</td>
                        <td class="px-4 py-3">{{ r.actual_utilization }}%</td>
                        <td class="px-4 py-3">{{ r.billable_utilization }}%</td>
                        <td class="px-4 py-3">{{ r.planned_utilization }}%</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="No tracked time for this range." />
        <Link :href="route('timesheets.export')" class="mt-4 inline-block text-sm text-teal-700 hover:underline">Export CSV</Link>
    </AuthenticatedLayout>
</template>
