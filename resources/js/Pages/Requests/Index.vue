<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ requests: Object, filters: Object, options: Object });
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
watch([search, status], () => {
    router.get(route('requests.index'), { search: search.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true });
});
const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head title="Client Requests" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight">Client Requests</h1>
            <p class="mt-1 text-sm text-slate-500">Intake, scope review, and convert to work.</p>
        </div>
        <div class="mb-4 flex gap-2">
            <input v-model="search" type="search" placeholder="Search…" class="rounded-lg border-slate-200 text-sm sm:max-w-sm" />
            <select v-model="status" class="rounded-lg border-slate-200 text-sm">
                <option value="">All statuses</option>
                <option v-for="s in options.statuses" :key="s" :value="s">{{ label(s) }}</option>
            </select>
        </div>
        <div v-if="requests.data.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Request</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Priority</th>
                        <th class="px-4 py-3">Scope</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="r in requests.data" :key="r.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <Link :href="route('requests.show', r.id)" class="font-medium text-teal-800 hover:underline">{{ r.title }}</Link>
                            <div class="text-xs text-slate-400">{{ r.request_number }}</div>
                        </td>
                        <td class="px-4 py-3">{{ r.client?.company_name }}</td>
                        <td class="px-4 py-3 capitalize">{{ label(r.request_type) }}</td>
                        <td class="px-4 py-3 capitalize">{{ r.priority }}</td>
                        <td class="px-4 py-3"><StatusBadge :status="r.scope_status" /></td>
                        <td class="px-4 py-3"><StatusBadge :status="r.status" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="No client requests need attention." />
    </AuthenticatedLayout>
</template>
