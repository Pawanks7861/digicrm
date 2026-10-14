<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatHours } from '@/utils/hours';
import { Head, router, useForm } from '@inertiajs/vue3';

defineProps({
    policies: Array,
    entries: Array,
    can: Object,
});

const form = useForm({
    code: 'OT-STD',
    name: 'Standard overtime',
    multiplier: 1.5,
    minimum_minutes: 30,
    rounding_minutes: 15,
    requires_approval: true,
    comp_off_allowed: false,
    hourly_divisor_days: 30,
    hourly_divisor_hours: 8,
});

const save = () => form.post(route('hr.overtime.policies.store'));
const decide = (id, status) => router.post(route('hr.overtime.decide', id), { status });
const statusTone = {
    pending: 'bg-slate-100 text-slate-700',
    submitted: 'bg-amber-100 text-amber-900',
    approved: 'bg-emerald-100 text-emerald-900',
    rejected: 'bg-rose-100 text-rose-900',
    included_in_payroll: 'bg-indigo-100 text-indigo-900',
    cancelled: 'bg-slate-100 text-slate-500',
};
</script>

<template>
    <Head title="Overtime" />
    <AuthenticatedLayout>
        <h1 class="text-2xl font-semibold">Overtime</h1>
        <p class="mt-1 text-sm text-slate-500">Only approved overtime is added to payroll, and each attendance day can be paid once.</p>
        <form v-if="can.manage" class="mt-4 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-3" @submit.prevent="save">
            <input v-model="form.code" class="rounded-lg border-slate-200 text-sm" />
            <input v-model="form.name" class="rounded-lg border-slate-200 text-sm" />
            <input v-model="form.multiplier" type="number" step="0.1" class="rounded-lg border-slate-200 text-sm" />
            <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm text-white">Save policy</button>
        </form>
        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr><th class="px-4 py-3">Employee</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Minutes</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
                </thead>
                <tbody>
                    <tr v-for="entry in entries" :key="entry.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ entry.employee?.first_name }}</td>
                        <td class="px-4 py-3">{{ String(entry.work_date).slice(0, 10) }}</td>
                        <td class="px-4 py-3">{{ formatHours(entry.minutes) }}</td>
                        <td class="px-4 py-3">{{ entry.amount }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="statusTone[entry.status] || statusTone.pending">{{ entry.status.replaceAll('_', ' ') }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <button v-if="can.approve && entry.status === 'submitted'" class="mr-2 text-teal-700" type="button" @click="decide(entry.id, 'approved')">Approve</button>
                            <button v-if="can.approve && entry.status === 'submitted'" class="text-rose-700" type="button" @click="decide(entry.id, 'rejected')">Reject</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
