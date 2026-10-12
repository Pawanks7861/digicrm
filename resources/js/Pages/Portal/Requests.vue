<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ requests: Object, canCreate: Boolean });
const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head title="Requests" />
    <PortalLayout>
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Requests</h1>
                <p class="mt-1 text-sm text-slate-500">Submit work requests to your agency.</p>
            </div>
            <Link v-if="canCreate" :href="route('portal.requests.create')" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white">New Request</Link>
        </div>
        <div v-if="requests.data.length" class="mt-6 space-y-3">
            <Link v-for="r in requests.data" :key="r.id" :href="route('portal.requests.show', r.id)" class="block rounded-2xl border border-slate-200 bg-white p-4 hover:border-teal-300">
                <div class="flex justify-between gap-2">
                    <div class="font-semibold">{{ r.title }}</div>
                    <StatusBadge :status="r.status" />
                </div>
                <div class="mt-1 text-xs text-slate-500">{{ r.request_number }} · <span class="capitalize">{{ label(r.request_type) }}</span></div>
            </Link>
        </div>
        <EmptyState v-else class="mt-8" title="You haven't submitted any requests yet." description="Submit Request" />
    </PortalLayout>
</template>
