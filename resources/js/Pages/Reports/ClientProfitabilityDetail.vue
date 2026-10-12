<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    detail: { type: Object, required: true },
    delivery: { type: Object, default: () => ({}) },
    approvals: { type: Object, default: () => ({}) },
    from: String,
    to: String,
});

const money = (v) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(v || 0));
const hours = (sec) => `${(Number(sec || 0) / 3600).toFixed(1)}h`;
</script>

<template>
    <Head :title="`Profitability · ${detail.client.company_name}`" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('reports.client-profitability')" class="text-sm text-teal-700 hover:underline">← Client Profitability</Link>
            <h1 class="mt-2 text-2xl font-semibold">{{ detail.client.company_name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ from }} → {{ to }}</p>
        </div>

        <p v-if="detail.data_completeness === 'missing_cost_rates'" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
            Some logged time does not have an internal cost rate ({{ detail.missing_cost_entries }} entries / {{ hours(detail.missing_cost_seconds) }}).
        </p>

        <h2 class="text-sm font-semibold">Revenue Breakdown</h2>
        <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Package</div><div class="mt-1 text-xl font-semibold">{{ money(detail.package_contracted_value) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Chargeable Overages</div><div class="mt-1 text-xl font-semibold">{{ money(detail.additional_charge_value) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Standalone Campaigns</div><div class="mt-1 text-xl font-semibold">{{ money(detail.campaign_standalone_value) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Operational Revenue</div><div class="mt-1 text-xl font-semibold text-teal-800">{{ money(detail.operational_revenue) }}</div></div>
        </div>

        <h2 class="mt-8 text-sm font-semibold">Cost Breakdown</h2>
        <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Labor</div><div class="mt-1 text-xl font-semibold">{{ money(detail.labor_cost) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Campaign Direct</div><div class="mt-1 text-xl font-semibold">{{ money(detail.campaign_internal_cost) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Operational Costs</div><div class="mt-1 text-xl font-semibold">{{ money(detail.operational_costs) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Total Cost</div><div class="mt-1 text-xl font-semibold">{{ money(detail.total_cost) }}</div></div>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Est. Gross Profit</div><div class="mt-1 text-xl font-semibold">{{ money(detail.estimated_gross_profit) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Gross Margin</div><div class="mt-1 text-xl font-semibold">{{ detail.estimated_gross_margin != null ? detail.estimated_gross_margin + '%' : 'N/A' }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Complimentary Work</div><div class="mt-1 text-xl font-semibold text-amber-700">{{ money(detail.complimentary_work_value) }}</div></div>
        </div>

        <h2 class="mt-8 text-sm font-semibold">Delivery</h2>
        <div class="mt-2 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border bg-white p-4">
                <div class="text-xs text-slate-400">On-Time</div>
                <div class="mt-1 text-xl font-semibold">
                    <template v-if="delivery.with_due_date">{{ delivery.on_time }} / {{ delivery.with_due_date }} ({{ delivery.rate }}%)</template>
                    <template v-else>—</template>
                </div>
            </div>
            <div class="rounded-xl border bg-white p-4">
                <div class="text-xs text-slate-400">Avg Approval Response</div>
                <div class="mt-1 text-xl font-semibold">{{ approvals.average_seconds != null ? hours(approvals.average_seconds) : '—' }}</div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
