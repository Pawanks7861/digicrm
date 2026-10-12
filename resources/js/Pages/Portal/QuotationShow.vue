<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

const props = defineProps({ quotation: Object, canAccept: Boolean });
const money = (v, c = 'INR') => new Intl.NumberFormat('en-IN', { style: 'currency', currency: c }).format(Number(v || 0));
const accept = useForm({ confirm: false });
const changes = useForm({ message: '' });
</script>

<template>
    <Head :title="quotation.display_number" />
    <PortalLayout>
        <h1 class="text-xl font-semibold">{{ quotation.display_number }}</h1>
        <p class="mt-1 text-sm capitalize text-slate-500">{{ quotation.status?.replaceAll('_', ' ') }} · Valid until {{ quotation.valid_until }}</p>
        <div class="mt-4 text-2xl font-semibold text-teal-800">{{ money(quotation.grand_total, quotation.currency) }}</div>

        <ul class="mt-6 space-y-2">
            <li v-for="item in quotation.items" :key="item.id" class="rounded-xl border bg-white px-4 py-3 text-sm flex justify-between">
                <span>{{ item.name }} × {{ item.quantity }}</span>
                <span>{{ money(item.total_amount, quotation.currency) }}</span>
            </li>
        </ul>

        <a :href="route('portal.quotations.pdf', quotation.id)" class="mt-4 inline-block text-sm text-teal-700 hover:underline">Download PDF</a>

        <div v-if="canAccept" class="mt-8 space-y-4 rounded-xl border bg-white p-4">
            <label class="flex items-center gap-2 text-sm">
                <input v-model="accept.confirm" type="checkbox" />
                I accept this quotation
            </label>
            <PrimaryButton :disabled="!accept.confirm || accept.processing" @click="accept.post(route('portal.quotations.accept', quotation.id))">Accept</PrimaryButton>
            <div>
                <textarea v-model="changes.message" class="w-full rounded-md border-slate-300 text-sm" rows="2" placeholder="Request changes…" />
                <button type="button" class="mt-2 text-sm text-amber-700" @click="changes.post(route('portal.quotations.request-changes', quotation.id))">Request changes</button>
                <button type="button" class="ml-4 text-sm text-rose-700" @click="router.post(route('portal.quotations.reject', quotation.id))">Reject</button>
            </div>
        </div>
    </PortalLayout>
</template>
