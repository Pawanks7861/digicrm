<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    clientPackage: Object,
    item: Object,
    summary: Object,
    history: Array,
    can: Object,
});
</script>

<template>
    <Head :title="item.service_name" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('client-packages.show', clientPackage.id)" class="text-sm text-teal-700 hover:underline">
                ← {{ clientPackage.package_name }}
            </Link>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ item.service_name }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                <template v-if="summary.is_unlimited">Unlimited · Used {{ summary.used }}</template>
                <template v-else>
                    Included {{ summary.included }} · Used {{ summary.used }} · Remaining {{ summary.remaining }}
                </template>
            </p>
        </div>

        <ul v-if="history.length" class="max-w-2xl divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
            <li v-for="tx in history" :key="tx.id" class="px-4 py-3 text-sm">
                <div class="flex justify-between gap-2">
                    <span class="font-medium capitalize">{{ tx.direction }} {{ tx.quantity }}</span>
                    <span class="text-xs text-slate-400">{{ tx.usage_date }}</span>
                </div>
                <div class="text-xs text-slate-500">{{ tx.reason || tx.notes || '—' }} · {{ tx.creator?.name || 'System' }}</div>
            </li>
        </ul>
        <EmptyState v-else title="No history for this service yet." />
    </AuthenticatedLayout>
</template>
