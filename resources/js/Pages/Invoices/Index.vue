<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ invoices: Array, total: Number, page: Number, status: String });

const statusFilter = ref(props.status || '');

function statusColor(status) {
    const s = (status || '').toLowerCase();
    if (s === 'paid') return 'bg-emerald-100 text-emerald-700';
    if (s === 'unpaid') return 'bg-amber-100 text-amber-700';
    if (['overdue', 'cancelled'].includes(s)) return 'bg-red-100 text-red-700';
    if (s === 'refunded') return 'bg-blue-100 text-blue-700';
    return 'bg-slate-100 text-slate-700';
}

function filter() {
    router.get(route('invoices.index'), { status: statusFilter.value, page: 1 }, { preserveState: true });
}
</script>

<template>
    <Head title="Invoices" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Invoices</h1>
                    <p class="text-slate-500 mt-1">{{ total }} total invoice(s)</p>
                </div>
                <div class="flex items-center gap-2">
                    <select v-model="statusFilter" @change="filter" class="rounded-lg border-slate-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Statuses</option>
                        <option value="Unpaid">Unpaid</option>
                        <option value="Paid">Paid</option>
                        <option value="Overdue">Overdue</option>
                        <option value="Cancelled">Cancelled</option>
                        <option value="Refunded">Refunded</option>
                    </select>
                </div>
            </div>

            <div v-if="invoices.length === 0" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-12 text-center">
                <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-slate-500">No invoices found</p>
            </div>

            <div v-else class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Invoice #</th>
                                <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Due Date</th>
                                <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 font-medium text-slate-900">#{{ inv.invoicenum || inv.id }}</td>
                                <td class="px-6 py-4 text-slate-600">{{ inv.date }}</td>
                                <td class="px-6 py-4 text-slate-600">{{ inv.duedate }}</td>
                                <td class="px-6 py-4 font-semibold text-slate-900">{{ inv.total }}</td>
                                <td class="px-6 py-4">
                                    <span :class="['px-2.5 py-1 rounded-full text-xs font-semibold', statusColor(inv.status)]">{{ inv.status }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link :href="route('invoices.show', inv.id)" class="text-sm font-medium text-blue-600 hover:text-blue-700">View →</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="total > 25" class="flex items-center gap-2 justify-center">
                <Link v-if="page > 1" :href="route('invoices.index', { page: page - 1, status: statusFilter })" class="px-4 py-2 rounded-lg bg-white border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50">← Previous</Link>
                <span class="text-sm text-slate-500">Page {{ page }}</span>
                <Link v-if="page * 25 < total" :href="route('invoices.index', { page: page + 1, status: statusFilter })" class="px-4 py-2 rounded-lg bg-white border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50">Next →</Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
