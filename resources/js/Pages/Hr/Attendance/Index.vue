<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    employee: Object,
    days: Array,
    today: Object,
    filters: Object,
    can: Object,
});

const punch = (punch_type) => {
    router.post(route('hr.attendance.punch'), { punch_type, working_arrangement: 'office' }, { preserveScroll: true });
};

const label = (v) => (v || '—').toString().replaceAll('_', ' ');
</script>

<template>
    <Head title="Attendance" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Attendance</h1>
                <p class="mt-1 text-sm text-slate-500">Raw punches stay separate from derived daily status. CRM timers are not used here.</p>
            </div>
            <div v-if="can.punch" class="flex gap-2">
                <button type="button" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white" @click="punch('check_in')">Check in</button>
                <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm" @click="punch('check_out')">Check out</button>
            </div>
        </div>

        <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <div class="text-xs uppercase text-slate-400">Today</div>
            <div class="mt-1 text-lg font-semibold capitalize">{{ label(today?.status) }}</div>
            <div class="text-slate-500">{{ today ? `${today.worked_minutes || 0} minutes · missing checkout: ${today.missing_checkout ? 'yes' : 'no'}` : 'No punches yet' }}</div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Worked</th>
                        <th class="px-4 py-3">Flags</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in days" :key="d.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ d.work_date }}</td>
                        <td class="px-4 py-3 capitalize">{{ label(d.status) }}</td>
                        <td class="px-4 py-3">{{ d.worked_minutes }} min</td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            <span v-if="d.is_late">Late </span>
                            <span v-if="d.is_early_departure">Early out </span>
                            <span v-if="d.missing_checkout">Open interval</span>
                            <span v-if="!d.is_late && !d.is_early_departure && !d.missing_checkout">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
