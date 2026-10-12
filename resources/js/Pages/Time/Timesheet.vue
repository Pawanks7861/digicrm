<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    entries: Array,
    byDay: Array,
    period: String,
    date: String,
    from: String,
    to: String,
    totals: Object,
    users: Array,
    view_user_id: Number,
    running: Object,
    categories: Array,
    tasks: Array,
});

const fmt = (sec) => {
    const s = Number(sec || 0);
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    return h ? `${h}h ${m}m` : `${m}m`;
};

const form = useForm({
    task_id: '',
    hours: 1,
    minutes: 0,
    description: '',
    billable: true,
    category: 'client_work',
    date: props.date,
});

const startForm = useForm({ task_id: '', stop_existing: false });

const setPeriod = (period) => router.get(route('timesheet.index'), { period, date: props.date, user_id: props.view_user_id }, { preserveState: true });
</script>

<template>
    <Head title="My Timesheet" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">My Timesheet</h1>
                <p class="mt-1 text-sm text-slate-500">{{ from }} → {{ to }} · Total {{ fmt(totals.seconds) }} · Billable {{ fmt(totals.billable_seconds) }}</p>
            </div>
            <div class="flex gap-2">
                <button v-for="p in ['day','week','month']" :key="p" type="button" class="rounded-lg border px-3 py-1.5 text-sm capitalize" :class="period===p ? 'border-teal-600 bg-teal-50 text-teal-800' : 'border-slate-200'" @click="setPeriod(p)">{{ p }}</button>
            </div>
        </div>

        <div class="mb-6 grid gap-4 lg:grid-cols-2">
            <form class="rounded-xl border border-slate-200 bg-white p-4 space-y-3" @submit.prevent="startForm.post(route('time-entries.start'))">
                <h3 class="text-sm font-semibold">Start Timer</h3>
                <select v-model="startForm.task_id" class="w-full rounded-md border-slate-300 text-sm">
                    <option value="">No task</option>
                    <option v-for="t in tasks" :key="t.id" :value="t.id">{{ t.title }}</option>
                </select>
                <label class="flex items-center gap-2 text-sm"><input v-model="startForm.stop_existing" type="checkbox" /> Stop current timer if running</label>
                <PrimaryButton :disabled="startForm.processing">Start</PrimaryButton>
            </form>
            <form class="rounded-xl border border-slate-200 bg-white p-4 space-y-3" @submit.prevent="form.post(route('time-entries.store'), { onSuccess: () => form.reset('description', 'hours', 'minutes') })">
                <h3 class="text-sm font-semibold">Log Time</h3>
                <select v-model="form.task_id" class="w-full rounded-md border-slate-300 text-sm">
                    <option value="">Internal / no task</option>
                    <option v-for="t in tasks" :key="t.id" :value="t.id">{{ t.title }}</option>
                </select>
                <div class="flex gap-2">
                    <input v-model.number="form.hours" type="number" min="0" class="w-24 rounded-md border-slate-300 text-sm" /> <span class="self-center text-sm">h</span>
                    <input v-model.number="form.minutes" type="number" min="0" max="59" class="w-24 rounded-md border-slate-300 text-sm" /> <span class="self-center text-sm">m</span>
                </div>
                <input v-model="form.description" type="text" placeholder="What did you work on?" class="w-full rounded-md border-slate-300 text-sm" />
                <label class="flex items-center gap-2 text-sm"><input v-model="form.billable" type="checkbox" /> Billable</label>
                <PrimaryButton :disabled="form.processing">Add Time</PrimaryButton>
            </form>
        </div>

        <div v-if="byDay?.length" class="space-y-4">
            <div v-for="day in byDay" :key="day.date" class="rounded-xl border border-slate-200 bg-white">
                <div class="flex justify-between border-b border-slate-100 px-4 py-3 text-sm font-semibold">
                    <span>{{ day.date }}</span>
                    <span>{{ fmt(day.seconds) }}</span>
                </div>
                <ul class="divide-y divide-slate-100 text-sm">
                    <li v-for="e in day.entries" :key="e.id" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                        <div>
                            <div class="font-medium">{{ e.task?.title || e.category || 'Time' }}</div>
                            <div class="text-xs text-slate-500">{{ e.client?.company_name || 'Internal' }} · {{ e.entry_type }} · {{ e.billable ? 'Billable' : 'Non-billable' }}</div>
                        </div>
                        <div class="font-mono">{{ fmt(e.duration_seconds) }}</div>
                    </li>
                </ul>
            </div>
        </div>
        <EmptyState v-else title="No time logged for this period." />
    </AuthenticatedLayout>
</template>
