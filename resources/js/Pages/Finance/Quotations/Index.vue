<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ quotations: Object, filters: Object, can: Object });
const money = (v, c = 'INR') => new Intl.NumberFormat('en-IN', { style: 'currency', currency: c }).format(Number(v || 0));
</script>

<template>
    <Head title="Quotations" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Quotations</h1>
                <p class="mt-1 text-sm text-slate-500">Commercial quotes — not operational revenue.</p>
            </div>
            <div class="flex flex-wrap gap-2 text-sm">
                <Link :href="route('invoices.index')" class="text-slate-600 hover:underline">Invoices</Link>
                <Link :href="route('payments.index')" class="text-slate-600 hover:underline">Payments</Link>
                <Link :href="route('finance.receivables')" class="text-slate-600 hover:underline">Receivables</Link>
                <Link v-if="can.create" :href="route('quotations.create')"><PrimaryButton>New Quotation</PrimaryButton></Link>
            </div>
        </div>
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table v-if="quotations.data?.length" class="min-w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Number</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Issue</th>
                        <th class="px-4 py-3">Valid Until</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="q in quotations.data" :key="q.id">
                        <td class="px-4 py-3"><Link :href="route('quotations.show', q.id)" class="font-medium text-teal-800 hover:underline">{{ q.display_number || q.quotation_number }}</Link></td>
                        <td class="px-4 py-3">{{ q.client?.company_name }}</td>
                        <td class="px-4 py-3">{{ q.issue_date }}</td>
                        <td class="px-4 py-3">{{ q.valid_until }}</td>
                        <td class="px-4 py-3">{{ money(q.grand_total, q.currency) }}</td>
                        <td class="px-4 py-3 capitalize">{{ q.status?.replaceAll('_', ' ') }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else class="m-6" title="No quotations yet." />
        </div>
    </AuthenticatedLayout>
</template>
