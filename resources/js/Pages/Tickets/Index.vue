<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ tickets: Array, total: Number, page: Number, status: String });

const statusFilter = ref(props.status || '');

function statusColor(status) {
    const s = (status || '').toLowerCase();
    if (['open', 'customer reply'].includes(s)) return 'bg-blue-100 text-blue-700';
    if (s === 'answered') return 'bg-emerald-100 text-emerald-700';
    if (s === 'closed') return 'bg-slate-200 text-slate-600';
    if (['on hold', 'in progress'].includes(s)) return 'bg-amber-100 text-amber-700';
    return 'bg-slate-100 text-slate-700';
}

function priorityColor(p) {
    const s = (p || '').toLowerCase();
    if (s === 'high') return 'text-red-600';
    if (s === 'medium') return 'text-amber-600';
    return 'text-slate-500';
}

function filter() {
    router.get(route('tickets.index'), { status: statusFilter.value, page: 1 }, { preserveState: true });
}
</script>

<template>
    <Head title="Support Tickets" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Support Tickets</h1>
                    <p class="text-slate-500 mt-1">{{ total }} total ticket(s)</p>
                </div>
                <div class="flex items-center gap-3">
                    <select v-model="statusFilter" @change="filter" class="rounded-lg border-slate-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Statuses</option>
                        <option value="Open">Open</option>
                        <option value="Answered">Answered</option>
                        <option value="Customer-Reply">Customer Reply</option>
                        <option value="Closed">Closed</option>
                    </select>
                    <Link :href="route('tickets.create')" class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-semibold rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Ticket
                    </Link>
                </div>
            </div>

            <div v-if="tickets.length === 0" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-12 text-center">
                <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                </svg>
                <p class="text-slate-500">No tickets found</p>
            </div>

            <div v-else class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Ticket</th>
                                <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Department</th>
                                <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Priority</th>
                                <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Last Reply</th>
                                <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="t in tickets" :key="t.id" class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-slate-900">{{ t.subject }}</p>
                                    <p class="text-sm text-slate-500">#{{ t.tid }}</p>
                                </td>
                                <td class="px-6 py-4 text-slate-600">{{ t.deptname }}</td>
                                <td class="px-6 py-4">
                                    <span :class="['text-sm font-medium', priorityColor(t.priority)]">{{ t.priority }}</span>
                                </td>
                                <td class="px-6 py-4 text-slate-600 text-sm">{{ t.lastreply || t.date }}</td>
                                <td class="px-6 py-4">
                                    <span :class="['px-2.5 py-1 rounded-full text-xs font-semibold', statusColor(t.status)]">{{ t.status }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link :href="route('tickets.show', t.id)" class="text-sm font-medium text-blue-600 hover:text-blue-700">View →</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="total > 25" class="flex items-center gap-2 justify-center">
                <Link v-if="page > 1" :href="route('tickets.index', { page: page - 1, status: statusFilter })" class="px-4 py-2 rounded-lg bg-white border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50">← Previous</Link>
                <span class="text-sm text-slate-500">Page {{ page }}</span>
                <Link v-if="page * 25 < total" :href="route('tickets.index', { page: page + 1, status: statusFilter })" class="px-4 py-2 rounded-lg bg-white border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50">Next →</Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
