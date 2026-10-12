<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps({ notifications: Object });
const markAll = () => router.post(route('portal.notifications.read-all'));
</script>

<template>
    <Head title="Notifications" />
    <PortalLayout>
        <div class="mb-4 flex justify-between">
            <h1 class="text-2xl font-semibold">Notifications</h1>
            <button type="button" class="text-sm text-teal-700" @click="markAll">Mark all read</button>
        </div>
        <div v-if="notifications.data.length" class="space-y-2">
            <div v-for="n in notifications.data" :key="n.id" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm" :class="!n.read_at && 'bg-teal-50/50'">
                <div class="font-medium">{{ n.data?.title }}</div>
                <p class="mt-1 text-slate-600">{{ n.data?.body }}</p>
            </div>
        </div>
        <EmptyState v-else title="No new notifications." />
    </PortalLayout>
</template>
