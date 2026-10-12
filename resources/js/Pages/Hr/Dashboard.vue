<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    role_view: String,
    employee: Object,
    today: Object,
    pending_approvals: Number,
    pending_leave: Number,
    attendance_exceptions: Number,
    headcount: Number,
    pending_expenses: Number,
    payroll_readiness: Object,
});

const label = (v) => (v || '—').toString().replaceAll('_', ' ');
</script>

<template>
    <Head title="HR Dashboard" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">People & HR</h1>
                <p class="mt-1 text-sm text-slate-500">Attendance, leave, and team readiness — separate from CRM time tracking.</p>
            </div>
            <div class="flex flex-wrap gap-2 text-sm">
                <Link :href="route('hr.attendance.index')" class="rounded-lg border border-slate-200 px-3 py-1.5 hover:bg-slate-50">Attendance</Link>
                <Link :href="route('hr.leave.index')" class="rounded-lg border border-slate-200 px-3 py-1.5 hover:bg-slate-50">Leave</Link>
                <Link :href="route('hr.employees.index')" class="rounded-lg border border-slate-200 px-3 py-1.5 hover:bg-slate-50">Employees</Link>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase tracking-wide text-slate-400">Today</div>
                <div class="mt-2 text-lg font-semibold capitalize">{{ label(today?.status) }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ today ? `${today.worked_minutes || 0} min worked` : 'No attendance yet' }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase tracking-wide text-slate-400">Pending approvals</div>
                <div class="mt-2 text-2xl font-semibold">{{ pending_approvals }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase tracking-wide text-slate-400">Leave pending</div>
                <div class="mt-2 text-2xl font-semibold">{{ pending_leave }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase tracking-wide text-slate-400">Headcount</div>
                <div class="mt-2 text-2xl font-semibold">{{ headcount }}</div>
            </div>
        </div>

        <div v-if="role_view !== 'employee'" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold">Payroll readiness</h2>
            <p class="mt-1 text-sm text-slate-500">Actionable blockers before a draft payroll run.</p>
            <div v-if="payroll_readiness?.ready" class="mt-3 text-sm text-emerald-700">No blockers detected for the current month snapshot.</div>
            <ul v-else class="mt-3 space-y-2 text-sm">
                <li v-for="b in payroll_readiness?.blockers || []" :key="b.code" class="flex justify-between rounded-lg bg-amber-50 px-3 py-2 text-amber-900">
                    <span>{{ b.label }}</span>
                    <span class="font-semibold">{{ b.count }}</span>
                </li>
            </ul>
            <div class="mt-3 text-xs text-slate-400">Exceptions: {{ attendance_exceptions }} · Expenses in review: {{ pending_expenses }}</div>
        </div>
    </AuthenticatedLayout>
</template>
