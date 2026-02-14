<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    downloads: Array,
    total: Number,
    page: Number,
    perPage: Number,
    category: [String, Number, null],
});
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Downloads</h1>
        </template>

        <div v-if="downloads.length === 0">
            <EmptyState title="No downloads" message="No downloadable files are available." />
        </div>
        <div v-else class="space-y-3">
            <div v-for="d in downloads" :key="d.id"
                class="bg-white rounded-xl border border-gray-200 p-5 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    </div>
                    <div>
                        <p class="text-[14px] font-semibold text-gray-900">{{ d.title }}</p>
                        <p v-if="d.description" class="text-[12px] text-gray-500 mt-0.5 line-clamp-1">{{ d.description?.replace(/<[^>]*>/g, '') }}</p>
                        <div class="flex items-center gap-3 mt-1">
                            <span v-if="d.filesize" class="text-[11px] text-gray-400">{{ d.filesize }}</span>
                            <span v-if="d.downloads" class="text-[11px] text-gray-400">{{ d.downloads }} downloads</span>
                        </div>
                    </div>
                </div>
                <a v-if="d.link" :href="d.link" target="_blank"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-[13px] font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    Download
                </a>
            </div>
        </div>

        <Pagination :page="page" :total="total" :per-page="perPage" route-name="client.downloads.index" :route-params="{ category }" />
    </ClientLayout>
</template>
