<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
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
    vpsStats: { type: Object, default: null },
    vpsStatsError: { type: String, default: null },
});

const s = props.service;
const isHosting = props.serviceType === 'hosting';
const isVps = props.serviceType === 'vps';
const isActive = s.status === 'Active';
const vps = computed(() => props.vpsStats);

// SSO login URL
const panelLoginUrl = computed(() => {
    if (props.ssoSupported) return route('client.services.sso', s.id);
    return props.controlPanelUrl;
});

// SSO deep link
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

// Tabs
const tabs = computed(() => {
    const t = [{ id: 'overview', label: 'Overview' }];
    if (isHosting || isVps) t.push({ id: 'management', label: 'Management' });
    if (hasConfig.value || hasCustomFields.value) t.push({ id: 'config', label: 'Configuration' });
    return t;
});
const activeTab = ref(isVps ? 'management' : 'overview');

// Details
const details = computed(() => {
    const d = [
        { label: 'Product / Service', value: s.name || s.groupname },
        { label: 'Domain', value: s.domain || '\u2014' },
        { label: 'Status', value: s.status, badge: true },
        { label: 'Billing Cycle', value: s.billingcycle },
        { label: 'Amount', value: formatCurrency(s.recurringamount) + ' / ' + s.billingcycle },
        { label: 'Payment Method', value: s.paymentmethodname || s.paymentmethod || '\u2014' },
        { label: 'Next Due Date', value: s.nextduedate && s.nextduedate !== '0000-00-00' ? s.nextduedate : '\u2014' },
        { label: 'Registration Date', value: s.regdate || '\u2014' },
    ];
    if (s.dedicatedip) d.push({ label: 'Dedicated IP', value: s.dedicatedip });
    if (s.assignedips) d.push({ label: 'Assigned IPs', value: s.assignedips });
    if (isHosting) {
        d.push({ label: 'Username', value: s.username || '\u2014' });
        d.push({ label: 'Server', value: s.servername || '\u2014' });
    }
    if (isVps) {
        d.push({ label: 'Server', value: s.servername || '\u2014' });
    }
    return d;
});

// Config Options
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

// Usage Statistics (hosting)
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

// VPS Stats Helpers
const vpsStatus = computed(() => vps.value?.status || 'unknown');

const vpsStatusColor = computed(() => {
    const colors = { online: 'bg-emerald-500', offline: 'bg-red-500', suspended: 'bg-amber-500' };
    return colors[vpsStatus.value] || 'bg-gray-400';
});

const vpsStatusLabel = computed(() => {
    const labels = { online: 'Online', offline: 'Offline', suspended: 'Suspended' };
    return labels[vpsStatus.value] || 'Unknown';
});

function vpsPercent(used, total) {
    if (!total || total === 0) return 0;
    return Math.min(100, Math.round((used / total) * 100));
}

function formatDisk(gb) {
    if (gb >= 1024) return (gb / 1024).toFixed(1) + ' TB';
    if (gb >= 1) return gb.toFixed(2) + ' GB';
    return (gb * 1024).toFixed(0) + ' MB';
}

function formatRam(mb) {
    if (mb >= 1024) return (mb / 1024).toFixed(1) + ' GB';
    return mb + ' MB';
}

function formatBandwidth(gb) {
    if (!gb || gb === 0) return '0 GB';
    if (gb >= 1024) return (gb / 1024).toFixed(2) + ' TB';
    return gb.toFixed(2) + ' GB';
}

function progressColor(pct) {
    if (pct > 90) return 'bg-red-500';
    if (pct > 70) return 'bg-amber-500';
    return 'bg-indigo-500';
}

function progressRingColor(pct) {
    if (pct > 90) return 'text-red-500';
    if (pct > 70) return 'text-amber-500';
    return 'text-indigo-500';
}

const osIcon = computed(() => {
    if (!vps.value) return '\uD83D\uDDA5\uFE0F';
    const distro = (vps.value.os_distro || vps.value.os_name || '').toLowerCase();
    if (distro.includes('ubuntu')) return '\uD83D\uDFE0';
    if (distro.includes('centos')) return '\uD83D\uDFE3';
    if (distro.includes('debian')) return '\uD83D\uDD34';
    if (distro.includes('almalinux') || distro.includes('alma')) return '\uD83D\uDD35';
    if (distro.includes('rocky')) return '\uD83D\uDFE2';
    if (distro.includes('windows')) return '\uD83E\uDE9F';
    return '\uD83D\uDC27';
});

// Cancel Modal
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

// Action Confirmation Modal
const showActionModal = ref(false);
const pendingAction = ref(null);
const actionLoading = ref(null);

const actionLabels = {
    boot: 'Boot', reboot: 'Reboot', shutdown: 'Shutdown',
    vnc: 'Open VNC Console', console: 'Open Console',
};

const actionDescriptions = {
    boot: 'This will start your VPS if it is currently powered off.',
    reboot: 'This will restart your VPS. All running processes will be temporarily interrupted.',
    shutdown: 'This will power off your VPS. You will need to boot it again to bring it back online.',
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

    // For VNC/Console: open a blank window BEFORE the async request
    // to avoid popup blocker (must be in direct user click handler)
    let vncWindow = null;
    if (action === 'vnc' || action === 'console') {
        vncWindow = window.open('about:blank', '_blank');
    }

    router.post(route('client.services.action', s.id), { action }, {
        preserveScroll: true,
        onSuccess: (page) => {
            const redirectUrl = page.props?.flash?.redirect_url;
            if (redirectUrl && vncWindow) {
                vncWindow.location.href = redirectUrl;
            } else if (vncWindow) {
                // No redirect URL returned — close the blank tab
                vncWindow.close();
            }
        },
        onError: () => {
            // Close blank tab on error
            if (vncWindow) vncWindow.close();
        },
        onFinish: () => {
            actionLoading.value = null;
            pendingAction.value = null;
        },
    });
}

// Password Change
const showPasswordModal = ref(false);
const passwordLoading = ref(false);
const showNewPasswordModal = ref(false);
const newPassword = ref('');
const passwordCopied = ref(false);

function doChangePassword() {
    showPasswordModal.value = false;
    passwordLoading.value = true;
    router.post(route('client.services.changePassword', s.id), {}, {
        preserveScroll: true,
        onSuccess: (page) => {
            const pass = page.props?.flash?.new_password;
            if (pass) {
                newPassword.value = pass;
                passwordCopied.value = false;
                showNewPasswordModal.value = true;
            }
        },
        onFinish: () => { passwordLoading.value = false; },
    });
}

function copyPassword() {
    navigator.clipboard.writeText(newPassword.value).then(() => {
        passwordCopied.value = true;
        setTimeout(() => { passwordCopied.value = false; }, 3000);
    });
}

// Auto-refresh VPS stats
let refreshInterval = null;
onMounted(() => {
    if (isVps && isActive) {
        refreshInterval = setInterval(() => {
            router.reload({ only: ['vpsStats'], preserveScroll: true });
        }, 30000);
    }
});
onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval);
});

// ── OS Reinstall / Rebuild ──────────────────────────────────
const showReinstallModal = ref(false);
const reinstallLoading = ref(false);
const templatesLoading = ref(false);
const osTemplates = ref([]);
const selectedOsId = ref('');
const reinstallPassword = ref('');
const reinstallPasswordConfirm = ref('');
const reinstallError = ref('');
const reinstallConfirmText = ref('');

const groupedTemplates = computed(() => {
    const groups = {};
    for (const tpl of osTemplates.value) {
        const group = tpl.group || 'Other';
        if (!groups[group]) groups[group] = [];
        groups[group].push(tpl);
    }
    // Sort groups: Linux first, then alphabetical
    const sorted = {};
    const keys = Object.keys(groups).sort((a, b) => {
        if (a === 'Linux') return -1;
        if (b === 'Linux') return 1;
        return a.localeCompare(b);
    });
    for (const k of keys) {
        sorted[k] = groups[k].sort((a, b) => a.name.localeCompare(b.name));
    }
    return sorted;
});

async function openReinstallModal() {
    showReinstallModal.value = true;
    reinstallError.value = '';
    selectedOsId.value = '';
    reinstallPassword.value = '';
    reinstallPasswordConfirm.value = '';
    reinstallConfirmText.value = '';

    if (osTemplates.value.length === 0) {
        templatesLoading.value = true;
        try {
            const response = await fetch(route('client.services.osTemplates', s.id), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await response.json();
            if (data.templates && data.templates.length > 0) {
                osTemplates.value = data.templates;
            } else {
                reinstallError.value = data.error || 'No OS templates available for this VPS.';
            }
        } catch (err) {
            reinstallError.value = 'Failed to load OS templates.';
        } finally {
            templatesLoading.value = false;
        }
    }
}

const canSubmitReinstall = computed(() => {
    return selectedOsId.value
        && reinstallPassword.value.length >= 6
        && reinstallPassword.value === reinstallPasswordConfirm.value
        && reinstallConfirmText.value === 'REINSTALL'
        && !reinstallLoading.value;
});

function executeReinstall() {
    if (!canSubmitReinstall.value) return;
    reinstallLoading.value = true;
    reinstallError.value = '';

    router.post(route('client.services.rebuild', s.id), {
        osid: selectedOsId.value,
        newpass: reinstallPassword.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showReinstallModal.value = false;
            reinstallPassword.value = '';
            reinstallPasswordConfirm.value = '';
            reinstallConfirmText.value = '';
        },
        onError: (errors) => {
            reinstallError.value = errors.whmcs || 'Rebuild failed.';
        },
        onFinish: () => {
            reinstallLoading.value = false;
        },
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

        <!-- VPS Stats Error -->
        <div v-if="isVps && isActive && props.vpsStatsError && !vps" class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-[13px] text-amber-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
            <span><strong>VPS Stats Unavailable:</strong> {{ props.vpsStatsError }}</span>
        </div>

        <!-- VPS DASHBOARD HERO -->
        <div v-if="isVps && isActive" class="mb-6">
            <!-- VPS Info Header -->
            <div class="rounded-t-xl bg-gradient-to-r from-slate-800 via-slate-800 to-indigo-900 p-5 text-white">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <div class="w-14 h-14 rounded-xl bg-white/10 backdrop-blur flex items-center justify-center text-2xl">{{ osIcon }}</div>
                            <span :class="[vpsStatusColor, 'absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full border-2 border-slate-800']" :title="vpsStatusLabel"></span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2.5">
                                <h2 class="text-base font-semibold">{{ vps?.hostname || s.name || s.groupname }}</h2>
                                <span :class="[vpsStatus === 'online' ? 'bg-emerald-500/20 text-emerald-300' : vpsStatus === 'offline' ? 'bg-red-500/20 text-red-300' : 'bg-amber-500/20 text-amber-300', 'text-[11px] px-2 py-0.5 rounded-full font-medium']">{{ vpsStatusLabel }}</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5 text-[13px] text-slate-300">
                                <span v-if="vps?.os_name" class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg>
                                    {{ vps.os_name }}
                                </span>
                                <span v-if="vps?.ips?.length" class="flex items-center gap-1.5 font-mono text-[12px]">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9" /></svg>
                                    {{ vps.ips[0] }}<span v-if="vps.ips.length > 1" class="text-slate-500 ml-1">+{{ vps.ips.length - 1 }}</span>
                                </span>
                                <span v-else-if="s.dedicatedip" class="flex items-center gap-1.5 font-mono text-[12px]">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9" /></svg>
                                    {{ s.dedicatedip }}
                                </span>
                                <span v-if="vps?.server_name" class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2" /></svg>
                                    {{ vps.server_name }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <a v-if="panelLoginUrl" :href="panelLoginUrl" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-500 hover:bg-indigo-400 text-white text-[13px] font-semibold rounded-lg transition-colors shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                            {{ moduleName }}
                        </a>
                        <button @click="confirmAction('vnc')" :disabled="actionLoading === 'vnc'" class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 text-white text-[13px] font-semibold rounded-lg transition-colors backdrop-blur">
                            <svg v-if="actionLoading !== 'vnc'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            VNC
                        </button>
                    </div>
                </div>
            </div>

            <!-- Resource Usage Rings -->
            <div v-if="vps" class="grid grid-cols-2 lg:grid-cols-4 gap-0 border-x border-b border-gray-200 rounded-b-xl bg-white overflow-hidden divide-x divide-gray-100">
                <!-- CPU -->
                <div class="p-4 text-center">
                    <div class="relative w-20 h-20 mx-auto mb-2">
                        <svg class="w-20 h-20 -rotate-90" viewBox="0 0 80 80">
                            <circle cx="40" cy="40" r="34" fill="none" stroke-width="6" class="text-gray-100" stroke="currentColor" />
                            <circle cx="40" cy="40" r="34" fill="none" stroke-width="6" :class="progressRingColor(vps.cpu_used)" stroke="currentColor" stroke-linecap="round" :stroke-dasharray="2 * Math.PI * 34" :stroke-dashoffset="2 * Math.PI * 34 * (1 - Math.min(vps.cpu_used, 100) / 100)" />
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-[15px] font-bold text-gray-900">{{ vps.cpu_used.toFixed(1) }}%</span>
                        </div>
                    </div>
                    <p class="text-[12px] font-semibold text-gray-700">CPU</p>
                    <p class="text-[11px] text-gray-400">{{ vps.cpu_cores }} Core{{ vps.cpu_cores !== 1 ? 's' : '' }}</p>
                </div>
                <!-- RAM -->
                <div class="p-4 text-center">
                    <div class="relative w-20 h-20 mx-auto mb-2">
                        <svg class="w-20 h-20 -rotate-90" viewBox="0 0 80 80">
                            <circle cx="40" cy="40" r="34" fill="none" stroke-width="6" class="text-gray-100" stroke="currentColor" />
                            <circle cx="40" cy="40" r="34" fill="none" stroke-width="6" :class="progressRingColor(vpsPercent(vps.ram_used, vps.ram_total))" stroke="currentColor" stroke-linecap="round" :stroke-dasharray="2 * Math.PI * 34" :stroke-dashoffset="2 * Math.PI * 34 * (1 - vpsPercent(vps.ram_used, vps.ram_total) / 100)" />
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-[15px] font-bold text-gray-900">{{ vpsPercent(vps.ram_used, vps.ram_total) }}%</span>
                        </div>
                    </div>
                    <p class="text-[12px] font-semibold text-gray-700">RAM</p>
                    <p class="text-[11px] text-gray-400">{{ formatRam(vps.ram_used) }} / {{ formatRam(vps.ram_total) }}</p>
                </div>
                <!-- Disk -->
                <div class="p-4 text-center">
                    <div class="relative w-20 h-20 mx-auto mb-2">
                        <svg class="w-20 h-20 -rotate-90" viewBox="0 0 80 80">
                            <circle cx="40" cy="40" r="34" fill="none" stroke-width="6" class="text-gray-100" stroke="currentColor" />
                            <circle cx="40" cy="40" r="34" fill="none" stroke-width="6" :class="progressRingColor(vpsPercent(vps.disk_used, vps.disk_total))" stroke="currentColor" stroke-linecap="round" :stroke-dasharray="2 * Math.PI * 34" :stroke-dashoffset="2 * Math.PI * 34 * (1 - vpsPercent(vps.disk_used, vps.disk_total) / 100)" />
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-[15px] font-bold text-gray-900">{{ vpsPercent(vps.disk_used, vps.disk_total) }}%</span>
                        </div>
                    </div>
                    <p class="text-[12px] font-semibold text-gray-700">Disk</p>
                    <p class="text-[11px] text-gray-400">{{ formatDisk(vps.disk_used) }} / {{ formatDisk(vps.disk_total) }}</p>
                </div>
                <!-- Bandwidth -->
                <div class="p-4 text-center">
                    <div class="relative w-20 h-20 mx-auto mb-2">
                        <svg class="w-20 h-20 -rotate-90" viewBox="0 0 80 80">
                            <circle cx="40" cy="40" r="34" fill="none" stroke-width="6" class="text-gray-100" stroke="currentColor" />
                            <circle cx="40" cy="40" r="34" fill="none" stroke-width="6" :class="progressRingColor(vps.bandwidth_total > 0 ? vpsPercent(vps.bandwidth_used, vps.bandwidth_total) : 0)" stroke="currentColor" stroke-linecap="round" :stroke-dasharray="2 * Math.PI * 34" :stroke-dashoffset="2 * Math.PI * 34 * (1 - (vps.bandwidth_total > 0 ? vpsPercent(vps.bandwidth_used, vps.bandwidth_total) : 0) / 100)" />
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-[15px] font-bold text-gray-900">{{ vps.bandwidth_total > 0 ? vpsPercent(vps.bandwidth_used, vps.bandwidth_total) : 0 }}%</span>
                        </div>
                    </div>
                    <p class="text-[12px] font-semibold text-gray-700">Bandwidth</p>
                    <p class="text-[11px] text-gray-400">{{ formatBandwidth(vps.bandwidth_used) }} / {{ vps.bandwidth_total > 0 ? formatBandwidth(vps.bandwidth_total) : '\u221E' }}</p>
                </div>
            </div>
            <!-- Loading skeleton -->
            <div v-else class="grid grid-cols-2 lg:grid-cols-4 gap-0 border-x border-b border-gray-200 rounded-b-xl bg-white overflow-hidden divide-x divide-gray-100">
                <div v-for="i in 4" :key="i" class="p-4 text-center animate-pulse">
                    <div class="w-20 h-20 mx-auto mb-2 rounded-full bg-gray-100"></div>
                    <div class="h-3 bg-gray-100 rounded w-12 mx-auto mb-1"></div>
                    <div class="h-2.5 bg-gray-50 rounded w-20 mx-auto"></div>
                </div>
            </div>
        </div>

        <!-- Hosting Login Bar -->
        <div v-if="isHosting && isActive && panelLoginUrl" class="mb-6 flex flex-wrap gap-3">
            <a :href="panelLoginUrl" target="_blank" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white text-[13px] font-semibold rounded-lg hover:bg-indigo-700 shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg>
                Login to {{ moduleName }}
            </a>
            <a v-if="webmailUrl" :href="webmailUrl" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-gray-700 text-[13px] font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                Login to Webmail
            </a>
        </div>

        <!-- Tab Navigation -->
        <div v-if="tabs.length > 1" class="mb-6 border-b border-gray-200">
            <nav class="flex gap-6">
                <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id" :class="['pb-3 text-[13px] font-medium border-b-2 transition-colors', activeTab === tab.id ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700']">{{ tab.label }}</button>
            </nav>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- MAIN COLUMN -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Overview Tab -->
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
                    <Card v-if="isHosting" title="Usage Statistics">
                        <div class="space-y-4">
                            <div v-for="stat in usageStats" :key="stat.label">
                                <div class="flex justify-between text-[13px] mb-1.5">
                                    <span class="font-medium text-gray-700">{{ stat.label }}</span>
                                    <span class="text-gray-500">{{ formatUsage(stat.used, stat.unit) }} / {{ stat.limit > 0 ? formatUsage(stat.limit, stat.unit) : 'Unlimited' }}</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2.5">
                                    <div class="h-2.5 rounded-full transition-all duration-500" :class="usagePercent(stat.used, stat.limit) > 90 ? 'bg-red-500' : usagePercent(stat.used, stat.limit) > 70 ? 'bg-amber-500' : 'bg-indigo-500'" :style="{ width: (stat.limit > 0 ? usagePercent(stat.used, stat.limit) : 0) + '%' }"></div>
                                </div>
                            </div>
                            <p v-if="usageStats.every(u => u.used === 0 && u.limit === 0)" class="text-[13px] text-gray-400 text-center py-2">Usage statistics are updated periodically.</p>
                        </div>
                    </Card>
                    <Card v-if="hasConfig && !isHosting && !isVps" title="Configuration Options">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                            <div v-for="opt in configOptions" :key="opt.id || opt.option">
                                <dt class="text-[12px] font-medium text-gray-500">{{ opt.option || opt.optionname }}</dt>
                                <dd class="text-[14px] text-gray-900 mt-0.5">{{ opt.value }}</dd>
                            </div>
                        </dl>
                    </Card>
                    <Card v-if="hasCustomFields" title="Additional Details">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                            <div v-for="cf in customFields" :key="cf.id">
                                <dt class="text-[12px] font-medium text-gray-500">{{ cf.name }}</dt>
                                <dd class="text-[14px] text-gray-900 mt-0.5">{{ cf.value }}</dd>
                            </div>
                        </dl>
                    </Card>
                </template>

                <!-- Management Tab (Hosting) -->
                <template v-if="activeTab === 'management' && isHosting">
                    <Card title="Quick Access" description="Login to your hosting control panel to manage your website.">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <a v-if="panelLoginUrl" :href="panelLoginUrl" target="_blank" class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50/60 transition-all group">
                                <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0"><svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg></div>
                                <div class="min-w-0"><p class="text-[13px] font-semibold text-gray-900 group-hover:text-indigo-700">Login to {{ moduleName }}</p><p class="text-[11px] text-gray-500">Manage files, emails, databases and more</p></div>
                                <svg class="w-4 h-4 text-gray-300 ml-auto flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                            </a>
                            <a v-if="webmailUrl" :href="webmailUrl" target="_blank" rel="noopener" class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 hover:border-emerald-300 hover:bg-emerald-50/60 transition-all group">
                                <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0"><svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg></div>
                                <div class="min-w-0"><p class="text-[13px] font-semibold text-gray-900 group-hover:text-emerald-700">Login to Webmail</p><p class="text-[11px] text-gray-500">Access your email inbox</p></div>
                                <svg class="w-4 h-4 text-gray-300 ml-auto flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                            </a>
                        </div>
                    </Card>
                    <Card title="Management Tools" description="Common hosting management tasks via your control panel.">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <a :href="ssoUrl('file/filemanager')" target="_blank" class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group"><div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center"><svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg></div><span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">File Manager</span></a>
                            <a :href="ssoUrl('email/emailaccounts')" target="_blank" class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group"><div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center"><svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" /></svg></div><span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">Email Accounts</span></a>
                            <a :href="ssoUrl('database/mysqldatabases')" target="_blank" class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group"><div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center"><svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" /></svg></div><span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">MySQL Databases</span></a>
                            <a :href="ssoUrl('tool/sslcertificates')" target="_blank" class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group"><div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center"><svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg></div><span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">Security / SSL</span></a>
                            <a :href="ssoUrl('domain/addondomains')" target="_blank" class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group"><div class="w-10 h-10 rounded-lg bg-cyan-100 flex items-center justify-center"><svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg></div><span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">Domains</span></a>
                            <a :href="ssoUrl('domain/dnseditor')" target="_blank" class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-gray-200 hover:shadow-sm transition-all group"><div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center"><svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" /></svg></div><span class="text-[12px] font-medium text-gray-700 group-hover:text-gray-900 text-center">DNS Editor</span></a>
                        </div>
                        <p class="mt-3 text-[11px] text-gray-400 text-center">All tools open in {{ moduleName }} via SSO login.</p>
                    </Card>
                    <Card v-if="hasConfig" title="Configuration Options">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3"><div v-for="opt in configOptions" :key="opt.id || opt.option"><dt class="text-[12px] font-medium text-gray-500">{{ opt.option || opt.optionname }}</dt><dd class="text-[14px] text-gray-900 mt-0.5">{{ opt.value }}</dd></div></dl>
                    </Card>
                </template>

                <!-- Management Tab (VPS) -->
                <template v-if="activeTab === 'management' && isVps">
                    <!-- VPS Information -->
                    <Card v-if="vps" title="VPS Information" description="Live server details from Virtualizor.">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                            <div v-if="vps.os_name"><dt class="text-[12px] font-medium text-gray-500 uppercase tracking-wider">Operating System</dt><dd class="mt-1 text-[14px] text-gray-900 flex items-center gap-2"><span>{{ osIcon }}</span> {{ vps.os_name }}</dd></div>
                            <div><dt class="text-[12px] font-medium text-gray-500 uppercase tracking-wider">Hostname</dt><dd class="mt-1 text-[13px] text-gray-900 font-mono">{{ vps.hostname || s.domain || '\u2014' }}</dd></div>
                            <div><dt class="text-[12px] font-medium text-gray-500 uppercase tracking-wider">Status</dt><dd class="mt-1 flex items-center gap-2"><span :class="[vpsStatusColor, 'w-2.5 h-2.5 rounded-full inline-block']"></span><span class="text-[14px] text-gray-900 font-medium">{{ vpsStatusLabel }}</span></dd></div>
                            <div v-if="vps.ips?.length"><dt class="text-[12px] font-medium text-gray-500 uppercase tracking-wider">IP Address{{ vps.ips.length > 1 ? 'es' : '' }}</dt><dd class="mt-1 space-y-0.5"><span v-for="ip in vps.ips" :key="ip" class="block text-[13px] text-gray-900 font-mono">{{ ip }}</span></dd></div>
                            <div v-else-if="s.dedicatedip"><dt class="text-[12px] font-medium text-gray-500 uppercase tracking-wider">IP Address</dt><dd class="mt-1 text-[13px] text-gray-900 font-mono">{{ s.dedicatedip }}</dd></div>
                            <div v-if="vps.ips6?.length"><dt class="text-[12px] font-medium text-gray-500 uppercase tracking-wider">IPv6</dt><dd class="mt-1 space-y-0.5"><span v-for="ip in vps.ips6" :key="ip" class="block text-[12px] text-gray-900 font-mono break-all">{{ ip }}</span></dd></div>
                            <div v-if="vps.server_name"><dt class="text-[12px] font-medium text-gray-500 uppercase tracking-wider">Server</dt><dd class="mt-1 text-[14px] text-gray-900">{{ vps.server_name }}</dd></div>
                            <div v-if="vps.virt"><dt class="text-[12px] font-medium text-gray-500 uppercase tracking-wider">Virtualization</dt><dd class="mt-1 text-[14px] text-gray-900 uppercase">{{ vps.virt }}</dd></div>
                            <div v-if="vps.cpu_cores"><dt class="text-[12px] font-medium text-gray-500 uppercase tracking-wider">CPU Cores</dt><dd class="mt-1 text-[14px] text-gray-900">{{ vps.cpu_cores }}</dd></div>
                            <div v-if="vps.ram_total"><dt class="text-[12px] font-medium text-gray-500 uppercase tracking-wider">RAM</dt><dd class="mt-1 text-[14px] text-gray-900">{{ formatRam(vps.ram_total) }}</dd></div>
                            <div v-if="vps.disk_total"><dt class="text-[12px] font-medium text-gray-500 uppercase tracking-wider">Disk Space</dt><dd class="mt-1 text-[14px] text-gray-900">{{ formatDisk(vps.disk_total) }}</dd></div>
                        </dl>
                    </Card>

                    <!-- Resource Usage Bars -->
                    <Card v-if="vps" title="Resource Usage" description="Live resource consumption. Auto-refreshes every 30 seconds.">
                        <div class="space-y-5">
                            <div>
                                <div class="flex justify-between text-[13px] mb-1.5">
                                    <span class="font-medium text-gray-700 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg>
                                        CPU ({{ vps.cpu_cores }} Core{{ vps.cpu_cores !== 1 ? 's' : '' }})
                                    </span>
                                    <span class="text-gray-500 font-semibold">{{ vps.cpu_used.toFixed(1) }}%</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-3"><div class="h-3 rounded-full transition-all duration-700" :class="progressColor(vps.cpu_used)" :style="{ width: Math.min(vps.cpu_used, 100) + '%' }"></div></div>
                            </div>
                            <div>
                                <div class="flex justify-between text-[13px] mb-1.5">
                                    <span class="font-medium text-gray-700 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                        RAM
                                    </span>
                                    <span class="text-gray-500">{{ formatRam(vps.ram_used) }} / {{ formatRam(vps.ram_total) }}</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-3"><div class="h-3 rounded-full transition-all duration-700" :class="progressColor(vpsPercent(vps.ram_used, vps.ram_total))" :style="{ width: vpsPercent(vps.ram_used, vps.ram_total) + '%' }"></div></div>
                            </div>
                            <div>
                                <div class="flex justify-between text-[13px] mb-1.5">
                                    <span class="font-medium text-gray-700 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2 1.79 4 4 4h8c2.21 0 4-1.79 4-4V7M4 7c0-2 1.79-4 4-4h8c2.21 0 4 1.79 4 4M4 7h16M12 11v6m-3-3h6" /></svg>
                                        Disk
                                    </span>
                                    <span class="text-gray-500">{{ formatDisk(vps.disk_used) }} / {{ formatDisk(vps.disk_total) }}</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-3"><div class="h-3 rounded-full transition-all duration-700" :class="progressColor(vpsPercent(vps.disk_used, vps.disk_total))" :style="{ width: vpsPercent(vps.disk_used, vps.disk_total) + '%' }"></div></div>
                            </div>
                            <div>
                                <div class="flex justify-between text-[13px] mb-1.5">
                                    <span class="font-medium text-gray-700 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                                        Bandwidth
                                    </span>
                                    <span class="text-gray-500">{{ formatBandwidth(vps.bandwidth_used) }} / {{ vps.bandwidth_total > 0 ? formatBandwidth(vps.bandwidth_total) : 'Unlimited' }}</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-3"><div class="h-3 rounded-full transition-all duration-700" :class="progressColor(vps.bandwidth_total > 0 ? vpsPercent(vps.bandwidth_used, vps.bandwidth_total) : 0)" :style="{ width: (vps.bandwidth_total > 0 ? vpsPercent(vps.bandwidth_used, vps.bandwidth_total) : 0) + '%' }"></div></div>
                            </div>
                        </div>
                        <p class="mt-4 text-[11px] text-gray-400 text-center flex items-center justify-center gap-1">
                            <svg class="w-3.5 h-3.5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                            Auto-refreshing every 30 seconds
                        </p>
                    </Card>

                    <!-- VPS Power Actions -->
                    <Card title="Power Actions" description="Control your VPS power state.">
                        <div class="grid grid-cols-3 gap-3">
                            <button @click="confirmAction('boot')" :disabled="actionLoading === 'boot'" class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-emerald-50 hover:border-emerald-200 hover:shadow-sm transition-all group disabled:opacity-50 disabled:cursor-wait">
                                <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                                    <svg v-if="actionLoading !== 'boot'" class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" /></svg>
                                    <svg v-else class="w-5 h-5 text-emerald-600 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-emerald-700 text-center">Boot</span>
                            </button>
                            <button @click="confirmAction('reboot')" :disabled="actionLoading === 'reboot'" class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-amber-50 hover:border-amber-200 hover:shadow-sm transition-all group disabled:opacity-50 disabled:cursor-wait">
                                <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center">
                                    <svg v-if="actionLoading !== 'reboot'" class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                    <svg v-else class="w-5 h-5 text-amber-600 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-amber-700 text-center">Reboot</span>
                            </button>
                            <button @click="confirmAction('shutdown')" :disabled="actionLoading === 'shutdown'" class="flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-red-50 hover:border-red-200 hover:shadow-sm transition-all group disabled:opacity-50 disabled:cursor-wait">
                                <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
                                    <svg v-if="actionLoading !== 'shutdown'" class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                    <svg v-else class="w-5 h-5 text-red-600 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                </div>
                                <span class="text-[12px] font-medium text-gray-700 group-hover:text-red-700 text-center">Shutdown</span>
                            </button>
                        </div>
                    </Card>

                    <!-- Reinstall OS -->
                    <Card title="Reinstall OS" description="Reinstall your VPS with a different operating system. This will erase all data.">
                        <div class="flex items-start gap-4 p-4 rounded-xl border border-orange-200 bg-orange-50/50">
                            <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[13px] font-semibold text-gray-900">Rebuild / Reinstall OS</p>
                                <p class="text-[12px] text-gray-500 mt-0.5">Choose a new OS template and set a root password. All existing data will be permanently erased.</p>
                            </div>
                            <button @click="openReinstallModal" :disabled="reinstallLoading" class="inline-flex items-center gap-2 px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-[13px] font-semibold rounded-lg transition-colors shadow-sm flex-shrink-0 disabled:opacity-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                                Reinstall
                            </button>
                        </div>
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

                <!-- Configuration Tab -->
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

            <!-- SIDEBAR -->
            <div class="space-y-4">
                <Card v-if="isVps && isActive" title="Quick Actions">
                    <div class="space-y-2">
                        <button @click="confirmAction('boot')" :disabled="actionLoading === 'boot'" class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-emerald-700 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors disabled:opacity-50">
                            <svg v-if="actionLoading !== 'boot'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" /></svg>
                            <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            Boot
                        </button>
                        <button @click="confirmAction('reboot')" :disabled="actionLoading === 'reboot'" class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-amber-700 bg-amber-50 rounded-lg hover:bg-amber-100 transition-colors disabled:opacity-50">
                            <svg v-if="actionLoading !== 'reboot'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                            <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            Reboot
                        </button>
                        <button @click="confirmAction('shutdown')" :disabled="actionLoading === 'shutdown'" class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-red-700 bg-red-50 rounded-lg hover:bg-red-100 transition-colors disabled:opacity-50">
                            <svg v-if="actionLoading !== 'shutdown'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                            <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            Shutdown
                        </button>
                    </div>
                </Card>

                <Card v-if="isHosting" title="Actions">
                    <div class="space-y-2">
                        <a v-if="isActive && panelLoginUrl" :href="panelLoginUrl" target="_blank" class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg>
                            Login to {{ moduleName }}
                        </a>
                        <a v-if="isActive && webmailUrl" :href="webmailUrl" target="_blank" rel="noopener" class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-emerald-700 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            Login to Webmail
                        </a>
                    </div>
                </Card>

                <Card title="Manage">
                    <div class="space-y-2">
                        <button v-if="isActive" @click="showPasswordModal = true" :disabled="passwordLoading" class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors disabled:opacity-50">
                            <svg v-if="!passwordLoading" class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>
                            <svg v-else class="w-4 h-4 text-gray-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            Change Password
                        </button>
                        <a v-if="isActive" :href="'https://dash.orcustech.com/upgrade.php?type=package&id=' + s.id" target="_blank" rel="noopener" class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 11l5-5m0 0l5 5m-5-5v12" /></svg>
                            Upgrade / Downgrade
                        </a>
                        <hr class="my-1.5 border-gray-100" />
                        <button @click="showCancelModal = true" class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                            Request Cancellation
                        </button>
                    </div>
                </Card>

                <!-- VPS Server Details sidebar -->
                <Card v-if="isVps && isActive && vps" title="Server Details">
                    <dl class="space-y-3">
                        <div v-if="vps.hostname">
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Hostname</dt>
                            <dd class="text-[12px] text-gray-900 mt-0.5 font-mono">{{ vps.hostname }}</dd>
                        </div>
                        <div v-if="vps.ips?.length">
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">IP{{ vps.ips.length > 1 ? 's' : '' }}</dt>
                            <dd class="mt-0.5"><span v-for="ip in vps.ips" :key="ip" class="block text-[12px] text-gray-900 font-mono">{{ ip }}</span></dd>
                        </div>
                        <div v-else-if="s.dedicatedip">
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">IP</dt>
                            <dd class="text-[12px] text-gray-900 mt-0.5 font-mono">{{ s.dedicatedip }}</dd>
                        </div>
                        <div v-if="vps.os_name">
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">OS</dt>
                            <dd class="text-[13px] text-gray-900 mt-0.5">{{ vps.os_name }}</dd>
                        </div>
                        <div v-if="vps.server_name">
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Server</dt>
                            <dd class="text-[13px] text-gray-900 mt-0.5">{{ vps.server_name }}</dd>
                        </div>
                        <div v-if="vps.virt">
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Type</dt>
                            <dd class="text-[13px] text-gray-900 mt-0.5 uppercase">{{ vps.virt }}</dd>
                        </div>
                    </dl>
                </Card>

                <!-- Fallback server info -->
                <Card v-if="(!isVps || !vps) && (isHosting || isVps) && (s.servername || s.dedicatedip || s.username)" title="Server Information">
                    <dl class="space-y-3">
                        <div v-if="s.servername">
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Server</dt>
                            <dd class="text-[13px] text-gray-900 mt-0.5">{{ s.servername }}</dd>
                        </div>
                        <div v-if="s.serverhostname">
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Hostname</dt>
                            <dd class="text-[12px] text-gray-900 mt-0.5 font-mono">{{ s.serverhostname }}</dd>
                        </div>
                        <div v-if="s.dedicatedip">
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">IP</dt>
                            <dd class="text-[12px] text-gray-900 mt-0.5 font-mono">{{ s.dedicatedip }}</dd>
                        </div>
                        <div v-if="s.username">
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Username</dt>
                            <dd class="text-[12px] text-gray-900 mt-0.5 font-mono">{{ s.username }}</dd>
                        </div>
                    </dl>
                </Card>

                <Card title="Billing">
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Amount</dt>
                            <dd class="text-[16px] font-semibold text-gray-900 mt-0.5">{{ formatCurrency(s.recurringamount) }}<span class="text-[12px] text-gray-500 font-normal"> / {{ s.billingcycle }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Payment Method</dt>
                            <dd class="text-[13px] text-gray-900 mt-0.5">{{ s.paymentmethodname || s.paymentmethod || '\u2014' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">Next Due Date</dt>
                            <dd class="text-[13px] text-gray-900 mt-0.5">{{ s.nextduedate && s.nextduedate !== '0000-00-00' ? s.nextduedate : '\u2014' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium text-gray-500 uppercase">First Payment</dt>
                            <dd class="text-[13px] text-gray-900 mt-0.5">{{ formatCurrency(s.firstpaymentamount) }}</dd>
                        </div>
                    </dl>
                </Card>
            </div>
        </div>

        <!-- Modals -->
        <ConfirmModal :show="showActionModal" :title="pendingAction ? actionLabels[pendingAction] : 'Confirm Action'" :message="pendingAction ? actionDescriptions[pendingAction] : 'Are you sure?'" :confirm-text="pendingAction ? actionLabels[pendingAction] : 'Confirm'" @confirm="executeAction" @close="showActionModal = false; pendingAction = null" />

        <ConfirmModal :show="showPasswordModal" title="Change Root Password" message="This will generate a new random root password and restart your VPS to apply it. The VPS will be offline for about 30 seconds. The new password will be displayed on screen — make sure to copy it." confirm-text="Reset Password" @confirm="doChangePassword" @close="showPasswordModal = false" />

        <!-- New Password Display Modal -->
        <div v-if="showNewPasswordModal" class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20">
                <div class="fixed inset-0 bg-gray-500/75 transition-opacity" @click="showNewPasswordModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6 z-10">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Password Changed</h3>
                    </div>
                    <p class="text-[13px] text-gray-600 mb-4">Your new root password has been set and your VPS is restarting to apply it. It will be back online in about 30 seconds. Please copy the password now — it will not be shown again.</p>
                    <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <code class="flex-1 text-[14px] font-mono text-gray-900 break-all select-all">{{ newPassword }}</code>
                        <button @click="copyPassword" class="flex-shrink-0 px-3 py-1.5 text-[12px] font-medium rounded-md transition-colors" :class="passwordCopied ? 'bg-emerald-100 text-emerald-700' : 'bg-indigo-100 text-indigo-700 hover:bg-indigo-200'">
                            {{ passwordCopied ? 'Copied!' : 'Copy' }}
                        </button>
                    </div>
                    <div class="mt-4 p-3 bg-amber-50 rounded-lg border border-amber-200">
                        <p class="text-[12px] text-amber-800 flex items-start gap-2">
                            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.27 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
                            <span>Save this password in a secure location. You'll need it to log in to your VPS via SSH or console.</span>
                        </p>
                    </div>
                    <button @click="showNewPasswordModal = false" class="mt-4 w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-[13px] font-semibold rounded-lg transition-colors">
                        I've Saved My Password
                    </button>
                </div>
            </div>
        </div>

        <ConfirmModal :show="showCancelModal" title="Request Cancellation" message="This will submit a cancellation request for this service." confirm-text="Submit Request" @confirm="submitCancel" @close="showCancelModal = false">
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

        <!-- Reinstall OS Modal -->
        <ConfirmModal
            :show="showReinstallModal"
            title="Reinstall Operating System"
            message=""
            :confirm-text="reinstallLoading ? 'Rebuilding...' : 'Reinstall OS'"
            :confirm-disabled="!canSubmitReinstall"
            @confirm="executeReinstall"
            @close="showReinstallModal = false"
        >
            <div class="space-y-4">
                <!-- Warning -->
                <div class="px-3 py-2.5 bg-red-50 border border-red-200 rounded-lg flex items-start gap-2">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
                    <p class="text-[12px] text-red-700"><strong>Warning:</strong> This will completely erase all data on the VPS and install a fresh operating system. This action cannot be undone.</p>
                </div>

                <!-- Error -->
                <div v-if="reinstallError" class="px-3 py-2 bg-red-50 border border-red-200 rounded-lg text-[12px] text-red-700">{{ reinstallError }}</div>

                <!-- Loading templates -->
                <div v-if="templatesLoading" class="flex items-center justify-center py-6 gap-2 text-[13px] text-gray-500">
                    <svg class="w-5 h-5 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                    Loading OS templates...
                </div>

                <!-- OS Template Selection -->
                <div v-if="!templatesLoading && osTemplates.length > 0">
                    <label class="block text-[13px] font-medium text-gray-700 mb-1">Operating System</label>
                    <select v-model="selectedOsId" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select an OS template...</option>
                        <optgroup v-for="(templates, group) in groupedTemplates" :key="group" :label="group">
                            <option v-for="tpl in templates" :key="tpl.osid" :value="tpl.osid">{{ tpl.name }}</option>
                        </optgroup>
                    </select>
                </div>

                <!-- New Root Password -->
                <div v-if="!templatesLoading && osTemplates.length > 0">
                    <label class="block text-[13px] font-medium text-gray-700 mb-1">New Root Password</label>
                    <input v-model="reinstallPassword" type="password" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Minimum 6 characters" autocomplete="new-password" />
                </div>

                <!-- Confirm Password -->
                <div v-if="!templatesLoading && osTemplates.length > 0">
                    <label class="block text-[13px] font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input v-model="reinstallPasswordConfirm" type="password" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Re-enter password" autocomplete="new-password" />
                    <p v-if="reinstallPasswordConfirm && reinstallPassword !== reinstallPasswordConfirm" class="text-[11px] text-red-500 mt-1">Passwords do not match</p>
                </div>

                <!-- Type REINSTALL to confirm -->
                <div v-if="!templatesLoading && osTemplates.length > 0">
                    <label class="block text-[13px] font-medium text-gray-700 mb-1">Type <strong class="text-red-600">REINSTALL</strong> to confirm</label>
                    <input v-model="reinstallConfirmText" type="text" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="REINSTALL" />
                </div>

                <!-- Disabled submit info -->
                <p v-if="!canSubmitReinstall && !templatesLoading && osTemplates.length > 0" class="text-[11px] text-gray-400 text-center">
                    Select an OS, set a password (min 6 chars), and type REINSTALL to proceed.
                </p>
            </div>
        </ConfirmModal>
    </ClientLayout>
</template>
