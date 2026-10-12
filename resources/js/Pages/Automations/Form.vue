<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    rule: Object,
    triggers: Object,
    actionTypes: Array,
    operators: Array,
});

const form = useForm({
    name: props.rule?.name || '',
    description: props.rule?.description || '',
    trigger_type: props.rule?.trigger_type || Object.keys(props.triggers || {})[0],
    group_operator: props.rule?.group_operator || 'and',
    conditions: props.rule?.conditions?.length
        ? props.rule.conditions.map((c) => ({ field: c.field, operator: c.operator, value: c.value }))
        : [{ field: 'priority', operator: 'equals', value: 'urgent' }],
    actions: props.rule?.actions?.length
        ? props.rule.actions.map((a) => ({
            action_type: a.action_type,
            config: a.config || {},
            delay_minutes: a.delay_minutes || 0,
            continue_on_match: a.continue_on_match !== false,
            config_json: JSON.stringify(a.config || {}, null, 0),
        }))
        : [{ action_type: 'create_follow_up', config_json: '{"title":"Follow up urgent request","due_at":null}', delay_minutes: 0, continue_on_match: true }],
});

const isEdit = computed(() => !!props.rule?.id);

const submit = () => {
    form.actions = form.actions.map((a) => {
        let config = {};
        try { config = JSON.parse(a.config_json || '{}'); } catch (e) { config = {}; }
        return {
            action_type: a.action_type,
            config,
            delay_minutes: Number(a.delay_minutes || 0),
            continue_on_match: !!a.continue_on_match,
        };
    });
    if (isEdit.value) {
        form.put(route('automations.update', props.rule.id));
    } else {
        form.post(route('automations.store'));
    }
};

const addCondition = () => form.conditions.push({ field: '', operator: 'equals', value: '' });
const addAction = () => form.actions.push({ action_type: 'send_notification', config_json: '{}', delay_minutes: 0, continue_on_match: true });
</script>

<template>
    <Head :title="isEdit ? 'Edit automation' : 'New automation'" />
    <AuthenticatedLayout>
        <Link :href="route('automations.index')" class="text-sm text-teal-700 hover:underline">← Automations</Link>
        <h1 class="mt-2 mb-6 text-2xl font-semibold">{{ isEdit ? 'Edit' : 'Create' }} automation</h1>

        <form class="max-w-3xl space-y-6" @submit.prevent="submit">
            <section class="rounded-xl border bg-white p-4 space-y-3">
                <h2 class="font-medium">WHEN</h2>
                <div><InputLabel value="Name" /><TextInput v-model="form.name" class="mt-1 w-full" required /></div>
                <div>
                    <InputLabel value="Trigger" />
                    <select v-model="form.trigger_type" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option v-for="(label, key) in triggers" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
            </section>

            <section class="rounded-xl border bg-white p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-medium">IF (conditions)</h2>
                    <select v-model="form.group_operator" class="rounded-md border-slate-300 text-xs">
                        <option value="and">AND</option>
                        <option value="or">OR</option>
                    </select>
                </div>
                <div v-for="(c, i) in form.conditions" :key="i" class="grid grid-cols-3 gap-2">
                    <TextInput v-model="c.field" placeholder="field" class="w-full" />
                    <select v-model="c.operator" class="rounded-md border-slate-300 text-sm">
                        <option v-for="op in operators" :key="op" :value="op">{{ op }}</option>
                    </select>
                    <TextInput v-model="c.value" placeholder="value" class="w-full" />
                </div>
                <button type="button" class="text-sm text-teal-700" @click="addCondition">+ condition</button>
            </section>

            <section class="rounded-xl border bg-white p-4 space-y-3">
                <h2 class="font-medium">THEN (actions)</h2>
                <div v-for="(a, i) in form.actions" :key="i" class="space-y-2 rounded border border-slate-100 p-3">
                    <select v-model="a.action_type" class="w-full rounded-md border-slate-300 text-sm">
                        <option v-for="t in actionTypes" :key="t" :value="t">{{ t }}</option>
                    </select>
                    <textarea v-model="a.config_json" rows="3" class="w-full rounded-md border-slate-300 font-mono text-xs" placeholder='{"title":"..."}' />
                    <div class="flex gap-3 text-xs">
                        <label>Delay minutes <input v-model="a.delay_minutes" type="number" min="0" class="ml-1 w-20 rounded border-slate-300" /></label>
                        <label class="flex items-center gap-1"><input v-model="a.continue_on_match" type="checkbox" /> Re-check conditions</label>
                    </div>
                </div>
                <button type="button" class="text-sm text-teal-700" @click="addAction">+ action</button>
            </section>

            <PrimaryButton :disabled="form.processing">Save draft</PrimaryButton>
        </form>
    </AuthenticatedLayout>
</template>
