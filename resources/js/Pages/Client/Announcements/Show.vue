<script setup>
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';

defineProps({ announcement: Object });
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <Link :href="route('client.announcements.index')" class="text-[12px] text-indigo-600 hover:text-indigo-700 font-medium">← Announcements</Link>
                <h1 class="text-lg font-bold text-gray-900 mt-1">{{ announcement.title }}</h1>
                <p class="text-[12px] text-gray-500 mt-0.5">{{ announcement.date }}</p>
            </div>
        </template>

        <div class="max-w-3xl">
            <Card>
                <div class="prose prose-sm max-w-none text-gray-700" v-html="announcement.announcement" />
            </Card>
        </div>
    </ClientLayout>
</template>
