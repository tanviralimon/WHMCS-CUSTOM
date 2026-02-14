<script setup>
import { Link, router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    services: Array,
    total: Number,
    page: Number,
    perPage: Number,
    status: String,
});

const statusOptions = ['', 'Active', 'Pending', 'Suspended', 'Terminated', 'Cancelled'];

function filterByStatus(val) {
    router.get(route('client.services.index'), { status: val || undefined }, { preserveState: true });
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Services</h1>
        </template>

        <div class="mb-6 flex items-center gap-3">
            <select
                :value="status"
                @change="filterByStatus($event.target.value)"
                class="text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 pr-8"
            >
                <option value="">All Statuses</option>
                <option v-for="s in statusOptions.filter(s => s)" :key="s" :value="s">{{ s }}</option>
            </select>
            <span class="text-[13px] text-gray-500">{{ total }} service{{ total !== 1 ? 's' : '' }}</span>
        </div>

        <div v-if="services.length === 0">
            <EmptyState title="No services found" message="You don't have any services matching the current filter." />
        </div>
        <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Product/Service</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Billing</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Next Due</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in services" :key="s.id" class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors">
                        <td class="px-5 py-3.5">
                            <Link :href="route('client.services.show', s.id)" class="block">
                                <p class="text-[13px] font-medium text-gray-900">{{ s.name || s.groupname }}</p>
                                <p class="text-[12px] text-gray-500 mt-0.5">{{ s.domain || '—' }}</p>
                            </Link>
                        </td>
                        <td class="px-5 py-3.5 hidden md:table-cell">
                            <p class="text-[13px] text-gray-900">${{ s.recurringamount }}/{{ s.billingcycle }}</p>
                        </td>
                        <td class="px-5 py-3.5 hidden lg:table-cell">
                            <p class="text-[13px] text-gray-600">{{ s.nextduedate }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            <StatusBadge :status="s.status" />
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <Link :href="route('client.services.show', s.id)" class="text-[12px] font-medium text-indigo-600 hover:text-indigo-700">
                                Manage →
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :page="page" :total="total" :per-page="perPage" route-name="client.services.index" :route-params="{ status }" />
    </ClientLayout>
</template>
