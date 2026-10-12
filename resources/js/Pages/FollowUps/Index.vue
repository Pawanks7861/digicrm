<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

defineProps({ groups: Object, options: Object });

const complete = (id) => {
    const outcome = prompt('Outcome (optional)') || '';
    router.post(route('follow-ups.complete', id), { outcome }, { preserveScroll: true });
};
const snooze = (id, days) => {
    const until = new Date();
    until.setDate(until.getDate() + days);
    router.post(route('follow-ups.snooze', id), { until: until.toISOString() }, { preserveScroll: true });
};

const Section = { props: ['title', 'items'], emits: [] };
</script>

<template>
    <Head title="My Follow-ups" />
    <AuthenticatedLayout>
        <h1 class="mb-6 text-2xl font-semibold tracking-tight">My Follow-ups</h1>

        <div v-for="(items, key) in { overdue: groups.overdue, today: groups.today, upcoming: groups.upcoming, snoozed: groups.snoozed }" :key="key" class="mb-6">
            <h2 class="mb-2 text-sm font-semibold uppercase text-slate-400">{{ key }}</h2>
            <div v-if="items.length" class="space-y-2">
                <div v-for="f in items" :key="f.id" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <div class="font-medium">{{ f.title }}</div>
                            <div class="text-xs text-slate-500"><template v-if="f.lead"><Link :href="route('leads.show', f.lead.id)" class="text-teal-700 hover:underline">{{ f.lead.lead_number }} · {{ f.lead.name }}</Link> · </template>{{ f.client?.company_name }}<template v-if="f.client"> · </template>{{ new Date(f.due_at).toLocaleString() }}</div>
                        </div>
                        <StatusBadge :status="f.display_status" />
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                        <button type="button" class="rounded border border-teal-200 px-2 py-1 text-teal-800" @click="complete(f.id)">Complete</button>
                        <button type="button" class="rounded border border-slate-200 px-2 py-1" @click="snooze(f.id, 1)">Snooze 1d</button>
                        <button type="button" class="rounded border border-slate-200 px-2 py-1" @click="snooze(f.id, 7)">Snooze 1w</button>
                    </div>
                </div>
            </div>
            <EmptyState v-else :title="key === 'overdue' || key === 'today' ? 'You\'re all caught up.' : 'Nothing here.'" class="!py-4" />
        </div>
    </AuthenticatedLayout>
</template>
