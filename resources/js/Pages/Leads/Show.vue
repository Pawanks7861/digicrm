<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import LeadsNav from '@/Components/Leads/LeadsNav.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    lead: Object,
    allowedTransitions: Array,
    duplicateCandidates: Array,
    lostReasons: Array,
    stages: Array,
    users: Array,
    communicationTypes: Array,
    can: Object,
});

const label = (v) => (v || '').replaceAll('_', ' ');
const fmt = (d) => (d ? new Date(String(d).replace(' ', 'T')).toLocaleString() : '—');
const isAgency = computed(() => props.lead.lead_category === 'agency_sales');
const merged = computed(() => !!props.lead.deleted_at);

const statusForm = useForm({ status: '', reason: '', lost_reason_id: '', lost_notes: '' });
const submitStatus = () => statusForm.post(route('leads.transition', props.lead.id), { preserveScroll: true, onSuccess: () => statusForm.reset() });

const assignForm = useForm({ assigned_to: props.lead.assigned_to || '', use_rules: false, reason: '' });
const submitAssign = (useRules = false) => {
    assignForm.use_rules = useRules;
    assignForm.transform((d) => ({ ...d, assigned_to: d.assigned_to || null })).post(route('leads.assign', props.lead.id), { preserveScroll: true });
};

const noteForm = useForm({ body: '', attachment: null });
const submitNote = () => noteForm.post(route('leads.notes.store', props.lead.id), { preserveScroll: true, forceFormData: true, onSuccess: () => noteForm.reset() });

const commForm = useForm({
    communication_type: props.communicationTypes?.[0] || 'call',
    direction: 'outbound',
    subject: '',
    notes: '',
    follow_up_required: false,
    next_follow_up_at: '',
    follow_up_title: '',
});
const submitComm = () => commForm.transform((d) => ({ ...d, next_follow_up_at: d.next_follow_up_at || null })).post(route('leads.communications.store', props.lead.id), { preserveScroll: true, onSuccess: () => commForm.reset() });

const fuForm = useForm({ lead_id: props.lead.id, assigned_to: props.lead.assigned_to || '', title: '', notes: '', due_at: '', priority: 'normal', follow_up_type: 'call' });
const submitFollowUp = () => fuForm.post(route('follow-ups.store'), { preserveScroll: true, onSuccess: () => fuForm.reset('title', 'notes', 'due_at') });

const oppForm = useForm({ title: '', sales_pipeline_stage_id: props.stages?.[0]?.id || '', estimated_value: props.lead.estimated_budget || '', currency: props.lead.currency || 'INR', expected_close_date: '' });
const showOpp = ref(false);
const submitOpp = () => oppForm.transform((d) => ({ ...d, title: d.title || null, expected_close_date: d.expected_close_date || null })).post(route('leads.opportunities.store', props.lead.id));

const convert = () => {
    if (confirm('Convert this lead into an agency client? This can only happen once.')) {
        router.post(route('leads.convert', props.lead.id));
    }
};
const mergeInto = (target) => {
    if (confirm(`Merge ${props.lead.lead_number} into ${target.lead_number}? This lead will be archived and its history moved.`)) {
        router.post(route('leads.merge', props.lead.id), { target_id: target.id });
    }
};
const keepSeparate = () => router.post(route('leads.keep-separate', props.lead.id), {}, { preserveScroll: true });
const markDuplicate = () => {
    if (confirm('Mark this lead as a duplicate? It will be closed as lost with reason "Duplicate" where allowed.')) {
        router.post(route('leads.mark-duplicate', props.lead.id), {}, { preserveScroll: true });
    }
};

const attribution = computed(() => {
    const l = props.lead;
    return [
        ['Source', l.source?.name],
        ['Source detail', l.source_detail],
        ['Campaign', l.campaign?.name],
        ['Content', l.content_item?.title],
        ['Capture form', l.capture_form?.name],
        ['Provider', l.provider && !String(l.provider).startsWith('form:') && !String(l.provider).startsWith('csv:') ? l.provider : null],
        ['UTM source', l.utm_source],
        ['UTM medium', l.utm_medium],
        ['UTM campaign', l.utm_campaign],
        ['UTM content', l.utm_content],
        ['UTM term', l.utm_term],
        ['GCLID', l.gclid],
        ['FBCLID', l.fbclid],
        ['Landing page', l.landing_page_url],
        ['Referrer', l.referrer],
    ].filter(([, v]) => v);
});
</script>

<template>
    <Head :title="lead.lead_number" />
    <AuthenticatedLayout>
        <LeadsNav />
        <div v-if="merged" class="mb-4 rounded-lg bg-zinc-100 px-4 py-3 text-sm text-zinc-700">
            This lead was merged<span v-if="lead.merged_into"> into <Link :href="route('leads.show', lead.merged_into.id)" class="font-medium text-teal-800 underline">{{ lead.merged_into.lead_number }}</Link></span>. It is kept for audit only.
        </div>

        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="text-xs text-slate-400">{{ lead.lead_number }} · <span class="capitalize">{{ label(lead.lead_category) }}</span><span v-if="lead.client"> · {{ lead.client.company_name }}</span></div>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ lead.name }}<span v-if="lead.company" class="text-slate-500"> · {{ lead.company }}</span></h1>
                <div class="mt-2 flex flex-wrap gap-1">
                    <StatusBadge :status="lead.status" />
                    <StatusBadge :status="lead.priority" />
                    <StatusBadge v-if="lead.duplicate_status" :status="lead.duplicate_status" />
                    <span v-if="lead.converted_client" class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs text-emerald-800">Client: <Link :href="route('clients.show', lead.converted_client.id)" class="underline">{{ lead.converted_client.company_name }}</Link></span>
                    <span v-else-if="lead.prospect_client" class="rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-700">Prospect record: {{ lead.prospect_client.company_name }}</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link v-if="can.update" :href="route('leads.edit', lead.id)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Edit</Link>
                <button v-if="can.createOpportunity" type="button" class="rounded-lg border border-teal-200 bg-white px-3 py-2 text-sm text-teal-800 hover:bg-teal-50" @click="showOpp = !showOpp">New opportunity</button>
                <button v-if="can.convert" type="button" class="rounded-lg bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800" @click="convert">Convert to client</button>
            </div>
        </div>

        <form v-if="showOpp" class="mb-6 grid gap-3 rounded-xl border border-teal-200 bg-teal-50/40 p-4 sm:grid-cols-5" @submit.prevent="submitOpp">
            <input v-model="oppForm.title" placeholder="Title (optional)" class="rounded-lg border-slate-200 text-sm sm:col-span-2" />
            <select v-model="oppForm.sales_pipeline_stage_id" class="rounded-lg border-slate-200 text-sm">
                <option v-for="s in stages" :key="s.id" :value="s.id">{{ s.name }} ({{ s.probability }}%)</option>
            </select>
            <div class="flex gap-1">
                <input v-model="oppForm.estimated_value" type="number" min="0" step="0.01" placeholder="Value" class="w-full rounded-lg border-slate-200 text-sm" />
                <input v-model="oppForm.currency" maxlength="3" class="w-16 rounded-lg border-slate-200 text-sm uppercase" />
            </div>
            <input v-model="oppForm.expected_close_date" type="date" class="rounded-lg border-slate-200 text-sm" />
            <div class="sm:col-span-5 flex items-center justify-between">
                <InputError :message="oppForm.errors.lead || oppForm.errors.sales_pipeline_stage_id" />
                <button type="submit" :disabled="oppForm.processing" class="rounded-lg bg-teal-700 px-3 py-2 text-sm text-white">Create opportunity</button>
            </div>
        </form>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700">Contact & requirement</h2>
                    <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs text-slate-400">Email</dt><dd>{{ lead.email || '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-400">Mobile</dt><dd>{{ lead.mobile || '—' }} <span v-if="lead.alternate_mobile" class="text-slate-400">/ {{ lead.alternate_mobile }}</span></dd></div>
                        <div><dt class="text-xs text-slate-400">Location</dt><dd>{{ [lead.city, lead.state, lead.country].filter(Boolean).join(', ') || '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-400">Industry</dt><dd>{{ lead.industry || '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-400">Interested in</dt><dd>{{ lead.interested_service || '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-400">Budget</dt><dd>{{ lead.estimated_budget ? `${lead.currency || ''} ${lead.estimated_budget}` : '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs text-slate-400">Requirement</dt><dd class="whitespace-pre-line">{{ lead.requirement || '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-400">First contacted</dt><dd>{{ fmt(lead.first_contacted_at) }}</dd></div>
                        <div><dt class="text-xs text-slate-400">Last contacted</dt><dd>{{ fmt(lead.last_contacted_at) }}</dd></div>
                        <div><dt class="text-xs text-slate-400">Next follow-up</dt><dd>{{ fmt(lead.next_follow_up_at) }}</dd></div>
                        <div><dt class="text-xs text-slate-400">Consent</dt><dd>{{ lead.consent_given ? `Given ${fmt(lead.consent_at)}` : 'Not recorded' }}</dd></div>
                        <div v-if="lead.lost_reason" class="sm:col-span-2"><dt class="text-xs text-slate-400">Lost reason</dt><dd>{{ lead.lost_reason.name }}<span v-if="lead.lost_notes"> — {{ lead.lost_notes }}</span></dd></div>
                    </dl>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="mb-1 text-sm font-semibold text-slate-700">First-touch attribution</h2>
                    <p class="mb-3 text-xs text-slate-400">Locked at capture. Later touches are listed below and never overwrite it.</p>
                    <dl v-if="attribution.length" class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                        <div v-for="[k, v] in attribution" :key="k" class="min-w-0"><dt class="text-xs text-slate-400">{{ k }}</dt><dd class="truncate" :title="v">{{ v }}</dd></div>
                    </dl>
                    <p v-else class="text-sm text-slate-500">No attribution recorded.</p>
                    <h3 class="mb-2 mt-4 text-xs font-semibold uppercase text-slate-400">Touchpoints</h3>
                    <ul class="space-y-2 text-sm">
                        <li v-for="t in lead.touchpoints" :key="t.id" class="rounded-lg bg-slate-50 px-3 py-2">
                            <div class="flex flex-wrap justify-between gap-2">
                                <span class="font-medium capitalize">{{ label(t.touch_type) }}</span>
                                <span class="text-xs text-slate-400">{{ fmt(t.occurred_at) }} · {{ label(t.provenance) }}</span>
                            </div>
                            <div class="text-xs text-slate-500">{{ [t.source?.name, t.campaign?.name, t.utm_source, t.utm_campaign].filter(Boolean).join(' · ') || 'No source data' }}<span v-if="t.recorder"> · by {{ t.recorder.name }}</span></div>
                        </li>
                    </ul>
                </section>

                <section v-if="can.merge && (duplicateCandidates.length || lead.duplicate_status === 'possible')" class="rounded-xl border border-amber-200 bg-amber-50/40 p-5">
                    <h2 class="mb-3 text-sm font-semibold text-amber-900">Possible duplicates</h2>
                    <ul class="space-y-2 text-sm">
                        <li v-for="d in duplicateCandidates" :key="d.id" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white px-3 py-2">
                            <div>
                                <Link :href="route('leads.show', d.id)" class="font-medium text-teal-800 hover:underline">{{ d.lead_number }} · {{ d.name }}</Link>
                                <div class="text-xs text-slate-500">{{ [d.company, d.email, d.mobile].filter(Boolean).join(' · ') }}</div>
                            </div>
                            <button type="button" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50" @click="mergeInto(d)">Merge into this</button>
                        </li>
                    </ul>
                    <div v-if="lead.duplicate_status === 'possible'" class="mt-3 flex gap-2">
                        <button type="button" class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs" @click="keepSeparate">Not a duplicate</button>
                        <button type="button" class="rounded-md border border-rose-200 bg-white px-3 py-1.5 text-xs text-rose-700" @click="markDuplicate">Mark as duplicate</button>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700">Communication log</h2>
                    <form v-if="can.logCommunication" class="mb-4 grid gap-2 sm:grid-cols-3" @submit.prevent="submitComm">
                        <select v-model="commForm.communication_type" class="rounded-lg border-slate-200 text-sm">
                            <option v-for="t in communicationTypes" :key="t" :value="t">{{ label(t) }}</option>
                        </select>
                        <select v-model="commForm.direction" class="rounded-lg border-slate-200 text-sm">
                            <option value="outbound">Outbound</option>
                            <option value="inbound">Inbound</option>
                            <option value="internal">Internal note</option>
                        </select>
                        <input v-model="commForm.subject" placeholder="Subject" class="rounded-lg border-slate-200 text-sm" />
                        <textarea v-model="commForm.notes" rows="2" placeholder="What happened?" class="rounded-lg border-slate-200 text-sm sm:col-span-3" />
                        <label class="flex items-center gap-2 text-xs text-slate-600"><input v-model="commForm.follow_up_required" type="checkbox" class="rounded border-slate-300" /> Schedule follow-up</label>
                        <template v-if="commForm.follow_up_required">
                            <input v-model="commForm.next_follow_up_at" type="datetime-local" class="rounded-lg border-slate-200 text-sm" />
                            <input v-model="commForm.follow_up_title" placeholder="Follow-up title" class="rounded-lg border-slate-200 text-sm" />
                        </template>
                        <div class="flex items-center justify-between sm:col-span-3">
                            <InputError :message="commForm.errors.notes || commForm.errors.next_follow_up_at" />
                            <button type="submit" :disabled="commForm.processing" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm text-white">Log</button>
                        </div>
                    </form>
                    <ul class="divide-y divide-slate-100 text-sm">
                        <li v-for="c in lead.communications" :key="c.id" class="py-2">
                            <div class="flex justify-between gap-2"><span class="font-medium capitalize">{{ label(c.communication_type) }} · {{ c.direction }}</span><span class="text-xs text-slate-400">{{ fmt(c.occurred_at) }}</span></div>
                            <div v-if="c.subject" class="text-slate-700">{{ c.subject }}</div>
                            <div class="whitespace-pre-line text-slate-600">{{ c.notes }}</div>
                            <div class="text-xs text-slate-400">{{ c.user?.name }}</div>
                        </li>
                        <li v-if="!lead.communications.length" class="py-2 text-slate-500">No communications yet.</li>
                    </ul>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700">Notes</h2>
                    <form v-if="can.update" class="mb-4 space-y-2" @submit.prevent="submitNote">
                        <textarea v-model="noteForm.body" rows="2" placeholder="Add an internal note…" class="w-full rounded-lg border-slate-200 text-sm" />
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <input type="file" class="text-xs" @input="noteForm.attachment = $event.target.files[0]" />
                            <button type="submit" :disabled="noteForm.processing" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm text-white">Add note</button>
                        </div>
                        <InputError :message="noteForm.errors.body || noteForm.errors.attachment" />
                    </form>
                    <ul class="divide-y divide-slate-100 text-sm">
                        <li v-for="n in lead.notes" :key="n.id" class="py-2">
                            <div class="flex justify-between gap-2 text-xs text-slate-400">
                                <span>{{ n.user?.name }} <span v-if="n.visibility === 'client'" class="ml-1 rounded bg-indigo-50 px-1.5 text-indigo-700">from client portal</span></span>
                                <span>{{ fmt(n.created_at) }}</span>
                            </div>
                            <div class="whitespace-pre-line">{{ n.body }}</div>
                            <a v-if="n.has_attachment" :href="route('leads.notes.download', [lead.id, n.id])" class="text-xs text-teal-800 underline">{{ n.attachment_name || 'Attachment' }}</a>
                        </li>
                        <li v-if="!lead.notes.length" class="py-2 text-slate-500">No notes yet.</li>
                    </ul>
                </section>
            </div>

            <div class="space-y-6">
                <section v-if="can.manageStatus" class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700">Status</h2>
                    <form v-if="allowedTransitions.length" class="space-y-2" @submit.prevent="submitStatus">
                        <select v-model="statusForm.status" class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">Move to…</option>
                            <option v-for="s in allowedTransitions" :key="s" :value="s">{{ label(s) }}</option>
                        </select>
                        <template v-if="statusForm.status === 'lost'">
                            <select v-model="statusForm.lost_reason_id" class="w-full rounded-lg border-slate-200 text-sm">
                                <option value="">Lost reason…</option>
                                <option v-for="r in lostReasons" :key="r.id" :value="r.id">{{ r.name }}</option>
                            </select>
                            <textarea v-model="statusForm.lost_notes" rows="2" placeholder="Lost notes" class="w-full rounded-lg border-slate-200 text-sm" />
                        </template>
                        <input v-model="statusForm.reason" placeholder="Reason / comment" class="w-full rounded-lg border-slate-200 text-sm" />
                        <InputError :message="statusForm.errors.status || statusForm.errors.lost_reason_id" />
                        <button type="submit" :disabled="!statusForm.status || statusForm.processing" class="w-full rounded-lg bg-teal-700 px-3 py-2 text-sm text-white disabled:opacity-50">Update status</button>
                    </form>
                    <p v-else class="text-sm text-slate-500">This lead is closed.</p>
                    <p v-if="!isAgency" class="mt-2 text-xs text-slate-400">Campaign leads use the qualification statuses only; they never become agency opportunities or clients.</p>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700">Assignment</h2>
                    <p class="mb-2 text-sm">{{ lead.assignee?.name || 'Unassigned' }} <span v-if="lead.assigned_at" class="text-xs text-slate-400">since {{ fmt(lead.assigned_at) }}</span></p>
                    <div v-if="can.assign" class="space-y-2">
                        <select v-model="assignForm.assigned_to" class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">Unassigned</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                        <InputError :message="assignForm.errors.assigned_to" />
                        <div class="flex gap-2">
                            <button type="button" class="flex-1 rounded-lg bg-slate-800 px-3 py-1.5 text-sm text-white" @click="submitAssign(false)">Save</button>
                            <button type="button" class="flex-1 rounded-lg border border-slate-200 px-3 py-1.5 text-sm" @click="submitAssign(true)">Apply rules</button>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700">Follow-ups</h2>
                    <ul class="mb-3 space-y-2 text-sm">
                        <li v-for="fu in lead.follow_ups" :key="fu.id" class="rounded-lg bg-slate-50 px-3 py-2">
                            <div class="flex justify-between gap-2"><span class="font-medium">{{ fu.title }}</span><StatusBadge :status="fu.status" /></div>
                            <div class="text-xs text-slate-500">{{ fmt(fu.due_at) }} · {{ fu.assignee?.name }}</div>
                        </li>
                        <li v-if="!lead.follow_ups.length" class="text-slate-500">No follow-ups.</li>
                    </ul>
                    <form v-if="can.createFollowUp" class="space-y-2" @submit.prevent="submitFollowUp">
                        <input v-model="fuForm.title" placeholder="Follow-up title" class="w-full rounded-lg border-slate-200 text-sm" />
                        <input v-model="fuForm.due_at" type="datetime-local" class="w-full rounded-lg border-slate-200 text-sm" />
                        <select v-model="fuForm.assigned_to" class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">Assign to…</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                        <InputError :message="fuForm.errors.title || fuForm.errors.due_at || fuForm.errors.assigned_to" />
                        <button type="submit" :disabled="fuForm.processing" class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm">Add follow-up</button>
                    </form>
                    <Link :href="route('follow-ups.index')" class="mt-2 inline-block text-xs text-teal-800 underline">All follow-ups</Link>
                </section>

                <section v-if="isAgency" class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700">Opportunities & quotations</h2>
                    <ul class="space-y-2 text-sm">
                        <li v-for="o in lead.opportunities" :key="o.id" class="rounded-lg bg-slate-50 px-3 py-2">
                            <Link :href="route('opportunities.show', o.id)" class="font-medium text-teal-800 hover:underline">{{ o.opportunity_number }} · {{ o.title }}</Link>
                            <div class="text-xs text-slate-500">{{ o.stage?.name }} · {{ o.currency }} {{ o.estimated_value }} · <span class="capitalize">{{ o.status }}</span></div>
                        </li>
                        <li v-for="q in lead.quotations" :key="'q' + q.id" class="rounded-lg bg-slate-50 px-3 py-2">
                            <Link :href="route('quotations.show', q.id)" class="font-medium text-teal-800 hover:underline">{{ q.quotation_number }}</Link>
                            <div class="text-xs text-slate-500">{{ q.currency }} {{ q.grand_total }} · <span class="capitalize">{{ label(q.status) }}</span></div>
                        </li>
                        <li v-if="!lead.opportunities.length && !lead.quotations.length" class="text-slate-500">None yet.</li>
                    </ul>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700">Status history</h2>
                    <ol class="space-y-2 text-sm">
                        <li v-for="h in lead.status_histories" :key="h.id" class="border-l-2 border-slate-200 pl-3">
                            <div><span class="capitalize text-slate-400">{{ label(h.from_status) || 'created' }}</span> → <span class="font-medium capitalize">{{ label(h.to_status) }}</span></div>
                            <div class="text-xs text-slate-400">{{ fmt(h.created_at) }} · {{ h.actor?.name || 'System' }}</div>
                            <div v-if="h.reason" class="text-xs text-slate-600">{{ h.reason }}</div>
                            <div v-if="h.lost_reason" class="text-xs text-rose-600">{{ h.lost_reason.name }}</div>
                        </li>
                    </ol>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
