<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import LeadsNav from '@/Components/Leads/LeadsNav.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ rules: Array, strategies: Array, categories: Array, sources: Array, forms: Array, clients: Array, campaigns: Array, users: Array });

const blank = () => ({
    name: '', priority: 100, is_active: true, match_category: '', match_lead_source_id: '', match_capture_form_id: '',
    match_campaign_id: '', match_client_id: '', strategy: 'round_robin', user_id: '', user_pool: [],
});
const editingId = ref(null);
const form = useForm(blank());

const edit = (r) => {
    editingId.value = r.id;
    Object.assign(form, {
        name: r.name, priority: r.priority, is_active: r.is_active, match_category: r.match_category || '',
        match_lead_source_id: r.match_lead_source_id || '', match_capture_form_id: r.match_capture_form_id || '',
        match_campaign_id: r.match_campaign_id || '', match_client_id: r.match_client_id || '',
        strategy: r.strategy, user_id: r.user_id || '', user_pool: r.user_pool || [],
    });
};
const reset = () => { editingId.value = null; Object.assign(form, blank()); form.clearErrors(); };
const save = () => {
    const t = (d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v]));
    const opts = { preserveScroll: true, onSuccess: reset };
    if (editingId.value) form.transform(t).put(route('leads.settings.rules.update', editingId.value), opts);
    else form.transform(t).post(route('leads.settings.rules.store'), opts);
};
const remove = (r) => { if (confirm(`Delete rule "${r.name}"?`)) router.delete(route('leads.settings.rules.destroy', r.id), { preserveScroll: true }); };
const label = (v) => (v || '').replaceAll('_', ' ');
const userName = (id) => props.users.find((u) => u.id === Number(id))?.name || `#${id}`;
</script>

<template>
    <Head title="Assignment Rules" />
    <AuthenticatedLayout>
        <div class="mb-4">
            <h1 class="text-2xl font-semibold tracking-tight">Lead assignment rules</h1>
            <p class="mt-1 text-sm text-slate-500">Rules run in priority order (lowest first). The first rule whose conditions match and resolves an active user wins. Empty conditions match everything.</p>
        </div>
        <LeadsNav />
        <div class="grid gap-6 lg:grid-cols-5">
            <section class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-3">
                <ul class="divide-y divide-slate-100 text-sm">
                    <li v-for="r in rules" :key="r.id" class="flex flex-wrap items-start justify-between gap-2 py-3">
                        <div>
                            <div class="font-medium" :class="!r.is_active && 'text-slate-400'">{{ r.priority }} · {{ r.name }} <span v-if="!r.is_active" class="text-xs">(inactive)</span></div>
                            <div class="text-xs text-slate-500">
                                {{ [r.match_category && label(r.match_category), r.source?.name, r.capture_form?.name, r.client?.company_name, r.campaign?.name].filter(Boolean).join(' · ') || 'Matches all leads' }}
                            </div>
                            <div class="text-xs text-slate-500">
                                <span class="capitalize">{{ label(r.strategy) }}</span>
                                <span v-if="r.strategy === 'specific_user'"> → {{ r.user?.name }}</span>
                                <span v-if="r.strategy === 'round_robin'"> → {{ (r.user_pool || []).map(userName).join(', ') }}</span>
                            </div>
                        </div>
                        <div class="flex gap-3 text-xs">
                            <button type="button" class="text-teal-800 underline" @click="edit(r)">Edit</button>
                            <button type="button" class="text-rose-700 underline" @click="remove(r)">Delete</button>
                        </div>
                    </li>
                    <li v-if="!rules.length" class="py-3 text-slate-500">No rules yet. New leads without a matching rule stay unassigned (or are assigned to the creator when entered manually).</li>
                </ul>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">{{ editingId ? 'Edit rule' : 'New rule' }}</h2>
                <form class="space-y-2" @submit.prevent="save">
                    <input v-model="form.name" placeholder="Rule name" class="w-full rounded-lg border-slate-200 text-sm" />
                    <InputError :message="form.errors.name" />
                    <div class="grid grid-cols-2 gap-2">
                        <input v-model="form.priority" type="number" min="0" class="rounded-lg border-slate-200 text-sm" aria-label="Priority" />
                        <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" /> Active</label>
                    </div>
                    <div class="text-xs font-semibold uppercase text-slate-400">Conditions</div>
                    <select v-model="form.match_category" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Any category</option>
                        <option v-for="c in categories" :key="c" :value="c">{{ label(c) }}</option>
                    </select>
                    <select v-model="form.match_lead_source_id" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Any source</option>
                        <option v-for="s in sources" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <select v-model="form.match_capture_form_id" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Any capture form</option>
                        <option v-for="f in forms" :key="f.id" :value="f.id">{{ f.name }}</option>
                    </select>
                    <select v-model="form.match_client_id" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Any client</option>
                        <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
                    </select>
                    <select v-model="form.match_campaign_id" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Any campaign</option>
                        <option v-for="c in campaigns.filter((c) => !form.match_client_id || String(c.client_id) === String(form.match_client_id))" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <div class="text-xs font-semibold uppercase text-slate-400">Assign using</div>
                    <select v-model="form.strategy" class="w-full rounded-lg border-slate-200 text-sm">
                        <option v-for="s in strategies" :key="s" :value="s">{{ label(s) }}</option>
                    </select>
                    <select v-if="form.strategy === 'specific_user' || form.strategy === 'default_account_manager'" v-model="form.user_id" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">{{ form.strategy === 'default_account_manager' ? 'First active account manager' : 'Select user…' }}</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <div v-if="form.strategy === 'round_robin'" class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2">
                        <label v-for="u in users" :key="u.id" class="flex items-center gap-2 text-sm"><input v-model="form.user_pool" type="checkbox" :value="u.id" class="rounded border-slate-300" /> {{ u.name }}</label>
                    </div>
                    <p v-if="form.strategy === 'client_account_manager'" class="text-xs text-slate-500">Uses the owning client's account manager (client campaign leads only).</p>
                    <InputError :message="form.errors.user_id || form.errors.user_pool || form.errors.strategy" />
                    <div class="flex justify-end gap-2">
                        <button v-if="editingId" type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" @click="reset">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="rounded-lg bg-teal-700 px-3 py-2 text-sm text-white">{{ editingId ? 'Save rule' : 'Add rule' }}</button>
                    </div>
                </form>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
