<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ dashboard: Object });
const money = (v) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(v || 0));
</script>

<template>
    <Head title="Receivables" />
    <AuthenticatedLayout>
        <div class="mb-6 flex justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Accounts Receivable</h1>
                <p class="mt-1 text-sm text-slate-500">Invoiced · Paid · Outstanding · Overdue</p>
            </div>
            <div class="flex gap-3 text-sm">
                <Link :href="route('finance.renewals')" class="text-teal-700 hover:underline">Renewals</Link>
                <Link :href="route('finance.unbilled-overages')" class="text-teal-700 hover:underline">Unbilled Overages</Link>
                <Link :href="route('finance.billing-settings')" class="text-teal-700 hover:underline">Billing Settings</Link>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border bg-white p-5"><div class="text-xs uppercase text-slate-400">Invoiced (period)</div><div class="mt-2 text-2xl font-semibold">{{ money(dashboard.invoiced) }}</div></div>
            <div class="rounded-xl border bg-white p-5"><div class="text-xs uppercase text-slate-400">Payments Received</div><div class="mt-2 text-2xl font-semibold">{{ money(dashboard.paid) }}</div></div>
            <div class="rounded-xl border bg-white p-5"><div class="text-xs uppercase text-slate-400">Outstanding</div><div class="mt-2 text-2xl font-semibold text-amber-700">{{ money(dashboard.outstanding) }}</div></div>
            <div class="rounded-xl border bg-white p-5"><div class="text-xs uppercase text-slate-400">Overdue</div><div class="mt-2 text-2xl font-semibold text-rose-700">{{ money(dashboard.overdue) }}</div></div>
        </div>

        <h2 class="mt-8 text-sm font-semibold">Aging</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-5">
            <div v-for="(b, key) in dashboard.aging?.buckets" :key="key" class="rounded-xl border bg-white p-4">
                <div class="text-xs uppercase text-slate-400">{{ key.replaceAll('_', '–') }}</div>
                <div class="mt-1 font-semibold">{{ money(b.amount) }}</div>
                <div class="text-xs text-slate-500">{{ b.count }} invoices</div>
            </div>
        </div>

        <h2 class="mt-8 text-sm font-semibold">Open Balances</h2>
        <ul v-if="dashboard.aging?.rows?.length" class="mt-2 space-y-2">
            <li v-for="r in dashboard.aging.rows" :key="r.invoice_id" class="flex flex-wrap justify-between gap-2 rounded-xl border bg-white px-4 py-3 text-sm">
                <Link :href="route('invoices.show', r.invoice_id)" class="font-medium text-teal-800 hover:underline">{{ r.invoice_number }}</Link>
                <span>{{ r.client }}</span>
                <span>Due {{ r.due_date }} · {{ r.days_overdue }}d</span>
                <span class="font-medium">{{ money(r.balance_due) }}</span>
            </li>
        </ul>
        <EmptyState v-else class="mt-4" title="No outstanding invoices." />
    </AuthenticatedLayout>
</template>
