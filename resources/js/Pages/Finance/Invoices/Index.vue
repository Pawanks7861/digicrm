<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ invoices: Object, filters: Object, can: Object });
const money = (v, c = 'INR') => new Intl.NumberFormat('en-IN', { style: 'currency', currency: c }).format(Number(v || 0));
</script>

<template>
    <Head title="Invoices" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Invoices</h1>
                <p class="mt-1 text-sm text-slate-500">Tax invoices & balances (not Phase 7 operational revenue).</p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('quotations.index')" class="text-sm text-slate-600 hover:underline">Quotations</Link>
                <Link v-if="can.create" :href="route('invoices.create')"><PrimaryButton>New Invoice</PrimaryButton></Link>
            </div>
        </div>
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table v-if="invoices.data?.length" class="min-w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Invoice #</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Issue</th>
                        <th class="px-4 py-3">Due</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Paid</th>
                        <th class="px-4 py-3">Balance</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="inv in invoices.data" :key="inv.id">
                        <td class="px-4 py-3"><Link :href="route('invoices.show', inv.id)" class="font-medium text-teal-800 hover:underline">{{ inv.invoice_number }}</Link></td>
                        <td class="px-4 py-3">{{ inv.client?.company_name }}</td>
                        <td class="px-4 py-3">{{ inv.issue_date }}</td>
                        <td class="px-4 py-3">{{ inv.due_date }}</td>
                        <td class="px-4 py-3">{{ money(inv.grand_total, inv.currency) }}</td>
                        <td class="px-4 py-3">{{ money(inv.amount_paid, inv.currency) }}</td>
                        <td class="px-4 py-3">{{ money(inv.balance_due, inv.currency) }}</td>
                        <td class="px-4 py-3 capitalize" :class="inv.is_overdue ? 'text-rose-700' : ''">{{ inv.display_status?.replaceAll('_', ' ') }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else class="m-6" title="No invoices have been created." />
        </div>
    </AuthenticatedLayout>
</template>
