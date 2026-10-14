<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

defineProps({
    runs: Array,
    can: Object,
});

const now = new Date();
const form = useForm({
    period: `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`,
});

const money = (value) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(Number(value || 0));
const day = (value) => String(value || '').slice(0, 10);
const nextStatus = {
    draft: 'review',
    review: 'approved',
    approved: 'locked',
};
const move = (run) => router.post(route('hr.payroll.transition', run.id), { status: nextStatus[run.status] });
</script>

<template>
    <Head title="Payroll" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <Link :href="route('hr.dashboard')" class="text-sm text-teal-700 hover:underline">← People & HR</Link>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">Payroll</h1>
                <p class="mt-1 text-sm text-slate-500">Draft runs use attendance, leave, and salary assignments. Statutory deductions are not configured.</p>
            </div>
            <form v-if="can.manage" class="flex items-end gap-2" @submit.prevent="form.post(route('hr.payroll.generate'))">
                <label class="text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Period</span>
                    <input v-model="form.period" type="month" class="rounded-lg border-slate-200 text-sm" required />
                </label>
                <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white" :disabled="form.processing">Generate draft</button>
            </form>
        </div>

        <EmptyState v-if="!runs.length" title="No payroll runs yet." description="Generate a draft for the current month." />

        <div v-for="run in runs" :key="run.id" class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                <div>
                    <div class="font-semibold">{{ run.run_code }}</div>
                    <div class="text-xs text-slate-500">{{ day(run.period_start) }} → {{ day(run.period_end) }} · {{ run.status }}</div>
                </div>
                <button
                    v-if="can.manage && nextStatus[run.status]"
                    type="button"
                    class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm capitalize"
                    @click="move(run)"
                >
                    Move to {{ nextStatus[run.status] }}
                </button>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Payable days</th>
                        <th class="px-4 py-3">LOP</th>
                        <th class="px-4 py-3">Gross</th>
                        <th class="px-4 py-3">Deductions</th>
                        <th class="px-4 py-3">Employer</th>
                        <th class="px-4 py-3">Net</th>
                        <th class="px-4 py-3"></th>
                        <th class="px-4 py-3">Payslip</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in run.items" :key="item.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ item.employee?.full_name || 'Employee' }}</div>
                            <div class="text-xs text-slate-400">{{ item.employee?.employee_code }}</div>
                        </td>
                        <td class="px-4 py-3">{{ item.payable_days }}</td>
                        <td class="px-4 py-3">{{ item.lop_days }}</td>
                        <td class="px-4 py-3">{{ money(item.gross) }}</td>
                        <td class="px-4 py-3">{{ money(item.deductions) }}</td>
                        <td class="px-4 py-3">{{ money(item.employer_contributions) }}</td>
                        <td class="px-4 py-3 font-medium">{{ money(item.net_pay) }}</td>
                        <td class="px-4 py-3">
                            <a v-if="item.payment_status !== 'blocked'" :href="route('hr.payslips.show', item.id)" class="text-teal-700">Payslip</a>
                            <span v-else class="text-amber-700">Blocked</span>
                        </td>
                        <td class="px-4 py-3">
                            <a v-if="item.payment_status !== 'blocked'" :href="route('hr.payslips.show', item.id)" class="text-teal-700">PDF</a>
                            <span v-else class="text-amber-700">Blocked</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
