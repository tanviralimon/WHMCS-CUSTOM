<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ ticket: Object });

const replies = props.ticket?.replies?.reply || [];

const form = useForm({ message: '' });

function submit() {
    form.post(route('tickets.reply', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function statusColor(status) {
    const s = (status || '').toLowerCase();
    if (['open', 'customer reply'].includes(s)) return 'bg-blue-100 text-blue-700';
    if (s === 'answered') return 'bg-emerald-100 text-emerald-700';
    if (s === 'closed') return 'bg-slate-200 text-slate-600';
    return 'bg-amber-100 text-amber-700';
}
</script>

<template>
    <Head :title="'Ticket #' + ticket.tid" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <Link :href="route('tickets.index')" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl font-bold text-slate-900 truncate">{{ ticket.subject }}</h1>
                    <p class="text-slate-500 text-sm">#{{ ticket.tid }} · {{ ticket.deptname }} · {{ ticket.date }}</p>
                </div>
                <span :class="['px-3 py-1 rounded-full text-xs font-semibold', statusColor(ticket.status)]">{{ ticket.status }}</span>
            </div>

            <!-- Original message -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold">
                        {{ (ticket.name || 'U').charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <p class="font-medium text-slate-900">{{ ticket.name }}</p>
                        <p class="text-xs text-slate-500">{{ ticket.date }}</p>
                    </div>
                </div>
                <div class="prose prose-slate max-w-none text-sm" v-html="ticket.message" />
            </div>

            <!-- Replies -->
            <div v-for="reply in replies" :key="reply.id" :class="[
                'rounded-2xl shadow-sm border p-6',
                reply.admin ? 'bg-blue-50 border-blue-200' : 'bg-white border-slate-200'
            ]">
                <div class="flex items-center gap-3 mb-3">
                    <div :class="[
                        'w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold',
                        reply.admin ? 'bg-gradient-to-br from-emerald-400 to-teal-500' : 'bg-gradient-to-br from-blue-400 to-purple-500'
                    ]">
                        {{ (reply.name || reply.admin || 'U').charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <p class="font-medium text-slate-900">
                            {{ reply.admin || reply.name }}
                            <span v-if="reply.admin" class="text-xs font-normal text-emerald-600 ml-1">Staff</span>
                        </p>
                        <p class="text-xs text-slate-500">{{ reply.date }}</p>
                    </div>
                </div>
                <div class="prose prose-slate max-w-none text-sm" v-html="reply.message" />
            </div>

            <!-- Reply form -->
            <div v-if="ticket.status?.toLowerCase() !== 'closed'" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">Reply</h3>
                <form @submit.prevent="submit" class="space-y-4">
                    <textarea
                        v-model="form.message"
                        rows="4"
                        placeholder="Type your reply..."
                        class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500 resize-none"
                        required
                    />
                    <div v-if="form.errors.message" class="text-sm text-red-600">{{ form.errors.message }}</div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-semibold rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all disabled:opacity-50"
                    >
                        <svg v-if="!form.processing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                        {{ form.processing ? 'Sending...' : 'Send Reply' }}
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
