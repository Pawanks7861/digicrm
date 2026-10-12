<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import LeadsNav from '@/Components/Leads/LeadsNav.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ forms: Array, mappings: Array, meta: Object, options: Object });

const blank = () => ({
    name: '', lead_category: 'agency_sales', client_id: '', campaign_id: '', lead_source_id: '', is_active: true,
    allowed_origins_text: '', require_consent: true, consent_text: 'I agree to be contacted about my enquiry.',
    min_fill_seconds: 3, honeypot_field: 'website_url', fields: ['name', 'email', 'mobile', 'requirement'],
    success_message: '', redirect_url: '', hmac_secret: '', clear_hmac_secret: false,
});
const editingId = ref(null);
const form = useForm(blank());
const openSnippet = ref(null);

const campaigns = computed(() => (form.lead_category === 'client_campaign'
    ? props.options.campaigns.filter((c) => String(c.client_id) === String(form.client_id))
    : props.options.campaigns));

const edit = (f) => {
    editingId.value = f.id;
    Object.assign(form, {
        ...blank(),
        name: f.name, lead_category: f.lead_category, client_id: f.client_id || '', campaign_id: f.campaign_id || '',
        lead_source_id: f.lead_source_id || '', is_active: f.is_active, allowed_origins_text: (f.allowed_origins || []).join('\n'),
        require_consent: f.require_consent, consent_text: f.consent_text || '', min_fill_seconds: f.min_fill_seconds,
        honeypot_field: f.honeypot_field, fields: f.fields || [], success_message: f.success_message || '', redirect_url: f.redirect_url || '',
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
};
const reset = () => { editingId.value = null; Object.assign(form, blank()); form.clearErrors(); };
const save = () => {
    const t = (d) => {
        const out = Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v]));
        out.allowed_origins = (d.allowed_origins_text || '').split(/\s+/).map((s) => s.trim()).filter(Boolean);
        delete out.allowed_origins_text;
        if (!out.hmac_secret) delete out.hmac_secret;
        return out;
    };
    const opts = { preserveScroll: true, onSuccess: reset };
    if (editingId.value) form.transform(t).put(route('leads.settings.forms.update', editingId.value), opts);
    else form.transform(t).post(route('leads.settings.forms.store'), opts);
};
const regenerate = (f) => { if (confirm('Regenerate the form key? The existing embed code will stop working.')) router.post(route('leads.settings.forms.regenerate', f.id), {}, { preserveScroll: true }); };
const archive = (f) => { if (confirm(`Archive "${f.name}"?`)) router.delete(route('leads.settings.forms.destroy', f.id), { preserveScroll: true }); };
const copy = async (text) => { try { await navigator.clipboard.writeText(text); } catch (e) { /* clipboard unavailable */ } };

const mapping = useForm({ integration_connection_id: '', page_id: '', form_id: '', form_name: '', lead_category: 'client_campaign', client_id: '', campaign_id: '', lead_source_id: '', is_active: true });
const mappingCampaigns = computed(() => props.options.campaigns.filter((c) => !mapping.client_id || String(c.client_id) === String(mapping.client_id)));
const saveMapping = () => mapping.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])))
    .post(route('leads.settings.mappings.store'), { preserveScroll: true, onSuccess: () => mapping.reset() });
const toggleMapping = (m) => router.put(route('leads.settings.mappings.update', m.id), { ...m, is_active: !m.is_active }, { preserveScroll: true });
const removeMapping = (m) => { if (confirm('Remove this mapping?')) router.delete(route('leads.settings.mappings.destroy', m.id), { preserveScroll: true }); };
const metaOperational = computed(() => props.meta.webhook_secret_configured && props.meta.connections.some((c) => c.operational));
const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head title="Lead Capture Forms" />
    <AuthenticatedLayout>
        <div class="mb-4">
            <h1 class="text-2xl font-semibold tracking-tight">Lead capture</h1>
            <p class="mt-1 text-sm text-slate-500">Website forms post to a public endpoint protected by origin checks, a honeypot, a minimum fill time and optional HMAC signing. Category, client and campaign always come from the form record, never from the visitor.</p>
        </div>
        <LeadsNav />

        <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">{{ editingId ? 'Edit form' : 'New website form' }}</h2>
            <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="save">
                <div class="sm:col-span-2"><label class="text-xs text-slate-500">Name</label><input v-model="form.name" class="mt-1 w-full rounded-lg border-slate-200 text-sm" /><InputError :message="form.errors.name" /></div>
                <div><label class="text-xs text-slate-500">Category</label>
                    <select v-model="form.lead_category" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option v-for="c in options.categories" :key="c" :value="c">{{ label(c) }}</option>
                    </select>
                </div>
                <div v-if="form.lead_category === 'client_campaign'"><label class="text-xs text-slate-500">Owning client</label>
                    <select v-model="form.client_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Select…</option>
                        <option v-for="c in options.clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
                    </select>
                    <InputError :message="form.errors.client_id" />
                </div>
                <div><label class="text-xs text-slate-500">Campaign</label>
                    <select v-model="form.campaign_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="">None</option>
                        <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <InputError :message="form.errors.campaign_id" />
                </div>
                <div><label class="text-xs text-slate-500">Source</label>
                    <select v-model="form.lead_source_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Website form</option>
                        <option v-for="s in options.sources" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div><label class="text-xs text-slate-500">Min. fill time (seconds)</label><input v-model="form.min_fill_seconds" type="number" min="0" class="mt-1 w-full rounded-lg border-slate-200 text-sm" /></div>
                <div><label class="text-xs text-slate-500">Honeypot field name</label><input v-model="form.honeypot_field" class="mt-1 w-full rounded-lg border-slate-200 text-sm" /><InputError :message="form.errors.honeypot_field" /></div>
                <div class="sm:col-span-2"><label class="text-xs text-slate-500">Allowed origins (one per line, e.g. https://example.com). Empty = any origin.</label>
                    <textarea v-model="form.allowed_origins_text" rows="2" class="mt-1 w-full rounded-lg border-slate-200 text-sm" />
                    <InputError :message="Object.entries(form.errors).find(([k]) => k.startsWith('allowed_origins'))?.[1]" />
                </div>
                <div class="sm:col-span-2"><label class="text-xs text-slate-500">Fields</label>
                    <div class="mt-1 flex flex-wrap gap-3">
                        <label v-for="f in options.fields" :key="f" class="flex items-center gap-1 text-sm"><input v-model="form.fields" type="checkbox" :value="f" class="rounded border-slate-300" /> {{ label(f) }}</label>
                    </div>
                </div>
                <div class="sm:col-span-2"><label class="flex items-center gap-2 text-sm"><input v-model="form.require_consent" type="checkbox" class="rounded border-slate-300" /> Require consent checkbox</label>
                    <input v-if="form.require_consent" v-model="form.consent_text" class="mt-1 w-full rounded-lg border-slate-200 text-sm" />
                </div>
                <div><label class="text-xs text-slate-500">Success message</label><input v-model="form.success_message" class="mt-1 w-full rounded-lg border-slate-200 text-sm" /></div>
                <div><label class="text-xs text-slate-500">Redirect URL (optional)</label><input v-model="form.redirect_url" class="mt-1 w-full rounded-lg border-slate-200 text-sm" /><InputError :message="form.errors.redirect_url" /></div>
                <div class="sm:col-span-2"><label class="text-xs text-slate-500">HMAC secret for server-to-server posts (min 16 chars; leave blank to keep). Browser embeds must not use a secret.</label>
                    <input v-model="form.hmac_secret" type="password" autocomplete="new-password" class="mt-1 w-full rounded-lg border-slate-200 text-sm" />
                    <label v-if="editingId" class="mt-1 flex items-center gap-2 text-xs"><input v-model="form.clear_hmac_secret" type="checkbox" class="rounded border-slate-300" /> Remove existing secret</label>
                    <InputError :message="form.errors.hmac_secret" />
                </div>
                <div class="flex items-center gap-2 sm:col-span-2"><label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" /> Active</label></div>
                <div class="flex justify-end gap-2 sm:col-span-2 lg:col-span-4">
                    <button v-if="editingId" type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" @click="reset">Cancel</button>
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-teal-700 px-4 py-2 text-sm text-white">{{ editingId ? 'Save form' : 'Create form' }}</button>
                </div>
            </form>
        </section>

        <section class="mb-6 space-y-3">
            <div v-for="f in forms" :key="f.id" class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="font-medium">{{ f.name }} <StatusBadge :status="f.is_active ? 'active' : 'inactive'" /></div>
                        <div class="text-xs text-slate-500">{{ label(f.lead_category) }}<span v-if="f.client"> · {{ f.client.company_name }}</span><span v-if="f.campaign"> · {{ f.campaign.name }}</span> · {{ f.submissions_count }} submissions · {{ f.leads_count }} leads<span v-if="f.has_hmac_secret"> · HMAC required</span></div>
                        <div class="mt-1 break-all font-mono text-xs text-slate-500">POST {{ f.endpoint }}</div>
                    </div>
                    <div class="flex flex-wrap gap-3 text-xs">
                        <button type="button" class="text-teal-800 underline" @click="openSnippet = openSnippet === f.id ? null : f.id">Embed code</button>
                        <button type="button" class="text-teal-800 underline" @click="edit(f)">Edit</button>
                        <button type="button" class="text-slate-600 underline" @click="regenerate(f)">Regenerate key</button>
                        <button type="button" class="text-rose-700 underline" @click="archive(f)">Archive</button>
                    </div>
                </div>
                <div v-if="openSnippet === f.id" class="mt-3">
                    <p v-if="f.has_hmac_secret" class="mb-2 rounded bg-amber-50 px-2 py-1 text-xs text-amber-800">This form requires an HMAC signature (header <code>X-Lead-Signature: sha256=&lt;hmac of raw body&gt;</code>). Post from your server, not the browser snippet.</p>
                    <pre class="max-h-72 overflow-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">{{ f.snippet }}</pre>
                    <button type="button" class="mt-2 rounded-md border border-slate-200 px-3 py-1 text-xs" @click="copy(f.snippet)">Copy</button>
                </div>
            </div>
            <p v-if="!forms.length" class="text-sm text-slate-500">No capture forms yet.</p>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-700">Meta Lead Ads</h2>
            <div class="mt-2 rounded-lg px-3 py-2 text-sm" :class="metaOperational ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'">
                <template v-if="metaOperational">Webhook secret configured and a connected Meta token is available. Leads are fetched from the Graph API only for mapped forms.</template>
                <template v-else>Permission Required — Meta lead intake is not operational. It needs <code>META_WEBHOOK_SECRET</code>, a verify token, and a connected Meta integration with the <code>leads_retrieval</code> permission. Webhooks received until then are logged as “permission required”; no leads are invented.</template>
            </div>
            <dl class="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-3">
                <div><dt class="text-slate-400">Webhook URL</dt><dd class="break-all font-mono">{{ meta.webhook_url }}</dd></div>
                <div><dt class="text-slate-400">App secret</dt><dd>{{ meta.webhook_secret_configured ? 'Configured' : 'Missing' }}</dd></div>
                <div><dt class="text-slate-400">Verify token</dt><dd>{{ meta.verify_token_configured ? 'Configured' : 'Missing' }}</dd></div>
            </dl>
            <ul v-if="meta.connections.length" class="mt-2 text-xs text-slate-600">
                <li v-for="c in meta.connections" :key="c.id">{{ c.name || 'Meta connection' }} · {{ c.status }} · {{ c.operational ? 'token available' : 'not operational' }}</li>
            </ul>

            <h3 class="mb-2 mt-5 text-xs font-semibold uppercase text-slate-400">Form mappings</h3>
            <form class="grid gap-2 sm:grid-cols-3 lg:grid-cols-6" @submit.prevent="saveMapping">
                <input v-model="mapping.form_id" placeholder="Meta form ID" class="rounded-lg border-slate-200 text-sm" />
                <input v-model="mapping.form_name" placeholder="Form name" class="rounded-lg border-slate-200 text-sm" />
                <select v-model="mapping.lead_category" class="rounded-lg border-slate-200 text-sm">
                    <option v-for="c in options.categories" :key="c" :value="c">{{ label(c) }}</option>
                </select>
                <select v-if="mapping.lead_category === 'client_campaign'" v-model="mapping.client_id" class="rounded-lg border-slate-200 text-sm">
                    <option value="">Client…</option>
                    <option v-for="c in options.clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
                </select>
                <select v-model="mapping.campaign_id" class="rounded-lg border-slate-200 text-sm">
                    <option value="">No campaign</option>
                    <option v-for="c in mappingCampaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <select v-model="mapping.integration_connection_id" class="rounded-lg border-slate-200 text-sm">
                    <option value="">Auto-select connection</option>
                    <option v-for="c in meta.connections" :key="c.id" :value="c.id">{{ c.name || `Connection #${c.id}` }}</option>
                </select>
                <div class="flex items-center justify-between sm:col-span-3 lg:col-span-6">
                    <InputError :message="mapping.errors.form_id || mapping.errors.client_id || mapping.errors.campaign_id" />
                    <button type="submit" :disabled="mapping.processing" class="rounded-lg bg-slate-800 px-3 py-2 text-sm text-white">Add mapping</button>
                </div>
            </form>
            <ul class="mt-3 divide-y divide-slate-100 text-sm">
                <li v-for="m in mappings" :key="m.id" class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <div>
                        <span class="font-mono text-xs">{{ m.form_id }}</span> <span v-if="m.form_name">· {{ m.form_name }}</span>
                        <div class="text-xs text-slate-500">{{ label(m.lead_category) }}<span v-if="m.client"> · {{ m.client.company_name }}</span><span v-if="m.campaign"> · {{ m.campaign.name }}</span> · {{ m.is_active ? 'active' : 'inactive' }}</div>
                    </div>
                    <div class="flex gap-3 text-xs">
                        <button type="button" class="text-teal-800 underline" @click="toggleMapping(m)">{{ m.is_active ? 'Disable' : 'Enable' }}</button>
                        <button type="button" class="text-rose-700 underline" @click="removeMapping(m)">Remove</button>
                    </div>
                </li>
                <li v-if="!mappings.length" class="py-2 text-slate-500">No Meta forms mapped.</li>
            </ul>
        </section>
    </AuthenticatedLayout>
</template>
