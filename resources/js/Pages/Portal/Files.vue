<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ files: Array });
</script>

<template>
    <Head title="Files" />
    <PortalLayout>
        <h1 class="text-2xl font-semibold tracking-tight">Shared Files</h1>
        <div v-if="files.length" class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Folder</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="f in files" :key="f.id">
                        <td class="px-4 py-3">{{ f.original_name }}</td>
                        <td class="px-4 py-3">{{ f.folder }}</td>
                        <td class="px-4 py-3 text-right">
                            <a :href="route('portal.files.download', f.id)" class="text-teal-700 hover:underline">Download</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else class="mt-8" title="No shared files." description="Only files marked visible to you appear here." />
    </PortalLayout>
</template>
