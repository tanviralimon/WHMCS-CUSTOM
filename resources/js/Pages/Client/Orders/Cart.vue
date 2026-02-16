<script setup>
import { ref, computed } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useCurrency } from '@/Composables/useCurrency.js';

const { formatCurrency } = useCurrency();

const props = defineProps({
    cart: Object,
    paymentMethods: Array,
});

const items = computed(() => props.cart?.items || []);
const total = computed(() => {
    return items.value.reduce((sum, item) => sum + parseFloat(item.price || 0), 0).toFixed(2);
});

const promoForm = useForm({ code: '' });
const selectedPayment = ref(props.paymentMethods?.[0]?.module || '');
const checkingOut = ref(false);

function removeItem(index) {
    router.delete(route('client.orders.cart.remove', index));
}

function applyPromo() {
    promoForm.post(route('client.orders.cart.promo'), { preserveScroll: true });
}

function checkout() {
    if (!selectedPayment.value) return;
    checkingOut.value = true;
    router.post(route('client.orders.checkout'), {
        paymentmethod: selectedPayment.value,
    }, {
        onFinish: () => checkingOut.value = false,
    });
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <h1 class="text-lg font-bold text-gray-900">Shopping Cart</h1>
                <p class="text-[13px] text-gray-500">{{ items.length }} item(s) in your cart</p>
            </div>
        </template>
        <template #actions>
            <Link :href="route('client.orders.products')"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-[13px] font-medium text-gray-700 bg-white rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
                Continue Shopping
            </Link>
        </template>

        <div v-if="items.length === 0">
            <EmptyState title="Your cart is empty" message="Browse our products and add items to get started.">
                <Link :href="route('client.orders.products')" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-sm">
                    Browse Products
                </Link>
            </EmptyState>
        </div>
        <div v-else class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
                <Card v-for="(item, i) in items" :key="i" noPadding>
                    <div class="flex items-center justify-between p-5">
                        <div class="flex-1 min-w-0">
                            <p class="text-[14px] font-semibold text-gray-900">{{ item.name }}</p>
                            <div class="flex items-center gap-3 mt-1">
                                <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium bg-indigo-50 text-indigo-700 rounded-md capitalize">{{ item.billingcycle }}</span>
                                <span v-if="item.domain" class="text-[12px] text-gray-500">{{ item.domain }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 ml-4">
                            <p class="text-[16px] font-bold text-gray-900">{{ formatCurrency(item.price) }}</p>
                            <button @click="removeItem(i)" class="p-2 text-gray-400 hover:text-red-500 transition-colors rounded-lg hover:bg-red-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </div>
                </Card>
            </div>

            <div class="space-y-4">
                <!-- Promo Code -->
                <Card title="Promo Code">
                    <form @submit.prevent="applyPromo" class="flex gap-2">
                        <input v-model="promoForm.code" type="text" class="flex-1 text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Enter promo code" />
                        <button type="submit" :disabled="promoForm.processing" class="px-3 py-2 text-[13px] font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 disabled:opacity-50">Apply</button>
                    </form>
                    <p v-if="promoForm.errors.code" class="mt-1.5 text-[12px] text-red-600">{{ promoForm.errors.code }}</p>
                    <div v-if="cart?.promo" class="mt-2 flex items-center gap-1.5 text-[12px] text-emerald-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Promo "{{ cart.promo.code }}" applied
                    </div>
                </Card>

                <!-- Payment Method -->
                <Card v-if="paymentMethods?.length" title="Payment Method">
                    <div class="space-y-2">
                        <label v-for="pm in paymentMethods" :key="pm.module" class="flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors" :class="selectedPayment === pm.module ? 'bg-indigo-50 ring-1 ring-indigo-200' : 'hover:bg-gray-50 border border-gray-100'">
                            <input type="radio" v-model="selectedPayment" :value="pm.module" class="text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-[13px] text-gray-900">{{ pm.displayname }}</span>
                        </label>
                    </div>
                </Card>

                <!-- Order Summary -->
                <Card title="Order Summary">
                    <dl class="space-y-2.5 mb-5">
                        <div class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">Items ({{ items.length }})</dt>
                            <dd class="text-[13px] font-medium text-gray-900">{{ formatCurrency(total) }}</dd>
                        </div>
                        <div class="border-t border-gray-200 pt-2.5 flex justify-between">
                            <dt class="text-[14px] font-semibold text-gray-900">Total</dt>
                            <dd class="text-[16px] font-bold text-gray-900">{{ formatCurrency(total) }}</dd>
                        </div>
                    </dl>
                    <button @click="checkout" :disabled="checkingOut || !selectedPayment"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 text-[14px] font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm">
                        {{ checkingOut ? 'Processing...' : 'Complete Order' }}
                    </button>
                    <p class="mt-2 text-[11px] text-gray-400 text-center">You'll be redirected to complete payment</p>
                </Card>
            </div>
        </div>
    </ClientLayout>
</template>
