<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    conversations: { type: Array, default: () => [] },
    activeId: { type: Number, default: null },
    messages: { type: Object, default: () => ({ data: [], next_before: null }) },
    detail: { type: Object, default: null },
    can: { type: Object, default: () => ({}) },
    recoveryGroups: { type: Array, default: () => [] },
    maxKb: { type: Number, default: 25600 },
    delivery: { type: String, default: 'polling' },
});

const list = ref(props.conversations || []);
const activeId = ref(props.activeId);
const thread = ref(props.messages?.data || []);
const nextBefore = ref(props.messages?.next_before || null);
const info = ref(props.detail);
const query = ref('');
const body = ref('');
const reply = ref(null);
const error = ref('');
const pane = ref(props.activeId ? 'chat' : 'list');
const infoOpen = ref(false);
const composer = ref(null);
const people = ref([]);
const personQuery = ref('');
const showDirect = ref(false);
const showGroup = ref(false);
const pickerMode = ref('direct');
const recoverIds = ref({});
const groupForm = ref({ title: '', description: '', members: [] });
const files = ref([]);
const scroller = ref(null);

const active = computed(() => list.value.find((row) => row.id === activeId.value) || info.value?.conversation || null);
const maxLabel = computed(() => `${Math.round(props.maxKb / 1024)} MB`);

function wait(hidden) {
    return hidden ? 30000 : 4000;
}
function listWait(hidden) {
    return hidden ? 30000 : 12000;
}

let messageTimer;
let listTimer;
let stopped = false;

async function refreshList() {
    const { data } = await window.axios.get(route('chat.feed'), { params: { q: query.value || undefined } });
    list.value = data.conversations || [];
}
async function refreshThread(before = null) {
    if (!activeId.value) return;
    const { data } = await window.axios.get(route('chat.conversations.show', activeId.value), {
        params: before ? { before } : {},
    });
    if (before) {
        thread.value = [...(data.messages?.data || []), ...thread.value];
        nextBefore.value = data.messages?.next_before || null;
        return;
    }
    thread.value = data.messages?.data || [];
    nextBefore.value = data.messages?.next_before || null;
    if (data.detail) info.value = data.detail;
    scrollDown();
}
function scheduleMessages() {
    clearTimeout(messageTimer);
    messageTimer = setTimeout(async () => {
        if (stopped) return;
        if (activeId.value && pane.value !== 'list') {
            try { await refreshThread(); } catch (e) { /* keep polling */ }
        }
        scheduleMessages();
    }, wait(document.hidden));
}
function scheduleList() {
    clearTimeout(listTimer);
    listTimer = setTimeout(async () => {
        if (stopped) return;
        try { await refreshList(); } catch (e) { /* keep polling */ }
        scheduleList();
    }, listWait(document.hidden));
}

function openConversation(id) {
    activeId.value = id;
    pane.value = 'chat';
    infoOpen.value = false;
    reply.value = null;
    router.get(route('chat.index'), { conversation: id }, {
        preserveState: true,
        preserveScroll: true,
        only: ['messages', 'detail', 'activeId', 'conversations'],
        onSuccess: (page) => {
            thread.value = page.props.messages?.data || [];
            nextBefore.value = page.props.messages?.next_before || null;
            info.value = page.props.detail;
            list.value = page.props.conversations || list.value;
            scrollDown();
        },
    });
}

async function searchPeople() {
    const { data } = await window.axios.get(route('chat.users'), { params: { q: personQuery.value || undefined } });
    people.value = data.users || [];
}
async function startDirect(user) {
    const { data } = await window.axios.post(route('chat.direct'), { user_id: user.id });
    showDirect.value = false;
    openConversation(data.id);
}
function toggleMember(id) {
    const members = groupForm.value.members;
    groupForm.value.members = members.includes(id) ? members.filter((row) => row !== id) : [...members, id];
}
async function createGroup() {
    const form = new FormData();
    form.append('title', groupForm.value.title);
    if (groupForm.value.description) form.append('description', groupForm.value.description);
    groupForm.value.members.forEach((id) => form.append('members[]', id));
    const { data } = await window.axios.post(route('chat.groups.store'), form);
    showGroup.value = false;
    groupForm.value = { title: '', description: '', members: [] };
    openConversation(data.id);
}

function onFiles(event) {
    files.value = Array.from(event.target.files || []);
}
function onKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        send();
    }
}
async function send() {
    if (!activeId.value) return;
    error.value = '';
    const form = new FormData();
    if (body.value.trim()) form.append('body', body.value.trim());
    if (reply.value) form.append('reply_to_message_id', reply.value.id);
    files.value.forEach((file) => form.append('files[]', file));
    try {
        await window.axios.post(route('chat.messages.store', activeId.value), form);
        body.value = '';
        reply.value = null;
        files.value = [];
        if (composer.value) composer.value.value = '';
        await refreshThread();
        await refreshList();
    } catch (e) {
        error.value = e.response?.data?.message || e.response?.data?.errors?.body?.[0] || e.response?.data?.errors?.files?.[0] || 'Message could not be sent.';
    }
}
async function editMessage(message) {
    const next = window.prompt('Edit message', message.body || '');
    if (!next || !next.trim()) return;
    await window.axios.put(route('chat.messages.update', message.id), { body: next.trim() });
    await refreshThread();
}
async function deleteMessage(message) {
    if (!window.confirm('Delete this message?')) return;
    await window.axios.delete(route('chat.messages.destroy', message.id));
    await refreshThread();
}
async function mute(duration) {
    await window.axios.post(route('chat.conversations.mute', activeId.value), { duration });
    await refreshThread();
}
async function saveGroup() {
    await window.axios.put(route('chat.conversations.update', activeId.value), {
        title: info.value.conversation.title,
        description: info.value.conversation.description,
    });
    await refreshList();
}
async function addMember(user) {
    await window.axios.post(route('chat.conversations.members.store', activeId.value), { user_id: user.id });
    showDirect.value = false;
    await refreshThread();
}
async function removeMember(member) {
    await window.axios.delete(route('chat.conversations.members.destroy', [activeId.value, member.id]));
    await refreshThread();
}
async function promote(member) {
    await window.axios.post(route('chat.conversations.members.promote', [activeId.value, member.id]));
    await refreshThread();
}
async function demote(member) {
    try {
        await window.axios.post(route('chat.conversations.members.demote', [activeId.value, member.id]));
        await refreshThread();
    } catch (e) {
        error.value = e.response?.data?.errors?.admin?.[0] || 'Could not change admin role.';
    }
}
async function leaveGroup() {
    try {
        await window.axios.post(route('chat.conversations.leave', activeId.value));
        activeId.value = null;
        pane.value = 'list';
        await refreshList();
    } catch (e) {
        error.value = e.response?.data?.errors?.admin?.[0] || 'Could not leave the group.';
    }
}
async function recover(group, userId) {
    await window.axios.post(route('chat.conversations.recover', group.id), { user_id: userId });
    error.value = '';
}
function scrollDown() {
    requestAnimationFrame(() => {
        if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight;
    });
}
function sizeLabel(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
function timeLabel(value) {
    if (!value) return '';
    return new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

watch(() => props.conversations, (rows) => { list.value = rows || []; });
onMounted(() => {
    scheduleMessages();
    scheduleList();
    scrollDown();
});
onBeforeUnmount(() => {
    stopped = true;
    clearTimeout(messageTimer);
    clearTimeout(listTimer);
});
</script>

<template>
    <Head title="Chat" />
    <AuthenticatedLayout>
        <div class="flex h-[calc(100vh-8.5rem)] overflow-hidden rounded-[24px] bg-card/80">
            <section class="flex w-full flex-col border-line/70 lg:w-[26%] lg:border-r" :class="pane === 'list' ? 'flex' : 'hidden lg:flex'">
                <div class="flex items-center gap-2 p-4">
                    <input v-model="query" type="search" placeholder="Search chats" class="w-full rounded-full border border-line bg-white px-3 py-2 text-sm" @change="refreshList" />
                </div>
                <div class="flex gap-2 px-4 pb-3">
                    <button type="button" class="rounded-full bg-mist px-3 py-1.5 text-xs" @click="pickerMode = 'direct'; showGroup = false; showDirect = true; searchPeople()">New chat</button>
                    <button v-if="can.create_group" type="button" class="rounded-full bg-mist px-3 py-1.5 text-xs" @click="pickerMode = 'group'; showDirect = false; showGroup = true; searchPeople()">Create group</button>
                </div>
                <div class="flex-1 overflow-y-auto px-2 pb-3">
                    <button
                        v-for="row in list"
                        :key="row.id"
                        type="button"
                        class="mb-1 flex w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-left"
                        :class="row.id === activeId ? 'bg-mist' : 'hover:bg-mist/70'"
                        @click="openConversation(row.id)"
                    >
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-sun/80 text-sm font-semibold">
                            <img v-if="row.avatar_url" :src="row.avatar_url" alt="" class="h-full w-full object-cover" />
                            <template v-else>{{ (row.title || '?').charAt(0) }}</template>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="truncate text-sm font-medium">{{ row.title }}</span>
                                <span v-if="row.unread" class="ml-auto rounded-full bg-accent px-1.5 text-[10px] text-white">{{ row.unread }}</span>
                            </span>
                            <span class="block truncate text-xs text-mute">{{ row.last_sender ? row.last_sender + ': ' : '' }}{{ row.last_message || 'No messages yet' }}</span>
                        </span>
                        <span class="text-[10px] text-mute">{{ timeLabel(row.last_message_at) }}</span>
                    </button>
                    <p v-if="!list.length" class="px-3 py-6 text-sm text-mute">No conversations yet.</p>
                </div>
                <div v-if="can.recover" class="border-t border-line/70 p-3 text-xs text-mute">
                    <p class="font-medium text-ink">Group recovery</p>
                    <p class="mt-1">Assign an admin without opening the conversation.</p>
                    <div v-for="group in recoveryGroups" :key="group.id" class="mt-2 flex items-center gap-2">
                        <span class="truncate">{{ group.title }}</span>
                        <input v-model="recoverIds[group.id]" type="number" placeholder="User id" class="w-20 rounded-full border border-line px-2 py-1" />
                        <button type="button" class="rounded-full bg-mist px-2 py-1" @click="recover(group, recoverIds[group.id])">Assign</button>
                    </div>
                </div>
            </section>

            <section class="flex min-w-0 flex-1 flex-col" :class="pane === 'chat' ? 'flex' : 'hidden lg:flex'">
                <header v-if="active" class="flex items-center gap-3 border-b border-line/70 px-4 py-3">
                    <button type="button" class="text-sm text-mute lg:hidden" @click="pane = 'list'">Back</button>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ active.title }}</p>
                        <p class="truncate text-xs text-mute">{{ active.peer?.presence?.label || (active.type === 'group' ? 'Group' : '') }}</p>
                    </div>
                    <button type="button" class="rounded-full bg-mist px-3 py-1.5 text-xs" @click="infoOpen = !infoOpen; pane = 'info'">Info</button>
                </header>
                <div ref="scroller" class="flex-1 space-y-3 overflow-y-auto px-4 py-4">
                    <button v-if="nextBefore" type="button" class="mx-auto block rounded-full bg-mist px-3 py-1 text-xs" @click="refreshThread(nextBefore)">Load older messages</button>
                    <article v-for="message in thread" :key="message.id" class="flex" :class="message.mine ? 'justify-end' : 'justify-start'">
                        <div class="max-w-[80%] rounded-[18px] px-3 py-2 text-sm" :class="message.mine ? 'bg-[#F6D7B8] text-ink' : 'bg-white text-ink'">
                            <p v-if="!message.mine" class="mb-1 text-xs font-medium text-accent">{{ message.sender?.name }}</p>
                            <div v-if="message.reply" class="mb-2 rounded-xl bg-black/5 px-2 py-1 text-xs">
                                <span class="font-medium">{{ message.reply.sender }}</span>
                                <span class="block truncate">{{ message.reply.deleted ? 'Message deleted' : message.reply.body }}</span>
                            </div>
                            <p v-if="message.deleted" class="italic text-mute">Message deleted</p>
                            <p v-else class="whitespace-pre-wrap">{{ message.body }}</p>
                            <div v-for="file in message.attachments" :key="file.id" class="mt-2">
                                <img v-if="file.kind === 'image'" :src="file.url" :alt="file.name" class="max-h-48 rounded-xl" />
                                <video v-else-if="file.kind === 'video'" :src="file.url" controls class="max-h-48 rounded-xl" />
                                <audio v-else-if="file.kind === 'audio'" :src="file.url" controls />
                                <a v-else :href="file.url + '?download=1'" class="flex items-center gap-2 rounded-xl bg-black/5 px-2 py-1">
                                    <span>{{ file.kind === 'pdf' ? 'PDF' : 'File' }}</span>
                                    <span class="truncate">{{ file.name }}</span>
                                    <span class="text-xs text-mute">{{ sizeLabel(file.size) }}</span>
                                </a>
                            </div>
                            <p class="mt-1 text-[10px] text-mute">
                                {{ timeLabel(message.created_at) }}
                                <span v-if="message.edited_at"> · Edited</span>
                                <span v-if="message.mine && active?.type === 'direct'"> · {{ message.seen ? 'Seen' : 'Sent' }}</span>
                                <span v-else-if="message.mine && message.seen"> · Seen by {{ message.seen }}</span>
                            </p>
                            <div v-if="message.mine && !message.deleted" class="mt-1 flex gap-2 text-[10px]">
                                <button v-if="message.body" type="button" @click="editMessage(message)">Edit</button>
                                <button type="button" @click="deleteMessage(message)">Delete</button>
                                <button type="button" @click="reply = message">Reply</button>
                            </div>
                            <button v-else-if="!message.deleted" type="button" class="mt-1 text-[10px]" @click="reply = message">Reply</button>
                        </div>
                    </article>
                    <p v-if="active && !thread.length" class="text-sm text-mute">No messages yet.</p>
                    <p v-if="!active" class="text-sm text-mute">Choose a conversation.</p>
                </div>
                <form v-if="active" class="border-t border-line/70 p-3" @submit.prevent="send">
                    <div v-if="reply" class="mb-2 flex items-center justify-between rounded-2xl bg-mist px-3 py-2 text-xs">
                        <span class="truncate">Replying to {{ reply.sender?.name }}: {{ reply.body || 'Attachment' }}</span>
                        <button type="button" @click="reply = null">Cancel</button>
                    </div>
                    <p v-if="error" class="mb-2 text-sm text-red-700">{{ error }}</p>
                    <p v-if="files.length" class="mb-2 text-xs text-mute">{{ files.map((file) => file.name).join(', ') }} · max {{ maxLabel }}</p>
                    <div class="flex items-end gap-2">
                        <label class="cursor-pointer rounded-full bg-mist px-3 py-2 text-sm" title="Attach a file">
                            Attach
                            <input type="file" class="sr-only" multiple :disabled="!can.upload" @change="onFiles" />
                        </label>
                        <textarea
                            ref="composer"
                            v-model="body"
                            rows="1"
                            class="max-h-32 min-h-[42px] flex-1 resize-none rounded-[18px] border border-line bg-white px-3 py-2 text-sm"
                            placeholder="Write a message"
                            @keydown="onKeydown"
                        />
                        <button type="submit" class="rounded-full bg-accent px-4 py-2 text-sm text-white">Send</button>
                    </div>
                </form>
            </section>

            <aside class="w-full border-line/70 bg-white/70 lg:w-[24%] lg:border-l" :class="infoOpen || pane === 'info' ? 'flex flex-col' : 'hidden'">
                <div v-if="info" class="flex-1 overflow-y-auto p-4">
                    <button type="button" class="mb-3 text-sm text-mute lg:hidden" @click="pane = 'chat'; infoOpen = false">Back to chat</button>
                    <div class="mb-4 flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl bg-sun/80 text-lg font-semibold">
                        <img v-if="info.conversation.avatar_url" :src="info.conversation.avatar_url" alt="" class="h-full w-full object-cover" />
                        <template v-else>{{ (info.conversation.title || '?').charAt(0) }}</template>
                    </div>
                    <template v-if="info.can_manage">
                        <input v-model="info.conversation.title" class="w-full rounded-xl border border-line px-3 py-2 text-sm font-semibold" />
                        <textarea v-model="info.conversation.description" rows="2" class="mt-2 w-full rounded-xl border border-line px-3 py-2 text-sm" placeholder="Description" />
                        <button type="button" class="mt-2 rounded-full bg-accent px-3 py-1.5 text-xs text-white" @click="saveGroup">Save group</button>
                    </template>
                    <template v-else>
                        <h2 class="text-lg font-semibold">{{ info.conversation.title }}</h2>
                        <p class="mt-1 text-sm text-mute">{{ info.conversation.description }}</p>
                    </template>
                    <p v-if="info.conversation.created_by" class="mt-3 text-xs text-mute">Created by {{ info.conversation.created_by.name }}</p>
                    <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-mute">Members</h3>
                    <ul class="mt-2 space-y-2">
                        <li v-for="member in info.members" :key="member.id" class="flex items-center gap-2 text-sm">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-mist text-xs">{{ (member.name || '?').charAt(0) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate">{{ member.name }}</span>
                                <span class="text-[10px] text-mute">{{ member.role === 'admin' ? 'Admin' : 'Member' }} · {{ member.app_role }} · {{ member.presence?.label }}</span>
                            </span>
                            <template v-if="info.can_manage">
                                <button v-if="member.role !== 'admin'" type="button" class="text-[10px]" @click="promote(member)">Promote</button>
                                <button v-else type="button" class="text-[10px]" @click="demote(member)">Remove admin</button>
                                <button type="button" class="text-[10px]" @click="removeMember(member)">Remove</button>
                            </template>
                        </li>
                    </ul>
                    <div v-if="info.can_manage" class="mt-3">
                        <button type="button" class="text-xs text-accent" @click="pickerMode = 'member'; showGroup = false; showDirect = true; searchPeople()">Add member</button>
                    </div>
                    <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-mute">Shared files</h3>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li v-for="file in info.files" :key="file.id">
                            <a :href="file.url + '?download=1'" class="truncate text-accent">{{ file.name }}</a>
                        </li>
                        <li v-if="!info.files?.length" class="text-mute">No files yet.</li>
                    </ul>
                    <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-mute">Links</h3>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li v-for="link in info.links" :key="link" class="truncate"><a :href="link" class="text-accent" target="_blank" rel="noreferrer">{{ link }}</a></li>
                    </ul>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <button type="button" class="rounded-full bg-mist px-3 py-1 text-xs" @click="mute('1h')">Mute 1 hour</button>
                        <button type="button" class="rounded-full bg-mist px-3 py-1 text-xs" @click="mute('8h')">Mute 8 hours</button>
                        <button type="button" class="rounded-full bg-mist px-3 py-1 text-xs" @click="mute('1d')">Mute 1 day</button>
                        <button type="button" class="rounded-full bg-mist px-3 py-1 text-xs" @click="mute('forever')">Mute until enabled</button>
                        <button type="button" class="rounded-full bg-mist px-3 py-1 text-xs" @click="mute('off')">Unmute</button>
                        <button v-if="info.conversation.type === 'group'" type="button" class="rounded-full bg-mist px-3 py-1 text-xs" @click="leaveGroup">Leave group</button>
                    </div>
                </div>
            </aside>
        </div>

        <div v-if="showDirect || showGroup" class="fixed inset-0 z-50 flex items-end justify-center bg-ink/30 p-4 sm:items-center" @click.self="showDirect = false; showGroup = false">
            <div class="w-full max-w-md rounded-[24px] bg-card p-5">
                <h2 class="text-lg font-semibold">{{ pickerMode === 'group' ? 'Create group' : (pickerMode === 'member' ? 'Add member' : 'New chat') }}</h2>
                <div v-if="showGroup" class="mt-3 space-y-2">
                    <input v-model="groupForm.title" placeholder="Group name" class="w-full rounded-xl border border-line px-3 py-2 text-sm" />
                    <textarea v-model="groupForm.description" placeholder="Description (optional)" rows="2" class="w-full rounded-xl border border-line px-3 py-2 text-sm" />
                </div>
                <input v-model="personQuery" placeholder="Search teammates" class="mt-3 w-full rounded-full border border-line px-3 py-2 text-sm" @input="searchPeople" />
                <ul class="mt-3 max-h-64 space-y-1 overflow-y-auto">
                    <li v-for="person in people" :key="person.id">
                        <button type="button" class="flex w-full items-center gap-3 rounded-2xl px-2 py-2 text-left hover:bg-mist" @click="pickerMode === 'group' ? toggleMember(person.id) : (pickerMode === 'member' ? addMember(person) : startDirect(person))">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-sun/80 text-sm">{{ person.name.charAt(0) }}</span>
                            <span>
                                <span class="block text-sm font-medium">{{ person.name }}</span>
                                <span class="text-xs text-mute">{{ person.role }}</span>
                            </span>
                            <span v-if="groupForm.members.includes(person.id)" class="ml-auto text-xs text-accent">Added</span>
                        </button>
                    </li>
                </ul>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-full px-3 py-2 text-sm" @click="showDirect = false; showGroup = false">Close</button>
                    <button v-if="showGroup" type="button" class="rounded-full bg-accent px-4 py-2 text-sm text-white" @click="createGroup">Create</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
