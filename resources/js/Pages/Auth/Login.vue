<script setup>
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({ canResetPassword: Boolean, status: String });

const features = computed(() => usePage().props.features || {});
const ssoEnabled = computed(() => {
    if (!features.value.sso) return false;
    try { route('sso.login'); return true; } catch { return false; }
});

const form = useForm({ email: '', password: '', remember: false });

function submit() {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Sign In" />
    <div class="min-h-screen flex">
        <!-- Left - Branding -->
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-slate-900 via-blue-900 to-indigo-900 items-center justify-center p-12 relative overflow-hidden">
            <!-- Background decoration -->
            <div class="absolute inset-0 opacity-10">
                <div class="absolute top-20 left-20 w-72 h-72 bg-blue-500 rounded-full blur-3xl"></div>
                <div class="absolute bottom-20 right-20 w-96 h-96 bg-purple-500 rounded-full blur-3xl"></div>
            </div>

            <div class="relative z-10 text-center max-w-md">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl flex items-center justify-center mx-auto mb-8">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h1 class="text-4xl font-bold text-white mb-4">Client Portal</h1>
                <p class="text-blue-200 text-lg leading-relaxed">Manage your services, invoices, and support tickets in one place.</p>
            </div>
        </div>

        <!-- Right - Form -->
        <div class="flex-1 flex items-center justify-center p-6 bg-slate-50">
            <div class="w-full max-w-md">
                <div class="lg:hidden text-center mb-8">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900">Client Portal</h1>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
                    <h2 class="text-2xl font-bold text-slate-900 mb-1">Welcome back</h2>
                    <p class="text-slate-500 mb-6">Sign in to your account</p>

                    <div v-if="status" class="mb-4 text-sm font-medium text-emerald-600 bg-emerald-50 rounded-xl p-3">{{ status }}</div>

                    <!-- SSO Login Button -->
                    <template v-if="ssoEnabled">
                        <a :href="route('sso.login')"
                            class="w-full mb-5 flex items-center justify-center gap-2.5 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-semibold rounded-xl hover:from-emerald-700 hover:to-teal-700 transition-all shadow-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                            </svg>
                            Sign in with WHMCS Account
                        </a>

                        <div class="relative mb-5">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-slate-200"></div>
                            </div>
                            <div class="relative flex justify-center text-[12px]">
                                <span class="bg-white px-3 text-slate-400">or sign in with email</span>
                            </div>
                        </div>
                    </template>

                    <form @submit.prevent="submit" class="space-y-5">
                        <div>
                            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email address</label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                required
                                class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                placeholder="you@example.com"
                            />
                            <div v-if="form.errors.email" class="text-sm text-red-600 mt-1">{{ form.errors.email }}</div>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                autocomplete="current-password"
                                required
                                class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                placeholder="••••••••"
                            />
                            <div v-if="form.errors.password" class="text-sm text-red-600 mt-1">{{ form.errors.password }}</div>
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                <span class="text-sm text-slate-600">Remember me</span>
                            </label>
                            <a v-if="canResetPassword" :href="route('password.request')" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                                Forgot password?
                            </a>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all disabled:opacity-50 shadow-sm"
                        >
                            {{ form.processing ? 'Signing in...' : 'Sign in' }}
                        </button>
                    </form>

                    <p class="mt-5 text-center text-[13px] text-slate-500">
                        Don't have an account?
                        <a :href="route('register')" class="text-blue-600 hover:text-blue-700 font-medium">Create one</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
