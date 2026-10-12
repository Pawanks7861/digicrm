<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({ notifications: Object });
const page = usePage();

const markAll = () => router.post(route('notifications.read-all'));
const markOne = (id) => router.post(route('notifications.read', id));

const playSound = (key) => {
    if (!page.props.auth?.user?.notification_sounds_enabled) return;
    const map = {
        follow_up: '/sounds/follow_up.wav',
        new_request: '/sounds/new_request.wav',
        approval: '/sounds/approval.wav',
        task: '/sounds/task.wav',
        finance: '/sounds/finance.wav',
        default: '/sounds/default.wav',
    };
    const src = map[key] || map.default;
    try {
        const audio = new Audio(src);
        audio.volume = 0.35;
        audio.play().catch(() => {});
    } catch (e) {}
};

onMounted(() => {
    const newest = props.notifications?.data?.[0];
    if (newest && !newest.read_at && newest.data?.sound) {
        playSound(newest.data.sound);
    }
});
</script>

<template>
    <Head title="Notifications" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Notifications</h1>
                <Link :href="route('profile.notifications')" class="text-sm text-teal-700 hover:underline">Preferences & devices</Link>
            </div>
            <button type="button" class="text-sm text-teal-700 hover:underline" @click="markAll">Mark all read</button>
        </div>
        <div v-if="notifications.data.length" class="space-y-2">
            <div
                v-for="n in notifications.data"
                :key="n.id"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm"
                :class="!n.read_at ? 'border-teal-200 bg-teal-50/40' : ''"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="font-medium">{{ n.data?.title }}</div>
                    <span v-if="n.data?.category" class="rounded bg-slate-100 px-2 py-0.5 text-[10px] uppercase text-slate-500">{{ n.data.category }}</span>
                </div>
                <p class="mt-1 text-slate-600">{{ n.data?.body }}</p>
                <div class="mt-2 flex gap-3 text-xs">
                    <span class="text-slate-400">{{ new Date(n.created_at).toLocaleString() }}</span>
                    <button v-if="!n.read_at" type="button" class="text-teal-700" @click="markOne(n.id)">Mark read</button>
                </div>
            </div>
        </div>
        <EmptyState v-else title="No new notifications." />
    </AuthenticatedLayout>
</template>
