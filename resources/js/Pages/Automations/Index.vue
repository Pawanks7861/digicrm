<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ rules: Object, canCreate: Boolean, canActivate: Boolean });
</script>

<template>
    <Head title="Automations" />
    <AuthenticatedLayout>
        <div class="mb-6 flex items-end justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Automations</h1>
                <p class="text-sm text-slate-500">WHEN → IF → THEN rules. Draft until activated.</p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('automation-runs.index')" class="rounded-lg border px-3 py-2 text-sm">Runs</Link>
                <Link v-if="canCreate" :href="route('automations.create')" class="rounded-lg bg-teal-700 px-3 py-2 text-sm text-white">New rule</Link>
            </div>
        </div>
        <div class="space-y-2">
            <Link
                v-for="r in rules.data"
                :key="r.id"
                :href="route('automations.show', r.id)"
                class="block rounded-xl border bg-white px-4 py-3 hover:border-teal-300"
            >
                <div class="flex justify-between gap-2">
                    <div>
                        <div class="font-medium">{{ r.name }}</div>
                        <div class="text-xs text-slate-500">{{ r.trigger_type }} · {{ r.conditions_count }} conditions · {{ r.actions_count }} actions</div>
                    </div>
                    <span class="text-xs capitalize text-slate-500">{{ r.status }}</span>
                </div>
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
