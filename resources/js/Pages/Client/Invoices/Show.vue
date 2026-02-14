<script setup>
import { computed } from 'vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Card from '@/Components/Card.vue';

const props = defineProps({
    invoice: Object,
    payUrl: String,
});

const inv = props.invoice;
const items = computed(() => inv.items?.item || []);
const transactions = computed(() => inv.transactions?.transaction || []);
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <h1 class="text-lg font-bold text-gray-900">Invoice #{{ inv.invoicenum || inv.invoiceid }}</h1>
                <p class="text-[13px] text-gray-500">{{ inv.date }}</p>
            </div>
        </template>
        <template #actions>
            <a v-if="inv.status === 'Unpaid'" :href="payUrl" target="_blank"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>
                Pay Now
            </a>
            <a :href="route('client.invoices.pdf', inv.invoiceid)" target="_blank"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-[13px] font-medium text-gray-700 bg-white rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                PDF
            </a>
        </template>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Invoice Info -->
                <Card noPadding>
                    <div class="px-6 py-4 flex items-center justify-between border-b border-gray-100">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Invoice #{{ inv.invoicenum || inv.invoiceid }}</p>
                            <p class="text-[12px] text-gray-500 mt-0.5">Issued: {{ inv.date }} · Due: {{ inv.duedate }}</p>
                        </div>
                        <StatusBadge :status="inv.status" size="md" />
                    </div>

                    <!-- Line Items -->
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/50">
                                <th class="text-left px-6 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="text-right px-6 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in items" :key="item.id" class="border-b border-gray-50">
                                <td class="px-6 py-3 text-[13px] text-gray-900">{{ item.description }}</td>
                                <td class="px-6 py-3 text-[13px] text-gray-900 text-right font-medium">${{ item.amount }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50/50">
                            <tr class="border-t border-gray-200">
                                <td class="px-6 py-2 text-[13px] text-gray-600 text-right">Subtotal</td>
                                <td class="px-6 py-2 text-[13px] text-gray-900 text-right font-medium">${{ inv.subtotal }}</td>
                            </tr>
                            <tr v-if="parseFloat(inv.tax) > 0">
                                <td class="px-6 py-2 text-[13px] text-gray-600 text-right">Tax</td>
                                <td class="px-6 py-2 text-[13px] text-gray-900 text-right font-medium">${{ inv.tax }}</td>
                            </tr>
                            <tr v-if="parseFloat(inv.credit) > 0">
                                <td class="px-6 py-2 text-[13px] text-gray-600 text-right">Credit</td>
                                <td class="px-6 py-2 text-[13px] text-emerald-600 text-right font-medium">-${{ inv.credit }}</td>
                            </tr>
                            <tr class="border-t border-gray-200">
                                <td class="px-6 py-3 text-[14px] font-semibold text-gray-900 text-right">Total</td>
                                <td class="px-6 py-3 text-[14px] font-bold text-gray-900 text-right">${{ inv.total }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </Card>

                <!-- Transactions -->
                <Card v-if="transactions.length" title="Transactions">
                    <div class="space-y-2">
                        <div v-for="t in transactions" :key="t.id" class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                            <div>
                                <p class="text-[13px] font-medium text-gray-900">{{ t.gateway }}</p>
                                <p class="text-[12px] text-gray-500">{{ t.date }} · {{ t.transid }}</p>
                            </div>
                            <p class="text-[13px] font-semibold text-emerald-600">${{ t.amountin || t.amount }}</p>
                        </div>
                    </div>
                </Card>
            </div>

            <!-- Sidebar -->
            <div class="space-y-4">
                <Card title="Summary">
                    <dl class="space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">Status</dt>
                            <dd><StatusBadge :status="inv.status" /></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">Total</dt>
                            <dd class="text-[13px] font-semibold text-gray-900">${{ inv.total }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">Balance</dt>
                            <dd class="text-[13px] font-semibold" :class="parseFloat(inv.balance) > 0 ? 'text-red-600' : 'text-emerald-600'">${{ inv.balance }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">Payment</dt>
                            <dd class="text-[13px] text-gray-900 capitalize">{{ inv.paymentmethod }}</dd>
                        </div>
                    </dl>
                </Card>
            </div>
        </div>
    </ClientLayout>
</template>
