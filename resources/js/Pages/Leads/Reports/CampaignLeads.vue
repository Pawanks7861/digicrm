<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import LeadsNav from '@/Components/Leads/LeadsNav.vue';
import CampaignLeadReport from '@/Components/Leads/CampaignLeadReport.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({ filters: Object, campaigns: Array, report: Object });
const f = reactive({ ...props.filters });
const apply = () => {
    const params = Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null));
    router.get(route('leads.reports.campaigns'), params, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Campaign Lead Report" />
    <AuthenticatedLayout>
        <div class="mb-4">
            <h1 class="text-2xl font-semibold tracking-tight">Campaign lead report</h1>
            <p class="mt-1 text-sm text-slate-500">CRM leads compared with Phase 11 spend. Provider-reported leads are shown alongside, never merged with CRM counts.</p>
        </div>
        <LeadsNav />
        <form class="mb-5 grid gap-2 sm:grid-cols-5" @submit.prevent="apply">
            <select v-model="f.campaign_id" class="rounded-lg border-slate-200 text-sm sm:col-span-2">
                <option :value="null">Select a campaign…</option>
                <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}<template v-if="c.client"> — {{ c.client }}</template></option>
            </select>
            <input v-model="f.from" type="date" class="rounded-lg border-slate-200 text-sm" />
            <input v-model="f.to" type="date" class="rounded-lg border-slate-200 text-sm" />
            <div class="flex gap-2">
                <select v-model="f.lead_category" class="flex-1 rounded-lg border-slate-200 text-sm">
                    <option value="client_campaign">Client campaign leads</option>
                    <option value="agency_sales">Agency sales leads</option>
                </select>
                <button type="submit" class="rounded-lg bg-slate-800 px-3 py-2 text-sm text-white">Run</button>
            </div>
        </form>
        <CampaignLeadReport v-if="report" :report="report" />
        <p v-else class="text-sm text-slate-500">Choose a campaign to see its lead report.</p>
    </AuthenticatedLayout>
</template>
