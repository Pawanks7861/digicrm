<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ rows: Array, from: String, to: String });

const fmt = (min) => `${Math.floor((min||0)/60)}h ${(min||0)%60}m`;
</script>

<template>
    <Head title="Utilization" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold">Utilization</h1>
            <p class="mt-1 text-sm text-slate-500">Operational planning metrics — not performance rankings. {{ from }} → {{ to }}</p>
        </div>
        <div v-if="rows?.length" class="overflow-hidden rounded-xl border bg-white">
            <table class="min-w-full divide-y text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Actual</th>
                        <th class="px-4 py-3">Billable</th>
                        <th class="px-4 py-3">Planned</th>
                        <th class="px-4 py-3">Logged</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="r in rows" :key="r.user.id">
                        <td class="px-4 py-3 font-medium">{{ r.user.name }}</td>
                        <td class="px-4 py-3">{{ r.actual_utilization }}%</td>
                        <td class="px-4 py-3">{{ r.billable_utilization }}%</td>
                        <td class="px-4 py-3">{{ r.planned_utilization }}%</td>
                        <td class="px-4 py-3">{{ fmt(r.logged_minutes) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="No tracked time for this range." />
    </AuthenticatedLayout>
</template>
