<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({ settings: Object, canManage: Boolean });
const form = useForm({
    email_enabled: props.settings.email_enabled,
    mail_from_name: props.settings.mail_from_name || '',
    mail_from_address: props.settings.mail_from_address || '',
    smtp_host: props.settings.smtp_host || '',
    smtp_port: props.settings.smtp_port || 587,
    smtp_encryption: props.settings.smtp_encryption || 'tls',
    smtp_username: props.settings.smtp_username || '',
    smtp_password: '',
});
</script>

<template>
    <Head title="Email Notification Settings" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <div class="flex flex-wrap gap-3 text-sm">
                <Link :href="route('settings.notifications.health')" class="text-teal-700 hover:underline">Health</Link>
                <Link :href="route('settings.notifications.defaults')" class="text-teal-700 hover:underline">Defaults</Link>
                <Link :href="route('settings.notifications.push')" class="text-teal-700 hover:underline">Push</Link>
                <Link :href="route('settings.notifications.templates')" class="text-teal-700 hover:underline">Templates</Link>
                <Link :href="route('settings.notifications.deliveries')" class="text-teal-700 hover:underline">Delivery logs</Link>
            </div>
            <h1 class="mt-3 text-2xl font-semibold">Email Settings</h1>
            <p class="mt-1 text-sm text-slate-500">SMTP credentials are never shown after save.</p>
        </div>

        <form v-if="canManage" class="max-w-xl space-y-4 rounded-xl border bg-white p-5" @submit.prevent="form.put(route('settings.notifications.email.update'))">
            <label class="flex items-center gap-2 text-sm"><input v-model="form.email_enabled" type="checkbox" class="rounded border-slate-300" /> Email channel enabled</label>
            <div><InputLabel value="From name" /><TextInput v-model="form.mail_from_name" class="mt-1 w-full" /><InputError :message="form.errors.mail_from_name" /></div>
            <div><InputLabel value="From address" /><TextInput v-model="form.mail_from_address" class="mt-1 w-full" /><InputError :message="form.errors.mail_from_address" /></div>
            <div><InputLabel value="SMTP host" /><TextInput v-model="form.smtp_host" class="mt-1 w-full" /></div>
            <div class="grid grid-cols-2 gap-3">
                <div><InputLabel value="Port" /><TextInput v-model="form.smtp_port" type="number" class="mt-1 w-full" /></div>
                <div><InputLabel value="Encryption" /><TextInput v-model="form.smtp_encryption" class="mt-1 w-full" /></div>
            </div>
            <div><InputLabel value="Username" /><TextInput v-model="form.smtp_username" class="mt-1 w-full" /></div>
            <div>
                <InputLabel :value="settings.smtp_password_set ? 'SMTP password (leave blank to keep)' : 'SMTP password'" />
                <TextInput v-model="form.smtp_password" type="password" class="mt-1 w-full" autocomplete="new-password" />
            </div>
            <div class="flex gap-2">
                <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
                <button type="button" class="rounded-lg border px-3 py-2 text-sm" @click="router.post(route('settings.notifications.email.test'))">Send test</button>
            </div>
        </form>
        <p v-else class="text-sm text-slate-500">You do not have permission to manage email settings.</p>
    </AuthenticatedLayout>
</template>
