<script setup>
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    categoryId: [String, Number],
    categoryName: String,
    articles: Array,
    total: Number,
    page: Number,
    perPage: Number,
});
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <Link :href="route('client.kb.index')" class="text-[12px] text-indigo-600 hover:text-indigo-700 font-medium">← Knowledgebase</Link>
                <h1 class="text-lg font-bold text-gray-900 mt-1">{{ categoryName }}</h1>
            </div>
        </template>

        <div v-if="articles.length === 0">
            <EmptyState title="No articles" message="No articles in this category yet." />
        </div>
        <div v-else class="space-y-3">
            <Link v-for="a in articles" :key="a.id" :href="route('client.kb.article', a.id)"
                class="block bg-white rounded-xl border border-gray-200 p-5 hover:border-indigo-200 hover:shadow-sm transition-all group">
                <h3 class="text-[14px] font-semibold text-gray-900 group-hover:text-indigo-600 mb-1">{{ a.title }}</h3>
                <p class="text-[12px] text-gray-500 line-clamp-2">{{ a.article?.replace(/<[^>]*>/g, '').substring(0, 200) }}</p>
                <div class="flex items-center gap-3 mt-2">
                    <span class="text-[11px] text-gray-400">{{ a.views || 0 }} views</span>
                    <span v-if="a.rating" class="text-[11px] text-gray-400">Rating: {{ a.rating }}%</span>
                </div>
            </Link>
        </div>

        <Pagination :page="page" :total="total" :per-page="perPage" route-name="client.kb.category" :route-params="{ category: categoryId }" />
    </ClientLayout>
</template>
