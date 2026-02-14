<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({ invoice: Object });

function statusColor(status) {
    const s = (status || '').toLowerCase();
    if (s === 'paid') return 'bg-emerald-100 text-emerald-700';
    if (s === 'unpaid') return 'bg-amber-100 text-amber-700';
    if (['overdue', 'cancelled'].includes(s)) return 'bg-red-100 text-red-700';
    return 'bg-slate-100 text-slate-700';
}

const items = props.invoice?.items?.item || [];
const transactions = props.invoice?.transactions?.transaction || [];
</script>

<template>
    <Head :title="'Invoice #' + (invoice.invoicenum || invoice.invoiceid)" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <Link :href="route('invoices.index')" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-slate-900">Invoice #{{ invoice.invoicenum || invoice.invoiceid }}</h1>
                <span :class="['px-3 py-1 rounded-full text-xs font-semibold', statusColor(invoice.status)]">{{ invoice.status }}</span>
            </div>

            <!-- Summary -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <dl class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div>
                        <dt class="text-sm text-slate-500">Date</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ invoice.date }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">Due Date</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ invoice.duedate }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">Total</dt>
                        <dd class="mt-1 text-xl font-bold text-slate-900">{{ invoice.total }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">Balance</dt>
                        <dd class="mt-1 text-xl font-bold" :class="parseFloat(invoice.balance) > 0 ? 'text-amber-600' : 'text-emerald-600'">{{ invoice.balance }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Items -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-lg font-semibold text-slate-900">Items</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">Description</th>
                                <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in items" :key="item.id" class="hover:bg-slate-50">
                                <td class="px-6 py-4 text-slate-900">{{ item.description }}</td>
                                <td class="px-6 py-4 text-right font-medium text-slate-900">{{ item.amount }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-slate-50 border-t border-slate-200">
                            <tr>
                                <td class="px-6 py-3 text-sm font-semibold text-slate-700">Subtotal</td>
                                <td class="px-6 py-3 text-right font-semibold text-slate-900">{{ invoice.subtotal }}</td>
                            </tr>
                            <tr v-if="parseFloat(invoice.tax) > 0">
                                <td class="px-6 py-3 text-sm text-slate-500">Tax</td>
                                <td class="px-6 py-3 text-right text-slate-700">{{ invoice.tax }}</td>
                            </tr>
                            <tr v-if="parseFloat(invoice.credit) > 0">
                                <td class="px-6 py-3 text-sm text-slate-500">Credit</td>
                                <td class="px-6 py-3 text-right text-emerald-600">-{{ invoice.credit }}</td>
                            </tr>
                            <tr class="border-t border-slate-300">
                                <td class="px-6 py-3 text-sm font-bold text-slate-900">Total</td>
                                <td class="px-6 py-3 text-right text-lg font-bold text-slate-900">{{ invoice.total }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Transactions -->
            <div v-if="transactions.length" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Transactions</h2>
                <div class="space-y-3">
                    <div v-for="tx in transactions" :key="tx.id" class="flex items-center justify-between p-3 rounded-xl bg-slate-50">
                        <div>
                            <p class="font-medium text-slate-900">{{ tx.gateway }}</p>
                            <p class="text-sm text-slate-500">{{ tx.date }} · {{ tx.transid }}</p>
                        </div>
                        <span class="font-semibold text-emerald-600">{{ tx.amountin }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
