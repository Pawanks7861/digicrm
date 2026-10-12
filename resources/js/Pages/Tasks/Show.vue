<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    task: Object,
    users: Array,
    statuses: Array,
    can: Object,
    timeSummary: Object,
    delayReasons: { type: Array, default: () => [] },
    running: Object,
});

const label = (v) => (v || '').replaceAll('_', ' ');
const fmtSec = (sec) => {
    const s = Number(sec || 0);
    const h = Math.floor(Math.abs(s) / 3600);
    const m = Math.floor((Math.abs(s) % 3600) / 60);
    return `${s < 0 ? '+' : ''}${h}h ${m}m`.replace('+-', '-');
};
const varianceLabel = computed(() => {
    const v = props.timeSummary?.variance_seconds || 0;
    if (v === 0) return '0m';
    return (v > 0 ? '+' : '') + fmtSec(v).replace(/^-/, (m) => m);
});

const statusForm = useForm({ status: props.task.status, delay_reason: 'waiting_for_client', delay_notes: '' });
const checklistForm = useForm({ title: '' });
const commentForm = useForm({ comment: '', visibility: 'internal' });
const depForm = useForm({ depends_on_task_id: '' });
const needsDelay = computed(() => statusForm.status === 'blocked');

const saveStatus = () => statusForm.post(route('tasks.status', props.task.id));
const addChecklist = () => checklistForm.post(route('tasks.checklist.store', props.task.id), { onSuccess: () => checklistForm.reset() });
const toggleItem = (item) => router.put(route('tasks.checklist.toggle', [props.task.id, item.id]), { is_completed: !item.is_completed }, { preserveScroll: true });
const addComment = () => commentForm.post(route('tasks.comments.store', props.task.id), { onSuccess: () => commentForm.reset('comment') });
const addDep = () => depForm.post(route('tasks.dependencies.store', props.task.id));
const submitReview = () => router.post(route('tasks.reviews.submit', props.task.id));
const startTimer = () => router.post(route('time-entries.start'), { task_id: props.task.id, stop_existing: true });
</script>

<template>
    <Head :title="task.title" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('tasks.index')" class="text-sm text-teal-700 hover:underline">← Tasks</Link>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold">{{ task.title }}</h1>
                <StatusBadge :status="task.status" />
                <span v-if="task.is_overdue" class="text-sm text-rose-600">Overdue</span>
                <PrimaryButton v-if="can.time" type="button" @click="startTimer">Start Timer</PrimaryButton>
            </div>
            <p class="mt-1 text-sm text-slate-500">
                {{ task.client?.company_name }}
                <span v-if="task.campaign"> · {{ task.campaign.name }}</span>
                <span v-if="task.deliverable"> · {{ task.deliverable.title }}</span>
            </p>
        </div>

        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border bg-white p-4 text-sm"><div class="text-xs uppercase text-slate-400">Estimated</div><div class="mt-1 font-semibold">{{ fmtSec(timeSummary?.estimated_seconds) }}</div></div>
            <div class="rounded-xl border bg-white p-4 text-sm"><div class="text-xs uppercase text-slate-400">Actual</div><div class="mt-1 font-semibold">{{ fmtSec(timeSummary?.actual_seconds) }}</div></div>
            <div class="rounded-xl border bg-white p-4 text-sm"><div class="text-xs uppercase text-slate-400">Variance</div><div class="mt-1 font-semibold" :class="(timeSummary?.variance_seconds||0) > 0 ? 'text-rose-700' : 'text-emerald-700'">{{ varianceLabel }}</div></div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600">
                    {{ task.description || 'No description.' }}
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="text-sm font-semibold uppercase text-slate-500">Checklist</h2>
                    <ul class="mt-3 space-y-2">
                        <li v-for="item in task.checklist_items" :key="item.id" class="flex items-center gap-2 text-sm">
                            <input type="checkbox" :checked="item.is_completed" @change="toggleItem(item)" />
                            <span :class="item.is_completed ? 'text-slate-400 line-through' : ''">{{ item.title }}</span>
                        </li>
                    </ul>
                    <form v-if="can.update" class="mt-3 flex gap-2" @submit.prevent="addChecklist">
                        <TextInput v-model="checklistForm.title" class="block w-full" placeholder="Add item…" />
                        <PrimaryButton>Add</PrimaryButton>
                    </form>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="text-sm font-semibold uppercase text-slate-500">Time Entries</h2>
                    <ul v-if="task.time_entries?.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="e in task.time_entries" :key="e.id" class="flex justify-between">
                            <span>{{ e.description || e.entry_type }} · {{ e.billable ? 'Billable' : 'Non-billable' }}</span>
                            <span class="font-mono">{{ fmtSec(e.duration_seconds) }}</span>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-slate-500">No time logged yet.</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="text-sm font-semibold uppercase text-slate-500">Delay History</h2>
                    <ul v-if="task.delay_records?.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="d in task.delay_records" :key="d.id">
                            <span class="font-medium">{{ d.reason?.name || 'Delay' }}</span>
                            <span class="text-slate-500"> · {{ d.is_active ? 'Active' : 'Resolved' }}</span>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-slate-500">No delays recorded.</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="text-sm font-semibold uppercase text-slate-500">Comments (internal)</h2>
                    <ul class="mt-3 space-y-3">
                        <li v-for="c in task.comments" :key="c.id" class="text-sm">
                            <div class="font-medium">{{ c.user?.name }}</div>
                            <div class="text-slate-600">{{ c.comment }}</div>
                        </li>
                    </ul>
                    <form class="mt-3 space-y-2" @submit.prevent="addComment">
                        <textarea v-model="commentForm.comment" rows="2" class="w-full rounded-md border-slate-300 text-sm" required />
                        <PrimaryButton>Add Comment</PrimaryButton>
                    </form>
                </div>
            </div>

            <div class="space-y-4">
                <form v-if="can.update" class="rounded-xl border border-slate-200 bg-white p-5 space-y-2" @submit.prevent="saveStatus">
                    <InputLabel value="Status" />
                    <select v-model="statusForm.status" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option v-for="s in statuses" :key="s" :value="s">{{ label(s) }}</option>
                    </select>
                    <div v-if="needsDelay" class="space-y-2">
                        <InputLabel value="Delay reason" />
                        <select v-model="statusForm.delay_reason" class="w-full rounded-md border-slate-300 text-sm" required>
                            <option v-for="r in delayReasons" :key="r.code" :value="r.code">{{ r.name }}</option>
                        </select>
                        <textarea v-model="statusForm.delay_notes" rows="2" class="w-full rounded-md border-slate-300 text-sm" placeholder="Notes" />
                    </div>
                    <PrimaryButton class="mt-3" :disabled="statusForm.processing">Update</PrimaryButton>
                    <button v-if="can.review && task.status === 'in_progress'" type="button" class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" @click="submitReview">
                        Submit Internal Review
                    </button>
                </form>
                <div v-if="task.allocations?.length" class="rounded-xl border bg-white p-5 text-sm">
                    <h3 class="text-xs font-semibold uppercase text-slate-500">Allocations</h3>
                    <ul class="mt-2 space-y-1">
                        <li v-for="a in task.allocations" :key="a.id">{{ a.user?.name }} · {{ Math.round(a.allocated_minutes/60) }}h {{ a.allocated_minutes%60 }}m</li>
                    </ul>
                </div>
                <form v-if="can.update" class="rounded-xl border border-slate-200 bg-white p-5" @submit.prevent="addDep">
                    <InputLabel value="Depends on Task ID" />
                    <TextInput v-model="depForm.depends_on_task_id" type="number" class="mt-1 block w-full" />
                    <PrimaryButton class="mt-3">Add Dependency</PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
