<script setup>
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';

defineProps({ article: Object });
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <Link :href="route('client.kb.index')" class="text-[12px] text-indigo-600 hover:text-indigo-700 font-medium">← Knowledgebase</Link>
                <h1 class="text-lg font-bold text-gray-900 mt-1">{{ article.title }}</h1>
            </div>
        </template>

        <div class="max-w-3xl">
            <Card>
                <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
                    <span class="text-[12px] text-gray-400">{{ article.views || 0 }} views</span>
                    <span v-if="article.rating" class="text-[12px] text-gray-400">Rating: {{ article.rating }}%</span>
                </div>
                <div class="prose prose-sm max-w-none text-gray-700" v-html="article.article" />
            </Card>
        </div>
    </ClientLayout>
</template>
