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

const statusOptions = [
    { value: '', label: 'All' },
    { value: 'Open', label: 'Open' },
    { value: 'Answered', label: 'Answered' },
    { value: 'Customer-Reply', label: 'Awaiting Reply' },
    { value: 'On Hold', label: 'On Hold' },
    { value: 'Closed', label: 'Closed' },
];

function filterByStatus(val) {
    router.get(route('client.tickets.index'), { status: val || undefined }, { preserveState: true });
}

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    if (isNaN(date)) return dateStr;
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined });
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Support Tickets</h1>
        </template>
        <template #actions>
            <Link :href="route('client.tickets.create')"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                New Ticket
            </Link>
        </template>

        <!-- Pill filters -->
        <div class="mb-5 flex flex-wrap items-center gap-2">
            <button v-for="opt in statusOptions" :key="opt.value"
                @click="filterByStatus(opt.value)"
                class="px-3 py-1.5 text-[12px] font-medium rounded-full border transition-colors"
                :class="status === opt.value || (!status && !opt.value)
                    ? 'bg-indigo-600 text-white border-indigo-600'
                    : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 hover:bg-gray-50'">
                {{ opt.label }}
            </button>
            <span class="ml-auto text-[12px] text-gray-400">{{ total }} ticket{{ total !== 1 ? 's' : '' }}</span>
        </div>

        <!-- Empty state -->
        <div v-if="tickets.length === 0">
            <EmptyState title="No tickets found" message="You haven't submitted any support tickets yet.">
                <Link :href="route('client.tickets.create')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-sm">
                    Open a Ticket
                </Link>
            </EmptyState>
        </div>

        <!-- Ticket cards -->
        <div v-else class="space-y-2">
            <Link v-for="t in tickets" :key="t.id"
                :href="route('client.tickets.show', t.id)"
                class="block bg-white rounded-xl border border-gray-200 hover:border-indigo-200 hover:shadow-sm transition-all group">
                <div class="px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <!-- Left -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="text-[11px] font-mono text-gray-400">#{{ t.tid }}</span>
                                <StatusBadge :status="t.status" size="xs" />
                                <span class="text-[11px] font-medium px-1.5 py-0.5 rounded-full"
                                    :class="{
                                        'bg-red-50 text-red-600': t.priority === 'High',
                                        'bg-amber-50 text-amber-600': t.priority === 'Medium',
                                        'bg-green-50 text-green-600': t.priority === 'Low',
                                    }">{{ t.priority }}</span>
                            </div>
                            <h3 class="text-[13px] font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors truncate">
                                {{ t.subject }}
                            </h3>
                            <div class="flex items-center gap-2 mt-1.5 text-[11px] text-gray-400">
                                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" />
                                </svg>
                                <span>{{ t.department || t.deptname }}</span>
                                <span class="text-gray-200">·</span>
                                <span>Opened {{ t.date }}</span>
                            </div>
                        </div>

                        <!-- Right: Last reply -->
                        <div class="flex-shrink-0 text-right hidden sm:block">
                            <p class="text-[10px] text-gray-400 uppercase tracking-wide">Last reply</p>
                            <p class="text-[12px] font-medium text-gray-600 mt-0.5">{{ timeAgo(t.lastreply) }}</p>
                        </div>

                        <!-- Arrow -->
                        <svg class="w-4 h-4 text-gray-300 group-hover:text-indigo-400 transition-colors flex-shrink-0 mt-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </div>
                </div>
            </Link>
        </div>

        <Pagination :page="page" :total="total" :per-page="perPage"
            route-name="client.tickets.index" :route-params="{ status }" />
    </ClientLayout>
</template>
