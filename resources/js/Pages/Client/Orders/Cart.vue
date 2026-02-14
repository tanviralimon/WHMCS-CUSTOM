<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({ cart: Object });
const items = props.cart?.items || [];

const promoForm = useForm({ promocode: '' });
const checkingOut = ref(false);

function removeItem(index) {
    router.delete(route('client.orders.cart.remove', index));
}

function applyPromo() {
    promoForm.post(route('client.orders.cart.promo'), { preserveScroll: true });
}

function checkout() {
    checkingOut.value = true;
    router.post(route('client.orders.checkout'), {}, {
        onFinish: () => checkingOut.value = false,
    });
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Shopping Cart</h1>
        </template>

        <div v-if="items.length === 0">
            <EmptyState title="Cart is empty" message="Add products to your cart to get started.">
                <a :href="route('client.orders.products')" class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                    Browse Products
                </a>
            </EmptyState>
        </div>
        <div v-else class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
                <Card v-for="(item, i) in items" :key="i">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[14px] font-semibold text-gray-900">{{ item.name || item.productname }}</p>
                            <p class="text-[12px] text-gray-500 mt-0.5">{{ item.billingcycle }} · {{ item.paymentmethod }}</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <p class="text-[14px] font-bold text-gray-900">${{ item.price || item.amount }}</p>
                            <button @click="removeItem(i)"
                                class="p-1.5 text-gray-400 hover:text-red-500 transition-colors rounded-lg hover:bg-red-50">
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
                        <input v-model="promoForm.promocode" type="text"
                            class="flex-1 text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Enter code" />
                        <button type="submit" :disabled="promoForm.processing"
                            class="px-3 py-2 text-[13px] font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 disabled:opacity-50 transition-colors">
                            Apply
                        </button>
                    </form>
                    <p v-if="promoForm.errors.promocode" class="mt-1 text-[12px] text-red-600">{{ promoForm.errors.promocode }}</p>
                </Card>

                <!-- Summary -->
                <Card title="Order Summary">
                    <dl class="space-y-2 mb-4">
                        <div class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">Items</dt>
                            <dd class="text-[13px] font-medium text-gray-900">{{ items.length }}</dd>
                        </div>
                        <div v-if="cart?.promo" class="flex justify-between text-emerald-600">
                            <dt class="text-[13px]">Promo ({{ cart.promo }})</dt>
                            <dd class="text-[13px] font-medium">Applied</dd>
                        </div>
                    </dl>
                    <button @click="checkout" :disabled="checkingOut"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 text-[14px] font-medium text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm">
                        Complete Order
                    </button>
                </Card>
            </div>
        </div>
    </ClientLayout>
</template>
