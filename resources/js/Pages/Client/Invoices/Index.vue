<script setup>
import { Link, router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useCurrency } from '@/Composables/useCurrency.js';

const { formatCurrency } = useCurrency();

const props = defineProps({
    invoices: Array,
    total: Number,
    page: Number,
    perPage: Number,
    status: String,
    stats: Object,
});

const statusOptions = ['', 'Paid', 'Unpaid', 'Overdue', 'Cancelled', 'Refunded'];

function filterByStatus(val) {
    router.get(route('client.invoices.index'), { status: val || undefined }, { preserveState: true });
}

function isOverdue(inv) {
    if ((inv.status || '').toLowerCase() === 'overdue') return true;
    if ((inv.status || '').toLowerCase() !== 'unpaid') return false;
    const due = new Date(inv.duedate);
    return due < new Date();
}

function dueDateLabel(inv) {
    const s = (inv.status || '').toLowerCase();
    if (s === 'paid' || s === 'cancelled' || s === 'refunded') return '';
    const d = new Date(inv.duedate);
    const now = new Date();
    const days = Math.ceil((d - now) / (1000 * 60 * 60 * 24));
    if (days < 0) return `${Math.abs(days)}d overdue`;
    if (days === 0) return 'Due today';
    if (days <= 3) return `${days}d left`;
    return '';
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <h1 class="text-lg font-bold text-gray-900">Invoices</h1>
                <p class="text-[13px] text-gray-500">Manage your billing and payments</p>
            </div>
        </template>

        <!-- Summary Stats Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-2xl font-bold text-gray-900">{{ stats?.total || total }}</p>
                    <p class="text-[11px] text-gray-500 font-medium uppercase tracking-wider">Total</p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3" :class="stats?.unpaid_count > 0 ? 'border-amber-200 bg-amber-50/30' : ''">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" :class="stats?.unpaid_count > 0 ? 'bg-amber-100' : 'bg-gray-100'">
                    <svg class="w-5 h-5" :class="stats?.unpaid_count > 0 ? 'text-amber-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-2xl font-bold" :class="stats?.unpaid_count > 0 ? 'text-amber-700' : 'text-gray-400'">{{ stats?.unpaid_count || 0 }}</p>
                    <p class="text-[11px] font-medium uppercase tracking-wider" :class="stats?.unpaid_count > 0 ? 'text-amber-600' : 'text-gray-500'">Unpaid</p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3" :class="stats?.overdue_count > 0 ? 'border-red-200 bg-red-50/30' : ''">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" :class="stats?.overdue_count > 0 ? 'bg-red-100' : 'bg-gray-100'">
                    <svg class="w-5 h-5" :class="stats?.overdue_count > 0 ? 'text-red-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-2xl font-bold" :class="stats?.overdue_count > 0 ? 'text-red-700' : 'text-gray-400'">{{ stats?.overdue_count || 0 }}</p>
                    <p class="text-[11px] font-medium uppercase tracking-wider" :class="stats?.overdue_count > 0 ? 'text-red-600' : 'text-gray-500'">Overdue</p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-2xl font-bold text-emerald-700">{{ stats?.paid_count || 0 }}</p>
                    <p class="text-[11px] text-emerald-600 font-medium uppercase tracking-wider">Paid</p>
                </div>
            </div>
        </div>

        <!-- Outstanding Balance Banner -->
        <div v-if="stats?.unpaid_total > 0" class="mb-6 bg-gradient-to-r from-amber-500 to-orange-500 rounded-xl p-4 flex items-center justify-between text-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" /></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold">Outstanding Balance</p>
                    <p class="text-[12px] text-white/80">{{ (stats?.unpaid_count || 0) + (stats?.overdue_count || 0) }} invoice(s) pending payment</p>
                </div>
            </div>
            <p class="text-2xl font-bold">{{ formatCurrency(stats.unpaid_total) }}</p>
        </div>

        <!-- Filter Tabs -->
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-0.5 overflow-x-auto">
                <button
                    v-for="s in statusOptions"
                    :key="s"
                    @click="filterByStatus(s)"
                    class="px-3 py-1.5 text-[12px] font-medium rounded-md transition-all whitespace-nowrap"
                    :class="status === s ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                >
                    {{ s || 'All' }}
                </button>
            </div>
            <span class="text-[12px] text-gray-400 hidden sm:inline">{{ total }} invoice{{ total !== 1 ? 's' : '' }}</span>
        </div>

        <!-- Empty State -->
        <div v-if="invoices.length === 0">
            <EmptyState title="No invoices found" message="No invoices match the current filter." />
        </div>

        <!-- Invoice Cards -->
        <div v-else class="space-y-2">
            <Link
                v-for="inv in invoices" :key="inv.id"
                :href="route('client.invoices.show', inv.id)"
                class="group block bg-white rounded-xl border border-gray-200 hover:border-gray-300 hover:shadow-md transition-all duration-200 overflow-hidden"
            >
                <div class="flex items-center">
                    <!-- Status color strip -->
                    <div class="w-1 self-stretch flex-shrink-0"
                        :class="{
                            'bg-emerald-500': (inv.status || '').toLowerCase() === 'paid',
                            'bg-amber-500': (inv.status || '').toLowerCase() === 'unpaid' && !isOverdue(inv),
                            'bg-red-500': (inv.status || '').toLowerCase() === 'overdue' || ((inv.status || '').toLowerCase() === 'unpaid' && isOverdue(inv)),
                            'bg-gray-300': (inv.status || '').toLowerCase() === 'cancelled',
                            'bg-purple-400': (inv.status || '').toLowerCase() === 'refunded',
                        }"
                    ></div>

                    <div class="flex-1 flex items-center px-5 py-4 gap-4 min-w-0">
                        <!-- Invoice icon -->
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 hidden sm:flex"
                            :class="{
                                'bg-emerald-50': (inv.status || '').toLowerCase() === 'paid',
                                'bg-amber-50': (inv.status || '').toLowerCase() === 'unpaid',
                                'bg-red-50': (inv.status || '').toLowerCase() === 'overdue',
                                'bg-gray-50': ['cancelled', 'refunded'].includes((inv.status || '').toLowerCase()),
                            }"
                        >
                            <svg v-if="(inv.status || '').toLowerCase() === 'paid'" class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <svg v-else-if="(inv.status || '').toLowerCase() === 'overdue'" class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                            <svg v-else class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>

                        <!-- Invoice info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-[13px] font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">
                                    Invoice #{{ inv.invoicenum || inv.id }}
                                </p>
                                <StatusBadge :status="inv.status" size="xs" />
                            </div>
                            <div class="flex items-center gap-2 mt-1 flex-wrap">
                                <span class="text-[12px] text-gray-500">{{ inv.date }}</span>
                                <span class="text-gray-300 hidden sm:inline">·</span>
                                <span class="text-[12px] text-gray-500 hidden sm:inline">Due {{ inv.duedate }}</span>
                                <span v-if="dueDateLabel(inv)" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                                    :class="dueDateLabel(inv).includes('overdue') ? 'bg-red-100 text-red-600' : dueDateLabel(inv).includes('today') ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-600'"
                                >{{ dueDateLabel(inv) }}</span>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="text-right flex-shrink-0">
                            <p class="text-[15px] font-bold text-gray-900">{{ formatCurrency(inv.total) }}</p>
                            <p v-if="['unpaid', 'overdue'].includes((inv.status || '').toLowerCase()) && inv.balance && parseFloat(inv.balance) !== parseFloat(inv.total)" class="text-[11px] text-gray-400 mt-0.5">
                                Due: {{ formatCurrency(inv.balance) }}
                            </p>
                        </div>

                        <!-- Arrow -->
                        <svg class="w-4 h-4 text-gray-300 group-hover:text-indigo-500 transition-colors flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </div>
                </div>
            </Link>
        </div>

        <div class="mt-6">
            <Pagination :page="page" :total="total" :per-page="perPage" route-name="client.invoices.index" :route-params="{ status }" />
        </div>
    </ClientLayout>
</template>