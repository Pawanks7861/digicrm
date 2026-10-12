<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    client: Object,
    statuses: Array,
    accountManagers: Array,
});

const form = useForm({
    company_name: props.client.company_name || '',
    contact_person: props.client.contact_person || '',
    email: props.client.email || '',
    mobile: props.client.mobile || '',
    alternate_mobile: props.client.alternate_mobile || '',
    website: props.client.website || '',
    industry: props.client.industry || '',
    address: props.client.address || '',
    city: props.client.city || '',
    state: props.client.state || '',
    country: props.client.country || 'India',
    gst_number: props.client.gst_number || '',
    pan: props.client.pan || '',
    social_urls: props.client.social_urls || {},
    account_manager_id: props.client.account_manager_id || '',
    start_date: props.client.start_date ? props.client.start_date.substring(0, 10) : '',
    renewal_date: props.client.renewal_date ? props.client.renewal_date.substring(0, 10) : '',
    status: props.client.status || 'prospect',
    notes: props.client.notes || '',
});

const submit = () => form.put(route('clients.update', props.client.id));
</script>

<template>
    <Head :title="`Edit ${client.company_name}`" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('clients.show', client.id)" class="text-sm text-teal-700 hover:underline">← Back to profile</Link>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">Edit Client</h1>
        </div>

        <form class="max-w-4xl space-y-6 rounded-xl border border-slate-200 bg-white p-6" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <InputLabel value="Company Name" />
                    <TextInput v-model="form.company_name" class="mt-1 block w-full" required />
                    <InputError class="mt-1" :message="form.errors.company_name" />
                </div>
                <div>
                    <InputLabel value="Contact Person" />
                    <TextInput v-model="form.contact_person" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel value="Email" />
                    <TextInput v-model="form.email" type="email" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel value="Mobile" />
                    <TextInput v-model="form.mobile" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel value="Alternate Mobile" />
                    <TextInput v-model="form.alternate_mobile" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel value="Website" />
                    <TextInput v-model="form.website" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel value="Industry" />
                    <TextInput v-model="form.industry" class="mt-1 block w-full" />
                </div>
                <div class="sm:col-span-2">
                    <InputLabel value="Address" />
                    <textarea v-model="form.address" class="mt-1 w-full rounded-md border-slate-300 text-sm" rows="2" />
                </div>
                <div>
                    <InputLabel value="City" />
                    <TextInput v-model="form.city" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel value="State" />
                    <TextInput v-model="form.state" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel value="Country" />
                    <TextInput v-model="form.country" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel value="GST Number" />
                    <TextInput v-model="form.gst_number" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel value="PAN" />
                    <TextInput v-model="form.pan" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel value="Account Manager" />
                    <select v-model="form.account_manager_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Unassigned</option>
                        <option v-for="am in accountManagers" :key="am.id" :value="am.id">{{ am.name }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Status" />
                    <select v-model="form.status" class="mt-1 w-full rounded-md border-slate-300 text-sm capitalize">
                        <option v-for="s in statuses" :key="s" :value="s">{{ s.replaceAll('_', ' ') }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Start Date" />
                    <TextInput v-model="form.start_date" type="date" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel value="Renewal Date" />
                    <TextInput v-model="form.renewal_date" type="date" class="mt-1 block w-full" />
                </div>
                <div class="sm:col-span-2">
                    <InputLabel value="Notes" />
                    <textarea v-model="form.notes" class="mt-1 w-full rounded-md border-slate-300 text-sm" rows="3" />
                </div>
            </div>

            <PrimaryButton :disabled="form.processing">Save Changes</PrimaryButton>
        </form>
    </AuthenticatedLayout>
</template>
