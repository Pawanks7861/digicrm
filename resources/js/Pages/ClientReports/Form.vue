<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ clients: Array, report: Object });

const form = useForm({
    client_id: '',
    period_start: new Date(new Date().getFullYear(), new Date().getMonth() - 1, 1).toISOString().slice(0, 10),
    period_end: new Date(new Date().getFullYear(), new Date().getMonth(), 0).toISOString().slice(0, 10),
    title: '',
    include_ad_spend: true,
    include_conversion_value: true,
});

function submit() {
    form.post(route('client-reports.store'));
}
</script>

<template>
    <Head title="Generate Report" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('client-reports.index')" class="text-sm text-teal-700 hover:underline">← Reports</Link>
            <h1 class="mt-2 text-2xl font-semibold">Generate performance report</h1>
            <p class="text-sm text-slate-500">Creates a draft from live aggregates, then freezes metrics. Never auto-publishes.</p>
        </div>
        <form class="max-w-xl space-y-4 rounded-xl border bg-white p-6" @submit.prevent="submit">
            <select v-model="form.client_id" class="w-full rounded-md border-slate-300" required>
                <option value="">Select client</option>
                <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
            </select>
            <div class="grid grid-cols-2 gap-3">
                <input v-model="form.period_start" type="date" class="rounded-md border-slate-300" required />
                <input v-model="form.period_end" type="date" class="rounded-md border-slate-300" required />
            </div>
            <input v-model="form.title" class="w-full rounded-md border-slate-300" placeholder="Title (optional)" />
            <label class="flex items-center gap-2 text-sm"><input v-model="form.include_ad_spend" type="checkbox" /> Include ad spend</label>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.include_conversion_value" type="checkbox" /> Include conversion value</label>
            <button class="rounded-lg bg-teal-700 px-4 py-2 text-white">Generate draft</button>
        </form>
    </AuthenticatedLayout>
</template>
