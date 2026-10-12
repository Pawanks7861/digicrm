<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    employee: Object,
    can: Object,
});

const label = (v) => (v || '—').toString().replaceAll('_', ' ');
</script>

<template>
    <Head :title="employee.full_name" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <Link :href="route('hr.employees.index')" class="text-sm text-slate-500 hover:underline">← Employees</Link>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ employee.full_name }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ employee.employee_code }} · <span class="capitalize">{{ label(employee.employment_status) }}</span></p>
            </div>
            <Link v-if="can.update" :href="route('hr.employees.edit', employee.id)" class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Edit</Link>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm">
                <h2 class="font-semibold">Employment</h2>
                <dl class="mt-3 space-y-2">
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Type</dt><dd class="capitalize">{{ label(employee.employment_type) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Joined</dt><dd>{{ employee.joining_date || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Exit</dt><dd>{{ employee.exit_date || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Branch</dt><dd>{{ employee.branch?.name || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Department</dt><dd>{{ employee.department?.name || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Designation</dt><dd>{{ employee.designation?.name || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Manager</dt><dd>{{ employee.reporting_manager ? `${employee.reporting_manager.first_name} ${employee.reporting_manager.last_name || ''}`.trim() : '—' }}</dd></div>
                </dl>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm">
                <h2 class="font-semibold">Login identity</h2>
                <p class="mt-3 text-slate-600">{{ employee.user ? `${employee.user.name} · ${employee.user.email}` : 'Not linked to a login user' }}</p>
                <p v-if="!can.view_salary" class="mt-4 text-xs text-slate-400">Salary and bank details are hidden for your role.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
