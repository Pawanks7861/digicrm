<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: { type: Object, default: null },
    roles: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    phone: props.user?.phone || '',
    job_title: props.user?.job_title || '',
    role: props.user?.role || props.roles[0] || 'team_member',
    is_active: props.user ? !!props.user.is_active : true,
    password: '',
    password_confirmation: '',
});

function submit() {
    if (props.user) {
        form.put(route('settings.users.update', props.user.id));
    } else {
        form.post(route('settings.users.store'));
    }
}
</script>

<template>
    <Head :title="user ? 'Edit user' : 'Add user'" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('settings.users.index')" class="text-sm text-mute">Users</Link>
            <h1 class="text-2xl font-semibold tracking-tight">{{ user ? 'Edit user' : 'Add user' }}</h1>
        </div>
        <form class="max-w-xl space-y-3 rounded-card bg-card p-5 shadow-sm" @submit.prevent="submit">
            <div><InputLabel value="Name" /><TextInput v-model="form.name" class="mt-1 w-full" required /></div>
            <p v-if="form.errors.name" class="text-sm text-red-700">{{ form.errors.name }}</p>
            <div><InputLabel value="Email" /><TextInput v-model="form.email" type="email" class="mt-1 w-full" required /></div>
            <p v-if="form.errors.email" class="text-sm text-red-700">{{ form.errors.email }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><InputLabel value="Phone" /><TextInput v-model="form.phone" class="mt-1 w-full" /></div>
                <div><InputLabel value="Job title" /><TextInput v-model="form.job_title" class="mt-1 w-full" /></div>
            </div>
            <div>
                <InputLabel value="Role" />
                <select v-model="form.role" class="mt-1 w-full rounded-[0.9rem] border-line bg-white text-sm">
                    <option v-for="item in roles" :key="item" :value="item">{{ item.replaceAll('_', ' ') }}</option>
                </select>
                <p v-if="form.errors.role" class="mt-1 text-sm text-red-700">{{ form.errors.role }}</p>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_active" type="checkbox" class="rounded border-line text-accent" />
                Active — can sign in
            </label>
            <div>
                <InputLabel :value="user ? 'New password (optional)' : 'Password'" />
                <TextInput v-model="form.password" type="password" class="mt-1 w-full" :required="!user" autocomplete="new-password" />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-700">{{ form.errors.password }}</p>
            </div>
            <div><InputLabel value="Confirm password" /><TextInput v-model="form.password_confirmation" type="password" class="mt-1 w-full" autocomplete="new-password" /></div>
            <PrimaryButton :disabled="form.processing">{{ user ? 'Save user' : 'Create user' }}</PrimaryButton>
        </form>
    </AuthenticatedLayout>
</template>
