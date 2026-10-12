<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    report: Object,
    can: { type: Object, default: () => ({}) },
});

function action(name) {
    router.post(route(name, props.report.id));
}
</script>

<template>
    <Head :title="report.title" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <Link :href="route('client-reports.index')" class="text-sm text-teal-700 hover:underline">← Reports</Link>
                <h1 class="mt-2 text-2xl font-semibold">{{ report.title }}</h1>
                <p class="text-sm text-slate-500">{{ report.report_number }} · {{ report.client?.company_name }} · {{ report.period_start }} → {{ report.period_end }}</p>
                <span class="mt-2 inline-block rounded-full bg-slate-100 px-2 py-0.5 text-xs capitalize">{{ report.status }}</span>
            </div>
            <div class="flex flex-wrap gap-2 text-sm">
                <a :href="route('client-reports.pdf', report.id)" class="rounded-lg border px-3 py-1.5">PDF</a>
                <button v-if="can.edit && report.status === 'draft'" class="rounded-lg border px-3 py-1.5" @click="action('client-reports.submit-review')">Submit review</button>
                <button v-if="can.publish && ['draft','internal_review'].includes(report.status)" class="rounded-lg border px-3 py-1.5" @click="action('client-reports.approve')">Approve</button>
                <button v-if="can.publish && report.status === 'approved'" class="rounded-lg bg-teal-700 px-3 py-1.5 text-white" @click="action('client-reports.publish')">Publish</button>
            </div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="m in report.metrics" :key="m.id" class="rounded-xl border bg-white p-4">
                <div class="text-xs uppercase text-slate-500">{{ m.label }}</div>
                <div class="mt-2 text-xl font-semibold">{{ m.display_value }}</div>
                <div v-if="m.formula_label" class="mt-1 text-xs text-slate-400">{{ m.formula_label }}</div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
