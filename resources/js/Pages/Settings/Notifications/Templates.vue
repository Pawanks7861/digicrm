<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ templates: Array, canManage: Boolean });
const editing = ref(null);
const form = useForm({
    name: '', subject: '', heading: '', body: '', cta_label: '', footer: '', is_active: true,
});

const open = (t) => {
    editing.value = t.id;
    form.name = t.name;
    form.subject = t.subject;
    form.heading = t.heading || '';
    form.body = t.body;
    form.cta_label = t.cta_label || '';
    form.footer = t.footer || '';
    form.is_active = t.is_active;
};

const save = () => form.put(route('settings.notifications.templates.update', editing.value), {
    onSuccess: () => { editing.value = null; },
});
</script>

<template>
    <Head title="Notification Templates" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('settings.notifications.email')" class="text-sm text-teal-700 hover:underline">← Notification settings</Link>
            <h1 class="mt-3 text-2xl font-semibold">Templates</h1>
        </div>

        <div class="space-y-3">
            <div v-for="t in templates" :key="t.id" class="rounded-xl border bg-white p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="font-medium">{{ t.name }} <span class="text-xs text-slate-400">{{ t.key }}</span></p>
                        <p class="text-sm text-slate-500">{{ t.subject }}</p>
                    </div>
                    <div class="flex gap-2 text-sm">
                        <Link :href="route('settings.notifications.templates.preview', t.id)" class="text-teal-700 hover:underline">Preview</Link>
                        <button v-if="canManage" type="button" class="text-slate-700 hover:underline" @click="open(t)">Edit</button>
                    </div>
                </div>
                <form v-if="editing === t.id" class="mt-4 space-y-2 border-t pt-4" @submit.prevent="save">
                    <TextInput v-model="form.name" class="w-full" placeholder="Name" />
                    <TextInput v-model="form.subject" class="w-full" placeholder="Subject" />
                    <TextInput v-model="form.heading" class="w-full" placeholder="Heading" />
                    <textarea v-model="form.body" class="w-full rounded-md border-slate-300 text-sm" rows="4" />
                    <TextInput v-model="form.cta_label" class="w-full" placeholder="CTA" />
                    <TextInput v-model="form.footer" class="w-full" placeholder="Footer" />
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded" /> Active</label>
                    <PrimaryButton :disabled="form.processing">Save template</PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
