<script setup>
import { ref, computed } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';

const props = defineProps({
    departments: Array,
    services: { type: Array, default: () => [] },
});

const form = useForm({
    deptid: '',
    service_id: '',
    subject: '',
    message: '',
    priority: 'Medium',
    attachments: [],
});

const serviceSearch = ref('');
const showServiceDropdown = ref(false);

const statusOrder = { 'Active': 0, 'Pending': 1, 'Suspended': 2, 'Terminated': 3, 'Cancelled': 4, 'Fraud': 5 };

function sortByStatus(list) {
    return [...list].sort((a, b) => {
        const sa = statusOrder[a.status] ?? 9;
        const sb = statusOrder[b.status] ?? 9;
        if (sa !== sb) return sa - sb;
        return a.label.localeCompare(b.label);
    });
}

const filteredServices = computed(() => {
    let list = props.services;
    if (serviceSearch.value) {
        const q = serviceSearch.value.toLowerCase();
        list = list.filter(s =>
            s.label.toLowerCase().includes(q) || (s.domain && s.domain.toLowerCase().includes(q))
        );
    }
    return sortByStatus(list);
});

function statusStyle(status) {
    switch (status) {
        case 'Active':     return { dot: 'bg-emerald-400', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
        case 'Pending':    return { dot: 'bg-amber-400',   badge: 'bg-amber-50 text-amber-700 border-amber-200' };
        case 'Suspended':  return { dot: 'bg-orange-400',  badge: 'bg-orange-50 text-orange-700 border-orange-200' };
        case 'Terminated': return { dot: 'bg-red-400',     badge: 'bg-red-50 text-red-700 border-red-200' };
        case 'Cancelled':  return { dot: 'bg-gray-400',    badge: 'bg-gray-100 text-gray-600 border-gray-200' };
        default:           return { dot: 'bg-gray-300',    badge: 'bg-gray-50 text-gray-500 border-gray-200' };
    }
}

const selectedService = computed(() => {
    if (!form.service_id) return null;
    return props.services.find(s => s.id === form.service_id) || null;
});

function selectService(svc) {
    form.service_id = svc.id;
    serviceSearch.value = '';
    showServiceDropdown.value = false;
}

function clearService() {
    form.service_id = '';
    serviceSearch.value = '';
}

const showCredentials = ref(false);
const credentials = ref([]);

const credentialTypes = [
    { value: 'SSH', icon: '🖥️' },
    { value: 'cPanel', icon: '⚙️' },
    { value: 'FTP', icon: '📁' },
    { value: 'Database', icon: '🗄️' },
    { value: 'WordPress', icon: '📝' },
    { value: 'Other', icon: '🔑' },
];

function addCredential() {
    credentials.value.push({ type: 'SSH', host: '', port: '', username: '', password: '', notes: '' });
    if (!showCredentials.value) showCredentials.value = true;
}

function removeCredential(idx) {
    credentials.value.splice(idx, 1);
    if (credentials.value.length === 0) showCredentials.value = false;
}

const charCount = computed(() => form.message.length);
const fileInput = ref(null);
const dragOver = ref(false);

// Department icon + color mapping
function deptStyle(name) {
    const n = (name || '').toLowerCase();
    if (n.includes('sales') || n.includes('pre-sales'))
        return { icon: 'cart', bg: 'bg-emerald-50', border: 'border-emerald-200', text: 'text-emerald-700', iconColor: 'text-emerald-500', activeBg: 'bg-emerald-50', activeRing: 'ring-emerald-500', activeBorder: 'border-emerald-400' };
    if (n.includes('billing') || n.includes('account'))
        return { icon: 'card', bg: 'bg-amber-50', border: 'border-amber-200', text: 'text-amber-700', iconColor: 'text-amber-500', activeBg: 'bg-amber-50', activeRing: 'ring-amber-500', activeBorder: 'border-amber-400' };
    if (n.includes('technical') || n.includes('support'))
        return { icon: 'wrench', bg: 'bg-blue-50', border: 'border-blue-200', text: 'text-blue-700', iconColor: 'text-blue-500', activeBg: 'bg-blue-50', activeRing: 'ring-blue-500', activeBorder: 'border-blue-400' };
    if (n.includes('abuse'))
        return { icon: 'shield', bg: 'bg-red-50', border: 'border-red-200', text: 'text-red-700', iconColor: 'text-red-500', activeBg: 'bg-red-50', activeRing: 'ring-red-500', activeBorder: 'border-red-400' };
    if (n.includes('domain'))
        return { icon: 'globe', bg: 'bg-violet-50', border: 'border-violet-200', text: 'text-violet-700', iconColor: 'text-violet-500', activeBg: 'bg-violet-50', activeRing: 'ring-violet-500', activeBorder: 'border-violet-400' };
    // General / enquiries / other
    return { icon: 'chat', bg: 'bg-indigo-50', border: 'border-indigo-200', text: 'text-indigo-700', iconColor: 'text-indigo-500', activeBg: 'bg-indigo-50', activeRing: 'ring-indigo-500', activeBorder: 'border-indigo-400' };
}

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
    if (form.service_id) data.append('service_id', form.service_id);
    data.append('subject', form.subject);
    data.append('message', form.message);
    data.append('priority', form.priority);

    form.attachments.forEach((file, i) => {
        data.append(`attachments[${i}]`, file);
    });

    if (credentials.value.length > 0) {
        data.append('credentials', JSON.stringify(credentials.value));
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
                            <label class="block text-[13px] font-semibold text-gray-700 mb-3">
                                Department
                                <span class="text-red-500 ml-0.5">*</span>
                            </label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                                <button v-for="d in departments" :key="d.id" type="button"
                                    @click="form.deptid = d.id"
                                    class="group relative flex items-center gap-3 px-3.5 py-3 rounded-xl border-2 transition-all text-left"
                                    :class="form.deptid === d.id
                                        ? `${deptStyle(d.name).activeBg} ${deptStyle(d.name).activeBorder} ${deptStyle(d.name).text} ring-2 ${deptStyle(d.name).activeRing} shadow-sm`
                                        : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300 hover:bg-gray-50 hover:shadow-sm'">
                                    <!-- Icon -->
                                    <div class="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center transition-colors"
                                        :class="form.deptid === d.id ? deptStyle(d.name).bg : 'bg-gray-100 group-hover:bg-gray-200/60'">
                                        <!-- Cart / Sales -->
                                        <svg v-if="deptStyle(d.name).icon === 'cart'" class="w-4.5 h-4.5" :class="form.deptid === d.id ? deptStyle(d.name).iconColor : 'text-gray-400 group-hover:text-gray-500'" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                                        </svg>
                                        <!-- Credit Card / Billing -->
                                        <svg v-else-if="deptStyle(d.name).icon === 'card'" class="w-4.5 h-4.5" :class="form.deptid === d.id ? deptStyle(d.name).iconColor : 'text-gray-400 group-hover:text-gray-500'" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                                        </svg>
                                        <!-- Wrench / Technical -->
                                        <svg v-else-if="deptStyle(d.name).icon === 'wrench'" class="w-4.5 h-4.5" :class="form.deptid === d.id ? deptStyle(d.name).iconColor : 'text-gray-400 group-hover:text-gray-500'" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-5.648 5.648a2.122 2.122 0 01-3-3l5.648-5.648m2.914-2.914L21 4.5l-2.25 2.25L21 9l-2.25 2.25-3-3L13.5 10.5l-2.08-2.08m0 0L4.5 15.38" />
                                        </svg>
                                        <!-- Shield / Abuse -->
                                        <svg v-else-if="deptStyle(d.name).icon === 'shield'" class="w-4.5 h-4.5" :class="form.deptid === d.id ? deptStyle(d.name).iconColor : 'text-gray-400 group-hover:text-gray-500'" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286zm0 13.036h.008v.008H12v-.008z" />
                                        </svg>
                                        <!-- Globe / Domain -->
                                        <svg v-else-if="deptStyle(d.name).icon === 'globe'" class="w-4.5 h-4.5" :class="form.deptid === d.id ? deptStyle(d.name).iconColor : 'text-gray-400 group-hover:text-gray-500'" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 003 12c0-1.605.42-3.113 1.157-4.418" />
                                        </svg>
                                        <!-- Chat / General -->
                                        <svg v-else class="w-4.5 h-4.5" :class="form.deptid === d.id ? deptStyle(d.name).iconColor : 'text-gray-400 group-hover:text-gray-500'" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                                        </svg>
                                    </div>
                                    <span class="text-[12.5px] font-medium leading-tight">{{ d.name }}</span>
                                    <!-- Check mark -->
                                    <svg v-if="form.deptid === d.id" class="absolute top-2 right-2 w-4 h-4" :class="deptStyle(d.name).iconColor" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                            <p v-if="form.errors.deptid" class="mt-1.5 text-[12px] text-red-600">{{ form.errors.deptid }}</p>
                        </div>

                        <!-- Related Service -->
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-2">
                                Related Service / Domain
                                <span class="text-red-500 ml-0.5">*</span>
                            </label>

                            <!-- Selected service chip -->
                            <div v-if="form.service_id && selectedService" class="flex items-center gap-2 mb-2">
                                <div class="inline-flex items-center gap-2.5 bg-white border border-gray-200 rounded-xl px-3 py-2.5 shadow-sm">
                                    <div class="flex-shrink-0 w-7 h-7 rounded-lg flex items-center justify-center"
                                        :class="selectedService.type === 'domain' ? 'bg-violet-100' : 'bg-sky-100'">
                                        <svg v-if="selectedService.type !== 'domain'" class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z" />
                                        </svg>
                                        <svg v-else class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 003 12c0-1.605.42-3.113 1.157-4.418" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[12.5px] font-medium text-gray-800 truncate max-w-[240px]">{{ selectedService.label }}</p>
                                        <div class="flex items-center gap-1 mt-0.5">
                                            <span class="inline-block w-1.5 h-1.5 rounded-full" :class="statusStyle(selectedService.status).dot"></span>
                                            <span class="text-[10px] text-gray-400 capitalize">{{ selectedService.status }}</span>
                                        </div>
                                    </div>
                                    <button type="button" @click="clearService" class="text-gray-300 hover:text-red-500 transition-colors ml-1 p-0.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Search / select dropdown -->
                            <div v-else class="relative">
                                <div class="relative">
                                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                    </svg>
                                    <input
                                        v-model="serviceSearch"
                                        @focus="showServiceDropdown = true"
                                        @blur="setTimeout(() => showServiceDropdown = false, 200)"
                                        type="text"
                                        class="w-full text-[13px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400 pl-9"
                                        placeholder="Search your services or domains…" />
                                </div>

                                <!-- Dropdown list -->
                                <div v-if="showServiceDropdown && filteredServices.length"
                                    class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                                    <button v-for="svc in filteredServices" :key="svc.id" type="button"
                                        @mousedown.prevent="selectService(svc)"
                                        class="w-full flex items-center gap-3 px-3.5 py-3 text-left hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-0 first:rounded-t-xl last:rounded-b-xl">
                                        <!-- Icon -->
                                        <div class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center"
                                            :class="svc.type === 'domain' ? 'bg-violet-100' : 'bg-sky-100'">
                                            <!-- Server/hosting icon -->
                                            <svg v-if="svc.type !== 'domain'" class="w-4.5 h-4.5 text-sky-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z" />
                                            </svg>
                                            <!-- Domain/globe icon -->
                                            <svg v-else class="w-4.5 h-4.5 text-violet-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 003 12c0-1.605.42-3.113 1.157-4.418" />
                                            </svg>
                                        </div>
                                        <!-- Label + status -->
                                        <div class="flex-1 min-w-0">
                                            <p class="text-[12.5px] font-medium text-gray-800 truncate">{{ svc.label }}</p>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <span class="inline-block w-1.5 h-1.5 rounded-full" :class="statusStyle(svc.status).dot"></span>
                                                <span class="text-[10.5px] capitalize" :class="svc.status === 'Active' ? 'text-emerald-600' : 'text-gray-400'">{{ svc.status }}</span>
                                                <span class="text-[10px] text-gray-300">·</span>
                                                <span class="text-[10.5px] text-gray-400 capitalize">{{ svc.type === 'domain' ? 'Domain' : 'Hosting' }}</span>
                                            </div>
                                        </div>
                                        <!-- Status badge -->
                                        <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border"
                                            :class="statusStyle(svc.status).badge">
                                            {{ svc.status }}
                                        </span>
                                    </button>
                                </div>
                                <div v-else-if="showServiceDropdown && serviceSearch && !filteredServices.length"
                                    class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg px-4 py-4">
                                    <div class="text-center">
                                        <svg class="w-6 h-6 mx-auto text-gray-300 mb-1" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                        </svg>
                                        <p class="text-[12px] text-gray-400">No matching services found</p>
                                    </div>
                                </div>
                            </div>
                            <p v-if="form.errors.service_id" class="mt-1.5 text-[12px] text-red-600">{{ form.errors.service_id }}</p>
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

                        <!-- Access Credentials (multiple) -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="flex items-center gap-2 text-[13px] font-semibold text-gray-700">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                                    </svg>
                                    Access Credentials
                                    <span class="font-normal text-gray-400">(optional)</span>
                                </label>
                                <button type="button" @click="addCredential"
                                    class="inline-flex items-center gap-1 text-[12px] font-medium text-indigo-600 hover:text-indigo-700 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    Add Credentials
                                </button>
                            </div>

                            <!-- Warning banner -->
                            <div v-if="credentials.length" class="flex items-start gap-2 text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-3">
                                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                                <p>Credentials will be included in your ticket message. <strong>Remember to change your passwords</strong> after the issue is resolved.</p>
                            </div>

                            <!-- Credential cards -->
                            <div class="space-y-3">
                                <div v-for="(cred, idx) in credentials" :key="idx"
                                    class="p-4 bg-gray-50 border border-gray-200 rounded-xl space-y-3 relative">
                                    <!-- Header with type selector and remove button -->
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[13px]">{{ credentialTypes.find(t => t.value === cred.type)?.icon || '🔑' }}</span>
                                            <select v-model="cred.type"
                                                class="text-[12px] font-semibold rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 bg-white pr-8 py-1.5">
                                                <option v-for="t in credentialTypes" :key="t.value" :value="t.value">{{ t.value }}</option>
                                            </select>
                                            <span class="text-[11px] text-gray-400">#{{ idx + 1 }}</span>
                                        </div>
                                        <button type="button" @click="removeCredential(idx)"
                                            class="text-gray-400 hover:text-red-500 transition-colors p-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-600 mb-1">Host / IP</label>
                                            <input v-model="cred.host" type="text"
                                                class="w-full text-[12px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 bg-white"
                                                placeholder="e.g. 192.168.1.1" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-600 mb-1">Port</label>
                                            <input v-model="cred.port" type="text"
                                                class="w-full text-[12px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 bg-white"
                                                placeholder="e.g. 22" />
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-600 mb-1">Username</label>
                                            <input v-model="cred.username" type="text"
                                                class="w-full text-[12px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 bg-white"
                                                placeholder="e.g. root" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-600 mb-1">Password</label>
                                            <input v-model="cred.password" type="password"
                                                class="w-full text-[12px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 bg-white"
                                                placeholder="••••••••" />
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-gray-600 mb-1">Additional Notes</label>
                                        <textarea v-model="cred.notes" rows="2"
                                            class="w-full text-[12px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 resize-none bg-white"
                                            placeholder="cPanel URL, control panel type, or other details…" />
                                    </div>
                                </div>
                            </div>

                            <!-- Add another button (when credentials exist) -->
                            <button v-if="credentials.length" type="button" @click="addCredential"
                                class="mt-2 w-full py-2 border-2 border-dashed border-gray-200 rounded-lg text-[12px] text-gray-400 hover:text-indigo-600 hover:border-indigo-300 transition-all">
                                + Add Another Credential Set
                            </button>
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
