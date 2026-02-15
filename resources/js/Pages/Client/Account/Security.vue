<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';

defineProps({ client: Object, whmcsUrl: String });

const flash = computed(() => usePage().props.flash || {});
const showPassword = ref(false);

const pwForm = useForm({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
});

function changePassword() {
    pwForm.put(route('client.account.password'), {
        onSuccess: () => pwForm.reset(),
    });
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <h1 class="text-lg font-bold text-gray-900">Security</h1>
                <p class="text-[13px] text-gray-500">Manage your password and account security settings</p>
            </div>
        </template>

        <!-- Success Message -->
        <div v-if="flash.success" class="mb-6 flex items-center gap-3 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-lg">
            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <p class="text-[13px] text-emerald-700 font-medium">{{ flash.success }}</p>
        </div>

        <div class="max-w-2xl space-y-6">
            <!-- Change Password -->
            <Card title="Change Password" description="Update your WHMCS account password">
                <form @submit.prevent="changePassword" class="space-y-4">
                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Current Password</label>
                        <div class="relative">
                            <input v-model="pwForm.current_password" :type="showPassword ? 'text' : 'password'"
                                class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 pr-10" />
                            <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                            </button>
                        </div>
                        <p v-if="pwForm.errors.current_password" class="mt-1 text-[12px] text-red-600">{{ pwForm.errors.current_password }}</p>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1.5">New Password</label>
                        <input v-model="pwForm.new_password" type="password"
                            class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                        <p v-if="pwForm.errors.new_password" class="mt-1 text-[12px] text-red-600">{{ pwForm.errors.new_password }}</p>
                        <p class="mt-1 text-[11px] text-gray-400">Minimum 8 characters</p>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Confirm New Password</label>
                        <input v-model="pwForm.new_password_confirmation" type="password"
                            class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                    <div class="flex justify-end pt-1">
                        <button type="submit" :disabled="pwForm.processing"
                            class="inline-flex items-center gap-2 px-5 py-2.5 text-[13px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm">
                            <svg v-if="pwForm.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Update Password
                        </button>
                    </div>
                </form>
            </Card>

            <!-- Two-Factor Auth Info -->
            <Card title="Two-Factor Authentication">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-[14px] font-semibold text-gray-900">Manage 2FA via WHMCS</p>
                        <p class="text-[12px] text-gray-500 mt-0.5">Two-factor authentication settings are managed through the main WHMCS client area for enhanced security.</p>
                    </div>
                    <a v-if="whmcsUrl" :href="whmcsUrl" target="_blank"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors flex-shrink-0">
                        Manage 2FA
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                    </a>
                </div>
            </Card>

            <!-- Account Info -->
            <Card title="Account Information" description="Your WHMCS account details">
                <dl class="space-y-3">
                    <div class="flex justify-between items-center py-1">
                        <dt class="text-[13px] text-gray-500 flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                            Email
                        </dt>
                        <dd class="text-[13px] text-gray-900">{{ client.email }}</dd>
                    </div>
                    <div class="flex justify-between items-center py-1 border-t border-gray-50">
                        <dt class="text-[13px] text-gray-500 flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5l-3.9 19.5m-2.1-19.5l-3.9 19.5" /></svg>
                            Client ID
                        </dt>
                        <dd class="text-[13px] text-gray-900 font-mono bg-gray-50 px-2 py-0.5 rounded">#{{ client.id || client.clientid }}</dd>
                    </div>
                    <div v-if="client.datecreated" class="flex justify-between items-center py-1 border-t border-gray-50">
                        <dt class="text-[13px] text-gray-500 flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                            Member Since
                        </dt>
                        <dd class="text-[13px] text-gray-900">{{ client.datecreated }}</dd>
                    </div>
                    <div v-if="client.lastlogin" class="flex justify-between items-center py-1 border-t border-gray-50">
                        <dt class="text-[13px] text-gray-500 flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Last Login
                        </dt>
                        <dd class="text-[13px] text-gray-900">{{ client.lastlogin }}</dd>
                    </div>
                </dl>
            </Card>
        </div>
    </ClientLayout>
</template>
