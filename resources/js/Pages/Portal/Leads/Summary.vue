<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ from: String, to: String, report: Object, flags: Object });
const from = ref(props.from);
const to = ref(props.to);
const apply = () => router.get(route('portal.leads.summary'), { from: from.value, to: to.value }, { preserveState: true });
</script>

<template>
    <Head title="Lead Summary" />
    <PortalLayout>
        <h1 class="text-xl font-semibold">Lead summary</h1>
        <p class="mt-1 text-sm text-slate-500">Leads recorded in our CRM for your campaigns. Ad platform lead counts can differ and are shown separately.</p>
        <div class="mt-4 flex gap-2 text-sm">
            <input v-model="from" type="date" class="rounded-md border-slate-300" />
            <input v-model="to" type="date" class="rounded-md border-slate-300" />
            <button class="rounded-lg bg-teal-700 px-3 py-1.5 text-white" @click="apply">Apply</button>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border bg-white p-4"><div class="text-xs uppercase text-slate-500">Leads</div><div class="mt-2 text-2xl font-semibold">{{ report.summary.total }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs uppercase text-slate-500">Qualified</div><div class="mt-2 text-2xl font-semibold">{{ report.summary.qualified }}</div><div class="text-xs text-slate-400">{{ report.summary.lead_to_qualified_rate.display }}</div></div>
            <div class="rounded-xl border bg-white p-4"><div class="text-xs uppercase text-slate-500">Avg. first response</div><div class="mt-2 text-2xl font-semibold">{{ report.summary.avg_first_response_display }}</div></div>
        </div>
        <div class="mt-4 overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Campaign</th><th class="px-4 py-2">CRM leads</th><th class="px-4 py-2">Qualified</th><th class="px-4 py-2">Platform leads</th>
                        <template v-if="flags.show_ad_spend"><th class="px-4 py-2">Spend</th><th class="px-4 py-2">Cost / lead</th><th class="px-4 py-2">Cost / qualified</th></template>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="c in report.campaigns" :key="c.campaign_id">
                        <td class="px-4 py-2">{{ c.campaign }}</td><td class="px-4 py-2">{{ c.crm_leads }}</td><td class="px-4 py-2">{{ c.qualified }}</td><td class="px-4 py-2">{{ c.provider_leads }}</td>
                        <template v-if="flags.show_ad_spend"><td class="px-4 py-2">{{ c.currency ? `${c.currency} ` : '' }}{{ c.spend }}</td><td class="px-4 py-2">{{ c.cpl }}</td><td class="px-4 py-2">{{ c.cost_per_qualified }}</td></template>
                    </tr>
                    <tr v-if="!report.campaigns.length"><td colspan="7" class="px-4 py-3 text-slate-500">No campaign leads in this period.</td></tr>
                </tbody>
            </table>
        </div>
        <p v-if="report.unattributed_to_campaign" class="mt-2 text-xs text-slate-500">{{ report.unattributed_to_campaign }} lead(s) are not linked to a campaign.</p>
        <div class="mt-4 rounded-xl border bg-white p-4">
            <h2 class="mb-2 text-sm font-semibold">By status</h2>
            <div class="flex flex-wrap gap-3 text-sm">
                <span v-for="(count, status) in report.summary.by_status" :key="status" class="flex items-center gap-1"><StatusBadge :status="status" /> {{ count }}</span>
            </div>
        </div>
    </PortalLayout>
</template>
