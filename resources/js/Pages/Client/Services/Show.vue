<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Card from '@/Components/Card.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({ service: Object });

const showCancelModal = ref(false);
const cancelType = ref('End of Billing Period');
const cancelReason = ref('');
const processing = ref(false);

function submitCancel() {
    processing.value = true;
    router.post(route('client.services.cancel', props.service.id), {
        type: cancelType.value,
        reason: cancelReason.value,
    }, {
        onFinish: () => { processing.value = false; showCancelModal.value = false; },
    });
}

function changePassword() {
    if (!confirm('Are you sure you want to reset the password?')) return;
    router.post(route('client.services.changePassword', props.service.id));
}

function doAction(action) {
    if (!confirm(`Execute "${action}" on this service?`)) return;
    router.post(route('client.services.action', props.service.id), { action });
}

const s = props.service;

const details = [
    { label: 'Product', value: s.name || s.groupname },
    { label: 'Domain', value: s.domain || '—' },
    { label: 'Status', value: s.status, badge: true },
    { label: 'Billing Cycle', value: s.billingcycle },
    { label: 'Amount', value: `$${s.recurringamount}` },
    { label: 'Next Due', value: s.nextduedate },
    { label: 'Registration Date', value: s.regdate },
    { label: 'Dedicated IP', value: s.dedicatedip || '—' },
    { label: 'Username', value: s.username || '—' },
    { label: 'Server', value: s.servername || '—' },
];

const safeActions = ['reboot', 'shutdown', 'boot'];
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <h1 class="text-lg font-bold text-gray-900">{{ s.name || s.groupname }}</h1>
                <p class="text-[13px] text-gray-500">{{ s.domain || 'Service #' + s.id }}</p>
            </div>
        </template>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Main Details -->
            <div class="lg:col-span-2 space-y-6">
                <Card title="Service Details">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                        <div v-for="d in details" :key="d.label">
                            <dt class="text-[12px] font-medium text-gray-500 uppercase tracking-wider">{{ d.label }}</dt>
                            <dd class="mt-1">
                                <StatusBadge v-if="d.badge" :status="d.value" />
                                <span v-else class="text-[14px] text-gray-900">{{ d.value }}</span>
                            </dd>
                        </div>
                    </dl>
                </Card>

                <!-- Configurable Options -->
                <Card v-if="s.configoptions && Object.keys(s.configoptions).length" title="Configuration">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                        <div v-for="(val, key) in (s.configoptions?.configoption || s.configoptions)" :key="key">
                            <dt class="text-[12px] font-medium text-gray-500">{{ val.optionname || key }}</dt>
                            <dd class="text-[14px] text-gray-900">{{ val.value || val }}</dd>
                        </div>
                    </dl>
                </Card>

                <!-- Custom Fields -->
                <Card v-if="s.customfields?.customfield?.length" title="Custom Fields">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                        <div v-for="cf in s.customfields.customfield" :key="cf.id">
                            <dt class="text-[12px] font-medium text-gray-500">{{ cf.name }}</dt>
                            <dd class="text-[14px] text-gray-900">{{ cf.value || '—' }}</dd>
                        </div>
                    </dl>
                </Card>
            </div>

            <!-- Sidebar Actions -->
            <div class="space-y-4">
                <Card title="Quick Actions">
                    <div class="space-y-2">
                        <button v-for="action in safeActions" :key="action" @click="doAction(action)"
                            class="w-full flex items-center gap-2 px-3 py-2 text-[13px] font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors capitalize"
                        >
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a5 5 0 010-7.07m7.072 0a5 5 0 010 7.07M13 12a1 1 0 11-2 0 1 1 0 012 0z" />
                            </svg>
                            {{ action }}
                        </button>
                        <hr class="my-2 border-gray-100" />
                        <button @click="changePassword"
                            class="w-full flex items-center gap-2 px-3 py-2 text-[13px] font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                        >
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                            </svg>
                            Change Password
                        </button>
                        <button @click="showCancelModal = true"
                            class="w-full flex items-center gap-2 px-3 py-2 text-[13px] font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                            Request Cancellation
                        </button>
                    </div>
                </Card>
            </div>
        </div>

        <!-- Cancel Modal -->
        <ConfirmModal
            :show="showCancelModal"
            title="Request Cancellation"
            message="This will submit a cancellation request for this service."
            confirm-text="Submit Request"
            @confirm="submitCancel"
            @close="showCancelModal = false"
        >
            <div class="mt-4 space-y-3">
                <div>
                    <label class="block text-[13px] font-medium text-gray-700 mb-1">When?</label>
                    <select v-model="cancelType" class="w-full text-[13px] rounded-lg border-gray-300">
                        <option>End of Billing Period</option>
                        <option>Immediate</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-gray-700 mb-1">Reason (optional)</label>
                    <textarea v-model="cancelReason" rows="3" class="w-full text-[13px] rounded-lg border-gray-300" placeholder="Why are you cancelling?" />
                </div>
            </div>
        </ConfirmModal>
    </ClientLayout>
</template>
