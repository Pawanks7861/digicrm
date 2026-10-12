<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({ invoice: Object, can: Object });
const money = (v, c = 'INR') => new Intl.NumberFormat('en-IN', { style: 'currency', currency: c }).format(Number(v || 0));

const pay = useForm({
    amount: props.invoice?.balance_due || '',
    payment_date: new Date().toISOString().slice(0, 10),
    payment_method: 'bank_transfer',
    transaction_reference: '',
    notes: '',
});
const submitPay = () => pay.post(route('invoices.payments.store', props.invoice.id), { onSuccess: () => pay.reset('transaction_reference', 'notes') });
</script>

<template>
    <Head :title="invoice.invoice_number" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <Link :href="route('invoices.index')" class="text-sm text-teal-700 hover:underline">← Invoices</Link>
                <h1 class="mt-2 text-2xl font-semibold">{{ invoice.invoice_number }}</h1>
                <p class="text-sm capitalize text-slate-500">{{ invoice.client?.company_name }} · {{ invoice.display_status?.replaceAll('_', ' ') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a :href="route('invoices.pdf', invoice.id)" class="rounded-lg border px-3 py-2 text-sm">PDF</a>
                <Link v-if="can.edit" :href="route('invoices.edit', invoice.id)" class="rounded-lg border px-3 py-2 text-sm">Edit</Link>
                <PrimaryButton v-if="can.send && invoice.status === 'draft'" @click="router.post(route('invoices.send', invoice.id))">Send</PrimaryButton>
                <button v-if="can.cancel && !['paid','cancelled','void'].includes(invoice.status)" type="button" class="rounded-lg border border-rose-200 px-3 py-2 text-sm text-rose-700" @click="router.post(route('invoices.cancel', invoice.id))">Cancel</button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Grand Total</div><div class="text-xl font-semibold">{{ money(invoice.grand_total, invoice.currency) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Paid</div><div class="text-xl font-semibold">{{ money(invoice.amount_paid, invoice.currency) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Balance</div><div class="text-xl font-semibold text-rose-700">{{ money(invoice.balance_due, invoice.currency) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Tax</div><div class="text-xl font-semibold">{{ money(invoice.tax_amount, invoice.currency) }}</div></div>
        </div>

        <div class="mt-6 overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-2 text-left">Item</th><th class="px-4 py-2 text-right">Qty</th><th class="px-4 py-2 text-right">Rate</th><th class="px-4 py-2 text-right">Total</th></tr></thead>
                <tbody class="divide-y">
                    <tr v-for="item in invoice.items" :key="item.id">
                        <td class="px-4 py-2">{{ item.name }}</td>
                        <td class="px-4 py-2 text-right">{{ item.quantity }}</td>
                        <td class="px-4 py-2 text-right">{{ money(item.unit_rate, invoice.currency) }}</td>
                        <td class="px-4 py-2 text-right">{{ money(item.total_amount, invoice.currency) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h2 class="mt-8 text-sm font-semibold">Payments</h2>
        <ul class="mt-2 space-y-2">
            <li v-for="p in invoice.valid_payments" :key="p.id" class="flex justify-between rounded-xl border bg-white px-4 py-3 text-sm">
                <span>{{ p.payment_number }} · {{ p.payment_date }} · {{ p.payment_method }}</span>
                <span class="font-medium">{{ money(p.amount, p.currency) }}</span>
            </li>
        </ul>

        <form v-if="can.pay && invoice.balance_due > 0 && invoice.status !== 'draft'" class="mt-6 max-w-md space-y-3 rounded-xl border bg-white p-4" @submit.prevent="submitPay">
            <h3 class="text-sm font-semibold">Record Payment</h3>
            <div><InputLabel value="Amount" /><TextInput v-model="pay.amount" type="number" step="0.01" class="mt-1 w-full" required /></div>
            <div><InputLabel value="Date" /><TextInput v-model="pay.payment_date" type="date" class="mt-1 w-full" required /></div>
            <div>
                <InputLabel value="Method" />
                <select v-model="pay.payment_method" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="upi">UPI</option>
                    <option value="cheque">Cheque</option>
                    <option value="cash">Cash</option>
                    <option value="neft">NEFT</option>
                    <option value="rtgs">RTGS</option>
                    <option value="imps">IMPS</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div><InputLabel value="Reference" /><TextInput v-model="pay.transaction_reference" class="mt-1 w-full" /></div>
            <PrimaryButton :disabled="pay.processing">Record</PrimaryButton>
        </form>
    </AuthenticatedLayout>
</template>
