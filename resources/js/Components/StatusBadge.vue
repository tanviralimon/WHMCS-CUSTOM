<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, default: '' },
    size: { type: String, default: 'sm' },
});

const normalized = computed(() => (props.status || '').toLowerCase().trim());

const config = computed(() => {
    const map = {
        active:      { bg: 'bg-emerald-50', text: 'text-emerald-700', dot: 'bg-emerald-500' },
        paid:        { bg: 'bg-emerald-50', text: 'text-emerald-700', dot: 'bg-emerald-500' },
        open:        { bg: 'bg-blue-50',    text: 'text-blue-700',    dot: 'bg-blue-500' },
        answered:    { bg: 'bg-indigo-50',  text: 'text-indigo-700',  dot: 'bg-indigo-500' },
        pending:     { bg: 'bg-amber-50',   text: 'text-amber-700',   dot: 'bg-amber-500' },
        'in progress': { bg: 'bg-amber-50', text: 'text-amber-700',   dot: 'bg-amber-500' },
        unpaid:      { bg: 'bg-red-50',     text: 'text-red-700',     dot: 'bg-red-500' },
        overdue:     { bg: 'bg-red-50',     text: 'text-red-700',     dot: 'bg-red-500' },
        suspended:   { bg: 'bg-orange-50',  text: 'text-orange-700',  dot: 'bg-orange-500' },
        cancelled:   { bg: 'bg-gray-50',    text: 'text-gray-600',    dot: 'bg-gray-400' },
        terminated:  { bg: 'bg-gray-100',   text: 'text-gray-500',    dot: 'bg-gray-400' },
        closed:      { bg: 'bg-gray-50',    text: 'text-gray-600',    dot: 'bg-gray-400' },
        expired:     { bg: 'bg-gray-50',    text: 'text-gray-600',    dot: 'bg-gray-400' },
        draft:       { bg: 'bg-slate-50',   text: 'text-slate-600',   dot: 'bg-slate-400' },
        accepted:    { bg: 'bg-emerald-50', text: 'text-emerald-700', dot: 'bg-emerald-500' },
        declined:    { bg: 'bg-red-50',     text: 'text-red-700',     dot: 'bg-red-500' },
        refunded:    { bg: 'bg-purple-50',  text: 'text-purple-700',  dot: 'bg-purple-500' },
        'customer-reply': { bg: 'bg-blue-50', text: 'text-blue-700',  dot: 'bg-blue-500' },
    };
    return map[normalized.value] || { bg: 'bg-gray-50', text: 'text-gray-600', dot: 'bg-gray-400' };
});

const sizeClass = computed(() => ({
    xs: 'text-[10px] px-1.5 py-0.5',
    sm: 'text-[11px] px-2 py-0.5',
    md: 'text-xs px-2.5 py-1',
}[props.size] || 'text-[11px] px-2 py-0.5'));
</script>

<template>
    <span :class="[config.bg, config.text, sizeClass, 'inline-flex items-center gap-1 rounded-full font-medium capitalize']">
        <span :class="[config.dot, 'w-1.5 h-1.5 rounded-full']" />
        {{ status }}
    </span>
</template>
