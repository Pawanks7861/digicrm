<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    structures: Array,
    components: Array,
    assignments: Array,
    employees: Array,
    can: Object,
});

const structure = useForm({ code: '', name: '' });
const component = useForm({
    code: '',
    name: '',
    component_type: 'earning',
    calc_type: 'fixed',
    amount: '',
    percentage: '',
    prorate: true,
    show_on_payslip: true,
    is_taxable: true,
    pf_applicable: false,
    esi_applicable: false,
});
const assignment = useForm({
    employee_id: '',
    salary_structure_id: '',
    ctc_monthly: '',
    gross_monthly: '',
    effective_from: '',
});

const money = (value) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(Number(value || 0));
const day = (value) => String(value || '').slice(0, 10);
const typeTone = {
    earning: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    deduction: 'bg-rose-50 text-rose-800 ring-rose-200',
    employer_contribution: 'bg-indigo-50 text-indigo-800 ring-indigo-200',
};
</script>

<template>
    <Head title="Salary structures" />
    <AuthenticatedLayout>
        <h1 class="text-2xl font-semibold tracking-tight">Salary structures</h1>
        <p class="mt-1 max-w-2xl text-sm text-mute">Each person is shown once, on the assignment in effect today. Employer contributions are kept separate from net pay.</p>

        <div class="mt-5 overflow-x-auto rounded-card border border-line bg-white shadow-float">
            <table class="min-w-full text-sm">
                <thead class="bg-mist/70 text-left text-xs uppercase tracking-wide text-mute">
                    <tr>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Structure</th>
                        <th class="px-4 py-3">Monthly gross</th>
                        <th class="px-4 py-3">From</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in assignments" :key="row.employee_id" class="border-t border-line/70">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ row.employee?.first_name }} {{ row.employee?.last_name || '' }}</div>
                            <div class="text-xs text-mute">{{ row.employee?.employee_code }}</div>
                        </td>
                        <td class="px-4 py-3">{{ row.structure?.name }}</td>
                        <td class="px-4 py-3 font-medium">{{ money(row.gross_monthly) }}</td>
                        <td class="px-4 py-3">
                            {{ day(row.effective_from) }}
                            <span class="ml-2 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-emerald-200">Current</span>
                        </td>
                    </tr>
                    <tr v-if="!assignments.length">
                        <td colspan="4" class="px-4 py-6 text-mute">No salary assignments yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 overflow-x-auto rounded-card border border-line bg-white shadow-float">
            <table class="min-w-full text-sm">
                <thead class="bg-mist/70 text-left text-xs uppercase tracking-wide text-mute">
                    <tr>
                        <th class="px-4 py-3">Component</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in components" :key="row.id" class="border-t border-line/70">
                        <td class="px-4 py-3">{{ row.name }} <span class="text-mute">{{ row.code }}</span></td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium capitalize ring-1" :class="typeTone[row.component_type]">{{ row.component_type.replaceAll('_', ' ') }}</span>
                        </td>
                        <td class="px-4 py-3">{{ row.calc_type === 'percentage' ? `${row.percentage}%` : money(row.amount) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="can.manage" class="mt-4 grid items-start gap-4 lg:grid-cols-3">
            <form class="space-y-3 rounded-card border border-line bg-white p-4 shadow-float" @submit.prevent="structure.post(route('hr.salary.structures.store'))">
                <h2 class="font-semibold">Template</h2>
                <label class="block text-xs text-mute">Code<input v-model="structure.code" required class="mt-1 w-full rounded-xl border-line text-sm" /></label>
                <label class="block text-xs text-mute">Name<input v-model="structure.name" required class="mt-1 w-full rounded-xl border-line text-sm" /></label>
                <button class="rounded-full bg-accent px-4 py-2 text-sm text-white">Save template</button>
            </form>
            <form class="space-y-3 rounded-card border border-line bg-white p-4 shadow-float" @submit.prevent="component.post(route('hr.salary.components.store'))">
                <h2 class="font-semibold">Component</h2>
                <label class="block text-xs text-mute">Code<input v-model="component.code" required class="mt-1 w-full rounded-xl border-line text-sm" /></label>
                <label class="block text-xs text-mute">Name<input v-model="component.name" required class="mt-1 w-full rounded-xl border-line text-sm" /></label>
                <label class="block text-xs text-mute">Category
                    <select v-model="component.component_type" class="mt-1 w-full rounded-xl border-line text-sm">
                        <option value="earning">Earning</option>
                        <option value="deduction">Deduction</option>
                        <option value="employer_contribution">Employer contribution</option>
                    </select>
                </label>
                <label class="block text-xs text-mute">Calculation
                    <select v-model="component.calc_type" class="mt-1 w-full rounded-xl border-line text-sm">
                        <option value="fixed">Fixed amount</option>
                        <option value="percentage">Percentage</option>
                    </select>
                </label>
                <label class="block text-xs text-mute">Amount<input v-model="component.amount" type="number" step="0.01" class="mt-1 w-full rounded-xl border-line text-sm" /></label>
                <label class="block text-xs text-mute">Percentage<input v-model="component.percentage" type="number" step="0.01" class="mt-1 w-full rounded-xl border-line text-sm" /></label>
                <button class="rounded-full bg-accent px-4 py-2 text-sm text-white">Save component</button>
            </form>
            <form class="space-y-3 rounded-card border border-line bg-white p-4 shadow-float" @submit.prevent="assignment.post(route('hr.salary.assign'))">
                <h2 class="font-semibold">Assign</h2>
                <label class="block text-xs text-mute">Employee
                    <select v-model="assignment.employee_id" required class="mt-1 w-full rounded-xl border-line text-sm">
                        <option value="">Select</option>
                        <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.first_name }} {{ employee.employee_code }}</option>
                    </select>
                </label>
                <label class="block text-xs text-mute">Structure
                    <select v-model="assignment.salary_structure_id" required class="mt-1 w-full rounded-xl border-line text-sm">
                        <option value="">Select</option>
                        <option v-for="row in structures" :key="row.id" :value="row.id">{{ row.name }}</option>
                    </select>
                </label>
                <label class="block text-xs text-mute">Monthly gross<input v-model="assignment.gross_monthly" required type="number" step="0.01" class="mt-1 w-full rounded-xl border-line text-sm" /></label>
                <label class="block text-xs text-mute">Monthly CTC<input v-model="assignment.ctc_monthly" required type="number" step="0.01" class="mt-1 w-full rounded-xl border-line text-sm" /></label>
                <label class="block text-xs text-mute">Effective from<input v-model="assignment.effective_from" required type="date" class="mt-1 w-full rounded-xl border-line text-sm" /></label>
                <button class="rounded-full bg-ink px-4 py-2 text-sm text-white">Assign salary</button>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
