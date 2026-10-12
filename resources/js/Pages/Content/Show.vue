<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    item: Object,
    options: Object,
    can: Object,
});

const tab = ref('overview');
const label = (v) => (v || '').replaceAll('_', ' ');

const revisionForm = useForm({
    file: null,
    notes: '',
    caption: props.item.caption || '',
    hashtags: props.item.hashtags || '',
    cta: props.item.cta || '',
});

const scheduleForm = useForm({ scheduled_at: '', override: false, reason: '' });
const publishForm = useForm({ published_at: new Date().toISOString().slice(0, 16), published_url: '' });
const commentForm = useForm({ comment: '', visibility: 'internal' });

const uploadRevision = () => {
    revisionForm.post(route('content.revisions.store', props.item.id), { forceFormData: true, preserveScroll: true });
};

const sendToClient = () => {
    if (confirm('Send current revision to the client for approval?')) {
        router.post(route('content.send-to-client', props.item.id));
    }
};

const submitReview = () => router.post(route('content.reviews.submit', props.item.id));
const schedule = () => scheduleForm.post(route('content.schedule', props.item.id), { preserveScroll: true });
const publish = () => publishForm.post(route('content.publish', props.item.id), { preserveScroll: true });
const addComment = () => commentForm.post(route('content.comments.store', props.item.id), {
    preserveScroll: true,
    onSuccess: () => commentForm.reset('comment'),
});

const isImage = (mime) => mime && mime.startsWith('image/');
const isVideo = (mime) => mime && mime.startsWith('video/');
</script>

<template>
    <Head :title="item.title" />
    <AuthenticatedLayout>
        <div class="mb-4">
            <Link :href="route('content.index')" class="text-sm text-teal-700 hover:underline">← Content</Link>
        </div>

        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ item.title }}</h1>
                    <StatusBadge :status="item.status" />
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    {{ item.client?.company_name }}
                    <span v-if="item.campaign"> · {{ item.campaign.name }}</span>
                    <span v-if="item.current_revision"> · Revision {{ item.current_revision.revision_number }}</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2 text-sm">
                <button v-if="can.upload" type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5" @click="tab = 'revisions'">Upload Revision</button>
                <button v-if="can.edit && ['draft','in_production','changes_required'].includes(item.status)" type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5" @click="submitReview">Internal Review</button>
                <button v-if="can.send && item.status === 'ready_for_client_review'" type="button" class="rounded-lg bg-teal-700 px-3 py-1.5 text-white" @click="sendToClient">Send to Client</button>
                <Link v-if="can.edit" :href="route('content.edit', item.id)" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5">Edit</Link>
            </div>
        </div>

        <div class="mb-4 flex gap-2 overflow-x-auto text-sm">
            <button
                v-for="t in ['overview','revisions','approvals','comments']"
                :key="t"
                type="button"
                class="rounded-lg px-3 py-1.5 capitalize"
                :class="tab === t ? 'bg-teal-700 text-white' : 'bg-white border border-slate-200'"
                @click="tab = t"
            >{{ t }}</button>
        </div>

        <div v-if="tab === 'overview'" class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 lg:col-span-2">
                <div v-if="item.current_revision?.path" class="overflow-hidden rounded-lg bg-slate-100">
                    <img v-if="isImage(item.current_revision.mime_type)" :src="route('content.preview', item.id)" :alt="item.title" class="max-h-[28rem] w-full object-contain" />
                    <video v-else-if="isVideo(item.current_revision.mime_type)" :src="route('content.preview', item.id)" controls class="max-h-[28rem] w-full" />
                    <a v-else :href="route('content.preview', item.id)" target="_blank" class="block p-6 text-teal-700 hover:underline">Open file: {{ item.current_revision.original_name }}</a>
                </div>
                <p v-else class="text-sm text-slate-500">No revision uploaded yet.</p>
                <div class="mt-4 space-y-2 text-sm">
                    <div><span class="text-slate-400">Caption:</span> {{ item.caption || '—' }}</div>
                    <div><span class="text-slate-400">Hashtags:</span> {{ item.hashtags || '—' }}</div>
                    <div><span class="text-slate-400">CTA:</span> {{ item.cta || '—' }}</div>
                    <div><span class="text-slate-400">Platform:</span> <span class="capitalize">{{ label(item.platform) }}</span></div>
                    <div><span class="text-slate-400">Type:</span> <span class="capitalize">{{ label(item.content_type) }}</span></div>
                    <div v-if="item.deliverable"><span class="text-slate-400">Deliverable:</span>
                        <Link :href="route('deliverables.show', item.deliverable.id)" class="text-teal-700 hover:underline">{{ item.deliverable.title }}</Link>
                    </div>
                </div>
            </div>
            <div class="space-y-4">
                <div v-if="can.schedule" class="rounded-xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-semibold">Schedule</h3>
                    <input v-model="scheduleForm.scheduled_at" type="datetime-local" class="mt-2 w-full rounded-md border-slate-300 text-sm" />
                    <label class="mt-2 flex items-center gap-2 text-xs text-slate-600">
                        <input v-model="scheduleForm.override" type="checkbox" /> Override approval requirement
                    </label>
                    <input v-if="scheduleForm.override" v-model="scheduleForm.reason" type="text" placeholder="Override reason" class="mt-2 w-full rounded-md border-slate-300 text-sm" />
                    <PrimaryButton class="mt-3" :disabled="scheduleForm.processing" @click="schedule">Save Schedule</PrimaryButton>
                </div>
                <div v-if="can.publish" class="rounded-xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-semibold">Mark Published</h3>
                    <input v-model="publishForm.published_at" type="datetime-local" class="mt-2 w-full rounded-md border-slate-300 text-sm" />
                    <input v-model="publishForm.published_url" type="url" placeholder="Published URL" class="mt-2 w-full rounded-md border-slate-300 text-sm" />
                    <PrimaryButton class="mt-3" :disabled="publishForm.processing" @click="publish">Publish</PrimaryButton>
                    <a v-if="item.published_url" :href="item.published_url" target="_blank" rel="noopener" class="mt-2 block text-sm text-teal-700 hover:underline">Open Post</a>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                    <div>Client revision cycles: <strong>{{ item.client_revision_cycles }}</strong></div>
                    <div class="mt-1 text-xs text-slate-400">Internal edits do not count toward package revision limits.</div>
                </div>
            </div>
        </div>

        <div v-else-if="tab === 'revisions'" class="space-y-4">
            <form v-if="can.upload" class="rounded-xl border border-slate-200 bg-white p-4" @submit.prevent="uploadRevision">
                <h3 class="text-sm font-semibold">Upload New Revision</h3>
                <input type="file" class="mt-2 block w-full text-sm" @input="revisionForm.file = $event.target.files[0]" required />
                <textarea v-model="revisionForm.notes" rows="2" placeholder="Revision notes" class="mt-2 w-full rounded-md border-slate-300 text-sm" />
                <textarea v-model="revisionForm.caption" rows="2" placeholder="Caption snapshot" class="mt-2 w-full rounded-md border-slate-300 text-sm" />
                <input v-model="revisionForm.hashtags" type="text" placeholder="Hashtags" class="mt-2 w-full rounded-md border-slate-300 text-sm" />
                <input v-model="revisionForm.cta" type="text" placeholder="CTA" class="mt-2 w-full rounded-md border-slate-300 text-sm" />
                <PrimaryButton class="mt-3" :disabled="revisionForm.processing || !revisionForm.file">Upload</PrimaryButton>
            </form>
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Rev</th>
                            <th class="px-4 py-3">File</th>
                            <th class="px-4 py-3">Uploaded by</th>
                            <th class="px-4 py-3">Notes</th>
                            <th class="px-4 py-3">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="r in item.revisions" :key="r.id">
                            <td class="px-4 py-3 font-medium">v{{ r.revision_number }}</td>
                            <td class="px-4 py-3">{{ r.original_name }}</td>
                            <td class="px-4 py-3">{{ r.uploader?.name || '—' }}</td>
                            <td class="px-4 py-3">{{ r.notes || '—' }}</td>
                            <td class="px-4 py-3 text-xs">{{ new Date(r.created_at).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-else-if="tab === 'approvals'" class="space-y-3">
            <div v-for="a in item.approvals" :key="a.id" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <StatusBadge :status="a.status" />
                        <span class="ml-2 text-slate-500">Revision {{ a.revision?.revision_number }}</span>
                    </div>
                    <div class="text-xs text-slate-400">{{ new Date(a.requested_at).toLocaleString() }}</div>
                </div>
                <div class="mt-1 text-xs text-slate-500">Requested by {{ a.requester?.name }}</div>
                <div v-if="a.feedback" class="mt-2 rounded-lg bg-orange-50 px-3 py-2 text-orange-900">{{ a.feedback }}</div>
                <button
                    v-if="a.status === 'pending'"
                    type="button"
                    class="mt-2 text-xs text-rose-700 hover:underline"
                    @click="router.post(route('client-approvals.cancel', a.id))"
                >Cancel approval</button>
            </div>
            <p v-if="!item.approvals?.length" class="text-sm text-slate-500">No client approvals yet.</p>

            <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4">
                <h3 class="text-sm font-semibold">Internal Reviews</h3>
                <div v-for="r in item.internal_reviews" :key="r.id" class="mt-2 border-t border-slate-100 pt-2 text-sm">
                    <StatusBadge :status="r.status" />
                    <span class="ml-2 text-slate-500">Rev {{ r.content_revision?.revision_number || '—' }}</span>
                    <div class="text-xs text-slate-400">{{ r.submitter?.name }} → {{ r.reviewer?.name || 'pending' }}</div>
                    <div v-if="r.feedback" class="mt-1 text-slate-700">{{ r.feedback }}</div>
                </div>
            </div>
        </div>

        <div v-else-if="tab === 'comments'" class="max-w-2xl space-y-3">
            <form class="rounded-xl border border-slate-200 bg-white p-4" @submit.prevent="addComment">
                <textarea v-model="commentForm.comment" rows="3" class="w-full rounded-md border-slate-300 text-sm" required />
                <select v-model="commentForm.visibility" class="mt-2 rounded-md border-slate-300 text-sm">
                    <option value="internal">Internal</option>
                    <option value="client_visible">Client visible</option>
                </select>
                <PrimaryButton class="mt-2" :disabled="commentForm.processing">Add comment</PrimaryButton>
            </form>
            <div v-for="c in item.comments" :key="c.id" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                <div class="flex justify-between gap-2">
                    <span class="font-medium">{{ c.user?.name }}</span>
                    <span class="text-xs text-slate-400">{{ c.visibility }} · {{ new Date(c.created_at).toLocaleString() }}</span>
                </div>
                <p class="mt-1 whitespace-pre-wrap text-slate-700">{{ c.comment }}</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
