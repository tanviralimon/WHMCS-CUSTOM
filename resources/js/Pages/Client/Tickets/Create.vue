<script setup>
import { ref, computed } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';

defineProps({
    departments: Array,
});

const form = useForm({
    deptid: '',
    subject: '',
    message: '',
    priority: 'Medium',
    attachments: [],
    // Secret credentials
    has_credentials: false,
    cred_host: '',
    cred_port: '',
    cred_username: '',
    cred_password: '',
    cred_notes: '',
});

const charCount = computed(() => form.message.length);
const fileInput = ref(null);
const dragOver = ref(false);

const maxFiles = 5;
const maxSizeMB = 2;
const allowedExts = ['jpg', 'jpeg', 'gif', 'png', 'txt', 'pdf', 'zip', 'doc', 'docx'];

const priorities = [
    { value: 'Low', label: 'Low', desc: 'General questions, no urgency', color: 'border-green-200 bg-green-50 text-green-700', active: 'ring-2 ring-green-500 border-green-400' },
    { value: 'Medium', label: 'Medium', desc: 'Standard request', color: 'border-amber-200 bg-amber-50 text-amber-700', active: 'ring-2 ring-amber-500 border-amber-400' },
    { value: 'High', label: 'High', desc: 'Urgent, service affected', color: 'border-red-200 bg-red-50 text-red-700', active: 'ring-2 ring-red-500 border-red-400' },
];

function handleFiles(fileList) {
    const files = Array.from(fileList);
    for (const file of files) {
        if (form.attachments.length >= maxFiles) break;
        const ext = file.name.split('.').pop().toLowerCase();
        if (!allowedExts.includes(ext)) continue;
        if (file.size > maxSizeMB * 1024 * 1024) continue;
        // Avoid duplicates
        if (form.attachments.some(f => f.name === file.name && f.size === file.size)) continue;
        form.attachments.push(file);
    }
    if (fileInput.value) fileInput.value.value = '';
}

function onFileChange(e) {
    handleFiles(e.target.files);
}

function onDrop(e) {
    dragOver.value = false;
    handleFiles(e.dataTransfer.files);
}

function removeFile(index) {
    form.attachments.splice(index, 1);
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function submit() {
    // Use router.post for file upload (multipart)
    const data = new FormData();
    data.append('deptid', form.deptid);
    data.append('subject', form.subject);
    data.append('message', form.message);
    data.append('priority', form.priority);

    form.attachments.forEach((file, i) => {
        data.append(`attachments[${i}]`, file);
    });

    if (form.has_credentials) {
        data.append('has_credentials', '1');
        data.append('cred_host', form.cred_host);
        data.append('cred_port', form.cred_port);
        data.append('cred_username', form.cred_username);
        data.append('cred_password', form.cred_password);
        data.append('cred_notes', form.cred_notes);
    }

    form.processing = true;
    router.post(route('client.tickets.store'), data, {
        forceFormData: true,
        onFinish: () => { form.processing = false; },
        onError: (errors) => { form.errors = errors; },
    });
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

                        <!-- Attachments -->
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-2">
                                Attachments
                                <span class="font-normal text-gray-400 ml-1">(optional, max {{ maxFiles }} files)</span>
                            </label>

                            <!-- Drop zone -->
                            <div @dragover.prevent="dragOver = true" @dragleave="dragOver = false" @drop.prevent="onDrop"
                                @click="fileInput?.click()"
                                class="relative border-2 border-dashed rounded-xl px-4 py-6 text-center cursor-pointer transition-all"
                                :class="dragOver
                                    ? 'border-indigo-400 bg-indigo-50'
                                    : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'">
                                <svg class="w-8 h-8 mx-auto mb-2" :class="dragOver ? 'text-indigo-500' : 'text-gray-300'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                                </svg>
                                <p class="text-[12px] text-gray-500">
                                    <span class="font-medium text-indigo-600">Click to browse</span> or drag files here
                                </p>
                                <p class="text-[10px] text-gray-400 mt-1">
                                    {{ allowedExts.join(', ').toUpperCase() }} · Max {{ maxSizeMB }}MB per file
                                </p>
                                <input ref="fileInput" type="file" multiple class="hidden" :accept="allowedExts.map(e => '.' + e).join(',')" @change="onFileChange" />
                            </div>

                            <!-- File list -->
                            <div v-if="form.attachments.length" class="mt-3 space-y-2">
                                <div v-for="(file, idx) in form.attachments" :key="idx"
                                    class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                                    </svg>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[12px] font-medium text-gray-700 truncate">{{ file.name }}</p>
                                        <p class="text-[10px] text-gray-400">{{ formatSize(file.size) }}</p>
                                    </div>
                                    <button type="button" @click="removeFile(idx)" class="text-gray-400 hover:text-red-500 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <p v-if="form.errors.attachments" class="mt-1.5 text-[12px] text-red-600">{{ form.errors.attachments }}</p>
                        </div>

                        <!-- Secret Credentials (collapsible) -->
                        <div>
                            <button type="button" @click="form.has_credentials = !form.has_credentials"
                                class="flex items-center gap-2 text-[13px] font-semibold text-gray-700 hover:text-indigo-600 transition-colors group">
                                <div class="w-5 h-5 rounded border flex items-center justify-center transition-all"
                                    :class="form.has_credentials
                                        ? 'bg-indigo-600 border-indigo-600'
                                        : 'border-gray-300 group-hover:border-indigo-400'">
                                    <svg v-if="form.has_credentials" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                                </svg>
                                Share Access Credentials
                                <span class="text-[11px] font-normal text-gray-400">(SSH, hosting, etc.)</span>
                            </button>

                            <transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 max-h-0" enter-to-class="opacity-100 max-h-96"
                                leave-active-class="transition-all duration-200" leave-from-class="opacity-100 max-h-96" leave-to-class="opacity-0 max-h-0">
                                <div v-if="form.has_credentials" class="mt-3 p-4 bg-amber-50 border border-amber-200 rounded-xl space-y-3 overflow-hidden">
                                    <div class="flex items-start gap-2 text-[11px] text-amber-700 bg-amber-100/50 rounded-lg px-3 py-2">
                                        <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                        </svg>
                                        <p>These credentials will be included securely in your ticket message. <strong>Remember to change your passwords</strong> after the issue is resolved.</p>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-600 mb-1">Host / IP</label>
                                            <input v-model="form.cred_host" type="text"
                                                class="w-full text-[12px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 bg-white"
                                                placeholder="e.g. 192.168.1.1" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-600 mb-1">Port</label>
                                            <input v-model="form.cred_port" type="text"
                                                class="w-full text-[12px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 bg-white"
                                                placeholder="e.g. 22" />
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-600 mb-1">Username</label>
                                            <input v-model="form.cred_username" type="text"
                                                class="w-full text-[12px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 bg-white"
                                                placeholder="e.g. root" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-600 mb-1">Password</label>
                                            <input v-model="form.cred_password" type="password"
                                                class="w-full text-[12px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 bg-white"
                                                placeholder="••••••••" />
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-gray-600 mb-1">Additional Notes</label>
                                        <textarea v-model="form.cred_notes" rows="2"
                                            class="w-full text-[12px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 resize-none bg-white"
                                            placeholder="cPanel URL, control panel type, or other details…" />
                                    </div>
                                </div>
                            </transition>
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
