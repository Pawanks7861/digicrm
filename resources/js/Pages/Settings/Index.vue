<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    cards: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Settings" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <p class="text-sm text-mute">Workspace</p>
            <h1 class="text-2xl font-semibold tracking-tight">Settings</h1>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="card in cards"
                :key="card.href"
                :href="card.href"
                class="rounded-card bg-card p-5 shadow-sm transition hover:bg-mist"
            >
                <h2 class="text-lg font-semibold">{{ card.title }}</h2>
                <p class="mt-1 text-sm text-mute">{{ card.body }}</p>
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
