<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    page: { type: Number, required: true },
    total: { type: Number, required: true },
    perPage: { type: Number, default: 25 },
    routeName: { type: String, required: true },
    routeParams: { type: Object, default: () => ({}) },
});

const totalPages = computed(() => Math.ceil(props.total / props.perPage));
const from = computed(() => props.total === 0 ? 0 : (props.page - 1) * props.perPage + 1);
const to = computed(() => Math.min(props.page * props.perPage, props.total));

const pages = computed(() => {
    const items = [];
    const total = totalPages.value;
    const current = props.page;

    if (total <= 7) {
        for (let i = 1; i <= total; i++) items.push(i);
    } else {
        items.push(1);
        if (current > 3) items.push('...');
        for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
            items.push(i);
        }
        if (current < total - 2) items.push('...');
        items.push(total);
    }
    return items;
});

function pageUrl(p) {
    return route(props.routeName, { ...props.routeParams, page: p });
}
</script>

<template>
    <div v-if="total > perPage" class="flex items-center justify-between pt-4 border-t border-gray-100">
        <p class="text-[13px] text-gray-500">
            Showing <span class="font-medium text-gray-700">{{ from }}</span> to <span class="font-medium text-gray-700">{{ to }}</span> of <span class="font-medium text-gray-700">{{ total }}</span>
        </p>
        <nav class="flex items-center gap-1">
            <Link
                v-if="page > 1"
                :href="pageUrl(page - 1)"
                class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                preserve-scroll
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </Link>
            <template v-for="p in pages" :key="p">
                <span v-if="p === '...'" class="px-1 text-gray-400 text-sm">…</span>
                <Link
                    v-else
                    :href="pageUrl(p)"
                    :class="[
                        'min-w-[32px] h-8 flex items-center justify-center rounded-lg text-[13px] font-medium transition-colors',
                        p === page
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-gray-600 hover:bg-gray-100'
                    ]"
                    preserve-scroll
                >
                    {{ p }}
                </Link>
            </template>
            <Link
                v-if="page < totalPages"
                :href="pageUrl(page + 1)"
                class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                preserve-scroll
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </Link>
        </nav>
    </div>
</template>
