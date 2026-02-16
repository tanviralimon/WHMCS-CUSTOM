<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';
import { useCurrency } from '@/Composables/useCurrency.js';

const { formatCurrency } = useCurrency();

const props = defineProps({
    credit: String,
    currency: String,
    credits: Array,
    paymentMethods: Array,
});

const amount = ref('');
const selectedMethod = ref('');
const submitting = ref(false);
const error = ref('');

function addFunds() {
    const val = parseFloat(amount.value);
    if (!val || val < 1) { error.value = 'Minimum amount is 1.00'; return; }
    if (val > 10000) { error.value = 'Maximum amount is 10,000.00'; return; }
    if (!selectedMethod.value) { error.value = 'Please select a payment method'; return; }
    error.value = '';
    submitting.value = true;
    router.post(route('client.billing.credit.addFunds'), {
        amount: val,
        payment_method: selectedMethod.value,
    }, {
        onFinish: () => { submitting.value = false; },
        onError: (errors) => { error.value = errors.amount || errors.payment_method || 'Something went wrong'; },
    });
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Credit Balance</h1>
        </template>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Main content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Add Funds -->
                <Card title="Add Funds">
                    <div class="space-y-4">
                        <p class="text-[13px] text-gray-500">Add credit to your account. An invoice will be generated for the selected amount.</p>

                        <div>
                            <label class="block text-[12px] font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Amount</label>
                            <div class="relative">
                                <input v-model="amount" type="number" min="1" max="10000" step="0.01" placeholder="0.00"
                                    class="w-full pl-4 pr-4 py-2.5 text-[14px] font-medium rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                        </div>

                        <div v-if="paymentMethods && paymentMethods.length">
                            <label class="block text-[12px] font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Payment Method</label>
                            <div class="space-y-1.5">
                                <label v-for="pm in paymentMethods" :key="pm.module"
                                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg border cursor-pointer transition-all"
                                    :class="selectedMethod === pm.module ? 'border-indigo-300 bg-indigo-50/60 ring-1 ring-indigo-200' : 'border-gray-200 bg-gray-50 hover:bg-gray-100'">
                                    <input type="radio" :value="pm.module" v-model="selectedMethod"
                                        class="text-indigo-600 focus:ring-indigo-500 h-4 w-4" />
                                    <span class="text-[13px] font-medium" :class="selectedMethod === pm.module ? 'text-indigo-700' : 'text-gray-700'">{{ pm.displayname }}</span>
                                </label>
                            </div>
                        </div>

                        <p v-if="error" class="text-[12px] text-red-600 font-medium">{{ error }}</p>

                        <button @click="addFunds" :disabled="submitting"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[13px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm disabled:opacity-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            {{ submitting ? 'Processing...' : 'Add Funds' }}
                        </button>
                    </div>
                </Card>

                <!-- Credit History -->
                <Card title="Credit History" v-if="credits && credits.length">
                    <div class="overflow-x-auto -mx-5">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-100">
                                    <th class="text-left px-5 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="text-left px-5 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="text-right px-5 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(c, i) in credits" :key="i" class="border-b border-gray-50 last:border-0">
                                    <td class="px-5 py-3 text-[13px] text-gray-600">{{ c.date }}</td>
                                    <td class="px-5 py-3 text-[13px] text-gray-900">{{ c.description }}</td>
                                    <td class="px-5 py-3 text-right text-[13px] font-semibold"
                                        :class="parseFloat(c.amount) >= 0 ? 'text-emerald-600' : 'text-red-600'">
                                        {{ formatCurrency(c.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Card v-else-if="credits && credits.length === 0">
                    <div class="text-center py-6">
                        <p class="text-[13px] text-gray-500">No credit history yet.</p>
                    </div>
                </Card>
            </div>

            <!-- Sidebar -->
            <div class="space-y-4">
                <Card>
                    <div class="text-center py-4">
                        <div class="w-16 h-16 rounded-2xl bg-emerald-50 flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                            </svg>
                        </div>
                        <p class="text-[13px] text-gray-500 mb-2">Available Credit</p>
                        <p class="text-4xl font-bold text-gray-900">{{ formatCurrency(credit) }}</p>
                        <p class="text-[13px] text-gray-400 mt-1">{{ currency }}</p>
                    </div>
                </Card>
            </div>
        </div>
    </ClientLayout>
</template>
