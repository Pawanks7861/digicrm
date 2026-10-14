<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatHours } from '@/utils/hours';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    employee: Object,
    days: Array,
    today: Object,
    filters: Object,
    can: Object,
    register: Boolean,
    employees: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
});

const reasons = ref({});

const punch = (punch_type) => {
    router.post(route('hr.attendance.punch'), { punch_type, working_arrangement: 'office' }, { preserveScroll: true });
};

const correct = (dayId) => {
    router.post(route('hr.attendance.correct', dayId), {
        reason: reasons.value[dayId] || '',
    }, { preserveScroll: true });
};

const statusLabel = {
    present_full: 'Full day',
    present_half: 'Half day',
    unpaid_leave: 'Unpaid leave',
    paid_leave: 'Paid leave',
    weekly_off: 'Weekly off',
};
const label = (v) => statusLabel[v] || (v || '—').toString().replaceAll('_', ' ');
const day = (value) => String(value || '').slice(0, 10);
const editing = ref(null);
const netMinutes = (row) => {
    const stored = Number(row?.net_minutes || 0);
    if (stored > 0) {
        return stored;
    }
    const elapsed = Number(row?.elapsed_minutes || row?.worked_minutes || 0);
    const unpaid = Number(row?.unpaid_break_minutes || row?.break_minutes || 0);

    return Math.max(0, elapsed - unpaid);
};

const statusTone = {
    present_full: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    present_half: 'bg-sky-50 text-sky-800 ring-sky-200',
    absent: 'bg-rose-50 text-rose-800 ring-rose-200',
    holiday: 'bg-violet-50 text-violet-800 ring-violet-200',
    weekly_off: 'bg-slate-100 text-slate-600 ring-slate-200',
    paid_leave: 'bg-teal-50 text-teal-800 ring-teal-200',
    unpaid_leave: 'bg-orange-50 text-orange-800 ring-orange-200',
    exception: 'bg-rose-100 text-rose-900 ring-rose-300',
};
const tone = (status) => statusTone[status] || 'bg-slate-50 text-slate-700 ring-slate-200';
</script>

<template>
    <Head title="Attendance" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <Link :href="route('hr.dashboard')" class="text-sm text-accent hover:underline">← People & HR</Link>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">Attendance</h1>
                <p class="mt-1 text-sm text-mute">Hours are net of the unpaid break. CRM timers are not used here.</p>
            </div>
            <div v-if="can.punch" class="flex shrink-0 gap-2">
                <button type="button" class="whitespace-nowrap rounded-full bg-accent px-4 py-2 text-sm font-medium text-white" @click="punch('check_in')">Check in</button>
                <button type="button" class="whitespace-nowrap rounded-full border border-line bg-white px-4 py-2 text-sm" @click="punch('check_out')">Check out</button>
            </div>
        </div>

        <div class="mb-4 rounded-card border border-line bg-white p-4 text-sm shadow-float">
            <div class="text-xs uppercase tracking-wide text-mute">Today</div>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <span class="rounded-full px-2.5 py-1 text-sm font-medium capitalize ring-1" :class="tone(today?.status)">{{ label(today?.status) }}</span>
                <span v-if="today?.is_late" class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-900">Late</span>
                <span v-if="today?.is_early_departure" class="rounded-full bg-orange-100 px-2.5 py-1 text-xs font-medium text-orange-900">Early out</span>
                <span v-if="today?.missing_checkout" class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-900">Missing checkout</span>
            </div>
            <div class="mt-2 text-mute">{{ today ? formatHours(netMinutes(today)) + ' net' : 'No punches yet' }}</div>
        </div>

        <div class="overflow-x-auto rounded-card border border-line bg-white shadow-float">
            <table class="min-w-[880px] text-sm">
                <thead class="bg-mist/70 text-left text-xs uppercase tracking-wide text-mute">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th v-if="register" class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Shift</th>
                        <th class="px-4 py-3">Elapsed</th>
                        <th class="px-4 py-3">Break</th>
                        <th class="px-4 py-3">Net</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in days" :key="d.id" class="border-t border-line/70">
                        <td class="px-4 py-3 whitespace-nowrap">{{ day(d.work_date) }}</td>
                        <td v-if="register" class="px-4 py-3">{{ d.employee?.first_name }} {{ d.employee?.last_name || '' }}</td>
                        <td class="px-4 py-3">{{ d.shift?.name || '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ formatHours(d.elapsed_minutes || d.worked_minutes) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ formatHours(d.unpaid_break_minutes || d.break_minutes) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap font-medium">{{ formatHours(netMinutes(d)) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ring-1" :class="tone(d.status)">{{ label(d.status) }}</span>
                                <span v-if="d.is_late" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900">Late</span>
                                <span v-if="d.is_early_departure" class="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-900">Early out</span>
                                <span v-if="d.missing_checkout" class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-900">Missing checkout</span>
                                <span v-if="d.overtime_minutes" class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-900">OT {{ formatHours(d.overtime_minutes) }}</span>
                            </div>
                            <button v-if="can.correct && editing !== d.id" type="button" class="mt-1 text-xs text-mute underline" @click="editing = d.id">Correct</button>
                            <form v-if="can.correct && editing === d.id" class="mt-2 flex gap-1" @submit.prevent="correct(d.id)">
                                <input v-model="reasons[d.id]" required placeholder="Reason" class="w-28 rounded-full border-line px-2 py-1 text-xs" />
                                <button class="rounded-full bg-ink px-2 py-1 text-xs text-white" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
