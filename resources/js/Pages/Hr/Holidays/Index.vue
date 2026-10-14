<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    year: Number,
    holidays: Array,
    can: Object,
});

const form = useForm({
    name: '',
    date: '',
    category: 'public',
    is_paid: true,
    is_optional: false,
    is_recurring: false,
    is_active: true,
    description: '',
});

const save = () => form.post(route('hr.holidays.store'));
const changeYear = (event) => router.get(route('hr.holidays.index'), { year: event.target.value }, { preserveState: true });
const upload = (event) => {
    const data = new FormData();
    data.append('file', event.target.files[0]);
    router.post(route('hr.holidays.import'), data);
};
</script>

<template>
    <Head title="Holidays" />
    <AuthenticatedLayout>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Holidays {{ year }}</h1>
            <div class="flex items-center gap-2 text-sm">
                <input :value="year" type="number" class="w-24 rounded-lg border-slate-200" @change="changeYear" />
                <a :href="route('hr.holidays.export', { year })" class="rounded-lg border border-slate-200 px-3 py-1.5">Export</a>
                <input v-if="can.manage" type="file" accept=".csv,text/csv" @change="upload" />
            </div>
        </div>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Name</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Paid</th></tr>
                </thead>
                <tbody>
                    <tr v-for="holiday in holidays" :key="holiday.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ String(holiday.date).slice(0, 10) }}</td>
                        <td class="px-4 py-3">{{ holiday.name }}</td>
                        <td class="px-4 py-3 capitalize">{{ holiday.category }}</td>
                        <td class="px-4 py-3">{{ holiday.is_paid ? 'Paid' : 'Unpaid' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <form v-if="can.manage" class="mt-4 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-3" @submit.prevent="save">
            <input v-model="form.name" required placeholder="Holiday name" class="rounded-lg border-slate-200 text-sm" />
            <input v-model="form.date" required type="date" class="rounded-lg border-slate-200 text-sm" />
            <select v-model="form.category" class="rounded-lg border-slate-200 text-sm">
                <option value="public">Public</option>
                <option value="company">Company</option>
                <option value="regional">Regional</option>
                <option value="optional">Optional</option>
                <option value="restricted">Restricted</option>
                <option value="custom">Custom</option>
            </select>
            <label class="text-sm"><input v-model="form.is_paid" type="checkbox" /> Paid</label>
            <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm text-white">Add holiday</button>
        </form>
    </AuthenticatedLayout>
</template>
