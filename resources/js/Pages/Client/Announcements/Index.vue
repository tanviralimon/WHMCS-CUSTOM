<script setup>
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    announcements: Array,
    total: Number,
    page: Number,
    perPage: Number,
});
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Announcements</h1>
        </template>

        <div v-if="announcements.length === 0">
            <EmptyState title="No announcements" message="There are no announcements at this time." />
        </div>
        <div v-else class="space-y-4">
            <Link v-for="a in announcements" :key="a.id" :href="route('client.announcements.show', a.id)"
                class="block bg-white rounded-xl border border-gray-200 p-6 hover:border-indigo-200 hover:shadow-sm transition-all group">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3 class="text-[14px] font-semibold text-gray-900 group-hover:text-indigo-600 mb-1">{{ a.title }}</h3>
                        <p class="text-[12px] text-gray-500 line-clamp-2">{{ a.announcement?.replace(/<[^>]*>/g, '').substring(0, 200) }}</p>
                    </div>
                    <span class="text-[11px] text-gray-400 flex-shrink-0 ml-4">{{ a.date }}</span>
                </div>
            </Link>
        </div>

        <Pagination :page="page" :total="total" :per-page="perPage" route-name="client.announcements.index" />
    </ClientLayout>
</template>
