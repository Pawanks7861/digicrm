<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    package: Object,
    billingCycles: Array,
    statuses: Array,
    units: Array,
});

const isEdit = !!props.package;

const blankItem = () => ({
    service_name: '',
    service_code: '',
    description: '',
    quantity: 1,
    unit: 'Post',
    overage_rate: 0,
    is_unlimited: false,
    sort_order: 0,
});

const form = useForm({
    name: props.package?.name || '',
    code: props.package?.code || '',
    description: props.package?.description || '',
    billing_cycle: props.package?.billing_cycle || 'monthly',
    base_price: props.package?.base_price ?? 0,
    currency: props.package?.currency || 'INR',
    status: props.package?.status || 'active',
    items: props.package?.items?.length
        ? props.package.items.map((i) => ({
              service_name: i.service_name,
              service_code: i.service_code || '',
              description: i.description || '',
              quantity: i.quantity,
              unit: i.unit,
              overage_rate: i.overage_rate,
              is_unlimited: !!i.is_unlimited,
              sort_order: i.sort_order,
          }))
        : [blankItem()],
});

const addItem = () => form.items.push(blankItem());
const removeItem = (index) => {
    if (form.items.length > 1) form.items.splice(index, 1);
};

const submit = () => {
    if (isEdit) {
        form.put(route('service-packages.update', props.package.id));
    } else {
        form.post(route('service-packages.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Package' : 'New Package'" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('service-packages.index')" class="text-sm text-teal-700 hover:underline">← Service Packages</Link>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ isEdit ? 'Edit Package' : 'New Package' }}</h1>
        </div>

        <form class="max-w-5xl space-y-6" @submit.prevent="submit">
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <InputLabel value="Package Name" />
                        <TextInput v-model="form.name" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Package Code" />
                        <TextInput v-model="form.code" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel value="Billing Cycle" />
                        <select v-model="form.billing_cycle" class="mt-1 w-full rounded-md border-slate-300 text-sm" required>
                            <option v-for="c in billingCycles" :key="c" :value="c">{{ c.replaceAll('_', ' ') }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Base Price" />
                        <TextInput v-model="form.base_price" type="number" step="0.01" min="0" class="mt-1 block w-full" required />
                    </div>
                    <div>
                        <InputLabel value="Currency" />
                        <TextInput v-model="form.currency" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel value="Status" />
                        <select v-model="form.status" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Description" />
                        <textarea v-model="form.description" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Package Services</h2>
                    <button type="button" class="text-sm font-medium text-teal-700 hover:underline" @click="addItem">+ Add Service</button>
                </div>
                <InputError class="mb-3" :message="form.errors.items" />

                <div class="space-y-3">
                    <div
                        v-for="(item, index) in form.items"
                        :key="index"
                        class="grid gap-2 rounded-lg border border-slate-100 bg-slate-50 p-3 md:grid-cols-12"
                    >
                        <div class="md:col-span-3">
                            <InputLabel value="Service Name" />
                            <TextInput v-model="item.service_name" class="mt-1 block w-full" required />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel value="Quantity" />
                            <TextInput v-model="item.quantity" type="number" min="0" class="mt-1 block w-full" :disabled="item.is_unlimited" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel value="Unit" />
                            <select v-model="item.unit" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                <option v-for="u in units" :key="u" :value="u">{{ u }}</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel value="Overage Rate" />
                            <TextInput v-model="item.overage_rate" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                        </div>
                        <div class="flex items-end gap-3 md:col-span-3">
                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                <input v-model="item.is_unlimited" type="checkbox" class="rounded border-slate-300 text-teal-700" />
                                Unlimited
                            </label>
                            <button type="button" class="text-sm text-rose-600 hover:underline" @click="removeItem(index)">Remove</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <PrimaryButton :disabled="form.processing">{{ isEdit ? 'Save Package' : 'Create Package' }}</PrimaryButton>
                <Link :href="route('service-packages.index')" class="text-sm text-slate-500 hover:text-slate-700">Cancel</Link>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
