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
    <Head title="Operations Dashboard" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Operations Dashboard</h1>
            <p class="mt-1 text-sm text-slate-500">Execution focus — tasks, reviews, capacity.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Link :href="route('tasks.index', { bucket: 'today' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Tasks Due Today</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.tasks_due_today ?? 0 }}</div>
            </Link>
            <Link :href="route('tasks.index', { bucket: 'overdue' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-rose-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Overdue Tasks</div>
                <div class="mt-2 text-3xl font-semibold text-rose-700">{{ data.tasks_overdue ?? 0 }}</div>
            </Link>
            <Link :href="route('tasks.index', { status: 'blocked' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-orange-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Blocked Tasks</div>
                <div class="mt-2 text-3xl font-semibold text-orange-700">{{ data.blocked_tasks ?? 0 }}</div>
            </Link>
            <Link :href="route('internal-reviews.index')" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Reviews Pending</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.reviews_pending ?? 0 }}</div>
            </Link>
            <Link :href="route('content.index', { status: 'sent_to_client' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Client Approvals Pending</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.pending_client_approvals ?? 0 }}</div>
            </Link>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Waiting for Client</div>
                <div class="mt-2 text-3xl font-semibold text-indigo-700">{{ data.waiting_for_client ?? 0 }}</div>
            </div>
            <Link :href="route('content.index', { status: 'client_changes_requested' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-orange-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Changes Required</div>
                <div class="mt-2 text-3xl font-semibold text-orange-700">{{ data.changes_required ?? 0 }}</div>
            </Link>
            <Link :href="route('requests.index', { status: 'submitted' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Requests Pending</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.requests_pending_review ?? 0 }}</div>
            </Link>
            <Link :href="route('follow-ups.index')" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-rose-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Follow-ups Overdue</div>
                <div class="mt-2 text-3xl font-semibold text-rose-700">{{ data.followups_overdue ?? 0 }}</div>
            </Link>
            <Link :href="route('workload.index')" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-rose-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Overloaded Users</div>
                <div class="mt-2 text-3xl font-semibold text-rose-700">{{ data.overloaded_members ?? 0 }}</div>
            </Link>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Active Timers</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.active_timers ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Overages Pending</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.overages_pending ?? 0 }}</div>
            </div>
        </div>
        <LeadWidgets :leads="dashboard.leads" role="operations" />
    </AuthenticatedLayout>
</template>
