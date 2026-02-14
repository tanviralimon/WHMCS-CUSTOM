<script setup>
import { useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';

defineProps({
    departments: Array,
});

const form = useForm({
    deptid: '',
    subject: '',
    message: '',
    priority: 'Medium',
});

function submit() {
    form.post(route('client.tickets.store'));
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Open a Ticket</h1>
        </template>

        <div class="max-w-2xl">
            <Card>
                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Department</label>
                        <select v-model="form.deptid" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="" disabled>Select a department…</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                        <p v-if="form.errors.deptid" class="mt-1 text-[12px] text-red-600">{{ form.errors.deptid }}</p>
                    </div>

                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Subject</label>
                        <input v-model="form.subject" type="text"
                            class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Brief summary of your issue" />
                        <p v-if="form.errors.subject" class="mt-1 text-[12px] text-red-600">{{ form.errors.subject }}</p>
                    </div>

                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Priority</label>
                        <select v-model="form.priority" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Message</label>
                        <textarea v-model="form.message" rows="8"
                            class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 resize-none"
                            placeholder="Describe your issue in detail…" />
                        <p v-if="form.errors.message" class="mt-1 text-[12px] text-red-600">{{ form.errors.message }}</p>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="submit" :disabled="form.processing"
                            class="inline-flex items-center gap-1.5 px-5 py-2.5 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
                            Submit Ticket
                        </button>
                    </div>
                </form>
            </Card>
        </div>
    </ClientLayout>
</template>
