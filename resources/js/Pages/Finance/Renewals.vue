<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({ renewals: Array, days: Number });
const money = (v) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(v || 0));
</script>

<template>
    <Head title="Package Renewals" />
    <AuthenticatedLayout>
        <h1 class="mb-2 text-2xl font-semibold">Package Renewals</h1>
        <p class="mb-6 text-sm text-slate-500">Within {{ days }} days · auto-renew does not auto-charge</p>
        <ul v-if="renewals?.length" class="space-y-2">
            <li v-for="r in renewals" :key="r.id" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-white px-4 py-3 text-sm">
                <div>
                    <div class="font-medium">{{ r.client?.company_name }} · {{ r.package_name }}</div>
                    <div class="text-xs text-slate-500">Ends {{ r.end_date }} · {{ r.days_remaining }} days · {{ money(r.agreed_price) }} · Auto-renew: {{ r.auto_renew ? 'yes' : 'no' }}</div>
                </div>
                <PrimaryButton @click="router.post(route('client-packages.invoice', r.id))">Create Invoice</PrimaryButton>
            </li>
        </ul>
        <EmptyState v-else title="No package renewals due." />
    </AuthenticatedLayout>
</template>
