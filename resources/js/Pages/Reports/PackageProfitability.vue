<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ rows: Array, from: String, to: String });
const money = (v) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(v || 0));
</script>

<template>
    <Head title="Package Profitability" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold">Package Profitability</h1>
            <p class="mt-1 text-sm text-slate-500">{{ from }} → {{ to }}</p>
        </div>
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table v-if="rows?.length" class="min-w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Package</th>
                        <th class="px-4 py-3">Contracted</th>
                        <th class="px-4 py-3">Charges</th>
                        <th class="px-4 py-3">Complimentary</th>
                        <th class="px-4 py-3">Labor</th>
                        <th class="px-4 py-3">Profit</th>
                        <th class="px-4 py-3">Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="r in rows" :key="r.package.id">
                        <td class="px-4 py-3">{{ r.client?.company_name }}</td>
                        <td class="px-4 py-3">{{ r.package.name }} <span class="text-xs text-slate-400">({{ r.package.billing_cycle }})</span></td>
                        <td class="px-4 py-3">{{ money(r.contracted_value) }}</td>
                        <td class="px-4 py-3">{{ money(r.additional_charge_value) }}</td>
                        <td class="px-4 py-3">{{ money(r.complimentary_work_value) }}</td>
                        <td class="px-4 py-3">{{ money(r.labor_cost) }}</td>
                        <td class="px-4 py-3">{{ money(r.estimated_gross_profit) }}</td>
                        <td class="px-4 py-3 text-xs capitalize">{{ r.data_completeness?.replaceAll('_', ' ') }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else class="m-6" title="No profitability data is available for this period." />
        </div>
    </AuthenticatedLayout>
</template>
