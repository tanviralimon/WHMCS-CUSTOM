<script setup>
import { router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Card from '@/Components/Card.vue';

const props = defineProps({ quote: Object });
const q = props.quote;

function acceptQuote() {
    if (!confirm('Accept this quote? An invoice will be generated.')) return;
    router.post(route('client.billing.quotes.accept', q.id));
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <h1 class="text-lg font-bold text-gray-900">Quote #{{ q.id }}</h1>
                <p class="text-[13px] text-gray-500">{{ q.subject }}</p>
            </div>
        </template>
        <template #actions>
            <button v-if="(q.stage || '').toLowerCase() !== 'accepted'" @click="acceptQuote"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors shadow-sm">
                Accept Quote
            </button>
        </template>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <Card title="Quote Details">
                    <dl class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <dt class="text-[12px] font-medium text-gray-500">Status</dt>
                            <dd class="mt-1"><StatusBadge :status="q.stage || q.status" /></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] font-medium text-gray-500">Date</dt>
                            <dd class="text-[14px] text-gray-900 mt-1">{{ q.datecreated }}</dd>
                        </div>
                        <div>
                            <dt class="text-[12px] font-medium text-gray-500">Valid Until</dt>
                            <dd class="text-[14px] text-gray-900 mt-1">{{ q.validuntil }}</dd>
                        </div>
                        <div>
                            <dt class="text-[12px] font-medium text-gray-500">Total</dt>
                            <dd class="text-[14px] font-semibold text-gray-900 mt-1">${{ q.total }}</dd>
                        </div>
                    </dl>
                    <div v-if="q.proposal" class="prose prose-sm max-w-none text-gray-700" v-html="q.proposal" />
                </Card>
            </div>
            <div>
                <Card title="Summary">
                    <dl class="space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">Subtotal</dt>
                            <dd class="text-[13px] font-medium text-gray-900">${{ q.subtotal || q.total }}</dd>
                        </div>
                        <div v-if="q.tax" class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">Tax</dt>
                            <dd class="text-[13px] font-medium text-gray-900">${{ q.tax }}</dd>
                        </div>
                        <div class="flex justify-between pt-2 border-t border-gray-100">
                            <dt class="text-[14px] font-semibold text-gray-900">Total</dt>
                            <dd class="text-[14px] font-bold text-gray-900">${{ q.total }}</dd>
                        </div>
                    </dl>
                </Card>
            </div>
        </div>
    </ClientLayout>
</template>
