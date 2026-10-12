<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ leads: { type: Object, default: null }, role: { type: String, default: 'team' } });
const totals = (rows) => (rows?.length ? rows.map((t) => `${t.currency} ${Number(t.total).toLocaleString()}`).join(' · ') : '—');
</script>

<template>
    <section v-if="leads" class="mt-8">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Leads & sales</h2>
            <Link :href="route('leads.index')" class="text-xs text-teal-800 hover:underline">Open lead inbox</Link>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <template v-if="role === 'team'">
                <Link :href="route('leads.index', { mine: 1 })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                    <div class="text-xs font-semibold uppercase text-slate-400">My open leads</div>
                    <div class="mt-2 text-3xl font-semibold">{{ leads.my_open }}</div>
                </Link>
                <Link :href="route('leads.index', { mine: 1, uncontacted: 1 })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-rose-300">
                    <div class="text-xs font-semibold uppercase text-slate-400">My uncontacted leads</div>
                    <div class="mt-2 text-3xl font-semibold text-rose-700">{{ leads.my_uncontacted }}</div>
                </Link>
                <Link :href="route('follow-ups.index')" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                    <div class="text-xs font-semibold uppercase text-slate-400">Lead follow-ups due</div>
                    <div class="mt-2 text-3xl font-semibold">{{ leads.lead_followups_today }}</div>
                </Link>
            </template>
            <template v-else>
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-semibold uppercase text-slate-400">New leads (period)</div>
                    <div class="mt-2 text-3xl font-semibold">{{ leads.new_agency + leads.new_campaign }}</div>
                    <div class="text-xs text-slate-500">{{ leads.new_agency }} agency · {{ leads.new_campaign }} campaign</div>
                </div>
                <Link :href="route('leads.index', { uncontacted: 1 })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-rose-300">
                    <div class="text-xs font-semibold uppercase text-slate-400">Uncontacted open leads</div>
                    <div class="mt-2 text-3xl font-semibold text-rose-700">{{ leads.uncontacted }}</div>
                    <div v-if="leads.unassigned !== undefined" class="text-xs text-slate-500">{{ leads.unassigned }} unassigned</div>
                </Link>
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-semibold uppercase text-slate-400">Lead → qualified</div>
                    <div class="mt-2 text-3xl font-semibold">{{ leads.qualified_rate }}</div>
                    <div class="text-xs text-slate-500">Avg. first response {{ leads.avg_first_response }}</div>
                </div>
                <Link v-if="leads.pipeline" :href="route('pipeline.index')" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                    <div class="text-xs font-semibold uppercase text-slate-400">Open pipeline</div>
                    <div class="mt-2 text-lg font-semibold">{{ totals(leads.pipeline) }}</div>
                    <div class="text-xs text-slate-500">Won in period: {{ totals(leads.won_period) }}</div>
                </Link>
                <div v-if="role === 'account_manager'" class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-semibold uppercase text-slate-400">My clients' campaign leads</div>
                    <div class="mt-2 text-3xl font-semibold">{{ leads.my_client_campaign_leads }}</div>
                </div>
                <Link v-if="role === 'account_manager'" :href="route('leads.index', { mine: 1 })" class="rounded-xl border border-slate-200 bg-white p-5 hover:border-teal-300">
                    <div class="text-xs font-semibold uppercase text-slate-400">My open leads</div>
                    <div class="mt-2 text-3xl font-semibold">{{ leads.my_open }}</div>
                    <div class="text-xs text-slate-500">{{ leads.lead_followups_today }} lead follow-ups due</div>
                </Link>
            </template>
        </div>
    </section>
</template>
