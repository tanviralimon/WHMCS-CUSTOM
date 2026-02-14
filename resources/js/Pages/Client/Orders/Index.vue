<script setup>
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    orders: Array,
    total: Number,
    page: Number,
    perPage: Number,
});
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Order History</h1>
        </template>
        <template #actions>
            <Link :href="route('client.orders.products')" class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                New Order
            </Link>
        </template>

        <div v-if="orders.length === 0">
            <EmptyState title="No orders" message="You haven't placed any orders yet." />
        </div>
        <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Order #</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Date</th>
                        <th class="text-right px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Payment</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="o in orders" :key="o.id" class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors">
                        <td class="px-5 py-3.5 text-[13px] font-medium text-gray-900">#{{ o.ordernum || o.id }}</td>
                        <td class="px-5 py-3.5 hidden md:table-cell text-[13px] text-gray-600">{{ o.date }}</td>
                        <td class="px-5 py-3.5 text-right text-[13px] font-semibold text-gray-900">${{ o.amount }}</td>
                        <td class="px-5 py-3.5 hidden md:table-cell text-[13px] text-gray-600 capitalize">{{ o.paymentmethod }}</td>
                        <td class="px-5 py-3.5"><StatusBadge :status="o.status" /></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :page="page" :total="total" :per-page="perPage" route-name="client.orders.index" />
    </ClientLayout>
</template>
