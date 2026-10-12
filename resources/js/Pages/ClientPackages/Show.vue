<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    clientPackage: Object,
    summary: Object,
    history: Object,
    overages: Array,
    can: Object,
});

const formatMoney = (amount, currency = 'INR') => {
    try {
        return new Intl.NumberFormat('en-IN', { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(amount || 0));
    } catch {
        return `${currency} ${amount}`;
    }
};

const statusLabel = (s) =>
    ({
        available: 'Available',
        near_limit: 'Near Limit',
        limit_reached: 'Limit Reached',
        exceeded: 'Exceeded',
    }[s] || s);

const barClass = (s) =>
    ({
        available: 'bg-emerald-500',
        near_limit: 'bg-amber-500',
        limit_reached: 'bg-orange-500',
        exceeded: 'bg-rose-500',
    }[s] || 'bg-slate-400');

const showAdjust = ref(false);
const showRenew = ref(false);
const overagePrompt = computed(() => props.$page?.props?.flash?.overage_check || null);

const adjustForm = useForm({
    client_package_item_id: props.clientPackage.items?.[0]?.id || '',
    action: 'consume',
    quantity: 1,
    reason: '',
    notes: '',
    decision: '',
    decision_notes: '',
});

const renewForm = useForm({
    start_date: '',
    end_date: '',
    agreed_price: props.clientPackage.agreed_price,
    billing_cycle: props.clientPackage.billing_cycle,
    auto_renew: props.clientPackage.auto_renew,
    renewal_type: props.clientPackage.renewal_type || 'manual',
    notes: '',
});

const submitAdjust = () => {
    adjustForm.post(route('client-packages.adjust', props.clientPackage.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAdjust.value = false;
            adjustForm.reset('quantity', 'reason', 'notes', 'decision', 'decision_notes');
        },
    });
};

const submitRenew = () => {
    renewForm.post(route('client-packages.renew', props.clientPackage.id));
};

const cancelPackage = () => {
    if (confirm('Cancel this client package? History will be kept.')) {
        router.post(route('client-packages.cancel', props.clientPackage.id));
    }
};

const decide = (overage, decision) => {
    router.post(route('package-overages.decide', overage.id), {
        decision,
        decision_notes: decision === 'complimentary' ? 'Complimentary approved' : null,
    });
};
</script>

<template>
    <Head :title="clientPackage.package_name" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <Link
                    :href="route('clients.show', { client: clientPackage.client_id, tab: 'packages' })"
                    class="text-sm text-teal-700 hover:underline"
                >
                    ← {{ clientPackage.client?.company_name || 'Client' }}
                </Link>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ clientPackage.package_name }}</h1>
                    <StatusBadge :status="clientPackage.status" />
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    <span v-if="can.view_price">{{ formatMoney(clientPackage.agreed_price, clientPackage.currency) }} / {{ clientPackage.billing_cycle.replaceAll('_', ' ') }}</span>
                    <span v-else class="capitalize">{{ clientPackage.billing_cycle.replaceAll('_', ' ') }}</span>
                    · {{ clientPackage.start_date }} → {{ clientPackage.end_date || 'Open' }}
                    <span v-if="clientPackage.expires_in_days !== null && clientPackage.expires_in_days >= 0 && clientPackage.expires_in_days <= 14">
                        · Expires in {{ clientPackage.expires_in_days }} days
                    </span>
                </p>
                <p class="mt-1 text-sm text-slate-500">Period: {{ summary.period_label }} ({{ summary.period_start }} → {{ summary.period_end }})</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-if="can.adjust"
                    type="button"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50"
                    @click="showAdjust = !showAdjust"
                >
                    Adjust Usage
                </button>
                <button
                    v-if="can.update"
                    type="button"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50"
                    @click="showRenew = !showRenew"
                >
                    Renew
                </button>
                <button
                    v-if="can.cancel"
                    type="button"
                    class="rounded-lg border border-rose-200 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50"
                    @click="cancelPackage"
                >
                    Cancel
                </button>
            </div>
        </div>

        <div v-if="$page.props.flash?.error" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <div class="font-semibold">{{ $page.props.flash.error }}</div>
            <div v-if="$page.props.flash.overage_check" class="mt-2 space-y-1 text-xs">
                <div>Service: {{ $page.props.flash.overage_check.service }}</div>
                <div>Included: {{ $page.props.flash.overage_check.included }} · Used: {{ $page.props.flash.overage_check.used }} · Remaining: {{ $page.props.flash.overage_check.remaining }}</div>
                <div>Requested: {{ $page.props.flash.overage_check.requested }} · Overage: {{ $page.props.flash.overage_check.overage_quantity }}</div>
                <div>Estimated charge: {{ formatMoney($page.props.flash.overage_check.estimated_extra_charge, clientPackage.currency) }}</div>
                <p class="mt-2">Use Adjust Usage with a decision: Charge Extra, Complimentary, Reject, or Quotation.</p>
            </div>
        </div>

        <form v-if="showAdjust" class="mb-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="submitAdjust">
            <div>
                <InputLabel value="Service" />
                <select v-model="adjustForm.client_package_item_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <option v-for="item in clientPackage.items" :key="item.id" :value="item.id">{{ item.service_name }}</option>
                </select>
            </div>
            <div>
                <InputLabel value="Action" />
                <select v-model="adjustForm.action" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <option value="consume">Add Usage (Consume)</option>
                    <option value="restore">Restore Usage</option>
                    <option value="adjustment">Manual Adjustment (+/-)</option>
                </select>
            </div>
            <div>
                <InputLabel value="Quantity" />
                <TextInput v-model="adjustForm.quantity" type="number" step="0.01" class="mt-1 block w-full" required />
            </div>
            <div class="sm:col-span-2">
                <InputLabel value="Reason" />
                <TextInput v-model="adjustForm.reason" class="mt-1 block w-full" required />
            </div>
            <div>
                <InputLabel value="Overage Decision (if exceeded)" />
                <select v-model="adjustForm.decision" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    <option value="chargeable">Charge Extra</option>
                    <option value="complimentary">Complimentary</option>
                    <option value="rejected">Reject</option>
                    <option value="quotation_required">Send Quotation</option>
                </select>
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <PrimaryButton :disabled="adjustForm.processing">Submit</PrimaryButton>
            </div>
        </form>

        <form v-if="showRenew" class="mb-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2" @submit.prevent="submitRenew">
            <div>
                <InputLabel value="New Start Date" />
                <TextInput v-model="renewForm.start_date" type="date" class="mt-1 block w-full" required />
            </div>
            <div>
                <InputLabel value="New End Date" />
                <TextInput v-model="renewForm.end_date" type="date" class="mt-1 block w-full" />
            </div>
            <div v-if="can.view_price">
                <InputLabel value="Agreed Price" />
                <TextInput v-model="renewForm.agreed_price" type="number" step="0.01" class="mt-1 block w-full" />
            </div>
            <div class="sm:col-span-2">
                <PrimaryButton :disabled="renewForm.processing">Renew Package</PrimaryButton>
            </div>
        </form>

        <div class="mb-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="row in summary.items"
                :key="row.id"
                :href="route('client-packages.items.history', [clientPackage.id, row.id])"
                class="rounded-xl border border-slate-200 bg-white p-4 transition hover:border-teal-300"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="font-medium text-slate-900">{{ row.service }}</div>
                    <span class="text-xs font-medium text-slate-500">{{ statusLabel(row.status) }}</span>
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">
                    <template v-if="row.is_unlimited">∞</template>
                    <template v-else>{{ row.used }} / {{ row.included }}</template>
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    <template v-if="row.is_unlimited">Unlimited</template>
                    <template v-else>Remaining: {{ row.remaining }}</template>
                </p>
                <div v-if="!row.is_unlimited && row.included" class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                    <div
                        class="h-full rounded-full"
                        :class="barClass(row.status)"
                        :style="{ width: Math.min(100, Math.round((row.used / row.included) * 100)) + '%' }"
                    />
                </div>
            </Link>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div>
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Usage History</h2>
                <div v-if="history.data?.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <ul class="divide-y divide-slate-100 text-sm">
                        <li v-for="tx in history.data" :key="tx.id" class="px-4 py-3">
                            <div class="flex justify-between gap-2">
                                <span class="font-medium">{{ tx.item?.service_name }} · {{ tx.direction }} {{ tx.quantity }}</span>
                                <span class="text-xs text-slate-400">{{ tx.usage_date }}</span>
                            </div>
                            <div class="text-xs text-slate-500">{{ tx.reason || '—' }} · {{ tx.creator?.name || 'System' }}</div>
                        </li>
                    </ul>
                </div>
                <EmptyState v-else title="No usage yet." description="Consume or adjust usage to start tracking." />
            </div>

            <div>
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Overages</h2>
                <div v-if="overages.length" class="space-y-3">
                    <div v-for="o in overages" :key="o.id" class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                        <div class="flex justify-between gap-2">
                            <span class="font-medium">{{ o.item?.service_name }} · +{{ o.overage_quantity }}</span>
                            <StatusBadge :status="o.decision" />
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            Extra: {{ formatMoney(o.extra_amount, clientPackage.currency) }} · {{ o.billing_status }}
                        </div>
                        <div v-if="can.overage && o.decision === 'pending'" class="mt-3 flex flex-wrap gap-2">
                            <button type="button" class="text-xs text-teal-700 hover:underline" @click="decide(o, 'chargeable')">Charge Extra</button>
                            <button type="button" class="text-xs text-teal-700 hover:underline" @click="decide(o, 'complimentary')">Complimentary</button>
                            <button type="button" class="text-xs text-rose-600 hover:underline" @click="decide(o, 'rejected')">Reject</button>
                            <button type="button" class="text-xs text-slate-600 hover:underline" @click="decide(o, 'quotation_required')">Quotation</button>
                        </div>
                    </div>
                </div>
                <EmptyState v-else title="No overages." description="Scope creep decisions will appear here." />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
