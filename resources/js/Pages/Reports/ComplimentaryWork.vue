<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ rows: Array, from: String, to: String });
const money = (v) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(v || 0));
</script>

<template>
    <Head title="Complimentary Work" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold">Complimentary Work</h1>
            <p class="mt-1 text-sm text-slate-500">{{ from }} → {{ to }} · equivalent value at normal overage rate (not charged)</p>
        </div>
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table v-if="rows?.length" class="min-w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Service</th>
                        <th class="px-4 py-3">Qty</th>
                        <th class="px-4 py-3">Rate</th>
                        <th class="px-4 py-3">Equivalent Value</th>
                        <th class="px-4 py-3">Approved By</th>
                        <th class="px-4 py-3">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="r in rows" :key="r.id">
                        <td class="px-4 py-3">{{ r.client }}</td>
                        <td class="px-4 py-3">{{ r.service }}</td>
                        <td class="px-4 py-3">{{ r.quantity }}</td>
                        <td class="px-4 py-3">{{ money(r.rate) }}</td>
                        <td class="px-4 py-3 font-medium">{{ money(r.equivalent_value) }}</td>
                        <td class="px-4 py-3">{{ r.approved_by }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ r.notes }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else class="m-6" title="No records match these filters." />
        </div>
    </AuthenticatedLayout>
</template>
