<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import LeadsNav from '@/Components/Leads/LeadsNav.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ opportunity: Object, stages: Array, lostReasons: Array, users: Array, taxRates: Array, can: Object });
const o = props.opportunity;
const isOpen = o.status === 'open';

const edit = useForm({
    title: o.title,
    estimated_value: o.estimated_value,
    currency: o.currency,
    probability: o.probability,
    expected_close_date: o.expected_close_date ? String(o.expected_close_date).slice(0, 10) : '',
    owner_id: o.owner_id || '',
    description: o.description || '',
});
const saveEdit = () => edit.transform((d) => ({ ...d, expected_close_date: d.expected_close_date || null, owner_id: d.owner_id || null })).put(route('opportunities.update', o.id), { preserveScroll: true });

const stageForm = useForm({ sales_pipeline_stage_id: o.sales_pipeline_stage_id, lost_reason_id: '', lost_notes: '' });
const selectedStage = () => props.stages.find((s) => s.id === Number(stageForm.sales_pipeline_stage_id));
const saveStage = () => stageForm.post(route('opportunities.stage', o.id), { preserveScroll: true });

const markWon = () => {
    if (confirm('Mark this opportunity as won?')) router.post(route('opportunities.won', o.id), {}, { preserveScroll: true });
};

const showQuote = ref(false);
const quote = useForm({
    valid_until: '',
    currency: o.currency,
    notes: '',
    items: [{ name: o.title, description: '', quantity: 1, unit_rate: o.estimated_value || 0, tax_rate_id: '' }],
});
const addItem = () => quote.items.push({ name: '', description: '', quantity: 1, unit_rate: 0, tax_rate_id: '' });
const saveQuote = () => quote.transform((d) => ({
    ...d,
    valid_until: d.valid_until || null,
    items: d.items.map((i) => ({ ...i, tax_rate_id: i.tax_rate_id || null })),
})).post(route('opportunities.quotation', o.id));
</script>

<template>
    <Head :title="opportunity.opportunity_number" />
    <AuthenticatedLayout>
        <LeadsNav />
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="text-xs text-slate-400">{{ opportunity.opportunity_number }}</div>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ opportunity.title }}</h1>
                <div class="mt-2 flex flex-wrap gap-2 text-sm">
                    <StatusBadge :status="opportunity.status" />
                    <span class="text-slate-500">{{ opportunity.stage?.name }} · {{ opportunity.currency }} {{ Number(opportunity.estimated_value).toLocaleString() }} · {{ opportunity.probability ?? 0 }}%</span>
                </div>
                <div v-if="opportunity.lead" class="mt-1 text-sm">Lead: <Link :href="route('leads.show', opportunity.lead.id)" class="text-teal-800 underline">{{ opportunity.lead.lead_number }} · {{ opportunity.lead.company || opportunity.lead.name }}</Link></div>
                <div v-if="opportunity.client" class="text-sm">Client record: <Link :href="route('clients.show', opportunity.client.id)" class="text-teal-800 underline">{{ opportunity.client.company_name }}</Link> <span class="capitalize text-slate-400">({{ opportunity.client.status }})</span></div>
                <div v-if="opportunity.lost_reason" class="mt-1 text-sm text-rose-700">Lost: {{ opportunity.lost_reason.name }}<span v-if="opportunity.lost_notes"> — {{ opportunity.lost_notes }}</span></div>
            </div>
            <div class="flex gap-2">
                <button v-if="can.quote" type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" @click="showQuote = !showQuote">Create quotation</button>
                <button v-if="can.update && isOpen" type="button" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm text-white" @click="markWon">Mark won</button>
            </div>
        </div>

        <form v-if="showQuote" class="mb-6 space-y-3 rounded-xl border border-teal-200 bg-white p-5" @submit.prevent="saveQuote">
            <h2 class="text-sm font-semibold text-slate-700">New quotation</h2>
            <p class="text-xs text-slate-500">Quotations use the existing quotation workflow. A prospect client record is created once for this lead if needed; it stays a prospect until conversion.</p>
            <div class="grid gap-2 sm:grid-cols-3">
                <input v-model="quote.valid_until" type="date" class="rounded-lg border-slate-200 text-sm" aria-label="Valid until" />
                <input v-model="quote.currency" maxlength="3" class="rounded-lg border-slate-200 text-sm uppercase" />
            </div>
            <div v-for="(item, i) in quote.items" :key="i" class="grid gap-2 sm:grid-cols-6">
                <input v-model="item.name" placeholder="Item" class="rounded-lg border-slate-200 text-sm sm:col-span-2" />
                <input v-model="item.quantity" type="number" min="0.01" step="0.01" class="rounded-lg border-slate-200 text-sm" />
                <input v-model="item.unit_rate" type="number" min="0" step="0.01" class="rounded-lg border-slate-200 text-sm" />
                <select v-model="item.tax_rate_id" class="rounded-lg border-slate-200 text-sm">
                    <option value="">No tax</option>
                    <option v-for="t in taxRates" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <button v-if="quote.items.length > 1" type="button" class="text-xs text-rose-700" @click="quote.items.splice(i, 1)">Remove</button>
            </div>
            <button type="button" class="text-xs text-teal-800 underline" @click="addItem">Add item</button>
            <textarea v-model="quote.notes" rows="2" placeholder="Notes" class="w-full rounded-lg border-slate-200 text-sm" />
            <InputError :message="quote.errors.items || quote.errors.opportunity || Object.values(quote.errors)[0]" />
            <div class="flex justify-end"><button type="submit" :disabled="quote.processing" class="rounded-lg bg-teal-700 px-4 py-2 text-sm text-white">Create draft quotation</button></div>
        </form>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Details</h2>
                <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="saveEdit">
                    <div class="sm:col-span-2"><label class="text-xs text-slate-500">Title</label><input v-model="edit.title" :disabled="!can.update || !isOpen" class="mt-1 w-full rounded-lg border-slate-200 text-sm" /></div>
                    <div><label class="text-xs text-slate-500">Estimated value</label><input v-model="edit.estimated_value" type="number" min="0" step="0.01" :disabled="!can.update || !isOpen" class="mt-1 w-full rounded-lg border-slate-200 text-sm" /></div>
                    <div><label class="text-xs text-slate-500">Currency</label><input v-model="edit.currency" maxlength="3" :disabled="!can.update || !isOpen" class="mt-1 w-full rounded-lg border-slate-200 text-sm uppercase" /></div>
                    <div><label class="text-xs text-slate-500">Probability %</label><input v-model="edit.probability" type="number" min="0" max="100" :disabled="!can.update || !isOpen" class="mt-1 w-full rounded-lg border-slate-200 text-sm" /></div>
                    <div><label class="text-xs text-slate-500">Expected close</label><input v-model="edit.expected_close_date" type="date" :disabled="!can.update || !isOpen" class="mt-1 w-full rounded-lg border-slate-200 text-sm" /></div>
                    <div><label class="text-xs text-slate-500">Owner</label>
                        <select v-model="edit.owner_id" :disabled="!can.update || !isOpen" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                            <option value="">None</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2"><label class="text-xs text-slate-500">Description</label><textarea v-model="edit.description" rows="3" :disabled="!can.update" class="mt-1 w-full rounded-lg border-slate-200 text-sm" /></div>
                    <div v-if="can.update" class="flex justify-end sm:col-span-2"><button type="submit" :disabled="edit.processing" class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white">Save</button></div>
                </form>
            </section>

            <div class="space-y-6">
                <section v-if="can.update && isOpen" class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700">Stage</h2>
                    <form class="space-y-2" @submit.prevent="saveStage">
                        <select v-model="stageForm.sales_pipeline_stage_id" class="w-full rounded-lg border-slate-200 text-sm">
                            <option v-for="s in stages" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <template v-if="selectedStage()?.is_lost">
                            <select v-model="stageForm.lost_reason_id" class="w-full rounded-lg border-slate-200 text-sm">
                                <option value="">Lost reason…</option>
                                <option v-for="r in lostReasons" :key="r.id" :value="r.id">{{ r.name }}</option>
                            </select>
                            <textarea v-model="stageForm.lost_notes" rows="2" placeholder="Notes" class="w-full rounded-lg border-slate-200 text-sm" />
                        </template>
                        <InputError :message="stageForm.errors.lost_reason_id || stageForm.errors.stage" />
                        <button type="submit" :disabled="stageForm.processing" class="w-full rounded-lg bg-teal-700 px-3 py-2 text-sm text-white">Update stage</button>
                    </form>
                </section>
                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700">Quotations</h2>
                    <ul class="space-y-2 text-sm">
                        <li v-for="q in opportunity.quotations" :key="q.id" class="rounded-lg bg-slate-50 px-3 py-2">
                            <Link :href="route('quotations.show', q.id)" class="font-medium text-teal-800 hover:underline">{{ q.quotation_number }}</Link>
                            <div class="text-xs text-slate-500">{{ q.currency }} {{ q.grand_total }} · <span class="capitalize">{{ q.status }}</span></div>
                        </li>
                        <li v-if="!opportunity.quotations.length" class="text-slate-500">No quotations yet.</li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
