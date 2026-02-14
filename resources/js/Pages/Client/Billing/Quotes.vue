<script setup>
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    quotes: Array,
    total: Number,
    page: Number,
    perPage: Number,
});
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Quotes</h1>
        </template>

        <div v-if="quotes.length === 0">
            <EmptyState title="No quotes" message="You have no quotes at this time." />
        </div>
        <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">#</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Subject</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Date</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Valid Until</th>
                        <th class="text-right px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="q in quotes" :key="q.id" class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50">
                        <td class="px-5 py-3.5 text-[13px] font-medium text-gray-900">#{{ q.id }}</td>
                        <td class="px-5 py-3.5 text-[13px] text-gray-900">{{ q.subject || '—' }}</td>
                        <td class="px-5 py-3.5 hidden md:table-cell text-[13px] text-gray-600">{{ q.datecreated }}</td>
                        <td class="px-5 py-3.5 hidden md:table-cell text-[13px] text-gray-600">{{ q.validuntil }}</td>
                        <td class="px-5 py-3.5 text-right text-[13px] font-semibold text-gray-900">${{ q.total }}</td>
                        <td class="px-5 py-3.5"><StatusBadge :status="q.stage || q.status" /></td>
                        <td class="px-5 py-3.5 text-right">
                            <Link :href="route('client.billing.quotes.show', q.id)" class="text-[12px] font-medium text-indigo-600 hover:text-indigo-700">View →</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :page="page" :total="total" :per-page="perPage" route-name="client.billing.quotes" />
    </ClientLayout>
</template>
