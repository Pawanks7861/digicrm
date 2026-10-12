<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({ requestItem: Object });
const form = useForm({ comment: '' });
const label = (v) => (v || '').replaceAll('_', ' ');
const r = props.requestItem;

const cancel = () => {
    if (confirm('Cancel this request?')) router.post(route('portal.requests.cancel', r.id));
};
</script>

<template>
    <Head :title="r.request_number" />
    <PortalLayout>
        <Link :href="route('portal.requests')" class="text-sm text-teal-700 hover:underline">← Requests</Link>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-semibold">{{ r.title }}</h1>
            <StatusBadge :status="r.status" />
        </div>
        <p class="mt-1 text-sm text-slate-500">{{ r.request_number }} · <span class="capitalize">{{ label(r.request_type) }}</span></p>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <p class="whitespace-pre-wrap">{{ r.description }}</p>
            <div v-if="r.rejection_reason" class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-rose-900">{{ r.rejection_reason }}</div>
            <div v-if="r.deliverable" class="mt-3 text-slate-500">Progress via deliverable: {{ r.deliverable.title }} ({{ r.deliverable.status }})</div>
        </div>

        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
            <h3 class="text-sm font-semibold">Conversation</h3>
            <div v-for="c in r.comments" :key="c.id" class="mt-3 border-t border-slate-100 pt-3 text-sm">
                <div class="text-xs text-slate-400">{{ c.user }} · {{ new Date(c.created_at).toLocaleString() }}</div>
                <p class="mt-1">{{ c.comment }}</p>
            </div>
            <form class="mt-4 space-y-2" @submit.prevent="form.post(route('portal.requests.comments', r.id), { preserveScroll: true, onSuccess: () => form.reset() })">
                <textarea v-model="form.comment" rows="3" class="w-full rounded-lg border-slate-300 text-sm" required placeholder="Add a comment…" />
                <PrimaryButton :disabled="form.processing">Send</PrimaryButton>
            </form>
        </div>

        <button v-if="r.can_cancel" type="button" class="mt-4 text-sm text-rose-700 hover:underline" @click="cancel">Cancel request</button>
    </PortalLayout>
</template>
