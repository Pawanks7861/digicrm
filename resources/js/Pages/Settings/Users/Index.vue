<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    users: Object,
    filters: Object,
    roles: Array,
    canManage: Boolean,
});

const q = ref(props.filters.q || '');
const role = ref(props.filters.role || '');
const status = ref(props.filters.status || '');

function apply() {
    router.get(route('settings.users.index'), {
        q: q.value || undefined,
        role: role.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Users" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm text-mute">Settings</p>
                <h1 class="text-2xl font-semibold tracking-tight">Users</h1>
            </div>
            <Link v-if="canManage" :href="route('settings.users.create')" class="rounded-full bg-accent px-4 py-2 text-sm text-white">Add user</Link>
        </div>

        <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="apply">
            <input v-model="q" type="search" placeholder="Search name or email" class="rounded-full border border-line bg-white px-3 py-2 text-sm" />
            <select v-model="role" class="rounded-full border border-line bg-white px-3 py-2 text-sm">
                <option value="">All roles</option>
                <option v-for="item in roles" :key="item" :value="item">{{ item.replaceAll('_', ' ') }}</option>
            </select>
            <select v-model="status" class="rounded-full border border-line bg-white px-3 py-2 text-sm">
                <option value="">Any status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <button type="submit" class="rounded-full bg-mist px-4 py-2 text-sm">Filter</button>
        </form>

        <div class="overflow-hidden rounded-card bg-card shadow-sm">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-mute">
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users.data" :key="user.id" class="border-t border-line/70">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ user.name }}</div>
                            <div class="text-xs text-mute">{{ user.email }}<span v-if="user.job_title"> · {{ user.job_title }}</span></div>
                        </td>
                        <td class="px-4 py-3 capitalize">{{ (user.role || '—').replaceAll('_', ' ') }}</td>
                        <td class="px-4 py-3">{{ user.is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link v-if="canManage && !user.is_client" :href="route('settings.users.edit', user.id)" class="text-accent">Edit</Link>
                        </td>
                    </tr>
                    <tr v-if="!users.data.length">
                        <td colspan="4" class="px-4 py-8 text-center text-mute">No users match this filter.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="users.links?.length > 3" class="mt-4 flex flex-wrap gap-2 text-sm">
            <Link v-for="link in users.links" :key="link.label" :href="link.url || ''" class="rounded-full px-3 py-1" :class="link.active ? 'bg-accent text-white' : 'bg-card text-mute'" v-html="link.label" />
        </div>
    </AuthenticatedLayout>
</template>
