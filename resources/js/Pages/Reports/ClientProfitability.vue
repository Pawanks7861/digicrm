<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    rows: { type: Array, default: () => [] },
    from: String,
    to: String,
    period: { type: String, default: 'month' },
});

const money = (v) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(v || 0));
</script>

<template>
    <Head title="Client Profitability" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Client Profitability</h1>
                <p class="mt-1 text-sm text-slate-500">{{ from }} → {{ to }} · operational revenue (not cash collected)</p>
            </div>
            <div class="flex gap-3 text-sm">
                <a :href="route('reports.client-profitability', { period, export: 1 })" class="text-teal-700 hover:underline">Export CSV</a>
                <Link :href="route('reports.index')" class="text-slate-500 hover:underline">All reports</Link>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table v-if="rows.length" class="min-w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Contracted</th>
                        <th class="px-4 py-3">Charges</th>
                        <th class="px-4 py-3">Op. Revenue</th>
                        <th class="px-4 py-3">Labor</th>
                        <th class="px-4 py-3">Direct</th>
                        <th class="px-4 py-3">Profit</th>
                        <th class="px-4 py-3">Margin</th>
                        <th class="px-4 py-3">Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="r in rows" :key="r.client.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <Link :href="route('reports.client-profitability.show', r.client.id)" class="font-medium text-teal-800 hover:underline">
                                {{ r.client.company_name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">{{ money(r.package_contracted_value) }}</td>
                        <td class="px-4 py-3">{{ money(r.additional_charge_value) }}</td>
                        <td class="px-4 py-3">{{ money(r.operational_revenue) }}</td>
                        <td class="px-4 py-3">{{ money(r.labor_cost) }}</td>
                        <td class="px-4 py-3">{{ money(r.direct_cost) }}</td>
                        <td class="px-4 py-3">{{ money(r.estimated_gross_profit) }}</td>
                        <td class="px-4 py-3">{{ r.estimated_gross_margin != null ? r.estimated_gross_margin + '%' : 'N/A' }}</td>
                        <td class="px-4 py-3 capitalize text-xs">{{ r.data_completeness?.replaceAll('_', ' ') }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else class="m-6" title="No profitability data is available for this period." />
        </div>
    </AuthenticatedLayout>
</template>
