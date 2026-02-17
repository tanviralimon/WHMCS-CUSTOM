<script setup>
import { Link, router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useCurrency } from '@/Composables/useCurrency.js';

const { formatCurrency } = useCurrency();

defineProps({
    groups: Array,
    products: Array,
    activeGroup: [String, Number, null],
});

function filterGroup(gid) {
    router.get(route('client.orders.products'), gid ? { group: gid } : {}, { preserveState: true });
}

function cycleLabel(cycle) {
    const map = {
        monthly: '/mo',
        quarterly: '/quarter',
        semiannually: '/6 mo',
        annually: '/yr',
        biennially: '/2 yr',
        triennially: '/3 yr',
    };
    return map[cycle] || '';
}

function groupIcon(name) {
    const n = (name || '').toLowerCase();
    if (n.includes('hosting') || n.includes('web') || n.includes('shared'))  return 'server';
    if (n.includes('vps') || n.includes('virtual') || n.includes('cloud'))   return 'cloud';
    if (n.includes('domain'))                                                  return 'globe';
    if (n.includes('ssl') || n.includes('security'))                          return 'shield';
    if (n.includes('email') || n.includes('mail'))                            return 'mail';
    if (n.includes('reseller'))                                                return 'users';
    if (n.includes('dedicated'))                                               return 'cpu';
    return 'box';
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-lg font-bold text-gray-900">Order New Services</h1>
                    <p class="text-[13px] text-gray-500 mt-0.5">Choose from our range of products and services</p>
                </div>
                <Link :href="route('client.orders.cart')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                    </svg>
                    Cart
                </Link>
            </div>
        </template>

        <!-- Group filter pills -->
        <div v-if="groups.length > 1" class="flex flex-wrap gap-2 mb-8">
            <button @click="filterGroup(null)"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium rounded-full transition-all"
                :class="!activeGroup
                    ? 'bg-indigo-600 text-white shadow-sm'
                    : 'bg-white text-gray-600 border border-gray-200 hover:border-gray-300 hover:bg-gray-50'">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                All Products
            </button>
            <button v-for="g in groups" :key="g.id" @click="filterGroup(g.id)"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium rounded-full transition-all"
                :class="String(activeGroup) === String(g.id)
                    ? 'bg-indigo-600 text-white shadow-sm'
                    : 'bg-white text-gray-600 border border-gray-200 hover:border-gray-300 hover:bg-gray-50'">
                {{ g.name }}
            </button>
        </div>

        <div v-if="products.length === 0">
            <EmptyState title="No products available" message="No products are available for order at this time." />
        </div>

        <div v-else class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
            <Link v-for="p in products" :key="p.pid" :href="route('client.orders.products.show', p.pid)"
                class="group block">
                <div class="relative h-full bg-white rounded-2xl border border-gray-200 overflow-hidden transition-all duration-200 hover:border-indigo-200 hover:shadow-lg hover:-translate-y-0.5">
                    <!-- Colored top accent bar -->
                    <div class="h-1 bg-gradient-to-r from-indigo-500 to-violet-500"></div>

                    <div class="p-5 flex flex-col h-full">
                        <!-- Header with icon -->
                        <div class="flex items-start gap-3 mb-4">
                            <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center group-hover:bg-indigo-100 transition-colors">
                                <!-- Server -->
                                <svg v-if="groupIcon(p.groupname) === 'server'" class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z" />
                                </svg>
                                <!-- Cloud -->
                                <svg v-else-if="groupIcon(p.groupname) === 'cloud'" class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z" />
                                </svg>
                                <!-- Globe -->
                                <svg v-else-if="groupIcon(p.groupname) === 'globe'" class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 003 12c0-1.605.42-3.113 1.157-4.418" />
                                </svg>
                                <!-- Shield -->
                                <svg v-else-if="groupIcon(p.groupname) === 'shield'" class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                </svg>
                                <!-- Mail -->
                                <svg v-else-if="groupIcon(p.groupname) === 'mail'" class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                                <!-- Users -->
                                <svg v-else-if="groupIcon(p.groupname) === 'users'" class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                </svg>
                                <!-- CPU -->
                                <svg v-else-if="groupIcon(p.groupname) === 'cpu'" class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z" />
                                </svg>
                                <!-- Box default -->
                                <svg v-else class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-[14px] font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors leading-snug">{{ p.name }}</h3>
                                <span class="inline-block mt-1 text-[10.5px] font-medium text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">{{ p.groupname }}</span>
                            </div>
                        </div>

                        <!-- Description -->
                        <p class="text-[12.5px] text-gray-500 leading-relaxed flex-1 mb-4 line-clamp-3">
                            {{ p.description?.replace(/<[^>]*>/g, '').substring(0, 180) || 'No description available.' }}
                        </p>

                        <!-- Divider -->
                        <div class="border-t border-gray-100 pt-4 mt-auto">
                            <div class="flex items-end justify-between">
                                <div v-if="p.displayPrice !== null && p.displayPrice !== undefined">
                                    <p class="text-[11px] text-gray-400 mb-0.5">Starting from</p>
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-xl font-bold text-gray-900">{{ formatCurrency(p.displayPrice) }}</span>
                                        <span class="text-[12px] text-gray-400">{{ cycleLabel(p.displayCycle) }}</span>
                                    </div>
                                </div>
                                <div v-else>
                                    <p class="text-[13px] font-medium text-gray-500">Contact for pricing</p>
                                </div>
                                <span class="inline-flex items-center gap-1 text-[12px] font-medium text-indigo-600 group-hover:text-indigo-700 transition-colors">
                                    Details
                                    <svg class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </Link>
        </div>
    </ClientLayout>
</template>
