<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: String, default: '' },
    color: { type: String, default: 'indigo' }, // indigo, emerald, amber, red, purple
    href: { type: String, default: '' },
    change: { type: String, default: '' },
});

const colorMap = {
    indigo:  { bg: 'bg-indigo-50', icon: 'text-indigo-600', ring: 'ring-indigo-100' },
    emerald: { bg: 'bg-emerald-50', icon: 'text-emerald-600', ring: 'ring-emerald-100' },
    amber:   { bg: 'bg-amber-50', icon: 'text-amber-600', ring: 'ring-amber-100' },
    red:     { bg: 'bg-red-50', icon: 'text-red-600', ring: 'ring-red-100' },
    purple:  { bg: 'bg-purple-50', icon: 'text-purple-600', ring: 'ring-purple-100' },
    sky:     { bg: 'bg-sky-50', icon: 'text-sky-600', ring: 'ring-sky-100' },
};
</script>

<template>
    <component :is="href ? 'a' : 'div'" :href="href || undefined" :class="[
        'relative bg-white rounded-xl border border-gray-200 p-5 transition-all group',
        href ? 'hover:border-gray-300 hover:shadow-sm cursor-pointer' : ''
    ]">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <p class="text-[13px] font-medium text-gray-500">{{ label }}</p>
                <p class="mt-1.5 text-2xl font-bold text-gray-900 tracking-tight">{{ value }}</p>
                <p v-if="change" class="mt-1 text-[12px] text-gray-400">{{ change }}</p>
            </div>
            <div v-if="icon" :class="[colorMap[color]?.bg, 'w-10 h-10 rounded-xl flex items-center justify-center ring-1', colorMap[color]?.ring]">
                <svg :class="['w-5 h-5', colorMap[color]?.icon]" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="icon" />
            </div>
        </div>
        <div v-if="href" class="absolute inset-0 rounded-xl ring-1 ring-transparent group-hover:ring-indigo-200 transition-all pointer-events-none" />
    </component>
</template>
