<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({ clients: Array, taxRates: Array, quotation: Object });

const form = useForm({
    client_id: props.quotation?.client_id || '',
    issue_date: props.quotation?.issue_date || new Date().toISOString().slice(0, 10),
    valid_until: props.quotation?.valid_until || '',
    discount_type: props.quotation?.discount_type || '',
    discount_value: props.quotation?.discount_value || 0,
    tax_treatment: props.quotation?.tax_treatment || 'auto',
    notes: props.quotation?.notes || '',
    terms: props.quotation?.terms || '',
    items: props.quotation?.items?.length
        ? props.quotation.items.map((i) => ({
            name: i.name, description: i.description || '', quantity: i.quantity, unit: i.unit || 'Nos',
            unit_rate: i.unit_rate, discount_amount: i.discount_amount || 0, tax_rate_id: i.tax_rate_id || '',
            item_type: i.item_type || 'custom', hsn_sac: i.hsn_sac || '',
        }))
        : [{ name: '', description: '', quantity: 1, unit: 'Nos', unit_rate: 0, discount_amount: 0, tax_rate_id: props.taxRates?.[0]?.id || '', item_type: 'custom', hsn_sac: '' }],
});

const addLine = () => form.items.push({ name: '', description: '', quantity: 1, unit: 'Nos', unit_rate: 0, discount_amount: 0, tax_rate_id: props.taxRates?.[0]?.id || '', item_type: 'custom', hsn_sac: '' });
const removeLine = (i) => { if (form.items.length > 1) form.items.splice(i, 1); };

const submit = () => {
    if (props.quotation) {
        form.put(route('quotations.update', props.quotation.id));
    } else {
        form.post(route('quotations.store'));
    }
};
</script>

<template>
    <Head :title="quotation ? 'Edit Quotation' : 'New Quotation'" />
    <AuthenticatedLayout>
        <h1 class="mb-6 text-2xl font-semibold">{{ quotation ? 'Edit Quotation' : 'New Quotation' }}</h1>
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
                        <option value="auto">Auto (state-based)</option>
                        <option value="intra">Intra-state (CGST+SGST)</option>
                        <option value="inter">Inter-state (IGST)</option>
                        <option value="exempt">Exempt</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Issue date" />
                    <TextInput v-model="form.issue_date" type="date" class="mt-1 w-full" />
                </div>
                <div>
                    <InputLabel value="Valid until" />
                    <TextInput v-model="form.valid_until" type="date" class="mt-1 w-full" />
                </div>
                <div>
                    <InputLabel value="Discount type" />
                    <select v-model="form.discount_type" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">None</option>
                        <option value="percent">Percent</option>
                        <option value="fixed">Fixed</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Discount value" />
                    <TextInput v-model="form.discount_value" type="number" step="0.01" class="mt-1 w-full" />
                </div>
            </div>

            <h2 class="pt-4 text-sm font-semibold">Items</h2>
            <div v-for="(item, i) in form.items" :key="i" class="grid gap-2 rounded-xl border bg-white p-3 sm:grid-cols-6">
                <TextInput v-model="item.name" placeholder="Name" class="sm:col-span-2" required />
                <TextInput v-model="item.quantity" type="number" step="0.01" placeholder="Qty" />
                <TextInput v-model="item.unit_rate" type="number" step="0.01" placeholder="Rate" />
                <select v-model="item.tax_rate_id" class="rounded-md border-slate-300 text-sm">
                    <option value="">No tax</option>
                    <option v-for="t in taxRates" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <button type="button" class="text-sm text-rose-600" @click="removeLine(i)">Remove</button>
            </div>
            <button type="button" class="text-sm text-teal-700" @click="addLine">+ Add line</button>

            <div>
                <InputLabel value="Notes" />
                <textarea v-model="form.notes" class="mt-1 w-full rounded-md border-slate-300 text-sm" rows="2" />
            </div>
            <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
        </form>
    </AuthenticatedLayout>
</template>
