<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    items: Array,
    filters: Object,
    options: Object,
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const view = ref(props.filters.view || 'month');

const apply = () => {
    router.get(route('calendar.index'), {
        from: from.value,
        to: to.value,
        view: view.value,
        platform: props.filters.platform || undefined,
        status: props.filters.status || undefined,
    }, { preserveState: true, replace: true });
};

const byDay = computed(() => {
    const map = {};
    for (const item of props.items || []) {
        const day = (item.scheduled_at || '').slice(0, 10);
        if (!map[day]) map[day] = [];
        map[day].push(item);
    }
    return map;
});

const days = computed(() => Object.keys(byDay.value).sort());
const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head title="Content Calendar" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Content Calendar</h1>
                <p class="mt-1 text-sm text-slate-500">Scheduled and planned publishing dates.</p>
            </div>
            <Link :href="route('content.index')" class="text-sm text-teal-700 hover:underline">Content Library</Link>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <input v-model="from" type="date" class="rounded-lg border-slate-200 text-sm" />
            <input v-model="to" type="date" class="rounded-lg border-slate-200 text-sm" />
            <select v-model="view" class="rounded-lg border-slate-200 text-sm">
                <option value="month">Month</option>
                <option value="week">Week</option>
            </select>
            <button type="button" class="rounded-lg bg-teal-700 px-3 py-1.5 text-sm text-white" @click="apply">Apply</button>
        </div>

        <div v-if="days.length" class="space-y-4">
            <div v-for="day in days" :key="day" class="rounded-xl border border-slate-200 bg-white p-4">
                <h3 class="text-sm font-semibold text-slate-900">{{ new Date(day + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' }) }}</h3>
                <div class="mt-3 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="item in byDay[day]"
                        :key="item.id"
                        :href="route('content.show', item.id)"
                        class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 hover:border-teal-300"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="font-medium text-slate-900">{{ item.title }}</div>
                            <StatusBadge :status="item.status" />
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            {{ item.client?.company_name }} · <span class="capitalize">{{ label(item.platform) }}</span>
                            · {{ new Date(item.scheduled_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                        </div>
                    </Link>
                </div>
            </div>
        </div>
        <EmptyState v-else title="No content scheduled for this period." description="Approve content and set a schedule date." />
    </AuthenticatedLayout>
</template>
