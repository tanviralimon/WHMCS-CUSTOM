<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ departments: Array });

const form = useForm({
    deptid: props.departments?.[0]?.id || '',
    subject: '',
    message: '',
    priority: 'Medium',
});

function submit() {
    form.post(route('tickets.store'));
}
</script>

<template>
    <Head title="New Ticket" />
    <AuthenticatedLayout>
        <div class="max-w-2xl mx-auto space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Open a Support Ticket</h1>
                <p class="text-slate-500 mt-1">Describe your issue and we'll get back to you shortly</p>
            </div>

            <form @submit.prevent="submit" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-5">
                <!-- Department -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Department</label>
                    <select v-model="form.deptid" class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500" required>
                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                    </select>
                    <div v-if="form.errors.deptid" class="text-sm text-red-600 mt-1">{{ form.errors.deptid }}</div>
                </div>

                <!-- Subject -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Subject</label>
                    <input v-model="form.subject" type="text" class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500" placeholder="Brief description of your issue" required />
                    <div v-if="form.errors.subject" class="text-sm text-red-600 mt-1">{{ form.errors.subject }}</div>
                </div>

                <!-- Priority -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Priority</label>
                    <select v-model="form.priority" class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500">
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>

                <!-- Message -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Message</label>
                    <textarea v-model="form.message" rows="6" class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500 resize-none" placeholder="Describe your issue in detail..." required />
                    <div v-if="form.errors.message" class="text-sm text-red-600 mt-1">{{ form.errors.message }}</div>
                </div>

                <div v-if="form.errors.message" class="text-sm text-red-600">{{ form.errors.message }}</div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all disabled:opacity-50"
                >
                    {{ form.processing ? 'Submitting...' : 'Submit Ticket' }}
                </button>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
