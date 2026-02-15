<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';

const props = defineProps({
    product: Object,
    paymentMethods: Array,
});

const p = props.product;
const selectedCycle = ref('monthly');
const selectedPayment = ref(props.paymentMethods?.[0]?.module || '');
const adding = ref(false);

const cycles = [
    { key: 'monthly', label: 'Monthly' },
    { key: 'quarterly', label: 'Quarterly' },
    { key: 'semiannually', label: 'Semi-Annual' },
    { key: 'annually', label: 'Annual' },
    { key: 'biennially', label: 'Biennial' },
    { key: 'triennially', label: 'Triennial' },
];

function getPrice(cycle) {
    return p.pricing?.[cycle] || null;
}

function addToCart() {
    adding.value = true;
    router.post(route('client.orders.cart.add'), {
        pid: p.pid,
        billingcycle: selectedCycle.value,
        name: p.name,
        price: getPrice(selectedCycle.value) || '0.00',
        domain: '',
    }, {
        onFinish: () => adding.value = false,
    });
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">{{ p.name }}</h1>
        </template>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
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
            </div>

            <div class="space-y-4">
                <!-- Pricing Card -->
                <Card title="Pricing">
                    <div class="space-y-2">
                        <template v-for="c in cycles" :key="c.key">
                            <label v-if="getPrice(c.key)" class="flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors"
                                :class="selectedCycle === c.key ? 'bg-indigo-50 ring-1 ring-indigo-200' : 'hover:bg-gray-50'">
                                <input type="radio" v-model="selectedCycle" :value="c.key" class="text-indigo-600 focus:ring-indigo-500" />
                                <span class="flex-1 text-[13px] text-gray-900">{{ c.label }}</span>
                                <span class="text-[13px] font-semibold text-gray-900">${{ getPrice(c.key) }}</span>
                            </label>
                        </template>
                    </div>
                </Card>

                <!-- Payment Method -->
                <Card v-if="paymentMethods?.length" title="Payment Method">
                    <select v-model="selectedPayment" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option v-for="pm in paymentMethods" :key="pm.module" :value="pm.module">{{ pm.displayname }}</option>
                    </select>
                </Card>

                <!-- Add to Cart -->
                <button @click="addToCart" :disabled="adding"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 text-[14px] font-medium text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" /></svg>
                    Add to Cart
                </button>
            </div>
        </div>
    </ClientLayout>
</template>
