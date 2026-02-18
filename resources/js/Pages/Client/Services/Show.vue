<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Card from '@/Components/Card.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useCurrency } from '@/Composables/useCurrency.js';

const { formatCurrency } = useCurrency();
const flash = computed(() => usePage().props.flash || {});

const props = defineProps({
    service: Object,
    serviceType: { type: String, default: 'other' },
    serverModule: { type: String, default: '' },
    controlPanelUrl: { type: String, default: null },
    webmailUrl: { type: String, default: null },
    ssoSupported: { type: Boolean, default: false },
});

const s = props.service;
const isHosting = props.serviceType === 'hosting';
const isVps = props.serviceType === 'vps';
const isActive = s.status === 'Active';

// SSO login URL
const panelLoginUrl = computed(() => {
    if (props.ssoSupported) return route('client.services.sso', s.id);
    return props.controlPanelUrl;
});

// SSO deep link (SPanel: "category/page" format)
function ssoUrl(redirect) {
    if (props.ssoSupported && redirect) {
        return route('client.services.sso', s.id) + '?redirect=' + encodeURIComponent(redirect);
    }
    return panelLoginUrl.value;
}

// Module display name
const moduleName = computed(() => {
    const names = {
        spanel: 'HostPanel', cpanel: 'cPanel', plesk: 'Plesk',
        directadmin: 'DirectAdmin', virtualizor: 'Virtualizor',
        proxmox: 'Proxmox', solusvm: 'SolusVM',
    };
    return names[props.serverModule] || props.serverModule || 'Control Panel';
});

// ─── Tabs ──────────────────────────────────────────────────
const tabs = computed(() => {
    const t = [{ id: 'overview', label: 'Overview' }];
    if (isHosting || isVps) t.push({ id: 'management', label: 'Management' });
    if (hasConfig.value || hasCustomFields.value) t.push({ id: 'config', label: 'Configuration' });
    return t;
});
// Default to management tab for VPS services
const activeTab = ref(isVps ? 'management' : 'overview');

// ─── Details ───────────────────────────────────────────────
const details = computed(() => {
    const d = [
        { label: 'Product / Service', value: s.name || s.groupname },
        { label: 'Domain', value: s.domain || '—' },
        { label: 'Status', value: s.status, badge: true },
        { label: 'Billing Cycle', value: s.billingcycle },
        { label: 'Amount', value: formatCurrency(s.recurringamount) + ' / ' + s.billingcycle },
        { label: 'Payment Method', value: s.paymentmethodname || s.paymentmethod || '—' },
        { label: 'Next Due Date', value: s.nextduedate && s.nextduedate !== '0000-00-00' ? s.nextduedate : '—' },
        { label: 'Registration Date', value: s.regdate || '—' },
    ];
    if (s.dedicatedip) d.push({ label: 'Dedicated IP', value: s.dedicatedip });
    if (s.assignedips) d.push({ label: 'Assigned IPs', value: s.assignedips });
    if (isHosting) {
        d.push({ label: 'Username', value: s.username || '—' });
        d.push({ label: 'Server', value: s.servername || '—' });
    }
    if (isVps) {
        d.push({ label: 'Server', value: s.servername || '—' });
    }
    return d;
});

// ─── Config Options ────────────────────────────────────────
const configOptions = computed(() => {
    const opts = s.configoptions?.configoption || [];
    return Array.isArray(opts) ? opts : [opts];
});
const hasConfig = computed(() => configOptions.value.length > 0);

const customFields = computed(() => {
    const fields = s.customfields?.customfield || [];
    return (Array.isArray(fields) ? fields : [fields]).filter(f => f.value);
});
const hasCustomFields = computed(() => customFields.value.length > 0);

// ─── Usage Statistics (for hosting) ────────────────────────
const usageStats = computed(() => {
    if (!isHosting) return [];
    return [
        { label: 'Disk Usage', used: parseFloat(s.diskusage) || 0, limit: parseFloat(s.disklimit) || 0, unit: 'MB' },
        { label: 'Bandwidth', used: parseFloat(s.bwusage) || 0, limit: parseFloat(s.bwlimit) || 0, unit: 'MB' },
    ];
});

function usagePercent(used, limit) {
    if (!limit || limit === 0) return 0;
    return Math.min(100, Math.round((used / limit) * 100));
}

function formatUsage(val, unit) {
    if (val >= 1024) return (val / 1024).toFixed(1) + ' GB';
    return val + ' ' + unit;
}

// ─── Cancel Modal ──────────────────────────────────────────
const showCancelModal = ref(false);
const cancelType = ref('End of Billing Period');
const cancelReason = ref('');
const processing = ref(false);

function submitCancel() {
    processing.value = true;
    router.post(route('client.services.cancel', s.id), {
        type: cancelType.value,
        reason: cancelReason.value,
    }, {
        onFinish: () => { processing.value = false; showCancelModal.value = false; },
    });
}

// ─── Action Confirmation Modal ─────────────────────────────
const showActionModal = ref(false);
const pendingAction = ref(null);
const actionLoading = ref(null);

const actionLabels = {
    boot: 'Boot',
    reboot: 'Reboot',
    shutdown: 'Shutdown',
    resetpassword: 'Reset Password',
    vnc: 'Open VNC Console',
    console: 'Open Console',
};

const actionDescriptions = {
    boot: 'This will start your VPS if it is currently powered off.',
    reboot: 'This will restart your VPS. All running processes will be temporarily interrupted.',
    shutdown: 'This will power off your VPS. You will need to boot it again to bring it back online.',
    resetpassword: 'This will reset the root password of your VPS. A new password will be emailed to you.',
    vnc: 'This will open a VNC console session to your VPS.',
    console: 'This will open a direct console to your VPS.',
};

function confirmAction(action) {
    pendingAction.value = action;
    showActionModal.value = true;
}

function executeAction() {
    if (!pendingAction.value) return;
    const action = pendingAction.value;
    showActionModal.value = false;
    actionLoading.value = action;

    router.post(route('client.services.action', s.id), { action }, {
        preserveScroll: true,
        onSuccess: (page) => {
            // VNC/Console: server returns a redirect_url — open in new tab
            const redirectUrl = page.props?.flash?.redirect_url;
            if (redirectUrl && (action === 'vnc' || action === 'console')) {
                window.open(redirectUrl, '_blank');
            }
        },
        onFinish: () => {
            actionLoading.value = null;
            pendingAction.value = null;
        },
    });
}

// ─── Password Change ───────────────────────────────────────
const showPasswordModal = ref(false);
const passwordLoading = ref(false);

function doChangePassword() {
    showPasswordModal.value = false;
    passwordLoading.value = true;
    router.post(route('client.services.changePassword', s.id), {}, {
        preserveScroll: true,
        onFinish: () => { passwordLoading.value = false; },
    });
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <div class="flex items-start justify-between w-full">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-lg font-bold text-gray-900">{{ s.name || s.groupname }}</h1>
                        <StatusBadge :status="s.status" />
                    </div>
                    <p class="text-[13px] text-gray-500 mt-0.5">{{ s.domain || 'Service #' + s.id }}</p>
                </div>
                <Link :href="route('client.services.index')" class="text-[13px] text-gray-500 hover:text-gray-700 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    Back to Services
                </Link>
            </div>
        </template>

        <!-- Flash Messages -->
        <div v-if="flash.success" class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-lg text-[13px] text-emerald-700 flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            {{ flash.success }}
        </div>
        <div v-if="flash.error || $page.props.errors?.whmcs" class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-[13px] text-red-700 flex items-center gap-2">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            {{ flash.error || $page.props.errors.whmcs }}
        </div>

        <!-- ═══ VPS Hero Panel ═══ -->
        <div v-if="isVps && isActive" class="mb-6 rounded-xl bg-gradient-to-r from-slate-800 to-slate-900 p-5 text-white shadow-lg">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-white/10 backdrop-blur flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" /></svg>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold">{{ s.name || s.groupname }}</h2>
                        <div class="flex items-center gap-3 mt-1 text-[13px] text-slate-300">
                            <span v-if="s.dedicatedip" class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9" /></svg>
                                {{ s.dedicatedip }}
                            </span>
                            <span v-if="s.domain" class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2" /></svg>
                                {{ s.domain }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a v-if="panelLoginUrl" :href="panelLoginUrl" target="_blank"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-500 hover:bg-indigo-400 text-white text-[13px] font-semibold rounded-lg transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                        Open {{ moduleName }}
                    </a>
                    <button @click="confirmAction('vnc')"
                        :disabled="actionLoading === 'vnc'"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 text-white text-[13px] font-semibold rounded-lg transition-colors backdrop-blur">
                        <svg v-if="actionLoading !== 'vnc'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                        VNC Console
                    </button>
                </div>
            </div>
        </div>

        <!-- ═══ Hosting Login Bar ═══ -->
        <div v-if="isHosting && isActive && panelLoginUrl" class="mb-6 flex flex-wrap gap-3">
            <a :href="panelLoginUrl" target="_blank"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white text-[13px] font-semibold rounded-lg hover:bg-indigo-700 shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg>
                Login to {{ moduleName }}
            </a>
            <a v-if="webmailUrl" :href="webmailUrl" target="_blank" rel="noopener"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-gray-700 text-[13px] font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                Login to Webmail
            </a>
        </div>

        <!-- Tab Navigation -->
        <div v-if="tabs.length > 1" class="mb-6 border-b border-gray-200">
            <nav class="flex gap-6">
                <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
                    :class="['pb-3 text-[13px] font-medium border-b-2 transition-colors', activeTab === tab.id ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700']">
                    {{ tab.label }}
                </button>
            </nav>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- ═══ MAIN COLUMN ═══ -->
            <div class="lg:col-span-2 space-y-6">

                <!-- ─── Overview Tab ─── -->
                <template v-if="activeTab === 'overview'">
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

                    <!-- Usage Statistics (Hosting) -->
                    <Card v-if="isHosting" title="Usage Statistics">
                        <div class="space-y-4">
                            <div v-for="stat in usageStats" :key="stat.label">
                                <div class="flex justify-between text-[13px] mb-1.5">
                                    <span class="font-medium text-gray-700">{{ stat.label }}</span>
                                    <span class="text-gray-500">
                                        {{ formatUsage(stat.used, stat.unit) }} / {{ stat.limit > 0 ? formatUsage(stat.limit, stat.unit) : 'Unlimited' }}
                                    </span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2.5">
                                    <div class="h-2.5 rounded-full transition-all duration-500"
                                        :class="usagePercent(stat.used, stat.limit) > 90 ? 'bg-red-500' : usagePercent(stat.used, stat.limit) > 70 ? 'bg-amber-500' : 'bg-indigo-500'"
                                        :style="{ width: (stat.limit > 0 ? usagePercent(stat.used, stat.limit) : 0) + '%' }">
                                    </div>
                                </div>
                            </div>
                            <p v-if="usageStats.every(u => u.used === 0 && u.limit === 0)" class="text-[13px] text-gray-400 text-center py-2">
                                Usage statistics are updated periodically. Data may not be available immediately.
                            </p>
                        </div>
                    </Card>

                    <!-- Config Options (inline on overview if no management tab) -->
                    <Card v-if="hasConfig && !isHosting && !isVps" title="Configuration Options">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                            <div v-for="opt in configOptions" :key="opt.id || opt.option">
                                <dt class="text-[12px] font-medium text-gray-500">{{ opt.option || opt.optionname }}</dt>
                                <dd class="text-[14px] text-gray-900 mt-0.5">{{ opt.value }}</dd>
                            </div>
                        </dl>
                    </Card>

                    <!-- Custom Fields -->
                    <Card v-if="hasCustomFields" title="Additional Details">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                            <div v-for="cf in customFields" :key="cf.id">
                                <dt class="text-[12px] font-medium text-gray-500">{{ cf.name }}</dt>
                                <dd class="text-[14px] text-gray-900 mt-0.5">{{ cf.value }}</dd>
                            </div>
                        </dl>
                    </Card>
                </template>

                <!-- ─── Management Tab (Hosting) ─── -->
                <template v-if="activeTab === 'management' && isHosting">
                    <Card title="Quick Access" description="Login to your hosting control panel to manage your website.">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <a v-if="panelLoginUrl" :href="panelLoginUrl" target="_blank"
                                class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50/60 transition-all group">
                                <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[13px] font-semibold text-gray-900 group-hover:text-indigo-700">Login to {{ moduleName }}</p>
                                    <p class="text-[11px] text-gray-500">Manage files, emails, databases &amp; more</p>
                                </div>
                                <svg class="w-4 h-4 text-gray-300 ml-auto flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                            </a>
                            <a v-if="webmailUrl" :href="webmailUrl" target="_blank" rel="noopener"
                                class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 hover:border-emerald-300 hover:bg-emerald-50/60 transition-all group">
                                <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[13px] font-semibold text-gray-900 group-hover:text-emerald-700">Login to Webmail</p>
                                    <p class="text-[11px] text-gray-500">Access your email inbox</p>
                                </div>
                                <svg class="w-4 h-4 text-gray-300 ml-auto flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                            </a>
                        </div>
                    </Card>

                    <!-- Management Tools Grid (Hosting) -->
                    <Card title="Management Tools" description="Common hosting management tasks via your control panel.">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <a :href="ssoUrl('file/filemanager')" target="_blank"
                                class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">File Manager</span>
                            </a>
                            <a :href="ssoUrl('email/emailaccounts')" target="_blank"
                                class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group">
                                <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">Email Accounts</span>
                            </a>
                            <a :href="ssoUrl('database/mysqldatabases')" target="_blank"
                                class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group">
                                <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">MySQL Databases</span>
                            </a>
                            <a :href="ssoUrl('tool/sslcertificates')" target="_blank"
                                class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group">
                                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">Security / SSL</span>
                            </a>
                            <a :href="ssoUrl('domain/addondomains')" target="_blank"
                                class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group">
                                <div class="w-10 h-10 rounded-lg bg-cyan-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">Domains</span>
                            </a>
                            <a :href="ssoUrl('domain/dnseditor')" target="_blank"
                                class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group">
                                <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">DNS Editor</span>
                            </a>
                        </div>
                        <p class="mt-3 text-[11px] text-gray-400 text-center">All tools open in {{ moduleName }} via SSO login.</p>
                    </Card>

                    <Card v-if="hasConfig" title="Configuration Options">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                            <div v-for="opt in configOptions" :key="opt.id || opt.option">
                                <dt class="text-[12px] font-medium text-gray-500">{{ opt.option || opt.optionname }}</dt>
                                <dd class="text-[14px] text-gray-900 mt-0.5">{{ opt.value }}</dd>
                            </div>
                        </dl>
                    </Card>
                </template>

                <!-- ─── Management Tab (VPS) ─── -->
                <template v-if="activeTab === 'management' && isVps">
                    <!-- VPS Panel Access -->
                    <Card title="Panel Access" description="Access your VPS control panel to manage your virtual server.">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <a v-if="panelLoginUrl" :href="panelLoginUrl" target="_blank"
                                class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50/60 transition-all group">
                                <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" /></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[13px] font-semibold text-gray-900 group-hover:text-indigo-700">Login to {{ moduleName }}</p>
                                    <p class="text-[11px] text-gray-500">Manage your VPS, OS, resources &amp; more</p>
                                </div>
                                <svg class="w-4 h-4 text-gray-300 ml-auto flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                            </a>

                            <button @click="confirmAction('vnc')"
                                :disabled="actionLoading === 'vnc'"
                                class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 hover:border-violet-300 hover:bg-violet-50/60 transition-all group text-left disabled:opacity-50 disabled:cursor-wait">
                                <div class="w-10 h-10 rounded-lg bg-violet-100 flex items-center justify-center flex-shrink-0">
                                    <svg v-if="actionLoading !== 'vnc'" class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                    <svg v-else class="w-5 h-5 text-violet-600 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[13px] font-semibold text-gray-900 group-hover:text-violet-700">VNC Console</p>
                                    <p class="text-[11px] text-gray-500">Direct console access to your VPS</p>
                                </div>
                            </button>
                        </div>
                    </Card>

                    <!-- VPS Power Actions -->
                    <Card title="Power Actions" description="Control your VPS power state.">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <!-- Boot -->
                            <button @click="confirmAction('boot')"
                                :disabled="actionLoading === 'boot'"
                                class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-emerald-50 hover:border-emerald-200 hover:shadow-sm transition-all group disabled:opacity-50 disabled:cursor-wait">
                                <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                                    <svg v-if="actionLoading !== 'boot'" class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" /></svg>
                                    <svg v-else class="w-5 h-5 text-emerald-600 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-emerald-700 text-center">Boot</span>
                            </button>

                            <!-- Reboot -->
                            <button @click="confirmAction('reboot')"
                                :disabled="actionLoading === 'reboot'"
                                class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-amber-50 hover:border-amber-200 hover:shadow-sm transition-all group disabled:opacity-50 disabled:cursor-wait">
                                <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center">
                                    <svg v-if="actionLoading !== 'reboot'" class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                    <svg v-else class="w-5 h-5 text-amber-600 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-amber-700 text-center">Reboot</span>
                            </button>

                            <!-- Shutdown -->
                            <button @click="confirmAction('shutdown')"
                                :disabled="actionLoading === 'shutdown'"
                                class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-red-50 hover:border-red-200 hover:shadow-sm transition-all group disabled:opacity-50 disabled:cursor-wait">
                                <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
                                    <svg v-if="actionLoading !== 'shutdown'" class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                    <svg v-else class="w-5 h-5 text-red-600 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-red-700 text-center">Shutdown</span>
                            </button>

                            <!-- Reset Password -->
                            <button @click="confirmAction('resetpassword')"
                                :disabled="actionLoading === 'resetpassword'"
                                class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-blue-50 hover:border-blue-200 hover:shadow-sm transition-all group disabled:opacity-50 disabled:cursor-wait">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <svg v-if="actionLoading !== 'resetpassword'" class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>
                                    <svg v-else class="w-5 h-5 text-blue-600 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-blue-700 text-center">Reset Password</span>
                            </button>
                        </div>
                    </Card>

                    <!-- Config Options on VPS Management tab -->
                    <Card v-if="hasConfig" title="Configuration Options">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                            <div v-for="opt in configOptions" :key="opt.id || opt.option">
                                <dt class="text-[12px] font-medium text-gray-500">{{ opt.option || opt.optionname }}</dt>
                                <dd class="text-[14px] text-gray-900 mt-0.5">{{ opt.value }}</dd>
                            </div>
                        </dl>
                    </Card>
                </template>

                <!-- ─── Configuration Tab ─── -->
                <template v-if="activeTab === 'config'">
                    <Card v-if="hasConfig" title="Configuration Options">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                            <div v-for="opt in configOptions" :key="opt.id || opt.option">
                                <dt class="text-[12px] font-medium text-gray-500">{{ opt.option || opt.optionname }}</dt>
                                <dd class="text-[14px] text-gray-900 mt-0.5">{{ opt.value }}</dd>
                            </div>
                        </dl>
                    </Card>
                    <Card v-if="hasCustomFields" title="Custom Fields">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                            <div v-for="cf in customFields" :key="cf.id">
                                <dt class="text-[12px] font-medium text-gray-500">{{ cf.name }}</dt>
                                <dd class="text-[14px] text-gray-900 mt-0.5">{{ cf.value }}</dd>
                            </div>
                        </dl>
                    </Card>
                </template>
            </div>

            <!-- ═══ SIDEBAR ═══ -->
            <div class="space-y-4">

                <!-- VPS Quick Actions (sidebar) -->
                <Card v-if="isVps && isActive" title="Quick Actions">
                    <div class="space-y-2">
                        <a v-if="panelLoginUrl" :href="panelLoginUrl" target="_blank"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" /></svg>
                            Open {{ moduleName }}
                            <svg class="w-3.5 h-3.5 ml-auto text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                        </a>

                        <button @click="confirmAction('boot')" :disabled="actionLoading === 'boot'"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-emerald-700 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors disabled:opacity-50">
                            <svg v-if="actionLoading !== 'boot'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" /></svg>
                            <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            Boot
                        </button>

                        <button @click="confirmAction('reboot')" :disabled="actionLoading === 'reboot'"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-amber-700 bg-amber-50 rounded-lg hover:bg-amber-100 transition-colors disabled:opacity-50">
                            <svg v-if="actionLoading !== 'reboot'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                            <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            Reboot
                        </button>

                        <button @click="confirmAction('shutdown')" :disabled="actionLoading === 'shutdown'"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-red-700 bg-red-50 rounded-lg hover:bg-red-100 transition-colors disabled:opacity-50">
                            <svg v-if="actionLoading !== 'shutdown'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                            <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            Shutdown
                        </button>
                    </div>
                </Card>

                <!-- Hosting Actions (sidebar) -->
                <Card v-if="isHosting" title="Actions">
                    <div class="space-y-2">
                        <a v-if="isActive && panelLoginUrl" :href="panelLoginUrl" target="_blank"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg>
                            Login to {{ moduleName }}
                        </a>
                        <a v-if="isActive && webmailUrl" :href="webmailUrl" target="_blank" rel="noopener"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-emerald-700 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            Login to Webmail
                        </a>
                    </div>
                </Card>

                <!-- Manage Card (shared) -->
                <Card title="Manage">
                    <div class="space-y-2">
                        <button v-if="isActive" @click="showPasswordModal = true" :disabled="passwordLoading"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors disabled:opacity-50">
                            <svg v-if="!passwordLoading" class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>
                            <svg v-else class="w-4 h-4 text-gray-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            Change Password
                        </button>

                        <a v-if="isActive" :href="'https://dash.orcustech.com/upgrade.php?type=package&id=' + s.id" target="_blank" rel="noopener"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 11l5-5m0 0l5 5m-5-5v12" /></svg>
                            Upgrade / Downgrade
                        </a>

                        <hr class="my-1.5 border-gray-100" />

                        <button @click="showCancelModal = true"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                            Request Cancellation
                        </button>
                    </div>
                </Card>

                <!-- Server Info -->
                <Card v-if="(isHosting || isVps) && (s.servername || s.serverhostname || s.serverip || s.dedicatedip || s.username)" title="Server Information">
                    <dl class="space-y-3">
                        <div v-if="s.servername">
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Server</dt>
                            <dd class="text-[13px] text-gray-900 mt-0.5">{{ s.servername }}</dd>
                        </div>
                        <div v-if="s.serverhostname">
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Hostname</dt>
                            <dd class="text-[13px] text-gray-900 mt-0.5 font-mono text-[12px]">{{ s.serverhostname }}</dd>
                        </div>
                        <div v-if="s.serverip">
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Server IP</dt>
                            <dd class="text-[13px] text-gray-900 mt-0.5 font-mono text-[12px]">{{ s.serverip }}</dd>
                        </div>
                        <div v-if="s.dedicatedip">
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Dedicated IP</dt>
                            <dd class="text-[13px] text-gray-900 mt-0.5 font-mono text-[12px]">{{ s.dedicatedip }}</dd>
                        </div>
                        <div v-if="s.assignedips">
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Assigned IPs</dt>
                            <dd class="text-[13px] text-gray-900 mt-0.5 font-mono text-[12px]">{{ s.assignedips }}</dd>
                        </div>
                        <div v-if="s.username">
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Username</dt>
                            <dd class="text-[13px] text-gray-900 mt-0.5 font-mono text-[12px]">{{ s.username }}</dd>
                        </div>
                    </dl>
                </Card>

                <!-- Billing Summary -->
                <Card title="Billing">
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Amount</dt>
                            <dd class="text-[16px] font-semibold text-gray-900 mt-0.5">{{ formatCurrency(s.recurringamount) }}<span class="text-[12px] text-gray-500 font-normal"> / {{ s.billingcycle }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Payment Method</dt>
                            <dd class="text-[13px] text-gray-900 mt-0.5">{{ s.paymentmethodname || s.paymentmethod || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Next Due Date</dt>
                            <dd class="text-[13px] text-gray-900 mt-0.5">{{ s.nextduedate && s.nextduedate !== '0000-00-00' ? s.nextduedate : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">First Payment</dt>
                            <dd class="text-[13px] text-gray-900 mt-0.5">{{ formatCurrency(s.firstpaymentamount) }}</dd>
                        </div>
                    </dl>
                </Card>
            </div>
        </div>

        <!-- ═══ Action Confirmation Modal ═══ -->
        <ConfirmModal
            :show="showActionModal"
            :title="pendingAction ? actionLabels[pendingAction] : 'Confirm Action'"
            :message="pendingAction ? actionDescriptions[pendingAction] : 'Are you sure?'"
            :confirm-text="pendingAction ? actionLabels[pendingAction] : 'Confirm'"
            @confirm="executeAction"
            @close="showActionModal = false; pendingAction = null"
        />

        <!-- ═══ Change Password Confirmation Modal ═══ -->
        <ConfirmModal
            :show="showPasswordModal"
            title="Change Password"
            message="This will reset the password for this service. The new password will be emailed to you."
            confirm-text="Reset Password"
            @confirm="doChangePassword"
            @close="showPasswordModal = false"
        />

        <!-- ═══ Cancel Modal ═══ -->
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
