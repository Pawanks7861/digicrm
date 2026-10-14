<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    employee: Object,
    options: Object,
});

const form = useForm({
    employee_code: props.employee?.employee_code || '',
    first_name: props.employee?.first_name || '',
    last_name: props.employee?.last_name || '',
    work_email: props.employee?.work_email || '',
    phone: props.employee?.phone || '',
    user_id: props.employee?.user_id || '',
    branch_id: props.employee?.branch_id || '',
    department_id: props.employee?.department_id || '',
    designation_id: props.employee?.designation_id || '',
    reporting_manager_id: props.employee?.reporting_manager_id || '',
    joining_date: String(props.employee?.joining_date || '').slice(0, 10),
    exit_date: String(props.employee?.exit_date || '').slice(0, 10),
    probation_start: String(props.employee?.probation_start || '').slice(0, 10),
    probation_end: String(props.employee?.probation_end || '').slice(0, 10),
    probation_status: props.employee?.probation_status || '',
    confirmation_date: String(props.employee?.confirmation_date || '').slice(0, 10),
    confirmation_status: props.employee?.confirmation_status || '',
    employment_type: props.employee?.employment_type || 'full_time',
    employment_status: props.employee?.employment_status || 'active',
});

const submit = () => {
    const payload = { ...form.data() };
    ['user_id', 'branch_id', 'department_id', 'designation_id', 'reporting_manager_id', 'joining_date', 'exit_date', 'probation_start', 'probation_end', 'probation_status', 'confirmation_date', 'confirmation_status'].forEach((k) => {
        if (payload[k] === '') payload[k] = null;
    });
    if (props.employee) {
        form.transform(() => payload).put(route('hr.employees.update', props.employee.id));
    } else {
        form.transform(() => payload).post(route('hr.employees.store'));
    }
};

const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head :title="employee ? 'Edit employee' : 'Add employee'" />
    <AuthenticatedLayout>
        <Link :href="route('hr.employees.index')" class="text-sm text-teal-700 hover:underline">← Employees</Link>
        <h1 class="mb-6 mt-1 text-2xl font-semibold tracking-tight">{{ employee ? 'Edit employee' : 'Add employee' }}</h1>
        <form class="max-w-3xl space-y-4 rounded-2xl border border-slate-200 bg-white p-5" @submit.prevent="submit">
            <div class="grid gap-3 sm:grid-cols-2">
                <div><InputLabel value="Employee code" /><TextInput v-model="form.employee_code" class="mt-1 w-full" required /></div>
                <div>
                    <InputLabel value="Linked user" />
                    <select v-model="form.user_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="">None</option>
                        <option v-for="u in options.users" :key="u.id" :value="u.id">{{ u.name }} · {{ u.email }}</option>
                    </select>
                </div>
                <div><InputLabel value="First name" /><TextInput v-model="form.first_name" class="mt-1 w-full" required /></div>
                <div><InputLabel value="Last name" /><TextInput v-model="form.last_name" class="mt-1 w-full" /></div>
                <div><InputLabel value="Work email" /><TextInput v-model="form.work_email" type="email" class="mt-1 w-full" /></div>
                <div><InputLabel value="Phone" /><TextInput v-model="form.phone" class="mt-1 w-full" /></div>
                <div>
                    <InputLabel value="Branch" />
                    <select v-model="form.branch_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="">—</option>
                        <option v-for="b in options.branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Department" />
                    <select v-model="form.department_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="">—</option>
                        <option v-for="d in options.departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Designation" />
                    <select v-model="form.designation_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="">—</option>
                        <option v-for="d in options.designations" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Reporting manager" />
                    <select v-model="form.reporting_manager_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="">—</option>
                        <option v-for="m in options.managers" :key="m.id" :value="m.id">{{ m.first_name }} {{ m.last_name || '' }} ({{ m.employee_code }})</option>
                    </select>
                </div>
                <div><InputLabel value="Joining date" /><TextInput v-model="form.joining_date" type="date" class="mt-1 w-full" /></div>
                <div><InputLabel value="Exit date" /><TextInput v-model="form.exit_date" type="date" class="mt-1 w-full" /></div>
                <div><InputLabel value="Probation start" /><TextInput v-model="form.probation_start" type="date" class="mt-1 w-full" /></div>
                <div><InputLabel value="Probation end" /><TextInput v-model="form.probation_end" type="date" class="mt-1 w-full" /></div>
                <div><InputLabel value="Probation status" /><TextInput v-model="form.probation_status" class="mt-1 w-full" /></div>
                <div><InputLabel value="Confirmation date" /><TextInput v-model="form.confirmation_date" type="date" class="mt-1 w-full" /></div>
                <div>
                    <InputLabel value="Employment type" />
                    <select v-model="form.employment_type" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option v-for="t in options.employment_types" :key="t" :value="t">{{ label(t) }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Employment status" />
                    <select v-model="form.employment_status" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option v-for="s in options.employment_statuses" :key="s" :value="s">{{ label(s) }}</option>
                    </select>
                </div>
            </div>
            <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
        </form>
    </AuthenticatedLayout>
</template>
