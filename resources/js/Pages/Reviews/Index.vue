<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    reviews: Object,
    can: Object,
});

const feedback = ref('');
const approve = (r) => router.post(route('internal-reviews.approve', r.id));
const requestChanges = (r) => {
    if (!feedback.value.trim()) {
        alert('Feedback is required');
        return;
    }
    router.post(route('internal-reviews.request-changes', r.id), { feedback: feedback.value });
};
</script>

<template>
    <Head title="Internal Reviews" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight">Internal Review Queue</h1>
            <p class="mt-1 text-sm text-slate-500">Approve work before it reaches the client.</p>
        </div>

        <div v-if="reviews.data?.length" class="space-y-3">
            <div v-for="r in reviews.data" :key="r.id" class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="font-medium">
                            {{ r.reviewable?.title || r.reviewable_type + ' #' + r.reviewable_id }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            Submitted by {{ r.submitter?.name }} · {{ r.submitted_at }}
                        </div>
                        <StatusBadge class="mt-2" :status="r.status" />
                    </div>
                    <div v-if="can.approve && r.status === 'pending'" class="flex flex-col gap-2">
                        <PrimaryButton @click="approve(r)">Approve</PrimaryButton>
                        <textarea v-model="feedback" rows="2" class="rounded-md border-slate-300 text-sm" placeholder="Feedback for changes…" />
                        <button type="button" class="rounded-lg border border-rose-200 px-3 py-1.5 text-sm text-rose-700" @click="requestChanges(r)">
                            Request Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <EmptyState v-else title="Nothing waiting for review." description="Submitted deliverables and tasks will appear here." />
    </AuthenticatedLayout>
</template>
