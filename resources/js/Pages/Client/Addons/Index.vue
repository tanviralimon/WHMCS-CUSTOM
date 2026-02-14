<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    addons: Array,
    total: Number,
    page: Number,
    perPage: Number,
});
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Addons</h1>
        </template>

        <div v-if="addons.length === 0">
            <EmptyState title="No addons" message="You don't have any addons at this time." />
        </div>
        <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Addon</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Linked Service</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Next Due</th>
                        <th class="text-right px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Price</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in addons" :key="a.id" class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors">
                        <td class="px-5 py-3.5 text-[13px] font-medium text-gray-900">{{ a.name }}</td>
                        <td class="px-5 py-3.5 hidden md:table-cell text-[13px] text-gray-600">{{ a.servername || '#' + a.serviceid }}</td>
                        <td class="px-5 py-3.5 hidden md:table-cell text-[13px] text-gray-500">{{ a.nextduedate || '—' }}</td>
                        <td class="px-5 py-3.5 text-right text-[13px] font-semibold text-gray-900">${{ a.recurring }}/{{ a.billingcycle }}</td>
                        <td class="px-5 py-3.5"><StatusBadge :status="a.status" /></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :page="page" :total="total" :per-page="perPage" route-name="client.addons.index" />
    </ClientLayout>
</template>
