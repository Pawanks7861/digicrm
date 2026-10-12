<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import LeadsNav from '@/Components/Leads/LeadsNav.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ pairs: Object, filters: Object });
const category = ref(props.filters.lead_category || '');
watch(category, () => router.get(route('leads.duplicates'), { lead_category: category.value || undefined }, { preserveState: true, replace: true }));

const merge = (pair) => {
    if (confirm(`Merge ${pair.lead.lead_number} into ${pair.original.lead_number}?`)) {
        router.post(route('leads.merge', pair.lead.id), { target_id: pair.original.id }, { preserveScroll: true });
    }
};
const keep = (pair) => router.post(route('leads.keep-separate', pair.lead.id), {}, { preserveScroll: true });
const mark = (pair) => router.post(route('leads.mark-duplicate', pair.lead.id), {}, { preserveScroll: true });
const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head title="Duplicate Review" />
    <AuthenticatedLayout>
        <div class="mb-4">
            <h1 class="text-2xl font-semibold tracking-tight">Duplicate review</h1>
            <p class="mt-1 text-sm text-slate-500">Possible duplicates are matched on normalised email or mobile within the same category and owning client. Nothing is merged automatically.</p>
        </div>
        <LeadsNav />
        <div class="mb-4">
            <select v-model="category" class="rounded-lg border-slate-200 text-sm">
                <option value="">All categories</option>
                <option value="agency_sales">Agency sales</option>
                <option value="client_campaign">Client campaign</option>
            </select>
        </div>
        <div v-if="pairs.data.length" class="space-y-3">
            <div v-for="pair in pairs.data" :key="pair.lead.id" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-[1fr_1fr_auto]">
                <div>
                    <div class="text-xs uppercase text-slate-400">New lead · {{ label(pair.lead.lead_category) }}<span v-if="pair.lead.client"> · {{ pair.lead.client }}</span></div>
                    <Link :href="route('leads.show', pair.lead.id)" class="font-medium text-teal-800 hover:underline">{{ pair.lead.lead_number }} · {{ pair.lead.name }}</Link>
                    <div class="text-xs text-slate-500">{{ [pair.lead.company, pair.lead.email, pair.lead.mobile].filter(Boolean).join(' · ') }}</div>
                    <div class="mt-1"><StatusBadge :status="pair.lead.status" /></div>
                </div>
                <div>
                    <div class="text-xs uppercase text-slate-400">Existing lead</div>
                    <template v-if="pair.original">
                        <Link :href="route('leads.show', pair.original.id)" class="font-medium text-teal-800 hover:underline">{{ pair.original.lead_number }} · {{ pair.original.name }}</Link>
                        <div class="text-xs text-slate-500">{{ [pair.original.company, pair.original.email, pair.original.mobile].filter(Boolean).join(' · ') }}</div>
                        <div class="mt-1"><StatusBadge :status="pair.original.status" /></div>
                    </template>
                    <p v-else class="text-sm text-slate-500">The original lead is no longer available.</p>
                </div>
                <div class="flex flex-row gap-2 md:flex-col">
                    <button v-if="pair.original" type="button" class="rounded-md bg-teal-700 px-3 py-1.5 text-xs text-white" @click="merge(pair)">Merge</button>
                    <button type="button" class="rounded-md border border-slate-200 px-3 py-1.5 text-xs" @click="keep(pair)">Keep separate</button>
                    <button type="button" class="rounded-md border border-rose-200 px-3 py-1.5 text-xs text-rose-700" @click="mark(pair)">Mark duplicate</button>
                </div>
            </div>
            <div v-if="pairs.links?.length > 3" class="flex flex-wrap gap-1">
                <Link v-for="(link, i) in pairs.links" :key="i" :href="link.url || '#'" v-html="link.label" class="rounded-md px-3 py-1 text-sm" :class="link.active ? 'bg-teal-700 text-white' : 'bg-white ring-1 ring-slate-200'" />
            </div>
        </div>
        <EmptyState v-else title="No possible duplicates to review." />
    </AuthenticatedLayout>
</template>
