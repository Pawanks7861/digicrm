<script setup>
defineProps({ report: { type: Object, required: true } });
const money = (m, currency) => (m?.value === null || m?.value === undefined ? m?.display : `${currency || m.currency || ''} ${m.display}`);
</script>

<template>
    <div class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase text-slate-400">Spend</div>
                <div class="text-xl font-semibold">{{ report.spend.value === null ? report.spend.display : `${report.spend.currency || ''} ${report.spend.display}` }}</div>
                <div class="text-xs text-slate-400">{{ report.spend.source }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase text-slate-400">CRM leads</div>
                <div class="text-xl font-semibold">{{ report.crm_leads }}</div>
                <div class="text-xs text-slate-400">{{ report.qualified }} qualified<span v-if="report.converted !== null"> · {{ report.converted }} converted</span></div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase text-slate-400">Provider-reported leads</div>
                <div class="text-xl font-semibold">{{ report.provider_leads.display }}</div>
                <div class="text-xs text-slate-400">{{ report.provider_leads.source }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase text-slate-400">CRM cost per lead</div>
                <div class="text-xl font-semibold">{{ money(report.cpl) }}</div>
                <div class="text-xs text-slate-400">Spend ÷ {{ report.cpl.denominator }} CRM leads</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase text-slate-400">Cost per qualified lead</div>
                <div class="text-xl font-semibold">{{ money(report.cost_per_qualified) }}</div>
                <div class="text-xs text-slate-400">Spend ÷ {{ report.cost_per_qualified.denominator }} qualified</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase text-slate-400">Cost per conversion</div>
                <div class="text-xl font-semibold">{{ money(report.cost_per_conversion) }}</div>
                <div class="text-xs text-slate-400">{{ report.cost_per_conversion.note || `Spend ÷ ${report.cost_per_conversion.denominator} converted` }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase text-slate-400">Lead → qualified</div>
                <div class="text-xl font-semibold">{{ report.summary.lead_to_qualified_rate.display }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase text-slate-400">Avg. first response</div>
                <div class="text-xl font-semibold">{{ report.summary.avg_first_response_display }}</div>
            </div>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-4">
                <h3 class="mb-2 text-sm font-semibold text-slate-700">Leads by source</h3>
                <ul class="text-sm">
                    <li v-for="s in report.by_source" :key="s.source" class="flex justify-between py-1"><span>{{ s.source }}</span><span>{{ s.total }}</span></li>
                    <li v-if="!report.by_source.length" class="text-slate-500">No CRM leads in this period.</li>
                </ul>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-4">
                <h3 class="mb-2 text-sm font-semibold text-slate-700">How these are calculated</h3>
                <dl class="space-y-1 text-xs text-slate-600">
                    <div v-for="(text, key) in report.definitions" :key="key"><dt class="inline font-medium">{{ key.replaceAll('_', ' ') }}:</dt> <dd class="inline">{{ text }}</dd></div>
                </dl>
            </section>
        </div>
    </div>
</template>
