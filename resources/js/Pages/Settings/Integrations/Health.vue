<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ connection: Object, result: Object });
</script>

<template>
    <Head title="Integration Health" />
    <AuthenticatedLayout>
        <Link :href="route('integrations.show', connection.id)" class="text-sm text-teal-700 hover:underline">← Back</Link>
        <h1 class="mt-2 text-2xl font-semibold">{{ connection.provider }} health</h1>
        <div class="mt-4 rounded-xl border bg-white p-4 text-sm">
            <p><span class="font-medium">Status:</span> {{ result.status }}</p>
            <p class="mt-1"><span class="font-medium">Message:</span> {{ result.message || '—' }}</p>
            <p class="mt-1 text-slate-500">Last check: {{ connection.last_health_check_at || '—' }}</p>
        </div>
    </AuthenticatedLayout>
</template>
