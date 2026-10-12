<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ run: Object });
</script>

<template>
    <Head :title="`Run #${run.id}`" />
    <AuthenticatedLayout>
        <Link :href="route('automation-runs.index')" class="text-sm text-teal-700 hover:underline">← Runs</Link>
        <h1 class="mt-2 text-2xl font-semibold">Run #{{ run.id }}</h1>
        <p class="text-sm text-slate-500">{{ run.rule?.name }} · {{ run.trigger_type }} · {{ run.status }} {{ run.dry_run ? '(dry-run)' : '' }}</p>

        <div class="mt-6 space-y-2">
            <div v-for="ar in run.action_runs" :key="ar.id" class="rounded-xl border bg-white px-4 py-3 text-sm">
                <div class="font-medium">{{ ar.action_type }} · {{ ar.status }}</div>
                <pre v-if="ar.result" class="mt-1 overflow-auto text-xs text-slate-500">{{ JSON.stringify(ar.result, null, 2) }}</pre>
                <p v-if="ar.error" class="mt-1 text-xs text-red-600">{{ ar.error }}</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
