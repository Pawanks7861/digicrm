<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import LeadsNav from '@/Components/Leads/LeadsNav.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({ filters: Object, summary: Object, sources: Object, assignees: Array, options: Object, can: Object });
const f = reactive({ ...props.filters });
const apply = () => {
    const params = Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null && v !== undefined));
    router.get(route('leads.reports.analytics'), params, { preserveState: true, replace: true });
};
const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head title="Lead Analytics" />
    <AuthenticatedLayout>
        <div class="mb-4">
            <h1 class="text-2xl font-semibold tracking-tight">Lead analytics</h1>
            <p class="mt-1 text-sm text-slate-500">CRM leads created in the period, scoped to the leads you can see. Merged duplicates are excluded.</p>
        </div>
        <LeadsNav />
        <form class="mb-5 grid gap-2 sm:grid-cols-3 lg:grid-cols-7" @submit.prevent="apply">
            <input v-model="f.from" type="date" class="rounded-lg border-slate-200 text-sm" />
            <input v-model="f.to" type="date" class="rounded-lg border-slate-200 text-sm" />
            <select v-model="f.lead_category" class="rounded-lg border-slate-200 text-sm">
                <option :value="undefined">All categories</option>
                <option v-for="c in options.categories" :key="c" :value="c">{{ label(c) }}</option>
            </select>
            <select v-model="f.client_id" class="rounded-lg border-slate-200 text-sm">
                <option :value="undefined">All clients</option>
                <option v-for="c in options.clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
            </select>
            <select v-model="f.lead_source_id" class="rounded-lg border-slate-200 text-sm">
                <option :value="undefined">All sources</option>
                <option v-for="s in options.sources" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <select v-model="f.assigned_to" class="rounded-lg border-slate-200 text-sm">
                <option :value="undefined">All assignees</option>
                <option v-for="u in options.users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
            <button type="submit" class="rounded-lg bg-slate-800 px-3 py-2 text-sm text-white">Apply</button>
        </form>

        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase text-slate-400">Leads created</div><div class="text-2xl font-semibold">{{ summary.total }}</div>
                <div class="text-xs text-slate-500">{{ summary.by_category.agency_sales }} agency · {{ summary.by_category.client_campaign }} campaign</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase text-slate-400">Lead → qualified</div><div class="text-2xl font-semibold">{{ summary.lead_to_qualified_rate.display }}</div>
                <div class="text-xs text-slate-500">{{ summary.lead_to_qualified_rate.numerator }} / {{ summary.lead_to_qualified_rate.denominator }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase text-slate-400">Lead → client (agency)</div><div class="text-2xl font-semibold">{{ summary.lead_to_conversion_rate.display }}</div>
                <div class="text-xs text-slate-500">{{ summary.lead_to_conversion_rate.numerator }} / {{ summary.lead_to_conversion_rate.denominator }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase text-slate-400">Avg. first response</div><div class="text-2xl font-semibold">{{ summary.avg_first_response_display }}</div>
                <div class="text-xs text-slate-500">Human contacts only · {{ summary.contacted_count }} contacted · {{ summary.uncontacted }} open uncontacted</div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
                <h2 class="mb-1 text-sm font-semibold text-slate-700">By source</h2>
                <p class="mb-3 text-xs text-slate-400">Each lead counts once, under its first-touch source. Totals equal the lead count ({{ sources.total }}).</p>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase text-slate-400"><tr><th class="py-2">Source</th><th>Leads</th><th>Qualified</th><th>Qualified %</th><th>Converted</th><th>Conversion %</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="r in sources.rows" :key="r.source">
                                <td class="py-2">{{ r.source }}</td><td>{{ r.total }}</td><td>{{ r.qualified }}</td><td>{{ r.qualified_rate.display }}</td><td>{{ r.converted }}</td><td>{{ r.conversion_rate.display }}</td>
                            </tr>
                            <tr v-if="!sources.rows.length"><td colspan="6" class="py-3 text-slate-500">No leads in this period.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">By status</h2>
                <ul class="space-y-1 text-sm">
                    <li v-for="(count, status) in summary.by_status" :key="status" class="flex justify-between"><StatusBadge :status="status" /><span>{{ count }}</span></li>
                </ul>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">By assignee</h2>
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-400"><tr><th class="py-2">User</th><th>Leads</th><th>Uncontacted</th><th>Qualified %</th><th>Avg. response</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="a in assignees" :key="a.user_id"><td class="py-2">{{ a.name }}</td><td>{{ a.total }}</td><td>{{ a.uncontacted }}</td><td>{{ a.qualified_rate.display }}</td><td>{{ a.avg_first_response_display }}</td></tr>
                        <tr v-if="!assignees.length"><td colspan="5" class="py-3 text-slate-500">No assigned leads.</td></tr>
                    </tbody>
                </table>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Definitions</h2>
                <dl class="space-y-2 text-xs text-slate-600">
                    <div v-for="(text, key) in summary.definitions" :key="key"><dt class="font-medium capitalize">{{ label(key) }}</dt><dd>{{ text }}</dd></div>
                </dl>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
