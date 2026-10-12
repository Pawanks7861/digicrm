<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ invoices: Object });
const money = (v, c = 'INR') => new Intl.NumberFormat('en-IN', { style: 'currency', currency: c }).format(Number(v || 0));
</script>

<template>
    <Head title="Invoices" />
    <PortalLayout>
        <h1 class="mb-4 text-xl font-semibold">Invoices</h1>
        <ul v-if="invoices.data?.length" class="space-y-2">
            <li v-for="inv in invoices.data" :key="inv.id" class="flex justify-between rounded-xl border bg-white px-4 py-3 text-sm">
                <Link :href="route('portal.invoices.show', inv.id)" class="font-medium text-teal-800">{{ inv.invoice_number }}</Link>
                <span>{{ money(inv.balance_due, inv.currency) }} due · <span class="capitalize">{{ inv.display_status?.replaceAll('_', ' ') }}</span></span>
            </li>
        </ul>
        <EmptyState v-else title="No invoices have been created." />
    </PortalLayout>
</template>
