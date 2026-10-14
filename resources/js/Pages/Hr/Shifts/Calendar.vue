<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    employee: Object,
    month: String,
    days: Array,
});
</script>

<template>
    <Head title="Shift calendar" />
    <AuthenticatedLayout>
        <Link :href="route('hr.shifts.index')" class="text-sm text-teal-700">← Shifts</Link>
        <h1 class="mt-1 text-2xl font-semibold">{{ employee.first_name }} · {{ month }}</h1>
        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
            <div v-for="day in days" :key="day.date" class="rounded-xl border border-slate-200 bg-white p-3 text-sm">
                <div class="text-slate-400">{{ day.date }}</div>
                <div class="font-medium">{{ day.shift?.name || 'Unassigned' }}</div>
                <div v-if="day.shift" class="text-xs text-slate-500">{{ String(day.shift.start_time).slice(0, 5) }}–{{ String(day.shift.end_time).slice(0, 5) }}</div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
