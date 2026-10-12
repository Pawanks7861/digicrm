<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ health: Object });
</script>

<template>
    <Head title="Notification Health" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('settings.notifications.email')" class="text-sm text-teal-700 hover:underline">← Notification settings</Link>
            <h1 class="mt-3 text-2xl font-semibold">Notification Health</h1>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border bg-white p-5 text-sm">
                <p>In-app: <strong>{{ health.in_app_enabled ? 'On' : 'Off' }}</strong></p>
                <p class="mt-1">Email: <strong>{{ health.email_enabled ? 'On' : 'Off' }}</strong></p>
                <p class="mt-1">Push: <strong>{{ health.push_enabled ? 'On' : 'Off' }}</strong></p>
                <p class="mt-1">VAPID: <strong>{{ health.has_vapid ? 'Configured' : 'Missing' }}</strong></p>
                <p class="mt-1">From: {{ health.mail_from || '—' }}</p>
            </div>
            <div class="rounded-xl border bg-white p-5 text-sm">
                <p>Sent (24h): <strong>{{ health.sent_24h }}</strong></p>
                <p class="mt-1">Failed (24h): <strong :class="health.failed_24h ? 'text-rose-600' : ''">{{ health.failed_24h }}</strong></p>
                <p class="mt-1">Last test email: {{ health.last_test_email_at || 'Never' }}</p>
                <p class="mt-1">Last test push: {{ health.last_test_push_at || 'Never' }}</p>
                <p class="mt-1">Last scheduler run: {{ health.last_scheduler_run_at || 'Never' }}</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
