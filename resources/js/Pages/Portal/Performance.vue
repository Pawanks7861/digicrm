<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    from: String,
    to: String,
    dashboard: Object,
    flags: Object,
});
const from = ref(props.from);
const to = ref(props.to);
function apply() {
    router.get(route('portal.performance'), { from: from.value, to: to.value }, { preserveState: true });
}
</script>

<template>
    <Head title="Performance" />
    <PortalLayout>
        <h1 class="text-xl font-semibold">Performance</h1>
        <p class="mt-1 text-sm text-slate-500">{{ dashboard.freshness?.label }} · {{ dashboard.coverage?.label }}</p>
        <div class="mt-4 flex gap-2 text-sm">
            <input v-model="from" type="date" class="rounded-md border-slate-300" />
            <input v-model="to" type="date" class="rounded-md border-slate-300" />
            <button class="rounded-lg bg-teal-700 px-3 py-1.5 text-white" @click="apply">Apply</button>
        </div>
        <div v-if="dashboard.coverage?.warning" class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ dashboard.coverage.warning }}</div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div v-for="m in dashboard.metrics" :key="m.code" class="rounded-xl border bg-white p-4">
                <div class="text-xs uppercase text-slate-500">{{ m.label }}</div>
                <div class="mt-2 text-2xl font-semibold">{{ m.display }}</div>
            </div>
        </div>
        <p v-if="!Object.keys(dashboard.metrics || {}).length" class="mt-6 text-slate-500">No Data</p>
    </PortalLayout>
</template>
