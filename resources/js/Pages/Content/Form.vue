<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    item: Object,
    options: Object,
    prefill: Object,
});

const isEdit = computed(() => !!props.item?.id);

const form = useForm({
    client_id: props.item?.client_id || props.prefill?.client_id || '',
    campaign_id: props.item?.campaign_id || props.prefill?.campaign_id || '',
    campaign_deliverable_id: props.item?.campaign_deliverable_id || props.prefill?.campaign_deliverable_id || '',
    title: props.item?.title || '',
    content_type: props.item?.content_type || 'static_post',
    platform: props.item?.platform || 'instagram',
    caption: props.item?.caption || '',
    description: props.item?.description || '',
    hashtags: props.item?.hashtags || '',
    cta: props.item?.cta || '',
    target_url: props.item?.target_url || '',
});

const campaigns = computed(() =>
    (props.options.campaigns || []).filter((c) => !form.client_id || c.client_id === Number(form.client_id))
);

const label = (v) => (v || '').replaceAll('_', ' ');

const submit = () => {
    if (isEdit.value) {
        form.put(route('content.update', props.item.id));
    } else {
        form.post(route('content.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Content' : 'Create Content'" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('content.index')" class="text-sm text-teal-700 hover:underline">← Content</Link>
            <h1 class="mt-2 text-2xl font-semibold">{{ isEdit ? 'Edit Content' : 'Create Content' }}</h1>
        </div>

        <form class="max-w-2xl space-y-4 rounded-xl border border-slate-200 bg-white p-6" @submit.prevent="submit">
            <div>
                <InputLabel value="Client" />
                <select v-model="form.client_id" class="mt-1 w-full rounded-md border-slate-300 text-sm" required>
                    <option value="">Select client</option>
                    <option v-for="c in options.clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
                </select>
                <InputError :message="form.errors.client_id" />
            </div>
            <div>
                <InputLabel value="Campaign" />
                <select v-model="form.campaign_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <option value="">Optional</option>
                    <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
                </select>
            </div>
            <div>
                <InputLabel value="Title" />
                <TextInput v-model="form.title" class="mt-1 block w-full" required />
                <InputError :message="form.errors.title" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel value="Type" />
                    <select v-model="form.content_type" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option v-for="t in options.content_types" :key="t" :value="t">{{ label(t) }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Platform" />
                    <select v-model="form.platform" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">—</option>
                        <option v-for="p in options.platforms" :key="p" :value="p">{{ label(p) }}</option>
                    </select>
                </div>
            </div>
            <div>
                <InputLabel value="Caption" />
                <textarea v-model="form.caption" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm" />
            </div>
            <div>
                <InputLabel value="Hashtags" />
                <TextInput v-model="form.hashtags" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel value="CTA" />
                <TextInput v-model="form.cta" class="mt-1 block w-full" />
            </div>
            <PrimaryButton :disabled="form.processing">{{ isEdit ? 'Save' : 'Create' }}</PrimaryButton>
        </form>
    </AuthenticatedLayout>
</template>
