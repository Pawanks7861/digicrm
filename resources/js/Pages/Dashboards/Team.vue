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
const hours = (sec) => `${(Number(sec || 0) / 3600).toFixed(1)}h`;
</script>

<template>
    <Head title="My Dashboard" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">My Dashboard</h1>
            <p class="mt-1 text-sm text-slate-500">Your tasks, time, and capacity.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Link :href="route('tasks.index', { bucket: 'today' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                <div class="text-xs font-semibold uppercase text-slate-400">My Tasks Today</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.tasks_today ?? 0 }}</div>
            </Link>
            <Link :href="route('tasks.index', { bucket: 'overdue' })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-rose-300">
                <div class="text-xs font-semibold uppercase text-slate-400">My Overdue</div>
                <div class="mt-2 text-3xl font-semibold text-rose-700">{{ data.tasks_overdue ?? 0 }}</div>
            </Link>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">This Week</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.tasks_week ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Changes Required</div>
                <div class="mt-2 text-3xl font-semibold text-orange-700">{{ data.changes_required ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Blocked</div>
                <div class="mt-2 text-3xl font-semibold text-orange-700">{{ data.blocked ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Logged Today</div>
                <div class="mt-2 text-3xl font-semibold">{{ hours(data.logged_seconds_today) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Period Logged</div>
                <div class="mt-2 text-3xl font-semibold">{{ hours(data.logged_seconds_period) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Week Utilization</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.planned_utilization != null ? data.planned_utilization + '%' : '—' }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ data.planned_minutes_week ?? 0 }}m planned / {{ data.capacity_minutes_week ?? 0 }}m capacity</div>
            </div>
        </div>

        <div v-if="data.running_timer" class="mt-6 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">
            Timer running since {{ new Date(data.running_timer.started_at).toLocaleTimeString() }}
            <Link :href="route('timesheet.index')" class="ml-2 font-medium underline">Timesheet</Link>
        </div>
        <LeadWidgets :leads="dashboard.leads" role="team" />
    </AuthenticatedLayout>
</template>
