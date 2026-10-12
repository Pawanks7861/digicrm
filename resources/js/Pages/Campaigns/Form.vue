<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    campaign: Object,
    types: Array,
    statuses: Array,
    priorities: Array,
    clients: Array,
    managers: Array,
    prefill_client_id: Number,
    canFinance: Boolean,
});

const isEdit = !!props.campaign;
const form = useForm({
    client_id: props.campaign?.client_id || props.prefill_client_id || '',
    name: props.campaign?.name || '',
    campaign_type: props.campaign?.campaign_type || 'social_media',
    objective: props.campaign?.objective || '',
    description: props.campaign?.description || '',
    start_date: props.campaign?.start_date?.substring?.(0, 10) || props.campaign?.start_date || '',
    end_date: props.campaign?.end_date?.substring?.(0, 10) || props.campaign?.end_date || '',
    budget: props.campaign?.budget ?? '',
    internal_cost: props.campaign?.internal_cost ?? '',
    client_charge: props.campaign?.client_charge ?? '',
    currency: props.campaign?.currency || 'INR',
    campaign_manager_id: props.campaign?.campaign_manager_id || '',
    status: props.campaign?.status || 'draft',
    priority: props.campaign?.priority || 'normal',
});

const submit = () => {
    if (isEdit) form.put(route('campaigns.update', props.campaign.id));
    else form.post(route('campaigns.store'));
};

const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head :title="isEdit ? 'Edit Campaign' : 'New Campaign'" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('campaigns.index')" class="text-sm text-teal-700 hover:underline">← Campaigns</Link>
            <h1 class="mt-2 text-2xl font-semibold">{{ isEdit ? 'Edit Campaign' : 'New Campaign' }}</h1>
        </div>
        <form class="max-w-3xl space-y-4 rounded-xl border border-slate-200 bg-white p-6" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <InputLabel value="Name" />
                    <TextInput v-model="form.name" class="mt-1 block w-full" required />
                </div>
                <div>
                    <InputLabel value="Client" />
                    <select v-model="form.client_id" class="mt-1 w-full rounded-md border-slate-300 text-sm" :disabled="isEdit" required>
                        <option value="">Select…</option>
                        <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Type" />
                    <select v-model="form.campaign_type" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option v-for="t in types" :key="t" :value="t">{{ label(t) }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Manager" />
                    <select v-model="form.campaign_manager_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Unassigned</option>
                        <option v-for="m in managers" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Status" />
                    <select v-model="form.status" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option v-for="s in statuses" :key="s" :value="s">{{ label(s) }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Priority" />
                    <select v-model="form.priority" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option v-for="p in priorities" :key="p" :value="p">{{ label(p) }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Start Date" />
                    <TextInput v-model="form.start_date" type="date" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel value="End Date" />
                    <TextInput v-model="form.end_date" type="date" class="mt-1 block w-full" />
                </div>
                <template v-if="canFinance">
                    <div>
                        <InputLabel value="Budget" />
                        <TextInput v-model="form.budget" type="number" step="0.01" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel value="Client Charge" />
                        <TextInput v-model="form.client_charge" type="number" step="0.01" class="mt-1 block w-full" />
                    </div>
                </template>
                <div class="sm:col-span-2">
                    <InputLabel value="Objective" />
                    <textarea v-model="form.objective" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm" />
                </div>
            </div>
            <PrimaryButton :disabled="form.processing">{{ isEdit ? 'Save' : 'Create Campaign' }}</PrimaryButton>
        </form>
    </AuthenticatedLayout>
</template>
