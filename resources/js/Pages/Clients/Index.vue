<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    clients: Object,
    filters: Object,
    statuses: Array,
    can: Object,
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');

watch([search, status], () => {
    router.get(
        route('clients.index'),
        { search: search.value || undefined, status: status.value || undefined },
        { preserveState: true, replace: true }
    );
});
</script>

<template>
    <Head title="Clients" />

    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Clients</h1>
                <p class="mt-1 text-sm text-slate-500">Manage agency clients, onboarding, and brand profiles.</p>
            </div>
            <Link
                v-if="can.create"
                :href="route('clients.create')"
                class="inline-flex items-center justify-center rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800"
            >
                New Client
            </Link>
        </div>

        <div class="mb-4 flex flex-col gap-3 sm:flex-row">
            <input
                v-model="search"
                type="search"
                placeholder="Search company, contact, email…"
                class="w-full rounded-lg border-slate-200 text-sm sm:max-w-sm"
            />
            <select v-model="status" class="rounded-lg border-slate-200 text-sm sm:w-48">
                <option value="">All statuses</option>
                <option v-for="s in statuses" :key="s" :value="s">{{ s.replaceAll('_', ' ') }}</option>
            </select>
        </div>

        <div v-if="clients.data.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Company</th>
                        <th class="px-4 py-3">Contact</th>
                        <th class="px-4 py-3">Account Manager</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Onboarding</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="client in clients.data" :key="client.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <Link :href="route('clients.show', client.id)" class="font-medium text-teal-800 hover:underline">
                                {{ client.company_name }}
                            </Link>
                            <div class="text-xs text-slate-400">{{ client.city }}{{ client.city && client.state ? ', ' : '' }}{{ client.state }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div>{{ client.contact_person || '—' }}</div>
                            <div class="text-xs text-slate-400">{{ client.email || client.mobile }}</div>
                        </td>
                        <td class="px-4 py-3">{{ client.account_manager?.name || '—' }}</td>
                        <td class="px-4 py-3"><StatusBadge :status="client.status" /></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        class="h-full rounded-full bg-teal-600"
                                        :style="{
                                            width:
                                                (client.onboarding_total
                                                    ? Math.round((client.onboarding_completed / client.onboarding_total) * 100)
                                                    : 0) + '%',
                                        }"
                                    />
                                </div>
                                <span class="text-xs text-slate-500">
                                    {{ client.onboarding_completed || 0 }}/{{ client.onboarding_total || 0 }}
                                </span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EmptyState
            v-else
            title="No clients yet."
            description="Create your first client to start onboarding and brand setup."
        >
            <template #action>
                <Link
                    v-if="can.create"
                    :href="route('clients.create')"
                    class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800"
                >
                    Create Client
                </Link>
            </template>
        </EmptyState>
    </AuthenticatedLayout>
</template>
