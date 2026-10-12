<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ template: Object, rendered: Object, vars: Object });
</script>

<template>
    <Head title="Template Preview" />
    <AuthenticatedLayout>
        <Link :href="route('settings.notifications.templates')" class="text-sm text-teal-700 hover:underline">← Templates</Link>
        <h1 class="mt-3 text-2xl font-semibold">{{ template.name }} preview</h1>
        <div class="mt-6 max-w-xl rounded-xl border bg-white p-5 shadow-sm">
            <p class="text-xs uppercase text-slate-400">Subject</p>
            <p class="font-medium">{{ rendered.subject }}</p>
            <p class="mt-4 text-lg font-semibold" v-html="rendered.heading" />
            <div class="mt-3 prose prose-sm text-slate-700" v-html="rendered.body" />
            <p class="mt-4 inline-block rounded bg-teal-700 px-3 py-1.5 text-sm text-white">{{ rendered.cta_label || 'Open' }}</p>
            <p class="mt-4 text-xs text-slate-400" v-html="rendered.footer" />
        </div>
    </AuthenticatedLayout>
</template>
