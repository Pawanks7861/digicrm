<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    client: Object,
    from: String,
    to: String,
    items: Array,
});
</script>

<template>
    <Head title="Top Content" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold">Top Content — {{ client.company_name }}</h1>
            <p class="text-sm text-slate-500">{{ from }} → {{ to }}</p>
        </div>
        <div class="space-y-2">
            <div v-for="item in items" :key="item.content_item_id" class="flex justify-between rounded-xl border bg-white px-4 py-3">
                <Link :href="route('content.performance', item.content_item_id)" class="text-teal-800 hover:underline">#{{ item.content_item_id }}</Link>
                <span class="font-medium">{{ item.display }}</span>
            </div>
            <p v-if="!items?.length" class="text-slate-500">No Data</p>
        </div>
    </AuthenticatedLayout>
</template>
