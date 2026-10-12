<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import LeadWidgets from '@/Components/Leads/LeadWidgets.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    dashboard: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});
const data = computed(() => props.dashboard.data || {});
</script>

<template>
    <Head title="Account Manager Dashboard" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">My Clients Command Center</h1>
            <p class="mt-1 text-sm text-slate-500">Scoped to your assigned clients.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">My Clients</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.my_clients ?? 0 }}</div>
            </div>
            <Link :href="route('requests.index')" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Requests Needing Attention</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.requests_needing_attention ?? 0 }}</div>
            </Link>
            <Link :href="route('content.index', { status: 'sent_to_client' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Approvals Pending</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.pending_approvals ?? 0 }}</div>
            </Link>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Waiting for Client</div>
                <div class="mt-2 text-3xl font-semibold text-indigo-700">{{ data.waiting_for_client ?? 0 }}</div>
            </div>
            <Link :href="route('content.index', { status: 'client_changes_requested' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-orange-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Changes Requested</div>
                <div class="mt-2 text-3xl font-semibold text-orange-700">{{ data.changes_requested ?? 0 }}</div>
            </Link>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Active Campaigns</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.campaigns_active ?? 0 }}</div>
            </div>
            <Link :href="route('follow-ups.index')" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Follow-ups Today</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.followups_today ?? 0 }}</div>
            </Link>
            <Link :href="route('follow-ups.index')" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-rose-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Overdue Follow-ups</div>
                <div class="mt-2 text-3xl font-semibold text-rose-700">{{ data.followups_overdue ?? 0 }}</div>
            </Link>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Overages Pending</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.overages_pending ?? 0 }}</div>
            </div>
        </div>

        <h2 class="mt-8 text-sm font-semibold text-slate-700">My Clients</h2>
        <ul v-if="data.clients?.length" class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
            <li v-for="c in data.clients" :key="c.id" class="flex items-center justify-between px-4 py-3 text-sm">
                <Link :href="route('clients.show', c.id)" class="font-medium text-teal-800 hover:underline">{{ c.company_name }}</Link>
                <span class="capitalize text-slate-500">{{ c.status }}</span>
            </li>
        </ul>
        <p v-else class="mt-3 text-sm text-slate-500">No assigned clients.</p>
        <LeadWidgets :leads="dashboard.leads" role="account_manager" />
    </AuthenticatedLayout>
</template>
