<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();
const show = ref(false);

const currencies = computed(() => page.props.currencies || []);
const activeCurrencyId = computed(() => page.props.activeCurrencyId || 1);
const activeCurrency = computed(() => {
    return currencies.value.find(c => Number(c.id) === Number(activeCurrencyId.value)) || currencies.value[0] || null;
});

function switchCurrency(id) {
    show.value = false;
    router.post(route('client.currency.switch'), { currency_id: id }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            // Reload the page to reflect new currency
            router.reload();
        },
    });
}

function handleClickOutside(e) {
    if (!e.target.closest('.currency-switcher')) {
        show.value = false;
    }
}
</script>

<template>
    <div v-if="currencies.length > 1" class="currency-switcher relative" @click.stop>
        <button
            @click="show = !show"
            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[12px] font-medium text-gray-600 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-gray-800 transition-colors"
        >
            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ activeCurrency?.code || 'USD' }}
            <svg class="w-3 h-3 text-gray-400 transition-transform" :class="show ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95 -translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-show="show" class="absolute right-0 top-full mt-1.5 w-44 bg-white rounded-xl shadow-lg ring-1 ring-gray-200 py-1.5 z-50">
                <button
                    v-for="c in currencies"
                    :key="c.id"
                    @click="switchCurrency(c.id)"
                    :class="[
                        'flex items-center justify-between w-full px-3.5 py-2 text-[13px] transition-colors',
                        Number(c.id) === Number(activeCurrencyId)
                            ? 'text-indigo-700 bg-indigo-50 font-medium'
                            : 'text-gray-700 hover:bg-gray-50'
                    ]"
                >
                    <span>{{ c.code }}</span>
                    <span class="text-[11px] text-gray-400">{{ c.prefix || c.suffix || '' }}</span>
                </button>
            </div>
        </Transition>
    </div>
</template>
