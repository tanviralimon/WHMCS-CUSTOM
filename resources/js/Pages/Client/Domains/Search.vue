<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';
import CurrencySwitcher from '@/Components/CurrencySwitcher.vue';

const props = defineProps({
    query: { type: String, default: '' },
    result: { type: Object, default: null },
    tlds: { type: Object, default: () => ({}) },
    currencies: { type: Array, default: () => [] },
    activeCurrency: { type: Object, default: null },
    currencyId: { type: Number, default: 1 },
});

const searchDomain = ref(props.query || '');
const searching = ref(false);
const results = ref([]);
const hasSearched = ref(false);
const addingToCart = ref({});
const addedToCart = ref({});

const currencyPrefix = computed(() => props.activeCurrency?.prefix || '$');
const currencySuffix = computed(() => props.activeCurrency?.suffix || '');

// Popular TLD list for quick suggestions
const popularTlds = computed(() => {
    const popular = ['.com', '.net', '.org', '.io', '.dev', '.co', '.info', '.xyz', '.online', '.tech'];
    return popular.filter(t => props.tlds[t.substring(1)] || props.tlds[t]);
});

function formatPrice(price) {
    if (!price || price === '-1.00' || price === '-1' || parseFloat(price) < 0) return null;
    return currencyPrefix.value + parseFloat(price).toFixed(2) + currencySuffix.value;
}

function getTldPrice(domain) {
    const parts = domain.split('.');
    if (parts.length < 2) return null;
    const tld = parts.slice(1).join('.');
    const tldData = props.tlds[tld] || props.tlds['.' + tld];
    if (!tldData) return null;
    return formatPrice(tldData.register);
}

function getRawPrice(domain) {
    const parts = domain.split('.');
    if (parts.length < 2) return null;
    const tld = parts.slice(1).join('.');
    const tldData = props.tlds[tld] || props.tlds['.' + tld];
    if (!tldData) return null;
    const price = tldData.register;
    if (!price || price === '-1.00' || price === '-1' || parseFloat(price) < 0) return null;
    return parseFloat(price).toFixed(2);
}

async function doSearch() {
    const q = searchDomain.value.trim();
    if (!q) return;

    searching.value = true;
    hasSearched.value = true;
    results.value = [];

    try {
        const response = await fetch(route('client.domains.check'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ domain: q }),
        });
        const data = await response.json();
        results.value = data.results || [];
    } catch (err) {
        console.error('Domain check failed:', err);
        results.value = [];
    } finally {
        searching.value = false;
    }
}

async function addDomainToCart(domain, type = 'register') {
    const price = getRawPrice(domain);
    if (!price) return;

    addingToCart.value[domain] = true;
    try {
        const response = await fetch(route('client.domains.cart.add'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                domain,
                type,
                years: 1,
                price: price,
            }),
        });
        const data = await response.json();
        if (data.success) {
            addedToCart.value[domain] = true;
        }
    } catch (err) {
        console.error('Add to cart failed:', err);
    } finally {
        addingToCart.value[domain] = false;
    }
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <h1 class="text-lg font-bold text-gray-900">Domain Search</h1>
                <p class="text-[13px] text-gray-500">Find and register your perfect domain name</p>
            </div>
        </template>
        <template #actions>
            <CurrencySwitcher />
            <Link :href="route('client.domains.pricing')" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium text-gray-700 bg-white rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                View Pricing
            </Link>
            <Link :href="route('client.orders.cart')" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>
                Cart
            </Link>
        </template>

        <!-- Search Bar -->
        <Card class="mb-6">
            <form @submit.prevent="doSearch" class="flex gap-3">
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                    </div>
                    <input
                        v-model="searchDomain"
                        type="text"
                        placeholder="Enter your domain name (e.g. example.com or just example)"
                        class="w-full pl-10 pr-4 py-3 text-[14px] rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50"
                        @keyup.enter="doSearch"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="searching || !searchDomain.trim()"
                    class="px-6 py-3 text-[14px] font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition-colors shadow-sm disabled:opacity-50 flex items-center gap-2"
                >
                    <svg v-if="searching" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
                    {{ searching ? 'Searching...' : 'Search' }}
                </button>
            </form>
            <p class="mt-2 text-[12px] text-gray-400">Enter a domain name with extension (e.g. example.com) or without to check multiple TLDs</p>
        </Card>

        <!-- Loading -->
        <div v-if="searching" class="flex items-center justify-center py-16">
            <div class="flex flex-col items-center gap-3">
                <svg class="w-8 h-8 text-indigo-600 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
                <p class="text-[13px] text-gray-500">Checking domain availability...</p>
            </div>
        </div>

        <!-- Results -->
        <div v-else-if="hasSearched && results.length > 0" class="space-y-3">
            <h2 class="text-[14px] font-semibold text-gray-800 mb-4">
                {{ results.length }} result{{ results.length !== 1 ? 's' : '' }} found
            </h2>

            <div v-for="r in results" :key="r.domain"
                class="flex items-center justify-between p-4 bg-white rounded-xl border transition-all hover:shadow-sm"
                :class="r.status === 'available' ? 'border-emerald-200' : 'border-gray-200'"
            >
                <div class="flex items-center gap-3">
                    <!-- Status icon -->
                    <div :class="[
                        'w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0',
                        r.status === 'available' ? 'bg-emerald-100' : 'bg-red-100'
                    ]">
                        <svg v-if="r.status === 'available'" class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <svg v-else class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </div>

                    <div>
                        <p class="text-[14px] font-semibold text-gray-900">{{ r.domain }}</p>
                        <p class="text-[12px]" :class="r.status === 'available' ? 'text-emerald-600' : 'text-red-500'">
                            {{ r.status === 'available' ? 'Available!' : 'Already registered' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Price -->
                    <span v-if="r.status === 'available' && getTldPrice(r.domain)" class="text-[15px] font-bold text-gray-900">
                        {{ getTldPrice(r.domain) }}<span class="text-[11px] font-normal text-gray-400">/yr</span>
                    </span>

                    <!-- Add to cart button -->
                    <button
                        v-if="r.status === 'available' && getRawPrice(r.domain)"
                        @click="addDomainToCart(r.domain)"
                        :disabled="addingToCart[r.domain] || addedToCart[r.domain]"
                        :class="[
                            'inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium rounded-lg transition-all shadow-sm',
                            addedToCart[r.domain]
                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 cursor-default'
                                : 'text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50'
                        ]"
                    >
                        <svg v-if="addingToCart[r.domain]" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                        <svg v-else-if="addedToCart[r.domain]" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        {{ addedToCart[r.domain] ? 'Added' : addingToCart[r.domain] ? 'Adding...' : 'Add to Cart' }}
                    </button>

                    <!-- Transfer option for registered domains -->
                    <span v-if="r.status !== 'available'" class="text-[12px] text-gray-400">Transfer not available</span>
                </div>
            </div>
        </div>

        <!-- No results -->
        <div v-else-if="hasSearched && results.length === 0 && !searching" class="text-center py-16">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3" />
            </svg>
            <p class="text-[14px] text-gray-500">Could not check availability. Please try again.</p>
        </div>

        <!-- Popular TLDs (before search) -->
        <div v-if="!hasSearched && Object.keys(tlds).length > 0" class="mt-2">
            <h2 class="text-[14px] font-semibold text-gray-800 mb-4">Popular Domain Extensions</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                <div v-for="(data, tld) in tlds" :key="tld"
                    v-show="['.com','.net','.org','.io','.dev','.co','.info','.xyz','.online','.tech','.store','.app','.site','.club','.me'].includes('.' + tld)"
                    class="bg-white rounded-xl border border-gray-200 p-4 text-center hover:border-indigo-200 hover:shadow-sm transition-all"
                >
                    <p class="text-[15px] font-bold text-indigo-600">.{{ tld }}</p>
                    <p v-if="formatPrice(data.register)" class="text-[13px] font-semibold text-gray-900 mt-1">{{ formatPrice(data.register) }}<span class="text-[11px] font-normal text-gray-400">/yr</span></p>
                    <p v-else class="text-[12px] text-gray-400 mt-1">N/A</p>
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
