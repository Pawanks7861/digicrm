<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ workload: Object, utilization: Object, capacity: Object, allocations: Array, from: String, to: String });

const fmt = (min) => {
    const m = Number(min || 0);
    return `${Math.floor(m / 60)}h ${m % 60}m`;
};
</script>

<template>
    <Head :title="`Workload · ${workload.user.name}`" />
    <AuthenticatedLayout>
        <Link :href="route('workload.index')" class="text-sm text-teal-700 hover:underline">← Workload</Link>
        <h1 class="mt-2 text-2xl font-semibold">{{ workload.user.name }}</h1>
        <p class="text-sm text-slate-500">{{ from }} → {{ to }}</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border bg-white p-4"><div class="text-xs uppercase text-slate-400">Capacity</div><div class="mt-1 text-xl font-semibold">{{ fmt(workload.available_minutes) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs uppercase text-slate-400">Allocated</div><div class="mt-1 text-xl font-semibold">{{ fmt(workload.allocated_minutes) }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs uppercase text-slate-400">Planned</div><div class="mt-1 text-xl font-semibold">{{ workload.planned_utilization }}%</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs uppercase text-slate-400">Actual / Billable</div><div class="mt-1 text-xl font-semibold">{{ utilization.actual_utilization }}% / {{ utilization.billable_utilization }}%</div></div>
        </div>
        <h2 class="mt-8 text-sm font-semibold">Allocations</h2>
        <ul v-if="allocations?.length" class="mt-2 space-y-2">
            <li v-for="a in allocations" :key="a.id" class="rounded-xl border bg-white px-4 py-3 text-sm flex justify-between">
                <Link :href="route('tasks.show', a.task_id)" class="font-medium text-teal-700 hover:underline">{{ a.task?.title }}</Link>
                <span>{{ fmt(a.allocated_minutes) }}</span>
            </li>
        </ul>
        <EmptyState v-else class="mt-4" title="No active allocations." />
    </AuthenticatedLayout>
</template>
