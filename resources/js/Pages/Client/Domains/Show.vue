<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Card from '@/Components/Card.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
    domain: Object,
    nameservers: Object,
    lockStatus: String,
});

const d = props.domain;
const ns = ref([
    props.nameservers?.ns1 || '',
    props.nameservers?.ns2 || '',
    props.nameservers?.ns3 || '',
    props.nameservers?.ns4 || '',
    props.nameservers?.ns5 || '',
].filter(n => n));

// Ensure at least 2 slots
while (ns.value.length < 2) ns.value.push('');

const savingNs = ref(false);
const showRenewModal = ref(false);

function saveNameservers() {
    savingNs.value = true;
    router.put(route('client.domains.nameservers.update', d.id), {
        nameservers: ns.value.filter(n => n.trim()),
    }, { onFinish: () => savingNs.value = false });
}

function addNs() {
    if (ns.value.length < 5) ns.value.push('');
}

function toggleLock() {
    const newLock = props.lockStatus !== 'locked';
    router.post(route('client.domains.lock.toggle', d.id), { lock: newLock });
}

function requestEpp() {
    router.post(route('client.domains.epp', d.id));
}

function renewDomain() {
    router.post(route('client.domains.renew', d.id));
    showRenewModal.value = false;
}

const details = [
    { label: 'Domain', value: d.domainname },
    { label: 'Status', value: d.status, badge: true },
    { label: 'Registration Date', value: d.registrationdate },
    { label: 'Expiry Date', value: d.expirydate },
    { label: 'Next Due', value: d.nextduedate },
    { label: 'Recurring Amount', value: `$${d.recurringamount || '0.00'}` },
    { label: 'Auto Renew', value: d.autorecurring ? 'Yes' : 'No' },
    { label: 'Registrar Lock', value: props.lockStatus || 'Unknown' },
];
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <h1 class="text-lg font-bold text-gray-900">{{ d.domainname }}</h1>
                <p class="text-[13px] text-gray-500">Domain Management</p>
            </div>
        </template>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Domain Details -->
                <Card title="Domain Information">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                        <div v-for="item in details" :key="item.label">
                            <dt class="text-[12px] font-medium text-gray-500 uppercase tracking-wider">{{ item.label }}</dt>
                            <dd class="mt-1">
                                <StatusBadge v-if="item.badge" :status="item.value" />
                                <span v-else class="text-[14px] text-gray-900">{{ item.value }}</span>
                            </dd>
                        </div>
                    </dl>
                </Card>

                <!-- Nameservers -->
                <Card title="Nameservers">
                    <form @submit.prevent="saveNameservers" class="space-y-3">
                        <div v-for="(n, i) in ns" :key="i" class="flex items-center gap-2">
                            <label class="text-[12px] font-medium text-gray-500 w-8">NS{{ i + 1 }}</label>
                            <input v-model="ns[i]" type="text" placeholder="ns1.example.com" class="flex-1 text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div class="flex items-center gap-3 pt-2">
                            <button v-if="ns.length < 5" type="button" @click="addNs" class="text-[12px] font-medium text-indigo-600 hover:text-indigo-700">+ Add nameserver</button>
                            <div class="flex-1" />
                            <button type="submit" :disabled="savingNs" class="px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm">
                                {{ savingNs ? 'Saving...' : 'Save Nameservers' }}
                            </button>
                        </div>
                    </form>
                </Card>
            </div>

            <!-- Sidebar -->
            <div class="space-y-4">
                <Card title="Actions">
                    <div class="space-y-2">
                        <button @click="showRenewModal = true" class="w-full flex items-center gap-2 px-3 py-2 text-[13px] font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                            Renew Domain
                        </button>
                        <button @click="toggleLock" class="w-full flex items-center gap-2 px-3 py-2 text-[13px] font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                            {{ lockStatus === 'locked' ? 'Unlock Domain' : 'Lock Domain' }}
                        </button>
                        <button @click="requestEpp" class="w-full flex items-center gap-2 px-3 py-2 text-[13px] font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>
                            Get EPP Code
                        </button>
                    </div>
                </Card>
            </div>
        </div>

        <ConfirmModal :show="showRenewModal" title="Renew Domain" :message="'Renew ' + d.domainname + '?'" confirm-text="Renew Now" variant="primary" @confirm="renewDomain" @close="showRenewModal = false" />
    </ClientLayout>
</template>
