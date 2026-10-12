<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({ rule: Object, canEdit: Boolean, canActivate: Boolean });
</script>

<template>
    <Head :title="rule.name" />
    <AuthenticatedLayout>
        <Link :href="route('automations.index')" class="text-sm text-teal-700 hover:underline">← Automations</Link>
        <div class="mt-2 mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">{{ rule.name }}</h1>
                <p class="text-sm text-slate-500">{{ rule.trigger_type }} · <span class="capitalize">{{ rule.status }}</span></p>
            </div>
            <div class="flex flex-wrap gap-2 text-sm">
                <Link v-if="canEdit" :href="route('automations.edit', rule.id)" class="rounded-lg border px-3 py-2">Edit</Link>
                <button v-if="canActivate && rule.status !== 'active'" type="button" class="rounded-lg bg-teal-700 px-3 py-2 text-white" @click="router.post(route('automations.activate', rule.id))">Activate</button>
                <button v-if="canActivate && rule.status === 'active'" type="button" class="rounded-lg border px-3 py-2" @click="router.post(route('automations.deactivate', rule.id))">Disable</button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border bg-white p-4">
                <h2 class="mb-2 font-medium">IF</h2>
                <ul class="space-y-1 text-sm">
                    <li v-for="c in rule.conditions" :key="c.id">{{ c.field }} {{ c.operator }} {{ c.value }}</li>
                    <li v-if="!rule.conditions?.length" class="text-slate-400">No conditions (always match)</li>
                </ul>
            </div>
            <div class="rounded-xl border bg-white p-4">
                <h2 class="mb-2 font-medium">THEN</h2>
                <ul class="space-y-1 text-sm">
                    <li v-for="a in rule.actions" :key="a.id">{{ a.action_type }} <span v-if="a.delay_minutes" class="text-slate-400">(+{{ a.delay_minutes }}m)</span></li>
                </ul>
            </div>
        </div>

        <div class="mt-6">
            <h2 class="mb-2 font-medium">Recent runs</h2>
            <ul class="space-y-1 text-sm">
                <li v-for="run in rule.runs" :key="run.id">
                    <Link :href="route('automation-runs.show', run.id)" class="text-teal-700 hover:underline">#{{ run.id }} {{ run.status }}</Link>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
