<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    users: Array,
    rates: Array,
    history: Array,
    canManage: Boolean,
});

const form = useForm({
    user_id: '',
    hourly_cost: '',
    currency: 'INR',
    effective_from: new Date().toISOString().slice(0, 10),
    effective_to: '',
});

const submit = () => {
    form.post(route('cost-rates.store'), { onSuccess: () => form.reset('hourly_cost', 'effective_to') });
};

const money = (v) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(v || 0));
</script>

<template>
    <Head title="Internal Cost Rates" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold">Internal Hourly Cost Rates</h1>
            <p class="mt-1 text-sm text-slate-500">Operational costing only — not salary or payroll.</p>
        </div>

        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Current Rate</th>
                        <th class="px-4 py-3">Effective From</th>
                        <th class="px-4 py-3">Effective To</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="r in rates" :key="r.id">
                        <td class="px-4 py-3">{{ r.user?.name }}</td>
                        <td class="px-4 py-3 font-medium">{{ money(r.hourly_cost) }}/hr</td>
                        <td class="px-4 py-3">{{ r.effective_from }}</td>
                        <td class="px-4 py-3">{{ r.effective_to || 'Current' }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!rates?.length" class="p-4 text-sm text-slate-500">No current rates configured.</p>
        </div>

        <form v-if="canManage" class="mt-8 max-w-xl space-y-4 rounded-xl border bg-white p-5" @submit.prevent="submit">
            <h2 class="text-sm font-semibold">Set / Update Rate</h2>
            <div>
                <InputLabel value="User" />
                <select v-model="form.user_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <option value="">Select…</option>
                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}{{ u.job_title ? ` · ${u.job_title}` : '' }}</option>
                </select>
                <InputError :message="form.errors.user_id" />
            </div>
            <div>
                <InputLabel value="Internal Hourly Cost" />
                <TextInput v-model="form.hourly_cost" type="number" step="0.01" min="0.01" class="mt-1 w-full" />
                <InputError :message="form.errors.hourly_cost" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <InputLabel value="Effective From" />
                    <TextInput v-model="form.effective_from" type="date" class="mt-1 w-full" />
                    <InputError :message="form.errors.effective_from" />
                </div>
                <div>
                    <InputLabel value="Effective To (optional)" />
                    <TextInput v-model="form.effective_to" type="date" class="mt-1 w-full" />
                </div>
            </div>
            <PrimaryButton :disabled="form.processing">Save Rate</PrimaryButton>
        </form>

        <h2 class="mt-8 text-sm font-semibold">History</h2>
        <ul class="mt-2 space-y-2 text-sm">
            <li v-for="h in history" :key="h.id" class="rounded-lg border bg-white px-4 py-2 flex justify-between">
                <span>{{ h.user?.name }} · {{ money(h.hourly_cost) }}/hr</span>
                <span class="text-slate-500">{{ h.effective_from }} → {{ h.effective_to || 'Current' }}</span>
            </li>
        </ul>
    </AuthenticatedLayout>
</template>
