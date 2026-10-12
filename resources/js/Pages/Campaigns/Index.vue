<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    campaigns: Object,
    filters: Object,
    types: Array,
    statuses: Array,
    priorities: Array,
    clients: Array,
    can: Object,
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const campaign_type = ref(props.filters.campaign_type || '');

watch([search, status, campaign_type], () => {
    router.get(route('campaigns.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        campaign_type: campaign_type.value || undefined,
    }, { preserveState: true, replace: true });
});

const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head title="Campaigns" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Campaigns</h1>
                <p class="mt-1 text-sm text-slate-500">Plan and run client campaigns, deliverables, and work.</p>
            </div>
            <Link v-if="can.create" :href="route('campaigns.create')" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">
                New Campaign
            </Link>
        </div>

        <div class="mb-4 flex flex-col gap-3 sm:flex-row">
            <input v-model="search" type="search" placeholder="Search name or code…" class="w-full rounded-lg border-slate-200 text-sm sm:max-w-sm" />
            <select v-model="status" class="rounded-lg border-slate-200 text-sm sm:w-40">
                <option value="">All statuses</option>
                <option v-for="s in statuses" :key="s" :value="s">{{ label(s) }}</option>
            </select>
            <select v-model="campaign_type" class="rounded-lg border-slate-200 text-sm sm:w-48">
                <option value="">All types</option>
                <option v-for="t in types" :key="t" :value="t">{{ label(t) }}</option>
            </select>
        </div>

        <div v-if="campaigns.data.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Campaign</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Manager</th>
                        <th class="px-4 py-3">Dates</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="c in campaigns.data" :key="c.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <Link :href="route('campaigns.show', c.id)" class="font-medium text-teal-800 hover:underline">{{ c.name }}</Link>
                            <div class="text-xs text-slate-400">{{ c.code }}</div>
                        </td>
                        <td class="px-4 py-3">{{ c.client?.company_name }}</td>
                        <td class="px-4 py-3 capitalize">{{ label(c.campaign_type) }}</td>
                        <td class="px-4 py-3">{{ c.manager?.name || '—' }}</td>
                        <td class="px-4 py-3 text-xs">{{ c.start_date || '—' }} → {{ c.end_date || '—' }}</td>
                        <td class="px-4 py-3"><StatusBadge :status="c.status" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="No campaigns yet." description="Create a campaign to organize deliverables and tasks.">
            <template #action>
                <Link v-if="can.create" :href="route('campaigns.create')" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white">Create Campaign</Link>
            </template>
        </EmptyState>
    </AuthenticatedLayout>
</template>
