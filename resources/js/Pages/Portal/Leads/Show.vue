<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import InputError from '@/Components/InputError.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ lead: Object, history: Array, notes: Array, flags: Object });
const form = useForm({ body: '' });
const submit = () => form.post(route('portal.leads.notes', props.lead.id), { preserveScroll: true, onSuccess: () => form.reset() });
const label = (v) => (v || '').replaceAll('_', ' ');
const fmt = (d) => (d ? new Date(String(d).replace(' ', 'T')).toLocaleString() : '—');
</script>

<template>
    <Head :title="lead.lead_number" />
    <PortalLayout>
        <Link :href="route('portal.leads')" class="text-sm text-teal-800 underline">← All leads</Link>
        <div class="mt-3 flex flex-wrap items-start justify-between gap-2">
            <div>
                <div class="text-xs text-slate-400">{{ lead.lead_number }}</div>
                <h1 class="text-xl font-semibold">{{ lead.name }}<span v-if="lead.company" class="text-slate-500"> · {{ lead.company }}</span></h1>
            </div>
            <StatusBadge :status="lead.status" />
        </div>
        <dl class="mt-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 text-sm sm:grid-cols-2">
            <div v-if="flags.show_lead_contact_details"><dt class="text-xs text-slate-400">Email</dt><dd>{{ lead.email || '—' }}</dd></div>
            <div v-if="flags.show_lead_contact_details"><dt class="text-xs text-slate-400">Mobile</dt><dd>{{ lead.mobile || '—' }}</dd></div>
            <div><dt class="text-xs text-slate-400">Received</dt><dd>{{ fmt(lead.created_at) }}</dd></div>
            <div><dt class="text-xs text-slate-400">First contacted</dt><dd>{{ fmt(lead.first_contacted_at) }}</dd></div>
            <div><dt class="text-xs text-slate-400">Campaign</dt><dd>{{ lead.campaign || '—' }}</dd></div>
            <div v-if="flags.show_lead_source"><dt class="text-xs text-slate-400">Source</dt><dd>{{ lead.source || '—' }}<span v-if="lead.utm_source" class="text-slate-400"> · {{ [lead.utm_source, lead.utm_medium, lead.utm_campaign].filter(Boolean).join(' / ') }}</span></dd></div>
            <div><dt class="text-xs text-slate-400">City</dt><dd>{{ lead.city || '—' }}</dd></div>
            <div><dt class="text-xs text-slate-400">Interested in</dt><dd>{{ lead.interested_service || '—' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-xs text-slate-400">Requirement</dt><dd class="whitespace-pre-line">{{ lead.requirement || '—' }}</dd></div>
        </dl>

        <section class="mt-4 rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="mb-2 text-sm font-semibold">Status history</h2>
            <ol class="space-y-1 text-sm">
                <li v-for="h in history" :key="h.id"><span class="capitalize">{{ label(h.to_status) }}</span> <span class="text-xs text-slate-400">· {{ fmt(h.created_at) }}</span></li>
            </ol>
        </section>

        <section class="mt-4 rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="mb-2 text-sm font-semibold">Updates for the agency</h2>
            <form v-if="flags.allow_lead_updates" class="mb-3 space-y-2" @submit.prevent="submit">
                <textarea v-model="form.body" rows="2" placeholder="e.g. Customer visited the showroom on Monday" class="w-full rounded-md border-slate-300 text-sm" />
                <InputError :message="form.errors.body" />
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-teal-700 px-3 py-1.5 text-sm text-white">Send update</button>
            </form>
            <ul class="divide-y divide-slate-100 text-sm">
                <li v-for="n in notes" :key="n.id" class="py-2"><div class="text-xs text-slate-400">{{ n.user?.name }} · {{ fmt(n.created_at) }}</div><div class="whitespace-pre-line">{{ n.body }}</div></li>
                <li v-if="!notes.length" class="py-2 text-slate-500">No updates yet.</li>
            </ul>
        </section>
    </PortalLayout>
</template>
