<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Confirm' },
    message: { type: String, default: 'Are you sure?' },
    confirmText: { type: String, default: 'Confirm' },
    cancelText: { type: String, default: 'Cancel' },
    variant: { type: String, default: 'danger' }, // 'danger' | 'primary'
    confirmDisabled: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel', 'close']);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="emit('close')" />
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="scale-95 opacity-0"
                    enter-to-class="scale-100 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="scale-100 opacity-100"
                    leave-to-class="scale-95 opacity-0"
                >
                    <div v-if="show" class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
                        <div class="p-6">
                            <div class="flex items-center gap-4">
                                <div :class="[
                                    'w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0',
                                    variant === 'danger' ? 'bg-red-100' : 'bg-indigo-100'
                                ]">
                                    <svg v-if="variant === 'danger'" class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                    </svg>
                                    <svg v-else class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900">{{ title }}</h3>
                                    <p class="mt-1 text-[13px] text-gray-500">{{ message }}</p>
                                </div>
                            </div>
                            <slot />
                        </div>
                        <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 border-t border-gray-100">
                            <button @click="emit('cancel'); emit('close')" class="px-4 py-2 text-[13px] font-medium text-gray-700 bg-white rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
                                {{ cancelText }}
                            </button>
                            <button @click="!confirmDisabled && emit('confirm')" :disabled="confirmDisabled" :class="[
                                'px-4 py-2 text-[13px] font-medium text-white rounded-lg transition-colors shadow-sm',
                                variant === 'danger'
                                    ? 'bg-red-600 hover:bg-red-700'
                                    : 'bg-indigo-600 hover:bg-indigo-700',
                                confirmDisabled ? 'opacity-50 cursor-not-allowed' : ''
                            ]">
                                {{ confirmText }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
