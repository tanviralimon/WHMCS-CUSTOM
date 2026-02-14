<script setup>
import { useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';

defineProps({ client: Object, whmcsUrl: String });

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
            <h1 class="text-lg font-bold text-gray-900">Security</h1>
        </template>

        <div class="max-w-2xl space-y-6">
            <!-- Change Password -->
            <Card title="Change Password">
                <form @submit.prevent="changePassword" class="space-y-4">
                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Current Password</label>
                        <input v-model="pwForm.current_password" type="password"
                            class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                        <p v-if="pwForm.errors.current_password" class="mt-1 text-[12px] text-red-600">{{ pwForm.errors.current_password }}</p>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1.5">New Password</label>
                        <input v-model="pwForm.new_password" type="password"
                            class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                        <p v-if="pwForm.errors.new_password" class="mt-1 text-[12px] text-red-600">{{ pwForm.errors.new_password }}</p>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Confirm New Password</label>
                        <input v-model="pwForm.new_password_confirmation" type="password"
                            class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" :disabled="pwForm.processing"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm">
                            Update Password
                        </button>
                    </div>
                </form>
            </Card>

            <!-- Two-Factor Auth Info -->
            <Card title="Two-Factor Authentication">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-[14px] font-semibold text-gray-900">Manage 2FA via WHMCS</p>
                        <p class="text-[12px] text-gray-500 mt-0.5">Two-factor authentication settings are managed through the main WHMCS client area.</p>
                    </div>
                    <a v-if="whmcsUrl" :href="whmcsUrl + '/clientarea.php?action=security'" target="_blank"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-[13px] font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors flex-shrink-0">
                        Manage
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                    </a>
                </div>
            </Card>

            <!-- Account Info -->
            <Card title="Account Information">
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-[13px] text-gray-500">Email</dt>
                        <dd class="text-[13px] text-gray-900">{{ client.email }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[13px] text-gray-500">Client ID</dt>
                        <dd class="text-[13px] text-gray-900 font-mono">#{{ client.id || client.clientid }}</dd>
                    </div>
                    <div v-if="client.datecreated" class="flex justify-between">
                        <dt class="text-[13px] text-gray-500">Member Since</dt>
                        <dd class="text-[13px] text-gray-900">{{ client.datecreated }}</dd>
                    </div>
                    <div v-if="client.lastlogin" class="flex justify-between">
                        <dt class="text-[13px] text-gray-500">Last Login</dt>
                        <dd class="text-[13px] text-gray-900">{{ client.lastlogin }}</dd>
                    </div>
                </dl>
            </Card>
        </div>
    </ClientLayout>
</template>
