<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    metrics: Object,
    clients: Array,
    registry: Array,
    can: { type: Object, default: () => ({}) },
});

const form = useForm({
    client_id: '',
    metric_key: 'leads',
    metric_value: '',
    metric_date: new Date().toISOString().slice(0, 10),
    currency: 'INR',
});

function submit() {
    form.post(route('performance.manual.store'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Manual Metrics" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('performance.index')" class="text-sm text-teal-700 hover:underline">← Performance</Link>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Manual Entry</h1>
            <p class="mt-1 text-sm text-slate-500">Manual rows coexist with API sync and are never overwritten.</p>
        </div>

        <form v-if="can.manage" class="mb-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="submit">
            <select v-model="form.client_id" class="rounded-md border-slate-300 text-sm" required>
                <option value="">Client</option>
                <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
            </select>
            <select v-model="form.metric_key" class="rounded-md border-slate-300 text-sm">
                <option v-for="m in registry" :key="m.code" :value="m.code">{{ m.label }}</option>
            </select>
            <input v-model="form.metric_value" type="number" step="any" class="rounded-md border-slate-300 text-sm" placeholder="Value" required />
            <input v-model="form.metric_date" type="date" class="rounded-md border-slate-300 text-sm" required />
            <input v-model="form.currency" class="rounded-md border-slate-300 text-sm" placeholder="Currency" maxlength="3" />
            <button type="submit" class="rounded-lg bg-teal-700 px-3 py-2 text-sm text-white">Save</button>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Client</th><th class="px-4 py-3">Metric</th><th class="px-4 py-3">Value</th></tr>
                </thead>
                <tbody>
                    <tr v-for="m in metrics.data" :key="m.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ m.metric_date }}</td>
                        <td class="px-4 py-3">{{ m.client?.company_name }}</td>
                        <td class="px-4 py-3">{{ m.metric_key }} <span class="text-xs text-slate-400">(manual)</span></td>
                        <td class="px-4 py-3">{{ m.metric_value }}</td>
                    </tr>
                    <tr v-if="!metrics.data?.length"><td colspan="4" class="px-4 py-8 text-center text-slate-500">No Data</td></tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
