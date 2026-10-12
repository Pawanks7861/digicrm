<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mappings: Object,
    clients: Array,
    can: { type: Object, default: () => ({}) },
});

const form = useForm({
    client_id: '',
    campaign_id: '',
    content_item_id: '',
    provider: 'meta',
    entity_type: 'ad_campaign',
    external_id: '',
    external_name: '',
});

function submit() {
    form.post(route('performance.mappings.store'), { preserveScroll: true, onSuccess: () => form.reset('external_id', 'external_name') });
}
</script>

<template>
    <Head title="Metric Mappings" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <Link :href="route('performance.index')" class="text-sm text-teal-700 hover:underline">← Performance</Link>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Mappings</h1>
            <p class="mt-1 text-sm text-slate-500">CRM campaigns/content ↔ external provider entities. Suggest only — never auto-save uncertain.</p>
        </div>

        <form v-if="can.manage" class="mb-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="submit">
            <select v-model="form.client_id" class="rounded-md border-slate-300 text-sm" required>
                <option value="">Client</option>
                <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
            </select>
            <select v-model="form.provider" class="rounded-md border-slate-300 text-sm">
                <option value="meta">Meta</option>
                <option value="google_ads">Google Ads</option>
                <option value="google_analytics">GA4</option>
                <option value="youtube">YouTube</option>
            </select>
            <select v-model="form.entity_type" class="rounded-md border-slate-300 text-sm">
                <option value="ad_campaign">Ad campaign</option>
                <option value="ad_set">Ad set</option>
                <option value="ad">Ad</option>
                <option value="ga4_property">GA4 property</option>
                <option value="youtube_video">YouTube video</option>
                <option value="social_post">Social post</option>
            </select>
            <input v-model="form.external_id" class="rounded-md border-slate-300 text-sm" placeholder="External ID" required />
            <input v-model="form.external_name" class="rounded-md border-slate-300 text-sm" placeholder="External name" />
            <input v-model="form.campaign_id" class="rounded-md border-slate-300 text-sm" placeholder="Campaign ID (optional)" />
            <button type="submit" class="rounded-lg bg-teal-700 px-3 py-2 text-sm text-white">Save mapping</button>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr><th class="px-4 py-3">Client</th><th class="px-4 py-3">Provider</th><th class="px-4 py-3">External</th><th class="px-4 py-3">Campaign</th></tr>
                </thead>
                <tbody>
                    <tr v-for="m in mappings.data" :key="m.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ m.client?.company_name }}</td>
                        <td class="px-4 py-3">{{ m.provider }} / {{ m.entity_type }}</td>
                        <td class="px-4 py-3">{{ m.external_name || m.external_id }}</td>
                        <td class="px-4 py-3">{{ m.campaign?.name || '—' }}</td>
                    </tr>
                    <tr v-if="!mappings.data?.length"><td colspan="4" class="px-4 py-8 text-center text-slate-500">No Data</td></tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
