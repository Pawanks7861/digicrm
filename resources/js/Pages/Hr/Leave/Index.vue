<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    requests: Object,
    balances: Array,
    leaveTypes: Array,
    can: Object,
});

const form = useForm({
    leave_type_id: props.leaveTypes?.[0]?.id || '',
    start_date: '',
    end_date: '',
    start_unit: 'full_day',
    end_unit: 'full_day',
    reason: '',
});

const submit = () => form.post(route('hr.leave.store'), { onSuccess: () => form.reset('reason') });
const act = (id, action) => router.post(route(`hr.leave.${action}`, id), {}, { preserveScroll: true });
const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head title="Leave" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('hr.dashboard')" class="text-sm text-teal-700 hover:underline">← People & HR</Link>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">Leave</h1>
            <p class="mt-1 text-sm text-slate-500">Balances use a ledger. Pending requests reserve days until approved or cancelled.</p>
        </div>

        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="b in balances" :key="b.leave_type_id" class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase text-slate-400">{{ b.code }}</div>
                <div class="mt-1 text-xl font-semibold">{{ b.balance }}</div>
                <div class="text-xs text-slate-500">{{ b.name }}</div>
            </div>
        </div>

        <form v-if="can.create" class="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2" @submit.prevent="submit">
            <div>
                <InputLabel value="Leave type" />
                <select v-model="form.leave_type_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm" required>
                    <option v-for="t in leaveTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
            </div>
            <div><InputLabel value="Reason" /><TextInput v-model="form.reason" class="mt-1 w-full" /></div>
            <div><InputLabel value="Start" /><TextInput v-model="form.start_date" type="date" class="mt-1 w-full" required /></div>
            <div><InputLabel value="End" /><TextInput v-model="form.end_date" type="date" class="mt-1 w-full" required /></div>
            <div>
                <InputLabel value="Start unit" />
                <select v-model="form.start_unit" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                    <option value="full_day">Full day</option>
                    <option value="first_half">First half</option>
                    <option value="second_half">Second half</option>
                </select>
            </div>
            <div>
                <InputLabel value="End unit" />
                <select v-model="form.end_unit" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                    <option value="full_day">Full day</option>
                    <option value="first_half">First half</option>
                    <option value="second_half">Second half</option>
                </select>
            </div>
            <div class="sm:col-span-2"><PrimaryButton :disabled="form.processing">Submit leave</PrimaryButton></div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Dates</th>
                        <th class="px-4 py-3">Days</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in requests.data" :key="r.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ r.employee?.first_name }} {{ r.employee?.last_name || '' }}</td>
                        <td class="px-4 py-3">{{ r.leave_type?.name }}</td>
                        <td class="px-4 py-3">{{ r.start_date }} → {{ r.end_date }}</td>
                        <td class="px-4 py-3">{{ r.days }}</td>
                        <td class="px-4 py-3 capitalize">{{ label(r.status) }}</td>
                        <td class="px-4 py-3 text-right">
                            <template v-if="can.approve && r.status === 'pending'">
                                <button type="button" class="mr-2 text-teal-700" @click="act(r.id, 'approve')">Approve</button>
                                <button type="button" class="text-rose-700" @click="act(r.id, 'reject')">Reject</button>
                            </template>
                            <button v-else-if="r.status === 'pending' || r.status === 'approved'" type="button" class="text-slate-600" @click="act(r.id, 'cancel')">Cancel</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
