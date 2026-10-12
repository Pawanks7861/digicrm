<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ clients: Array, taxRates: Array, invoice: Object });

const form = useForm({
    client_id: props.invoice?.client_id || '',
    issue_date: props.invoice?.issue_date || new Date().toISOString().slice(0, 10),
    due_date: props.invoice?.due_date || '',
    discount_type: props.invoice?.discount_type || '',
    discount_value: props.invoice?.discount_value || 0,
    tax_treatment: props.invoice?.tax_treatment || 'auto',
    period_label: props.invoice?.period_label || '',
    notes: props.invoice?.notes || '',
    items: props.invoice?.items?.length
        ? props.invoice.items.map((i) => ({
            name: i.name, description: i.description || '', quantity: i.quantity, unit: i.unit || 'Nos',
            unit_rate: i.unit_rate, discount_amount: i.discount_amount || 0, tax_rate_id: i.tax_rate_id || '',
            item_type: i.item_type || 'custom', hsn_sac: i.hsn_sac || '',
        }))
        : [{ name: '', description: '', quantity: 1, unit: 'Nos', unit_rate: 0, discount_amount: 0, tax_rate_id: props.taxRates?.[0]?.id || '', item_type: 'custom', hsn_sac: '' }],
});

const addLine = () => form.items.push({ name: '', description: '', quantity: 1, unit: 'Nos', unit_rate: 0, discount_amount: 0, tax_rate_id: props.taxRates?.[0]?.id || '', item_type: 'custom', hsn_sac: '' });
const submit = () => props.invoice ? form.put(route('invoices.update', props.invoice.id)) : form.post(route('invoices.store'));
</script>

<template>
    <Head :title="invoice ? 'Edit Invoice' : 'New Invoice'" />
    <AuthenticatedLayout>
        <h1 class="mb-6 text-2xl font-semibold">{{ invoice ? 'Edit Invoice' : 'New Invoice' }}</h1>
        <form class="max-w-4xl space-y-4" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel value="Client" />
                    <select v-model="form.client_id" class="mt-1 w-full rounded-md border-slate-300 text-sm" required>
                        <option value="">Select…</option>
                        <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Tax treatment" />
                    <select v-model="form.tax_treatment" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="auto">Auto</option>
                        <option value="intra">Intra-state</option>
                        <option value="inter">Inter-state</option>
                        <option value="exempt">Exempt</option>
                    </select>
                </div>
                <div><InputLabel value="Issue date" /><TextInput v-model="form.issue_date" type="date" class="mt-1 w-full" /></div>
                <div><InputLabel value="Due date" /><TextInput v-model="form.due_date" type="date" class="mt-1 w-full" /></div>
                <div><InputLabel value="Period label" /><TextInput v-model="form.period_label" class="mt-1 w-full" placeholder="October 2026" /></div>
                <div>
                    <InputLabel value="Discount" />
                    <div class="mt-1 flex gap-2">
                        <select v-model="form.discount_type" class="rounded-md border-slate-300 text-sm">
                            <option value="">None</option>
                            <option value="percent">%</option>
                            <option value="fixed">Fixed</option>
                        </select>
                        <TextInput v-model="form.discount_value" type="number" step="0.01" class="w-full" />
                    </div>
                </div>
            </div>
            <div v-for="(item, i) in form.items" :key="i" class="grid gap-2 rounded-xl border bg-white p-3 sm:grid-cols-5">
                <TextInput v-model="item.name" placeholder="Name" class="sm:col-span-2" required />
                <TextInput v-model="item.quantity" type="number" step="0.01" />
                <TextInput v-model="item.unit_rate" type="number" step="0.01" />
                <select v-model="item.tax_rate_id" class="rounded-md border-slate-300 text-sm">
                    <option value="">No tax</option>
                    <option v-for="t in taxRates" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
            </div>
            <button type="button" class="text-sm text-teal-700" @click="addLine">+ Add line</button>
            <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
        </form>
    </AuthenticatedLayout>
</template>
