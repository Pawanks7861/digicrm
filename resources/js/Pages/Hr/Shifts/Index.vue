<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatHours } from '@/utils/hours';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    shifts: Array,
    assignments: Array,
    departmentAssignments: Array,
    employees: Array,
    departments: Array,
    can: Object,
});

const form = useForm({
    code: '',
    name: '',
    category: 'general',
    start_time: '09:30',
    end_time: '18:30',
    lunch_break_minutes: 30,
    tea_break_minutes: 15,
    grace_minutes: 10,
    full_day_minutes: 480,
    half_day_minutes: 240,
    weekly_off_days: [0],
    overtime_eligible: false,
    is_overnight: false,
    half_day_rule: 'hours',
    is_active: true,
    timezone: 'Asia/Kolkata',
});

const assign = useForm({
    work_shift_id: '',
    employee_id: '',
    department_id: '',
    effective_from: '',
    effective_to: '',
});

const save = () => form.post(route('hr.shifts.store'));
const assignShift = () => assign.post(route('hr.shifts.assign'));
const categoryTone = {
    morning: 'bg-amber-100 text-amber-900',
    evening: 'bg-orange-100 text-orange-900',
    night: 'bg-indigo-100 text-indigo-900',
    general: 'bg-emerald-100 text-emerald-900',
    custom: 'bg-slate-100 text-slate-700',
};
</script>

<template>
    <Head title="Shifts" />
    <AuthenticatedLayout>
        <h1 class="text-2xl font-semibold">Shifts</h1>
        <p class="mt-1 text-sm text-slate-500">Net scheduled hours use start, end, and unpaid lunch plus tea. Midnight shifts stay one work date.</p>

        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Shift</th>
                        <th class="px-4 py-3">Time</th>
                        <th class="px-4 py-3">Breaks</th>
                        <th class="px-4 py-3">Full / half day</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="shift in shifts" :key="shift.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ shift.name }}</div>
                            <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="categoryTone[shift.category] || categoryTone.custom">{{ shift.category || 'general' }}</span>
                        </td>
                        <td class="px-4 py-3">{{ String(shift.start_time).slice(0, 5) }}–{{ String(shift.end_time).slice(0, 5) }} <span v-if="shift.is_overnight" class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-900">Overnight</span></td>
                        <td class="px-4 py-3">Lunch {{ formatHours(shift.lunch_break_minutes) }} · Tea {{ formatHours(shift.tea_break_minutes) }}</td>
                        <td class="px-4 py-3">{{ formatHours(shift.full_day_minutes) }} / {{ formatHours(shift.half_day_minutes) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <form v-if="can.manage" class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-3" @submit.prevent="save">
            <input v-model="form.code" required placeholder="Code" class="rounded-lg border-slate-200 text-sm" />
            <input v-model="form.name" required placeholder="Name" class="rounded-lg border-slate-200 text-sm" />
            <select v-model="form.category" class="rounded-lg border-slate-200 text-sm">
                <option value="morning">Morning</option>
                <option value="evening">Evening</option>
                <option value="night">Night</option>
                <option value="general">General</option>
                <option value="custom">Custom</option>
            </select>
            <input v-model="form.start_time" type="time" required class="rounded-lg border-slate-200 text-sm" />
            <input v-model="form.end_time" type="time" required class="rounded-lg border-slate-200 text-sm" />
            <select v-model="form.half_day_rule" class="rounded-lg border-slate-200 text-sm">
                <option value="hours">Half day by hours</option>
                <option value="arrival">Half day by late arrival</option>
                <option value="both">Hours and arrival</option>
            </select>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.overtime_eligible" type="checkbox" /> Overtime eligible</label>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" /> Active</label>
            <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm text-white" :disabled="form.processing">Save shift</button>
        </form>

        <form v-if="can.manage" class="mt-4 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-3" @submit.prevent="assignShift">
            <select v-model="assign.work_shift_id" required class="rounded-lg border-slate-200 text-sm">
                <option value="">Shift</option>
                <option v-for="shift in shifts" :key="shift.id" :value="shift.id">{{ shift.name }}</option>
            </select>
            <select v-model="assign.employee_id" class="rounded-lg border-slate-200 text-sm">
                <option value="">Employee</option>
                <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.first_name }} {{ employee.employee_code }}</option>
            </select>
            <select v-model="assign.department_id" class="rounded-lg border-slate-200 text-sm">
                <option value="">Department</option>
                <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
            </select>
            <input v-model="assign.effective_from" type="date" required class="rounded-lg border-slate-200 text-sm" />
            <input v-model="assign.effective_to" type="date" class="rounded-lg border-slate-200 text-sm" />
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Assign</button>
        </form>

        <div class="mt-4 text-sm text-slate-600">
            <div v-for="row in assignments" :key="'e'+row.id">
                {{ row.employee?.first_name }} → {{ row.shift?.name }}
                <Link :href="route('hr.shifts.calendar', row.employee_id)" class="text-teal-700">Calendar</Link>
            </div>
            <div v-for="row in departmentAssignments" :key="'d'+row.id">{{ row.department?.name }} → {{ row.shift?.name }}</div>
        </div>
    </AuthenticatedLayout>
</template>
