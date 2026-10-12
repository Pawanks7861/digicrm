<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import LeadsNav from '@/Components/Leads/LeadsNav.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({ columns: Array, summary: Object, filters: Object, lostReasons: Array, users: Array, can: Object });

const view = ref('board');
const ownerId = ref(props.filters.owner_id || '');
watch(ownerId, () => router.get(route('pipeline.index'), { owner_id: ownerId.value || undefined }, { preserveState: true, replace: true }));

const dragging = ref(null);
const overStage = ref(null);
const pendingLost = ref(null);
const lostReasonId = ref('');
const lostNotes = ref('');
const error = ref('');

const allItems = computed(() => props.columns.flatMap((c) => c.items.map((i) => ({ ...i, stage: c.stage }))));

const move = (item, stage) => {
    error.value = '';
    if (!item || item.status !== 'open' || item.stage?.id === stage.id) return;
    if (stage.is_lost) {
        pendingLost.value = { item, stage };
        return;
    }
    router.post(route('opportunities.stage', item.id), { sales_pipeline_stage_id: stage.id }, {
        preserveScroll: true,
        onError: (e) => { error.value = Object.values(e)[0] || 'Could not move opportunity.'; },
    });
};
const confirmLost = () => {
    const { item, stage } = pendingLost.value;
    router.post(route('opportunities.stage', item.id), { sales_pipeline_stage_id: stage.id, lost_reason_id: lostReasonId.value, lost_notes: lostNotes.value }, {
        preserveScroll: true,
        onSuccess: () => { pendingLost.value = null; lostReasonId.value = ''; lostNotes.value = ''; },
        onError: (e) => { error.value = Object.values(e)[0] || 'Could not mark as lost.'; },
    });
};

const onDragStart = (item, stage, e) => {
    dragging.value = { ...item, stage };
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', String(item.id));
};
const onDrop = (stage) => {
    overStage.value = null;
    const item = dragging.value;
    dragging.value = null;
    move(item, stage);
};
const onSelect = (item, stage, e) => {
    const target = props.columns.find((c) => String(c.stage.id) === e.target.value)?.stage;
    if (target) move({ ...item, stage }, target);
    e.target.value = '';
};
const totals = (rows) => (rows?.length ? rows.map((t) => `${t.currency} ${Number(t.total).toLocaleString()}`).join(' · ') : '—');
</script>

<template>
    <Head title="Sales Pipeline" />
    <AuthenticatedLayout>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Sales pipeline</h1>
                <p class="mt-1 text-sm text-slate-500">Agency sales opportunities. Drag cards between stages, or use the stage selector on each card.</p>
            </div>
            <div class="flex gap-2">
                <select v-model="ownerId" class="rounded-lg border-slate-200 text-sm">
                    <option value="">All owners</option>
                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
                <div class="flex rounded-lg border border-slate-200 bg-white p-0.5 text-sm">
                    <button type="button" class="rounded-md px-3 py-1" :class="view === 'board' ? 'bg-teal-700 text-white' : ''" @click="view = 'board'">Board</button>
                    <button type="button" class="rounded-md px-3 py-1" :class="view === 'list' ? 'bg-teal-700 text-white' : ''" @click="view = 'list'">List</button>
                </div>
            </div>
        </div>
        <LeadsNav />

        <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs uppercase text-slate-400">Open opportunities</div><div class="text-2xl font-semibold">{{ summary.open_count }}</div></div>
            <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs uppercase text-slate-400">Open value</div><div class="text-sm font-semibold">{{ totals(summary.open_value) }}</div></div>
            <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs uppercase text-slate-400">Weighted value</div><div class="text-sm font-semibold">{{ totals(summary.weighted_value) }}</div><div class="text-xs text-slate-400">Value × stage probability</div></div>
            <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs uppercase text-slate-400">Closed (30 days)</div><div class="text-sm font-semibold"><span class="text-emerald-700">{{ summary.won_count }} won</span> · <span class="text-rose-700">{{ summary.lost_count }} lost</span></div></div>
        </div>
        <p v-if="error" class="mb-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</p>

        <div v-if="pendingLost" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4">
            <div class="mb-2 text-sm font-medium text-rose-900">Mark {{ pendingLost.item.opportunity_number }} as lost</div>
            <div class="flex flex-wrap gap-2">
                <select v-model="lostReasonId" class="rounded-lg border-slate-200 text-sm">
                    <option value="">Lost reason…</option>
                    <option v-for="r in lostReasons" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
                <input v-model="lostNotes" placeholder="Notes" class="flex-1 rounded-lg border-slate-200 text-sm" />
                <button type="button" :disabled="!lostReasonId" class="rounded-lg bg-rose-700 px-3 py-2 text-sm text-white disabled:opacity-50" @click="confirmLost">Confirm</button>
                <button type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" @click="pendingLost = null">Cancel</button>
            </div>
        </div>

        <div v-if="view === 'board'" class="flex gap-3 overflow-x-auto pb-4">
            <div
                v-for="col in columns"
                :key="col.stage.id"
                class="flex w-72 shrink-0 flex-col rounded-xl border bg-slate-50"
                :class="overStage === col.stage.id ? 'border-teal-400 bg-teal-50' : 'border-slate-200'"
                @dragover.prevent="overStage = col.stage.id"
                @dragleave="overStage = null"
                @drop.prevent="onDrop(col.stage)"
            >
                <div class="border-b border-slate-200 px-3 py-2">
                    <div class="flex items-center justify-between text-sm font-semibold" :class="col.stage.is_won ? 'text-emerald-800' : col.stage.is_lost ? 'text-rose-800' : 'text-slate-700'">
                        <span>{{ col.stage.name }}</span><span class="text-xs font-normal text-slate-400">{{ col.items.length }}</span>
                    </div>
                    <div class="text-xs text-slate-500">{{ totals(col.totals) }}</div>
                </div>
                <div class="flex-1 space-y-2 p-2">
                    <div
                        v-for="item in col.items"
                        :key="item.id"
                        class="rounded-lg border border-slate-200 bg-white p-3 text-sm shadow-sm"
                        :class="item.status === 'open' && can.move ? 'cursor-grab' : 'opacity-80'"
                        :draggable="item.status === 'open' && can.move"
                        @dragstart="onDragStart(item, col.stage, $event)"
                    >
                        <Link :href="route('opportunities.show', item.id)" class="font-medium text-teal-800 hover:underline">{{ item.title }}</Link>
                        <div class="text-xs text-slate-500">{{ item.opportunity_number }}<span v-if="item.lead"> · {{ item.lead.name }}</span></div>
                        <div class="mt-1 flex justify-between text-xs"><span class="font-medium">{{ item.currency }} {{ Number(item.estimated_value).toLocaleString() }}</span><span class="text-slate-400">{{ item.probability ?? 0 }}%</span></div>
                        <div class="text-xs text-slate-400">{{ item.owner || 'No owner' }}<span v-if="item.expected_close_date"> · closes {{ item.expected_close_date }}</span></div>
                        <select v-if="item.status === 'open' && can.move" class="mt-2 w-full rounded-md border-slate-200 py-1 text-xs" aria-label="Move to stage" @change="onSelect(item, col.stage, $event)">
                            <option value="">Move to…</option>
                            <option v-for="c in columns.filter((c) => c.stage.id !== col.stage.id)" :key="c.stage.id" :value="c.stage.id">{{ c.stage.name }}</option>
                        </select>
                    </div>
                    <p v-if="!col.items.length" class="px-1 py-4 text-center text-xs text-slate-400">Drop here</p>
                </div>
            </div>
        </div>

        <div v-else class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr><th class="px-4 py-3">Opportunity</th><th class="px-4 py-3">Stage</th><th class="px-4 py-3">Value</th><th class="px-4 py-3">Probability</th><th class="px-4 py-3">Owner</th><th class="px-4 py-3">Close date</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="item in allItems" :key="item.id">
                        <td class="px-4 py-3"><Link :href="route('opportunities.show', item.id)" class="font-medium text-teal-800 hover:underline">{{ item.title }}</Link><div class="text-xs text-slate-400">{{ item.opportunity_number }}</div></td>
                        <td class="px-4 py-3">{{ item.stage.name }}</td>
                        <td class="px-4 py-3">{{ item.currency }} {{ Number(item.estimated_value).toLocaleString() }}</td>
                        <td class="px-4 py-3">{{ item.probability ?? 0 }}%</td>
                        <td class="px-4 py-3">{{ item.owner || '—' }}</td>
                        <td class="px-4 py-3">{{ item.expected_close_date || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
