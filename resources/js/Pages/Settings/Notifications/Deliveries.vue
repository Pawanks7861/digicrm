<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({ deliveries: Object, filters: Object, canRetry: Boolean });
</script>

<template>
    <Head title="Delivery Logs" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('settings.notifications.email')" class="text-sm text-teal-700 hover:underline">← Notification settings</Link>
            <h1 class="mt-3 text-2xl font-semibold">Delivery Logs</h1>
        </div>
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Channel</th>
                        <th class="px-4 py-3">Event</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="d in deliveries.data" :key="d.id">
                        <td class="px-4 py-3 whitespace-nowrap">{{ d.created_at }}</td>
                        <td class="px-4 py-3">{{ d.user?.name || '—' }}</td>
                        <td class="px-4 py-3">{{ d.channel }}</td>
                        <td class="px-4 py-3">{{ d.event_key }}</td>
                        <td class="px-4 py-3">
                            <span :class="d.status === 'failed' ? 'text-rose-600' : 'text-slate-700'">{{ d.status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <button
                                v-if="canRetry && d.status === 'failed'"
                                type="button"
                                class="text-teal-700 hover:underline"
                                @click="router.post(route('settings.notifications.deliveries.retry', d.id))"
                            >Retry</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!deliveries.data?.length" class="p-4 text-sm text-slate-500">No deliveries logged yet.</p>
        </div>
    </AuthenticatedLayout>
</template>
