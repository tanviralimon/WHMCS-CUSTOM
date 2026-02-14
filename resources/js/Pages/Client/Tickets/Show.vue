<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Card from '@/Components/Card.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({ ticket: Object });
const ticket = props.ticket;

const replies = computed(() => ticket.replies?.reply || []);

const replyForm = useForm({ message: '' });
const closing = ref(false);

function submitReply() {
    replyForm.post(route('client.tickets.reply', ticket.tid || ticket.id), {
        preserveScroll: true,
        onSuccess: () => replyForm.reset(),
    });
}

function closeTicket() {
    closing.value = false;
    router.post(route('client.tickets.close', ticket.tid || ticket.id));
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <h1 class="text-lg font-bold text-gray-900">#{{ ticket.tid }} — {{ ticket.subject }}</h1>
                <p class="text-[13px] text-gray-500">{{ ticket.department || ticket.deptname }} · Opened {{ ticket.date }}</p>
            </div>
        </template>
        <template #actions>
            <button v-if="ticket.status !== 'Closed'" @click="closing = true"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-[13px] font-medium text-gray-700 bg-white rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
                Close Ticket
            </button>
        </template>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
                <!-- Original message -->
                <Card>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
                            <span class="text-[12px] font-bold text-indigo-600">{{ (ticket.name || 'U')[0].toUpperCase() }}</span>
                        </div>
                        <div>
                            <p class="text-[13px] font-semibold text-gray-900">{{ ticket.name || 'You' }}</p>
                            <p class="text-[11px] text-gray-400">{{ ticket.date }}</p>
                        </div>
                    </div>
                    <div class="text-[13px] text-gray-700 whitespace-pre-wrap leading-relaxed" v-html="ticket.message" />
                </Card>

                <!-- Replies -->
                <Card v-for="reply in replies" :key="reply.id">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center"
                            :class="reply.admin ? 'bg-emerald-100' : 'bg-indigo-100'">
                            <span class="text-[12px] font-bold"
                                :class="reply.admin ? 'text-emerald-600' : 'text-indigo-600'">
                                {{ (reply.admin || reply.name || 'U')[0].toUpperCase() }}
                            </span>
                        </div>
                        <div>
                            <p class="text-[13px] font-semibold text-gray-900">
                                {{ reply.admin || reply.name || 'You' }}
                                <span v-if="reply.admin" class="ml-1 text-[10px] font-medium bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full">Staff</span>
                            </p>
                            <p class="text-[11px] text-gray-400">{{ reply.date }}</p>
                        </div>
                    </div>
                    <div class="text-[13px] text-gray-700 whitespace-pre-wrap leading-relaxed" v-html="reply.message" />
                </Card>

                <!-- Reply form -->
                <Card v-if="ticket.status !== 'Closed'">
                    <form @submit.prevent="submitReply">
                        <label class="block text-[13px] font-medium text-gray-700 mb-2">Reply</label>
                        <textarea v-model="replyForm.message" rows="5"
                            class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 resize-none"
                            placeholder="Type your reply..." />
                        <div v-if="replyForm.errors.message" class="mt-1 text-[12px] text-red-600">{{ replyForm.errors.message }}</div>
                        <div class="mt-3 flex justify-end">
                            <button type="submit" :disabled="replyForm.processing || !replyForm.message.trim()"
                                class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
                                Send Reply
                            </button>
                        </div>
                    </form>
                </Card>
            </div>

            <!-- Sidebar -->
            <div class="space-y-4">
                <Card title="Ticket Info">
                    <dl class="space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">Status</dt>
                            <dd><StatusBadge :status="ticket.status" /></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">Priority</dt>
                            <dd>
                                <span class="text-[12px] font-medium px-2 py-0.5 rounded-full"
                                    :class="{
                                        'bg-red-100 text-red-700': ticket.priority === 'High',
                                        'bg-orange-100 text-orange-700': ticket.priority === 'Medium',
                                        'bg-blue-100 text-blue-700': ticket.priority === 'Low',
                                    }">{{ ticket.priority }}</span>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">Department</dt>
                            <dd class="text-[13px] text-gray-900">{{ ticket.department || ticket.deptname }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">Opened</dt>
                            <dd class="text-[13px] text-gray-900">{{ ticket.date }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-[13px] text-gray-500">Last Reply</dt>
                            <dd class="text-[13px] text-gray-900">{{ ticket.lastreply }}</dd>
                        </div>
                    </dl>
                </Card>
            </div>
        </div>

        <ConfirmModal :show="closing" title="Close Ticket" message="Are you sure you want to close this ticket?" confirmText="Close Ticket" variant="danger" @confirm="closeTicket" @cancel="closing = false" />
    </ClientLayout>
</template>
