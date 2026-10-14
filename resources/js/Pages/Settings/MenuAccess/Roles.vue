<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    menus: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
});

function snapshot() {
    return Object.fromEntries(props.roles.map((role) => [
        role.id,
        Object.fromEntries(role.menus.map((m) => [m.key, m.visible])),
    ]));
}

const original = ref(snapshot());
const state = reactive(snapshot());
const saving = ref(false);

const cell = (role, key) => role.menus.find((m) => m.key === key);

const dirtyRoles = computed(() => props.roles.filter((role) =>
    Object.keys(state[role.id]).some((key) => state[role.id][key] !== original.value[role.id][key]),
));

function toggle(role, menu) {
    if (menu.locked || !cell(role, menu.key).feature) return;
    state[role.id][menu.key] = !state[role.id][menu.key];
}

function setColumn(role, visible) {
    props.menus.forEach((menu) => {
        if (!menu.locked && cell(role, menu.key).feature) state[role.id][menu.key] = visible;
    });
}

function reset() {
    Object.assign(state, snapshot());
}

function save() {
    const queue = [...dirtyRoles.value];
    if (!queue.length) return;
    saving.value = true;
    const next = () => {
        const role = queue.shift();
        if (!role) {
            saving.value = false;
            original.value = snapshot();
            Object.assign(state, snapshot());
            return;
        }
        router.put(route('settings.menu-access.roles.update', role.id), { menus: { ...state[role.id] } }, {
            preserveScroll: true,
            onSuccess: next,
            onError: () => { saving.value = false; },
        });
    };
    next();
}

const label = (name) => name.replaceAll('_', ' ');
</script>

<template>
    <Head title="Menu access" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <Link :href="route('settings.index')" class="text-sm text-mute hover:text-ink">Settings</Link>
                <h1 class="text-2xl font-semibold tracking-tight">Menu access by role</h1>
                <p class="mt-1 max-w-2xl text-sm text-mute">
                    Turn sidebar menus on or off for each role. A hidden menu also blocks its pages for that role.
                    To change a single person, use <Link :href="route('settings.users.index')" class="text-accent">Users → Menus</Link>.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button v-if="dirtyRoles.length" type="button" class="rounded-full bg-mist px-4 py-2 text-sm" :disabled="saving" @click="reset">Discard</button>
                <button
                    type="button"
                    class="rounded-full px-4 py-2 text-sm text-white transition"
                    :class="dirtyRoles.length ? 'bg-accent' : 'cursor-not-allowed bg-accent/40'"
                    :disabled="!dirtyRoles.length || saving"
                    @click="save"
                >
                    {{ saving ? 'Saving…' : dirtyRoles.length ? `Save ${dirtyRoles.length} role${dirtyRoles.length > 1 ? 's' : ''}` : 'Saved' }}
                </button>
            </div>
        </div>

        <div class="mb-3 flex flex-wrap items-center gap-4 text-xs text-mute">
            <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-emerald-500" /> Visible</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-rose-300" /> Hidden</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-slate-200" /> Role has no permission for this area</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-sky-300" /> Always on</span>
        </div>

        <div class="overflow-x-auto rounded-card bg-card shadow-sm">
            <table class="min-w-[760px] w-full text-sm">
                <thead>
                    <tr class="border-b border-line/70 text-left text-xs uppercase tracking-wide text-mute">
                        <th class="sticky left-0 bg-card px-4 py-3">Menu</th>
                        <th v-for="role in roles" :key="role.id" class="px-3 py-3 text-center">
                            <div class="capitalize">{{ label(role.name) }}</div>
                            <div class="mt-1 flex justify-center gap-1 font-normal normal-case tracking-normal">
                                <button type="button" class="rounded-full bg-mist px-2 py-0.5 text-[11px] hover:bg-line" @click="setColumn(role, true)">All</button>
                                <button type="button" class="rounded-full bg-mist px-2 py-0.5 text-[11px] hover:bg-line" @click="setColumn(role, false)">None</button>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="menu in menus" :key="menu.key" class="border-t border-line/50 hover:bg-mist/40">
                        <td class="sticky left-0 bg-card px-4 py-2.5 font-medium">{{ menu.label }}</td>
                        <td v-for="role in roles" :key="role.id + menu.key" class="px-3 py-2.5 text-center">
                            <span v-if="menu.locked" class="inline-flex rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-800">Always</span>
                            <span
                                v-else-if="!cell(role, menu.key).feature"
                                class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-500"
                                title="This role has no permission for this area, so the menu stays hidden."
                            >No access</span>
                            <button
                                v-else
                                type="button"
                                role="switch"
                                :aria-checked="state[role.id][menu.key]"
                                :aria-label="`${menu.label} for ${label(role.name)}`"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                :class="[
                                    state[role.id][menu.key] ? 'bg-emerald-500' : 'bg-rose-300',
                                    state[role.id][menu.key] !== original[role.id][menu.key] ? 'ring-2 ring-accent ring-offset-2' : '',
                                ]"
                                @click="toggle(role, menu)"
                            >
                                <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition" :class="state[role.id][menu.key] ? 'translate-x-5' : 'translate-x-0.5'" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-mute">Super Admin always sees every menu. Dashboard and Settings stay on so nobody gets locked out.</p>
    </AuthenticatedLayout>
</template>
