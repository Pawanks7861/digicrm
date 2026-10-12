<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import LeadWidgets from '@/Components/Leads/LeadWidgets.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    dashboard: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const data = computed(() => props.dashboard.data || {});
const financial = computed(() => data.value.financial || null);
const attention = computed(() => data.value.attention || {});
const period = computed(() => props.dashboard.period || {});

const money = (v) => {
    if (v == null) return '—';
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(Number(v));
};
const hours = (sec) => `${(Number(sec || 0) / 3600).toFixed(1)}h`;

const setPeriod = (preset) => {
    router.get(route('dashboard'), { period: preset }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Executive Dashboard" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Executive Dashboard</h1>
                <p class="mt-1 text-sm text-slate-500">{{ period.from }} → {{ period.to }} · contracted / operational values (not cash collected)</p>
            </div>
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="p in ['today','week','month','last_month','quarter','year']"
                    :key="p"
                    type="button"
                    class="rounded-lg px-2.5 py-1.5 text-xs font-medium"
                    :class="period.preset === p ? 'bg-teal-700 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-teal-300'"
                    @click="setPeriod(p)"
                >{{ p.replace('_', ' ') }}</button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Active Clients</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.active_clients ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">New Clients</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.new_clients ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">On Hold</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.clients_on_hold ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase text-slate-400">Active Campaigns</div>
                <div class="mt-2 text-3xl font-semibold">{{ data.active_campaigns ?? 0 }}</div>
            </div>
        </div>

        <template v-if="financial">
            <h2 class="mt-8 text-sm font-semibold text-slate-700">Financial Summary</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-semibold uppercase text-slate-400">Contracted Package Value</div>
                    <div class="mt-2 text-2xl font-semibold">{{ money(financial.contracted_package_value) }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-semibold uppercase text-slate-400">Additional Charges</div>
                    <div class="mt-2 text-2xl font-semibold">{{ money(financial.additional_charge_value) }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-semibold uppercase text-slate-400">Operational Revenue</div>
                    <div class="mt-2 text-2xl font-semibold text-teal-800">{{ money(financial.operational_revenue) }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-semibold uppercase text-slate-400">Complimentary Work</div>
                    <div class="mt-2 text-2xl font-semibold text-amber-700">{{ money(financial.complimentary_work_value) }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-semibold uppercase text-slate-400">Labor Cost</div>
                    <div class="mt-2 text-2xl font-semibold">{{ money(financial.labor_cost) }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-semibold uppercase text-slate-400">Est. Gross Profit</div>
                    <div class="mt-2 text-2xl font-semibold">{{ money(financial.estimated_gross_profit) }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-semibold uppercase text-slate-400">Gross Margin</div>
                    <div class="mt-2 text-2xl font-semibold">{{ financial.estimated_gross_margin != null ? financial.estimated_gross_margin + '%' : 'N/A' }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-semibold uppercase text-slate-400">Logged / Billable</div>
                    <div class="mt-2 text-2xl font-semibold">{{ hours(financial.logged_seconds) }} <span class="text-base text-slate-400">/ {{ hours(financial.billable_seconds) }}</span></div>
                </div>
            </div>
            <p v-if="financial.data_completeness === 'missing_cost_rates'" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                Cost data incomplete — {{ financial.missing_cost_entries }} time entries lack an internal cost rate. Margin may be understated.
            </p>
        </template>

        <h2 class="mt-8 text-sm font-semibold text-slate-700">Attention Required</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Link :href="route('tasks.index', { bucket: 'overdue' })" class="rounded-xl border border-slate-200 bg-white p-4 hover:border-rose-300">
                <div class="text-xs uppercase text-slate-400">Overdue Tasks</div>
                <div class="mt-1 text-2xl font-semibold text-rose-700">{{ attention.tasks_overdue ?? 0 }}</div>
            </Link>
            <Link :href="route('content.index', { status: 'sent_to_client' })" class="rounded-xl border border-slate-200 bg-white p-4 hover:border-teal-300">
                <div class="text-xs uppercase text-slate-400">Pending Approvals</div>
                <div class="mt-1 text-2xl font-semibold">{{ attention.pending_client_approvals ?? 0 }}</div>
            </Link>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase text-slate-400">Blocked Tasks</div>
                <div class="mt-1 text-2xl font-semibold text-orange-700">{{ attention.blocked_tasks ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase text-slate-400">Overages Pending</div>
                <div class="mt-1 text-2xl font-semibold">{{ attention.overages_pending ?? 0 }}</div>
            </div>
        </div>

        <h2 class="mt-8 text-sm font-semibold text-slate-700">Delivery</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs uppercase text-slate-400">On-Time Delivery</div>
                <div class="mt-2 text-2xl font-semibold">
                    <template v-if="data.on_time_delivery?.with_due_date">
                        {{ data.on_time_delivery.on_time }} / {{ data.on_time_delivery.with_due_date }}
                        <span class="text-base text-slate-500">({{ data.on_time_delivery.rate }}%)</span>
                    </template>
                    <template v-else>No comparison available</template>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs uppercase text-slate-400">Avg Approval Response</div>
                <div class="mt-2 text-2xl font-semibold">
                    <template v-if="data.approval_response?.average_seconds != null">
                        {{ hours(data.approval_response.average_seconds) }}
                        <span class="text-base text-slate-500">({{ data.approval_response.count }})</span>
                    </template>
                    <template v-else>No comparison available</template>
                </div>
            </div>
        </div>

        <div v-if="can.reports" class="mt-8">
            <Link :href="route('reports.index')" class="text-sm font-medium text-teal-700 hover:underline">Open Reports →</Link>
        </div>
        <LeadWidgets :leads="dashboard.leads" role="executive" />
    </AuthenticatedLayout>
</template>
