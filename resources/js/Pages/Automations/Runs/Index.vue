<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ runs: Object, filters: Object });
</script>

<template>
    <Head title="Automation runs" />
    <AuthenticatedLayout>
        <div class="mb-6 flex items-end justify-between">
            <h1 class="text-2xl font-semibold">Automation runs</h1>
            <Link :href="route('automations.index')" class="text-sm text-teal-700 hover:underline">Rules</Link>
        </div>
        <div class="space-y-2">
            <Link
                v-for="r in runs.data"
                :key="r.id"
                :href="route('automation-runs.show', r.id)"
                class="block rounded-xl border bg-white px-4 py-3 text-sm"
            >
                #{{ r.id }} · {{ r.rule?.name || '—' }} · {{ r.trigger_type }} · <span class="capitalize">{{ r.status }}</span>
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
