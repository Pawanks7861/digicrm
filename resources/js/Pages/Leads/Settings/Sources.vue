<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import LeadsNav from '@/Components/Leads/LeadsNav.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

defineProps({ sources: Array, lostReasons: Array, channels: Array });

const sourceForm = useForm({ name: '', channel: 'other', lead_category: '' });
const addSource = () => sourceForm.transform((d) => ({ ...d, lead_category: d.lead_category || null })).post(route('leads.settings.sources.store'), { preserveScroll: true, onSuccess: () => sourceForm.reset() });
const toggleSource = (s) => router.put(route('leads.settings.sources.update', s.id), { is_active: !s.is_active }, { preserveScroll: true });

const reasonForm = useForm({ name: '' });
const addReason = () => reasonForm.post(route('leads.settings.lost-reasons.store'), { preserveScroll: true, onSuccess: () => reasonForm.reset() });
const toggleReason = (r) => router.put(route('leads.settings.lost-reasons.update', r.id), { is_active: !r.is_active }, { preserveScroll: true });
const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head title="Lead Sources" />
    <AuthenticatedLayout>
        <div class="mb-4">
            <h1 class="text-2xl font-semibold tracking-tight">Lead sources & lost reasons</h1>
            <p class="mt-1 text-sm text-slate-500">System sources cannot be deleted; deactivate a source to hide it from new leads.</p>
        </div>
        <LeadsNav />
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Sources</h2>
                <form class="mb-4 grid gap-2 sm:grid-cols-4" @submit.prevent="addSource">
                    <input v-model="sourceForm.name" placeholder="New source" class="rounded-lg border-slate-200 text-sm sm:col-span-2" />
                    <select v-model="sourceForm.channel" class="rounded-lg border-slate-200 text-sm">
                        <option v-for="c in channels" :key="c" :value="c">{{ c }}</option>
                    </select>
                    <button type="submit" class="rounded-lg bg-teal-700 px-3 py-2 text-sm text-white">Add</button>
                    <InputError class="sm:col-span-4" :message="sourceForm.errors.name" />
                </form>
                <ul class="divide-y divide-slate-100 text-sm">
                    <li v-for="s in sources" :key="s.id" class="flex items-center justify-between gap-2 py-2">
                        <div>
                            <span class="font-medium" :class="!s.is_active && 'text-slate-400 line-through'">{{ s.name }}</span>
                            <span class="ml-2 text-xs text-slate-400">{{ s.code }} · {{ s.channel }}<span v-if="s.lead_category"> · {{ label(s.lead_category) }}</span> · {{ s.leads_count }} leads</span>
                            <span v-if="s.is_system" class="ml-1 rounded bg-slate-100 px-1.5 text-xs text-slate-500">system</span>
                        </div>
                        <button type="button" class="text-xs text-teal-800 underline" @click="toggleSource(s)">{{ s.is_active ? 'Deactivate' : 'Activate' }}</button>
                    </li>
                </ul>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Lost reasons</h2>
                <form class="mb-4 flex gap-2" @submit.prevent="addReason">
                    <input v-model="reasonForm.name" placeholder="New lost reason" class="flex-1 rounded-lg border-slate-200 text-sm" />
                    <button type="submit" class="rounded-lg bg-teal-700 px-3 py-2 text-sm text-white">Add</button>
                </form>
                <InputError :message="reasonForm.errors.name" />
                <ul class="divide-y divide-slate-100 text-sm">
                    <li v-for="r in lostReasons" :key="r.id" class="flex items-center justify-between py-2">
                        <span :class="!r.is_active && 'text-slate-400 line-through'">{{ r.name }}</span>
                        <button type="button" class="text-xs text-teal-800 underline" @click="toggleReason(r)">{{ r.is_active ? 'Deactivate' : 'Activate' }}</button>
                    </li>
                </ul>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
