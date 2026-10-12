<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    campaign: Object,
    tab: String,
    types: Array,
    deliverableTypes: Array,
    platforms: Array,
    deliverableStatuses: Array,
    packages: Array,
    users: Array,
    can: Object,
});

const activeTab = ref(props.tab || 'overview');
const label = (v) => (v || '').replaceAll('_', ' ');
const showDeliverableForm = ref(false);
const showBulk = ref(false);
const showTaskForm = ref(false);

const dForm = useForm({
    title: '',
    deliverable_type: 'reel',
    platform: 'instagram',
    quantity: 1,
    client_package_id: '',
    client_package_item_id: '',
    internal_due_date: '',
    description: '',
});

const packageItems = ref([]);
const onPackageChange = () => {
    const pkg = props.packages.find((p) => p.id === Number(dForm.client_package_id));
    packageItems.value = pkg?.items || [];
    dForm.client_package_item_id = '';
};

const submitDeliverable = () => {
    dForm.post(route('campaigns.deliverables.store', props.campaign.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeliverableForm.value = false;
            dForm.reset();
            activeTab.value = 'deliverables';
        },
    });
};

const bulkForm = useForm({
    items: [
        { title: 'Static Post', deliverable_type: 'static_post', quantity: 1, platform: 'instagram' },
        { title: 'Reel', deliverable_type: 'reel', quantity: 1, platform: 'instagram' },
    ],
});

const submitBulk = () => {
    bulkForm.post(route('campaigns.deliverables.bulk', props.campaign.id), {
        onSuccess: () => {
            showBulk.value = false;
            activeTab.value = 'deliverables';
        },
    });
};

const taskForm = useForm({
    client_id: props.campaign.client_id,
    campaign_id: props.campaign.id,
    campaign_deliverable_id: '',
    title: '',
    priority: 'normal',
    due_date: '',
    assignee_id: '',
});

const submitTask = () => {
    taskForm.post(route('tasks.store'), {
        onSuccess: () => {
            showTaskForm.value = false;
            activeTab.value = 'tasks';
        },
    });
};

const transition = (d, status) => {
    router.post(route('deliverables.transition', d.id), { status }, { preserveScroll: true });
};

const cancelDeliverable = (d) => {
    const restore = confirm('Cancel deliverable. OK = restore package usage if consumed. Cancel dialog = cancel without restore?');
    // confirm true = restore; we always cancel — use second confirm for restore choice
    const doRestore = confirm('Restore package allowance?');
    router.post(route('deliverables.cancel', d.id), {
        restore_usage: doRestore,
        reason: 'Cancelled from campaign',
    }, { preserveScroll: true });
};
</script>

<template>
    <Head :title="campaign.name" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:justify-between">
            <div>
                <Link :href="route('campaigns.index')" class="text-sm text-teal-700 hover:underline">← Campaigns</Link>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ campaign.name }}</h1>
                    <StatusBadge :status="campaign.status" />
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    {{ campaign.code }} · {{ campaign.client?.company_name }} · {{ label(campaign.campaign_type) }}
                    · Manager: {{ campaign.manager?.name || '—' }}
                </p>
            </div>
            <Link v-if="can.update" :href="route('campaigns.edit', campaign.id)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Edit</Link>
        </div>

        <div v-if="$page.props.flash?.error" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <div class="font-semibold">{{ $page.props.flash.error }}</div>
            <div v-if="$page.props.flash.overage_check" class="mt-1 text-xs">
                Remaining {{ $page.props.flash.overage_check.remaining }} · Overage {{ $page.props.flash.overage_check.overage_quantity }}
                · Est. ₹{{ $page.props.flash.overage_check.estimated_extra_charge }}
                — Retry transition with decision via Adjust on packages, or use Charge/Complimentary when committing.
            </div>
        </div>

        <div class="mb-6 flex gap-1 overflow-x-auto border-b border-slate-200">
            <button
                v-for="t in ['overview', 'deliverables', 'tasks']"
                :key="t"
                type="button"
                :class="['rounded-t-lg px-3 py-2 text-sm font-medium capitalize', activeTab === t ? 'bg-white text-teal-800 shadow-sm ring-1 ring-slate-200' : 'text-slate-500']"
                @click="activeTab = t"
            >
                {{ t }}
            </button>
        </div>

        <div v-if="activeTab === 'overview'" class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Deliverables</div>
                <div class="mt-2 text-3xl font-semibold">{{ campaign.progress?.deliverables?.total || 0 }}</div>
                <p class="mt-1 text-sm text-slate-500">
                    {{ campaign.progress?.deliverables?.completed || 0 }} done · {{ campaign.progress?.deliverables?.in_review || 0 }} review · {{ campaign.progress?.deliverables?.pending || 0 }} pending
                </p>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full bg-teal-600" :style="{ width: (campaign.progress?.deliverables?.percent || 0) + '%' }" />
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Tasks</div>
                <div class="mt-2 text-3xl font-semibold">{{ campaign.progress?.tasks?.total || 0 }}</div>
                <p class="mt-1 text-sm text-slate-500">
                    {{ campaign.progress?.tasks?.completed || 0 }} completed · {{ campaign.progress?.tasks?.overdue || 0 }} overdue · {{ campaign.progress?.tasks?.active || 0 }} active
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Objective</div>
                <p class="mt-3 text-sm text-slate-600">{{ campaign.objective || 'No objective set.' }}</p>
                <template v-if="can.finance">
                    <p class="mt-3 text-xs text-slate-500">Budget: {{ campaign.budget ?? '—' }} · Charge: {{ campaign.client_charge ?? '—' }}</p>
                </template>
            </div>
        </div>

        <div v-else-if="activeTab === 'deliverables'" class="space-y-4">
            <div class="flex flex-wrap gap-2">
                <button v-if="can.create_deliverable" type="button" class="rounded-lg bg-teal-700 px-3 py-2 text-sm text-white" @click="showDeliverableForm = !showDeliverableForm">Add Deliverable</button>
                <button v-if="can.create_deliverable" type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" @click="showBulk = !showBulk">Bulk Add</button>
            </div>

            <form v-if="showDeliverableForm" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-3" @submit.prevent="submitDeliverable">
                <div class="sm:col-span-2">
                    <InputLabel value="Title" />
                    <TextInput v-model="dForm.title" class="mt-1 block w-full" required />
                </div>
                <div>
                    <InputLabel value="Type" />
                    <select v-model="dForm.deliverable_type" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option v-for="t in deliverableTypes" :key="t" :value="t">{{ label(t) }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Platform" />
                    <select v-model="dForm.platform" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">—</option>
                        <option v-for="p in platforms" :key="p" :value="p">{{ label(p) }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Client Package" />
                    <select v-model="dForm.client_package_id" class="mt-1 w-full rounded-md border-slate-300 text-sm" @change="onPackageChange">
                        <option value="">Out of package</option>
                        <option v-for="p in packages" :key="p.id" :value="p.id">{{ p.package_name }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Package Service" />
                    <select v-model="dForm.client_package_item_id" class="mt-1 w-full rounded-md border-slate-300 text-sm" :disabled="!packageItems.length">
                        <option value="">—</option>
                        <option v-for="i in packageItems" :key="i.id" :value="i.id">
                            {{ i.service_name }} ({{ i.is_unlimited ? '∞' : i.included_quantity }} {{ i.unit }})
                        </option>
                    </select>
                </div>
                <div class="sm:col-span-3"><PrimaryButton :disabled="dForm.processing">Create</PrimaryButton></div>
            </form>

            <form v-if="showBulk" class="space-y-3 rounded-xl border border-slate-200 bg-white p-4" @submit.prevent="submitBulk">
                <div v-for="(row, idx) in bulkForm.items" :key="idx" class="grid gap-2 sm:grid-cols-4">
                    <TextInput v-model="row.title" placeholder="Title" />
                    <select v-model="row.deliverable_type" class="rounded-md border-slate-300 text-sm">
                        <option v-for="t in deliverableTypes" :key="t" :value="t">{{ label(t) }}</option>
                    </select>
                    <select v-model="row.platform" class="rounded-md border-slate-300 text-sm">
                        <option v-for="p in platforms" :key="p" :value="p">{{ label(p) }}</option>
                    </select>
                    <TextInput v-model="row.quantity" type="number" min="1" />
                </div>
                <PrimaryButton :disabled="bulkForm.processing">Create All</PrimaryButton>
            </form>

            <div v-if="campaign.deliverables?.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Deliverable</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Scope</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Progress</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="d in campaign.deliverables" :key="d.id">
                            <td class="px-4 py-3">
                                <Link :href="route('deliverables.show', d.id)" class="font-medium text-teal-800 hover:underline">{{ d.title }}</Link>
                            </td>
                            <td class="px-4 py-3 capitalize">{{ label(d.deliverable_type) }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="d.scope_status" /></td>
                            <td class="px-4 py-3"><StatusBadge :status="d.status" /></td>
                            <td class="px-4 py-3">{{ d.progress_percent }}%</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <button v-if="d.status === 'brief_pending'" type="button" class="text-teal-700 hover:underline" @click="transition(d, 'ready')">Commit / Ready</button>
                                    <button v-if="d.status === 'ready'" type="button" class="text-teal-700 hover:underline" @click="transition(d, 'in_progress')">Start</button>
                                    <button v-if="d.status === 'in_progress'" type="button" class="text-teal-700 hover:underline" @click="transition(d, 'internal_review')">Internal Review</button>
                                    <button v-if="d.status !== 'cancelled' && d.status !== 'completed'" type="button" class="text-rose-600 hover:underline" @click="cancelDeliverable(d)">Cancel</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState v-else title="No deliverables created for this campaign." description="Add deliverables to track production and package usage." />
        </div>

        <div v-else-if="activeTab === 'tasks'" class="space-y-4">
            <button v-if="can.create_task" type="button" class="rounded-lg bg-teal-700 px-3 py-2 text-sm text-white" @click="showTaskForm = !showTaskForm">Add Task</button>
            <form v-if="showTaskForm" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2" @submit.prevent="submitTask">
                <div class="sm:col-span-2">
                    <InputLabel value="Title" />
                    <TextInput v-model="taskForm.title" class="mt-1 block w-full" required />
                </div>
                <div>
                    <InputLabel value="Deliverable (optional)" />
                    <select v-model="taskForm.campaign_deliverable_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">—</option>
                        <option v-for="d in campaign.deliverables" :key="d.id" :value="d.id">{{ d.title }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Assignee" />
                    <select v-model="taskForm.assignee_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">—</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Due Date" />
                    <TextInput v-model="taskForm.due_date" type="date" class="mt-1 block w-full" />
                </div>
                <div class="sm:col-span-2"><PrimaryButton :disabled="taskForm.processing">Create Task</PrimaryButton></div>
            </form>

            <div v-if="campaign.tasks?.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Task</th>
                            <th class="px-4 py-3">Assignee</th>
                            <th class="px-4 py-3">Due</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="t in campaign.tasks" :key="t.id">
                            <td class="px-4 py-3">
                                <Link :href="route('tasks.show', t.id)" class="font-medium text-teal-800 hover:underline">{{ t.title }}</Link>
                                <span v-if="t.is_overdue" class="ml-2 text-xs text-rose-600">Overdue</span>
                            </td>
                            <td class="px-4 py-3">{{ t.assignees?.find(a => a.role === 'assignee')?.user?.name || '—' }}</td>
                            <td class="px-4 py-3">{{ t.due_date || '—' }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="t.status" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState v-else title="No tasks assigned." description="Create tasks to track production work." />
        </div>
    </AuthenticatedLayout>
</template>
