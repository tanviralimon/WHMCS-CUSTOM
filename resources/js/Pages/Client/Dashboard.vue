<script setup>
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useCurrency } from '@/Composables/useCurrency.js';

const { formatCurrency } = useCurrency();

defineProps({
    profile: Object,
    stats: Object,
    services: Array,
    invoices: Array,
    tickets: Array,
    domains: Array,
    features: Object,
});
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <h1 class="text-lg font-bold text-gray-900">Dashboard</h1>
                <p class="text-[13px] text-gray-500">Welcome back, {{ profile?.firstname || 'there' }}</p>
            </div>
        </template>

        <template #actions>
            <a v-if="features?.sso" :href="route('client.sso', { destination: 'clientarea:services' })"
                class="inline-flex items-center gap-2 px-4 py-2 text-[13px] font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl hover:bg-amber-100 hover:border-amber-300 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                </svg>
                Switch to Old Panel
            </a>
        </template>

        <!-- Stats row -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
            <StatCard
                label="Active Services"
                :value="stats.activeServices"
                color="indigo"
                :href="route('client.services.index')"
                :icon="'<path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0h.375a2.625 2.625 0 010 5.25H3.375a2.625 2.625 0 010-5.25H3.75\' />'"
            />
            <StatCard
                label="Unpaid Invoices"
                :value="stats.unpaidInvoices"
                color="red"
                :href="route('client.invoices.index', { status: 'Unpaid' })"
                :icon="'<path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z\' />'"
            />
            <StatCard
                label="Open Tickets"
                :value="stats.openTickets"
                color="amber"
                :href="route('client.tickets.index', { status: 'Open' })"
                :icon="'<path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155\' />'"
            />
            <StatCard
                v-if="features?.domains"
                label="Domains"
                :value="stats.totalDomains"
                color="purple"
                :href="route('client.domains.index')"
                :icon="'<path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5a17.92 17.92 0 01-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418\' />'"
            />
            <StatCard
                label="Credit Balance"
                :value="formatCurrency(stats.creditBalance)"
                color="emerald"
                :icon="'<path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z\' />'"
            />
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <!-- Recent Invoices -->
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Unpaid Invoices</h3>
                    <Link :href="route('client.invoices.index')" class="text-[12px] font-medium text-indigo-600 hover:text-indigo-700">View all &#8594;</Link>
                </div>
                <div v-if="invoices.length === 0" class="px-5 py-8 text-center text-[13px] text-gray-400">No unpaid invoices</div>
                <div v-else>
                    <Link v-for="inv in invoices" :key="inv.id" :href="route('client.invoices.show', inv.id)" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-0">
                        <div>
                            <p class="text-[13px] font-medium text-gray-900">#{{ inv.invoicenum || inv.id }}</p>
                            <p class="text-[12px] text-gray-500">{{ inv.date }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[13px] font-semibold text-gray-900">{{ formatCurrency(inv.total) }}</p>
                            <StatusBadge :status="inv.status" size="xs" />
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Recent Tickets -->
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Recent Tickets</h3>
                    <Link :href="route('client.tickets.index')" class="text-[12px] font-medium text-indigo-600 hover:text-indigo-700">View all &#8594;</Link>
                </div>
                <div v-if="tickets.length === 0" class="px-5 py-8 text-center text-[13px] text-gray-400">No recent tickets</div>
                <div v-else>
                    <Link v-for="t in tickets" :key="t.id" :href="route('client.tickets.show', t.id)" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-0">
                        <div class="flex-1 min-w-0">
                            <p class="text-[13px] font-medium text-gray-900 truncate">#{{ t.tid }} &#8212; {{ t.subject }}</p>
                            <p class="text-[12px] text-gray-500">{{ t.date }}</p>
                        </div>
                        <StatusBadge :status="t.status" size="xs" class="ml-3" />
                    </Link>
                </div>
            </div>

            <!-- Active Services -->
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Services</h3>
                    <Link :href="route('client.services.index')" class="text-[12px] font-medium text-indigo-600 hover:text-indigo-700">View all &#8594;</Link>
                </div>
                <div v-if="services.length === 0" class="px-5 py-8 text-center text-[13px] text-gray-400">No active services</div>
                <div v-else>
                    <Link v-for="s in services" :key="s.id" :href="route('client.services.show', s.id)" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-0">
                        <div class="flex-1 min-w-0">
                            <p class="text-[13px] font-medium text-gray-900 truncate">{{ s.name || s.groupname }}</p>
                            <p class="text-[12px] text-gray-500">{{ s.domain }}</p>
                        </div>
                        <StatusBadge :status="s.status" size="xs" class="ml-3" />
                    </Link>
                </div>
            </div>

            <!-- Domains (if enabled) -->
            <div v-if="features?.domains" class="bg-white rounded-xl border border-gray-200">
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Domains</h3>
                    <Link :href="route('client.domains.index')" class="text-[12px] font-medium text-indigo-600 hover:text-indigo-700">View all &#8594;</Link>
                </div>
                <div v-if="!domains || domains.length === 0" class="px-5 py-8 text-center text-[13px] text-gray-400">No domains</div>
                <div v-else>
                    <Link v-for="d in domains" :key="d.id" :href="route('client.domains.show', d.id)" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-0">
                        <div>
                            <p class="text-[13px] font-medium text-gray-900">{{ d.domainname }}</p>
                            <p class="text-[12px] text-gray-500">Expires: {{ d.expirydate }}</p>
                        </div>
                        <StatusBadge :status="d.status" size="xs" />
                    </Link>
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
