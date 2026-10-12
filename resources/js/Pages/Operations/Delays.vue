<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps({ analytics: Object, active: Array, reasons: Array, from: String, to: String });

const fmt = (sec) => {
    const s = Number(sec || 0);
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    return `${h}h ${m}m`;
};
</script>

<template>
    <Head title="Delay Analytics" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Delays & Blockers</h1>
                <p class="mt-1 text-sm text-slate-500">{{ from }} → {{ to }} · factual categories, not blame</p>
            </div>
            <a :href="route('delays.export')" class="text-sm text-teal-700 hover:underline">Export CSV</a>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border bg-white p-4"><div class="text-xs uppercase text-slate-400">Total Delay</div><div class="mt-1 text-2xl font-semibold">{{ fmt(analytics.total_seconds) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs uppercase text-slate-400">Client-caused</div><div class="mt-1 text-2xl font-semibold">{{ fmt(analytics.client_seconds) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs uppercase text-slate-400">Active Blockers</div><div class="mt-1 text-2xl font-semibold">{{ analytics.active_count }}</div></div>
        </div>

        <h2 class="mt-8 text-sm font-semibold">By Reason</h2>
        <ul v-if="analytics.by_reason?.length" class="mt-2 space-y-2">
            <li v-for="r in analytics.by_reason" :key="r.key" class="flex items-center justify-between rounded-xl border bg-white px-4 py-3 text-sm">
                <span class="capitalize">{{ r.key.replaceAll('_', ' ') }}</span>
                <span>{{ r.percent }}% · {{ fmt(r.seconds) }} · {{ r.count }}</span>
            </li>
        </ul>
        <EmptyState v-else class="mt-4" title="No delay data for this range." />

        <h2 class="mt-8 text-sm font-semibold">Active Blockers</h2>
        <ul v-if="active?.length" class="mt-2 space-y-2">
            <li v-for="b in active" :key="b.id" class="rounded-xl border bg-white px-4 py-3 text-sm flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div class="font-medium">{{ b.reason?.name }}</div>
                    <div class="text-xs text-slate-500">Since {{ new Date(b.started_at).toLocaleString() }} · {{ b.delayable_type }} #{{ b.delayable_id }}</div>
                </div>
                <PrimaryButton @click="router.post(route('delays.resolve', b.id))">Resolve</PrimaryButton>
            </li>
        </ul>
        <EmptyState v-else class="mt-4" title="No active blockers." />
    </AuthenticatedLayout>
</template>
