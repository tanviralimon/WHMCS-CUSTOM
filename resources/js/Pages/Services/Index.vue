<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({ services: Array, total: Number, page: Number });

function statusColor(status) {
    const s = (status || '').toLowerCase();
    if (s === 'active') return 'bg-emerald-100 text-emerald-700';
    if (s === 'pending') return 'bg-amber-100 text-amber-700';
    if (['suspended', 'cancelled', 'terminated'].includes(s)) return 'bg-red-100 text-red-700';
    return 'bg-slate-100 text-slate-700';
}
</script>

<template>
    <Head title="Services" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Services</h1>
                    <p class="text-slate-500 mt-1">{{ total }} total service(s)</p>
                </div>
            </div>

            <div v-if="services.length === 0" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-12 text-center">
                <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2" />
                </svg>
                <p class="text-slate-500">No services found</p>
            </div>

            <div v-else class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Domain</th>
                                <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Billing</th>
                                <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Next Due</th>
                                <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="svc in services" :key="svc.id" class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 font-medium text-slate-900">{{ svc.name || svc.translated_name }}</td>
                                <td class="px-6 py-4 text-slate-600">{{ svc.domain || '—' }}</td>
                                <td class="px-6 py-4 text-slate-600">{{ svc.billingcycle }} · {{ svc.recurringamount }}</td>
                                <td class="px-6 py-4 text-slate-600">{{ svc.nextduedate }}</td>
                                <td class="px-6 py-4">
                                    <span :class="['px-2.5 py-1 rounded-full text-xs font-semibold', statusColor(svc.status)]">{{ svc.status }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link :href="route('services.show', svc.id)" class="text-sm font-medium text-blue-600 hover:text-blue-700">View →</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="total > 25" class="flex items-center gap-2 justify-center">
                <Link v-if="page > 1" :href="route('services.index', { page: page - 1 })" class="px-4 py-2 rounded-lg bg-white border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50">← Previous</Link>
                <span class="text-sm text-slate-500">Page {{ page }}</span>
                <Link v-if="page * 25 < total" :href="route('services.index', { page: page + 1 })" class="px-4 py-2 rounded-lg bg-white border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50">Next →</Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
