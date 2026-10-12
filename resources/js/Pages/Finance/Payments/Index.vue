<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ payments: Object, can: Object });
const money = (v, c = 'INR') => new Intl.NumberFormat('en-IN', { style: 'currency', currency: c }).format(Number(v || 0));
</script>

<template>
    <Head title="Payments" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold">Payments Received</h1>
            <p class="mt-1 text-sm text-slate-500">Cash collected against invoices — not operational revenue.</p>
        </div>
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table v-if="payments.data?.length" class="min-w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Payment #</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Method</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="p in payments.data" :key="p.id">
                        <td class="px-4 py-3"><a :href="route('payments.pdf', p.id)" class="font-medium text-teal-800 hover:underline">{{ p.payment_number }}</a></td>
                        <td class="px-4 py-3">{{ p.client?.company_name }}</td>
                        <td class="px-4 py-3"><Link v-if="p.invoice" :href="route('invoices.show', p.invoice.id)">{{ p.invoice.invoice_number }}</Link></td>
                        <td class="px-4 py-3">{{ p.payment_date }}</td>
                        <td class="px-4 py-3">{{ money(p.amount, p.currency) }}</td>
                        <td class="px-4 py-3 capitalize">{{ p.payment_method?.replaceAll('_', ' ') }}</td>
                        <td class="px-4 py-3 capitalize">{{ p.status }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else class="m-6" title="No payments recorded." />
        </div>
    </AuthenticatedLayout>
</template>
