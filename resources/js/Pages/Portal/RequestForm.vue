<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ types: Array, platforms: Array, priorities: Array });
const label = (v) => (v || '').replaceAll('_', ' ');

const form = useForm({
    title: '',
    request_type: 'reel',
    description: '',
    platform: 'instagram',
    priority: 'normal',
    requested_due_date: '',
    quantity: 1,
    reference_url: '',
    attachments: [],
});

const submit = () => form.post(route('portal.requests.store'), { forceFormData: true });
</script>

<template>
    <Head title="New Request" />
    <PortalLayout>
        <Link :href="route('portal.requests')" class="text-sm text-teal-700 hover:underline">← Requests</Link>
        <h1 class="mt-3 text-2xl font-semibold">New Request</h1>
        <form class="mt-6 max-w-xl space-y-4 rounded-2xl border border-slate-200 bg-white p-5" @submit.prevent="submit">
            <div>
                <InputLabel value="Title" />
                <TextInput v-model="form.title" class="mt-1 block w-full" required />
            </div>
            <div>
                <InputLabel value="Type" />
                <select v-model="form.request_type" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <option v-for="t in types" :key="t" :value="t">{{ label(t) }}</option>
                </select>
            </div>
            <div>
                <InputLabel value="Description" />
                <textarea v-model="form.description" rows="4" class="mt-1 w-full rounded-md border-slate-300 text-sm" required />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <InputLabel value="Platform" />
                    <select v-model="form.platform" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">—</option>
                        <option v-for="p in platforms" :key="p" :value="p">{{ label(p) }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Priority" />
                    <select v-model="form.priority" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option v-for="p in priorities" :key="p" :value="p">{{ label(p) }}</option>
                    </select>
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <InputLabel value="Requested due date" />
                    <input v-model="form.requested_due_date" type="date" class="mt-1 w-full rounded-md border-slate-300 text-sm" />
                </div>
                <div>
                    <InputLabel value="Quantity" />
                    <input v-model="form.quantity" type="number" min="1" step="1" class="mt-1 w-full rounded-md border-slate-300 text-sm" />
                </div>
            </div>
            <div>
                <InputLabel value="Reference link" />
                <TextInput v-model="form.reference_url" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel value="Attachments" />
                <input type="file" multiple class="mt-1 block w-full text-sm" @input="form.attachments = Array.from($event.target.files || [])" />
            </div>
            <PrimaryButton class="w-full justify-center py-3" :disabled="form.processing">Submit Request</PrimaryButton>
        </form>
    </PortalLayout>
</template>
