<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ reports: Object });
</script>

<template>
    <Head title="Reports" />
    <PortalLayout>
        <h1 class="text-xl font-semibold">Reports</h1>
        <p class="mt-1 text-sm text-slate-500">Published performance reports only</p>
        <div class="mt-4 space-y-2">
            <Link v-for="r in reports.data" :key="r.id" :href="route('portal.reports.show', r.id)" class="block rounded-xl border bg-white px-4 py-3 hover:border-teal-300">
                <div class="font-medium">{{ r.title }}</div>
                <div class="text-sm text-slate-500">{{ r.period_start }} → {{ r.period_end }}</div>
            </Link>
            <p v-if="!reports.data?.length" class="text-slate-500">No Data</p>
        </div>
    </PortalLayout>
</template>
