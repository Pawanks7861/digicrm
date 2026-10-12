<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    client: Object,
    from: String,
    to: String,
    dashboard: Object,
    can: { type: Object, default: () => ({}) },
    leadSummary: { type: Object, default: null },
});

const from = ref(props.from);
const to = ref(props.to);

function applyRange() {
    router.get(route('clients.performance', props.client.id), { from: from.value, to: to.value }, { preserveState: true });
}
</script>

<template>
    <Head :title="`Performance — ${client.company_name}`" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ client.company_name }}</h1>
                <p class="mt-1 text-sm text-slate-500">Marketing performance</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="{
                        'bg-emerald-50 text-emerald-800': dashboard.freshness?.status === 'fresh',
                        'bg-amber-50 text-amber-800': dashboard.freshness?.status === 'delayed',
                        'bg-rose-50 text-rose-800': ['stale','sync_failed','not_connected'].includes(dashboard.freshness?.status),
                        'bg-slate-100 text-slate-600': !dashboard.freshness?.status || dashboard.freshness?.status === 'no_data',
                    }"
                >{{ dashboard.freshness?.label || 'No Data' }}</span>
                <span v-if="dashboard.coverage?.warning" class="rounded-full bg-amber-50 px-2.5 py-1 text-xs text-amber-900">{{ dashboard.coverage.warning }}</span>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap items-end gap-2">
            <label class="text-xs text-slate-500">From<input v-model="from" type="date" class="ml-1 rounded-md border-slate-300 text-sm" /></label>
            <label class="text-xs text-slate-500">To<input v-model="to" type="date" class="ml-1 rounded-md border-slate-300 text-sm" /></label>
            <button type="button" class="rounded-lg bg-teal-700 px-3 py-1.5 text-sm text-white" @click="applyRange">Apply</button>
            <Link v-if="can.export" :href="route('clients.metrics.export', { client: client.id, from, to })" class="text-sm text-teal-700 hover:underline">Export CSV</Link>
        </div>

        <div v-if="dashboard.coverage?.status === 'not_connected' || dashboard.coverage?.status === 'missing_provider'" class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500">
            Not Connected — link Meta, Google Ads, GA4, or YouTube under Data Sources.
        </div>

        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="m in dashboard.metrics" :key="m.code" class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ m.label }}</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ m.display }}</div>
                <div v-if="m.formula_label" class="mt-1 text-xs text-slate-400">{{ m.formula_label }}</div>
                <div v-if="m.currency" class="mt-1 text-xs text-slate-400">{{ m.currency }}</div>
            </div>
        </div>

        <section v-if="leadSummary" class="mt-8">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700">CRM campaign leads</h2>
                <Link :href="route('leads.index', { lead_category: 'client_campaign', client_id: client.id })" class="text-xs text-teal-700 hover:underline">Open leads</Link>
            </div>
            <div class="mb-3 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs uppercase text-slate-500">CRM leads</div><div class="mt-2 text-2xl font-semibold">{{ leadSummary.summary.total }}</div></div>
                <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs uppercase text-slate-500">Lead → qualified</div><div class="mt-2 text-2xl font-semibold">{{ leadSummary.summary.lead_to_qualified_rate.display }}</div></div>
                <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs uppercase text-slate-500">Avg. first response</div><div class="mt-2 text-2xl font-semibold">{{ leadSummary.summary.avg_first_response_display }}</div></div>
            </div>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr><th class="px-4 py-2">Campaign</th><th class="px-4 py-2">CRM leads</th><th class="px-4 py-2">Qualified</th><th class="px-4 py-2">Platform leads</th><th class="px-4 py-2">Spend</th><th class="px-4 py-2">CRM CPL</th><th class="px-4 py-2">Cost / qualified</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="c in leadSummary.campaigns" :key="c.campaign_id">
                            <td class="px-4 py-2"><Link :href="route('campaigns.performance', c.campaign_id)" class="text-teal-700 hover:underline">{{ c.campaign }}</Link></td>
                            <td class="px-4 py-2">{{ c.crm_leads }}</td><td class="px-4 py-2">{{ c.qualified }}</td><td class="px-4 py-2">{{ c.provider_leads }}</td>
                            <td class="px-4 py-2">{{ c.currency ? `${c.currency} ` : '' }}{{ c.spend }}</td><td class="px-4 py-2">{{ c.cpl }}</td><td class="px-4 py-2">{{ c.cost_per_qualified }}</td>
                        </tr>
                        <tr v-if="!leadSummary.campaigns.length"><td colspan="7" class="px-4 py-3 text-slate-500">No campaign leads in this period.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
