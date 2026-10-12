<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CampaignLeadReport from '@/Components/Leads/CampaignLeadReport.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    campaign: Object,
    client: Object,
    from: String,
    to: String,
    dashboard: Object,
    leadReport: { type: Object, default: null },
});
const from = ref(props.from);
const to = ref(props.to);
function apply() {
    router.get(route('campaigns.performance', props.campaign.id), { from: from.value, to: to.value }, { preserveState: true });
}
</script>

<template>
    <Head :title="`Campaign performance — ${campaign.name}`" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('campaigns.show', campaign.id)" class="text-sm text-teal-700 hover:underline">← Campaign</Link>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ campaign.name }}</h1>
            <p class="text-sm text-slate-500">{{ client.company_name }} · {{ campaign.code }}</p>
            <span class="mt-2 inline-block rounded-full bg-slate-100 px-2.5 py-1 text-xs">{{ dashboard.freshness?.label }}</span>
        </div>
        <div class="mb-4 flex gap-2 text-sm">
            <input v-model="from" type="date" class="rounded-md border-slate-300" />
            <input v-model="to" type="date" class="rounded-md border-slate-300" />
            <button class="rounded-lg bg-teal-700 px-3 py-1.5 text-white" @click="apply">Apply</button>
            <Link :href="route('campaigns.kpis', campaign.id)" class="rounded-lg border px-3 py-1.5">KPIs</Link>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="m in dashboard.metrics" :key="m.code" class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase text-slate-500">{{ m.label }}</div>
                <div class="mt-2 text-2xl font-semibold">{{ m.display }}</div>
            </div>
        </div>
        <p v-if="!Object.keys(dashboard.metrics || {}).length" class="text-slate-500">No Data</p>
        <section v-if="leadReport" class="mt-8">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">CRM leads for this campaign</h2>
            <CampaignLeadReport :report="leadReport" />
        </section>
    </AuthenticatedLayout>
</template>
