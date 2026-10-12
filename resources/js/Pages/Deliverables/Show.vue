<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    deliverable: Object,
    scopeCheck: Object,
    can: Object,
});

const label = (v) => (v || '').replaceAll('_', ' ');

const reviewForm = useForm({ reviewer_id: '' });
const submitReview = () => reviewForm.post(route('deliverables.reviews.submit', props.deliverable.id));

const overageForm = useForm({
    status: 'ready',
    decision: 'chargeable',
    decision_notes: '',
});
const commitWithDecision = () => {
    overageForm.post(route('deliverables.transition', props.deliverable.id), { preserveScroll: true });
};

const transition = (status) => router.post(route('deliverables.transition', props.deliverable.id), { status }, { preserveScroll: true });
</script>

<template>
    <Head :title="deliverable.title" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('campaigns.show', { campaign: deliverable.campaign_id, tab: 'deliverables' })" class="text-sm text-teal-700 hover:underline">
                ← {{ deliverable.campaign?.name }}
            </Link>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold">{{ deliverable.title }}</h1>
                <StatusBadge :status="deliverable.status" />
                <StatusBadge :status="deliverable.scope_status" />
            </div>
            <p class="mt-1 text-sm text-slate-500 capitalize">
                {{ label(deliverable.deliverable_type) }} · {{ label(deliverable.platform) }} · Progress {{ deliverable.progress_percent }}%
            </p>
        </div>

        <div v-if="$page.props.flash?.error" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <div class="font-semibold">PACKAGE LIMIT EXCEEDED</div>
            <div v-if="$page.props.flash.overage_check" class="mt-2 space-y-1 text-xs">
                <div>Included {{ $page.props.flash.overage_check.included }} · Used {{ $page.props.flash.overage_check.used }} · Remaining {{ $page.props.flash.overage_check.remaining }}</div>
                <div>Requested {{ $page.props.flash.overage_check.requested }} · Extra {{ $page.props.flash.overage_check.overage_quantity }} · Est. ₹{{ $page.props.flash.overage_check.estimated_extra_charge }}</div>
            </div>
            <form class="mt-3 flex flex-wrap items-end gap-2" @submit.prevent="commitWithDecision">
                <select v-model="overageForm.decision" class="rounded-md border-slate-300 text-sm">
                    <option value="chargeable">Charge Extra</option>
                    <option value="complimentary">Complimentary</option>
                    <option value="rejected">Reject</option>
                    <option value="quotation_required">Send Quotation</option>
                </select>
                <PrimaryButton>Apply Decision & Commit</PrimaryButton>
            </form>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
                <h2 class="text-sm font-semibold uppercase text-slate-500">Details</h2>
                <p class="mt-3 whitespace-pre-wrap text-sm text-slate-600">{{ deliverable.description || 'No description.' }}</p>
                <div class="mt-4 flex flex-wrap gap-2 text-sm">
                    <button v-if="deliverable.status === 'brief_pending'" type="button" class="rounded-lg bg-teal-700 px-3 py-1.5 text-white" @click="transition('ready')">Mark Ready (consume scope)</button>
                    <button v-if="deliverable.status === 'in_progress' && can.review" type="button" class="rounded-lg border border-slate-200 px-3 py-1.5" @click="submitReview">Submit Internal Review</button>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-3 py-1.5"
                        @click="router.post(route('deliverables.content.generate', deliverable.id))"
                    >Generate Content Items</button>
                </div>

                <div class="mt-6">
                    <h3 class="text-sm font-semibold text-slate-700">Content Items</h3>
                    <ul v-if="deliverable.content_items?.length" class="mt-2 space-y-1 text-sm">
                        <li v-for="ci in deliverable.content_items" :key="ci.id" class="flex items-center justify-between gap-2 border-b border-slate-50 py-1">
                            <Link :href="route('content.show', ci.id)" class="text-teal-700 hover:underline">{{ ci.title }}</Link>
                            <StatusBadge :status="ci.status" />
                        </li>
                    </ul>
                    <p v-else class="mt-1 text-xs text-slate-400">No content items yet.</p>
                </div>
            </div>
            <div class="space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm">
                    <div class="font-semibold">Package / Scope</div>
                    <p class="mt-2 text-slate-500">{{ deliverable.client_package?.package_name || 'Out of package' }}</p>
                    <p v-if="deliverable.client_package_item">{{ deliverable.client_package_item.service_name }}</p>
                    <div v-if="scopeCheck" class="mt-2 text-xs text-slate-500">
                        Used {{ scopeCheck.used }} / {{ scopeCheck.is_unlimited ? '∞' : scopeCheck.included }} · Remaining {{ scopeCheck.remaining ?? '∞' }}
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm">
                    <div class="font-semibold">Review History</div>
                    <ul v-if="deliverable.reviews?.length" class="mt-3 space-y-2">
                        <li v-for="r in deliverable.reviews" :key="r.id" class="border-b border-slate-100 pb-2">
                            <div class="flex justify-between"><StatusBadge :status="r.status" /><span class="text-xs text-slate-400">#{{ r.id }}</span></div>
                            <div class="text-xs text-slate-500">By {{ r.submitter?.name }} → {{ r.reviewer?.name || 'pending' }}</div>
                            <div v-if="r.feedback" class="mt-1 text-xs">{{ r.feedback }}</div>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-xs text-slate-400">No reviews yet.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
