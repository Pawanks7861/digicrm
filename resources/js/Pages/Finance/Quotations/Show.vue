<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({ quotation: Object, can: Object });
const money = (v, c = 'INR') => new Intl.NumberFormat('en-IN', { style: 'currency', currency: c }).format(Number(v || 0));
</script>

<template>
    <Head :title="quotation.display_number" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <Link :href="route('quotations.index')" class="text-sm text-teal-700 hover:underline">← Quotations</Link>
                <h1 class="mt-2 text-2xl font-semibold">{{ quotation.display_number }}</h1>
                <p class="text-sm text-slate-500">{{ quotation.client?.company_name }} · <span class="capitalize">{{ quotation.status?.replaceAll('_', ' ') }}</span></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a :href="route('quotations.pdf', quotation.id)" class="rounded-lg border px-3 py-2 text-sm">PDF</a>
                <Link v-if="can.edit" :href="route('quotations.edit', quotation.id)" class="rounded-lg border px-3 py-2 text-sm">Edit</Link>
                <PrimaryButton v-if="can.send && ['draft','changes_requested'].includes(quotation.status)" @click="router.post(route('quotations.send', quotation.id))">Send</PrimaryButton>
                <PrimaryButton v-if="can.convert && quotation.status === 'accepted'" @click="router.post(route('quotations.convert', quotation.id))">Convert to Invoice</PrimaryButton>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Subtotal</div><div class="text-xl font-semibold">{{ money(quotation.subtotal, quotation.currency) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Tax</div><div class="text-xl font-semibold">{{ money(quotation.tax_amount, quotation.currency) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Discount</div><div class="text-xl font-semibold">{{ money(quotation.discount_amount, quotation.currency) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs text-slate-400">Grand Total</div><div class="text-xl font-semibold text-teal-800">{{ money(quotation.grand_total, quotation.currency) }}</div></div>
        </div>

        <div class="mt-6 overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-2 text-left">Item</th><th class="px-4 py-2 text-right">Qty</th><th class="px-4 py-2 text-right">Rate</th><th class="px-4 py-2 text-right">Tax</th><th class="px-4 py-2 text-right">Total</th></tr></thead>
                <tbody class="divide-y">
                    <tr v-for="item in quotation.items" :key="item.id">
                        <td class="px-4 py-2">{{ item.name }}</td>
                        <td class="px-4 py-2 text-right">{{ item.quantity }}</td>
                        <td class="px-4 py-2 text-right">{{ money(item.unit_rate, quotation.currency) }}</td>
                        <td class="px-4 py-2 text-right">{{ money(item.tax_amount, quotation.currency) }}</td>
                        <td class="px-4 py-2 text-right">{{ money(item.total_amount, quotation.currency) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="quotation.cgst_amount > 0" class="mt-3 text-sm text-slate-600">CGST {{ money(quotation.cgst_amount) }} · SGST {{ money(quotation.sgst_amount) }}</p>
        <p v-else-if="quotation.igst_amount > 0" class="mt-3 text-sm text-slate-600">IGST {{ money(quotation.igst_amount) }}</p>
    </AuthenticatedLayout>
</template>
