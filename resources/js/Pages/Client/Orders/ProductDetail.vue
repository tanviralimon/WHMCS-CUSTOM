<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';
import { useCurrency } from '@/Composables/useCurrency.js';

const { formatCurrency } = useCurrency();

const props = defineProps({
    product: Object,
    paymentMethods: Array,
    requiresDomain: { type: Boolean, default: false },
    requiresServerConfig: { type: Boolean, default: false },
    configOptions: { type: Array, default: () => [] },
    customFields: { type: Array, default: () => [] },
    tldPricing: { type: Array, default: () => [] },
    currencyPrefix: { type: String, default: '' },
    currencySuffix: { type: String, default: '' },
});

const p = props.product;
const selectedCycle = ref('monthly');
const selectedPayment = ref(props.paymentMethods?.[0]?.module || '');
const adding = ref(false);

// ─── Domain configuration ──────────────────────────────────
const domainOption = ref('own');       // 'register', 'transfer', 'own'
const domainInput = ref('');
const domainSearch = ref('');
const domainResults = ref([]);
const domainChecking = ref(false);
const selectedDomain = ref('');        // The chosen domain for this product

// Configurable options state
const configSelections = ref({});

// Custom fields state (keyed by field id)
const customFieldValues = ref({});

// ─── Server configuration (VPS / Dedicated) ────────────────
const serverHostname = ref('');
const serverRootPassword = ref('');
const serverNs1Prefix = ref('ns1');
const serverNs2Prefix = ref('ns2');
const showRootPassword = ref(false);

const cycles = [
    { key: 'monthly', label: 'Monthly' },
    { key: 'quarterly', label: 'Quarterly' },
    { key: 'semiannually', label: 'Semi-Annual' },
    { key: 'annually', label: 'Annual' },
    { key: 'biennially', label: 'Biennial' },
    { key: 'triennially', label: 'Triennial' },
];

function fmtPrice(amount) {
    if (amount === null || amount === undefined) return '';
    const num = parseFloat(amount);
    const formatted = num % 1 === 0 ? num.toFixed(0) : num.toFixed(2);
    if (props.currencyPrefix || props.currencySuffix) {
        return `${props.currencyPrefix}${formatted}${props.currencySuffix}`;
    }
    return formatCurrency(amount);
}

function getPrice(cycle) {
    return p.flatPricing?.[cycle] ?? null;
}

const availableCycles = computed(() => cycles.filter(c => getPrice(c.key) !== null));

// Auto-select first available cycle
if (availableCycles.value.length && !getPrice(selectedCycle.value)) {
    selectedCycle.value = availableCycles.value[0].key;
}

// ─── Config option helpers ─────────────────────────────────
function getOptionPrice(sub, cycle) {
    if (!sub?.cyclePricing) return sub?.pricing ?? null;
    return sub.cyclePricing[cycle] ?? sub.cyclePricing['monthly'] ?? sub.pricing ?? null;
}

function findSubOption(opt, subId) {
    return (opt.options?.option || []).find(s => s.id == subId);
}

// Calculate additional cost from configurable options based on current selections
const configOptionsTotal = computed(() => {
    let total = 0;
    for (const opt of props.configOptions) {
        const sel = configSelections.value[opt.id];
        if (sel === undefined || sel === '' || sel === null) continue;

        const type = opt.optiontype;
        // Dropdown (1) or Radio (4) — value is sub-option id
        if (type === '1' || type === 'dropdown' || type === '4' || type === 'radio') {
            const sub = findSubOption(opt, sel);
            if (sub) {
                const price = getOptionPrice(sub, selectedCycle.value);
                if (price) total += parseFloat(price);
            }
        }
        // Yes/No (2) — value is boolean/checkbox
        else if (type === '2' || type === 'yesno') {
            if (sel) {
                const sub = (opt.options?.option || [])[0];
                if (sub) {
                    const price = getOptionPrice(sub, selectedCycle.value);
                    if (price) total += parseFloat(price);
                }
            }
        }
        // Quantity (3) — value is qty number, price is per unit
        else if (type === '3' || type === 'quantity') {
            const qty = parseInt(sel) || 0;
            if (qty > 0) {
                const sub = (opt.options?.option || [])[0];
                if (sub) {
                    const price = getOptionPrice(sub, selectedCycle.value);
                    if (price) total += parseFloat(price) * qty;
                }
            }
        }
    }
    return total;
});

const basePrice = computed(() => getPrice(selectedCycle.value) ?? 0);
const orderTotal = computed(() => parseFloat(basePrice.value) + configOptionsTotal.value);

// Build config labels for cart display
function buildConfigLabels() {
    const labels = {};
    for (const opt of props.configOptions) {
        const sel = configSelections.value[opt.id];
        if (sel === undefined || sel === '' || sel === null) continue;

        const type = opt.optiontype;
        if (type === '1' || type === 'dropdown' || type === '4' || type === 'radio') {
            const sub = findSubOption(opt, sel);
            if (sub) labels[opt.id] = { name: opt.optionname, value: sub.optionname, price: getOptionPrice(sub, selectedCycle.value) };
        } else if (type === '2' || type === 'yesno') {
            if (sel) {
                const sub = (opt.options?.option || [])[0];
                labels[opt.id] = { name: opt.optionname, value: 'Yes', price: sub ? getOptionPrice(sub, selectedCycle.value) : 0 };
            }
        } else if (type === '3' || type === 'quantity') {
            const qty = parseInt(sel) || 0;
            if (qty > 0) {
                const sub = (opt.options?.option || [])[0];
                labels[opt.id] = { name: opt.optionname, value: `${qty}`, price: sub ? (getOptionPrice(sub, selectedCycle.value) * qty) : 0 };
            }
        }
    }
    return labels;
}

// Domain validation
const domainValid = computed(() => {
    if (!props.requiresDomain) return true;
    if (domainOption.value === 'own') {
        return domainInput.value.trim().length > 3 && domainInput.value.includes('.');
    }
    if (domainOption.value === 'register' || domainOption.value === 'transfer') {
        return selectedDomain.value.length > 0;
    }
    return false;
});

const effectiveDomain = computed(() => {
    if (!props.requiresDomain) return '';
    if (domainOption.value === 'own') return domainInput.value.trim();
    return selectedDomain.value;
});

async function checkDomain() {
    const q = domainSearch.value.trim();
    if (!q) return;
    domainChecking.value = true;
    domainResults.value = [];
    try {
        const resp = await fetch(route('client.orders.domain.check'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ domain: q }),
        });
        const data = await resp.json();
        domainResults.value = data.results || [];
    } catch {
        domainResults.value = [];
    } finally {
        domainChecking.value = false;
    }
}

function selectDomain(domain) {
    selectedDomain.value = domain;
}

// Server config validation
const serverConfigValid = computed(() => {
    if (!props.requiresServerConfig) return true;
    return serverHostname.value.trim().length >= 3 && serverRootPassword.value.trim().length >= 6;
});

function addToCart() {
    if (props.requiresDomain && !domainValid.value) return;
    if (!serverConfigValid.value) return;
    adding.value = true;
    router.post(route('client.orders.cart.add'), {
        pid: p.pid,
        billingcycle: selectedCycle.value,
        name: p.name,
        price: String(orderTotal.value.toFixed(2)),
        domain: effectiveDomain.value,
        configoptions: configSelections.value,
        configlabels: buildConfigLabels(),
        customfields: customFieldValues.value,
        hostname: serverHostname.value.trim() || null,
        rootpw: serverRootPassword.value.trim() || null,
        ns1prefix: serverNs1Prefix.value.trim() || 'ns1',
        ns2prefix: serverNs2Prefix.value.trim() || 'ns2',
    }, {
        onFinish: () => adding.value = false,
    });
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('client.orders.products')"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </Link>
                <h1 class="text-lg font-bold text-gray-900">{{ p.name }}</h1>
            </div>
        </template>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left: Description + Domain Config + Config Options -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Description -->
                <Card title="Description">
                    <div class="text-[13px] text-gray-700 prose prose-sm max-w-none" v-html="p.description" />
                </Card>

                <!-- Features -->
                <Card v-if="p.features?.length" title="Features">
                    <ul class="space-y-2">
                        <li v-for="(f, i) in p.features" :key="i" class="flex items-start gap-2 text-[13px] text-gray-700">
                            <svg class="w-4 h-4 text-emerald-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            {{ f }}
                        </li>
                    </ul>
                </Card>

                <!-- Domain Configuration (for hosting products) -->
                <Card v-if="requiresDomain" title="Domain Configuration">
                    <p class="text-[12.5px] text-gray-500 mb-4">A domain is required for this hosting product. Choose one of the options below.</p>

                    <!-- Domain option tabs -->
                    <div class="flex gap-2 mb-5 flex-wrap">
                        <button @click="domainOption = 'register'"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 text-[13px] font-medium rounded-lg transition-all"
                            :class="domainOption === 'register'
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'bg-gray-50 text-gray-600 border border-gray-200 hover:bg-gray-100'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            Register New Domain
                        </button>
                        <button @click="domainOption = 'transfer'"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 text-[13px] font-medium rounded-lg transition-all"
                            :class="domainOption === 'transfer'
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'bg-gray-50 text-gray-600 border border-gray-200 hover:bg-gray-100'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                            Transfer Domain
                        </button>
                        <button @click="domainOption = 'own'"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 text-[13px] font-medium rounded-lg transition-all"
                            :class="domainOption === 'own'
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'bg-gray-50 text-gray-600 border border-gray-200 hover:bg-gray-100'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9" /></svg>
                            Use My Own Domain
                        </button>
                    </div>

                    <!-- Register New Domain -->
                    <div v-if="domainOption === 'register'">
                        <div class="flex gap-2 mb-4">
                            <input v-model="domainSearch" type="text" placeholder="Search for a domain, e.g. example.com"
                                @keyup.enter="checkDomain"
                                class="flex-1 text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                            <button @click="checkDomain" :disabled="domainChecking || !domainSearch.trim()"
                                class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                                <svg v-if="domainChecking" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                Search
                            </button>
                        </div>

                        <!-- Domain results -->
                        <div v-if="domainResults.length" class="space-y-2 max-h-64 overflow-y-auto">
                            <div v-for="(r, i) in domainResults" :key="i"
                                @click="r.status === 'available' ? selectDomain(r.domain) : null"
                                class="flex items-center justify-between p-3 rounded-lg border transition-all"
                                :class="{
                                    'border-emerald-300 bg-emerald-50 cursor-pointer hover:border-emerald-400': r.status === 'available' && selectedDomain !== r.domain,
                                    'border-indigo-400 bg-indigo-50 ring-2 ring-indigo-200': selectedDomain === r.domain,
                                    'border-gray-200 bg-gray-50 opacity-60 cursor-not-allowed': r.status !== 'available',
                                }">
                                <div class="flex items-center gap-2.5">
                                    <span v-if="r.status === 'available'" class="flex-shrink-0 w-5 h-5 rounded-full bg-emerald-500 flex items-center justify-center">
                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                    </span>
                                    <span v-else class="flex-shrink-0 w-5 h-5 rounded-full bg-red-400 flex items-center justify-center">
                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </span>
                                    <span class="text-[13px] font-medium text-gray-900">{{ r.domain }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[12px] font-medium"
                                        :class="r.status === 'available' ? 'text-emerald-600' : 'text-red-500'">
                                        {{ r.status === 'available' ? 'Available' : 'Unavailable' }}
                                    </span>
                                    <span v-if="selectedDomain === r.domain" class="text-[11px] font-semibold text-indigo-600 bg-indigo-100 px-2 py-0.5 rounded-full">Selected</span>
                                </div>
                            </div>
                        </div>

                        <p v-if="domainResults.length === 0 && !domainChecking" class="text-[12.5px] text-gray-400 mt-2">
                            Search for a domain to check availability and register with your hosting.
                        </p>
                    </div>

                    <!-- Transfer Domain -->
                    <div v-if="domainOption === 'transfer'">
                        <div class="flex gap-2 mb-4">
                            <input v-model="domainSearch" type="text" placeholder="Enter domain to transfer, e.g. example.com"
                                @keyup.enter="selectDomain(domainSearch.trim())"
                                class="flex-1 text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                            <button @click="selectDomain(domainSearch.trim())" :disabled="!domainSearch.trim() || !domainSearch.includes('.')"
                                class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                                Use Domain
                            </button>
                        </div>
                        <div v-if="selectedDomain" class="flex items-center gap-2 p-3 rounded-lg bg-indigo-50 border border-indigo-200">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            <span class="text-[13px] text-gray-900">{{ selectedDomain }}</span>
                            <span class="text-[11px] font-semibold text-indigo-600 bg-indigo-100 px-2 py-0.5 rounded-full ml-auto">Transfer</span>
                        </div>
                        <p class="text-[12px] text-gray-400 mt-2">Enter the domain you wish to transfer to us. You'll need the EPP/auth code from your current registrar.</p>
                    </div>

                    <!-- Use Existing Domain -->
                    <div v-if="domainOption === 'own'">
                        <input v-model="domainInput" type="text" placeholder="Enter your existing domain, e.g. example.com"
                            class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                        <p class="text-[12px] text-gray-400 mt-2">Enter a domain you already own. You'll need to update your DNS/nameservers to point to our servers.</p>
                        <div v-if="domainInput.trim() && domainInput.includes('.')" class="flex items-center gap-2 mt-3 p-3 rounded-lg bg-emerald-50 border border-emerald-200">
                            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            <span class="text-[13px] text-gray-700">{{ domainInput.trim() }}</span>
                        </div>
                    </div>
                </Card>

                <!-- Server Configuration (VPS / Dedicated Server) -->
                <Card v-if="requiresServerConfig" title="Configure Server">
                    <p class="text-[12.5px] text-gray-500 mb-4">Configure your server settings below. Hostname and root password are required.</p>

                    <div class="space-y-4">
                        <!-- Hostname -->
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5">
                                Hostname <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2" /></svg>
                                </div>
                                <input v-model="serverHostname" type="text" placeholder="e.g. server1.example.com"
                                    class="pl-10 w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <p class="text-[11.5px] text-gray-400 mt-1">A fully qualified domain name for your server (e.g. vps1.yourdomain.com)</p>
                        </div>

                        <!-- Root Password -->
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5">
                                Root Password <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                </div>
                                <input v-model="serverRootPassword" :type="showRootPassword ? 'text' : 'password'"
                                    placeholder="Minimum 6 characters"
                                    class="pl-10 pr-10 w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                                <button type="button" @click="showRootPassword = !showRootPassword"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                    <svg v-if="showRootPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.59 6.59m7.532 7.532l3.29 3.29M3 3l18 18" /></svg>
                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </button>
                            </div>
                            <p class="text-[11.5px] text-gray-400 mt-1">The root/admin password for your server</p>
                        </div>

                        <!-- NS Prefixes in a row -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">NS1 Prefix</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9" /></svg>
                                    </div>
                                    <input v-model="serverNs1Prefix" type="text" placeholder="ns1"
                                        class="pl-10 w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                                <p class="text-[11.5px] text-gray-400 mt-1">e.g. <strong>ns1</strong>.hostname.com</p>
                            </div>
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">NS2 Prefix</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9" /></svg>
                                    </div>
                                    <input v-model="serverNs2Prefix" type="text" placeholder="ns2"
                                        class="pl-10 w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                                <p class="text-[11.5px] text-gray-400 mt-1">e.g. <strong>ns2</strong>.hostname.com</p>
                            </div>
                        </div>

                        <!-- Hostname Preview -->
                        <div v-if="serverHostname.trim()" class="p-3 rounded-lg bg-gray-50 border border-gray-200">
                            <p class="text-[12px] font-medium text-gray-500 mb-1.5">Nameserver Preview</p>
                            <div class="space-y-1">
                                <p class="text-[13px] text-gray-700 font-mono">{{ serverNs1Prefix || 'ns1' }}.{{ serverHostname.trim() }}</p>
                                <p class="text-[13px] text-gray-700 font-mono">{{ serverNs2Prefix || 'ns2' }}.{{ serverHostname.trim() }}</p>
                            </div>
                        </div>
                    </div>
                </Card>

                <!-- Custom Fields (e.g. additional product-specific fields from WHMCS) -->
                <Card v-if="customFields.length" title="Additional Information">
                    <div class="space-y-4">
                        <div v-for="cf in customFields" :key="cf.id">
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5">
                                {{ cf.name }}
                                <span v-if="cf.required" class="text-red-500">*</span>
                            </label>
                            <input v-model="customFieldValues[cf.id]" type="text"
                                :placeholder="cf.description || 'Enter value...'"
                                :required="cf.required"
                                class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                            <p v-if="cf.description" class="text-[11.5px] text-gray-400 mt-1">{{ cf.description }}</p>
                        </div>
                    </div>
                </Card>

                <!-- Configurable Options -->
                <Card v-if="configOptions.length" title="Configure Your Plan">
                    <p class="text-[12.5px] text-gray-500 mb-4">Customize your plan with additional resources and features.</p>
                    <div class="space-y-5">
                        <div v-for="opt in configOptions" :key="opt.id" class="p-4 rounded-xl bg-gray-50/70 border border-gray-200">
                            <label class="block text-[13px] font-semibold text-gray-800 mb-2">{{ opt.optionname }}</label>

                            <!-- Dropdown type (1) -->
                            <div v-if="opt.optiontype === '1' || opt.optiontype === 'dropdown'">
                                <div class="space-y-1.5">
                                    <label v-for="sub in (opt.options?.option || [])" :key="sub.id"
                                        class="flex items-center justify-between p-3 rounded-lg border cursor-pointer transition-all"
                                        :class="configSelections[opt.id] == sub.id ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-gray-200 hover:border-gray-300 bg-white'">
                                        <div class="flex items-center gap-3">
                                            <input type="radio" :name="'config_' + opt.id" :value="sub.id" v-model="configSelections[opt.id]" class="text-indigo-600 focus:ring-indigo-500" />
                                            <span class="text-[13px] text-gray-800">{{ sub.optionname }}</span>
                                        </div>
                                        <span v-if="getOptionPrice(sub, selectedCycle)" class="text-[12px] font-semibold text-gray-600">
                                            <template v-if="parseFloat(getOptionPrice(sub, selectedCycle)) > 0">+ {{ fmtPrice(getOptionPrice(sub, selectedCycle)) }}</template>
                                            <template v-else>Included</template>
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <!-- Quantity type (3) — e.g. Additional Space -->
                            <div v-else-if="opt.optiontype === '3' || opt.optiontype === 'quantity'">
                                <div v-for="sub in (opt.options?.option || [])" :key="sub.id">
                                    <div class="flex items-center gap-4">
                                        <div class="flex items-center bg-white border border-gray-300 rounded-lg overflow-hidden">
                                            <button type="button" @click="configSelections[opt.id] = Math.max((parseInt(opt.minqty) || 0), (parseInt(configSelections[opt.id]) || 0) - 1)"
                                                class="px-3 py-2 text-gray-500 hover:bg-gray-100 transition-colors text-[14px] font-bold">−</button>
                                            <input type="number" v-model="configSelections[opt.id]"
                                                :min="opt.minqty || 0" :max="opt.maxqty || 9999"
                                                class="w-16 text-center text-[13px] border-0 focus:ring-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                                            <button type="button" @click="configSelections[opt.id] = Math.min((parseInt(opt.maxqty) || 9999), (parseInt(configSelections[opt.id]) || 0) + 1)"
                                                class="px-3 py-2 text-gray-500 hover:bg-gray-100 transition-colors text-[14px] font-bold">+</button>
                                        </div>
                                        <div class="text-[12px] text-gray-500">
                                            <span v-if="getOptionPrice(sub, selectedCycle)" class="font-medium text-gray-700">{{ fmtPrice(getOptionPrice(sub, selectedCycle)) }}</span> per unit / {{ selectedCycle }}
                                        </div>
                                    </div>
                                    <div v-if="(parseInt(configSelections[opt.id]) || 0) > 0" class="mt-2 text-[12px] text-indigo-600 font-medium">
                                        {{ configSelections[opt.id] }} × {{ fmtPrice(getOptionPrice(sub, selectedCycle)) }} = {{ fmtPrice((parseInt(configSelections[opt.id]) || 0) * parseFloat(getOptionPrice(sub, selectedCycle) || 0)) }}
                                    </div>
                                    <div v-if="opt.minqty || opt.maxqty" class="mt-1 text-[11px] text-gray-400">
                                        <template v-if="opt.minqty && opt.maxqty">Min: {{ opt.minqty }}, Max: {{ opt.maxqty }}</template>
                                        <template v-else-if="opt.maxqty">Max: {{ opt.maxqty }}</template>
                                    </div>
                                </div>
                            </div>

                            <!-- Yes/No type (2) -->
                            <div v-else-if="opt.optiontype === '2' || opt.optiontype === 'yesno'">
                                <label v-for="sub in (opt.options?.option || [])" :key="sub.id"
                                    class="flex items-center justify-between p-3 rounded-lg border cursor-pointer transition-all"
                                    :class="configSelections[opt.id] ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-gray-200 bg-white hover:border-gray-300'">
                                    <div class="flex items-center gap-3">
                                        <input v-model="configSelections[opt.id]" type="checkbox"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        <span class="text-[13px] text-gray-800">{{ sub.optionname || 'Enable' }}</span>
                                    </div>
                                    <span v-if="getOptionPrice(sub, selectedCycle) && parseFloat(getOptionPrice(sub, selectedCycle)) > 0" class="text-[12px] font-semibold text-gray-600">+ {{ fmtPrice(getOptionPrice(sub, selectedCycle)) }}</span>
                                </label>
                            </div>

                            <!-- Radio type (4) -->
                            <div v-else-if="opt.optiontype === '4' || opt.optiontype === 'radio'" class="space-y-1.5">
                                <label v-for="sub in (opt.options?.option || [])" :key="sub.id"
                                    class="flex items-center justify-between p-3 rounded-lg border cursor-pointer transition-all"
                                    :class="configSelections[opt.id] == sub.id ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-gray-200 hover:border-gray-300 bg-white'">
                                    <div class="flex items-center gap-3">
                                        <input v-model="configSelections[opt.id]" type="radio" :value="sub.id"
                                            class="text-indigo-600 focus:ring-indigo-500" />
                                        <span class="text-[13px] text-gray-800">{{ sub.optionname }}</span>
                                    </div>
                                    <span v-if="getOptionPrice(sub, selectedCycle)" class="text-[12px] font-semibold text-gray-600">
                                        <template v-if="parseFloat(getOptionPrice(sub, selectedCycle)) > 0">+ {{ fmtPrice(getOptionPrice(sub, selectedCycle)) }}</template>
                                        <template v-else>Included</template>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>

            <!-- Right sidebar: Pricing + Actions -->
            <div class="space-y-4">
                <!-- Pricing Card -->
                <Card title="Billing Cycle">
                    <div class="space-y-2">
                        <template v-for="c in cycles" :key="c.key">
                            <label v-if="getPrice(c.key) !== null" class="flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors"
                                :class="selectedCycle === c.key ? 'bg-indigo-50 ring-1 ring-indigo-200' : 'hover:bg-gray-50'">
                                <input type="radio" v-model="selectedCycle" :value="c.key" class="text-indigo-600 focus:ring-indigo-500" />
                                <span class="flex-1 text-[13px] text-gray-900">{{ c.label }}</span>
                                <span class="text-[13px] font-semibold text-gray-900">{{ fmtPrice(getPrice(c.key)) }}</span>
                            </label>
                        </template>
                        <p v-if="availableCycles.length === 0" class="text-[13px] text-gray-500 py-2">Contact us for pricing.</p>
                    </div>
                </Card>

                <!-- Order Summary with config options breakdown -->
                <Card title="Order Summary">
                    <dl class="space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">{{ p.name }}</dt>
                            <dd class="text-[13px] font-medium text-gray-900">{{ fmtPrice(basePrice) }}</dd>
                        </div>
                        <!-- Config options line items -->
                        <template v-for="opt in configOptions" :key="'summary_' + opt.id">
                            <div v-if="configSelections[opt.id] && (() => { const l = buildConfigLabels()[opt.id]; return l && parseFloat(l.price) > 0; })()"
                                class="flex justify-between">
                                <dt class="text-[12px] text-gray-500 pl-2">
                                    + {{ buildConfigLabels()[opt.id]?.name }}
                                    <span class="text-gray-400">({{ buildConfigLabels()[opt.id]?.value }})</span>
                                </dt>
                                <dd class="text-[12px] font-medium text-gray-700">{{ fmtPrice(buildConfigLabels()[opt.id]?.price) }}</dd>
                            </div>
                        </template>
                        <div class="border-t border-gray-200 pt-2 flex justify-between">
                            <dt class="text-[14px] font-semibold text-gray-900">Total</dt>
                            <dd class="text-[16px] font-bold text-gray-900">{{ fmtPrice(orderTotal) }}<span class="text-[11px] text-gray-500 font-normal"> / {{ selectedCycle }}</span></dd>
                        </div>
                    </dl>
                </Card>

                <!-- Payment Method -->
                <Card v-if="paymentMethods?.length" title="Payment Method">
                    <select v-model="selectedPayment" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option v-for="pm in paymentMethods" :key="pm.module" :value="pm.module">{{ pm.displayname }}</option>
                    </select>
                </Card>

                <!-- Domain summary (if domain product) -->
                <Card v-if="requiresDomain && effectiveDomain" title="Domain">
                    <div class="flex items-center gap-2 text-[13px]">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9" /></svg>
                        <span class="font-medium text-gray-900">{{ effectiveDomain }}</span>
                    </div>
                </Card>

                <!-- Domain required warning -->
                <div v-if="requiresDomain && !domainValid" class="flex items-start gap-2 p-3 rounded-lg bg-amber-50 border border-amber-200">
                    <svg class="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
                    <p class="text-[12px] text-amber-700">Please configure a domain above before adding to cart.</p>
                </div>

                <!-- Server config warning -->
                <div v-if="requiresServerConfig && !serverConfigValid" class="flex items-start gap-2 p-3 rounded-lg bg-amber-50 border border-amber-200">
                    <svg class="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
                    <p class="text-[12px] text-amber-700">Please set a hostname (min 3 chars) and root password (min 6 chars) above.</p>
                </div>

                <!-- Add to Cart -->
                <button @click="addToCart" :disabled="adding || (requiresDomain && !domainValid) || !serverConfigValid"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 text-[14px] font-medium text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm">
                    <svg v-if="adding" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" /></svg>
                    {{ adding ? 'Adding...' : 'Add to Cart' }}
                </button>
            </div>
        </div>
    </ClientLayout>
</template>
