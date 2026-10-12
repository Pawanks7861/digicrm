<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import LeadsNav from '@/Components/Leads/LeadsNav.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ lead: Object, options: Object, defaults: Object });
const editing = computed(() => !!props.lead);

const form = useForm({
    lead_category: props.lead?.lead_category || props.defaults?.lead_category || 'agency_sales',
    client_id: props.lead?.client_id || props.defaults?.client_id || '',
    name: props.lead?.name || '',
    company: props.lead?.company || '',
    email: props.lead?.email || '',
    mobile: props.lead?.mobile || '',
    alternate_mobile: props.lead?.alternate_mobile || '',
    industry: props.lead?.industry || '',
    city: props.lead?.city || '',
    state: props.lead?.state || '',
    country: props.lead?.country || '',
    interested_service: props.lead?.interested_service || '',
    requirement: props.lead?.requirement || '',
    estimated_budget: props.lead?.estimated_budget || '',
    currency: props.lead?.currency || 'INR',
    priority: props.lead?.priority || 'normal',
    lead_source_id: props.lead?.lead_source_id || '',
    source_detail: props.lead?.source_detail || '',
    campaign_id: props.lead?.campaign_id || '',
    assigned_to: '',
    utm_source: '',
    utm_medium: '',
    utm_campaign: '',
    consent_given: false,
});

const campaigns = computed(() => {
    if (form.lead_category === 'client_campaign') {
        return props.options.campaigns.filter((c) => String(c.client_id) === String(form.client_id));
    }
    return props.options.campaigns;
});

const submit = () => {
    const transform = (data) => Object.fromEntries(Object.entries(data).map(([k, v]) => [k, v === '' ? null : v]));
    if (editing.value) {
        form.transform(transform).put(route('leads.update', props.lead.id));
    } else {
        form.transform(transform).post(route('leads.store'));
    }
};
const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head :title="editing ? 'Edit Lead' : 'New Lead'" />
    <AuthenticatedLayout>
        <div class="mb-4">
            <h1 class="text-2xl font-semibold tracking-tight">{{ editing ? `Edit ${lead.lead_number}` : 'New Lead' }}</h1>
            <p class="mt-1 text-sm text-slate-500">First-touch attribution is locked once set; later changes are recorded as touchpoints.</p>
        </div>
        <LeadsNav />

        <form class="space-y-6" @submit.prevent="submit">
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Category</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-xs font-medium text-slate-500">Lead category</label>
                        <select v-model="form.lead_category" :disabled="editing" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                            <option v-for="c in options.categories" :key="c" :value="c">{{ label(c) }}</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-400">Agency sales leads are the agency's own prospects. Client campaign leads belong to a client and can never become agency clients.</p>
                        <InputError :message="form.errors.lead_category" />
                    </div>
                    <div v-if="form.lead_category === 'client_campaign'">
                        <label class="text-xs font-medium text-slate-500">Owning client</label>
                        <select v-model="form.client_id" :disabled="editing" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                            <option value="">Select client…</option>
                            <option v-for="c in options.clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
                        </select>
                        <InputError :message="form.errors.client_id" />
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Contact</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="field in ['name', 'company', 'email', 'mobile', 'alternate_mobile', 'industry', 'city', 'state', 'country']" :key="field">
                        <label class="text-xs font-medium capitalize text-slate-500">{{ label(field) }}</label>
                        <input v-model="form[field]" :type="field === 'email' ? 'email' : 'text'" class="mt-1 w-full rounded-lg border-slate-200 text-sm" />
                        <InputError :message="form.errors[field]" />
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Requirement</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label class="text-xs font-medium text-slate-500">Interested service</label>
                        <input v-model="form.interested_service" class="mt-1 w-full rounded-lg border-slate-200 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-500">Estimated budget</label>
                        <input v-model="form.estimated_budget" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border-slate-200 text-sm" />
                        <InputError :message="form.errors.estimated_budget" />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-500">Currency</label>
                        <input v-model="form.currency" maxlength="3" class="mt-1 w-full rounded-lg border-slate-200 text-sm uppercase" />
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <label class="text-xs font-medium text-slate-500">Requirement</label>
                        <textarea v-model="form.requirement" rows="3" class="mt-1 w-full rounded-lg border-slate-200 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-500">Priority</label>
                        <select v-model="form.priority" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                            <option v-for="p in options.priorities" :key="p" :value="p">{{ label(p) }}</option>
                        </select>
                    </div>
                    <div v-if="!editing">
                        <label class="text-xs font-medium text-slate-500">Assign to</label>
                        <select v-model="form.assigned_to" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                            <option value="">Use assignment rules</option>
                            <option v-for="u in options.users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                        <InputError :message="form.errors.assigned_to" />
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Attribution</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="text-xs font-medium text-slate-500">Source</label>
                        <select v-model="form.lead_source_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                            <option value="">{{ editing ? 'Unchanged' : 'Manual entry' }}</option>
                            <option v-for="s in options.sources" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-500">Source detail</label>
                        <input v-model="form.source_detail" class="mt-1 w-full rounded-lg border-slate-200 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-500">Campaign</label>
                        <select v-model="form.campaign_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                            <option value="">None</option>
                            <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <InputError :message="form.errors.campaign_id" />
                    </div>
                    <template v-if="!editing">
                        <div v-for="field in ['utm_source', 'utm_medium', 'utm_campaign']" :key="field">
                            <label class="text-xs font-medium text-slate-500">{{ field }}</label>
                            <input v-model="form[field]" class="mt-1 w-full rounded-lg border-slate-200 text-sm" />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-600 sm:col-span-2">
                            <input v-model="form.consent_given" type="checkbox" class="rounded border-slate-300" /> Contact consent recorded
                        </label>
                    </template>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <Link :href="editing ? route('leads.show', lead.id) : route('leads.index')" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm">Cancel</Link>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-50">{{ editing ? 'Save changes' : 'Create lead' }}</button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
