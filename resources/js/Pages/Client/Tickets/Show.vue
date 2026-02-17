<script setup>
import { ref, computed, nextTick, onMounted } from 'vue';
import { useForm, router, Link, usePage } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({ ticket: Object });
const ticket = props.ticket;
const flash = computed(() => usePage().props?.flash || {});

const rawReplies = computed(() => ticket.replies?.reply || []);

// Helper: strip HTML tags and normalise whitespace for comparison
function normalise(str) {
    return (str || '').replace(/<[^>]*>/g, '').replace(/&[a-z]+;/gi, ' ').replace(/\s+/g, ' ').trim();
}

// Build a unified, chronologically-sorted conversation
const conversation = computed(() => {
    const msgs = [];

    // Original message
    const origNorm = normalise(ticket.message);
    const origDate = (ticket.date || '').trim();

    msgs.push({
        id: 'original',
        name: ticket.name || 'You',
        email: ticket.email || '',
        date: ticket.date,
        message: ticket.message,
        isAdmin: false,
        isOriginal: true,
        attachments: ticket.attachments ? (Array.isArray(ticket.attachments) ? ticket.attachments : []) : [],
    });

    // Replies — skip any reply that duplicates the original message.
    // WHMCS GetTicket API includes the opening message both in the
    // top-level fields AND as the first entry in the replies array.
    // We compare normalised text (HTML-stripped) and date to catch this.
    for (const r of rawReplies.value) {
        const rNorm = normalise(r.message);
        const rDate = (r.date || '').trim();
        const isDupe = rDate === origDate && !r.admin && rNorm === origNorm;
        if (isDupe) continue;

        msgs.push({
            id: r.id,
            name: r.admin || r.name || 'You',
            email: r.email || '',
            date: r.date,
            message: r.message,
            isAdmin: !!r.admin,
            isOriginal: false,
            attachments: r.attachments ? (Array.isArray(r.attachments) ? r.attachments : []) : [],
        });
    }
    return msgs;
});

const replyForm = useForm({ message: '', attachments: [] });
const replyCredentials = ref([]);
const closing = ref(false);
const replyBox = ref(null);
const replyFileInput = ref(null);
const replyDragOver = ref(false);
const charCount = computed(() => replyForm.message.length);
const isClosed = computed(() => ticket.status === 'Closed');

const credentialTypes = [
    { value: 'SSH', icon: '🖥️' },
    { value: 'cPanel', icon: '⚙️' },
    { value: 'FTP', icon: '📁' },
    { value: 'Database', icon: '🗄️' },
    { value: 'WordPress', icon: '📝' },
    { value: 'Other', icon: '🔑' },
];

function addReplyCredential() {
    replyCredentials.value.push({ type: 'SSH', host: '', port: '', username: '', password: '', notes: '' });
}

function removeReplyCredential(idx) {
    replyCredentials.value.splice(idx, 1);
}

const maxFiles = 5;
const maxSizeMB = 2;
const allowedExts = ['jpg', 'jpeg', 'gif', 'png', 'txt', 'pdf', 'zip', 'doc', 'docx'];

function handleReplyFiles(fileList) {
    const files = Array.from(fileList);
    for (const file of files) {
        if (replyForm.attachments.length >= maxFiles) break;
        const ext = file.name.split('.').pop().toLowerCase();
        if (!allowedExts.includes(ext)) continue;
        if (file.size > maxSizeMB * 1024 * 1024) continue;
        if (replyForm.attachments.some(f => f.name === file.name && f.size === file.size)) continue;
        replyForm.attachments.push(file);
    }
    if (replyFileInput.value) replyFileInput.value.value = '';
}

function onReplyFileChange(e) { handleReplyFiles(e.target.files); }
function onReplyDrop(e) { replyDragOver.value = false; handleReplyFiles(e.dataTransfer.files); }
function removeReplyFile(idx) { replyForm.attachments.splice(idx, 1); }
function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function submitReply() {
    const data = new FormData();
    data.append('message', replyForm.message);

    replyForm.attachments.forEach((file, i) => {
        data.append(`attachments[${i}]`, file);
    });

    if (replyCredentials.value.length > 0) {
        data.append('credentials', JSON.stringify(replyCredentials.value));
    }

    replyForm.processing = true;
    router.post(route('client.tickets.reply', ticket.id), data, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset();
            replyForm.attachments = [];
            replyCredentials.value = [];
            nextTick(() => {
                window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
            });
        },
        onFinish: () => { replyForm.processing = false; },
        onError: (errors) => { replyForm.errors = errors; },
    });
}

function closeTicket() {
    closing.value = false;
    router.post(route('client.tickets.close', ticket.id));
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    if (isNaN(date)) return dateStr;
    return date.toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit',
    });
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
}

onMounted(() => {
    // Auto scroll to latest reply
    nextTick(() => {
        const el = document.getElementById('reply-form');
        if (el && rawReplies.value.length > 2) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
});
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
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-lg font-bold text-gray-900">{{ ticket.subject }}</h1>
                        <StatusBadge :status="ticket.status" />
                    </div>
                    <p class="text-[12px] text-gray-400 mt-0.5">
                        Ticket #{{ ticket.tid }} · {{ ticket.department || ticket.deptname }} · Opened {{ formatDate(ticket.date) }}
                    </p>
                </div>
            </div>
        </template>
        <template #actions>
            <div class="flex items-center gap-2">
                <span class="text-[11px] font-medium px-2 py-0.5 rounded-full"
                    :class="{
                        'bg-red-50 text-red-600': ticket.priority === 'High',
                        'bg-amber-50 text-amber-600': ticket.priority === 'Medium',
                        'bg-green-50 text-green-600': ticket.priority === 'Low',
                    }">{{ ticket.priority }} Priority</span>
                <button v-if="!isClosed" @click="closing = true"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[12px] font-medium text-gray-600 bg-white rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Close Ticket
                </button>
            </div>
        </template>

        <!-- Success flash -->
        <div v-if="flash.success" class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-[13px] text-emerald-700 flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ flash.success }}
        </div>

        <!-- Closed notice -->
        <div v-if="isClosed" class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded-lg text-[13px] text-gray-500 flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
            This ticket has been closed. Open a new ticket if you need further assistance.
        </div>

        <div class="max-w-4xl mx-auto">
            <!-- Conversation thread -->
            <div class="space-y-1">
                <div v-for="(msg, idx) in conversation" :key="msg.id" class="flex gap-3" :class="msg.isAdmin ? '' : 'flex-row-reverse'">
                    <!-- Avatar -->
                    <div class="flex-shrink-0 mt-1">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold"
                            :class="msg.isAdmin ? 'bg-emerald-100 text-emerald-700' : 'bg-indigo-100 text-indigo-700'">
                            {{ getInitials(msg.name) }}
                        </div>
                    </div>

                    <!-- Message bubble -->
                    <div class="max-w-[75%] min-w-0">
                        <!-- Name + time -->
                        <div class="flex items-center gap-2 mb-1 px-1" :class="msg.isAdmin ? '' : 'flex-row-reverse'">
                            <span class="text-[12px] font-semibold text-gray-700">{{ msg.name }}</span>
                            <span v-if="msg.isAdmin" class="text-[9px] font-semibold bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full uppercase tracking-wider">Staff</span>
                            <span class="text-[10px] text-gray-400">{{ formatDate(msg.date) }}</span>
                        </div>

                        <!-- Bubble -->
                        <div class="rounded-2xl px-4 py-3 text-[13px] leading-relaxed"
                            :class="msg.isAdmin
                                ? 'bg-white border border-gray-200 rounded-tl-sm text-gray-700'
                                : 'bg-indigo-600 text-white rounded-tr-sm'">
                            <div class="whitespace-pre-wrap break-words [&_a]:underline"
                                :class="msg.isAdmin ? '[&_a]:text-indigo-600' : '[&_a]:text-indigo-200'"
                                v-html="msg.message" />
                        </div>

                        <!-- Attachments -->
                        <div v-if="msg.attachments && msg.attachments.length" class="mt-2 px-1 space-y-1">
                            <div v-for="att in msg.attachments" :key="att.filename"
                                class="inline-flex items-center gap-1.5 text-[11px] text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-2.5 py-1.5">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                                </svg>
                                {{ att.filename || att }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reply form -->
            <div v-if="!isClosed" id="reply-form" class="mt-8">
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                    <form @submit.prevent="submitReply">
                        <div class="p-4 space-y-4">
                            <textarea v-model="replyForm.message" ref="replyBox" rows="4"
                                class="w-full text-[13px] border-0 focus:ring-0 resize-none placeholder-gray-400 p-0"
                                placeholder="Type your reply…" />

                            <!-- Attachments drop zone -->
                            <div>
                                <div @dragover.prevent="replyDragOver = true" @dragleave="replyDragOver = false" @drop.prevent="onReplyDrop"
                                    @click="replyFileInput?.click()"
                                    class="border-2 border-dashed rounded-xl px-3 py-4 text-center cursor-pointer transition-all"
                                    :class="replyDragOver
                                        ? 'border-indigo-400 bg-indigo-50'
                                        : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'">
                                    <div class="flex items-center justify-center gap-2">
                                        <svg class="w-5 h-5" :class="replyDragOver ? 'text-indigo-500' : 'text-gray-300'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                                        </svg>
                                        <p class="text-[11px] text-gray-500">
                                            <span class="font-medium text-indigo-600">Attach files</span> or drag here
                                            <span class="text-gray-400 ml-1">(max {{ maxFiles }}, {{ maxSizeMB }}MB each)</span>
                                        </p>
                                    </div>
                                    <input ref="replyFileInput" type="file" multiple class="hidden" :accept="allowedExts.map(e => '.' + e).join(',')" @change="onReplyFileChange" />
                                </div>

                                <!-- Attached files -->
                                <div v-if="replyForm.attachments.length" class="mt-2 flex flex-wrap gap-2">
                                    <div v-for="(file, idx) in replyForm.attachments" :key="idx"
                                        class="inline-flex items-center gap-1.5 bg-gray-50 border border-gray-200 rounded-lg px-2.5 py-1.5 text-[11px]">
                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                                        </svg>
                                        <span class="text-gray-700 font-medium max-w-[120px] truncate">{{ file.name }}</span>
                                        <span class="text-gray-400">{{ formatSize(file.size) }}</span>
                                        <button type="button" @click="removeReplyFile(idx)" class="text-gray-400 hover:text-red-500 ml-0.5">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Credentials -->
                            <div>
                                <button type="button" @click="addReplyCredential"
                                    class="flex items-center gap-1.5 text-[12px] text-gray-500 hover:text-indigo-600 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                                    </svg>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    Add Access Credentials
                                </button>

                                <div v-if="replyCredentials.length" class="mt-3 space-y-3">
                                    <div class="flex items-start gap-2 text-[10px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5">
                                        <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                        </svg>
                                        <p>Credentials will be included in your reply. <strong>Change passwords</strong> after resolved.</p>
                                    </div>

                                    <div v-for="(cred, idx) in replyCredentials" :key="idx"
                                        class="p-3 bg-gray-50 border border-gray-200 rounded-xl space-y-2">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[12px]">{{ credentialTypes.find(t => t.value === cred.type)?.icon || '🔑' }}</span>
                                                <select v-model="cred.type"
                                                    class="text-[11px] font-semibold rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 bg-white pr-7 py-1">
                                                    <option v-for="t in credentialTypes" :key="t.value" :value="t.value">{{ t.value }}</option>
                                                </select>
                                            </div>
                                            <button type="button" @click="removeReplyCredential(idx)" class="text-gray-400 hover:text-red-500">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <input v-model="cred.host" type="text"
                                                class="text-[11px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 bg-white"
                                                placeholder="Host / IP" />
                                            <input v-model="cred.port" type="text"
                                                class="text-[11px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 bg-white"
                                                placeholder="Port" />
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <input v-model="cred.username" type="text"
                                                class="text-[11px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 bg-white"
                                                placeholder="Username" />
                                            <input v-model="cred.password" type="password"
                                                class="text-[11px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 bg-white"
                                                placeholder="Password" />
                                        </div>
                                        <textarea v-model="cred.notes" rows="1"
                                            class="w-full text-[11px] rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 resize-none bg-white"
                                            placeholder="Notes (URL, panel type…)" />
                                    </div>

                                    <button type="button" @click="addReplyCredential"
                                        class="w-full py-1.5 border-2 border-dashed border-gray-200 rounded-lg text-[11px] text-gray-400 hover:text-indigo-600 hover:border-indigo-300 transition-all">
                                        + Add Another
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-t border-gray-100">
                            <span class="text-[11px] text-gray-400">{{ charCount }} / 10,000</span>
                            <button type="submit" :disabled="replyForm.processing || !replyForm.message.trim()"
                                class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors shadow-sm">
                                <svg v-if="replyForm.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                </svg>
                                {{ replyForm.processing ? 'Sending…' : 'Send Reply' }}
                            </button>
                        </div>
                    </form>
                </div>
                <p v-if="replyForm.errors.message" class="mt-2 text-[12px] text-red-600 px-1">{{ replyForm.errors.message }}</p>
            </div>
        </div>

        <ConfirmModal :show="closing" title="Close Ticket"
            message="Are you sure you want to close this ticket? You can open a new one later if needed."
            confirmText="Close Ticket" variant="danger"
            @confirm="closeTicket" @cancel="closing = false" />
    </ClientLayout>
</template>
