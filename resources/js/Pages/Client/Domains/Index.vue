<script setup>
import { Link, router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    domains: Array,
    total: Number,
    page: Number,
    perPage: Number,
    status: String,
});

const statusOptions = ['', 'Active', 'Pending', 'Expired', 'Cancelled'];

function filterByStatus(val) {
    router.get(route('client.domains.index'), { status: val || undefined }, { preserveState: false, preserveScroll: true });
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Domains</h1>
        </template>
        <template #actions>
            <Link :href="route('client.domains.search')" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                Search Domain
            </Link>
        </template>

        <div class="mb-6 flex items-center gap-3">
            <select :value="status" @change="filterByStatus($event.target.value)" class="text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 pr-8">
                <option value="">All Statuses</option>
                <option v-for="s in statusOptions.filter(s => s)" :key="s" :value="s">{{ s }}</option>
            </select>
            <span class="text-[13px] text-gray-500">{{ total }} domain{{ total !== 1 ? 's' : '' }}</span>
        </div>

        <div v-if="domains.length === 0">
            <EmptyState title="No domains found" message="You don't have any domains registered." />
        </div>
        <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Domain</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Registration</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Expiry</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in domains" :key="d.id" class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors">
                        <td class="px-5 py-3.5">
                            <Link :href="route('client.domains.show', d.id)" class="text-[13px] font-medium text-gray-900 hover:text-indigo-600">{{ d.domainname }}</Link>
                            <p class="text-[12px] text-gray-500 mt-0.5">{{ d.autorecurring ? 'Auto-renew' : 'Manual renewal' }}</p>
                        </td>
                        <td class="px-5 py-3.5 hidden md:table-cell text-[13px] text-gray-600">{{ d.registrationdate }}</td>
                        <td class="px-5 py-3.5 hidden md:table-cell text-[13px] text-gray-600">{{ d.expirydate }}</td>
                        <td class="px-5 py-3.5"><StatusBadge :status="d.status" /></td>
                        <td class="px-5 py-3.5 text-right">
                            <Link :href="route('client.domains.show', d.id)" class="text-[12px] font-medium text-indigo-600 hover:text-indigo-700">Manage →</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :page="page" :total="total" :per-page="perPage" route-name="client.domains.index" :route-params="{ status }" />
    </ClientLayout>
</template>
