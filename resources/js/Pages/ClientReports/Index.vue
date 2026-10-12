<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    reports: Object,
    clients: Array,
    can: { type: Object, default: () => ({}) },
});
</script>

<template>
    <Head title="Client Reports" />
    <AuthenticatedLayout>
        <div class="mb-6 flex justify-between gap-3">
            <h1 class="text-2xl font-semibold">Client Performance Reports</h1>
            <Link v-if="can.create" :href="route('client-reports.create')" class="rounded-lg bg-teal-700 px-3 py-1.5 text-sm text-white">Generate draft</Link>
        </div>
        <div class="overflow-hidden rounded-xl border bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr><th class="px-4 py-3">Report</th><th class="px-4 py-3">Client</th><th class="px-4 py-3">Status</th></tr>
                </thead>
                <tbody>
                    <tr v-for="r in reports.data" :key="r.id" class="border-t">
                        <td class="px-4 py-3"><Link :href="route('client-reports.show', r.id)" class="text-teal-800 hover:underline">{{ r.title }}</Link></td>
                        <td class="px-4 py-3">{{ r.client?.company_name }}</td>
                        <td class="px-4 py-3 capitalize">{{ r.status }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
