<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import LeadsNav from '@/Components/Leads/LeadsNav.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

const props = defineProps({ opportunities: Object, filters: Object, stages: Array, users: Array });
const f = reactive({
    search: props.filters.search || '',
    status: props.filters.status || '',
    sales_pipeline_stage_id: props.filters.sales_pipeline_stage_id || '',
    owner_id: props.filters.owner_id || '',
});
watch(f, () => {
    const params = Object.fromEntries(Object.entries(f).filter(([, v]) => v !== ''));
    router.get(route('opportunities.index'), params, { preserveState: true, replace: true });
});
</script>

<template>
    <Head title="Opportunities" />
    <AuthenticatedLayout>
        <div class="mb-4">
            <h1 class="text-2xl font-semibold tracking-tight">Opportunities</h1>
            <p class="mt-1 text-sm text-slate-500">Deals created from agency sales leads.</p>
        </div>
        <LeadsNav />
        <div class="mb-4 grid gap-2 sm:grid-cols-4">
            <input v-model="f.search" type="search" placeholder="Search…" class="rounded-lg border-slate-200 text-sm" />
            <select v-model="f.status" class="rounded-lg border-slate-200 text-sm">
                <option value="">All statuses</option>
                <option value="open">Open</option><option value="won">Won</option><option value="lost">Lost</option>
            </select>
            <select v-model="f.sales_pipeline_stage_id" class="rounded-lg border-slate-200 text-sm">
                <option value="">All stages</option>
                <option v-for="s in stages" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <select v-model="f.owner_id" class="rounded-lg border-slate-200 text-sm">
                <option value="">All owners</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
        </div>
        <div v-if="opportunities.data.length" class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr><th class="px-4 py-3">Opportunity</th><th class="px-4 py-3">Lead</th><th class="px-4 py-3">Stage</th><th class="px-4 py-3">Value</th><th class="px-4 py-3">Owner</th><th class="px-4 py-3">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="o in opportunities.data" :key="o.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3"><Link :href="route('opportunities.show', o.id)" class="font-medium text-teal-800 hover:underline">{{ o.title }}</Link><div class="text-xs text-slate-400">{{ o.opportunity_number }}</div></td>
                        <td class="px-4 py-3"><Link v-if="o.lead" :href="route('leads.show', o.lead.id)" class="hover:underline">{{ o.lead.company || o.lead.name }}</Link></td>
                        <td class="px-4 py-3">{{ o.stage?.name }}</td>
                        <td class="px-4 py-3">{{ o.currency }} {{ Number(o.estimated_value).toLocaleString() }}</td>
                        <td class="px-4 py-3">{{ o.owner?.name || '—' }}</td>
                        <td class="px-4 py-3"><StatusBadge :status="o.status" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="No opportunities yet." />
        <div v-if="opportunities.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
            <Link v-for="(link, i) in opportunities.links" :key="i" :href="link.url || '#'" v-html="link.label" class="rounded-md px-3 py-1 text-sm" :class="link.active ? 'bg-teal-700 text-white' : 'bg-white ring-1 ring-slate-200'" />
        </div>
    </AuthenticatedLayout>
</template>
