<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    tasks: Object,
    filters: Object,
    statuses: Array,
    priorities: Array,
    view: String,
    can: Object,
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const bucket = ref(props.filters.bucket || '');
const viewMode = ref(props.view || 'list');

watch([search, status, bucket], () => {
    router.get(route('tasks.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        bucket: bucket.value || undefined,
        view: viewMode.value,
        mine: props.filters.mine,
    }, { preserveState: true, replace: true });
});

const label = (v) => (v || '').replaceAll('_', ' ');
const columns = ['to_do', 'in_progress', 'internal_review', 'changes_required', 'blocked', 'completed'];
const byStatus = (s) => (props.tasks.data || []).filter((t) => t.status === s);

const setStatus = (task, status) => {
    router.post(route('tasks.status', task.id), { status }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Tasks" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">My Tasks</h1>
                <p class="mt-1 text-sm text-slate-500">Work assigned to you and your campaigns.</p>
            </div>
            <div class="flex gap-2">
                <button type="button" class="rounded-lg border px-3 py-1.5 text-sm" :class="viewMode === 'list' ? 'border-teal-600 text-teal-800' : 'border-slate-200'" @click="viewMode = 'list'">List</button>
                <button type="button" class="rounded-lg border px-3 py-1.5 text-sm" :class="viewMode === 'kanban' ? 'border-teal-600 text-teal-800' : 'border-slate-200'" @click="viewMode = 'kanban'">Kanban</button>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <input v-model="search" type="search" placeholder="Search tasks…" class="rounded-lg border-slate-200 text-sm" />
            <select v-model="bucket" class="rounded-lg border-slate-200 text-sm">
                <option value="">All open</option>
                <option value="overdue">Overdue</option>
                <option value="today">Due Today</option>
            </select>
            <select v-model="status" class="rounded-lg border-slate-200 text-sm">
                <option value="">All statuses</option>
                <option v-for="s in statuses" :key="s" :value="s">{{ label(s) }}</option>
            </select>
        </div>

        <div v-if="viewMode === 'kanban'" class="flex gap-3 overflow-x-auto pb-4">
            <div v-for="col in columns" :key="col" class="min-w-[220px] flex-1 rounded-xl border border-slate-200 bg-slate-50 p-3">
                <div class="mb-2 text-xs font-semibold uppercase text-slate-500">{{ label(col) }}</div>
                <div class="space-y-2">
                    <div v-for="t in byStatus(col)" :key="t.id" class="rounded-lg border border-slate-200 bg-white p-3 text-sm shadow-sm">
                        <Link :href="route('tasks.show', t.id)" class="font-medium text-teal-800 hover:underline">{{ t.title }}</Link>
                        <div class="mt-1 text-xs text-slate-400">{{ t.client?.company_name }}</div>
                        <select class="mt-2 w-full rounded border-slate-200 text-xs" :value="t.status" @change="setStatus(t, $event.target.value)">
                            <option v-for="s in statuses" :key="s" :value="s">{{ label(s) }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div v-else-if="tasks.data.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Task</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Due</th>
                        <th class="px-4 py-3">Priority</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="t in tasks.data" :key="t.id">
                        <td class="px-4 py-3">
                            <Link :href="route('tasks.show', t.id)" class="font-medium text-teal-800 hover:underline">{{ t.title }}</Link>
                            <span v-if="t.is_overdue" class="ml-2 text-xs text-rose-600">Overdue</span>
                        </td>
                        <td class="px-4 py-3">{{ t.client?.company_name }}</td>
                        <td class="px-4 py-3">{{ t.due_date || '—' }}</td>
                        <td class="px-4 py-3 capitalize">{{ label(t.priority) }}</td>
                        <td class="px-4 py-3"><StatusBadge :status="t.status" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="No tasks assigned." description="Tasks from campaigns will appear here." />
    </AuthenticatedLayout>
</template>
