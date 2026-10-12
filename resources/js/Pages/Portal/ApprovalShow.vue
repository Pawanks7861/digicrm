<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    approval: Object,
    feedback_categories: Array,
});

const showChanges = ref(false);
const form = useForm({
    feedback: '',
    feedback_category: '',
});

const approve = () => {
    if (confirm('Approve this creative?')) {
        form.post(route('portal.approvals.approve', props.approval.id));
    }
};

const requestChanges = () => {
    form.post(route('portal.approvals.request-changes', props.approval.id));
};

const label = (v) => (v || '').replaceAll('_', ' ');
const mime = props.approval.preview_mime || '';
const isImage = mime.startsWith('image/');
const isVideo = mime.startsWith('video/');
</script>

<template>
    <Head :title="'Review: ' + approval.content.title" />
    <PortalLayout>
        <Link :href="route('portal.approvals')" class="text-sm text-teal-700 hover:underline">← Approvals</Link>

        <div class="mt-4">
            <h1 class="text-2xl font-semibold tracking-tight">{{ approval.content.title }}</h1>
            <p class="mt-1 text-sm text-slate-500 capitalize">
                {{ label(approval.content.platform) }} · {{ approval.content.campaign?.name }} · Revision {{ approval.revision_number }}
            </p>
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-black">
            <img
                v-if="isImage && approval.has_preview"
                :src="route('portal.content.preview', approval.content.id)"
                :alt="approval.content.title"
                class="mx-auto max-h-[70vh] w-full object-contain"
            />
            <video
                v-else-if="isVideo && approval.has_preview"
                :src="route('portal.content.preview', approval.content.id)"
                controls
                class="mx-auto max-h-[70vh] w-full"
            />
            <div v-else class="bg-white p-8 text-center text-sm text-slate-600">Preview unavailable — open from Files if needed.</div>
        </div>

        <div class="mt-4 space-y-2 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <div><span class="text-slate-400">Caption</span><p class="mt-1 whitespace-pre-wrap">{{ approval.content.caption || '—' }}</p></div>
            <div><span class="text-slate-400">Hashtags</span><p class="mt-1">{{ approval.content.hashtags || '—' }}</p></div>
            <div><span class="text-slate-400">CTA</span><p class="mt-1">{{ approval.content.cta || '—' }}</p></div>
            <div v-if="approval.content.scheduled_at"><span class="text-slate-400">Proposed date</span><p class="mt-1">{{ new Date(approval.content.scheduled_at).toLocaleString() }}</p></div>
        </div>

        <div v-if="approval.feedback_history?.length" class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
            <h3 class="text-sm font-semibold">Previous feedback</h3>
            <div v-for="f in approval.feedback_history" :key="f.id" class="mt-2 border-t border-slate-100 pt-2 text-sm">
                <div class="text-xs text-slate-400">{{ f.user }} · {{ new Date(f.created_at).toLocaleString() }}</div>
                <p class="mt-1">{{ f.comment }}</p>
            </div>
        </div>

        <div class="sticky bottom-0 mt-6 -mx-4 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:static sm:mx-0 sm:rounded-2xl sm:border sm:px-4">
            <div class="flex flex-col gap-3 sm:flex-row">
                <PrimaryButton class="w-full justify-center !bg-teal-700 py-3 text-base sm:flex-1" :disabled="form.processing" @click="approve">
                    Approve
                </PrimaryButton>
                <button
                    type="button"
                    class="w-full rounded-md border border-orange-300 bg-orange-50 px-4 py-3 text-base font-medium text-orange-900 sm:flex-1"
                    @click="showChanges = !showChanges"
                >
                    Request Changes
                </button>
            </div>

            <div v-if="showChanges" class="mt-4 space-y-3">
                <label class="block text-sm font-medium">What changes are required?</label>
                <textarea v-model="form.feedback" rows="4" class="w-full rounded-lg border-slate-300 text-sm" required placeholder="Describe the changes…" />
                <select v-model="form.feedback_category" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Category (optional)</option>
                    <option v-for="c in feedback_categories" :key="c" :value="c">{{ label(c) }}</option>
                </select>
                <PrimaryButton class="w-full justify-center !bg-orange-600" :disabled="form.processing || !form.feedback.trim()" @click="requestChanges">
                    Submit Changes Request
                </PrimaryButton>
            </div>
        </div>
    </PortalLayout>
</template>
