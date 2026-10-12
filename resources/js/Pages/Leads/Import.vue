<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import LeadsNav from '@/Components/Leads/LeadsNav.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, ref } from 'vue';

const props = defineProps({ clients: Array, campaigns: Array, sources: Array, users: Array, fields: Array, maxRows: Number, result: Object });

const opts = reactive({ lead_category: 'agency_sales', client_id: '', campaign_id: '', lead_source_id: '', assigned_to: '' });
const file = ref(null);
const preview = ref(null);
const errors = ref({});
const busy = ref(false);

const campaigns = computed(() => (opts.lead_category === 'client_campaign'
    ? props.campaigns.filter((c) => String(c.client_id) === String(opts.client_id))
    : props.campaigns));

const runPreview = async () => {
    errors.value = {};
    preview.value = null;
    if (!file.value) {
        errors.value = { file: 'Choose a CSV file.' };
        return;
    }
    const data = new FormData();
    Object.entries(opts).forEach(([k, v]) => { if (v !== '' && v !== null) data.append(k, v); });
    data.append('file', file.value);
    busy.value = true;
    try {
        const res = await axios.post(route('leads.import.preview'), data, { headers: { Accept: 'application/json' } });
        preview.value = res.data;
    } catch (e) {
        const errs = e.response?.data?.errors || {};
        errors.value = Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
        if (!Object.keys(errors.value).length) errors.value = { file: e.response?.data?.message || 'Preview failed.' };
    } finally {
        busy.value = false;
    }
};

const runImport = () => {
    busy.value = true;
    router.post(route('leads.import.store'), { token: preview.value.token }, {
        onFinish: () => { busy.value = false; },
        onSuccess: () => { preview.value = null; file.value = null; },
    });
};
const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head title="Import Leads" />
    <AuthenticatedLayout>
        <div class="mb-4">
            <h1 class="text-2xl font-semibold tracking-tight">Import leads from CSV</h1>
            <p class="mt-1 text-sm text-slate-500">Preview first; nothing is created until you confirm. Up to {{ maxRows }} rows per file. Rows with an <code>external_id</code> are idempotent.</p>
        </div>
        <LeadsNav />

        <div v-if="result" class="mb-4 rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm">
            <div class="font-medium text-teal-900">Last import: {{ result.created }} created · {{ result.deduplicated }} already existed · {{ result.flagged_duplicates }} flagged as possible duplicates · {{ result.skipped }} skipped</div>
            <ul v-if="result.errors?.length" class="mt-2 list-disc pl-5 text-xs text-rose-700">
                <li v-for="e in result.errors.slice(0, 20)" :key="e.line">Line {{ e.line }}: {{ e.errors.join(', ') }}</li>
            </ul>
        </div>

        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="text-xs font-medium text-slate-500">Lead category</label>
                    <select v-model="opts.lead_category" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="agency_sales">Agency sales</option>
                        <option value="client_campaign">Client campaign</option>
                    </select>
                </div>
                <div v-if="opts.lead_category === 'client_campaign'">
                    <label class="text-xs font-medium text-slate-500">Owning client</label>
                    <select v-model="opts.client_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Select…</option>
                        <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
                    </select>
                    <InputError :message="errors.client_id" />
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-500">Campaign</label>
                    <select v-model="opts.campaign_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="">None</option>
                        <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <InputError :message="errors.campaign_id" />
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-500">Source</label>
                    <select v-model="opts.lead_source_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="">CSV import</option>
                        <option v-for="s in sources" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-500">Assign to</label>
                    <select v-model="opts.assigned_to" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Use assignment rules</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-500">CSV file</label>
                    <input type="file" accept=".csv,text/csv" class="mt-1 block w-full text-sm" @input="file = $event.target.files[0]" />
                    <InputError :message="errors.file || errors.lead_category" />
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-400">Recognised columns: {{ fields.join(', ') }} (plus common aliases like "Full name", "Phone", "Company name").</p>
            <div class="mt-4 flex justify-end">
                <button type="button" :disabled="busy" class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white disabled:opacity-50" @click="runPreview">Preview</button>
            </div>
        </section>

        <section v-if="preview" class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm">
                    <span class="font-medium">{{ preview.original_name }}</span>: {{ preview.total }} rows ·
                    <span class="text-emerald-700">{{ preview.valid }} valid</span> ·
                    <span class="text-rose-700">{{ preview.invalid }} invalid</span> ·
                    <span class="text-amber-700">{{ preview.possible_duplicates }} possible duplicates</span>
                </div>
                <button type="button" :disabled="busy || !preview.valid" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" @click="runImport">Import {{ preview.valid }} rows</button>
            </div>
            <p class="mb-2 text-xs text-slate-500">Mapped columns: {{ Object.values(preview.mapping).join(', ') }}</p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50 text-left font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Line</th>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Email</th>
                            <th class="px-3 py-2">Mobile</th>
                            <th class="px-3 py-2">Company</th>
                            <th class="px-3 py-2">Check</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="r in preview.rows" :key="r.line" :class="r.errors.length ? 'bg-rose-50' : r.duplicate_of || r.in_file_duplicate ? 'bg-amber-50' : ''">
                            <td class="px-3 py-2">{{ r.line }}</td>
                            <td class="px-3 py-2">{{ r.values.name }}</td>
                            <td class="px-3 py-2">{{ r.values.email }}</td>
                            <td class="px-3 py-2">{{ r.values.mobile }}</td>
                            <td class="px-3 py-2">{{ r.values.company }}</td>
                            <td class="px-3 py-2">
                                <span v-if="r.errors.length" class="text-rose-700">{{ r.errors.join(', ') }}</span>
                                <span v-else-if="r.duplicate_of" class="text-amber-700">Possible duplicate of {{ r.duplicate_of.lead_number }}</span>
                                <span v-else-if="r.in_file_duplicate" class="text-amber-700">Repeated in file</span>
                                <span v-else class="text-emerald-700">OK</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="preview.total > preview.rows.length" class="mt-2 text-xs text-slate-400">Showing the first {{ preview.rows.length }} rows.</p>
        </section>
    </AuthenticatedLayout>
</template>
