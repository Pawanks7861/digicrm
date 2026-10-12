<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ invoice: Object });
const money = (v, c = 'INR') => new Intl.NumberFormat('en-IN', { style: 'currency', currency: c }).format(Number(v || 0));
</script>

<template>
    <Head :title="invoice.invoice_number" />
    <PortalLayout>
        <h1 class="text-xl font-semibold">{{ invoice.invoice_number }}</h1>
        <p class="mt-1 text-sm capitalize text-slate-500">{{ invoice.display_status?.replaceAll('_', ' ') }} · Due {{ invoice.due_date }}</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Total</div><div class="text-lg font-semibold">{{ money(invoice.grand_total, invoice.currency) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Paid</div><div class="text-lg font-semibold">{{ money(invoice.amount_paid, invoice.currency) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Balance</div><div class="text-lg font-semibold">{{ money(invoice.balance_due, invoice.currency) }}</div></div>
        </div>
        <a :href="route('portal.invoices.pdf', invoice.id)" class="mt-4 inline-block text-sm text-teal-700 hover:underline">Download PDF</a>
    </PortalLayout>
</template>
