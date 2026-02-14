<script setup>
import { Link, router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    tickets: Array,
    total: Number,
    page: Number,
    perPage: Number,
    status: String,
});

const statusOptions = ['', 'Open', 'Answered', 'Customer-Reply', 'Closed'];

function filterByStatus(val) {
    router.get(route('client.tickets.index'), { status: val || undefined }, { preserveState: true });
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Support Tickets</h1>
        </template>
        <template #actions>
            <Link :href="route('client.tickets.create')" class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                New Ticket
            </Link>
        </template>

        <div class="mb-6 flex items-center gap-3">
            <select :value="status" @change="filterByStatus($event.target.value)" class="text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 pr-8">
                <option value="">All Statuses</option>
                <option v-for="s in statusOptions.filter(s => s)" :key="s" :value="s">{{ s }}</option>
            </select>
            <span class="text-[13px] text-gray-500">{{ total }} ticket{{ total !== 1 ? 's' : '' }}</span>
        </div>

        <div v-if="tickets.length === 0">
            <EmptyState title="No tickets found" message="No support tickets match the current filter.">
                <Link :href="route('client.tickets.create')" class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                    Open a Ticket
                </Link>
            </EmptyState>
        </div>
        <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Ticket</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Department</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Last Updated</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Priority</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in tickets" :key="t.id" class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors">
                        <td class="px-5 py-3.5">
                            <Link :href="route('client.tickets.show', t.tid || t.id)" class="block">
                                <p class="text-[13px] font-medium text-gray-900 hover:text-indigo-600">#{{ t.tid }} — {{ t.subject }}</p>
                            </Link>
                        </td>
                        <td class="px-5 py-3.5 hidden lg:table-cell text-[13px] text-gray-600">{{ t.department || t.deptname }}</td>
                        <td class="px-5 py-3.5 hidden md:table-cell text-[13px] text-gray-500">{{ t.lastreply }}</td>
                        <td class="px-5 py-3.5">
                            <span class="text-[12px] font-medium px-2 py-0.5 rounded-full"
                                :class="{
                                    'bg-red-100 text-red-700': t.priority === 'High',
                                    'bg-orange-100 text-orange-700': t.priority === 'Medium',
                                    'bg-blue-100 text-blue-700': t.priority === 'Low',
                                }">{{ t.priority }}</span>
                        </td>
                        <td class="px-5 py-3.5"><StatusBadge :status="t.status" /></td>
                        <td class="px-5 py-3.5 text-right">
                            <Link :href="route('client.tickets.show', t.tid || t.id)" class="text-[12px] font-medium text-indigo-600 hover:text-indigo-700">View →</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :page="page" :total="total" :per-page="perPage" route-name="client.tickets.index" :route-params="{ status }" />
    </ClientLayout>
</template>
