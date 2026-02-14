<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({ service: Object });

function statusColor(status) {
    const s = (status || '').toLowerCase();
    if (s === 'active') return 'bg-emerald-100 text-emerald-700';
    if (s === 'pending') return 'bg-amber-100 text-amber-700';
    if (['suspended', 'cancelled', 'terminated'].includes(s)) return 'bg-red-100 text-red-700';
    return 'bg-slate-100 text-slate-700';
}

const details = [
    { label: 'Product', value: props.service.name || props.service.translated_name },
    { label: 'Domain', value: props.service.domain },
    { label: 'Status', value: props.service.status },
    { label: 'Billing Cycle', value: props.service.billingcycle },
    { label: 'Amount', value: props.service.recurringamount },
    { label: 'First Payment', value: props.service.firstpaymentamount },
    { label: 'Registration Date', value: props.service.regdate },
    { label: 'Next Due Date', value: props.service.nextduedate },
    { label: 'Dedicated IP', value: props.service.dedicatedip },
    { label: 'Server', value: props.service.servername },
    { label: 'Username', value: props.service.username },
];
</script>

<template>
    <Head :title="service.name || 'Service Details'" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <Link :href="route('services.index')" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-slate-900">{{ service.name || service.translated_name }}</h1>
                <span :class="['px-3 py-1 rounded-full text-xs font-semibold', statusColor(service.status)]">{{ service.status }}</span>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Service Details</h2>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                    <div v-for="d in details.filter(d => d.value)" :key="d.label">
                        <dt class="text-sm text-slate-500">{{ d.label }}</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ d.value }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Custom Fields -->
            <div v-if="service.customfields?.customfield?.length" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Custom Fields</h2>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                    <div v-for="cf in service.customfields.customfield" :key="cf.id">
                        <dt class="text-sm text-slate-500">{{ cf.name }}</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ cf.value || '—' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Config Options -->
            <div v-if="service.configoptions?.configoption?.length" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Configuration</h2>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                    <div v-for="co in service.configoptions.configoption" :key="co.id">
                        <dt class="text-sm text-slate-500">{{ co.option }}</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ co.value || '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
