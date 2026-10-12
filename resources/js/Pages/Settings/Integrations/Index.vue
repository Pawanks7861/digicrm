<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({ catalog: Array, connections: Array, canManage: Boolean });

const statusFor = (provider, connections) => {
    const c = (connections || []).find((x) => x.provider === provider);
    return c?.status || 'not_connected';
};
</script>

<template>
    <Head title="Integrations" />
    <AuthenticatedLayout>
        <div class="mb-6 flex items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Integrations</h1>
                <p class="mt-1 text-sm text-slate-500">Connect calendars, social, and analytics. Tokens never leave the server.</p>
            </div>
            <Link v-if="canManage" :href="route('integrations.google.redirect')" class="rounded-lg bg-teal-700 px-3 py-2 text-sm text-white">Connect Google Calendar</Link>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="p in catalog" :key="p.key" class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h2 class="font-medium">{{ p.label }}</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ (p.capabilities || []).join(' · ') }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs capitalize">{{ statusFor(p.key, connections) }}</span>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <Link
                        v-if="connections.find((c) => c.provider === p.key)"
                        :href="route('integrations.show', connections.find((c) => c.provider === p.key).id)"
                        class="text-teal-700 hover:underline"
                    >Details</Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
