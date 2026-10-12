<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    requestItem: Object,
    scopeCheck: Object,
    suggestedItem: Object,
    campaigns: Array,
    users: Array,
    options: Object,
    can: Object,
});

const label = (v) => (v || '').replaceAll('_', ' ');
const r = props.requestItem;

const infoForm = useForm({ message: '' });
const rejectForm = useForm({ rejection_reason: '', internal_notes: '' });
const commentForm = useForm({ comment: '', visibility: 'internal' });
const overageForm = useForm({ decision: 'complimentary', notes: '' });
const convertForm = useForm({ campaign_id: '', title: r.title });
const showInfo = ref(false);
const showReject = ref(false);

const post = (name, data = {}) => router.post(route(name, r.id), data, { preserveScroll: true });
</script>

<template>
    <Head :title="r.request_number" />
    <AuthenticatedLayout>
        <Link :href="route('requests.index')" class="text-sm text-teal-700 hover:underline">← Requests</Link>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-semibold">{{ r.title }}</h1>
            <StatusBadge :status="r.status" />
            <StatusBadge :status="r.scope_status" />
        </div>
        <p class="mt-1 text-sm text-slate-500">{{ r.request_number }} · {{ r.client?.company_name }}</p>

        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="text-xs font-semibold uppercase text-slate-400">Client Request</h2>
                    <p class="mt-3 whitespace-pre-wrap text-sm">{{ r.description }}</p>
                    <div class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                        <div>Type: <span class="capitalize">{{ label(r.request_type) }}</span></div>
                        <div>Platform: <span class="capitalize">{{ label(r.platform) || '—' }}</span></div>
                        <div>Priority: <span class="capitalize">{{ r.priority }}</span></div>
                        <div>Qty: {{ r.quantity }}</div>
                        <div>Requested due: {{ r.requested_due_date || '—' }}</div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="text-xs font-semibold uppercase text-slate-400">Conversation</h2>
                    <div v-for="c in r.comments" :key="c.id" class="mt-3 border-t border-slate-100 pt-3 text-sm">
                        <div class="flex justify-between text-xs text-slate-400">
                            <span>{{ c.user?.name }} · {{ c.visibility }}</span>
                            <span>{{ new Date(c.created_at).toLocaleString() }}</span>
                        </div>
                        <p class="mt-1 whitespace-pre-wrap">{{ c.comment }}</p>
                    </div>
                    <form class="mt-4 space-y-2" @submit.prevent="commentForm.post(route('requests.comments.store', r.id), { preserveScroll: true, onSuccess: () => commentForm.reset('comment') })">
                        <textarea v-model="commentForm.comment" rows="2" class="w-full rounded-md border-slate-300 text-sm" required />
                        <select v-model="commentForm.visibility" class="rounded-md border-slate-300 text-sm">
                            <option value="internal">Internal</option>
                            <option value="client_visible">Reply to Client</option>
                        </select>
                        <PrimaryButton :disabled="commentForm.processing">Add comment</PrimaryButton>
                    </form>
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                    <h3 class="font-semibold">Package / Scope</h3>
                    <div v-if="scopeCheck" class="mt-2 space-y-1 text-xs text-slate-600">
                        <div>{{ suggestedItem?.service_name || r.client_package_item?.service_name }}</div>
                        <div>Included {{ scopeCheck.included }} · Used {{ scopeCheck.used }} · Remaining {{ scopeCheck.remaining }}</div>
                        <div>Requested {{ scopeCheck.requested }}</div>
                        <div v-if="scopeCheck.is_overage" class="font-semibold text-rose-700">PACKAGE LIMIT EXCEEDED · Est ₹{{ scopeCheck.estimated_extra_charge }}</div>
                    </div>
                    <button v-if="can.review" type="button" class="mt-3 rounded-lg border border-slate-200 px-3 py-1.5 text-xs" @click="post('requests.check-scope')">Check Scope</button>
                    <form v-if="can.review && r.scope_status === 'overage_pending'" class="mt-3 space-y-2" @submit.prevent="overageForm.post(route('requests.decide-overage', r.id))">
                        <select v-model="overageForm.decision" class="w-full rounded-md border-slate-300 text-sm">
                            <option value="complimentary">Complimentary</option>
                            <option value="chargeable">Charge Extra</option>
                            <option value="quotation_required">Quotation Required</option>
                            <option value="rejected">Reject</option>
                        </select>
                        <PrimaryButton>Apply Decision</PrimaryButton>
                    </form>
                </div>

                <div v-if="can.review" class="rounded-xl border border-slate-200 bg-white p-4 text-sm space-y-2">
                    <h3 class="font-semibold">Actions</h3>
                    <button type="button" class="block w-full rounded-lg bg-teal-700 px-3 py-1.5 text-white" @click="post('requests.accept')">Accept</button>
                    <button type="button" class="block w-full rounded-lg border border-slate-200 px-3 py-1.5" @click="showInfo = !showInfo">Need More Info</button>
                    <button type="button" class="block w-full rounded-lg border border-orange-200 px-3 py-1.5 text-orange-800" @click="showReject = !showReject">Reject</button>
                    <button type="button" class="block w-full rounded-lg border border-slate-200 px-3 py-1.5" @click="post('requests.complete')">Complete</button>
                    <form v-if="showInfo" class="space-y-2" @submit.prevent="infoForm.post(route('requests.need-info', r.id))">
                        <textarea v-model="infoForm.message" rows="2" class="w-full rounded-md border-slate-300 text-sm" required placeholder="Message to client" />
                        <PrimaryButton>Send</PrimaryButton>
                    </form>
                    <form v-if="showReject" class="space-y-2" @submit.prevent="rejectForm.post(route('requests.reject', r.id))">
                        <textarea v-model="rejectForm.rejection_reason" rows="2" class="w-full rounded-md border-slate-300 text-sm" required placeholder="Client-facing reason" />
                        <PrimaryButton class="!bg-rose-700">Reject</PrimaryButton>
                    </form>
                </div>

                <div v-if="can.convert" class="rounded-xl border border-slate-200 bg-white p-4 text-sm space-y-2">
                    <h3 class="font-semibold">Convert</h3>
                    <select v-model="convertForm.campaign_id" class="w-full rounded-md border-slate-300 text-sm">
                        <option value="">Create / pick campaign</option>
                        <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <button type="button" class="block w-full rounded-lg border border-slate-200 px-3 py-1.5" @click="convertForm.post(route('requests.convert-deliverable', r.id))">→ Deliverable</button>
                    <button type="button" class="block w-full rounded-lg border border-slate-200 px-3 py-1.5" @click="router.post(route('requests.convert-task', r.id))">→ Task</button>
                    <button type="button" class="block w-full rounded-lg border border-slate-200 px-3 py-1.5" @click="router.post(route('requests.convert-campaign', r.id))">→ Campaign</button>
                    <div v-if="r.deliverable" class="text-xs text-teal-700">Linked deliverable: {{ r.deliverable.title }}</div>
                    <div v-if="r.task" class="text-xs text-teal-700">Linked task: {{ r.task.title }}</div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
