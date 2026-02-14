<script setup>
defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    noPadding: { type: Boolean, default: false },
});
</script>

<template>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div v-if="title || $slots.headerActions" class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 v-if="title" class="text-sm font-semibold text-gray-900">{{ title }}</h3>
                <p v-if="description" class="mt-0.5 text-[13px] text-gray-500">{{ description }}</p>
            </div>
            <div class="flex items-center gap-2">
                <slot name="headerActions" />
            </div>
        </div>
        <div :class="noPadding ? '' : 'p-6'">
            <slot />
        </div>
    </div>
</template>
