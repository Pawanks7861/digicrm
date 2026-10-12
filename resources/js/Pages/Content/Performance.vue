<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    content: Object,
    client: Object,
    from: String,
    to: String,
    dashboard: Object,
});
</script>

<template>
    <Head :title="`Content performance — ${content.title}`" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('content.show', content.id)" class="text-sm text-teal-700 hover:underline">← Content</Link>
            <h1 class="mt-2 text-2xl font-semibold">{{ content.title }}</h1>
            <p class="text-sm text-slate-500">{{ client.company_name }}</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="m in dashboard.metrics" :key="m.code" class="rounded-xl border bg-white p-4">
                <div class="text-xs uppercase text-slate-500">{{ m.label }}</div>
                <div class="mt-2 text-2xl font-semibold">{{ m.display }}</div>
            </div>
        </div>
        <p v-if="!Object.keys(dashboard.metrics || {}).length" class="text-slate-500">No Data</p>
    </AuthenticatedLayout>
</template>
