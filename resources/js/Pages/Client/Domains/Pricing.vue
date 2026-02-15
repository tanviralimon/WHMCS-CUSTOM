<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';
import CurrencySwitcher from '@/Components/CurrencySwitcher.vue';

const props = defineProps({
    pricing: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    activeCurrency: { type: Object, default: null },
    currencyId: { type: Number, default: 1 },
});

const searchFilter = ref('');
const activeCategory = ref('all');

const currencyPrefix = computed(() => props.activeCurrency?.prefix || '$');
const currencySuffix = computed(() => props.activeCurrency?.suffix || '');

// Group TLDs by category
const categories = computed(() => {
    const cats = {
        all: { label: 'All', icon: '🌐' },
        popular: { label: 'Popular', icon: '⭐' },
        country: { label: 'Country', icon: '🏳️' },
        new: { label: 'New gTLDs', icon: '✨' },
    };
    return cats;
});

const popularTlds = ['.com', '.net', '.org', '.io', '.dev', '.co', '.biz', '.info', '.me', '.us', '.uk', '.ca', '.de', '.fr', '.eu'];
const countryTlds = ['.us', '.uk', '.ca', '.de', '.fr', '.eu', '.au', '.jp', '.cn', '.in', '.br', '.ru', '.nl', '.it', '.es', '.ch', '.at', '.be', '.se', '.no', '.dk', '.fi', '.nz', '.za', '.mx', '.ar', '.cl', '.co', '.pe', '.sg', '.hk', '.tw', '.kr', '.th', '.my', '.ph', '.id', '.vn'];

function categorize(tld) {
    const dotTld = '.' + tld;
    if (popularTlds.includes(dotTld)) return 'popular';
    if (countryTlds.includes(dotTld) || tld.length === 2) return 'country';
    return 'new';
}

const filteredPricing = computed(() => {
    let list = props.pricing;

    // Category filter
    if (activeCategory.value !== 'all') {
        list = list.filter(p => categorize(p.tld) === activeCategory.value);
    }

    // Text filter
    if (searchFilter.value.trim()) {
        const q = searchFilter.value.toLowerCase().replace(/^\./, '');
        list = list.filter(p => p.tld.toLowerCase().includes(q));
    }

    return list;
});

function formatPrice(price) {
    if (!price || price === '-1.00' || price === '-1' || parseFloat(price) < 0) return '—';
    return currencyPrefix.value + parseFloat(price).toFixed(2) + currencySuffix.value;
}

function getFirstPrice(priceObj) {
    if (!priceObj || typeof priceObj !== 'object') return null;
    const keys = Object.keys(priceObj);
    if (keys.length === 0) return null;
    return priceObj[keys[0]];
}

function isAvailable(priceObj) {
    const price = getFirstPrice(priceObj);
    return price && price !== '-1.00' && price !== '-1' && parseFloat(price) >= 0;
}

function switchCurrency(id) {
    router.get(route('client.domains.pricing'), { currency: id }, { preserveState: false });
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <h1 class="text-lg font-bold text-gray-900">Domain Pricing</h1>
                <p class="text-[13px] text-gray-500">{{ pricing.length }} domain extensions available</p>
            </div>
        </template>
        <template #actions>
            <CurrencySwitcher />
            <Link :href="route('client.domains.search')" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                Search Domains
            </Link>
        </template>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 mb-6">
            <!-- Category tabs -->
            <div class="flex gap-1.5 bg-gray-100 p-1 rounded-xl">
                <button
                    v-for="(cat, key) in categories" :key="key"
                    @click="activeCategory = key"
                    :class="[
                        'px-3 py-1.5 text-[12px] font-medium rounded-lg transition-colors',
                        activeCategory === key
                            ? 'bg-white text-indigo-700 shadow-sm'
                            : 'text-gray-500 hover:text-gray-700'
                    ]"
                >
                    {{ cat.icon }} {{ cat.label }}
                </button>
            </div>

            <!-- Search filter -->
            <div class="relative flex-1 min-w-[200px] max-w-xs">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                </div>
                <input
                    v-model="searchFilter"
                    type="text"
                    placeholder="Filter by extension..."
                    class="w-full pl-9 pr-4 py-2 text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <span class="text-[12px] text-gray-400">{{ filteredPricing.length }} extension{{ filteredPricing.length !== 1 ? 's' : '' }}</span>
        </div>

        <!-- Pricing Table -->
        <div v-if="filteredPricing.length > 0" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50">
                            <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">TLD</th>
                            <th class="text-right px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Register</th>
                            <th class="text-right px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Transfer</th>
                            <th class="text-right px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Renew</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in filteredPricing" :key="p.tld" class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="text-[14px] font-bold text-indigo-600">.{{ p.tld }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <span v-if="isAvailable(p.register)" class="text-[13px] font-semibold text-gray-900">
                                    {{ formatPrice(getFirstPrice(p.register)) }}
                                </span>
                                <span v-else class="text-[12px] text-gray-400">N/A</span>
                            </td>
                            <td class="px-5 py-3.5 text-right hidden md:table-cell">
                                <span v-if="isAvailable(p.transfer)" class="text-[13px] text-gray-600">
                                    {{ formatPrice(getFirstPrice(p.transfer)) }}
                                </span>
                                <span v-else class="text-[12px] text-gray-400">N/A</span>
                            </td>
                            <td class="px-5 py-3.5 text-right hidden md:table-cell">
                                <span v-if="isAvailable(p.renew)" class="text-[13px] text-gray-600">
                                    {{ formatPrice(getFirstPrice(p.renew)) }}
                                </span>
                                <span v-else class="text-[12px] text-gray-400">N/A</span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <Link
                                    v-if="isAvailable(p.register)"
                                    :href="route('client.domains.search') + '?domain=.'+p.tld"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-[12px] font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors"
                                >
                                    Register →
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="text-center py-16 bg-white rounded-xl border border-gray-200">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-[14px] text-gray-500">No matching extensions found.</p>
            <button @click="searchFilter = ''; activeCategory = 'all'" class="mt-2 text-[13px] text-indigo-600 hover:text-indigo-700">Clear filters</button>
        </div>

        <!-- Multi-year pricing info -->
        <p class="mt-4 text-[12px] text-gray-400 text-center">
            Prices shown are for 1-year registration. Contact us for multi-year discounts.
            Currency: <strong>{{ activeCurrency?.code || 'USD' }}</strong>
        </p>
    </ClientLayout>
</template>
