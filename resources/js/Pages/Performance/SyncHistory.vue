<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ runs: Object, filters: Object });
</script>

<template>
    <Head title="Sync History" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('performance.index')" class="text-sm text-teal-700 hover:underline">← Performance</Link>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Sync History</h1>
        </div>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Provider</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Rows</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in runs.data" :key="r.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ r.started_at }}</td>
                        <td class="px-4 py-3">{{ r.client?.company_name || '—' }}</td>
                        <td class="px-4 py-3">{{ r.provider }} / {{ r.sync_type }}</td>
                        <td class="px-4 py-3 capitalize">{{ r.status }}</td>
                        <td class="px-4 py-3">{{ r.rows_upserted }} / {{ r.rows_fetched }}</td>
                    </tr>
                    <tr v-if="!runs.data?.length"><td colspan="5" class="px-4 py-8 text-center text-slate-500">No Data</td></tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
