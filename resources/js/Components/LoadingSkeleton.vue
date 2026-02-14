<script setup>
const props = defineProps({
    rows: { type: Number, default: 5 },
    cols: { type: Number, default: 4 },
});
</script>

<template>
    <div class="animate-pulse">
        <!-- Header skeleton -->
        <div class="h-8 bg-gray-200 rounded-lg w-48 mb-6" />

        <!-- Table skeleton -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-3 flex gap-4">
                <div v-for="c in cols" :key="'h'+c" class="h-4 bg-gray-200 rounded flex-1" />
            </div>
            <div v-for="r in rows" :key="r" class="px-6 py-4 border-b border-gray-50 flex gap-4">
                <div v-for="c in cols" :key="'r'+r+'c'+c" class="h-4 bg-gray-100 rounded flex-1" :class="c === 1 ? 'w-2/5' : ''" />
            </div>
        </div>
    </div>
</template>
