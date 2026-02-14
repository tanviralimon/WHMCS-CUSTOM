<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const show = ref(false);
const message = ref('');
const type = ref('success');

watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        message.value = flash.success;
        type.value = 'success';
        show.value = true;
        setTimeout(() => show.value = false, 4000);
    }
    if (flash?.error) {
        message.value = flash.error;
        type.value = 'error';
        show.value = true;
        setTimeout(() => show.value = false, 5000);
    }
}, { deep: true, immediate: true });
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-4 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-4 opacity-0"
        >
            <div v-if="show" class="fixed bottom-6 right-6 z-[100] max-w-sm">
                <div :class="[
                    'flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg ring-1 backdrop-blur-sm',
                    type === 'success'
                        ? 'bg-white ring-emerald-200 text-emerald-800'
                        : 'bg-white ring-red-200 text-red-800'
                ]">
                    <!-- Icon -->
                    <div :class="[
                        'w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0',
                        type === 'success' ? 'bg-emerald-100' : 'bg-red-100'
                    ]">
                        <svg v-if="type === 'success'" class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <svg v-else class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <p class="text-[13px] font-medium">{{ message }}</p>
                    <button @click="show = false" class="ml-auto p-1 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
