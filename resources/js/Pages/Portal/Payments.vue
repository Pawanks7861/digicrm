<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ payments: Object });
const money = (v, c = 'INR') => new Intl.NumberFormat('en-IN', { style: 'currency', currency: c }).format(Number(v || 0));
</script>

<template>
    <Head title="Payments" />
    <PortalLayout>
        <h1 class="mb-4 text-xl font-semibold">Payments</h1>
        <ul v-if="payments.data?.length" class="space-y-2">
            <li v-for="p in payments.data" :key="p.id" class="flex justify-between rounded-xl border bg-white px-4 py-3 text-sm">
                <a :href="route('portal.payments.pdf', p.id)" class="font-medium text-teal-800">{{ p.payment_number }}</a>
                <span>{{ money(p.amount, p.currency) }} · {{ p.payment_date }} · {{ p.invoice?.invoice_number }}</span>
            </li>
        </ul>
        <EmptyState v-else title="No payments recorded." />
    </PortalLayout>
</template>
