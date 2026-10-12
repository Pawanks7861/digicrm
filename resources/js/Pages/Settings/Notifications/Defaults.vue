<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ settings: Object, canManage: Boolean });
const form = useForm({
    in_app_enabled: props.settings.in_app_enabled,
    email_enabled: props.settings.email_enabled,
    push_enabled: props.settings.push_enabled,
    sounds_enabled_default: props.settings.sounds_enabled_default,
    quiet_hours_start: props.settings.quiet_hours_start,
    quiet_hours_end: props.settings.quiet_hours_end,
    default_timezone: props.settings.default_timezone || 'Asia/Kolkata',
    digest_enabled: props.settings.digest_enabled,
    digest_time: props.settings.digest_time || '08:00',
    invoice_reminder_offsets: (props.settings.invoice_reminder_offsets || []).join(','),
    renewal_reminder_offsets: (props.settings.renewal_reminder_offsets || []).join(','),
    task_reminder_offsets: (props.settings.task_reminder_offsets || []).join(','),
    followup_reminder_offsets: (props.settings.followup_reminder_offsets || []).join(','),
});

const parseOffsets = (s) => String(s || '').split(',').map((x) => parseInt(x.trim(), 10)).filter((n) => !Number.isNaN(n));

const submit = () => {
    form.transform((data) => ({
        ...data,
        invoice_reminder_offsets: parseOffsets(data.invoice_reminder_offsets),
        renewal_reminder_offsets: parseOffsets(data.renewal_reminder_offsets),
        task_reminder_offsets: parseOffsets(data.task_reminder_offsets),
        followup_reminder_offsets: parseOffsets(data.followup_reminder_offsets),
    })).put(route('settings.notifications.defaults.update'));
};
</script>

<template>
    <Head title="Notification Defaults" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('settings.notifications.email')" class="text-sm text-teal-700 hover:underline">← Notification settings</Link>
            <h1 class="mt-3 text-2xl font-semibold">Notification Defaults</h1>
        </div>
        <form v-if="canManage" class="max-w-xl space-y-4 rounded-xl border bg-white p-5" @submit.prevent="submit">
            <label class="flex items-center gap-2 text-sm"><input v-model="form.in_app_enabled" type="checkbox" class="rounded" /> In-app</label>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.email_enabled" type="checkbox" class="rounded" /> Email</label>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.push_enabled" type="checkbox" class="rounded" /> Push</label>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.sounds_enabled_default" type="checkbox" class="rounded" /> Sounds default</label>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.digest_enabled" type="checkbox" class="rounded" /> Digests</label>
            <div class="grid grid-cols-2 gap-3">
                <div><InputLabel value="Quiet start" /><TextInput v-model="form.quiet_hours_start" type="number" class="mt-1 w-full" /></div>
                <div><InputLabel value="Quiet end" /><TextInput v-model="form.quiet_hours_end" type="number" class="mt-1 w-full" /></div>
            </div>
            <div><InputLabel value="Timezone" /><TextInput v-model="form.default_timezone" class="mt-1 w-full" /></div>
            <div><InputLabel value="Digest time" /><TextInput v-model="form.digest_time" class="mt-1 w-full" /></div>
            <div><InputLabel value="Invoice offsets (days, comma)" /><TextInput v-model="form.invoice_reminder_offsets" class="mt-1 w-full" /></div>
            <div><InputLabel value="Renewal offsets" /><TextInput v-model="form.renewal_reminder_offsets" class="mt-1 w-full" /></div>
            <div><InputLabel value="Task offsets" /><TextInput v-model="form.task_reminder_offsets" class="mt-1 w-full" /></div>
            <div><InputLabel value="Follow-up offsets" /><TextInput v-model="form.followup_reminder_offsets" class="mt-1 w-full" /></div>
            <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
        </form>
    </AuthenticatedLayout>
</template>
