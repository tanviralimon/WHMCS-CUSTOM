<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Card from '@/Components/Card.vue';
import { useCurrency } from '@/Composables/useCurrency.js';

const { formatCurrency } = useCurrency();
const page = usePage();

const props = defineProps({
    invoice: Object,
    creditBalance: Number,
    clientDetails: Object,
    companyName: String,
    paymentMethods: Array,
    bankInfo: Object,
    ticketUploadConfig: Object,
    proofSubmitted: Boolean,
});

// WHMCS ticket upload limits
const maxUploadMB = computed(() => props.ticketUploadConfig?.max_size_mb || 2);
const maxUploadBytes = computed(() => maxUploadMB.value * 1024 * 1024);
const allowedExtensions = computed(() => {
    const ext = props.ticketUploadConfig?.extensions || 'jpg,jpeg,gif,png';
    return ext.split(',').map(e => '.' + e.trim()).join(',');
});

const inv = computed(() => props.invoice);
const items = computed(() => {
    const raw = inv.value.items?.item;
    if (!raw) return [];
    return Array.isArray(raw) ? raw : [raw];
});
const transactions = computed(() => {
    const raw = inv.value.transactions?.transaction;
    if (!raw || raw === '0' || raw === 0) return [];
    const arr = Array.isArray(raw) ? raw : [raw];
    return arr.filter(t => t && typeof t === 'object');
});
const isUnpaid = computed(() => inv.value.status === 'Unpaid');
const isPaid = computed(() => (inv.value.status || '').toLowerCase() === 'paid');
const isOverdue = computed(() => (inv.value.status || '').toLowerCase() === 'overdue');
const balance = computed(() => parseFloat(inv.value.balance || 0));
const creditBalance = computed(() => props.creditBalance || 0);
const hasCredit = computed(() => !isAddFundsInvoice.value);
const hasGateways = computed(() => props.paymentMethods && props.paymentMethods.length > 0);

// Detect "Add Funds" invoices
const isAddFundsInvoice = computed(() => {
    return items.value.some(item => {
        const desc = (item.description || '').toLowerCase();
        return desc.includes('add funds') || desc.includes('add credit') || desc.includes('credit top');
    });
});

// Flash messages
const urlParams = new URLSearchParams(window.location.search);
const paymentSuccessFromUrl = urlParams.get('payment_success');
const paymentErrorFromUrl = urlParams.get('payment_error');

const flashSuccess = computed(() => paymentSuccessFromUrl ? 'Payment completed successfully!' : page.props.flash?.success);
const flashErrors = computed(() => {
    if (paymentErrorFromUrl) return { payment: paymentErrorFromUrl };
    return page.props.errors;
});

if (paymentSuccessFromUrl || paymentErrorFromUrl) {
    const cleanUrl = window.location.pathname;
    window.history.replaceState({}, '', cleanUrl);
}

// Payment tab
const activeTab = ref(hasGateways.value ? 'gateway' : 'credit');

// ── Gateway payment ──
const selectedGateway = ref(inv.value.paymentmethod || (props.paymentMethods?.[0]?.module ?? ''));
const processingPay = ref(false);
const payError = ref('');

function payWithGateway() {
    if (!selectedGateway.value) return;
    processingPay.value = true;
    payError.value = '';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    fetch(route('client.payment.pay', inv.value.invoiceid), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ gateway: selectedGateway.value }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.url) {
            window.location.href = data.url;
        } else if (data.bankInfo !== undefined) {
            showBankDetails.value = true;
            processingPay.value = false;
        } else if (data.reload) {
            alert(data.message || 'Payment method updated.');
            window.location.reload();
        } else {
            payError.value = data.error || 'Failed to initiate payment.';
            processingPay.value = false;
        }
    })
    .catch(() => {
        payError.value = 'Something went wrong. Please try again.';
        processingPay.value = false;
    });
}

// ── Credit payment ──
const creditAmount = ref(Math.min(creditBalance.value, balance.value).toFixed(2));
const applyingCredit = ref(false);
const removingCreditId = ref(null);

// ── Bank transfer ──
const isBankTransfer = computed(() => {
    const m = (selectedGateway.value || '').toLowerCase();
    return m.includes('bank') || m.includes('wire');
});
const showBankDetails = ref(isBankTransfer.value && !!props.bankInfo);

// ── Payment proof upload ──
const proofFile = ref(null);
const uploadingProof = ref(false);
const proofError = ref('');
const proofSuccess = ref('');

function onProofFileChange(event) {
    const file = event.target.files[0];
    if (file) {
        if (file.size > maxUploadBytes.value) {
            proofError.value = `File must be under ${maxUploadMB.value}MB.`;
            proofFile.value = null;
            return;
        }
        proofFile.value = file;
        proofError.value = '';
    }
}

function uploadProof() {
    if (!proofFile.value) return;
    uploadingProof.value = true;
    proofError.value = '';
    proofSuccess.value = '';

    router.post(route('client.payment.uploadProof', inv.value.invoiceid), {
        proof: proofFile.value,
        amount: balance.value,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: (p) => {
            proofSuccess.value = p.props?.flash?.success || 'Payment proof submitted! We\'ll verify it shortly.';
            proofFile.value = null;
        },
        onError: (errors) => {
            proofError.value = errors.proof || 'Upload failed. Please try again.';
        },
        onFinish: () => {
            uploadingProof.value = false;
        },
    });
}

function applyCredit() {
    applyingCredit.value = true;
    router.post(route('client.payment.applyCredit', inv.value.invoiceid), {
        amount: parseFloat(creditAmount.value),
    }, {
        preserveScroll: true,
        onFinish: () => { applyingCredit.value = false; },
    });
}

function removeCredit(transactionId) {
    if (!confirm('Remove this credit payment? Your credit balance will be restored.')) return;
    removingCreditId.value = transactionId;
    router.post(route('client.payment.removeCredit', { id: inv.value.invoiceid, transactionId }), {}, {
        preserveScroll: true,
        onFinish: () => { removingCreditId.value = null; },
    });
}

// ── Helpers ──
function gatewayIcon(module) {
    const m = (module || '').toLowerCase();
    if (m.includes('stripe')) return '💳';
    if (m.includes('ssl') || m.includes('sslcommerz')) return '🏦';
    if (m.includes('paypal')) return '🅿️';
    if (m.includes('bank') || m.includes('wire')) return '🏧';
    if (m.includes('razorpay')) return '💳';
    return '💰';
}

function selectedGatewayName() {
    const pm = props.paymentMethods?.find(p => p.module === selectedGateway.value);
    return pm?.displayname || selectedGateway.value;
}

function friendlyGatewayName() {
    const m = (selectedGateway.value || '').toLowerCase();
    if (m.includes('sslcommerz')) return 'SSLCommerz';
    if (m.includes('stripe')) return 'Stripe';
    if (m.includes('paypal')) return 'PayPal';
    if (m.includes('razorpay')) return 'Razorpay';
    if (m.includes('bank') || m.includes('wire')) return 'Bank Transfer';
    return selectedGatewayName();
}

function formatDate(d) {
    if (!d) return '';
    const date = new Date(d);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

// Client address formatted
const clientAddress = computed(() => {
    const c = props.clientDetails || {};
    const parts = [c.address1, c.address2, [c.city, c.state].filter(Boolean).join(', '), c.postcode, c.country].filter(Boolean);
    return parts;
});

// Status color for the header
const statusConfig = computed(() => {
    const s = (inv.value.status || '').toLowerCase();
    if (s === 'paid') return { bg: 'bg-emerald-500', light: 'bg-emerald-50', text: 'text-emerald-700', border: 'border-emerald-200', icon: 'check', label: 'Paid' };
    if (s === 'overdue') return { bg: 'bg-red-500', light: 'bg-red-50', text: 'text-red-700', border: 'border-red-200', icon: 'alert', label: 'Overdue' };
    if (s === 'unpaid') return { bg: 'bg-amber-500', light: 'bg-amber-50', text: 'text-amber-700', border: 'border-amber-200', icon: 'clock', label: 'Unpaid' };
    if (s === 'cancelled') return { bg: 'bg-gray-400', light: 'bg-gray-50', text: 'text-gray-600', border: 'border-gray-200', icon: 'x', label: 'Cancelled' };
    if (s === 'refunded') return { bg: 'bg-purple-500', light: 'bg-purple-50', text: 'text-purple-700', border: 'border-purple-200', icon: 'refund', label: 'Refunded' };
    return { bg: 'bg-gray-400', light: 'bg-gray-50', text: 'text-gray-600', border: 'border-gray-200', icon: 'doc', label: inv.value.status };
});
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <h1 class="text-lg font-bold text-gray-900">Invoice #{{ inv.invoicenum || inv.invoiceid }}</h1>
                <p class="text-[13px] text-gray-500">{{ formatDate(inv.date) }}</p>
            </div>
        </template>
        <template #actions>
            <a :href="route('client.invoices.pdf', inv.invoiceid)" target="_blank"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-[13px] font-medium text-gray-700 bg-white rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                PDF
            </a>
        </template>

        <!-- Flash Messages -->
        <div v-if="flashSuccess" class="mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-[13px] text-emerald-700 font-medium flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            {{ flashSuccess }}
        </div>
        <div v-if="flashErrors?.payment" class="mb-5 p-4 rounded-xl bg-red-50 border border-red-200 text-[13px] text-red-700 font-medium flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9.303 3.376c-.866 1.5-2.032 1.5-2.898 0L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374z" /></svg>
            </div>
            {{ flashErrors.payment }}
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- ═══════ MAIN CONTENT — LEFT 2 COLS ═══════ -->
            <div class="lg:col-span-2 space-y-6">

                <!-- ─── Invoice Document Card ─── -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <!-- Header with status gradient strip -->
                    <div class="relative">
                        <div class="h-1.5" :class="statusConfig.bg"></div>
                        <div class="px-6 py-5">
                            <div class="flex items-start justify-between">
                                <!-- Company / Invoice branding -->
                                <div>
                                    <div class="flex items-center gap-2.5 mb-3">
                                        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
                                        </div>
                                        <span class="text-[16px] font-bold text-gray-900 tracking-tight">{{ companyName || 'OrcusTech' }}</span>
                                    </div>
                                    <h2 class="text-[11px] font-bold text-gray-400 uppercase tracking-[0.15em]">Invoice</h2>
                                    <p class="text-xl font-bold text-gray-900 mt-0.5">#{{ inv.invoicenum || inv.invoiceid }}</p>
                                </div>

                                <!-- Status Badge (large) -->
                                <div class="text-right">
                                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-xl" :class="[statusConfig.light, statusConfig.border, 'border']">
                                        <!-- Status icons -->
                                        <svg v-if="statusConfig.icon === 'check'" class="w-5 h-5" :class="statusConfig.text" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <svg v-else-if="statusConfig.icon === 'alert'" class="w-5 h-5" :class="statusConfig.text" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                                        <svg v-else-if="statusConfig.icon === 'clock'" class="w-5 h-5" :class="statusConfig.text" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <svg v-else-if="statusConfig.icon === 'refund'" class="w-5 h-5" :class="statusConfig.text" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                                        <svg v-else class="w-5 h-5" :class="statusConfig.text" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        <span class="text-[14px] font-bold" :class="statusConfig.text">{{ statusConfig.label }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Date & Billing Info Row -->
                    <div class="px-6 pb-5">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 p-4 bg-gray-50/80 rounded-xl border border-gray-100">
                            <!-- Invoice Dates -->
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Invoice Date</p>
                                <p class="text-[13px] font-semibold text-gray-900">{{ formatDate(inv.date) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Due Date</p>
                                <p class="text-[13px] font-semibold" :class="isOverdue ? 'text-red-600' : 'text-gray-900'">
                                    {{ formatDate(inv.duedate) }}
                                    <span v-if="isOverdue" class="text-[10px] font-bold text-red-500 ml-1">OVERDUE</span>
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Payment Method</p>
                                <p class="text-[13px] font-semibold text-gray-900 capitalize">{{ inv.paymentmethod || '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Bill To Section -->
                    <div v-if="clientDetails" class="px-6 pb-5">
                        <div class="flex items-start gap-3 p-4 bg-indigo-50/50 rounded-xl border border-indigo-100">
                            <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest mb-1">Billed To</p>
                                <p class="text-[13px] font-semibold text-gray-900">{{ clientDetails.name }}</p>
                                <p v-if="clientDetails.company" class="text-[12px] text-gray-600">{{ clientDetails.company }}</p>
                                <p class="text-[12px] text-gray-500 mt-0.5">{{ clientDetails.email }}</p>
                                <p v-for="(line, i) in clientAddress" :key="i" class="text-[12px] text-gray-500 leading-relaxed">{{ line }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- ─── Line Items ─── -->
                    <div class="px-6 pb-1">
                        <table class="w-full">
                            <thead>
                                <tr class="border-y border-gray-200">
                                    <th class="text-left py-3 px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Description</th>
                                    <th class="text-right py-3 px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest w-32">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, idx) in items" :key="item.id" class="border-b border-gray-50 group">
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-start gap-3">
                                            <span class="w-6 h-6 rounded-md bg-gray-100 text-[11px] font-bold text-gray-400 flex items-center justify-center flex-shrink-0 mt-0.5">{{ idx + 1 }}</span>
                                            <p class="text-[13px] text-gray-900 leading-relaxed">{{ item.description }}</p>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <span class="text-[13px] font-semibold text-gray-900">{{ formatCurrency(item.amount) }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ─── Totals ─── -->
                    <div class="px-6 pb-6">
                        <div class="flex justify-end">
                            <div class="w-full sm:w-72">
                                <div class="space-y-2 pt-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-[12px] text-gray-500">Subtotal</span>
                                        <span class="text-[13px] font-medium text-gray-700">{{ formatCurrency(inv.subtotal) }}</span>
                                    </div>
                                    <div v-if="parseFloat(inv.tax) > 0" class="flex justify-between items-center">
                                        <span class="text-[12px] text-gray-500">Tax</span>
                                        <span class="text-[13px] font-medium text-gray-700">{{ formatCurrency(inv.tax) }}</span>
                                    </div>
                                    <div v-if="parseFloat(inv.credit) > 0" class="flex justify-between items-center">
                                        <span class="text-[12px] text-gray-500">Credit Applied</span>
                                        <span class="text-[13px] font-medium text-emerald-600">-{{ formatCurrency(inv.credit) }}</span>
                                    </div>
                                    <!-- Total -->
                                    <div class="flex justify-between items-center pt-3 border-t-2 border-gray-900">
                                        <span class="text-[13px] font-bold text-gray-900">Total</span>
                                        <span class="text-[18px] font-bold text-gray-900">{{ formatCurrency(inv.total) }}</span>
                                    </div>
                                    <!-- Balance Due -->
                                    <div v-if="balance > 0 && balance !== parseFloat(inv.total)" class="flex justify-between items-center pt-2 border-t border-dashed border-gray-300">
                                        <span class="text-[12px] font-semibold text-red-600">Balance Due</span>
                                        <span class="text-[16px] font-bold text-red-600">{{ formatCurrency(inv.balance) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── Transactions ─── -->
                <div v-if="transactions.length" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-emerald-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                        </div>
                        <h3 class="text-[14px] font-semibold text-gray-900">Payment Transactions</h3>
                    </div>
                    <div class="divide-y divide-gray-50">
                        <div v-for="t in transactions" :key="t.id" class="px-6 py-4 flex items-center justify-between hover:bg-gray-50/50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-emerald-50 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" /></svg>
                                </div>
                                <div>
                                    <p class="text-[13px] font-semibold text-gray-900">{{ t.gateway }}</p>
                                    <p class="text-[11px] text-gray-500">{{ formatDate(t.date) }} <span v-if="t.transid" class="text-gray-300">·</span> <span v-if="t.transid" class="font-mono text-gray-400">{{ t.transid }}</span></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-[14px] font-bold text-emerald-600">+{{ formatCurrency(t.amountin || t.amount) }}</span>
                                <button v-if="(t.gateway || '').toLowerCase().includes('credit')"
                                    @click="removeCredit(t.id)"
                                    :disabled="removingCreditId === t.id"
                                    class="text-[11px] px-2.5 py-1 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors disabled:opacity-50 font-medium"
                                    title="Remove credit and restore balance">
                                    {{ removingCreditId === t.id ? '...' : '✕ Remove' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── Notes (if any) ─── -->
                <div v-if="inv.notes" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                        <h3 class="text-[14px] font-semibold text-gray-900">Notes</h3>
                    </div>
                    <div class="px-6 py-4">
                        <p class="text-[13px] text-gray-600 whitespace-pre-line leading-relaxed">{{ inv.notes }}</p>
                    </div>
                </div>
            </div>

            <!-- ═══════ SIDEBAR — RIGHT COL ═══════ -->
            <div class="space-y-5">

                <!-- ─── Quick Summary Card ─── -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="p-5 text-center" :class="isPaid ? 'bg-gradient-to-br from-emerald-50 to-teal-50' : isOverdue ? 'bg-gradient-to-br from-red-50 to-orange-50' : 'bg-gradient-to-br from-slate-50 to-gray-50'">
                        <div class="w-14 h-14 rounded-2xl mx-auto mb-3 flex items-center justify-center"
                            :class="isPaid ? 'bg-emerald-100' : isOverdue ? 'bg-red-100' : 'bg-gray-100'">
                            <svg v-if="isPaid" class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            <svg v-else-if="isOverdue" class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                            <svg v-else class="w-7 h-7 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <p class="text-3xl font-bold" :class="isPaid ? 'text-emerald-700' : isOverdue ? 'text-red-700' : 'text-gray-900'">{{ formatCurrency(inv.total) }}</p>
                        <p class="text-[12px] font-medium mt-1" :class="isPaid ? 'text-emerald-600' : isOverdue ? 'text-red-500' : 'text-gray-500'">
                            {{ isPaid ? 'Paid in Full' : isOverdue ? 'Payment Overdue' : 'Amount Due' }}
                        </p>
                    </div>

                    <div class="p-4 space-y-3 border-t border-gray-100">
                        <div class="flex justify-between items-center">
                            <span class="text-[12px] text-gray-500">Status</span>
                            <StatusBadge :status="inv.status" />
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[12px] text-gray-500">Total</span>
                            <span class="text-[13px] font-bold text-gray-900">{{ formatCurrency(inv.total) }}</span>
                        </div>
                        <div v-if="balance > 0" class="flex justify-between items-center">
                            <span class="text-[12px] text-gray-500">Balance Due</span>
                            <span class="text-[13px] font-bold text-red-600">{{ formatCurrency(inv.balance) }}</span>
                        </div>
                        <div v-if="parseFloat(inv.credit) > 0" class="flex justify-between items-center">
                            <span class="text-[12px] text-gray-500">Credit Applied</span>
                            <span class="text-[13px] font-semibold text-emerald-600">-{{ formatCurrency(inv.credit) }}</span>
                        </div>
                    </div>
                </div>

                <!-- ═══════ PAY INVOICE ═══════ -->
                <div v-if="isUnpaid && balance > 0" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-indigo-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>
                        </div>
                        <h3 class="text-[14px] font-semibold text-gray-900">Pay Invoice</h3>
                    </div>

                    <div class="p-5 space-y-4">
                        <!-- Amount Due -->
                        <div class="text-center py-2">
                            <p class="text-[11px] text-gray-400 font-medium uppercase tracking-wider">Amount Due</p>
                            <p class="text-2xl font-bold text-gray-900 mt-1">{{ formatCurrency(balance) }}</p>
                        </div>

                        <!-- Payment Method Tabs -->
                        <div v-if="hasGateways || hasCredit" class="flex rounded-lg bg-gray-100 p-0.5">
                            <button v-if="hasGateways" @click="activeTab = 'gateway'"
                                class="flex-1 px-3 py-2 text-[12px] font-semibold rounded-md transition-all"
                                :class="activeTab === 'gateway' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                💳 Pay Online
                            </button>
                            <button v-if="hasCredit" @click="activeTab = 'credit'"
                                class="flex-1 px-3 py-2 text-[12px] font-semibold rounded-md transition-all"
                                :class="activeTab === 'credit' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                🏦 Use Credit
                            </button>
                        </div>

                        <!-- ── Pay via Gateway ── -->
                        <div v-if="activeTab === 'gateway' && hasGateways" class="space-y-3">
                            <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Choose Payment Method</label>
                            <div class="space-y-1.5">
                                <label v-for="pm in paymentMethods" :key="pm.module"
                                    class="flex items-center gap-3 px-3.5 py-3 rounded-xl border cursor-pointer transition-all"
                                    :class="selectedGateway === pm.module ? 'border-indigo-300 bg-indigo-50/60 ring-1 ring-indigo-200' : 'border-gray-200 bg-gray-50 hover:bg-gray-100'">
                                    <input type="radio" :value="pm.module" v-model="selectedGateway"
                                        class="text-indigo-600 focus:ring-indigo-500 h-4 w-4" />
                                    <span class="text-[16px]">{{ gatewayIcon(pm.module) }}</span>
                                    <span class="text-[13px] font-medium" :class="selectedGateway === pm.module ? 'text-indigo-700' : 'text-gray-700'">{{ pm.displayname }}</span>
                                </label>
                            </div>

                            <div v-if="payError" class="p-3 rounded-xl bg-red-50 border border-red-200 text-[12px] text-red-700 flex items-center gap-2">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9.303 3.376c-.866 1.5-2.032 1.5-2.898 0L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374z" /></svg>
                                {{ payError }}
                            </div>

                            <button v-if="!isBankTransfer || !showBankDetails" @click="payWithGateway" :disabled="processingPay || !selectedGateway"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 text-[13px] font-semibold text-white bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-xl hover:from-indigo-700 hover:to-indigo-800 transition-all shadow-sm shadow-indigo-200 disabled:opacity-50 disabled:shadow-none">
                                <svg v-if="!processingPay" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>
                                <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                {{ processingPay ? 'Processing...' : (isBankTransfer ? 'Show Bank Details' : `Pay ${formatCurrency(balance)} Now`) }}
                            </button>

                            <p v-if="!isBankTransfer" class="text-[11px] text-gray-400 text-center">
                                You'll be redirected to {{ friendlyGatewayName() }} to complete payment
                            </p>

                            <!-- ── Bank Transfer Details ── -->
                            <div v-if="isBankTransfer && bankInfo" class="space-y-3 mt-2">
                                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                                    <p class="text-[12px] font-bold text-amber-800 mb-3 flex items-center gap-1.5">
                                        🏦 Bank Transfer Details
                                    </p>
                                    <dl class="space-y-2 text-[12px]">
                                        <div v-if="bankInfo.bank_name" class="flex justify-between">
                                            <dt class="text-amber-700">Bank Name</dt>
                                            <dd class="font-semibold text-amber-900">{{ bankInfo.bank_name }}</dd>
                                        </div>
                                        <div v-if="bankInfo.account_name" class="flex justify-between">
                                            <dt class="text-amber-700">Account Name</dt>
                                            <dd class="font-semibold text-amber-900">{{ bankInfo.account_name }}</dd>
                                        </div>
                                        <div v-if="bankInfo.account_number" class="flex justify-between">
                                            <dt class="text-amber-700">Account No.</dt>
                                            <dd class="font-mono font-semibold text-amber-900">{{ bankInfo.account_number }}</dd>
                                        </div>
                                        <div v-if="bankInfo.branch" class="flex justify-between">
                                            <dt class="text-amber-700">Branch</dt>
                                            <dd class="font-semibold text-amber-900">{{ bankInfo.branch }}</dd>
                                        </div>
                                        <div v-if="bankInfo.routing" class="flex justify-between">
                                            <dt class="text-amber-700">Routing No.</dt>
                                            <dd class="font-mono font-semibold text-amber-900">{{ bankInfo.routing }}</dd>
                                        </div>
                                        <div v-if="bankInfo.swift" class="flex justify-between">
                                            <dt class="text-amber-700">SWIFT</dt>
                                            <dd class="font-mono font-semibold text-amber-900">{{ bankInfo.swift }}</dd>
                                        </div>
                                        <div v-if="bankInfo.iban" class="flex justify-between">
                                            <dt class="text-amber-700">IBAN</dt>
                                            <dd class="font-mono font-semibold text-amber-900">{{ bankInfo.iban }}</dd>
                                        </div>
                                    </dl>
                                    <div v-if="bankInfo.instructions" class="mt-3 pt-3 border-t border-amber-200">
                                        <p class="text-[11px] text-amber-700 whitespace-pre-line">{{ bankInfo.instructions }}</p>
                                    </div>
                                </div>

                                <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 text-center">
                                    <p class="text-[11px] font-bold text-indigo-500 uppercase tracking-wider">Amount to Transfer</p>
                                    <p class="text-xl font-bold text-indigo-900 mt-1">{{ formatCurrency(balance) }}</p>
                                    <p class="text-[11px] text-indigo-600 mt-1">Reference: Invoice #{{ inv.invoicenum || inv.invoiceid }}</p>
                                </div>

                                <!-- Upload Payment Proof -->
                                <div class="border border-gray-200 rounded-xl p-4 space-y-3">
                                    <p class="text-[12px] font-bold text-gray-700 flex items-center gap-1.5">
                                        📎 Upload Payment Proof
                                    </p>

                                    <div v-if="proofSubmitted && !proofSuccess" class="bg-emerald-50 border border-emerald-200 rounded-lg p-3 text-center">
                                        <p class="text-[12px] font-semibold text-emerald-800 flex items-center justify-center gap-1.5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Payment proof already submitted
                                        </p>
                                        <p class="text-[11px] text-emerald-600 mt-1">A billing ticket has been created. We'll verify your payment shortly.</p>
                                    </div>

                                    <template v-else-if="!proofSubmitted">
                                        <p class="text-[11px] text-gray-500">Upload your transfer receipt (max {{ maxUploadMB }}MB).</p>

                                        <input type="file" :accept="allowedExtensions"
                                            @change="onProofFileChange"
                                            class="w-full text-[12px] text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[12px] file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />

                                        <div v-if="proofError" class="text-[11px] text-red-600">{{ proofError }}</div>

                                        <button v-if="proofFile && !proofSuccess" @click="uploadProof" :disabled="uploadingProof"
                                            class="w-full inline-flex items-center justify-center gap-2 px-3 py-2.5 text-[12px] font-semibold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 transition-colors disabled:opacity-50">
                                            <svg v-if="!uploadingProof" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                                            <svg v-else class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                            {{ uploadingProof ? 'Uploading...' : 'Submit Payment Proof' }}
                                        </button>
                                    </template>

                                    <div v-if="proofSuccess" class="text-[11px] text-emerald-600 font-medium">{{ proofSuccess }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- ── Credit Balance ── -->
                        <div v-if="activeTab === 'credit'" class="space-y-3">
                            <div v-if="creditBalance <= 0" class="text-center py-4 space-y-3">
                                <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" /></svg>
                                </div>
                                <p class="text-[13px] text-gray-600 font-medium">No credit balance</p>
                                <p class="text-[12px] text-gray-400">Add funds to pay with credit.</p>
                                <a :href="route('client.billing.credit')" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12px] font-semibold text-indigo-600 bg-indigo-50 rounded-xl hover:bg-indigo-100 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                    Add Funds
                                </a>
                            </div>

                            <template v-else>
                                <div class="bg-emerald-50 rounded-xl p-3.5">
                                    <div class="flex justify-between items-center">
                                        <span class="text-[12px] text-emerald-700 font-medium">Your Credit Balance</span>
                                        <span class="text-[15px] font-bold text-emerald-700">{{ formatCurrency(creditBalance) }}</span>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Amount to Apply</label>
                                    <input v-model="creditAmount" type="number" :min="0.01" :max="Math.min(creditBalance, balance)" step="0.01"
                                        class="w-full px-3 py-2.5 text-[14px] font-medium rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>

                                <button @click="applyCredit" :disabled="applyingCredit || !parseFloat(creditAmount)"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 text-[13px] font-semibold text-white bg-gradient-to-r from-emerald-600 to-emerald-700 rounded-xl hover:from-emerald-700 hover:to-emerald-800 transition-all shadow-sm shadow-emerald-200 disabled:opacity-50 disabled:shadow-none">
                                    <svg v-if="!applyingCredit" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                    <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                    {{ applyingCredit ? 'Applying...' : `Apply ${formatCurrency(creditAmount)} Credit` }}
                                </button>

                                <p v-if="parseFloat(creditAmount) < balance" class="text-[11px] text-amber-600 text-center">
                                    Remaining {{ formatCurrency(balance - parseFloat(creditAmount || 0)) }} can be paid via online payment.
                                </p>
                            </template>
                        </div>

                        <div v-if="!hasGateways && !hasCredit" class="text-center py-3">
                            <p class="text-[13px] text-gray-500">No payment methods available.</p>
                            <p class="text-[12px] text-gray-400 mt-1">Please contact support.</p>
                        </div>
                    </div>
                </div>

                <!-- ─── Paid Confirmation ─── -->
                <div v-else-if="isPaid" class="bg-white rounded-2xl border border-emerald-200 shadow-sm overflow-hidden">
                    <div class="p-6 text-center bg-gradient-to-br from-emerald-50 to-teal-50">
                        <div class="w-16 h-16 rounded-2xl bg-emerald-100 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5" /></svg>
                        </div>
                        <p class="text-[15px] font-bold text-emerald-700">Invoice Paid</p>
                        <p class="text-[12px] text-emerald-600 mt-1">Thank you for your payment!</p>
                    </div>
                </div>

                <!-- ─── Download PDF ─── -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                        <h3 class="text-[14px] font-semibold text-gray-900">Documents</h3>
                    </div>
                    <div class="p-4">
                        <a :href="route('client.invoices.pdf', inv.invoiceid)" target="_blank"
                            class="w-full flex items-center gap-3 px-4 py-3 text-[13px] font-medium text-gray-700 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors border border-gray-100">
                            <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[13px] font-semibold text-gray-900">Download PDF</p>
                                <p class="text-[11px] text-gray-400">Invoice #{{ inv.invoicenum || inv.invoiceid }}</p>
                            </div>
                            <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                        </a>
                    </div>
                </div>

                <!-- ─── Need Help ─── -->
                <div class="bg-gray-50 rounded-2xl border border-gray-200 p-4 text-center">
                    <p class="text-[12px] text-gray-500">Having trouble with this invoice?</p>
                    <a :href="route('client.tickets.create')" class="inline-flex items-center gap-1 text-[12px] font-semibold text-indigo-600 hover:text-indigo-700 mt-1">
                        Contact Support
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                    </a>
                </div>
            </div>
        </div>
    </ClientLayout>
</template>