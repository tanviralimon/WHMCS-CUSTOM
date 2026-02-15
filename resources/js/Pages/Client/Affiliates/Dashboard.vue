<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';
import StatCard from '@/Components/StatCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useCurrency } from '@/Composables/useCurrency.js';

const { formatCurrency } = useCurrency();

defineProps({ affiliate: Object });
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Affiliate Program</h1>
        </template>

        <div v-if="!affiliate">
            <EmptyState title="Not enrolled" message="You are not enrolled in the affiliate program." />
        </div>
        <template v-else>
            <div class="grid md:grid-cols-4 gap-4 mb-6">
                <StatCard label="Visitors" :value="affiliate.visitors || 0">
                    <template #icon>
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                    </template>
                </StatCard>
                <StatCard label="Signups" :value="affiliate.signups || 0">
                    <template #icon>
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" /></svg>
                    </template>
                </StatCard>
                <StatCard label="Conversion" :value="affiliate.conversionrate || '0%'">
                    <template #icon>
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
                    </template>
                </StatCard>
                <StatCard label="Balance" :value="formatCurrency(affiliate.balance || '0.00')">
                    <template #icon>
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </template>
                </StatCard>
            </div>

            <!-- Referral Link -->
            <Card title="Your Referral Link">
                <div class="flex items-center gap-3">
                    <input type="text" readonly :value="affiliate.referrallink || '—'"
                        class="flex-1 text-[13px] rounded-lg border-gray-300 bg-gray-50 text-gray-700 font-mono" />
                    <button @click="navigator.clipboard.writeText(affiliate.referrallink || '')"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-[13px] font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" /></svg>
                        Copy
                    </button>
                </div>
            </Card>

            <!-- Payouts Info -->
            <Card v-if="affiliate.withdrawn" title="Payout History" class="mt-4">
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-[13px] text-gray-500">Total Commissions</dt>
                        <dd class="text-[13px] font-semibold text-gray-900">{{ formatCurrency(affiliate.commissions || '0.00') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[13px] text-gray-500">Withdrawn</dt>
                        <dd class="text-[13px] font-semibold text-gray-900">{{ formatCurrency(affiliate.withdrawn || '0.00') }}</dd>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-gray-100">
                        <dt class="text-[14px] font-semibold text-gray-900">Available Balance</dt>
                        <dd class="text-[14px] font-bold text-emerald-600">{{ formatCurrency(affiliate.balance || '0.00') }}</dd>
                    </div>
                </dl>
            </Card>
        </template>
    </ClientLayout>
</template>
