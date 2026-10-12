<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-full border border-transparent bg-haze px-4 py-2 text-xs font-semibold tracking-wide text-ink transition duration-150 hover:bg-line focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 disabled:opacity-25"
    >
        <slot />
    </button>
</template>
