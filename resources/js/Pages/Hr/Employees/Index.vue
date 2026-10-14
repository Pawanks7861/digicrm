<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    employees: Object,
    filters: Object,
    statuses: Array,
    can: Object,
});

const search = ref(props.filters.search || '');
const employment_status = ref(props.filters.employment_status || '');

watch([search, employment_status], () => {
    router.get(route('hr.employees.index'), {
        search: search.value || undefined,
        employment_status: employment_status.value || undefined,
    }, { preserveState: true, replace: true });
});

const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head title="Employees" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <Link :href="route('hr.dashboard')" class="text-sm text-teal-700 hover:underline">← People & HR</Link>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">Employees</h1>
                <p class="mt-1 text-sm text-slate-500">Employment records linked to login identities where needed.</p>
            </div>
            <Link v-if="can.create" :href="route('hr.employees.create')" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white">Add employee</Link>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <input v-model="search" type="search" placeholder="Search…" class="rounded-lg border-slate-200 text-sm" />
            <select v-model="employment_status" class="rounded-lg border-slate-200 text-sm">
                <option value="">All statuses</option>
                <option v-for="s in statuses" :key="s" :value="s">{{ label(s) }}</option>
            </select>
        </div>

        <div v-if="!(employees.data || []).length">
            <EmptyState title="No employees yet" description="Create an employee profile to enable attendance and leave." />
        </div>
        <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3">Manager</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="e in employees.data" :key="e.id" class="border-t border-slate-100">
                        <td class="px-4 py-3 font-mono text-xs">{{ e.employee_code }}</td>
                        <td class="px-4 py-3">
                            <Link :href="route('hr.employees.show', e.id)" class="font-medium text-teal-800 hover:underline">{{ e.full_name }}</Link>
                            <div class="text-xs text-slate-400">{{ e.work_email || e.user?.email }}</div>
                        </td>
                        <td class="px-4 py-3">{{ e.department?.name || '—' }}</td>
                        <td class="px-4 py-3">{{ e.reporting_manager ? `${e.reporting_manager.first_name} ${e.reporting_manager.last_name || ''}`.trim() : '—' }}</td>
                        <td class="px-4 py-3 capitalize">{{ label(e.employment_status) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
