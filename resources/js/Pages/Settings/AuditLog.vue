<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    logs: Object,
    filters: Object,
    modules: Array,
    users: Array,
});

const q = ref(props.filters.q || '');
const module = ref(props.filters.module || '');
const userId = ref(props.filters.user_id || '');
const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');

function apply() {
    router.get(route('settings.audit-logs.index'), {
        q: q.value || undefined,
        module: module.value || undefined,
        user_id: userId.value || undefined,
        from: from.value || undefined,
        to: to.value || undefined,
    }, { preserveState: true, replace: true });
}

function when(value) {
    if (!value) return '';
    return new Date(value).toLocaleString();
}
</script>

<template>
    <Head title="Audit log" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <p class="text-sm text-mute">Settings</p>
            <h1 class="text-2xl font-semibold tracking-tight">Audit log</h1>
            <p class="mt-1 text-sm text-mute">Sign-ins, page visits, and changes across the CRM. Passwords and chat message text are not stored.</p>
        </div>

        <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="apply">
            <input v-model="q" type="search" placeholder="Search action" class="rounded-full border border-line bg-white px-3 py-2 text-sm" />
            <select v-model="userId" class="rounded-full border border-line bg-white px-3 py-2 text-sm">
                <option value="">All users</option>
                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
            </select>
            <select v-model="module" class="rounded-full border border-line bg-white px-3 py-2 text-sm">
                <option value="">All modules</option>
                <option v-for="item in modules" :key="item" :value="item">{{ item }}</option>
            </select>
            <input v-model="from" type="date" class="rounded-full border border-line bg-white px-3 py-2 text-sm" />
            <input v-model="to" type="date" class="rounded-full border border-line bg-white px-3 py-2 text-sm" />
            <button type="submit" class="rounded-full bg-mist px-4 py-2 text-sm">Filter</button>
        </form>

        <div class="overflow-hidden rounded-card bg-card shadow-sm">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-mute">
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs.data" :key="log.id" class="border-t border-line/70">
                        <td class="whitespace-nowrap px-4 py-3 text-mute">{{ when(log.created_at) }}</td>
                        <td class="px-4 py-3">{{ log.user?.name || 'System' }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ log.action }}</div>
                            <div class="text-xs text-mute">{{ log.module }}<span v-if="log.ip_address"> · {{ log.ip_address }}</span></div>
                        </td>
                        <td class="px-4 py-3 text-mute">{{ log.summary || log.subject || '—' }}</td>
                    </tr>
                    <tr v-if="!logs.data.length">
                        <td colspan="4" class="px-4 py-8 text-center text-mute">No audit entries yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="logs.links?.length > 3" class="mt-4 flex flex-wrap gap-2 text-sm">
            <Link v-for="link in logs.links" :key="link.label" :href="link.url || ''" class="rounded-full px-3 py-1" :class="link.active ? 'bg-accent text-white' : 'bg-card text-mute'" v-html="link.label" />
        </div>
    </AuthenticatedLayout>
</template>
