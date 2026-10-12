<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ rows: Array, from: String, to: String });
const hours = (sec) => `${(Number(sec || 0) / 3600).toFixed(2)}h`;
</script>

<template>
    <Head title="Planned vs Actual" />
    <AuthenticatedLayout>
        <div class="mb-6 flex justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Planned vs Actual</h1>
                <p class="mt-1 text-sm text-slate-500">{{ from }} → {{ to }} · variance = actual − estimated</p>
            </div>
            <a :href="route('reports.planned-vs-actual', { export: 1 })" class="text-sm text-teal-700 hover:underline">Export CSV</a>
        </div>
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table v-if="rows?.length" class="min-w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Task</th>
                        <th class="px-4 py-3">Estimated</th>
                        <th class="px-4 py-3">Actual</th>
                        <th class="px-4 py-3">Variance</th>
                        <th class="px-4 py-3">Variance %</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="r in rows" :key="r.task_id">
                        <td class="px-4 py-3">{{ r.title }}</td>
                        <td class="px-4 py-3">{{ hours(r.estimated_seconds) }}</td>
                        <td class="px-4 py-3">{{ hours(r.actual_seconds) }}</td>
                        <td class="px-4 py-3" :class="r.variance_seconds > 0 ? 'text-rose-700' : ''">{{ hours(r.variance_seconds) }}</td>
                        <td class="px-4 py-3">{{ r.variance_percent != null ? r.variance_percent + '%' : 'N/A' }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else class="m-6" title="No records match these filters." />
        </div>
    </AuthenticatedLayout>
</template>
