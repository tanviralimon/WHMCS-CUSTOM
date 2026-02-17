<script setup>
import { ref, computed, nextTick, onMounted } from 'vue';
import { useForm, router, Link, usePage } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({ ticket: Object });
const ticket = props.ticket;
const flash = computed(() => usePage().props?.flash || {});

const replies = computed(() => ticket.replies?.reply || []);

// Build a unified, chronologically-sorted conversation
const conversation = computed(() => {
    const msgs = [];

    // Original message
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

    // Replies
    for (const r of replies.value) {
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

const replyForm = useForm({ message: '' });
const closing = ref(false);
const replyBox = ref(null);
const charCount = computed(() => replyForm.message.length);
const isClosed = computed(() => ticket.status === 'Closed');

function submitReply() {
    replyForm.post(route('client.tickets.reply', ticket.tid || ticket.id), {
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset();
            nextTick(() => {
                window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
            });
        },
    });
}

function closeTicket() {
    closing.value = false;
    router.post(route('client.tickets.close', ticket.tid || ticket.id));
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
        if (el && replies.value.length > 2) {
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
                        <div class="p-4">
                            <textarea v-model="replyForm.message" ref="replyBox" rows="4"
                                class="w-full text-[13px] border-0 focus:ring-0 resize-none placeholder-gray-400 p-0"
                                placeholder="Type your reply…" />
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
