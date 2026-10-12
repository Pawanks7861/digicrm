<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    client: Object,
    activities: Array,
    folders: Array,
    statuses: Array,
    tab: String,
    can: Object,
    packageSummary: Object,
    availablePackages: Array,
    billingCycles: Array,
    campaigns: { type: Array, default: () => [] },
    communications: { type: Array, default: () => [] },
    clientRequests: { type: Array, default: () => [] },
    portalSettings: { type: Object, default: null },
    communicationTypes: { type: Array, default: () => [] },
});

const activeTab = ref(props.tab || 'dashboard');

const tabs = [
    { id: 'dashboard', label: 'Dashboard' },
    { id: 'brand', label: 'Brand' },
    { id: 'onboarding', label: 'Onboarding' },
    { id: 'packages', label: 'Packages' },
    { id: 'campaigns', label: 'Campaigns' },
    { id: 'requests', label: 'Requests' },
    { id: 'communications', label: 'Communications' },
    { id: 'files', label: 'Files' },
    { id: 'portal', label: 'Portal Settings' },
    { id: 'social', label: 'Social' },
    { id: 'activity', label: 'Activity' },
    { id: 'notes', label: 'Notes' },
    { id: 'content', label: 'Content', soon: true },
    { id: 'approvals', label: 'Approvals', soon: true },
    { id: 'tasks', label: 'Tasks', soon: true },
    { id: 'invoices', label: 'Invoices', soon: true },
    { id: 'reports', label: 'Reports', soon: true },
    { id: 'credentials', label: 'Credentials', soon: true },
];

const progress = computed(() => props.client.onboarding_progress || { completed: 0, total: 0, percent: 0 });

const tones = ['bg-[#FFF1E4] text-ink', 'bg-sun/80 text-ink', 'bg-[#F6E8FF] text-ink', 'bg-sage text-ink', 'bg-card text-ink', 'bg-mist text-ink'];

const interactionCards = computed(() => {
    const cards = [];
    (props.campaigns || []).forEach((item) => {
        cards.push({
            date: item.start_date || item.created_at,
            title: item.name,
            meta: item.status ? String(item.status).replaceAll('_', ' ') : 'Campaign',
            value: item.client_charge != null && props.can?.view_finance ? formatMoney(item.client_charge, item.currency || 'INR') : null,
        });
    });
    (props.communications || []).forEach((item) => {
        cards.push({
            date: item.occurred_at,
            title: item.subject || item.communication_type,
            meta: item.communication_type,
            value: null,
        });
    });
    (props.clientRequests || []).forEach((item) => {
        cards.push({
            date: item.created_at,
            title: item.title,
            meta: item.status ? String(item.status).replaceAll('_', ' ') : 'Request',
            value: null,
        });
    });
    return cards.slice(0, 6).map((card, index) => ({ ...card, tone: tones[index % tones.length] }));
});

const brandForm = useForm({
    brand_description: props.client.brand_profile?.brand_description || '',
    target_audience: props.client.brand_profile?.target_audience || '',
    tone_of_voice: props.client.brand_profile?.tone_of_voice || '',
    preferred_colors: props.client.brand_profile?.preferred_colors || [],
    brand_fonts: props.client.brand_profile?.brand_fonts || [],
    dos: props.client.brand_profile?.dos || '',
    donts: props.client.brand_profile?.donts || '',
    competitors: props.client.brand_profile?.competitors || '',
    hashtags: props.client.brand_profile?.hashtags || [],
    content_preferences: props.client.brand_profile?.content_preferences || '',
    important_urls: props.client.brand_profile?.important_urls || [],
    reference_accounts: props.client.brand_profile?.reference_accounts || '',
});

const colorsText = ref((props.client.brand_profile?.preferred_colors || []).join(', '));
const fontsText = ref((props.client.brand_profile?.brand_fonts || []).join(', '));
const hashtagsText = ref((props.client.brand_profile?.hashtags || []).join(', '));

const saveBrand = () => {
    brandForm.preferred_colors = colorsText.value
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);
    brandForm.brand_fonts = fontsText.value
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);
    brandForm.hashtags = hashtagsText.value
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);
    brandForm.put(route('clients.brand.update', props.client.id), { preserveScroll: true });
};

const onboardingLabel = ref('');
const addOnboarding = () => {
    if (!onboardingLabel.value.trim()) return;
    router.post(
        route('clients.onboarding.store', props.client.id),
        { label: onboardingLabel.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                onboardingLabel.value = '';
            },
        }
    );
};

const toggleItem = (item) => {
    router.put(
        route('clients.onboarding.toggle', [props.client.id, item.id]),
        { is_completed: !item.is_completed },
        { preserveScroll: true }
    );
};

const fileForm = useForm({
    file: null,
    folder: 'Documents',
    visibility: 'internal',
});

const commForm = useForm({
    communication_type: 'phone',
    direction: 'outbound',
    subject: '',
    notes: '',
    follow_up_required: false,
    next_follow_up_at: '',
});

const portalForm = useForm({
    portal_enabled: props.portalSettings?.portal_enabled ?? true,
    show_campaigns: props.portalSettings?.show_campaigns ?? true,
    show_content: props.portalSettings?.show_content ?? true,
    show_calendar: props.portalSettings?.show_calendar ?? false,
    show_files: props.portalSettings?.show_files ?? true,
    show_requests: props.portalSettings?.show_requests ?? true,
    show_approvals: props.portalSettings?.show_approvals ?? true,
    show_package_usage: props.portalSettings?.show_package_usage ?? false,
    allow_request_creation: props.portalSettings?.allow_request_creation ?? true,
    allow_file_upload: props.portalSettings?.allow_file_upload ?? false,
    allow_comments: props.portalSettings?.allow_comments ?? true,
    allow_content_approval: props.portalSettings?.allow_content_approval ?? true,
    show_leads: props.portalSettings?.show_leads ?? false,
    show_lead_contact_details: props.portalSettings?.show_lead_contact_details ?? false,
    show_lead_source: props.portalSettings?.show_lead_source ?? false,
    show_lead_reports: props.portalSettings?.show_lead_reports ?? false,
    allow_lead_updates: props.portalSettings?.allow_lead_updates ?? false,
});

const uploadFile = () => {
    fileForm.post(route('clients.files.store', props.client.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => fileForm.reset('file'),
    });
};

const destroyClient = () => {
    if (confirm('Archive this client? Soft delete will keep history.')) {
        router.delete(route('clients.destroy', props.client.id));
    }
};

const formatMoney = (amount, currency = 'INR') => {
    try {
        return new Intl.NumberFormat('en-IN', { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(amount || 0));
    } catch {
        return `${currency} ${amount}`;
    }
};

const showAssign = ref(false);
const assignForm = useForm({
    service_package_id: '',
    start_date: new Date().toISOString().slice(0, 10),
    end_date: '',
    agreed_price: 0,
    currency: 'INR',
    billing_cycle: 'monthly',
    auto_renew: false,
    renewal_type: 'manual',
    notes: '',
});

const onPackagePick = () => {
    const pkg = (props.availablePackages || []).find((p) => p.id === Number(assignForm.service_package_id));
    if (pkg) {
        assignForm.agreed_price = pkg.base_price;
        assignForm.currency = pkg.currency;
        assignForm.billing_cycle = pkg.billing_cycle;
    }
};

const submitAssign = () => {
    assignForm.post(route('clients.packages.store', props.client.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAssign.value = false;
            activeTab.value = 'packages';
        },
    });
};
</script>

<template>
    <Head :title="client.company_name" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <Link :href="route('clients.index')" class="text-sm text-mute hover:text-ink">Clients</Link>
                <h1 class="mt-2 text-[2rem] font-semibold leading-[1.05] tracking-tight text-ink">Client<br>Information</h1>
            </div>
            <div class="flex flex-wrap gap-3">
                <div class="min-w-[9rem] rounded-card bg-card px-4 py-3">
                    <div class="text-[11px] text-mute">Onboarding</div>
                    <div class="text-2xl font-semibold">{{ progress.percent }}%</div>
                </div>
                <div class="min-w-[9rem] rounded-card bg-card px-4 py-3">
                    <div class="text-[11px] text-mute">Active packages</div>
                    <div class="text-2xl font-semibold">{{ packageSummary?.active_packages ?? 0 }}</div>
                </div>
                <div class="min-w-[9rem] rounded-card bg-card px-4 py-3">
                    <div class="text-[11px] text-mute">Campaigns</div>
                    <div class="text-2xl font-semibold">{{ campaigns.length }}</div>
                </div>
            </div>
        </div>

        <div class="mb-6 grid gap-4 xl:grid-cols-[minmax(0,1.9fr)_minmax(280px,1fr)]">
            <div class="space-y-4">
                <section class="rounded-card bg-white/50 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-[15px] font-semibold">Interaction History</h2>
                        <button type="button" class="text-xs text-mute" @click="activeTab = 'communications'">Open log</button>
                    </div>
                    <div v-if="interactionCards.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <article v-for="card in interactionCards" :key="card.title + card.date" class="rounded-[1.4rem] p-4" :class="card.tone">
                            <div class="text-[11px] opacity-80">{{ card.date ? String(card.date).slice(0, 10) : 'Recent' }}</div>
                            <div class="mt-6 text-sm font-semibold leading-snug">{{ card.title }}</div>
                            <div class="mt-1 text-xs capitalize opacity-80">{{ card.meta }}</div>
                            <div v-if="card.value" class="mt-4 text-lg font-semibold">{{ card.value }}</div>
                        </article>
                    </div>
                    <p v-else class="py-8 text-sm text-mute">No campaigns, communications, or requests yet.</p>
                </section>
                <section class="rounded-card bg-card p-5">
                    <h2 class="text-[15px] font-semibold">Work in motion</h2>
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between rounded-2xl bg-mist px-4 py-3 text-sm">
                            <span>Onboarding</span><span class="font-semibold">{{ progress.completed }}/{{ progress.total }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl bg-sage px-4 py-3 text-sm">
                            <span>Packages near limit</span><span class="font-semibold">{{ packageSummary?.near_limit ?? 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl bg-haze px-4 py-3 text-sm">
                            <span>Pending overages</span><span class="font-semibold">{{ packageSummary?.overages_pending ?? 0 }}</span>
                        </div>
                    </div>
                </section>
            </div>
            <aside class="space-y-4">
                <section class="rounded-card bg-card p-5">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-mist text-xl font-semibold text-accent">
                        {{ (client.contact_person || client.company_name || '?').charAt(0) }}
                    </div>
                    <div class="mt-3 text-center">
                        <div class="text-lg font-semibold">{{ client.contact_person || 'No contact' }}</div>
                        <div class="text-sm text-mute">{{ client.company_name }}</div>
                        <StatusBadge class="mt-2" :status="client.status" />
                    </div>
                    <div class="mt-4 flex justify-center gap-2">
                        <Link v-if="can.update" :href="route('clients.edit', client.id)" class="rounded-full bg-haze px-3 py-1.5 text-xs font-medium">Edit</Link>
                        <a v-if="client.email" :href="'mailto:' + client.email" class="rounded-full bg-haze px-3 py-1.5 text-xs font-medium">Email</a>
                        <a v-if="client.mobile" :href="'tel:' + client.mobile" class="rounded-full bg-haze px-3 py-1.5 text-xs font-medium">Call</a>
                        <button type="button" class="rounded-full bg-haze px-3 py-1.5 text-xs font-medium" @click="activeTab = 'communications'">Log</button>
                    </div>
                </section>
                <section class="rounded-card bg-card p-5 text-sm">
                    <h2 class="text-[15px] font-semibold">Detailed Information</h2>
                    <dl class="mt-4 space-y-3">
                        <div class="flex justify-between gap-3"><dt class="text-mute">Company</dt><dd class="text-right font-medium">{{ client.company_name }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-mute">Email</dt><dd class="text-right font-medium">{{ client.email || '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-mute">Mobile</dt><dd class="text-right font-medium">{{ client.mobile || '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-mute">Industry</dt><dd class="text-right font-medium">{{ client.industry || '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-mute">City</dt><dd class="text-right font-medium">{{ client.city || '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-mute">Account manager</dt><dd class="text-right font-medium">{{ client.account_manager?.name || 'Unassigned' }}</dd></div>
                    </dl>
                    <button v-if="can.delete" type="button" class="mt-4 text-xs text-rose-700" @click="destroyClient">Archive client</button>
                </section>
            </aside>
        </div>

        <div class="mb-6 flex gap-1 overflow-x-auto">
            <button
                v-for="t in tabs"
                :key="t.id"
                type="button"
                :disabled="t.soon"
                :class="[
                    'whitespace-nowrap rounded-full px-3 py-1.5 text-sm font-medium transition duration-150',
                    activeTab === t.id ? 'nav-active text-ink' : 'bg-card text-mute hover:text-ink',
                    t.soon ? 'cursor-not-allowed opacity-40' : '',
                ]"
                @click="!t.soon && (activeTab = t.id)"
            >
                {{ t.label }}
            </button>
        </div>

        <!-- Dashboard -->
        <div v-if="activeTab === 'dashboard'" class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Onboarding</div>
                <div class="mt-2 text-3xl font-semibold">{{ progress.percent }}%</div>
                <p class="mt-1 text-sm text-slate-500">{{ progress.completed }} / {{ progress.total }} completed</p>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full bg-teal-600" :style="{ width: progress.percent + '%' }" />
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Files</div>
                <div class="mt-2 text-3xl font-semibold">{{ client.files?.length || 0 }}</div>
                <p class="mt-1 text-sm text-slate-500">Stored assets & documents</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Packages</div>
                <div class="mt-2 text-3xl font-semibold">{{ packageSummary?.active_packages ?? 0 }}</div>
                <p class="mt-1 text-sm text-slate-500">
                    Expiring soon: {{ packageSummary?.expiring_soon ?? 0 }} · Near limit: {{ packageSummary?.near_limit ?? 0 }} · Overages: {{ packageSummary?.overages_pending ?? 0 }}
                </p>
            </div>
        </div>

        <!-- Packages -->
        <div v-else-if="activeTab === 'packages'" class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Client Packages</h2>
                    <p class="text-sm text-slate-500">Assigned retainers, usage, and scope tracking.</p>
                </div>
                <button
                    v-if="can.assign_package"
                    type="button"
                    class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800"
                    @click="showAssign = !showAssign"
                >
                    Assign Package
                </button>
            </div>

            <form
                v-if="showAssign"
                class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3"
                @submit.prevent="submitAssign"
            >
                <div class="sm:col-span-2 lg:col-span-3">
                    <InputLabel value="Package" />
                    <select v-model="assignForm.service_package_id" class="mt-1 w-full rounded-md border-slate-300 text-sm" required @change="onPackagePick">
                        <option value="">Select package…</option>
                        <option v-for="pkg in availablePackages" :key="pkg.id" :value="pkg.id">
                            {{ pkg.name }} — {{ formatMoney(pkg.base_price, pkg.currency) }}
                        </option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Start Date" />
                    <TextInput v-model="assignForm.start_date" type="date" class="mt-1 block w-full" required />
                </div>
                <div>
                    <InputLabel value="End Date" />
                    <TextInput v-model="assignForm.end_date" type="date" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel value="Agreed Price" />
                    <TextInput v-model="assignForm.agreed_price" type="number" step="0.01" min="0" class="mt-1 block w-full" required />
                </div>
                <div>
                    <InputLabel value="Billing Cycle" />
                    <select v-model="assignForm.billing_cycle" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option v-for="c in billingCycles" :key="c" :value="c">{{ c.replaceAll('_', ' ') }}</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input v-model="assignForm.auto_renew" type="checkbox" class="rounded border-slate-300 text-teal-700" />
                        Auto renew
                    </label>
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <PrimaryButton :disabled="assignForm.processing">Assign</PrimaryButton>
                </div>
            </form>

            <div v-if="packageSummary?.packages?.length" class="grid gap-4 lg:grid-cols-2">
                <div
                    v-for="pkg in packageSummary.packages"
                    :key="pkg.id"
                    class="rounded-xl border border-slate-200 bg-white p-5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-lg font-semibold text-slate-900">{{ pkg.package_name }}</div>
                            <div class="mt-1 text-sm text-slate-500">
                                {{ formatMoney(pkg.agreed_price, pkg.currency) }} / {{ pkg.billing_cycle.replaceAll('_', ' ') }}
                            </div>
                            <div class="mt-1 text-xs text-slate-400">{{ pkg.start_date }} → {{ pkg.end_date || 'Open' }}</div>
                        </div>
                        <StatusBadge :status="pkg.status" />
                    </div>
                    <div class="mt-4 space-y-2">
                        <div v-for="row in pkg.usage.items" :key="row.id" class="text-sm">
                            <div class="flex justify-between gap-2">
                                <span>{{ row.service }}</span>
                                <span class="tabular-nums text-slate-600">
                                    <template v-if="row.is_unlimited">∞ used {{ row.used }}</template>
                                    <template v-else>{{ row.used }} / {{ row.included }}</template>
                                </span>
                            </div>
                            <div v-if="!row.is_unlimited && row.included" class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full bg-teal-600"
                                    :style="{ width: Math.min(100, Math.round((row.used / row.included) * 100)) + '%' }"
                                />
                            </div>
                        </div>
                    </div>
                    <Link
                        :href="route('client-packages.show', pkg.id)"
                        class="mt-4 inline-flex text-sm font-medium text-teal-700 hover:underline"
                    >
                        View Usage
                    </Link>
                </div>
            </div>
            <EmptyState
                v-else
                title="No packages assigned."
                description="Assign a service package to track allowances and prevent silent scope creep."
            />
        </div>

        <!-- Brand -->
        <form v-else-if="activeTab === 'brand'" class="max-w-3xl space-y-4 rounded-xl border border-slate-200 bg-white p-6" @submit.prevent="saveBrand">
            <div>
                <InputLabel value="Brand Description" />
                <textarea v-model="brandForm.brand_description" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm" />
            </div>
            <div>
                <InputLabel value="Target Audience" />
                <textarea v-model="brandForm.target_audience" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm" />
            </div>
            <div>
                <InputLabel value="Tone of Voice" />
                <TextInput v-model="brandForm.tone_of_voice" class="mt-1 block w-full" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel value="Preferred Colors (comma-separated)" />
                    <TextInput v-model="colorsText" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel value="Brand Fonts (comma-separated)" />
                    <TextInput v-model="fontsText" class="mt-1 block w-full" />
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel value="Do's" />
                    <textarea v-model="brandForm.dos" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm" />
                </div>
                <div>
                    <InputLabel value="Don'ts" />
                    <textarea v-model="brandForm.donts" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm" />
                </div>
            </div>
            <div>
                <InputLabel value="Competitors" />
                <textarea v-model="brandForm.competitors" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm" />
            </div>
            <div>
                <InputLabel value="Hashtags (comma-separated)" />
                <TextInput v-model="hashtagsText" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel value="Content Preferences" />
                <textarea v-model="brandForm.content_preferences" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm" />
            </div>
            <div>
                <InputLabel value="Reference Accounts" />
                <textarea v-model="brandForm.reference_accounts" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm" />
            </div>
            <PrimaryButton v-if="can.update" :disabled="brandForm.processing">Save Brand Profile</PrimaryButton>
        </form>

        <!-- Onboarding -->
        <div v-else-if="activeTab === 'onboarding'" class="max-w-2xl space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-medium text-slate-900">Progress</div>
                        <div class="text-sm text-slate-500">Completed {{ progress.completed }} / Total {{ progress.total }}</div>
                    </div>
                    <div class="text-2xl font-semibold text-teal-800">{{ progress.percent }}%</div>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full bg-teal-600 transition-all" :style="{ width: progress.percent + '%' }" />
                </div>
            </div>

            <ul class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                <li
                    v-for="item in client.onboarding_items"
                    :key="item.id"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <input
                        type="checkbox"
                        class="rounded border-slate-300 text-teal-700 focus:ring-teal-600"
                        :checked="item.is_completed"
                        :disabled="!can.update"
                        @change="toggleItem(item)"
                    />
                    <div class="flex-1">
                        <div :class="item.is_completed ? 'text-slate-400 line-through' : 'text-slate-800'">{{ item.label }}</div>
                        <div v-if="item.is_custom" class="text-xs text-slate-400">Custom item</div>
                    </div>
                </li>
            </ul>

            <form v-if="can.update" class="flex gap-2" @submit.prevent="addOnboarding">
                <TextInput v-model="onboardingLabel" class="block w-full" placeholder="Add custom checklist item…" />
                <PrimaryButton>Add</PrimaryButton>
            </form>
        </div>

        <!-- Files -->
        <div v-else-if="activeTab === 'files'" class="space-y-4">
            <form
                v-if="can.update"
                class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-end"
                @submit.prevent="uploadFile"
            >
                <div class="flex-1">
                    <InputLabel value="File" />
                    <input type="file" class="mt-1 block w-full text-sm" @input="fileForm.file = $event.target.files[0]" />
                </div>
                <div>
                    <InputLabel value="Folder" />
                    <select v-model="fileForm.folder" class="mt-1 rounded-md border-slate-300 text-sm">
                        <option v-for="f in folders" :key="f" :value="f">{{ f }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Visibility" />
                    <select v-model="fileForm.visibility" class="mt-1 rounded-md border-slate-300 text-sm">
                        <option value="internal">Internal Only</option>
                        <option value="client">Visible to Client</option>
                    </select>
                </div>
                <PrimaryButton :disabled="fileForm.processing || !fileForm.file">Upload</PrimaryButton>
            </form>

            <div v-if="client.files?.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Folder</th>
                            <th class="px-4 py-3">Visibility</th>
                            <th class="px-4 py-3">Uploaded by</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="file in client.files" :key="file.id">
                            <td class="px-4 py-3">{{ file.original_name }}</td>
                            <td class="px-4 py-3">{{ file.folder }}</td>
                            <td class="px-4 py-3 capitalize">{{ file.visibility }}</td>
                            <td class="px-4 py-3">{{ file.uploader?.name || '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    :href="route('clients.files.download', [client.id, file.id])"
                                    class="text-teal-700 hover:underline"
                                >Download</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState v-else title="No files yet." description="Upload brand assets, contracts, and documents." />
        </div>

        <!-- Campaigns -->
        <div v-else-if="activeTab === 'campaigns'" class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-slate-900">Campaigns</h3>
                <Link
                    v-if="can.create_campaign"
                    :href="route('campaigns.create', { client_id: client.id })"
                    class="rounded-md bg-teal-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-teal-800"
                >
                    Create Campaign
                </Link>
            </div>
            <div v-if="campaigns?.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Campaign</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Manager</th>
                            <th class="px-4 py-3">Dates</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="c in campaigns" :key="c.id">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ c.name }}</div>
                                <div class="text-xs text-slate-400">{{ c.code }}</div>
                            </td>
                            <td class="px-4 py-3 capitalize">{{ (c.campaign_type || '').replaceAll('_', ' ') }}</td>
                            <td class="px-4 py-3">{{ c.manager?.name || '—' }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                {{ c.start_date || '—' }} → {{ c.end_date || '—' }}
                            </td>
                            <td class="px-4 py-3"><StatusBadge :status="c.status" /></td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('campaigns.show', c.id)" class="text-teal-700 hover:underline">View</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState
                v-else
                title="No campaigns yet."
                description="Create a campaign to start deliverables and tasks for this client."
            />
        </div>

        <!-- Requests -->
        <div v-else-if="activeTab === 'requests'" class="space-y-4">
            <div v-if="clientRequests?.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Request</th>
                            <th class="px-4 py-3">Priority</th>
                            <th class="px-4 py-3">Due</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="r in clientRequests" :key="r.id">
                            <td class="px-4 py-3">
                                <Link :href="route('requests.show', r.id)" class="font-medium text-teal-700 hover:underline">{{ r.title }}</Link>
                                <div class="text-xs text-slate-400">{{ r.request_number }}</div>
                            </td>
                            <td class="px-4 py-3 capitalize">{{ r.priority }}</td>
                            <td class="px-4 py-3 text-xs">{{ r.requested_due_date || '—' }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="r.status" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState v-else title="No requests yet." />
        </div>

        <!-- Communications -->
        <div v-else-if="activeTab === 'communications'" class="space-y-4">
            <form v-if="can.log_communication" class="rounded-xl border border-slate-200 bg-white p-4" @submit.prevent="commForm.post(route('clients.communications.store', client.id), { preserveScroll: true, onSuccess: () => { commForm.reset('notes', 'subject'); } })">
                <h3 class="text-sm font-semibold">Log Communication</h3>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <select v-model="commForm.communication_type" class="rounded-md border-slate-300 text-sm">
                        <option v-for="t in communicationTypes" :key="t" :value="t">{{ t }}</option>
                    </select>
                    <input v-model="commForm.subject" type="text" placeholder="Subject" class="rounded-md border-slate-300 text-sm" />
                    <label class="flex items-center gap-2 text-sm"><input v-model="commForm.follow_up_required" type="checkbox" /> Create follow-up</label>
                </div>
                <textarea v-model="commForm.notes" rows="3" class="mt-2 w-full rounded-md border-slate-300 text-sm" required placeholder="Notes" />
                <input v-if="commForm.follow_up_required" v-model="commForm.next_follow_up_at" type="datetime-local" class="mt-2 rounded-md border-slate-300 text-sm" />
                <PrimaryButton class="mt-2" :disabled="commForm.processing">Save</PrimaryButton>
            </form>
            <div v-if="communications?.length" class="space-y-2">
                <div v-for="c in communications" :key="c.id" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                    <div class="flex justify-between gap-2">
                        <span class="font-medium capitalize">{{ c.communication_type }} · {{ c.direction }}</span>
                        <span class="text-xs text-slate-400">{{ new Date(c.occurred_at).toLocaleString() }}</span>
                    </div>
                    <p class="mt-1 text-slate-600">{{ c.notes }}</p>
                    <div class="text-xs text-slate-400">{{ c.user?.name }}</div>
                </div>
            </div>
            <EmptyState v-else title="No communications have been logged." />
        </div>

        <!-- Portal Settings -->
        <div v-else-if="activeTab === 'portal'" class="max-w-xl space-y-4">
            <form v-if="can.edit_portal_settings && portalSettings" class="rounded-xl border border-slate-200 bg-white p-5 space-y-3" @submit.prevent="portalForm.put(route('clients.portal-settings.update', client.id))">
                <h3 class="text-sm font-semibold">Portal Settings</h3>
                <label v-for="key in Object.keys(portalForm.data()).filter(k => k !== 'errors')" :key="key" class="flex items-center justify-between gap-3 text-sm capitalize">
                    <span>{{ key.replaceAll('_', ' ') }}</span>
                    <input v-model="portalForm[key]" type="checkbox" />
                </label>
                <PrimaryButton :disabled="portalForm.processing">Save Settings</PrimaryButton>
            </form>
            <p v-else class="text-sm text-slate-500">Portal settings unavailable.</p>
        </div>

        <!-- Social -->
        <div v-else-if="activeTab === 'social'" class="max-w-xl space-y-3">
            <h3 class="text-sm font-semibold">Connected social accounts</h3>
            <ul v-if="client.social_accounts?.length" class="space-y-2">
                <li v-for="a in client.social_accounts" :key="a.id" class="rounded-xl border bg-white px-4 py-3 text-sm">
                    <div class="font-medium">{{ a.display_name || a.username || a.platform }}</div>
                    <div class="text-xs text-slate-500">{{ a.provider }} · {{ a.status }}</div>
                </li>
            </ul>
            <EmptyState v-else title="No social accounts linked yet." description="Connect Meta or other publishers from Integrations, then link accounts here." />
        </div>

        <!-- Activity -->
        <div v-else-if="activeTab === 'activity'" class="max-w-2xl">
            <ul v-if="activities.length" class="space-y-3">
                <li v-for="log in activities" :key="log.id" class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-medium text-slate-900">{{ log.action.replaceAll('_', ' ') }}</span>
                        <span class="text-xs text-slate-400">{{ new Date(log.created_at).toLocaleString() }}</span>
                    </div>
                    <div class="mt-1 text-xs text-slate-500">
                        {{ log.user?.name || 'System' }} · {{ log.visibility }}
                    </div>
                </li>
            </ul>
            <EmptyState v-else title="No activity yet." />
        </div>

        <!-- Notes -->
        <div v-else-if="activeTab === 'notes'" class="max-w-2xl rounded-xl border border-slate-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-slate-900">Internal notes</h3>
            <p class="mt-3 whitespace-pre-wrap text-sm text-slate-600">{{ client.notes || 'No notes recorded.' }}</p>
            <p class="mt-4 text-xs text-amber-700">Internal only — never shown on the client portal.</p>
        </div>
    </AuthenticatedLayout>
</template>
