<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    items: Object,
    filters: Object,
    options: Object,
    stats: Object,
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const platform = ref(props.filters.platform || '');

watch([search, status, platform], () => {
    router.get(route('content.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        platform: platform.value || undefined,
    }, { preserveState: true, replace: true });
});

const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head title="Content" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Content Library</h1>
                <p class="mt-1 text-sm text-slate-500">Creatives, revisions, approvals, and publishing.</p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('calendar.index')" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Calendar</Link>
                <Link :href="route('content.create')" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Create Content</Link>
            </div>
        </div>

        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                <div class="text-xs uppercase text-slate-400">Waiting on Client</div>
                <div class="mt-1 text-2xl font-semibold">{{ stats?.waiting_client ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                <div class="text-xs uppercase text-slate-400">Client Changes</div>
                <div class="mt-1 text-2xl font-semibold text-orange-700">{{ stats?.changes_requested ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                <div class="text-xs uppercase text-slate-400">Ready to Schedule</div>
                <div class="mt-1 text-2xl font-semibold text-teal-700">{{ stats?.ready_to_schedule ?? 0 }}</div>
            </div>
        </div>

        <div class="mb-4 flex flex-col gap-3 sm:flex-row">
            <input v-model="search" type="search" placeholder="Search title, client, campaign…" class="w-full rounded-lg border-slate-200 text-sm sm:max-w-sm" />
            <select v-model="status" class="rounded-lg border-slate-200 text-sm sm:w-48">
                <option value="">All statuses</option>
                <option v-for="s in options.statuses" :key="s" :value="s">{{ label(s) }}</option>
            </select>
            <select v-model="platform" class="rounded-lg border-slate-200 text-sm sm:w-40">
                <option value="">All platforms</option>
                <option v-for="p in options.platforms" :key="p" :value="p">{{ label(p) }}</option>
            </select>
        </div>

        <div v-if="items.data.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Content</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Platform</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Revision</th>
                        <th class="px-4 py-3">Scheduled</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="item in items.data" :key="item.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <Link :href="route('content.show', item.id)" class="font-medium text-teal-800 hover:underline">{{ item.title }}</Link>
                            <div class="text-xs text-slate-400">{{ item.campaign?.name || '—' }}</div>
                        </td>
                        <td class="px-4 py-3">{{ item.client?.company_name }}</td>
                        <td class="px-4 py-3 capitalize">{{ label(item.platform) || '—' }}</td>
                        <td class="px-4 py-3 capitalize">{{ label(item.content_type) }}</td>
                        <td class="px-4 py-3">{{ item.current_revision ? `v${item.current_revision.revision_number}` : '—' }}</td>
                        <td class="px-4 py-3 text-xs">{{ item.scheduled_at ? new Date(item.scheduled_at).toLocaleString() : '—' }}</td>
                        <td class="px-4 py-3"><StatusBadge :status="item.status" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="No content has been created yet." description="Create content from a deliverable or start a new item." />
    </AuthenticatedLayout>
</template>
