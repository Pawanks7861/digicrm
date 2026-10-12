<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const brand = computed(() => usePage().props.branding || {});
</script>

<template>
    <Head>
        <link v-if="brand.favicon_url" rel="icon" :href="brand.favicon_url" head-key="favicon" />
    </Head>
    <div class="flex min-h-screen flex-col items-center bg-canvas pt-6 sm:justify-center sm:pt-0">
        <div>
            <Link href="/" class="flex items-center gap-2 text-ink">
                <img v-if="brand.logo_url" :src="brand.logo_url" alt="" class="h-10 w-10 object-contain" />
                <ApplicationLogo v-else class="h-10 w-10 fill-current text-accent" />
                <span class="text-lg font-semibold tracking-tight">{{ brand.name || 'DigiCRM' }}</span>
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-card px-6 py-6 sm:max-w-md sm:rounded-card"
        >
            <slot />
        </div>
    </div>
</template>
