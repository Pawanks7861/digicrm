<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const mobile = ref(false);
const flash = computed(() => page.props.flash?.success);
const settings = computed(() => page.props.portalSettings || {});
const brand = computed(() => page.props.branding || {});

const nav = computed(() => {
    const items = [{ name: 'Dashboard', href: 'portal.dashboard' }];
    if (settings.value.show_approvals !== false) items.push({ name: 'Approvals', href: 'portal.approvals' });
    if (settings.value.show_requests !== false) items.push({ name: 'Requests', href: 'portal.requests' });
    if (settings.value.show_content !== false) items.push({ name: 'Content', href: 'portal.content' });
    if (settings.value.show_campaigns !== false) items.push({ name: 'Campaigns', href: 'portal.campaigns' });
    if (settings.value.show_files !== false) items.push({ name: 'Files', href: 'portal.files' });
    if (settings.value.show_quotations) items.push({ name: 'Quotations', href: 'portal.quotations' });
    if (settings.value.show_invoices) items.push({ name: 'Invoices', href: 'portal.invoices' });
    if (settings.value.show_payments) items.push({ name: 'Payments', href: 'portal.payments' });
    if (settings.value.show_performance) items.push({ name: 'Performance', href: 'portal.performance' });
    if (settings.value.show_reports) items.push({ name: 'Reports', href: 'portal.reports' });
    if (settings.value.show_leads) items.push({ name: 'Leads', href: 'portal.leads' });
    if (settings.value.show_leads && settings.value.show_lead_reports) items.push({ name: 'Lead Summary', href: 'portal.leads.summary' });
    items.push({ name: 'Notifications', href: 'portal.notifications' });
    items.push({ name: 'Alert prefs', href: 'portal.notification-preferences' });
    return items;
});
</script>

<template>
    <Head>
        <link v-if="brand.favicon_url" rel="icon" :href="brand.favicon_url" head-key="favicon" />
    </Head>
    <div class="min-h-screen text-ink">
        <header class="sticky top-0 z-20 px-4 py-4">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3">
                <div class="flex items-center gap-2 text-[15px] font-semibold tracking-tight">
                    <img v-if="brand.logo_url" :src="brand.logo_url" alt="" class="h-8 w-8 rounded-lg object-contain" />
                    {{ brand.name || page.props.appName || 'Client Portal' }}
                </div>
                <button type="button" class="rounded-full bg-card px-3 py-1.5 text-sm lg:hidden" @click="mobile = !mobile">Menu</button>
                <nav class="hidden flex-wrap justify-center gap-1 lg:flex">
                    <Link
                        v-for="item in nav"
                        :key="item.name"
                        :href="route(item.href)"
                        class="rounded-full px-3 py-1.5 text-sm transition duration-150"
                        :class="route().current(item.href) || route().current(item.href + '.*') ? 'nav-active font-medium text-ink' : 'text-mute hover:bg-mist hover:text-ink'"
                    >{{ item.name }}</Link>
                </nav>
                <div class="flex items-center gap-3">
                    <span v-if="page.props.auth?.user?.unread_notifications" class="rounded-full bg-accent px-2 py-0.5 text-xs text-white">{{ page.props.auth.user.unread_notifications }}</span>
                    <Link :href="route('logout')" method="post" as="button" class="rounded-full bg-card px-3 py-1.5 text-sm text-ink">Log out</Link>
                </div>
            </div>
            <nav v-if="mobile" class="mx-auto mt-3 flex max-w-6xl flex-col gap-1 rounded-card bg-card p-2 lg:hidden">
                <Link v-for="item in nav" :key="item.name" :href="route(item.href)" class="rounded-2xl px-3 py-2 text-sm" @click="mobile = false">{{ item.name }}</Link>
            </nav>
        </header>
        <div v-if="flash" class="mx-auto max-w-6xl px-4">
            <div class="rounded-card bg-sage px-4 py-2 text-sm text-ink">{{ flash }}</div>
        </div>
        <main class="mx-auto max-w-6xl px-4 py-4">
            <slot />
        </main>
    </div>
</template>
