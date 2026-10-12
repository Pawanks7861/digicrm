<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

const props = defineProps({ leads: Object, filters: Object, statuses: Array, campaigns: Array, flags: Object });
const f = reactive({ search: props.filters.search || '', status: props.filters.status || '', campaign_id: props.filters.campaign_id || '' });
let timer = null;
watch(f, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('portal.leads'), Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '')), { preserveState: true, replace: true }), 250);
});
const label = (v) => (v || '').replaceAll('_', ' ');
const fmt = (d) => (d ? new Date(d.replace(' ', 'T')).toLocaleDateString() : '—');
</script>

<template>
    <Head title="Leads" />
    <PortalLayout>
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <h1 class="text-xl font-semibold">Leads</h1>
                <p class="mt-1 text-sm text-slate-500">Leads captured from your campaigns.</p>
            </div>
            <Link v-if="flags.show_lead_reports" :href="route('portal.leads.summary')" class="text-sm text-teal-800 underline">View summary</Link>
        </div>
        <div class="mt-4 grid gap-2 sm:grid-cols-3">
            <input v-model="f.search" type="search" placeholder="Search…" class="rounded-md border-slate-300 text-sm" />
            <select v-model="f.status" class="rounded-md border-slate-300 text-sm">
                <option value="">All statuses</option>
                <option v-for="s in statuses" :key="s" :value="s">{{ label(s) }}</option>
            </select>
            <select v-model="f.campaign_id" class="rounded-md border-slate-300 text-sm">
                <option value="">All campaigns</option>
                <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
        </div>
        <ul v-if="leads.data.length" class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
            <li v-for="l in leads.data" :key="l.id" class="flex flex-wrap items-start justify-between gap-2 px-4 py-3 text-sm">
                <div>
                    <Link :href="route('portal.leads.show', l.id)" class="font-medium text-teal-800 hover:underline">{{ l.name }}</Link>
                    <span v-if="l.company" class="text-slate-500"> · {{ l.company }}</span>
                    <div class="text-xs text-slate-500">
                        {{ l.lead_number }} · {{ fmt(l.created_at) }}<span v-if="l.campaign"> · {{ l.campaign }}</span><span v-if="flags.show_lead_source && l.source"> · {{ l.source }}</span>
                    </div>
                    <div v-if="flags.show_lead_contact_details" class="text-xs text-slate-500">{{ [l.email, l.mobile].filter(Boolean).join(' · ') }}</div>
                </div>
                <StatusBadge :status="l.status" />
            </li>
        </ul>
        <p v-else class="mt-6 text-slate-500">No leads yet.</p>
        <div v-if="leads.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
            <Link v-for="(link, i) in leads.links" :key="i" :href="link.url || '#'" v-html="link.label" class="rounded-md px-3 py-1 text-sm" :class="link.active ? 'bg-teal-700 text-white' : 'bg-white ring-1 ring-slate-200'" />
        </div>
    </PortalLayout>
</template>
