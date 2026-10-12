<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    items: Object,
    filters: Object,
    campaigns: Array,
    platforms: Array,
});

const status = ref(props.filters.status || '');
const campaign_id = ref(props.filters.campaign_id || '');

watch([status, campaign_id], () => {
    router.get(route('portal.content'), {
        status: status.value || undefined,
        campaign_id: campaign_id.value || undefined,
    }, { preserveState: true, replace: true });
});

const label = (v) => (v || '').replaceAll('_', ' ');
</script>

<template>
    <Head title="Content" />
    <PortalLayout>
        <h1 class="text-2xl font-semibold tracking-tight">Your Content</h1>
        <div class="mt-4 flex flex-wrap gap-2">
            <select v-model="status" class="rounded-lg border-slate-200 text-sm">
                <option value="">All</option>
                <option value="sent_to_client">Pending Approval</option>
                <option value="client_approved">Approved</option>
                <option value="client_changes_requested">Changes Requested</option>
                <option value="scheduled">Scheduled</option>
                <option value="published">Published</option>
            </select>
            <select v-model="campaign_id" class="rounded-lg border-slate-200 text-sm">
                <option value="">All campaigns</option>
                <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
        </div>

        <div v-if="items.data.length" class="mt-6 space-y-3">
            <Link
                v-for="item in items.data"
                :key="item.id"
                :href="route('portal.content.show', item.id)"
                class="block rounded-2xl border border-slate-200 bg-white p-4 hover:border-teal-300"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="font-semibold">{{ item.title }}</div>
                    <StatusBadge :status="item.status" />
                </div>
                <div class="mt-1 text-sm text-slate-500 capitalize">
                    {{ label(item.platform) }} · {{ item.campaign?.name || '—' }}
                </div>
            </Link>
        </div>
        <EmptyState v-else class="mt-8" title="No content yet." description="Your agency will share creatives here for review." />
    </PortalLayout>
</template>
