<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ settings: Object, taxRates: Array, canManage: Boolean });

const form = useForm({ ...props.settings });
const tax = useForm({ name: 'GST 18%', percentage: 18, tax_type: 'gst', cgst_percentage: 9, sgst_percentage: 9, igst_percentage: 18 });
</script>

<template>
    <Head title="Billing Settings" />
    <AuthenticatedLayout>
        <h1 class="mb-2 text-2xl font-semibold">Company Billing Settings</h1>
        <p class="mb-6 text-sm text-slate-500">Seller identity for GST & PDFs. Snapshotted onto issued invoices.</p>

        <form v-if="canManage" class="max-w-2xl space-y-3 rounded-xl border bg-white p-5" @submit.prevent="form.put(route('finance.billing-settings.update'))">
            <div><InputLabel value="Legal name" /><TextInput v-model="form.legal_name" class="mt-1 w-full" required /></div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><InputLabel value="State (GST)" /><TextInput v-model="form.state" class="mt-1 w-full" required /></div>
                <div><InputLabel value="GSTIN" /><TextInput v-model="form.gstin" class="mt-1 w-full" /></div>
            </div>
            <div><InputLabel value="Address" /><textarea v-model="form.address" class="mt-1 w-full rounded-md border-slate-300 text-sm" rows="2" /></div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><InputLabel value="Bank" /><TextInput v-model="form.bank_name" class="mt-1 w-full" /></div>
                <div><InputLabel value="UPI" /><TextInput v-model="form.upi_id" class="mt-1 w-full" /></div>
            </div>
            <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
        </form>

        <h2 class="mt-8 text-sm font-semibold">Tax Rates</h2>
        <ul class="mt-2 space-y-1 text-sm">
            <li v-for="t in taxRates" :key="t.id" class="rounded-lg border bg-white px-4 py-2">{{ t.name }} · {{ t.percentage }}% (CGST {{ t.cgst_percentage }} / SGST {{ t.sgst_percentage }} / IGST {{ t.igst_percentage }})</li>
        </ul>
        <form v-if="canManage" class="mt-4 flex flex-wrap gap-2" @submit.prevent="tax.post(route('finance.tax-rates.store'), { onSuccess: () => tax.reset() })">
            <TextInput v-model="tax.name" placeholder="Name" />
            <TextInput v-model="tax.percentage" type="number" step="0.001" placeholder="%" class="w-24" />
            <PrimaryButton>Add rate</PrimaryButton>
        </form>
    </AuthenticatedLayout>
</template>
