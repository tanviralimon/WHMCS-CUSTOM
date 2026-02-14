<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    profile: Object,
    services: Array,
    totalServices: Number,
    invoices: Array,
    totalInvoices: Number,
    tickets: Array,
    totalTickets: Number,
});

const firstname = props.profile?.firstname || props.profile?.client?.firstname || 'User';

function statusColor(status) {
    const s = (status || '').toLowerCase();
    if (['active', 'paid'].includes(s)) return 'bg-emerald-100 text-emerald-700';
    if (['pending', 'unpaid'].includes(s)) return 'bg-amber-100 text-amber-700';
    if (['suspended', 'cancelled', 'overdue'].includes(s)) return 'bg-red-100 text-red-700';
    if (['open', 'answered'].includes(s)) return 'bg-blue-100 text-blue-700';
    return 'bg-slate-100 text-slate-700';
}

const activeServices = (props.services || []).filter(s => s.status?.toLowerCase() === 'active').length;
const unpaidInvoices = (props.invoices || []).filter(i => i.status?.toLowerCase() === 'unpaid').length;
const openTickets = (props.tickets || []).filter(t => ['open', 'answered', 'customer reply'].includes(t.status?.toLowerCase())).length;

const stats = [
    { label: 'Active Services', value: activeServices, color: 'from-emerald-500 to-teal-600', icon: 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01', href: 'services.index' },
    { label: 'Unpaid Invoices', value: unpaidInvoices, color: unpaidInvoices > 0 ? 'from-amber-500 to-orange-600' : 'from-blue-500 to-indigo-600', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', href: 'invoices.index' },
    { label: 'Open Tickets', value: openTickets, color: 'from-purple-500 to-violet-600', icon: 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z', href: 'tickets.index' },
];
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <div class="space-y-8">
            <!-- Welcome -->
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Welcome back, {{ firstname }}!</h1>
                <p class="text-slate-500 mt-1">Here's an overview of your account</p>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <Link
                    v-for="stat in stats"
                    :key="stat.label"
                    :href="route(stat.href)"
                    class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm border border-slate-200 hover:shadow-md transition-shadow group"
                >
                    <div class="flex items-center gap-4">
                        <div :class="['p-3 rounded-xl bg-gradient-to-br text-white', stat.color]">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="stat.icon" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">{{ stat.label }}</p>
                            <p class="text-3xl font-bold text-slate-900">{{ stat.value }}</p>
                        </div>
                    </div>
                    <div class="absolute -bottom-4 -right-4 w-24 h-24 rounded-full bg-gradient-to-br opacity-5 group-hover:opacity-10 transition-opacity" :class="stat.color" />
                </Link>
            </div>

            <!-- Recent Items -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Services -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-lg font-semibold text-slate-900">Recent Services</h2>
                        <Link :href="route('services.index')" class="text-sm font-medium text-blue-600 hover:text-blue-700">View all →</Link>
                    </div>
                    <div v-if="services.length === 0" class="text-slate-400 text-sm py-8 text-center">No services found</div>
                    <div v-else class="space-y-3">
                        <Link
                            v-for="svc in services.slice(0, 4)"
                            :key="svc.id"
                            :href="route('services.show', svc.id)"
                            class="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-slate-100 transition-colors"
                        >
                            <div class="min-w-0">
                                <p class="font-medium text-slate-900 truncate">{{ svc.name || svc.translated_name }}</p>
                                <p class="text-sm text-slate-500 truncate">{{ svc.domain }}</p>
                            </div>
                            <span :class="['px-2.5 py-1 rounded-full text-xs font-semibold', statusColor(svc.status)]">
                                {{ svc.status }}
                            </span>
                        </Link>
                    </div>
                </div>

                <!-- Recent Invoices -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-lg font-semibold text-slate-900">Recent Invoices</h2>
                        <Link :href="route('invoices.index')" class="text-sm font-medium text-blue-600 hover:text-blue-700">View all →</Link>
                    </div>
                    <div v-if="invoices.length === 0" class="text-slate-400 text-sm py-8 text-center">No invoices found</div>
                    <div v-else class="space-y-3">
                        <Link
                            v-for="inv in invoices.slice(0, 4)"
                            :key="inv.id"
                            :href="route('invoices.show', inv.id)"
                            class="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-slate-100 transition-colors"
                        >
                            <div class="min-w-0">
                                <p class="font-medium text-slate-900">#{{ inv.invoicenum || inv.id }}</p>
                                <p class="text-sm text-slate-500">{{ inv.date }} · {{ inv.total }}</p>
                            </div>
                            <span :class="['px-2.5 py-1 rounded-full text-xs font-semibold', statusColor(inv.status)]">
                                {{ inv.status }}
                            </span>
                        </Link>
                    </div>
                </div>

                <!-- Recent Tickets -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 lg:col-span-2">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-lg font-semibold text-slate-900">Recent Tickets</h2>
                        <Link :href="route('tickets.index')" class="text-sm font-medium text-blue-600 hover:text-blue-700">View all →</Link>
                    </div>
                    <div v-if="tickets.length === 0" class="text-slate-400 text-sm py-8 text-center">No tickets found</div>
                    <div v-else class="space-y-3">
                        <Link
                            v-for="t in tickets.slice(0, 4)"
                            :key="t.id"
                            :href="route('tickets.show', t.id)"
                            class="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-slate-100 transition-colors"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-slate-900 truncate">{{ t.subject }}</p>
                                <p class="text-sm text-slate-500">#{{ t.tid }} · {{ t.lastreply || t.date }}</p>
                            </div>
                            <span :class="['px-2.5 py-1 rounded-full text-xs font-semibold ml-3', statusColor(t.status)]">
                                {{ t.status }}
                            </span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
