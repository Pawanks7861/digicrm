<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({ connection: Object, logs: Array, canManage: Boolean });
</script>

<template>
    <Head :title="connection.name || connection.provider" />
    <AuthenticatedLayout>
        <div class="mb-4">
            <Link :href="route('integrations.index')" class="text-sm text-teal-700 hover:underline">← Integrations</Link>
            <h1 class="mt-2 text-2xl font-semibold">{{ connection.name || connection.provider }}</h1>
            <p class="text-sm text-slate-500 capitalize">{{ connection.status }} · {{ connection.health_status || '—' }}</p>
        </div>

        <div v-if="canManage" class="mb-6 flex flex-wrap gap-2">
            <button type="button" class="rounded-lg border px-3 py-2 text-sm" @click="router.post(route('integrations.test', connection.id))">Test</button>
            <button type="button" class="rounded-lg border px-3 py-2 text-sm" @click="router.post(route('integrations.disable', connection.id))">Disable</button>
            <button type="button" class="rounded-lg border border-red-200 px-3 py-2 text-sm text-red-700" @click="router.post(route('integrations.disconnect', connection.id))">Disconnect</button>
            <Link :href="route('integrations.health', connection.id)" class="rounded-lg border px-3 py-2 text-sm">Health</Link>
        </div>

        <div class="rounded-xl border bg-white p-4 text-sm">
            <h2 class="mb-2 font-medium">Safe metadata</h2>
            <pre class="overflow-auto text-xs text-slate-600">{{ JSON.stringify(connection.meta || {}, null, 2) }}</pre>
            <p class="mt-2 text-xs text-slate-400">Access tokens and credentials are never returned to the browser.</p>
        </div>

        <div v-if="logs?.length" class="mt-6">
            <h2 class="mb-2 font-medium">Recent logs</h2>
            <ul class="space-y-1 text-sm">
                <li v-for="log in logs" :key="log.id" class="rounded border bg-white px-3 py-2">
                    {{ log.operation }} · {{ log.status }} · {{ log.safe_error || '—' }}
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
