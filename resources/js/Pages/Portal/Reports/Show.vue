<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ report: Object, metrics: Array });
</script>

<template>
    <Head :title="report.title" />
    <PortalLayout>
        <Link :href="route('portal.reports')" class="text-sm text-teal-700 hover:underline">← Reports</Link>
        <h1 class="mt-2 text-xl font-semibold">{{ report.title }}</h1>
        <p class="text-sm text-slate-500">{{ report.period_start }} → {{ report.period_end }}</p>
        <a :href="route('portal.reports.pdf', report.id)" class="mt-3 inline-block text-sm text-teal-700 hover:underline">Download PDF</a>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div v-for="m in metrics" :key="m.id" class="rounded-xl border bg-white p-4">
                <div class="text-xs uppercase text-slate-500">{{ m.label }}</div>
                <div class="mt-2 text-xl font-semibold">{{ m.display_value }}</div>
            </div>
        </div>
    </PortalLayout>
</template>
