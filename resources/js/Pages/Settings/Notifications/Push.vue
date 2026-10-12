<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ settings: Object, canManage: Boolean });
const form = useForm({
    push_enabled: props.settings.push_enabled,
    vapid_subject: props.settings.vapid_subject || '',
    generate_keys: false,
});
</script>

<template>
    <Head title="Push Settings" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('settings.notifications.email')" class="text-sm text-teal-700 hover:underline">← Notification settings</Link>
            <h1 class="mt-3 text-2xl font-semibold">Web Push Settings</h1>
            <p class="mt-1 text-sm text-slate-500">Private VAPID key is never sent to the browser.</p>
        </div>

        <form v-if="canManage" class="max-w-xl space-y-4 rounded-xl border bg-white p-5" @submit.prevent="form.put(route('settings.notifications.push.update'))">
            <label class="flex items-center gap-2 text-sm"><input v-model="form.push_enabled" type="checkbox" class="rounded" /> Push enabled</label>
            <div><InputLabel value="VAPID subject (mailto:…)" /><TextInput v-model="form.vapid_subject" class="mt-1 w-full" /></div>
            <div class="rounded-lg bg-slate-50 p-3 text-sm">
                <p>Public key: <code class="break-all text-xs">{{ settings.vapid_public_key || 'Not generated' }}</code></p>
                <p class="mt-1">Private key: {{ settings.has_private_key ? 'Configured' : 'Missing' }}</p>
            </div>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.generate_keys" type="checkbox" class="rounded" /> Generate new VAPID key pair on save</label>
            <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
        </form>
    </AuthenticatedLayout>
</template>
