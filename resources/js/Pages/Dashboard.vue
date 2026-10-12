<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stats: { type: Object, default: () => ({}) },
});
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <h1 class="text-[2rem] font-semibold leading-[1.05] tracking-tight text-ink">Agency<br>Overview</h1>
            <p class="max-w-sm text-sm text-mute">Live operational counts from your workspace. Trend badges appear only when a comparison exists.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Link :href="route('campaigns.index')" class="rounded-card bg-card p-5 transition duration-150 hover:-translate-y-0.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-sun/70 text-accent">
                    <span class="text-xs font-semibold">C</span>
                </div>
                <div class="mt-4 text-[11px] font-medium text-mute">Active Campaigns</div>
                <div class="mt-1 text-3xl font-semibold tracking-tight">{{ stats.active_campaigns ?? 0 }}</div>
            </Link>
            <Link :href="route('tasks.index', { bucket: 'today' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Tasks Due Today</div>
                <div class="mt-2 text-3xl font-semibold">{{ stats.tasks_due_today ?? 0 }}</div>
            </Link>
            <Link :href="route('tasks.index', { bucket: 'overdue' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Overdue Tasks</div>
                <div class="mt-2 text-3xl font-semibold text-rose-700">{{ stats.tasks_overdue ?? 0 }}</div>
            </Link>
            <Link :href="route('internal-reviews.index')" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Reviews Pending</div>
                <div class="mt-2 text-3xl font-semibold">{{ stats.reviews_pending ?? 0 }}</div>
            </Link>
            <Link :href="route('content.index', { status: 'sent_to_client' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Waiting on Client</div>
                <div class="mt-2 text-3xl font-semibold">{{ stats.pending_client_approvals ?? 0 }}</div>
            </Link>
            <Link :href="route('content.index', { status: 'client_changes_requested' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Client Changes</div>
                <div class="mt-2 text-3xl font-semibold text-orange-700">{{ stats.client_changes_requested ?? 0 }}</div>
            </Link>
            <Link :href="route('content.index', { status: 'client_approved' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Ready to Schedule</div>
                <div class="mt-2 text-3xl font-semibold text-teal-700">{{ stats.ready_to_schedule ?? 0 }}</div>
            </Link>
            <Link :href="route('requests.index', { status: 'submitted' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Requests Pending Review</div>
                <div class="mt-2 text-3xl font-semibold">{{ stats.requests_pending_review ?? 0 }}</div>
            </Link>
            <Link :href="route('requests.index', { status: 'need_more_information' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Waiting for Client</div>
                <div class="mt-2 text-3xl font-semibold text-indigo-700">{{ stats.requests_waiting_client ?? 0 }}</div>
            </Link>
            <Link :href="route('follow-ups.index')" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Follow-ups Due Today</div>
                <div class="mt-2 text-3xl font-semibold">{{ stats.followups_due_today ?? 0 }}</div>
            </Link>
            <Link :href="route('follow-ups.index')" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Overdue Follow-ups</div>
                <div class="mt-2 text-3xl font-semibold text-rose-700">{{ stats.followups_overdue ?? 0 }}</div>
            </Link>
            <Link :href="route('tasks.index', { status: 'blocked' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Blocked Tasks</div>
                <div class="mt-2 text-3xl font-semibold text-orange-700">{{ stats.blocked_tasks ?? 0 }}</div>
            </Link>
            <Link :href="route('workload.index')" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">Overloaded This Week</div>
                <div class="mt-2 text-3xl font-semibold text-rose-700">{{ stats.overloaded_members ?? 0 }}</div>
            </Link>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Active Timers</div>
                <div class="mt-2 text-3xl font-semibold">{{ stats.active_timers ?? 0 }}</div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
