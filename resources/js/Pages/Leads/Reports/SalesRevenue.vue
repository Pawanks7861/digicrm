<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import LeadsNav from '@/Components/Leads/LeadsNav.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({ filters: Object, revenue: Object });
const f = reactive({ from: props.filters.from || '', to: props.filters.to || '' });
const apply = () => router.get(route('leads.reports.sales-revenue'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });
const measures = computed(() => Object.entries(props.revenue).filter(([k]) => k !== 'note'));
</script>

<template>
    <Head title="Sales Revenue" />
    <AuthenticatedLayout>
        <div class="mb-4">
            <h1 class="text-2xl font-semibold tracking-tight">Sales revenue from leads</h1>
            <p class="mt-1 text-sm text-slate-500">{{ revenue.note }}</p>
        </div>
        <LeadsNav />
        <form class="mb-5 flex flex-wrap gap-2" @submit.prevent="apply">
            <input v-model="f.from" type="date" class="rounded-lg border-slate-200 text-sm" />
            <input v-model="f.to" type="date" class="rounded-lg border-slate-200 text-sm" />
            <button type="submit" class="rounded-lg bg-slate-800 px-3 py-2 text-sm text-white">Apply</button>
        </form>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <section v-for="[key, m] in measures" :key="key" class="rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="text-xs font-semibold uppercase text-slate-400">{{ m.label }}</h2>
                <ul class="mt-2 space-y-1">
                    <li v-for="r in m.rows" :key="r.currency" class="flex justify-between text-sm"><span class="font-semibold">{{ r.currency }} {{ Number(r.total).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</span><span class="text-xs text-slate-400">{{ r.count }} records</span></li>
                    <li v-if="!m.rows.length" class="text-sm text-slate-400">None</li>
                </ul>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
