<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    transactions: Array,
    total: Number,
    page: Number,
    perPage: Number,
});
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Transactions</h1>
        </template>

        <div v-if="transactions.length === 0">
            <EmptyState title="No transactions" message="No transaction records found." />
        </div>
        <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Gateway</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Transaction ID</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Invoice</th>
                        <th class="text-right px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in transactions" :key="t.id" class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50">
                        <td class="px-5 py-3.5 text-[13px] text-gray-900">{{ t.date }}</td>
                        <td class="px-5 py-3.5 text-[13px] text-gray-600">{{ t.gateway }}</td>
                        <td class="px-5 py-3.5 hidden md:table-cell text-[13px] text-gray-500 font-mono">{{ t.transid || '—' }}</td>
                        <td class="px-5 py-3.5 hidden md:table-cell text-[13px] text-gray-600">{{ t.invoiceid ? '#' + t.invoiceid : '—' }}</td>
                        <td class="px-5 py-3.5 text-right">
                            <span v-if="parseFloat(t.amountin || 0) > 0" class="text-[13px] font-semibold text-emerald-600">+${{ t.amountin }}</span>
                            <span v-else-if="parseFloat(t.amountout || 0) > 0" class="text-[13px] font-semibold text-red-600">-${{ t.amountout }}</span>
                            <span v-else class="text-[13px] text-gray-500">$0.00</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :page="page" :total="total" :per-page="perPage" route-name="client.billing.transactions" />
    </ClientLayout>
</template>
