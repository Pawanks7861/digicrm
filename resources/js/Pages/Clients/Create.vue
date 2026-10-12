<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    statuses: Array,
    accountManagers: Array,
});

const form = useForm({
    company_name: '',
    contact_person: '',
    email: '',
    mobile: '',
    alternate_mobile: '',
    website: '',
    industry: '',
    address: '',
    city: '',
    state: '',
    country: 'India',
    gst_number: '',
    pan: '',
    social_urls: {
        instagram: '',
        facebook: '',
        linkedin: '',
        youtube: '',
        twitter: '',
    },
    account_manager_id: '',
    start_date: '',
    renewal_date: '',
    status: 'prospect',
    notes: '',
});

const submit = () => form.post(route('clients.store'));
</script>

<template>
    <Head title="New Client" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('clients.index')" class="text-sm text-teal-700 hover:underline">← Back to clients</Link>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">New Client</h1>
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
                    <TextInput v-model="form.website" class="mt-1 block w-full" placeholder="https://" />
                    <InputError class="mt-1" :message="form.errors.website" />
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

            <div class="flex items-center gap-3">
                <PrimaryButton :disabled="form.processing">Create Client</PrimaryButton>
                <Link :href="route('clients.index')" class="text-sm text-slate-500 hover:text-slate-700">Cancel</Link>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
