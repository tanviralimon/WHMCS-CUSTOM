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
    paymentConfig: Object,
    paymentMethods: Array,
});

const inv = props.invoice;
const items = computed(() => inv.items?.item || []);
const transactions = computed(() => inv.transactions?.transaction || []);
const isUnpaid = computed(() => inv.status === 'Unpaid');
const balance = computed(() => parseFloat(inv.balance || 0));
const hasCredit = computed(() => (props.creditBalance || 0) > 0);

// Flash messages
const flashSuccess = computed(() => page.props.flash?.success);
const flashErrors = computed(() => page.props.errors);

// Payment tab: 'credit' | 'card' | 'bank'
const activeTab = ref(
    props.paymentConfig?.stripe ? 'card' :
    hasCredit.value ? 'credit' : 'bank'
);

// ── Credit payment ──
const creditAmount = ref(Math.min(props.creditBalance || 0, balance.value).toFixed(2));
const applyingCredit = ref(false);

function applyCredit() {
    applyingCredit.value = true;
    router.post(route('client.payment.applyCredit', inv.invoiceid), {
        amount: parseFloat(creditAmount.value),
    }, {
        preserveScroll: true,
        onFinish: () => { applyingCredit.value = false; },
    });
}

// ── Stripe payment ──
const processingStripe = ref(false);

function payWithStripe() {
    processingStripe.value = true;

    fetch(route('client.payment.stripe.create', inv.invoiceid), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.content,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.url) {
            window.location.href = data.url;
        } else {
            alert(data.error || 'Failed to create payment session.');
            processingStripe.value = false;
        }
    })
    .catch(() => {
        alert('Something went wrong. Please try again.');
        processingStripe.value = false;
    });
}

// ── Bank transfer ──
const bankNotified = ref(false);

function confirmBankTransfer() {
    bankNotified.value = true;
    router.post(route('client.payment.bank.notify', inv.invoiceid), {}, {
        preserveScroll: true,
    });
}

// ── Copy to clipboard ──
function copyText(text) {
    navigator.clipboard.writeText(text);
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <h1 class="text-lg font-bold text-gray-900">Invoice #{{ inv.invoicenum || inv.invoiceid }}</h1>
                <p class="text-[13px] text-gray-500">{{ inv.date }}</p>
            </div>
        </template>
        <template #actions>
            <a :href="route('client.invoices.pdf', inv.invoiceid)" target="_blank"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-[13px] font-medium text-gray-700 bg-white rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                PDF
            </a>
        </template>

        <!-- Flash messages -->
        <div v-if="flashSuccess" class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-[13px] text-emerald-700 font-medium flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            {{ flashSuccess }}
        </div>
        <div v-if="flashErrors?.payment" class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-[13px] text-red-700 font-medium">
            {{ flashErrors.payment }}
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Invoice Info -->
                <Card noPadding>
                    <div class="px-6 py-4 flex items-center justify-between border-b border-gray-100">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Invoice #{{ inv.invoicenum || inv.invoiceid }}</p>
                            <p class="text-[12px] text-gray-500 mt-0.5">Issued: {{ inv.date }} · Due: {{ inv.duedate }}</p>
                        </div>
                        <StatusBadge :status="inv.status" size="md" />
                    </div>

                    <!-- Line Items -->
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/50">
                                <th class="text-left px-6 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="text-right px-6 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in items" :key="item.id" class="border-b border-gray-50">
                                <td class="px-6 py-3 text-[13px] text-gray-900">{{ item.description }}</td>
                                <td class="px-6 py-3 text-[13px] text-gray-900 text-right font-medium">{{ formatCurrency(item.amount) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50/50">
                            <tr class="border-t border-gray-200">
                                <td class="px-6 py-2 text-[13px] text-gray-600 text-right">Subtotal</td>
                                <td class="px-6 py-2 text-[13px] text-gray-900 text-right font-medium">{{ formatCurrency(inv.subtotal) }}</td>
                            </tr>
                            <tr v-if="parseFloat(inv.tax) > 0">
                                <td class="px-6 py-2 text-[13px] text-gray-600 text-right">Tax</td>
                                <td class="px-6 py-2 text-[13px] text-gray-900 text-right font-medium">{{ formatCurrency(inv.tax) }}</td>
                            </tr>
                            <tr v-if="parseFloat(inv.credit) > 0">
                                <td class="px-6 py-2 text-[13px] text-gray-600 text-right">Credit</td>
                                <td class="px-6 py-2 text-[13px] text-emerald-600 text-right font-medium">-{{ formatCurrency(inv.credit) }}</td>
                            </tr>
                            <tr class="border-t border-gray-200">
                                <td class="px-6 py-3 text-[14px] font-semibold text-gray-900 text-right">Total</td>
                                <td class="px-6 py-3 text-[14px] font-bold text-gray-900 text-right">{{ formatCurrency(inv.total) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </Card>

                <!-- Transactions -->
                <Card v-if="transactions.length" title="Transactions">
                    <div class="space-y-2">
                        <div v-for="t in transactions" :key="t.id" class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                            <div>
                                <p class="text-[13px] font-medium text-gray-900">{{ t.gateway }}</p>
                                <p class="text-[12px] text-gray-500">{{ t.date }} · {{ t.transid }}</p>
                            </div>
                            <p class="text-[13px] font-semibold text-emerald-600">{{ formatCurrency(t.amountin || t.amount) }}</p>
                        </div>
                    </div>
                </Card>
            </div>

            <!-- Sidebar -->
            <div class="space-y-4">
                <Card title="Summary">
                    <dl class="space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">Status</dt>
                            <dd><StatusBadge :status="inv.status" /></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">Total</dt>
                            <dd class="text-[13px] font-semibold text-gray-900">{{ formatCurrency(inv.total) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">Balance</dt>
                            <dd class="text-[13px] font-semibold" :class="balance > 0 ? 'text-red-600' : 'text-emerald-600'">{{ formatCurrency(inv.balance) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">Payment</dt>
                            <dd class="text-[13px] text-gray-900 capitalize">{{ inv.paymentmethod }}</dd>
                        </div>
                    </dl>
                </Card>

                <!-- ═══════ PAY INVOICE (In-Portal) ═══════ -->
                <Card v-if="isUnpaid && balance > 0" title="Pay Invoice">
                    <div class="space-y-4">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(balance) }}</p>
                            <p class="text-[12px] text-gray-500 mt-1">Amount Due</p>
                        </div>

                        <!-- Payment Method Tabs -->
                        <div class="flex rounded-lg bg-gray-100 p-0.5">
                            <button v-if="paymentConfig?.stripe" @click="activeTab = 'card'"
                                class="flex-1 px-3 py-2 text-[12px] font-semibold rounded-md transition-all"
                                :class="activeTab === 'card' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                💳 Card
                            </button>
                            <button v-if="hasCredit" @click="activeTab = 'credit'"
                                class="flex-1 px-3 py-2 text-[12px] font-semibold rounded-md transition-all"
                                :class="activeTab === 'credit' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                🏦 Credit
                            </button>
                            <button v-if="paymentConfig?.bank_transfer" @click="activeTab = 'bank'"
                                class="flex-1 px-3 py-2 text-[12px] font-semibold rounded-md transition-all"
                                :class="activeTab === 'bank' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                🏧 Bank
                            </button>
                        </div>

                        <!-- ── Card Payment (Stripe) ── -->
                        <div v-if="activeTab === 'card' && paymentConfig?.stripe" class="space-y-3">
                            <p class="text-[12px] text-gray-500">Pay securely with your credit or debit card via Stripe.</p>

                            <button @click="payWithStripe" :disabled="processingStripe"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 text-[13px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm disabled:opacity-50">
                                <svg v-if="!processingStripe" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>
                                <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                {{ processingStripe ? 'Redirecting to Stripe...' : `Pay ${formatCurrency(balance)} with Card` }}
                            </button>

                            <div class="flex items-center justify-center gap-3 pt-1">
                                <span class="text-[10px] text-gray-400 uppercase tracking-wider">Secured by</span>
                                <span class="text-[11px] font-bold text-gray-400">Stripe</span>
                            </div>
                        </div>

                        <!-- ── Credit Balance ── -->
                        <div v-if="activeTab === 'credit' && hasCredit" class="space-y-3">
                            <div class="bg-emerald-50 rounded-lg p-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-[12px] text-emerald-700">Your Credit Balance</span>
                                    <span class="text-[14px] font-bold text-emerald-700">{{ formatCurrency(creditBalance) }}</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[12px] font-medium text-gray-600 mb-1">Amount to Apply</label>
                                <input v-model="creditAmount" type="number" :min="0.01" :max="Math.min(creditBalance, balance)" step="0.01"
                                    class="w-full px-3 py-2 text-[14px] font-medium rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>

                            <button @click="applyCredit" :disabled="applyingCredit || !creditAmount"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 text-[13px] font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors shadow-sm disabled:opacity-50">
                                <svg v-if="!applyingCredit" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                {{ applyingCredit ? 'Applying...' : `Apply ${formatCurrency(creditAmount)} Credit` }}
                            </button>

                            <p v-if="parseFloat(creditAmount) < balance" class="text-[11px] text-amber-600 text-center">
                                Remaining {{ formatCurrency(balance - parseFloat(creditAmount || 0)) }} can be paid via card or bank transfer.
                            </p>
                        </div>

                        <!-- ── Bank Transfer ── -->
                        <div v-if="activeTab === 'bank' && paymentConfig?.bank_transfer" class="space-y-3">
                            <p class="text-[12px] text-gray-500">Transfer the amount to the bank account below. Use your invoice number as reference.</p>

                            <div class="bg-gray-50 rounded-lg p-3 space-y-2.5 text-[13px]">
                                <div v-if="paymentConfig.bank_details.bank_name" class="flex justify-between">
                                    <span class="text-gray-500">Bank</span>
                                    <span class="font-medium text-gray-900">{{ paymentConfig.bank_details.bank_name }}</span>
                                </div>
                                <div v-if="paymentConfig.bank_details.account_name" class="flex justify-between">
                                    <span class="text-gray-500">Name</span>
                                    <span class="font-medium text-gray-900">{{ paymentConfig.bank_details.account_name }}</span>
                                </div>
                                <div v-if="paymentConfig.bank_details.account_number" class="flex justify-between items-center">
                                    <span class="text-gray-500">Account #</span>
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono font-medium text-gray-900">{{ paymentConfig.bank_details.account_number }}</span>
                                        <button @click="copyText(paymentConfig.bank_details.account_number)" class="text-gray-400 hover:text-indigo-600" title="Copy">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>
                                        </button>
                                    </div>
                                </div>
                                <div v-if="paymentConfig.bank_details.routing_number" class="flex justify-between items-center">
                                    <span class="text-gray-500">Routing #</span>
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono font-medium text-gray-900">{{ paymentConfig.bank_details.routing_number }}</span>
                                        <button @click="copyText(paymentConfig.bank_details.routing_number)" class="text-gray-400 hover:text-indigo-600" title="Copy">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>
                                        </button>
                                    </div>
                                </div>
                                <div v-if="paymentConfig.bank_details.swift_code" class="flex justify-between items-center">
                                    <span class="text-gray-500">SWIFT</span>
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono font-medium text-gray-900">{{ paymentConfig.bank_details.swift_code }}</span>
                                        <button @click="copyText(paymentConfig.bank_details.swift_code)" class="text-gray-400 hover:text-indigo-600" title="Copy">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>
                                        </button>
                                    </div>
                                </div>
                                <div v-if="paymentConfig.bank_details.iban" class="flex justify-between items-center">
                                    <span class="text-gray-500">IBAN</span>
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono font-medium text-gray-900 text-[11px]">{{ paymentConfig.bank_details.iban }}</span>
                                        <button @click="copyText(paymentConfig.bank_details.iban)" class="text-gray-400 hover:text-indigo-600" title="Copy">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="border-t border-gray-200 pt-2 mt-2">
                                    <span class="text-gray-500">Reference</span>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="font-mono font-bold text-indigo-600">INV-{{ inv.invoicenum || inv.invoiceid }}</span>
                                        <button @click="copyText('INV-' + (inv.invoicenum || inv.invoiceid))" class="text-gray-400 hover:text-indigo-600" title="Copy">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <p v-if="paymentConfig.bank_details.instructions" class="text-[11px] text-gray-500 italic">
                                {{ paymentConfig.bank_details.instructions }}
                            </p>

                            <button v-if="!bankNotified" @click="confirmBankTransfer"
                                class="w-full px-4 py-2.5 text-[13px] font-medium text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                                I've Made the Transfer
                            </button>
                            <p v-else class="text-[12px] text-emerald-600 font-medium text-center">
                                ✓ We'll update your invoice once payment is confirmed.
                            </p>
                        </div>

                        <!-- No payment methods available fallback -->
                        <div v-if="!paymentConfig?.stripe && !hasCredit && !paymentConfig?.bank_transfer" class="text-center py-2">
                            <p class="text-[13px] text-gray-500">No payment methods are currently configured.</p>
                            <p class="text-[12px] text-gray-400 mt-1">Please contact support for payment assistance.</p>
                        </div>
                    </div>
                </Card>

                <!-- Paid confirmation -->
                <Card v-else-if="inv.status === 'Paid'">
                    <div class="text-center py-2">
                        <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5" /></svg>
                        </div>
                        <p class="text-[14px] font-semibold text-emerald-700">Invoice Paid</p>
                        <p class="text-[12px] text-gray-500 mt-1">Thank you for your payment</p>
                    </div>
                </Card>

                <!-- Download PDF -->
                <Card title="Documents">
                    <a :href="route('client.invoices.pdf', inv.invoiceid)" target="_blank"
                        class="w-full flex items-center gap-2.5 px-3 py-2.5 text-[13px] font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                        Download PDF Invoice
                        <svg class="w-3.5 h-3.5 text-gray-300 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                    </a>
                </Card>
            </div>
        </div>
    </ClientLayout>
</template>
