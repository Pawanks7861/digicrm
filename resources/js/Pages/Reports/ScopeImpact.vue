<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ rows: Array, from: String, to: String });
const money = (v) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(v || 0));
</script>

<template>
    <Head title="Scope Impact" />
    <AuthenticatedLayout>
        <div class="mb-6 flex justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Scope Impact</h1>
                <p class="mt-1 text-sm text-slate-500">{{ from }} → {{ to }}</p>
            </div>
            <a :href="route('reports.scope-impact', { export: 1 })" class="text-sm text-teal-700 hover:underline">Export CSV</a>
        </div>
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table v-if="rows?.length" class="min-w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Chargeable</th>
                        <th class="px-4 py-3">Complimentary</th>
                        <th class="px-4 py-3">Rejected</th>
                        <th class="px-4 py-3">Pending</th>
                        <th class="px-4 py-3">Addl. Value</th>
                        <th class="px-4 py-3">Compl. Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="(r, i) in rows" :key="i">
                        <td class="px-4 py-3">{{ r.client?.company_name }}</td>
                        <td class="px-4 py-3">{{ r.total }}</td>
                        <td class="px-4 py-3">{{ r.chargeable }}</td>
                        <td class="px-4 py-3">{{ r.complimentary }}</td>
                        <td class="px-4 py-3">{{ r.rejected }}</td>
                        <td class="px-4 py-3">{{ r.pending }}</td>
                        <td class="px-4 py-3">{{ money(r.additional_charge_value) }}</td>
                        <td class="px-4 py-3">{{ money(r.complimentary_work_value) }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else class="m-6" title="No records match these filters." />
        </div>
    </AuthenticatedLayout>
</template>
