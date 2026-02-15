<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';

const props = defineProps({ client: Object });

const flash = computed(() => usePage().props.flash || {});

const countries = [
    { code: 'AF', name: 'Afghanistan' }, { code: 'AL', name: 'Albania' }, { code: 'DZ', name: 'Algeria' },
    { code: 'AD', name: 'Andorra' }, { code: 'AO', name: 'Angola' }, { code: 'AR', name: 'Argentina' },
    { code: 'AM', name: 'Armenia' }, { code: 'AU', name: 'Australia' }, { code: 'AT', name: 'Austria' },
    { code: 'AZ', name: 'Azerbaijan' }, { code: 'BS', name: 'Bahamas' }, { code: 'BH', name: 'Bahrain' },
    { code: 'BD', name: 'Bangladesh' }, { code: 'BB', name: 'Barbados' }, { code: 'BY', name: 'Belarus' },
    { code: 'BE', name: 'Belgium' }, { code: 'BZ', name: 'Belize' }, { code: 'BJ', name: 'Benin' },
    { code: 'BT', name: 'Bhutan' }, { code: 'BO', name: 'Bolivia' }, { code: 'BA', name: 'Bosnia and Herzegovina' },
    { code: 'BW', name: 'Botswana' }, { code: 'BR', name: 'Brazil' }, { code: 'BN', name: 'Brunei' },
    { code: 'BG', name: 'Bulgaria' }, { code: 'KH', name: 'Cambodia' }, { code: 'CM', name: 'Cameroon' },
    { code: 'CA', name: 'Canada' }, { code: 'CL', name: 'Chile' }, { code: 'CN', name: 'China' },
    { code: 'CO', name: 'Colombia' }, { code: 'CR', name: 'Costa Rica' }, { code: 'HR', name: 'Croatia' },
    { code: 'CU', name: 'Cuba' }, { code: 'CY', name: 'Cyprus' }, { code: 'CZ', name: 'Czech Republic' },
    { code: 'DK', name: 'Denmark' }, { code: 'DO', name: 'Dominican Republic' }, { code: 'EC', name: 'Ecuador' },
    { code: 'EG', name: 'Egypt' }, { code: 'SV', name: 'El Salvador' }, { code: 'EE', name: 'Estonia' },
    { code: 'ET', name: 'Ethiopia' }, { code: 'FI', name: 'Finland' }, { code: 'FR', name: 'France' },
    { code: 'GE', name: 'Georgia' }, { code: 'DE', name: 'Germany' }, { code: 'GH', name: 'Ghana' },
    { code: 'GR', name: 'Greece' }, { code: 'GT', name: 'Guatemala' }, { code: 'HN', name: 'Honduras' },
    { code: 'HK', name: 'Hong Kong' }, { code: 'HU', name: 'Hungary' }, { code: 'IS', name: 'Iceland' },
    { code: 'IN', name: 'India' }, { code: 'ID', name: 'Indonesia' }, { code: 'IR', name: 'Iran' },
    { code: 'IQ', name: 'Iraq' }, { code: 'IE', name: 'Ireland' }, { code: 'IL', name: 'Israel' },
    { code: 'IT', name: 'Italy' }, { code: 'JM', name: 'Jamaica' }, { code: 'JP', name: 'Japan' },
    { code: 'JO', name: 'Jordan' }, { code: 'KZ', name: 'Kazakhstan' }, { code: 'KE', name: 'Kenya' },
    { code: 'KW', name: 'Kuwait' }, { code: 'LV', name: 'Latvia' }, { code: 'LB', name: 'Lebanon' },
    { code: 'LY', name: 'Libya' }, { code: 'LT', name: 'Lithuania' }, { code: 'LU', name: 'Luxembourg' },
    { code: 'MY', name: 'Malaysia' }, { code: 'MV', name: 'Maldives' }, { code: 'MT', name: 'Malta' },
    { code: 'MX', name: 'Mexico' }, { code: 'MD', name: 'Moldova' }, { code: 'MC', name: 'Monaco' },
    { code: 'MN', name: 'Mongolia' }, { code: 'MA', name: 'Morocco' }, { code: 'MZ', name: 'Mozambique' },
    { code: 'MM', name: 'Myanmar' }, { code: 'NP', name: 'Nepal' }, { code: 'NL', name: 'Netherlands' },
    { code: 'NZ', name: 'New Zealand' }, { code: 'NI', name: 'Nicaragua' }, { code: 'NG', name: 'Nigeria' },
    { code: 'NO', name: 'Norway' }, { code: 'OM', name: 'Oman' }, { code: 'PK', name: 'Pakistan' },
    { code: 'PA', name: 'Panama' }, { code: 'PY', name: 'Paraguay' }, { code: 'PE', name: 'Peru' },
    { code: 'PH', name: 'Philippines' }, { code: 'PL', name: 'Poland' }, { code: 'PT', name: 'Portugal' },
    { code: 'QA', name: 'Qatar' }, { code: 'RO', name: 'Romania' }, { code: 'RU', name: 'Russia' },
    { code: 'SA', name: 'Saudi Arabia' }, { code: 'RS', name: 'Serbia' }, { code: 'SG', name: 'Singapore' },
    { code: 'SK', name: 'Slovakia' }, { code: 'SI', name: 'Slovenia' }, { code: 'ZA', name: 'South Africa' },
    { code: 'KR', name: 'South Korea' }, { code: 'ES', name: 'Spain' }, { code: 'LK', name: 'Sri Lanka' },
    { code: 'SE', name: 'Sweden' }, { code: 'CH', name: 'Switzerland' }, { code: 'TW', name: 'Taiwan' },
    { code: 'TZ', name: 'Tanzania' }, { code: 'TH', name: 'Thailand' }, { code: 'TN', name: 'Tunisia' },
    { code: 'TR', name: 'Turkey' }, { code: 'UA', name: 'Ukraine' }, { code: 'AE', name: 'United Arab Emirates' },
    { code: 'GB', name: 'United Kingdom' }, { code: 'US', name: 'United States' }, { code: 'UY', name: 'Uruguay' },
    { code: 'UZ', name: 'Uzbekistan' }, { code: 'VE', name: 'Venezuela' }, { code: 'VN', name: 'Vietnam' },
    { code: 'ZM', name: 'Zambia' }, { code: 'ZW', name: 'Zimbabwe' },
];

// Resolve country name from code
const countryName = computed(() => {
    const c = countries.find(c => c.code === props.client.country);
    return c ? c.name : props.client.country || '—';
});
</script>

<template>
    <ClientLayout>
        <template #header>
            <div>
                <h1 class="text-lg font-bold text-gray-900">Profile</h1>
                <p class="text-[13px] text-gray-500">Your personal information and contact details</p>
            </div>
        </template>

        <!-- Success Message -->
        <div v-if="flash.success" class="mb-6 flex items-center gap-3 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-lg">
            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <p class="text-[13px] text-emerald-700 font-medium">{{ flash.success }}</p>
        </div>

        <!-- Locked Notice -->
        <div class="mb-6 flex items-start gap-3 px-4 py-3.5 bg-amber-50 border border-amber-200 rounded-lg">
            <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
            <div>
                <p class="text-[13px] font-semibold text-amber-800">Profile fields are locked</p>
                <p class="text-[12px] text-amber-700 mt-0.5">For security purposes, profile information can only be updated by an administrator. Please <a :href="route('client.tickets.create')" class="underline font-medium hover:text-amber-900">open a support ticket</a> to request changes.</p>
            </div>
        </div>

        <div class="max-w-3xl">
            <div class="space-y-6">
                <!-- Personal Information -->
                <Card title="Personal Information" description="Your basic identity details">
                    <div class="space-y-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">First Name</label>
                                <div class="w-full px-3 py-2 text-[13px] rounded-lg border border-gray-200 bg-gray-50 text-gray-700">{{ client.firstname || '—' }}</div>
                            </div>
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Last Name</label>
                                <div class="w-full px-3 py-2 text-[13px] rounded-lg border border-gray-200 bg-gray-50 text-gray-700">{{ client.lastname || '—' }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Email Address</label>
                                <div class="flex items-center w-full px-3 py-2 text-[13px] rounded-lg border border-gray-200 bg-gray-50 text-gray-700">
                                    <svg class="w-4 h-4 text-gray-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                                    {{ client.email || '—' }}
                                </div>
                            </div>
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Phone Number</label>
                                <div class="flex items-center w-full px-3 py-2 text-[13px] rounded-lg border border-gray-200 bg-gray-50 text-gray-700">
                                    <svg class="w-4 h-4 text-gray-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" /></svg>
                                    {{ client.phonenumber || '—' }}
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Company Name</label>
                            <div class="flex items-center w-full px-3 py-2 text-[13px] rounded-lg border border-gray-200 bg-gray-50 text-gray-700">
                                <svg class="w-4 h-4 text-gray-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg>
                                {{ client.companyname || '—' }}
                            </div>
                        </div>
                    </div>
                </Card>

                <!-- Address -->
                <Card title="Address" description="Your billing and correspondence address">
                    <div class="space-y-5">
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Address Line 1</label>
                            <div class="w-full px-3 py-2 text-[13px] rounded-lg border border-gray-200 bg-gray-50 text-gray-700">{{ client.address1 || '—' }}</div>
                        </div>

                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Address Line 2</label>
                            <div class="w-full px-3 py-2 text-[13px] rounded-lg border border-gray-200 bg-gray-50 text-gray-700">{{ client.address2 || '—' }}</div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">City</label>
                                <div class="w-full px-3 py-2 text-[13px] rounded-lg border border-gray-200 bg-gray-50 text-gray-700">{{ client.city || '—' }}</div>
                            </div>
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">State / Province</label>
                                <div class="w-full px-3 py-2 text-[13px] rounded-lg border border-gray-200 bg-gray-50 text-gray-700">{{ client.state || '—' }}</div>
                            </div>
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Postcode / ZIP</label>
                                <div class="w-full px-3 py-2 text-[13px] rounded-lg border border-gray-200 bg-gray-50 text-gray-700">{{ client.postcode || '—' }}</div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Country</label>
                            <div class="w-full px-3 py-2 text-[13px] rounded-lg border border-gray-200 bg-gray-50 text-gray-700">{{ countryName }}</div>
                        </div>
                    </div>
                </Card>
            </div>
        </div>
    </ClientLayout>
</template>
