<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ rows: Array, from: String, to: String });
const money = (v) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(v || 0));
</script>

<template>
    <Head title="Campaign Profitability" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Campaign Profitability</h1>
                <p class="mt-1 text-sm text-slate-500">{{ from }} → {{ to }} · revenue only when standalone_charge</p>
            </div>
            <a :href="route('reports.campaign-profitability', { export: 1 })" class="text-sm text-teal-700 hover:underline">Export CSV</a>
        </div>
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table v-if="rows?.length" class="min-w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Campaign</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Revenue</th>
                        <th class="px-4 py-3">Labor</th>
                        <th class="px-4 py-3">Direct</th>
                        <th class="px-4 py-3">Profit</th>
                        <th class="px-4 py-3">Margin</th>
                        <th class="px-4 py-3">Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="r in rows" :key="r.campaign.id">
                        <td class="px-4 py-3">
                            <Link :href="route('campaigns.show', r.campaign.id)" class="font-medium text-teal-800 hover:underline">{{ r.campaign.name }}</Link>
                        </td>
                        <td class="px-4 py-3">{{ r.client_name }}</td>
                        <td class="px-4 py-3">{{ money(r.operational_revenue) }}</td>
                        <td class="px-4 py-3">{{ money(r.labor_cost) }}</td>
                        <td class="px-4 py-3">{{ money(r.direct_cost) }}</td>
                        <td class="px-4 py-3">{{ money(r.estimated_gross_profit) }}</td>
                        <td class="px-4 py-3">{{ r.estimated_gross_margin != null ? r.estimated_gross_margin + '%' : 'N/A' }}</td>
                        <td class="px-4 py-3 text-xs capitalize">{{ r.data_completeness?.replaceAll('_', ' ') }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else class="m-6" title="No records match these filters." />
        </div>
    </AuthenticatedLayout>
</template>
