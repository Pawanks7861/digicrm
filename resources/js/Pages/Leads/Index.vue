<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import LeadsNav from '@/Components/Leads/LeadsNav.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

const props = defineProps({ leads: Object, filters: Object, counts: Object, options: Object, can: Object });

const f = reactive({
    search: props.filters.search || '',
    lead_category: props.filters.lead_category || '',
    status: props.filters.status || '',
    lead_source_id: props.filters.lead_source_id || '',
    assigned_to: props.filters.assigned_to || '',
    client_id: props.filters.client_id || '',
    priority: props.filters.priority || '',
    from: props.filters.from || '',
    to: props.filters.to || '',
    mine: props.filters.mine || '',
    uncontacted: props.filters.uncontacted || '',
    duplicates: props.filters.duplicates || '',
});

let timer = null;
watch(f, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        const params = Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null));
        router.get(route('leads.index'), params, { preserveState: true, replace: true });
    }, 250);
});

const quick = (key) => {
    const on = !f[key];
    f.mine = '';
    f.uncontacted = '';
    f.duplicates = '';
    if (key === 'unassigned') {
        f.assigned_to = f.assigned_to === 'none' ? '' : 'none';
        return;
    }
    f[key] = on ? '1' : '';
};
const label = (v) => (v || '').replaceAll('_', ' ');
const fmt = (d) => (d ? new Date(d.replace(' ', 'T')).toLocaleString() : '—');
</script>

<template>
    <Head title="Leads" />
    <AuthenticatedLayout>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Lead Inbox</h1>
                <p class="mt-1 text-sm text-slate-500">Agency sales leads and client campaign leads you are allowed to see.</p>
            </div>
            <div class="flex gap-2">
                <Link v-if="can.import" :href="route('leads.import')" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Import CSV</Link>
                <Link v-if="can.create" :href="route('leads.create')" class="rounded-lg bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800">New lead</Link>
            </div>
        </div>
        <LeadsNav />

        <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-5">
            <button type="button" class="rounded-xl border bg-white p-3 text-left" :class="!f.mine && !f.uncontacted && !f.duplicates && f.assigned_to !== 'none' ? 'border-teal-400' : 'border-slate-200'" @click="f.mine = ''; f.uncontacted = ''; f.duplicates = ''; f.assigned_to = ''">
                <div class="text-xs uppercase text-slate-400">Open</div><div class="text-xl font-semibold">{{ counts.open }}</div>
            </button>
            <button type="button" class="rounded-xl border bg-white p-3 text-left" :class="f.mine ? 'border-teal-400' : 'border-slate-200'" @click="quick('mine')">
                <div class="text-xs uppercase text-slate-400">Mine</div><div class="text-xl font-semibold">{{ counts.mine }}</div>
            </button>
            <button type="button" class="rounded-xl border bg-white p-3 text-left" :class="f.assigned_to === 'none' ? 'border-teal-400' : 'border-slate-200'" @click="quick('unassigned')">
                <div class="text-xs uppercase text-slate-400">Unassigned</div><div class="text-xl font-semibold text-amber-700">{{ counts.unassigned }}</div>
            </button>
            <button type="button" class="rounded-xl border bg-white p-3 text-left" :class="f.uncontacted ? 'border-teal-400' : 'border-slate-200'" @click="quick('uncontacted')">
                <div class="text-xs uppercase text-slate-400">Uncontacted</div><div class="text-xl font-semibold text-rose-700">{{ counts.uncontacted }}</div>
            </button>
            <button type="button" class="rounded-xl border bg-white p-3 text-left" :class="f.duplicates ? 'border-teal-400' : 'border-slate-200'" @click="quick('duplicates')">
                <div class="text-xs uppercase text-slate-400">Possible duplicates</div><div class="text-xl font-semibold">{{ counts.duplicates }}</div>
            </button>
        </div>

        <div class="mb-4 grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
            <input v-model="f.search" type="search" placeholder="Search name, company, email, mobile…" class="rounded-lg border-slate-200 text-sm sm:col-span-3 lg:col-span-2" />
            <select v-model="f.lead_category" class="rounded-lg border-slate-200 text-sm">
                <option value="">All categories</option>
                <option v-for="c in options.categories" :key="c" :value="c">{{ label(c) }}</option>
            </select>
            <select v-model="f.status" class="rounded-lg border-slate-200 text-sm">
                <option value="">All statuses</option>
                <option v-for="s in options.statuses" :key="s" :value="s">{{ label(s) }}</option>
            </select>
            <select v-model="f.lead_source_id" class="rounded-lg border-slate-200 text-sm">
                <option value="">All sources</option>
                <option v-for="s in options.sources" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <select v-model="f.assigned_to" class="rounded-lg border-slate-200 text-sm">
                <option value="">Any assignee</option>
                <option value="none">Unassigned</option>
                <option v-for="u in options.users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
            <select v-model="f.client_id" class="rounded-lg border-slate-200 text-sm">
                <option value="">Any client</option>
                <option v-for="c in options.clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
            </select>
            <select v-model="f.priority" class="rounded-lg border-slate-200 text-sm">
                <option value="">Any priority</option>
                <option v-for="p in options.priorities" :key="p" :value="p">{{ label(p) }}</option>
            </select>
            <input v-model="f.from" type="date" class="rounded-lg border-slate-200 text-sm" aria-label="From" />
            <input v-model="f.to" type="date" class="rounded-lg border-slate-200 text-sm" aria-label="To" />
        </div>

        <div v-if="leads.data.length">
            <div class="hidden overflow-x-auto rounded-xl border border-slate-200 bg-white md:block">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Lead</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Source / Campaign</th>
                            <th class="px-4 py-3">Assignee</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="l in leads.data" :key="l.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <Link :href="route('leads.show', l.id)" class="font-medium text-teal-800 hover:underline">{{ l.name }}</Link>
                                <span v-if="l.company" class="text-slate-500"> · {{ l.company }}</span>
                                <div class="text-xs text-slate-400">{{ l.lead_number }} <span v-if="l.email">· {{ l.email }}</span> <span v-if="l.mobile">· {{ l.mobile }}</span></div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="capitalize">{{ label(l.lead_category) }}</span>
                                <div v-if="l.client" class="text-xs text-slate-500">{{ l.client }}</div>
                            </td>
                            <td class="px-4 py-3">{{ l.source || '—' }}<div v-if="l.campaign" class="text-xs text-slate-500">{{ l.campaign }}</div></td>
                            <td class="px-4 py-3">{{ l.assignee || 'Unassigned' }}</td>
                            <td class="px-4 py-3 space-x-1">
                                <StatusBadge :status="l.status" />
                                <StatusBadge v-if="l.priority !== 'normal'" :status="l.priority" />
                                <StatusBadge v-if="l.duplicate_status === 'possible'" status="possible" />
                                <div v-if="!l.first_contacted_at && !['won','lost'].includes(l.status)" class="mt-1 text-xs text-rose-600">Not contacted</div>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ fmt(l.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ul class="space-y-2 md:hidden">
                <li v-for="l in leads.data" :key="l.id" class="rounded-xl border border-slate-200 bg-white p-3">
                    <div class="flex items-start justify-between gap-2">
                        <Link :href="route('leads.show', l.id)" class="font-medium text-teal-800">{{ l.name }}</Link>
                        <StatusBadge :status="l.status" />
                    </div>
                    <div class="mt-1 text-xs text-slate-500">{{ l.lead_number }} · {{ label(l.lead_category) }}<span v-if="l.client"> · {{ l.client }}</span></div>
                    <div class="mt-1 text-xs text-slate-500">{{ l.source || 'No source' }} · {{ l.assignee || 'Unassigned' }}</div>
                </li>
            </ul>
            <div v-if="leads.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
                <Link v-for="(link, i) in leads.links" :key="i" :href="link.url || '#'" v-html="link.label" class="rounded-md px-3 py-1 text-sm" :class="link.active ? 'bg-teal-700 text-white' : link.url ? 'bg-white text-slate-600 ring-1 ring-slate-200' : 'text-slate-300'" preserve-scroll />
            </div>
        </div>
        <EmptyState v-else title="No leads match these filters." />
    </AuthenticatedLayout>
</template>
