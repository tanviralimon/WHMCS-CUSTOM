<script setup>
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({ categories: Array });
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Knowledgebase</h1>
        </template>

        <div v-if="categories.length === 0">
            <EmptyState title="No articles" message="The knowledgebase is empty." />
        </div>
        <div v-else class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
            <Link v-for="cat in categories" :key="cat.id" :href="route('client.kb.category', cat.id)"
                class="group block bg-white rounded-xl border border-gray-200 p-6 hover:border-indigo-200 hover:shadow-md transition-all">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
                </div>
                <h3 class="text-[14px] font-semibold text-gray-900 group-hover:text-indigo-600 mb-1">{{ cat.name }}</h3>
                <p class="text-[12px] text-gray-500 line-clamp-2">{{ cat.description?.replace(/<[^>]*>/g, '') || 'Browse articles' }}</p>
                <p class="text-[11px] text-gray-400 mt-2">{{ cat.numarticles || 0 }} article{{ cat.numarticles !== 1 ? 's' : '' }}</p>
            </Link>
        </div>
    </ClientLayout>
</template>
