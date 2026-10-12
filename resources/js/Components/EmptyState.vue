<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="rounded-card border border-dashed border-line bg-card px-6 py-14 text-center">
        <h3 class="text-base font-semibold text-ink">{{ title }}</h3>
        <p v-if="description" class="mt-1 text-sm text-mute">{{ description }}</p>
        <div v-if="$slots.action" class="mt-4 flex justify-center">
            <slot name="action" />
        </div>
    </div>
</template>
