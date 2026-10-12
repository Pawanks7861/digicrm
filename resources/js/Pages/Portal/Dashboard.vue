<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stats: Object,
});
</script>

<template>
    <Head title="Portal Dashboard" />
    <PortalLayout>
        <h1 class="text-2xl font-semibold tracking-tight">Welcome</h1>
        <p class="mt-1 text-sm text-slate-500">Review creatives and track your campaigns.</p>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <Link :href="route('portal.approvals')" class="rounded-2xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Pending Approvals</div>
                <div class="mt-2 text-3xl font-semibold">{{ stats.pending_approvals }}</div>
            </Link>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Approved This Month</div>
                <div class="mt-2 text-3xl font-semibold text-teal-700">{{ stats.approved_this_month }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Changes Requested</div>
                <div class="mt-2 text-3xl font-semibold text-orange-700">{{ stats.changes_requested }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Upcoming Content</div>
                <div class="mt-2 text-3xl font-semibold">{{ stats.upcoming }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Published (30d)</div>
                <div class="mt-2 text-3xl font-semibold">{{ stats.recent_published }}</div>
            </div>
            <Link :href="route('portal.campaigns')" class="rounded-2xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Active Campaigns</div>
                <div class="mt-2 text-3xl font-semibold">{{ stats.active_campaigns }}</div>
            </Link>
        </div>
    </PortalLayout>
</template>
