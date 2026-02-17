<script setup>
import { computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';

defineProps({
    departments: Array,
});

const form = useForm({
    deptid: '',
    subject: '',
    message: '',
    priority: 'Medium',
});

const charCount = computed(() => form.message.length);

const priorities = [
    { value: 'Low', label: 'Low', desc: 'General questions, no urgency', color: 'border-green-200 bg-green-50 text-green-700', active: 'ring-2 ring-green-500 border-green-400' },
    { value: 'Medium', label: 'Medium', desc: 'Standard request', color: 'border-amber-200 bg-amber-50 text-amber-700', active: 'ring-2 ring-amber-500 border-amber-400' },
    { value: 'High', label: 'High', desc: 'Urgent, service affected', color: 'border-red-200 bg-red-50 text-red-700', active: 'ring-2 ring-red-500 border-red-400' },
];

function submit() {
    form.post(route('client.tickets.store'));
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('client.tickets.index')"
                    class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                </Link>
                <h1 class="text-lg font-bold text-gray-900">Open a Support Ticket</h1>
            </div>
        </template>

        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                <form @submit.prevent="submit">
                    <div class="p-6 space-y-6">
                        <!-- Department -->
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-2">Department</label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                <button v-for="d in departments" :key="d.id" type="button"
                                    @click="form.deptid = d.id"
                                    class="px-3 py-2.5 text-[12px] font-medium rounded-lg border transition-all text-center"
                                    :class="form.deptid === d.id
                                        ? 'bg-indigo-50 border-indigo-300 text-indigo-700 ring-2 ring-indigo-500'
                                        : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300 hover:bg-gray-50'">
                                    {{ d.name }}
                                </button>
                            </div>
                            <p v-if="form.errors.deptid" class="mt-1.5 text-[12px] text-red-600">{{ form.errors.deptid }}</p>
                        </div>

                        <!-- Subject -->
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-2">Subject</label>
                            <input v-model="form.subject" type="text"
                                class="w-full text-[13px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400"
                                placeholder="Brief summary of your issue" />
                            <p v-if="form.errors.subject" class="mt-1.5 text-[12px] text-red-600">{{ form.errors.subject }}</p>
                        </div>

                        <!-- Priority -->
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-2">Priority</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button v-for="p in priorities" :key="p.value" type="button"
                                    @click="form.priority = p.value"
                                    class="px-3 py-2.5 rounded-lg border transition-all text-center"
                                    :class="[p.color, form.priority === p.value ? p.active : 'opacity-60 hover:opacity-80']">
                                    <p class="text-[12px] font-bold">{{ p.label }}</p>
                                    <p class="text-[10px] mt-0.5 opacity-75">{{ p.desc }}</p>
                                </button>
                            </div>
                        </div>

                        <!-- Message -->
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-2">Message</label>
                            <textarea v-model="form.message" rows="8"
                                class="w-full text-[13px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 resize-none placeholder-gray-400"
                                placeholder="Describe your issue in detail. Include any relevant account info, error messages, or steps to reproduce…" />
                            <div class="flex items-center justify-between mt-1.5">
                                <p v-if="form.errors.message" class="text-[12px] text-red-600">{{ form.errors.message }}</p>
                                <span v-else />
                                <span class="text-[11px] text-gray-400">{{ charCount }} / 10,000</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-between px-6 py-4 bg-gray-50 border-t border-gray-100">
                        <Link :href="route('client.tickets.index')"
                            class="text-[13px] text-gray-500 hover:text-gray-700 transition-colors">
                            Cancel
                        </Link>
                        <button type="submit" :disabled="form.processing"
                            class="inline-flex items-center gap-1.5 px-5 py-2.5 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors shadow-sm">
                            <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                            </svg>
                            {{ form.processing ? 'Submitting…' : 'Submit Ticket' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </ClientLayout>
</template>
