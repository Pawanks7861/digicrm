<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-[0.9rem] border-line bg-[#F4F7F8] text-ink shadow-none focus:border-accent focus:ring-accent"
        v-model="model"
        ref="input"
    />
</template>
