<script setup>
import ClientPortalLayout from '@/Layouts/PortalLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    preferences: Array,
    settings: Object,
    pushDevices: Array,
    vapidPublicKey: String,
    pushEnabled: Boolean,
});

const form = useForm({
    preferences: props.preferences.map((p) => ({ ...p })),
    notifications_paused: props.settings.notifications_paused,
    notifications_paused_until: props.settings.notifications_paused_until
        ? props.settings.notifications_paused_until.slice(0, 16)
        : '',
    quiet_hours_enabled: props.settings.quiet_hours_enabled,
    quiet_hours_start: props.settings.quiet_hours_start ?? 22,
    quiet_hours_end: props.settings.quiet_hours_end ?? 7,
    notification_timezone: props.settings.notification_timezone || 'Asia/Kolkata',
    notification_sounds_enabled: props.settings.notification_sounds_enabled,
});

const pushBusy = ref(false);

const save = () => form.put(route('portal.notification-preferences.update'));

const urlBase64ToUint8Array = (base64String) => {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
};

const enablePush = async () => {
    pushBusy.value = true;
    try {
        const reg = await navigator.serviceWorker.register('/sw.js');
        await navigator.serviceWorker.ready;
        await Notification.requestPermission();
        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(props.vapidPublicKey),
        });
        const json = sub.toJSON();
        router.post(route('portal.push.subscriptions.store'), {
            endpoint: json.endpoint,
            keys: json.keys,
            contentEncoding: 'aesgcm',
        }, { preserveScroll: true });
    } finally {
        pushBusy.value = false;
    }
};
</script>

<template>
    <Head title="Notification Preferences" />
    <ClientPortalLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold">Notification Preferences</h1>
            <PrimaryButton :disabled="form.processing" @click="save">Save</PrimaryButton>
        </div>

        <div class="mb-4 space-y-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <label class="flex items-center gap-2"><input v-model="form.notifications_paused" type="checkbox" class="rounded" /> Pause notifications</label>
            <label class="flex items-center gap-2"><input v-model="form.quiet_hours_enabled" type="checkbox" class="rounded" /> Quiet hours</label>
            <div class="grid grid-cols-2 gap-2">
                <div><InputLabel value="Start" /><input v-model.number="form.quiet_hours_start" type="number" min="0" max="23" class="mt-1 w-full rounded-md border-slate-300 text-sm" /></div>
                <div><InputLabel value="End" /><input v-model.number="form.quiet_hours_end" type="number" min="0" max="23" class="mt-1 w-full rounded-md border-slate-300 text-sm" /></div>
            </div>
            <label class="flex items-center gap-2"><input v-model="form.notification_sounds_enabled" type="checkbox" class="rounded" /> Sounds</label>
        </div>

        <div class="space-y-2 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <div v-for="(row, idx) in form.preferences" :key="row.category" class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 py-2 last:border-0">
                <span class="font-medium capitalize">{{ row.category.replace('_', ' ') }}</span>
                <div class="flex gap-3">
                    <label class="flex items-center gap-1"><input v-model="form.preferences[idx].in_app_enabled" type="checkbox" class="rounded" /> In-app</label>
                    <label class="flex items-center gap-1"><input v-model="form.preferences[idx].email_enabled" type="checkbox" class="rounded" /> Email</label>
                    <label class="flex items-center gap-1"><input v-model="form.preferences[idx].push_enabled" type="checkbox" class="rounded" /> Push</label>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <PrimaryButton :disabled="pushBusy || !pushEnabled" @click="enablePush">Enable push on this device</PrimaryButton>
            <ul class="mt-3 divide-y">
                <li v-for="d in pushDevices" :key="d.id" class="flex justify-between py-2">
                    <span>{{ d.device_name || 'Browser' }}</span>
                    <button type="button" class="text-rose-600" @click="router.delete(route('portal.push.subscriptions.destroy', d.id))">Remove</button>
                </li>
            </ul>
        </div>
    </ClientPortalLayout>
</template>
