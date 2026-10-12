<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    package: Object,
    can: Object,
});

const formatMoney = (amount, currency = 'INR') => {
    try {
        return new Intl.NumberFormat('en-IN', { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(amount || 0));
    } catch {
        return `${currency} ${amount}`;
    }
};
</script>

<template>
    <Head :title="package.name" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <Link :href="route('service-packages.index')" class="text-sm text-teal-700 hover:underline">← Service Packages</Link>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ package.name }}</h1>
                    <StatusBadge :status="package.status" />
                </div>
                <p class="mt-1 text-sm text-slate-500 capitalize">
                    {{ package.billing_cycle.replaceAll('_', ' ') }} · {{ formatMoney(package.base_price, package.currency) }}
                </p>
            </div>
            <Link
                v-if="can.update"
                :href="route('service-packages.edit', package.id)"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50"
            >
                Edit
            </Link>
        </div>

        <p v-if="package.description" class="mb-6 max-w-3xl text-sm text-slate-600">{{ package.description }}</p>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Service</th>
                        <th class="px-4 py-3">Quantity</th>
                        <th class="px-4 py-3">Unit</th>
                        <th class="px-4 py-3">Overage Rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="item in package.items" :key="item.id">
                        <td class="px-4 py-3 font-medium">{{ item.service_name }}</td>
                        <td class="px-4 py-3">{{ item.is_unlimited ? 'Unlimited' : item.quantity }}</td>
                        <td class="px-4 py-3">{{ item.unit }}</td>
                        <td class="px-4 py-3">{{ formatMoney(item.overage_rate, package.currency) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
