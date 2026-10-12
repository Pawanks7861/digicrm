<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps({ overages: Array });
const money = (v) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(v || 0));
</script>

<template>
    <Head title="Unbilled Overages" />
    <AuthenticatedLayout>
        <h1 class="mb-6 text-2xl font-semibold">Unbilled Chargeable Overages</h1>
        <ul v-if="overages?.length" class="space-y-2">
            <li v-for="o in overages" :key="o.id" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-white px-4 py-3 text-sm">
                <div>
                    <div class="font-medium">{{ o.client?.company_name }} · {{ o.item?.service_name }}</div>
                    <div class="text-xs text-slate-500">Qty {{ o.overage_quantity }} · {{ money(o.extra_amount) }} · {{ o.approved_at }}</div>
                </div>
                <PrimaryButton @click="router.post(route('overages.quotation', o.id))">Create Quotation</PrimaryButton>
            </li>
        </ul>
        <EmptyState v-else title="No unbilled chargeable overages." />
    </AuthenticatedLayout>
</template>
