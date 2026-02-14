<script setup>
import { Link, router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    groups: Array,
    products: Array,
    activeGroup: [String, Number, null],
});

function filterGroup(gid) {
    router.get(route('client.orders.products'), gid ? { gid } : {}, { preserveState: true });
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Order New Services</h1>
        </template>

        <!-- Group Tabs -->
        <div v-if="groups.length > 1" class="flex flex-wrap gap-2 mb-6">
            <button @click="filterGroup(null)"
                class="px-3.5 py-1.5 text-[13px] font-medium rounded-lg transition-colors"
                :class="!activeGroup ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'">
                All
            </button>
            <button v-for="g in groups" :key="g.id" @click="filterGroup(g.id)"
                class="px-3.5 py-1.5 text-[13px] font-medium rounded-lg transition-colors"
                :class="String(activeGroup) === String(g.id) ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'">
                {{ g.name }}
            </button>
        </div>

        <div v-if="products.length === 0">
            <EmptyState title="No products available" message="No products are available for order at this time." />
        </div>
        <div v-else class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
            <Link v-for="p in products" :key="p.pid" :href="route('client.orders.product', p.pid)"
                class="group block">
                <Card class="h-full transition-all group-hover:border-indigo-200 group-hover:shadow-md">
                    <div class="flex flex-col h-full">
                        <h3 class="text-[14px] font-semibold text-gray-900 group-hover:text-indigo-600 mb-2">{{ p.name }}</h3>
                        <p class="text-[12px] text-gray-500 flex-1 line-clamp-3 mb-4">{{ p.description?.replace(/<[^>]*>/g, '').substring(0, 150) }}</p>
                        <div class="flex items-end justify-between">
                            <div>
                                <p v-if="p.pricing" class="text-xl font-bold text-gray-900">
                                    ${{ p.pricing.monthly || p.pricing.annually || p.pricing.onetime || '—' }}
                                </p>
                                <p v-if="p.pricing?.monthly" class="text-[11px] text-gray-400">/month</p>
                            </div>
                            <span class="text-[12px] font-medium text-indigo-600 group-hover:text-indigo-700">
                                View Details →
                            </span>
                        </div>
                    </div>
                </Card>
            </Link>
        </div>
    </ClientLayout>
</template>
