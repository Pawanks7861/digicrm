<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    campaign: Object,
    kpis: Array,
    can: { type: Object, default: () => ({}) },
});

const form = useForm({
    metric_code: 'leads',
    target_value: '',
    target_type: 'min',
    period: 'month',
});

function submit() {
    form.post(route('campaigns.kpis.store', props.campaign.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Campaign KPIs" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('campaigns.performance', campaign.id)" class="text-sm text-teal-700 hover:underline">← Performance</Link>
            <h1 class="mt-2 text-2xl font-semibold">KPIs — {{ campaign.name }}</h1>
        </div>
        <form v-if="can.manage" class="mb-6 flex flex-wrap gap-2 rounded-xl border bg-white p-4" @submit.prevent="submit">
            <input v-model="form.metric_code" class="rounded-md border-slate-300 text-sm" placeholder="metric_code" />
            <input v-model="form.target_value" type="number" step="any" class="rounded-md border-slate-300 text-sm" placeholder="Target" />
            <select v-model="form.target_type" class="rounded-md border-slate-300 text-sm">
                <option value="min">Min</option>
                <option value="max">Max</option>
                <option value="reference">Reference</option>
            </select>
            <button class="rounded-lg bg-teal-700 px-3 py-1.5 text-sm text-white">Add KPI</button>
        </form>
        <div class="space-y-3">
            <div v-for="k in kpis" :key="k.id" class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex justify-between gap-3">
                    <div>
                        <div class="font-medium">{{ k.metric_code }}</div>
                        <div class="text-sm text-slate-500">Target {{ k.target_value }} ({{ k.target_type }})</div>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-semibold">{{ k.progress?.progress_pct != null ? k.progress.progress_pct + '%' : 'N/A' }}</div>
                        <div class="text-xs" :class="k.progress?.status === 'on_track' ? 'text-emerald-700' : 'text-amber-700'">{{ k.progress?.label }}</div>
                    </div>
                </div>
            </div>
            <p v-if="!kpis?.length" class="text-slate-500">No Data</p>
        </div>
    </AuthenticatedLayout>
</template>
