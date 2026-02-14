<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';

defineProps({
    credit: String,
    currency: String,
});
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Credit Balance</h1>
        </template>

        <Card>
            <div class="text-center py-8">
                <div class="w-16 h-16 rounded-2xl bg-emerald-50 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                    </svg>
                </div>
                <p class="text-[13px] text-gray-500 mb-2">Available Credit</p>
                <p class="text-4xl font-bold text-gray-900">${{ credit }}</p>
                <p class="text-[13px] text-gray-400 mt-1">{{ currency }}</p>
            </div>
        </Card>
    </ClientLayout>
</template>
