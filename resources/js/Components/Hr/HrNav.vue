<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const perms = computed(() => page.props.auth?.user?.permissions || []);
const roles = computed(() => page.props.auth?.user?.roles || []);
const can = (...names) => roles.value.includes('super_admin') || names.some((name) => perms.value.includes(name));

const items = computed(() => [
    { name: 'Dashboard', href: route('hr.dashboard'), show: true, current: route().current('hr.dashboard') },
    { name: 'Employees', href: route('hr.employees.index'), show: can('hr.employees.view_own', 'hr.employees.view_team', 'hr.employees.view_all', 'hr.employees.manage'), current: route().current('hr.employees.*') },
    { name: 'Shifts', href: route('hr.shifts.index'), show: can('hr.shifts.view', 'hr.shifts.manage'), current: route().current('hr.shifts.*') },
    { name: 'Attendance', href: route('hr.attendance.index'), show: can('hr.attendance.view_own', 'hr.attendance.view_team', 'hr.attendance.manage'), current: route().current('hr.attendance.*') },
    { name: 'Leave', href: route('hr.leave.index'), show: can('hr.leave.view_own', 'hr.leave.request', 'hr.leave.approve'), current: route().current('hr.leave.*') },
    { name: 'Holidays', href: route('hr.holidays.index'), show: can('hr.holidays.view', 'hr.holidays.manage'), current: route().current('hr.holidays.*') },
    { name: 'Overtime', href: route('hr.overtime.index'), show: can('hr.overtime.view', 'hr.overtime.request', 'hr.overtime.approve'), current: route().current('hr.overtime.*') },
    { name: 'Salary', href: route('hr.salary.index'), show: can('hr.salary.view', 'hr.salary.manage'), current: route().current('hr.salary.*') },
    { name: 'Payroll', href: route('hr.payroll.index'), show: can('hr.payroll.view', 'hr.payroll.manage'), current: route().current('hr.payroll.*') || route().current('hr.payslips.*') },
].filter((item) => item.show));
</script>

<template>
    <nav class="mb-4 flex gap-2 overflow-x-auto pb-1 text-sm">
        <Link
            v-for="item in items"
            :key="item.name"
            :href="item.href"
            class="shrink-0 rounded-full px-3 py-1.5"
            :class="item.current ? 'bg-teal-700 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200'"
        >
            {{ item.name }}
        </Link>
    </nav>
</template>
