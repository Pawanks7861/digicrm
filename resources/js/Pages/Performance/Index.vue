<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    reports: Object,
    clients: Array,
    filters: Object,
    can: { type: Object, default: () => ({}) },
});
</script>

<template>
    <Head title="Performance" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Performance</h1>
                <p class="mt-1 text-sm text-slate-500">Report center, sources, and sync history</p>
            </div>
            <div class="flex flex-wrap gap-2 text-sm">
                <Link v-if="can.create" :href="route('client-reports.create')" class="rounded-lg bg-teal-700 px-3 py-1.5 text-white">New report</Link>
                <Link v-if="can.sources" :href="route('performance.data-sources')" class="rounded-lg border border-slate-200 px-3 py-1.5">Data sources</Link>
                <Link :href="route('performance.mappings')" class="rounded-lg border border-slate-200 px-3 py-1.5">Mappings</Link>
                <Link :href="route('performance.sync-history')" class="rounded-lg border border-slate-200 px-3 py-1.5">Sync history</Link>
                <Link :href="route('performance.manual')" class="rounded-lg border border-slate-200 px-3 py-1.5">Manual entry</Link>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Report</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Period</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in reports.data" :key="r.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">
                            <Link :href="route('client-reports.show', r.id)" class="font-medium text-teal-800 hover:underline">{{ r.title }}</Link>
                            <div class="text-xs text-slate-400">{{ r.report_number }}</div>
                        </td>
                        <td class="px-4 py-3">{{ r.client?.company_name }}</td>
                        <td class="px-4 py-3">{{ r.period_start }} → {{ r.period_end }}</td>
                        <td class="px-4 py-3 capitalize">{{ r.status?.replace('_', ' ') }}</td>
                    </tr>
                    <tr v-if="!reports.data?.length">
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">No Data</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
