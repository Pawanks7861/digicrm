<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    can: { type: Object, default: () => ({}) },
});

const operational = [
    { name: 'Planned vs Actual', route: 'reports.planned-vs-actual', desc: 'Estimate vs logged hours' },
    { name: 'Delivery Performance', route: 'reports.delivery-performance', desc: 'On-time delivery & delays' },
    { name: 'Scope Impact', route: 'reports.scope-impact', desc: 'Overage decisions & values' },
];

const financial = [
    { name: 'Client Profitability', route: 'reports.client-profitability', desc: 'Operational revenue vs cost' },
    { name: 'Campaign Profitability', route: 'reports.campaign-profitability', desc: 'Campaign margin analysis' },
    { name: 'Package Profitability', route: 'reports.package-profitability', desc: 'Retainer effort vs value' },
    { name: 'Complimentary Work', route: 'reports.complimentary-work', desc: 'Waived overage value' },
];
</script>

<template>
    <Head title="Reports" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Reports</h1>
            <p class="mt-1 text-sm text-slate-500">Operational and financial intelligence. Values are contracted/operational — not cash collected.</p>
        </div>

        <h2 class="text-sm font-semibold text-slate-700">Operational</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="r in operational"
                :key="r.route"
                :href="route(r.route)"
                class="rounded-xl border border-slate-200 bg-white p-4 hover:border-teal-300"
            >
                <div class="font-medium text-slate-900">{{ r.name }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ r.desc }}</div>
            </Link>
        </div>

        <template v-if="can.financial">
            <h2 class="mt-8 text-sm font-semibold text-slate-700">Financial</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="r in financial"
                    :key="r.route"
                    :href="route(r.route)"
                    class="rounded-xl border border-slate-200 bg-white p-4 hover:border-teal-300"
                >
                    <div class="font-medium text-slate-900">{{ r.name }}</div>
                    <div class="mt-1 text-sm text-slate-500">{{ r.desc }}</div>
                </Link>
            </div>
        </template>

        <div v-if="can.cost_rates" class="mt-8">
            <Link :href="route('cost-rates.index')" class="text-sm font-medium text-teal-700 hover:underline">Internal Cost Rates →</Link>
        </div>
    </AuthenticatedLayout>
</template>
