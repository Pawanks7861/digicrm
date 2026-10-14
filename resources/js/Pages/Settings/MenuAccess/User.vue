<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    subject: { type: Object, required: true },
    menus: { type: Array, default: () => [] },
    canEditRoles: { type: Boolean, default: false },
});

const form = useForm({
    overrides: Object.fromEntries(props.menus.filter((m) => !m.locked).map((m) => [m.key, m.override])),
});

const modes = [
    { value: 'inherit', label: 'Use role', active: 'bg-white text-ink shadow-sm' },
    { value: 'show', label: 'Show', active: 'bg-emerald-500 text-white shadow-sm' },
    { value: 'hide', label: 'Hide', active: 'bg-rose-500 text-white shadow-sm' },
];

function willShow(menu) {
    if (menu.locked || props.subject.is_super_admin) return menu.feature;
    const mode = form.overrides[menu.key];
    const allowed = mode === 'inherit' ? menu.role_visible : mode === 'show';
    return menu.feature && allowed;
}

function resetAll() {
    Object.keys(form.overrides).forEach((key) => { form.overrides[key] = 'inherit'; });
}

function submit() {
    form.put(route('settings.users.menus.update', props.subject.id), { preserveScroll: true, onSuccess: () => form.defaults() });
}

const roleLabel = (r) => (r || 'no role').replaceAll('_', ' ');
</script>

<template>
    <Head :title="`Menus · ${subject.name}`" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <Link :href="route('settings.users.index')" class="text-sm text-mute hover:text-ink">Users</Link>
                <h1 class="text-2xl font-semibold tracking-tight">Menu access · {{ subject.name }}</h1>
                <p class="mt-1 text-sm text-mute">
                    {{ subject.email }} · <span class="capitalize">{{ roleLabel(subject.role) }}</span>.
                    “Use role” follows the
                    <Link v-if="canEditRoles" :href="route('settings.menu-access.index')" class="text-accent">role settings</Link>
                    <span v-else>role settings</span>; Show or Hide applies to this person only.
                </p>
            </div>
            <div v-if="!subject.is_super_admin" class="flex items-center gap-2">
                <button type="button" class="rounded-full bg-mist px-4 py-2 text-sm" @click="resetAll">Reset all to role</button>
                <button type="button" class="rounded-full bg-accent px-4 py-2 text-sm text-white disabled:opacity-50" :disabled="form.processing || !form.isDirty" @click="submit">
                    {{ form.processing ? 'Saving…' : 'Save' }}
                </button>
            </div>
        </div>

        <div v-if="subject.is_super_admin" class="mb-4 rounded-card bg-sky-50 px-4 py-3 text-sm text-sky-900">
            Super Admin always sees every menu, so there is nothing to change here.
        </div>

        <div class="overflow-x-auto rounded-card bg-card shadow-sm">
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr class="border-b border-line/70 text-left text-xs uppercase tracking-wide text-mute">
                        <th class="px-4 py-3">Menu</th>
                        <th class="px-4 py-3">Role default</th>
                        <th class="px-4 py-3">This user</th>
                        <th class="px-4 py-3 text-right">Result</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="menu in menus" :key="menu.key" class="border-t border-line/50">
                        <td class="px-4 py-2.5 font-medium">{{ menu.label }}</td>
                        <td class="px-4 py-2.5">
                            <span v-if="menu.locked" class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-800">Always</span>
                            <span v-else-if="menu.role_visible" class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800">Visible</span>
                            <span v-else class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-800">Hidden</span>
                        </td>
                        <td class="px-4 py-2.5">
                            <span v-if="menu.locked || subject.is_super_admin" class="text-xs text-mute">—</span>
                            <div v-else class="inline-flex rounded-full bg-mist p-0.5">
                                <button
                                    v-for="mode in modes"
                                    :key="mode.value"
                                    type="button"
                                    class="rounded-full px-3 py-1 text-xs transition"
                                    :class="form.overrides[menu.key] === mode.value ? mode.active : 'text-mute hover:text-ink'"
                                    @click="form.overrides[menu.key] = mode.value"
                                >{{ mode.label }}</button>
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <span
                                v-if="!menu.feature"
                                class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600"
                                title="The user's role has no permission for this area. Change the role to give access."
                            >No permission</span>
                            <span v-else-if="willShow(menu)" class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800">Shown</span>
                            <span v-else class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-800">Hidden</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-mute">
            Menus marked “No permission” stay hidden whatever you pick here — the role needs the underlying permission first.
        </p>
    </AuthenticatedLayout>
</template>
