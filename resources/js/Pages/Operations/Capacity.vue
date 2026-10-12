<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ users: Array, capacities: Array, defaults: Object, holidays: Array });

const form = useForm({
    user_id: '',
    working_hours_per_day: props.defaults?.working_hours_per_day || 8,
    working_days: props.defaults?.working_days || [1, 2, 3, 4, 5],
    effective_from: new Date().toISOString().slice(0, 10),
});

const holidayForm = useForm({ date: '', name: '', is_working_day_override: false });

const dayLabels = { 1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat', 7: 'Sun' };
const toggleDay = (d) => {
    const i = form.working_days.indexOf(d);
    if (i >= 0) form.working_days.splice(i, 1);
    else form.working_days.push(d);
};
</script>

<template>
    <Head title="Capacity Settings" />
    <AuthenticatedLayout>
        <h1 class="text-2xl font-semibold">Capacity Settings</h1>
        <p class="mt-1 text-sm text-slate-500">Default {{ defaults.working_hours_per_day }}h/day · agency holidays reduce capacity</p>

        <form class="mt-6 max-w-lg space-y-3 rounded-xl border bg-white p-5" @submit.prevent="form.post(route('capacity.store'))">
            <select v-model="form.user_id" class="w-full rounded-md border-slate-300 text-sm" required>
                <option value="">Select user</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
            <input v-model="form.working_hours_per_day" type="number" step="0.5" min="0.5" class="w-full rounded-md border-slate-300 text-sm" />
            <div class="flex flex-wrap gap-2">
                <button v-for="d in [1,2,3,4,5,6,7]" :key="d" type="button" class="rounded border px-2 py-1 text-xs" :class="form.working_days.includes(d) ? 'border-teal-600 bg-teal-50' : 'border-slate-200'" @click="toggleDay(d)">{{ dayLabels[d] }}</button>
            </div>
            <PrimaryButton :disabled="form.processing">Save Capacity</PrimaryButton>
        </form>

        <form class="mt-6 max-w-lg space-y-3 rounded-xl border bg-white p-5" @submit.prevent="holidayForm.post(route('capacity.holidays.store'), { onSuccess: () => holidayForm.reset() })">
            <h3 class="text-sm font-semibold">Agency Holiday</h3>
            <input v-model="holidayForm.date" type="date" class="w-full rounded-md border-slate-300 text-sm" required />
            <input v-model="holidayForm.name" type="text" placeholder="Name" class="w-full rounded-md border-slate-300 text-sm" required />
            <PrimaryButton :disabled="holidayForm.processing">Add Holiday</PrimaryButton>
        </form>

        <ul class="mt-6 space-y-2 text-sm">
            <li v-for="c in capacities" :key="c.id" class="rounded-xl border bg-white px-4 py-3">{{ c.user?.name }} · {{ c.working_hours_per_day }}h/day · from {{ c.effective_from }}</li>
        </ul>
    </AuthenticatedLayout>
</template>
