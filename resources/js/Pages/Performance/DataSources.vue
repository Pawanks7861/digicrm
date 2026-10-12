<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    connections: Array,
    can: { type: Object, default: () => ({}) },
});
</script>

<template>
    <Head title="Data Sources" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('performance.index')" class="text-sm text-teal-700 hover:underline">← Performance</Link>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Data Sources</h1>
            <p class="mt-1 text-sm text-slate-500">Connected marketing providers. Missing credentials show Permission Required — never invent numbers.</p>
        </div>
        <div class="space-y-3">
            <div v-for="c in connections" :key="c.id" class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="font-medium text-slate-900">{{ c.name || c.provider }}</div>
                        <div class="text-sm text-slate-500">{{ c.client_name || 'Agency-level' }} · {{ c.status }}</div>
                    </div>
                    <span class="text-xs text-slate-500">{{ c.has_access_token ? 'Token present' : 'Permission Required' }}</span>
                </div>
            </div>
            <div v-if="!connections?.length" class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-slate-500">Not Connected</div>
        </div>
    </AuthenticatedLayout>
</template>
