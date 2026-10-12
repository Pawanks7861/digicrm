<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ quotations: Object });
const money = (v, c = 'INR') => new Intl.NumberFormat('en-IN', { style: 'currency', currency: c }).format(Number(v || 0));
</script>

<template>
    <Head title="Quotations" />
    <PortalLayout>
        <h1 class="mb-4 text-xl font-semibold">Quotations</h1>
        <ul v-if="quotations.data?.length" class="space-y-2">
            <li v-for="q in quotations.data" :key="q.id" class="flex justify-between rounded-xl border bg-white px-4 py-3 text-sm">
                <Link :href="route('portal.quotations.show', q.id)" class="font-medium text-teal-800">{{ q.display_number || q.quotation_number }}</Link>
                <span>{{ money(q.grand_total, q.currency) }} · <span class="capitalize">{{ q.status?.replaceAll('_', ' ') }}</span></span>
            </li>
        </ul>
        <EmptyState v-else title="No quotations yet." />
    </PortalLayout>
</template>
