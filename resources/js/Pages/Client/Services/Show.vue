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

// SSO login URL — use route if SSO is supported, otherwise fall back to direct panel URL
const panelLoginUrl = computed(() => {
    if (props.ssoSupported) return route('client.services.sso', s.id);
    return props.controlPanelUrl;
});
const panelLoginIsSSO = props.ssoSupported;

// SSO deep link — append redirect param for specific panel pages (SPanel: "category/page" format)
function ssoUrl(redirect) {
    if (props.ssoSupported && redirect) {
        return route('client.services.sso', s.id) + '?redirect=' + encodeURIComponent(redirect);
    }
    return panelLoginUrl.value;
}

// Module display name
const moduleName = computed(() => {
    const names = { spanel: 'HostPanel', cpanel: 'cPanel', plesk: 'Plesk', directadmin: 'DirectAdmin', virtualizor: 'Virtualizor', proxmox: 'Proxmox', solusvm: 'SolusVM' };
    return names[props.serverModule] || props.serverModule || 'Control Panel';
});

// ─── Tabs ──────────────────────────────────────────────────
const tabs = computed(() => {
    const t = [{ id: 'overview', label: 'Overview' }];
    if (isHosting) t.push({ id: 'management', label: 'Management' });
    if (hasConfig.value || hasCustomFields.value) t.push({ id: 'config', label: 'Configuration' });
    return t;
});
const activeTab = ref('overview');

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

// ─── Actions ───────────────────────────────────────────────
function changePassword() {
    if (!confirm('Are you sure you want to reset the password?')) return;
    router.post(route('client.services.changePassword', s.id));
}

function doAction(action) {
    if (!confirm(`Execute "${action}" on this service?`)) return;
    router.post(route('client.services.action', s.id), { action });
}

const vpsActions = ['reboot', 'shutdown', 'boot'];
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
        <div v-if="flash.success" class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-lg text-[13px] text-emerald-700">{{ flash.success }}</div>
        <div v-if="flash.error || $page.props.errors?.whmcs" class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-[13px] text-red-700">{{ flash.error || $page.props.errors.whmcs }}</div>

        <!-- Login Buttons Bar (hosting, active) -->
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
                    <!-- Service Details Card -->
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
                    <Card v-if="hasConfig && !isHosting" title="Configuration Options">
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
                    <!-- Quick Access -->
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

                    <!-- Management Tools Grid -->
                    <Card title="Management Tools" description="Common hosting management tasks via your control panel.">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <!-- File Manager -->
                            <a :href="ssoUrl('file/manager')" target="_blank"
                                class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">File Manager</span>
                            </a>

                            <!-- Email Accounts -->
                            <a :href="ssoUrl('email/accounts')" target="_blank"
                                class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group">
                                <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">Email Accounts</span>
                            </a>

                            <!-- MySQL Databases -->
                            <a :href="ssoUrl('database/databases')" target="_blank"
                                class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group">
                                <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">MySQL Databases</span>
                            </a>

                            <!-- Security / SSL -->
                            <a :href="ssoUrl('tool/ssl')" target="_blank"
                                class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group">
                                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">Security / SSL</span>
                            </a>

                            <!-- Domains -->
                            <a :href="ssoUrl('domain/domains')" target="_blank"
                                class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group">
                                <div class="w-10 h-10 rounded-lg bg-cyan-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">Domains</span>
                            </a>

                            <!-- DNS Editor -->
                            <a :href="ssoUrl('domain/dns')" target="_blank"
                                class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group">
                                <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">DNS Editor</span>
                            </a>
                        </div>
                        <p class="mt-3 text-[11px] text-gray-400 text-center">All tools open in {{ moduleName }} via SSO login.</p>
                    </Card>

                    <!-- Config Options on Management tab -->
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

                <!-- Actions Card -->
                <Card title="Actions">
                    <div class="space-y-2">
                        <!-- SSO Login shortcuts (hosting) -->
                        <a v-if="isHosting && isActive && panelLoginUrl" :href="panelLoginUrl" target="_blank"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg>
                            Login to {{ moduleName }}
                        </a>

                        <a v-if="isHosting && isActive && webmailUrl" :href="webmailUrl" target="_blank" rel="noopener"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-emerald-700 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            Login to Webmail
                        </a>

                        <!-- VPS Actions -->
                        <template v-if="isVps && isActive">
                            <button v-for="action in vpsActions" :key="action" @click="doAction(action)"
                                class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors capitalize">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a5 5 0 010-7.07m7.072 0a5 5 0 010 7.07M13 12a1 1 0 11-2 0 1 1 0 012 0z" />
                                </svg>
                                {{ action }}
                            </button>
                        </template>

                        <hr v-if="isActive" class="my-1.5 border-gray-100" />

                        <!-- Change Password -->
                        <button v-if="isActive" @click="changePassword"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>
                            Change Password
                        </button>

                        <!-- Upgrade/Downgrade -->
                        <a v-if="isActive" :href="'https://dash.orcustech.com/upgrade.php?type=package&id=' + s.id" target="_blank" rel="noopener"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 11l5-5m0 0l5 5m-5-5v12" /></svg>
                            Upgrade / Downgrade
                        </a>

                        <hr class="my-1.5 border-gray-100" />

                        <!-- Request Cancellation -->
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
