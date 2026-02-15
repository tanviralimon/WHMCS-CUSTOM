<script setup>
import { ref, computed, reactive, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Card from '@/Components/Card.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useCurrency } from '@/Composables/useCurrency.js';

const { formatCurrency } = useCurrency();

const props = defineProps({
    domain: Object,
    nameservers: Object,
    lockStatus: String,
    whois: Object,
    dnsRecords: Array,
    addons: Object,
});

const d = props.domain;
const flash = computed(() => usePage().props.flash || {});

// ─── Tabs ─────────────────────────────────────────────────
const tabs = [
    { key: 'overview',   label: 'Overview',             icon: 'info' },
    { key: 'nameservers', label: 'Nameservers',          icon: 'server' },
    { key: 'autorenew',  label: 'Auto Renew',            icon: 'refresh' },
    { key: 'addons',     label: 'Addons',                icon: 'puzzle' },
    { key: 'contact',    label: 'Contact Information',   icon: 'user' },
    { key: 'privatens',  label: 'Private Nameservers',   icon: 'globe' },
    { key: 'dns',        label: 'DNS Management',        icon: 'dns' },
    { key: 'epp',        label: 'EPP Code',              icon: 'key' },
];
const activeTab = ref('overview');

// ─── Overview ──────────────────────────────────────────────
const details = [
    { label: 'Domain Name', value: d.domainname },
    { label: 'Status', value: d.status, badge: true },
    { label: 'Registration Date', value: d.regdate || d.registrationdate },
    { label: 'Expiry Date', value: d.expirydate },
    { label: 'Next Due Date', value: d.nextduedate },
    { label: 'Recurring Amount', value: formatCurrency(d.recurringamount || '0.00') },
    { label: 'Auto Renew', value: d.autorecurring ? 'Enabled' : 'Disabled' },
    { label: 'Registrar Lock', value: props.lockStatus === 'locked' ? 'Locked' : 'Unlocked' },
];

// ─── Nameservers ───────────────────────────────────────────
const ns = ref([
    props.nameservers?.ns1 || '',
    props.nameservers?.ns2 || '',
    props.nameservers?.ns3 || '',
    props.nameservers?.ns4 || '',
    props.nameservers?.ns5 || '',
].filter(n => n));
while (ns.value.length < 2) ns.value.push('');

const savingNs = ref(false);

function saveNameservers() {
    savingNs.value = true;
    router.put(route('client.domains.nameservers.update', d.id), {
        nameservers: ns.value.filter(n => n.trim()),
    }, { onFinish: () => (savingNs.value = false) });
}

function addNs() {
    if (ns.value.length < 5) ns.value.push('');
}

// ─── Lock / Renew ──────────────────────────────────────────
const showRenewModal = ref(false);
const lockProcessing = ref(false);

function toggleLock() {
    lockProcessing.value = true;
    const newLock = props.lockStatus !== 'locked';
    router.post(route('client.domains.lock.toggle', d.id), { lock: newLock }, {
        onFinish: () => (lockProcessing.value = false),
    });
}

function renewDomain() {
    router.post(route('client.domains.renew', d.id));
    showRenewModal.value = false;
}

// ─── Auto Renew ────────────────────────────────────────────
const autoRenewEnabled = ref(!!d.autorecurring);
const autoRenewProcessing = ref(false);

function toggleAutoRenew() {
    autoRenewProcessing.value = true;
    const newValue = !autoRenewEnabled.value;
    router.post(route('client.domains.autorenew', d.id), { autorenew: newValue }, {
        preserveScroll: true,
        onSuccess: () => (autoRenewEnabled.value = newValue),
        onFinish: () => (autoRenewProcessing.value = false),
    });
}

// ─── Addons ────────────────────────────────────────────────
const addonList = computed(() => [
    { key: 'dnsmanagement', label: 'DNS Management', desc: 'Manage DNS records directly from this panel. This feature is free.' },
    { key: 'emailforwarding', label: 'Email Forwarding', desc: 'Forward emails for your domain.' },
    { key: 'idprotection', label: 'ID Protection', desc: 'Hide your WHOIS contact details from public lookups.' },
]);

// ─── Contact / WHOIS ───────────────────────────────────────
// WHMCS DomainGetWhoisInfo returns an array of contact-type sections
const whoisSections = computed(() => {
    if (!props.whois) return [];
    // The API may return { 'Registrant': {...}, 'Admin': {...}, ... }
    // or nested under a key. Normalize:
    const data = props.whois;
    const sections = [];
    const skip = ['result', 'clientid', 'domainid'];
    for (const [sectionName, fields] of Object.entries(data)) {
        if (skip.includes(sectionName)) continue;
        if (typeof fields === 'object' && fields !== null && !Array.isArray(fields)) {
            sections.push({ name: sectionName, fields });
        }
    }
    return sections;
});

const whoisForm = reactive({});
const whoisProcessing = ref(false);

// Initialize whois form from sections
watch(whoisSections, (sections) => {
    for (const section of sections) {
        if (!whoisForm[section.name]) {
            whoisForm[section.name] = { ...section.fields };
        }
    }
}, { immediate: true });

function saveWhoisContact() {
    whoisProcessing.value = true;
    router.put(route('client.domains.whois.update', d.id), {
        contactdetails: { ...whoisForm },
    }, {
        preserveScroll: true,
        onFinish: () => (whoisProcessing.value = false),
    });
}

// ─── Private Nameservers ───────────────────────────────────
const pnsHostname = ref('');
const pnsIp = ref('');
const pnsRegistering = ref(false);

const pnsModHostname = ref('');
const pnsModCurrentIp = ref('');
const pnsModNewIp = ref('');
const pnsModifying = ref(false);

const pnsDelHostname = ref('');
const pnsDeleting = ref(false);

const showDeletePnsModal = ref(false);

function registerPns() {
    pnsRegistering.value = true;
    router.post(route('client.domains.privatens.register', d.id), {
        nameserver: pnsHostname.value,
        ipaddress: pnsIp.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { pnsHostname.value = ''; pnsIp.value = ''; },
        onFinish: () => (pnsRegistering.value = false),
    });
}

function modifyPns() {
    pnsModifying.value = true;
    router.post(route('client.domains.privatens.modify', d.id), {
        nameserver: pnsModHostname.value,
        currentip: pnsModCurrentIp.value,
        newip: pnsModNewIp.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { pnsModHostname.value = ''; pnsModCurrentIp.value = ''; pnsModNewIp.value = ''; },
        onFinish: () => (pnsModifying.value = false),
    });
}

function confirmDeletePns() {
    showDeletePnsModal.value = true;
}

function deletePns() {
    pnsDeleting.value = true;
    showDeletePnsModal.value = false;
    router.post(route('client.domains.privatens.delete', d.id), {
        nameserver: pnsDelHostname.value,
    }, {
        preserveScroll: true,
        onSuccess: () => (pnsDelHostname.value = ''),
        onFinish: () => (pnsDeleting.value = false),
    });
}

// ─── DNS Management ────────────────────────────────────────
const dnsTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA'];

// Initialize DNS records from props
const dnsRecordList = ref(
    (props.dnsRecords || []).map((r, i) => ({
        _key: i,
        name: r.hostname || r.name || '',
        type: r.type || 'A',
        address: r.address || r.destination || r.content || '',
        priority: r.priority ?? '',
        ttl: r.ttl ?? '',
    }))
);
let dnsKeyCounter = dnsRecordList.value.length;

const dnsSaving = ref(false);

function addDnsRecord() {
    dnsRecordList.value.push({ _key: ++dnsKeyCounter, name: '', type: 'A', address: '', priority: '', ttl: '' });
}

function removeDnsRecord(idx) {
    dnsRecordList.value.splice(idx, 1);
}

const dnsStatus = ref('');   // 'success' | 'error' | ''
const dnsMessage = ref('');

function saveDns() {
    dnsSaving.value = true;
    dnsStatus.value = '';
    dnsMessage.value = '';

    const records = dnsRecordList.value
        .filter(r => r.name && r.address)
        .map(r => ({
            name: r.name,
            type: r.type,
            address: r.address,
            priority: r.priority !== '' && r.priority !== null ? parseInt(r.priority) : null,
            ttl: r.ttl !== '' && r.ttl !== null ? parseInt(r.ttl) : null,
        }));

    if (records.length === 0) {
        dnsSaving.value = false;
        dnsStatus.value = 'error';
        dnsMessage.value = 'Add at least one record with a hostname and address.';
        return;
    }

    router.put(route('client.domains.dns.update', d.id), { records }, {
        preserveScroll: true,
        onSuccess: (page) => {
            const flash = page.props?.flash || {};
            if (flash.success) {
                dnsStatus.value = 'success';
                dnsMessage.value = flash.success;
            }
            // Refresh the DNS record list from the newly loaded props
            const freshRecords = page.props?.dnsRecords || [];
            dnsRecordList.value = freshRecords.map((r, i) => ({
                _key: i,
                name: r.hostname || r.name || '',
                type: r.type || 'A',
                address: r.address || r.destination || r.content || '',
                priority: r.priority ?? '',
                ttl: r.ttl ?? '',
            }));
            dnsKeyCounter = dnsRecordList.value.length;
        },
        onError: (errors) => {
            dnsStatus.value = 'error';
            dnsMessage.value = errors.whmcs || errors.records || Object.values(errors).flat().join(', ') || 'Failed to save DNS records.';
        },
        onFinish: () => (dnsSaving.value = false),
    });
}

// ─── EPP Code ──────────────────────────────────────────────
const eppLoading = ref(false);
const eppCode = ref('');
const eppCopied = ref(false);

// Watch for flash eppCode
watch(() => flash.value.eppCode, (val) => {
    if (val) eppCode.value = val;
}, { immediate: true });

function requestEpp() {
    eppLoading.value = true;
    eppCode.value = '';
    router.post(route('client.domains.epp', d.id), {}, {
        preserveScroll: true,
        onFinish: () => (eppLoading.value = false),
    });
}

async function copyEpp() {
    try {
        await navigator.clipboard.writeText(eppCode.value);
        eppCopied.value = true;
        setTimeout(() => (eppCopied.value = false), 2000);
    } catch {
        // Fallback
        const el = document.createElement('textarea');
        el.value = eppCode.value;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        eppCopied.value = true;
        setTimeout(() => (eppCopied.value = false), 2000);
    }
}

// ─── Tab icons (simple SVG paths) ──────────────────────────
const iconPaths = {
    info: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    server: 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01',
    refresh: 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182',
    puzzle: 'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z',
    user: 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
    globe: 'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418',
    dns: 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z',
    key: 'M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z',
};
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <h1 class="text-lg font-bold text-gray-900">{{ d.domainname }}</h1>
                <p class="text-[13px] text-gray-500">Domain Management</p>
            </div>
        </template>
        <template #actions>
            <button @click="showRenewModal = true" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                Renew
            </button>
            <button @click="toggleLock" :disabled="lockProcessing" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors shadow-sm disabled:opacity-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                {{ lockStatus === 'locked' ? 'Unlock' : 'Lock' }}
            </button>
        </template>

        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Sidebar Tabs -->
            <nav class="lg:w-56 flex-shrink-0">
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="activeTab = tab.key"
                        :class="[
                            'w-full flex items-center gap-3 px-4 py-3 text-[13px] font-medium transition-colors border-l-2',
                            activeTab === tab.key
                                ? 'bg-indigo-50/70 text-indigo-700 border-indigo-600'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-transparent',
                        ]"
                    >
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="iconPaths[tab.icon]" />
                        </svg>
                        {{ tab.label }}
                    </button>
                </div>
            </nav>

            <!-- Tab Content -->
            <div class="flex-1 min-w-0">
                <!-- ═══ OVERVIEW ═══ -->
                <Card v-if="activeTab === 'overview'" title="Domain Information">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                        <div v-for="item in details" :key="item.label">
                            <dt class="text-[12px] font-medium text-gray-500 uppercase tracking-wider">{{ item.label }}</dt>
                            <dd class="mt-1">
                                <StatusBadge v-if="item.badge" :status="item.value" />
                                <span v-else class="text-[14px] text-gray-900">{{ item.value }}</span>
                            </dd>
                        </div>
                    </dl>
                </Card>

                <!-- ═══ NAMESERVERS ═══ -->
                <Card v-if="activeTab === 'nameservers'" title="Nameservers" description="Configure the nameservers for your domain.">
                    <form @submit.prevent="saveNameservers" class="space-y-3">
                        <div v-for="(n, i) in ns" :key="i" class="flex items-center gap-3">
                            <label class="text-[12px] font-semibold text-gray-500 w-10 text-right">NS{{ i + 1 }}</label>
                            <input v-model="ns[i]" type="text" placeholder="ns1.example.com" class="flex-1 text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                            <button v-if="ns.length > 2" type="button" @click="ns.splice(i, 1)" class="p-1.5 text-gray-400 hover:text-red-500 transition-colors" title="Remove">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
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

                <!-- ═══ AUTO RENEW ═══ -->
                <Card v-if="activeTab === 'autorenew'" title="Auto Renew" description="Control whether your domain renews automatically before expiry.">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div>
                            <p class="text-[14px] font-medium text-gray-900">Automatic Renewal</p>
                            <p class="text-[13px] text-gray-500 mt-0.5">
                                {{ autoRenewEnabled
                                    ? 'Your domain will be automatically renewed before it expires.'
                                    : 'Your domain will NOT be automatically renewed. Remember to renew manually.' }}
                            </p>
                        </div>
                        <button
                            @click="toggleAutoRenew"
                            :disabled="autoRenewProcessing"
                            :class="[
                                'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 disabled:opacity-50',
                                autoRenewEnabled ? 'bg-indigo-600' : 'bg-gray-200',
                            ]"
                        >
                            <span :class="[
                                'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                autoRenewEnabled ? 'translate-x-5' : 'translate-x-0',
                            ]" />
                        </button>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                            <p class="text-[12px] font-medium text-gray-500 uppercase tracking-wider">Status</p>
                            <p class="mt-1 text-[14px] font-semibold" :class="autoRenewEnabled ? 'text-emerald-600' : 'text-red-600'">
                                {{ autoRenewEnabled ? 'Enabled' : 'Disabled' }}
                            </p>
                        </div>
                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                            <p class="text-[12px] font-medium text-gray-500 uppercase tracking-wider">Expiry Date</p>
                            <p class="mt-1 text-[14px] font-semibold text-gray-900">{{ d.expirydate }}</p>
                        </div>
                    </div>
                </Card>

                <!-- ═══ ADDONS ═══ -->
                <Card v-if="activeTab === 'addons'" title="Domain Addons" description="Add-on services active for this domain.">
                    <div class="space-y-3">
                        <div
                            v-for="addon in addonList"
                            :key="addon.key"
                            class="flex items-center justify-between p-4 bg-gray-50 rounded-lg"
                        >
                            <div>
                                <p class="text-[14px] font-medium text-gray-900">{{ addon.label }}</p>
                                <p class="text-[13px] text-gray-500 mt-0.5">{{ addon.desc }}</p>
                            </div>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-medium',
                                    addons[addon.key]
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-gray-100 text-gray-500',
                                ]"
                            >
                                <span :class="['w-1.5 h-1.5 rounded-full', addons[addon.key] ? 'bg-emerald-500' : 'bg-gray-400']" />
                                {{ addons[addon.key] ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                    <p v-if="!addonList.length" class="text-[13px] text-gray-500 text-center py-6">No addons available for this domain.</p>
                </Card>

                <!-- ═══ CONTACT INFORMATION (WHOIS) ═══ -->
                <Card v-if="activeTab === 'contact'" title="Contact Information" description="WHOIS contact details for this domain. Changes may take time to propagate.">
                    <div v-if="whoisSections.length === 0" class="text-center py-8">
                        <svg class="w-10 h-10 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                        <p class="mt-3 text-[13px] text-gray-500">WHOIS contact information is not available for this domain.</p>
                    </div>
                    <form v-else @submit.prevent="saveWhoisContact" class="space-y-6">
                        <div v-for="section in whoisSections" :key="section.name">
                            <h4 class="text-[13px] font-semibold text-gray-900 mb-3 pb-2 border-b border-gray-100">{{ section.name }}</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div v-for="(val, fieldName) in section.fields" :key="fieldName">
                                    <label class="block text-[12px] font-medium text-gray-500 mb-1">{{ fieldName }}</label>
                                    <input
                                        v-model="whoisForm[section.name][fieldName]"
                                        type="text"
                                        class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end pt-2">
                            <button type="submit" :disabled="whoisProcessing" class="px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm">
                                {{ whoisProcessing ? 'Saving...' : 'Save Contact Info' }}
                            </button>
                        </div>
                    </form>
                </Card>

                <!-- ═══ PRIVATE NAMESERVERS ═══ -->
                <div v-if="activeTab === 'privatens'" class="space-y-6">
                    <!-- Register -->
                    <Card title="Register Private Nameserver" description="Create a child nameserver (glue record) under your domain.">
                        <form @submit.prevent="registerPns" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[12px] font-medium text-gray-500 mb-1">Hostname</label>
                                    <div class="flex items-center">
                                        <input v-model="pnsHostname" type="text" placeholder="ns1" class="flex-1 text-[13px] rounded-l-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                                        <span class="inline-flex items-center px-3 py-2 text-[12px] text-gray-500 bg-gray-50 border border-l-0 border-gray-300 rounded-r-lg">.{{ d.domainname }}</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[12px] font-medium text-gray-500 mb-1">IP Address</label>
                                    <input v-model="pnsIp" type="text" placeholder="1.2.3.4" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" :disabled="pnsRegistering || !pnsHostname || !pnsIp" class="px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm">
                                    {{ pnsRegistering ? 'Registering...' : 'Register Nameserver' }}
                                </button>
                            </div>
                        </form>
                    </Card>

                    <!-- Modify -->
                    <Card title="Modify Private Nameserver" description="Update the IP address of an existing child nameserver.">
                        <form @submit.prevent="modifyPns" class="space-y-4">
                            <div>
                                <label class="block text-[12px] font-medium text-gray-500 mb-1">Nameserver Hostname</label>
                                <input v-model="pnsModHostname" type="text" :placeholder="'ns1.' + d.domainname" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[12px] font-medium text-gray-500 mb-1">Current IP Address</label>
                                    <input v-model="pnsModCurrentIp" type="text" placeholder="1.2.3.4" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-[12px] font-medium text-gray-500 mb-1">New IP Address</label>
                                    <input v-model="pnsModNewIp" type="text" placeholder="5.6.7.8" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" :disabled="pnsModifying || !pnsModHostname || !pnsModCurrentIp || !pnsModNewIp" class="px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm">
                                    {{ pnsModifying ? 'Updating...' : 'Update IP Address' }}
                                </button>
                            </div>
                        </form>
                    </Card>

                    <!-- Delete -->
                    <Card title="Delete Private Nameserver" description="Remove a child nameserver from your domain.">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-[12px] font-medium text-gray-500 mb-1">Nameserver Hostname</label>
                                <input v-model="pnsDelHostname" type="text" :placeholder="'ns1.' + d.domainname" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div class="flex justify-end">
                                <button @click="confirmDeletePns" :disabled="pnsDeleting || !pnsDelHostname" class="px-4 py-2 text-[13px] font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50 transition-colors shadow-sm">
                                    {{ pnsDeleting ? 'Deleting...' : 'Delete Nameserver' }}
                                </button>
                            </div>
                        </div>
                    </Card>
                </div>

                <!-- ═══ DNS MANAGEMENT ═══ -->
                <Card v-if="activeTab === 'dns'" title="DNS Management" description="Manage DNS records for your domain directly from this panel — it's free!">
                    <div>
                        <!-- Status messages -->
                        <div v-if="dnsStatus === 'success'" class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-[13px] text-emerald-700">
                            ✓ {{ dnsMessage }}
                        </div>
                        <div v-if="dnsStatus === 'error'" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-[13px] text-red-700">
                            ✗ {{ dnsMessage }}
                        </div>

                        <!-- Records Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-[13px]">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-2 pr-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Hostname</th>
                                        <th class="text-left py-2 pr-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-28">Type</th>
                                        <th class="text-left py-2 pr-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Address / Value</th>
                                        <th class="text-left py-2 pr-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-20">Priority</th>
                                        <th class="text-left py-2 pr-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-20">TTL</th>
                                        <th class="py-2 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(rec, idx) in dnsRecordList" :key="rec._key" class="border-b border-gray-50">
                                        <td class="py-2 pr-2">
                                            <input v-model="rec.name" type="text" placeholder="@" class="w-full text-[13px] rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 py-1.5" />
                                        </td>
                                        <td class="py-2 pr-2">
                                            <select v-model="rec.type" class="w-full text-[13px] rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 py-1.5">
                                                <option v-for="t in dnsTypes" :key="t" :value="t">{{ t }}</option>
                                            </select>
                                        </td>
                                        <td class="py-2 pr-2">
                                            <input v-model="rec.address" type="text" placeholder="1.2.3.4" class="w-full text-[13px] rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 py-1.5" />
                                        </td>
                                        <td class="py-2 pr-2">
                                            <input v-model="rec.priority" type="number" placeholder="10" class="w-full text-[13px] rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 py-1.5" />
                                        </td>
                                        <td class="py-2 pr-2">
                                            <input v-model="rec.ttl" type="number" placeholder="14400" class="w-full text-[13px] rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 py-1.5" />
                                        </td>
                                        <td class="py-2 text-center">
                                            <button @click="removeDnsRecord(idx)" class="p-1 text-gray-400 hover:text-red-500 transition-colors" title="Remove record">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="dnsRecordList.length === 0">
                                        <td colspan="6" class="py-6 text-center text-gray-400 text-[13px]">No DNS records. Click "Add Record" to create one.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="flex items-center justify-between pt-4 mt-2 border-t border-gray-100">
                            <button @click="addDnsRecord" type="button" class="text-[12px] font-medium text-indigo-600 hover:text-indigo-700">+ Add Record</button>
                            <button @click="saveDns" :disabled="dnsSaving" class="px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm">
                                {{ dnsSaving ? 'Saving...' : 'Save DNS Records' }}
                            </button>
                        </div>
                    </div>
                </Card>

                <!-- ═══ EPP CODE ═══ -->
                <Card v-if="activeTab === 'epp'" title="EPP / Authorization Code" description="The EPP code (also known as Auth Code or Transfer Key) is required to transfer your domain to another registrar.">
                    <div class="space-y-4">
                        <!-- EPP code display -->
                        <div v-if="eppCode" class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                            <p class="text-[12px] font-medium text-emerald-700 mb-2">Your EPP / Authorization Code:</p>
                            <div class="flex items-center gap-3">
                                <code class="flex-1 px-3 py-2 bg-white border border-emerald-200 rounded-lg text-[14px] font-mono text-gray-900 select-all">{{ eppCode }}</code>
                                <button
                                    @click="copyEpp"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 text-[13px] font-medium rounded-lg transition-colors shadow-sm"
                                    :class="eppCopied
                                        ? 'bg-emerald-600 text-white'
                                        : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'"
                                >
                                    <svg v-if="!eppCopied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>
                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                    {{ eppCopied ? 'Copied!' : 'Copy' }}
                                </button>
                            </div>
                        </div>

                        <!-- Request button -->
                        <div v-if="!eppCode" class="text-center py-4">
                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>
                            <p class="text-[13px] text-gray-500 mb-4">Click the button below to retrieve your domain's EPP code.</p>
                            <button
                                @click="requestEpp"
                                :disabled="eppLoading"
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm"
                            >
                                <svg v-if="eppLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>
                                {{ eppLoading ? 'Requesting...' : 'Get EPP Code' }}
                            </button>
                        </div>

                        <!-- Request again button (when code is already showing) -->
                        <div v-if="eppCode" class="flex justify-end">
                            <button
                                @click="requestEpp"
                                :disabled="eppLoading"
                                class="text-[12px] font-medium text-indigo-600 hover:text-indigo-700 disabled:opacity-50"
                            >
                                {{ eppLoading ? 'Requesting...' : 'Request New Code' }}
                            </button>
                        </div>

                        <div class="mt-2 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                            <p class="text-[12px] text-amber-700">
                                <strong>Note:</strong> Some registrars may send the EPP code to your registered email address instead of displaying it here. If you don't see the code above after requesting, please check your email.
                            </p>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <!-- Modals -->
        <ConfirmModal :show="showRenewModal" title="Renew Domain" :message="'Renew ' + d.domainname + '?'" confirm-text="Renew Now" variant="primary" @confirm="renewDomain" @close="showRenewModal = false" />
        <ConfirmModal :show="showDeletePnsModal" title="Delete Nameserver" :message="'Delete private nameserver ' + pnsDelHostname + '?'" confirm-text="Delete" variant="danger" @confirm="deletePns" @close="showDeletePnsModal = false" />
    </ClientLayout>
</template>
