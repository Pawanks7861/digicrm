<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    packages: Object,
    filters: Object,
    billingCycles: Array,
    statuses: Array,
    can: Object,
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const billing_cycle = ref(props.filters.billing_cycle || '');

watch([search, status, billing_cycle], () => {
    router.get(
        route('service-packages.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            billing_cycle: billing_cycle.value || undefined,
        },
        { preserveState: true, replace: true }
    );
});

const formatMoney = (amount, currency = 'INR') => {
    const n = Number(amount || 0);
    try {
        return new Intl.NumberFormat('en-IN', { style: 'currency', currency, maximumFractionDigits: 0 }).format(n);
    } catch {
        return `${currency} ${n.toFixed(2)}`;
    }
};

const destroy = (pkg) => {
    if (confirm('Archive this service package?')) {
        router.delete(route('service-packages.destroy', pkg.id));
    }
};

const toggle = (pkg) => router.post(route('service-packages.toggle-status', pkg.id));
const duplicate = (pkg) => router.post(route('service-packages.duplicate', pkg.id));
</script>

<template>
    <Head title="Service Packages" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Service Packages</h1>
                <p class="mt-1 text-sm text-slate-500">Define retainers and included deliverables for client agreements.</p>
            </div>
            <Link
                v-if="can.create"
                :href="route('service-packages.create')"
                class="inline-flex rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800"
            >
                New Package
            </Link>
        </div>

        <div class="mb-4 flex flex-col gap-3 sm:flex-row">
            <input v-model="search" type="search" placeholder="Search packages…" class="w-full rounded-lg border-slate-200 text-sm sm:max-w-sm" />
            <select v-model="status" class="rounded-lg border-slate-200 text-sm sm:w-40">
                <option value="">All statuses</option>
                <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
            </select>
            <select v-model="billing_cycle" class="rounded-lg border-slate-200 text-sm sm:w-44">
                <option value="">All cycles</option>
                <option v-for="c in billingCycles" :key="c" :value="c">{{ c.replaceAll('_', ' ') }}</option>
            </select>
        </div>

        <div v-if="packages.data.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Package Name</th>
                        <th class="px-4 py-3">Billing Cycle</th>
                        <th class="px-4 py-3">Base Price</th>
                        <th class="px-4 py-3">Services</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="pkg in packages.data" :key="pkg.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <Link :href="route('service-packages.show', pkg.id)" class="font-medium text-teal-800 hover:underline">
                                {{ pkg.name }}
                            </Link>
                            <div v-if="pkg.code" class="text-xs text-slate-400">{{ pkg.code }}</div>
                        </td>
                        <td class="px-4 py-3 capitalize">{{ pkg.billing_cycle.replaceAll('_', ' ') }}</td>
                        <td class="px-4 py-3">{{ formatMoney(pkg.base_price, pkg.currency) }}</td>
                        <td class="px-4 py-3">{{ pkg.items_count }}</td>
                        <td class="px-4 py-3"><StatusBadge :status="pkg.status" /></td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2 text-xs">
                                <Link :href="route('service-packages.show', pkg.id)" class="text-teal-700 hover:underline">View</Link>
                                <Link :href="route('service-packages.edit', pkg.id)" class="text-slate-600 hover:underline">Edit</Link>
                                <button type="button" class="text-slate-600 hover:underline" @click="duplicate(pkg)">Duplicate</button>
                                <button type="button" class="text-slate-600 hover:underline" @click="toggle(pkg)">
                                    {{ pkg.status === 'active' ? 'Deactivate' : 'Activate' }}
                                </button>
                                <button type="button" class="text-rose-600 hover:underline" @click="destroy(pkg)">Delete</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EmptyState
            v-else
            title="No service packages yet."
            description="Create packages to define monthly client deliverables and automatically track scope."
        >
            <template #action>
                <Link
                    v-if="can.create"
                    :href="route('service-packages.create')"
                    class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800"
                >
                    Create Package
                </Link>
            </template>
        </EmptyState>
    </AuthenticatedLayout>
</template>
