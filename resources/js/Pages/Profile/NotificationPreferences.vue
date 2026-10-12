<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
const pushMessage = ref('');

const save = () => form.put(route('profile.notifications.update'));

const urlBase64ToUint8Array = (base64String) => {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
};

const enablePush = async () => {
    pushBusy.value = true;
    pushMessage.value = '';
    try {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            pushMessage.value = 'Push is not supported in this browser.';
            return;
        }
        if (!props.vapidPublicKey) {
            pushMessage.value = 'VAPID public key is not configured.';
            return;
        }
        const reg = await navigator.serviceWorker.register('/sw.js');
        await navigator.serviceWorker.ready;
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            pushMessage.value = 'Notification permission denied.';
            return;
        }
        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(props.vapidPublicKey),
        });
        const json = sub.toJSON();
        router.post(route('push.subscriptions.store'), {
            endpoint: json.endpoint,
            keys: json.keys,
            contentEncoding: 'aesgcm',
        }, { preserveScroll: true });
    } catch (e) {
        pushMessage.value = e.message || 'Failed to enable push.';
    } finally {
        pushBusy.value = false;
    }
};
</script>

<template>
    <Head title="Notification Preferences" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Notification Preferences</h1>
                <p class="mt-1 text-sm text-slate-500">Choose channels, quiet hours, and push devices.</p>
            </div>
            <PrimaryButton :disabled="form.processing" @click="save">Save</PrimaryButton>
        </div>

        <div class="mb-6 grid gap-4 rounded-xl border bg-white p-5 md:grid-cols-2">
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.notifications_paused" type="checkbox" class="rounded border-slate-300" />
                Pause all notifications
            </label>
            <div>
                <InputLabel value="Pause until" />
                <input v-model="form.notifications_paused_until" type="datetime-local" class="mt-1 w-full rounded-md border-slate-300 text-sm" />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.quiet_hours_enabled" type="checkbox" class="rounded border-slate-300" />
                Quiet hours
            </label>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <InputLabel value="Start hour" />
                    <input v-model.number="form.quiet_hours_start" type="number" min="0" max="23" class="mt-1 w-full rounded-md border-slate-300 text-sm" />
                </div>
                <div>
                    <InputLabel value="End hour" />
                    <input v-model.number="form.quiet_hours_end" type="number" min="0" max="23" class="mt-1 w-full rounded-md border-slate-300 text-sm" />
                </div>
            </div>
            <div>
                <InputLabel value="Timezone" />
                <input v-model="form.notification_timezone" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm" />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.notification_sounds_enabled" type="checkbox" class="rounded border-slate-300" />
                In-app sounds
            </label>
        </div>

        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">In-app</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Push</th>
                        <th class="px-4 py-3">Sound</th>
                        <th class="px-4 py-3">Digest</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="(row, idx) in form.preferences" :key="row.category">
                        <td class="px-4 py-3 font-medium capitalize">{{ row.category.replace('_', ' ') }}</td>
                        <td class="px-4 py-3"><input v-model="form.preferences[idx].in_app_enabled" type="checkbox" class="rounded border-slate-300" /></td>
                        <td class="px-4 py-3"><input v-model="form.preferences[idx].email_enabled" type="checkbox" class="rounded border-slate-300" /></td>
                        <td class="px-4 py-3"><input v-model="form.preferences[idx].push_enabled" type="checkbox" class="rounded border-slate-300" /></td>
                        <td class="px-4 py-3"><input v-model="form.preferences[idx].sound_enabled" type="checkbox" class="rounded border-slate-300" /></td>
                        <td class="px-4 py-3">
                            <select v-model="form.preferences[idx].digest_mode" class="rounded-md border-slate-300 text-sm">
                                <option value="immediate">Immediate</option>
                                <option value="daily">Daily digest</option>
                                <option value="off">Off</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-8 rounded-xl border bg-white p-5">
            <h2 class="text-sm font-semibold">Push devices</h2>
            <p class="mt-1 text-sm text-slate-500">Enable browser push for closed-tab alerts when configured.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <PrimaryButton :disabled="pushBusy || !pushEnabled" @click="enablePush">Enable this device</PrimaryButton>
                <button type="button" class="rounded-lg border px-3 py-2 text-sm" @click="router.post(route('push.test'))">Send test push</button>
            </div>
            <p v-if="pushMessage" class="mt-2 text-sm text-rose-600">{{ pushMessage }}</p>
            <ul class="mt-4 divide-y text-sm">
                <li v-for="d in pushDevices" :key="d.id" class="flex items-center justify-between py-2">
                    <span>{{ d.device_name || 'Browser' }} · {{ d.is_active ? 'Active' : 'Inactive' }}</span>
                    <button type="button" class="text-rose-600" @click="router.delete(route('push.subscriptions.destroy', d.id))">Remove</button>
                </li>
                <li v-if="!pushDevices?.length" class="py-2 text-slate-500">No devices registered.</li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
