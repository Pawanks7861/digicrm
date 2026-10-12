<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const perms = computed(() => page.props.auth?.user?.permissions || []);
const has = (p) => perms.value.includes(p);

const links = computed(() => {
    const items = [];
    if (has('leads.view') || has('leads.view_all')) items.push({ name: 'Inbox', route: 'leads.index', match: ['leads.index', 'leads.show', 'leads.create', 'leads.edit'] });
    if (has('opportunities.view')) {
        items.push({ name: 'Pipeline', route: 'pipeline.index', match: ['pipeline.*'] });
        items.push({ name: 'Opportunities', route: 'opportunities.index', match: ['opportunities.*'] });
    }
    if (has('leads.import')) items.push({ name: 'Import', route: 'leads.import', match: ['leads.import*'] });
    if (has('leads.merge')) items.push({ name: 'Duplicates', route: 'leads.duplicates', match: ['leads.duplicates'] });
    if (has('lead_reports.view')) {
        items.push({ name: 'Analytics', route: 'leads.reports.analytics', match: ['leads.reports.analytics'] });
        items.push({ name: 'Campaign Leads', route: 'leads.reports.campaigns', match: ['leads.reports.campaigns'] });
    }
    if (has('lead_reports.view') && (has('reports.financial') || has('financial_dashboard.view'))) {
        items.push({ name: 'Sales Revenue', route: 'leads.reports.sales-revenue', match: ['leads.reports.sales-revenue'] });
    }
    if (has('lead_sources.manage')) {
        items.push({ name: 'Sources', route: 'leads.settings.sources', match: ['leads.settings.sources'] });
        items.push({ name: 'Capture Forms', route: 'leads.settings.forms', match: ['leads.settings.forms'] });
    }
    if (has('lead_assignment_rules.manage')) items.push({ name: 'Assignment Rules', route: 'leads.settings.rules', match: ['leads.settings.rules'] });
    return items;
});
const isCurrent = (item) => item.match.some((m) => route().current(m));
</script>

<template>
    <nav class="-mx-1 mb-5 flex gap-1 overflow-x-auto pb-1 text-sm">
        <Link
            v-for="item in links"
            :key="item.route"
            :href="route(item.route)"
            class="whitespace-nowrap rounded-lg px-3 py-1.5"
            :class="isCurrent(item) ? 'bg-teal-700 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'"
        >{{ item.name }}</Link>
    </nav>
</template>
