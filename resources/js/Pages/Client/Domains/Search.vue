<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';

defineProps({
    query: { type: String, default: '' },
    result: { type: Object, default: null },
});

const searchDomain = ref('');

function doSearch() {
    if (!searchDomain.value.trim()) return;
    router.get(route('client.domains.search'), { domain: searchDomain.value });
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Domain Search</h1>
        </template>

        <Card title="Check Domain Availability">
            <form @submit.prevent="doSearch" class="flex gap-3">
                <input
                    v-model="searchDomain"
                    type="text"
                    :placeholder="query || 'example.com'"
                    class="flex-1 text-[14px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                />
                <button type="submit" class="px-5 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                    Search
                </button>
            </form>
        </Card>

        <Card v-if="result && query" title="Results" class="mt-6">
            <div class="flex items-center gap-4 p-4 rounded-lg" :class="result.status === 'available' ? 'bg-emerald-50' : 'bg-red-50'">
                <div :class="[
                    'w-10 h-10 rounded-full flex items-center justify-center',
                    result.status === 'available' ? 'bg-emerald-100' : 'bg-red-100'
                ]">
                    <svg v-if="result.status === 'available'" class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <svg v-else class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </div>
                <div>
                    <p class="text-[14px] font-semibold" :class="result.status === 'available' ? 'text-emerald-800' : 'text-red-800'">
                        {{ query }}
                    </p>
                    <p class="text-[13px]" :class="result.status === 'available' ? 'text-emerald-600' : 'text-red-600'">
                        {{ result.status === 'available' ? 'This domain is available!' : 'This domain is already registered.' }}
                    </p>
                </div>
            </div>
        </Card>
    </ClientLayout>
</template>
