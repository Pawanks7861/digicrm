<script setup>
import { computed, ref } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavIcon from '@/Components/Shell/NavIcon.vue';
import { Bell, Search } from 'lucide-vue-next';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const showingMobile = ref(false);
const moreOpen = ref(false);
const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success);
const canClients = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('clients.view') || perms.includes('clients.view_all');
});
const canPackages = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('packages.view') || perms.includes('packages.manage') || perms.includes('packages.create');
});
const canCampaigns = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('campaigns.view') || perms.includes('campaigns.view_all') || perms.includes('campaigns.manage') || perms.includes('campaigns.create');
});
const canTasks = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('tasks.view') || perms.includes('tasks.view_all') || perms.includes('tasks.view_assigned') || perms.includes('tasks.manage');
});
const canReviews = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('internal_reviews.view') || perms.includes('internal_reviews.approve');
});
const canContent = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('content.view') || perms.includes('content.view_all') || perms.includes('content.manage') || perms.includes('content.create');
});
const canCalendar = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('content_calendar.view') || canContent.value;
});
const canRequests = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('client_requests.view') || perms.includes('client_requests.view_all') || perms.includes('client_requests.review');
});
const canFollowUps = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('followups.view') || perms.includes('followups.view_all');
});
const canTime = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('timesheets.view_own') || perms.includes('timesheets.view_all') || perms.includes('time_entries.view_own') || perms.includes('time_entries.create');
});
const canWorkload = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('workload.view');
});
const canDelays = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('delays.view');
});
const canReports = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('reports.operational') || perms.includes('reports.financial') || perms.includes('profitability.view') || perms.includes('reports.view');
});
const canFinance = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('quotations.view') || perms.includes('invoices.view') || perms.includes('payments.view') || perms.includes('receivables.view');
});
const canNotificationSettings = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('notification_settings.view') || perms.includes('notification_settings.manage') || perms.includes('email_settings.manage');
});
const canIntegrations = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('integrations.view') || perms.includes('integrations.manage');
});
const canAutomations = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('automations.view');
});
const canPerformance = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('performance.view') || perms.includes('client_reports.view');
});
const canLeads = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('leads.view') || perms.includes('leads.view_all');
});
const canPipeline = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('opportunities.view');
});
const canChat = computed(() => (page.props.auth?.user?.permissions || []).includes('chat.use') || (page.props.auth?.user?.roles || []).includes('super_admin'));
const canSettings = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    const roles = page.props.auth?.user?.roles || [];
    return roles.includes('super_admin')
        || perms.includes('users.manage')
        || perms.includes('users.view')
        || perms.includes('audit_logs.view')
        || perms.includes('company_settings.view')
        || perms.includes('company_settings.manage')
        || perms.includes('company_branding.manage');
});
const canHr = computed(() => {
    const perms = page.props.auth?.user?.permissions || [];
    return perms.includes('hr.dashboard.view')
        || perms.includes('hr.employees.view_own')
        || perms.includes('hr.employees.view_all')
        || perms.includes('hr.attendance.view_own')
        || (page.props.auth?.user?.roles || []).includes('super_admin');
});
const brand = computed(() => page.props.branding || {});

const activeTimer = computed(() => page.props.activeTimer);
const elapsedLabel = computed(() => {
    const s = activeTimer.value?.elapsed_seconds || 0;
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
});

const nav = computed(() => {
    const items = [
        { name: 'Dashboard', icon: 'dashboard', href: route('dashboard'), current: route().current('dashboard') },
    ];
    if (canChat.value) {
        items.push({
            name: 'Chat',
            icon: 'chat',
            href: route('chat.index'),
            current: route().current('chat.*'),
            badge: page.props.unreadChats || 0,
        });
    }
    if (canClients.value) {
        items.push({ name: 'Clients', icon: 'clients', href: route('clients.index'), current: route().current('clients.*') });
    }
    if (canLeads.value) {
        items.push({ name: 'Leads', icon: 'leads', href: route('leads.index'), current: route().current('leads.*') });
    }
    if (canPipeline.value) {
        items.push({ name: 'Pipeline', icon: 'pipeline', href: route('pipeline.index'), current: route().current('pipeline.*') || route().current('opportunities.*') });
    }
    if (canCampaigns.value) {
        items.push({ name: 'Campaigns', icon: 'campaigns', href: route('campaigns.index'), current: route().current('campaigns.*') || route().current('deliverables.*') });
    }
    if (canRequests.value) {
        items.push({ name: 'Requests', icon: 'requests', href: route('requests.index'), current: route().current('requests.*') });
    }
    if (canContent.value) {
        items.push({ name: 'Content', icon: 'content', href: route('content.index'), current: route().current('content.*') });
    }
    if (canCalendar.value) {
        items.push({ name: 'Calendar', icon: 'calendar', href: route('calendar.index'), current: route().current('calendar.*') });
    }
    if (canTasks.value) {
        items.push({ name: 'Tasks', icon: 'tasks', href: route('tasks.index'), current: route().current('tasks.*') });
    }
    if (canHr.value) {
        items.push({ name: 'HR', icon: 'workload', href: route('hr.dashboard'), current: route().current('hr.*') });
    }
    if (canFollowUps.value) {
        items.push({ name: 'Follow-ups', icon: 'followups', href: route('follow-ups.index'), current: route().current('follow-ups.*') });
    }
    if (canTime.value) {
        items.push({ name: 'Time', icon: 'time', href: route('timesheet.index'), current: route().current('timesheet.*') || route().current('time-entries.*') || route().current('timesheets.*') });
    }
    if (canWorkload.value) {
        items.push({ name: 'Workload', icon: 'workload', href: route('workload.index'), current: route().current('workload.*') || route().current('utilization.*') || route().current('capacity.*') });
    }
    if (canDelays.value) {
        items.push({ name: 'Delays', icon: 'delays', href: route('delays.index'), current: route().current('delays.*') });
    }
    if (canReports.value) {
        items.push({ name: 'Reports', icon: 'reports', href: route('reports.index'), current: route().current('reports.*') || route().current('cost-rates.*') });
    }
    if (canPerformance.value) {
        items.push({ name: 'Performance', icon: 'performance', href: route('performance.index'), current: route().current('performance.*') || route().current('client-reports.*') });
    }
    if (canFinance.value) {
        items.push({ name: 'Finance', icon: 'finance', href: route('quotations.index'), current: route().current('quotations.*') || route().current('invoices.*') || route().current('payments.*') || route().current('finance.*') });
    }
    if (canNotificationSettings.value) {
        items.push({ name: 'Alerts', icon: 'alerts', href: route('settings.notifications.health'), current: route().current('settings.notifications.*') });
    }
    if (canIntegrations.value) {
        items.push({ name: 'Integrations', icon: 'integrations', href: route('integrations.index'), current: route().current('integrations.*') });
    }
    if (canAutomations.value) {
        items.push({ name: 'Automations', icon: 'automations', href: route('automations.index'), current: route().current('automations.*') || route().current('automation-runs.*') });
    }
    if (canReviews.value) {
        items.push({ name: 'Reviews', icon: 'reviews', href: route('internal-reviews.index'), current: route().current('internal-reviews.*') });
    }
    if (canPackages.value) {
        items.push({ name: 'Packages', icon: 'packages', href: route('service-packages.index'), current: route().current('service-packages.*') || route().current('client-packages.*') });
    }
    if (canSettings.value) {
        items.push({ name: 'Settings', icon: 'settings', href: route('settings.index'), current: route().current('settings.*') });
    }
    return items;
});

const railItems = computed(() => nav.value.slice(0, 8));
const overflowItems = computed(() => nav.value.slice(8));
const currentNav = computed(() => nav.value.find((item) => item.current));
</script>

<template>
    <Head>
        <link v-if="brand.favicon_url" rel="icon" :href="brand.favicon_url" head-key="favicon" />
    </Head>
    <div class="min-h-screen p-2 text-ink sm:p-4">
        <div class="flex min-h-[calc(100vh-1rem)] overflow-hidden rounded-[28px] bg-[#fffdf9] shadow-float sm:min-h-[calc(100vh-2rem)]">
            <aside class="hidden w-[232px] shrink-0 flex-col border-r border-line/70 px-3 py-5 lg:flex">
                <Link :href="route('dashboard')" class="mb-6 flex items-center gap-2.5 px-2">
                    <img v-if="brand.logo_url" :src="brand.logo_url" :alt="brand.name || 'Company logo'" class="h-16 w-16 shrink-0 rounded-2xl object-contain" />
                    <span v-else class="flex h-9 w-9 items-center justify-center rounded-xl bg-accent text-sm font-semibold text-white">
                        {{ (brand.name || $page.props.appName || 'D').charAt(0) }}
                    </span>
                    <span class="text-[15px] font-semibold tracking-tight leading-tight">{{ brand.name || $page.props.appName || 'DigiCRM' }}</span>
                </Link>
                <nav class="flex-1 space-y-1 overflow-y-auto pr-1">
                    <Link
                        v-for="item in nav"
                        :key="item.name"
                        :href="item.href"
                        class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm text-mute transition duration-150 hover:bg-mist hover:text-ink"
                        :class="item.current ? 'nav-active font-medium text-ink' : ''"
                    >
                        <span :class="item.current ? 'text-accent' : ''">
                            <NavIcon :name="item.icon" :size="18" />
                        </span>
                        {{ item.name }}
                        <span v-if="item.badge" class="ml-auto rounded-full bg-accent px-1.5 text-[10px] font-semibold text-white">{{ item.badge }}</span>
                    </Link>
                </nav>
            </aside>

            <div v-if="showingMobile" class="fixed inset-0 z-40 bg-ink/20 lg:hidden" @click="showingMobile = false" />
            <aside
                class="fixed inset-y-0 left-0 z-50 w-72 overflow-y-auto bg-[#fffdf9] p-4 shadow-float transition duration-200 lg:hidden"
                :class="showingMobile ? 'translate-x-0' : '-translate-x-full'"
            >
                <div class="mb-4 flex items-center gap-2 px-2 text-sm font-semibold">
                    <img v-if="brand.logo_url" :src="brand.logo_url" :alt="brand.name || 'Company logo'" class="h-12 w-12 rounded-xl object-contain" />
                    {{ brand.name || $page.props.appName || 'DigiCRM' }}
                </div>
                <nav class="space-y-1">
                    <Link
                        v-for="item in nav"
                        :key="'m-' + item.name"
                        :href="item.href"
                        class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm text-mute"
                        :class="item.current ? 'nav-active text-ink' : ''"
                        @click="showingMobile = false"
                    >
                        <NavIcon :name="item.icon" />
                        {{ item.name }}
                        <span v-if="item.badge" class="ml-auto rounded-full bg-accent px-1.5 text-[10px] font-semibold text-white">{{ item.badge }}</span>
                    </Link>
                </nav>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col bg-[linear-gradient(180deg,#fffaf3_0%,#fff7ee_100%)]">
                <header class="flex h-[72px] items-center gap-3 px-4 sm:px-6">
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-card text-ink lg:hidden"
                        @click="showingMobile = !showingMobile"
                    >
                        <span class="sr-only">Open navigation</span>
                        <NavIcon name="dashboard" :size="18" />
                    </button>
                    <h1 class="truncate text-lg font-semibold tracking-tight">{{ currentNav?.name || 'Workspace' }}</h1>
                    <div class="ml-auto flex items-center gap-2">
                        <div class="relative hidden md:block">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-mute" />
                            <input
                                type="search"
                                placeholder="Search"
                                class="w-52 rounded-full border border-line/80 bg-white py-2 pl-9 pr-3 text-sm text-ink shadow-sm placeholder:text-mute"
                                disabled
                            />
                        </div>
                        <div
                            v-if="activeTimer"
                            class="hidden items-center gap-2 rounded-full bg-sun/80 px-3 py-1.5 text-xs font-medium text-ink sm:flex"
                            :title="activeTimer.stale ? 'Timer has been running a long time' : ''"
                        >
                            <span class="font-mono">{{ elapsedLabel }}</span>
                            <button type="button" class="rounded-full bg-accent px-2 py-0.5 text-[10px] text-white" @click="router.post(route('time-entries.stop', activeTimer.id))">Stop</button>
                        </div>
                        <Link :href="route('notifications.index')" class="relative inline-flex h-10 w-10 items-center justify-center rounded-full bg-white text-ink shadow-sm" title="Notifications">
                            <Bell class="h-[18px] w-[18px]" />
                            <span
                                v-if="$page.props.auth?.user?.unread_notifications"
                                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 text-[10px] font-semibold text-white"
                            >{{ $page.props.auth.user.unread_notifications }}</span>
                        </Link>
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full bg-accent text-sm font-semibold text-white">
                                    {{ $page.props.auth.user.name.charAt(0) }}
                                </button>
                            </template>
                            <template #content>
                                <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                                <DropdownLink v-if="canSettings" :href="route('settings.index')">Settings</DropdownLink>
                                <DropdownLink :href="route('profile.notifications')">Notification prefs</DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </header>

                <div v-if="flashSuccess" class="mx-4 mb-2 rounded-card bg-sun/50 px-4 py-3 text-sm text-ink sm:mx-6">
                    {{ flashSuccess }}
                </div>
                <main class="flex-1 px-4 pb-6 sm:px-6">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
