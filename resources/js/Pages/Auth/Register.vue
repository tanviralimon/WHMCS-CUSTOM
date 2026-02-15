<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';

const form = useForm({
    firstname: '',
    lastname: '',
    email: '',
    phone: '',
    companyname: '',
    address1: '',
    address2: '',
    city: '',
    state: '',
    postcode: '',
    country: 'BD',
    password: '',
    password_confirmation: '',
});

const countries = [
    { code: 'AF', name: 'Afghanistan' }, { code: 'AL', name: 'Albania' }, { code: 'DZ', name: 'Algeria' },
    { code: 'AR', name: 'Argentina' }, { code: 'AU', name: 'Australia' }, { code: 'AT', name: 'Austria' },
    { code: 'BD', name: 'Bangladesh' }, { code: 'BE', name: 'Belgium' }, { code: 'BR', name: 'Brazil' },
    { code: 'CA', name: 'Canada' }, { code: 'CN', name: 'China' }, { code: 'CO', name: 'Colombia' },
    { code: 'HR', name: 'Croatia' }, { code: 'CZ', name: 'Czech Republic' }, { code: 'DK', name: 'Denmark' },
    { code: 'EG', name: 'Egypt' }, { code: 'FI', name: 'Finland' }, { code: 'FR', name: 'France' },
    { code: 'DE', name: 'Germany' }, { code: 'GH', name: 'Ghana' }, { code: 'GR', name: 'Greece' },
    { code: 'HK', name: 'Hong Kong' }, { code: 'HU', name: 'Hungary' }, { code: 'IN', name: 'India' },
    { code: 'ID', name: 'Indonesia' }, { code: 'IR', name: 'Iran' }, { code: 'IQ', name: 'Iraq' },
    { code: 'IE', name: 'Ireland' }, { code: 'IL', name: 'Israel' }, { code: 'IT', name: 'Italy' },
    { code: 'JP', name: 'Japan' }, { code: 'JO', name: 'Jordan' }, { code: 'KE', name: 'Kenya' },
    { code: 'KR', name: 'South Korea' }, { code: 'KW', name: 'Kuwait' }, { code: 'MY', name: 'Malaysia' },
    { code: 'MX', name: 'Mexico' }, { code: 'MA', name: 'Morocco' }, { code: 'NL', name: 'Netherlands' },
    { code: 'NZ', name: 'New Zealand' }, { code: 'NG', name: 'Nigeria' }, { code: 'NO', name: 'Norway' },
    { code: 'PK', name: 'Pakistan' }, { code: 'PH', name: 'Philippines' }, { code: 'PL', name: 'Poland' },
    { code: 'PT', name: 'Portugal' }, { code: 'QA', name: 'Qatar' }, { code: 'RO', name: 'Romania' },
    { code: 'RU', name: 'Russia' }, { code: 'SA', name: 'Saudi Arabia' }, { code: 'SG', name: 'Singapore' },
    { code: 'ZA', name: 'South Africa' }, { code: 'ES', name: 'Spain' }, { code: 'LK', name: 'Sri Lanka' },
    { code: 'SE', name: 'Sweden' }, { code: 'CH', name: 'Switzerland' }, { code: 'TW', name: 'Taiwan' },
    { code: 'TH', name: 'Thailand' }, { code: 'TR', name: 'Turkey' }, { code: 'UA', name: 'Ukraine' },
    { code: 'AE', name: 'United Arab Emirates' }, { code: 'GB', name: 'United Kingdom' },
    { code: 'US', name: 'United States' }, { code: 'VN', name: 'Vietnam' },
];

function submit() {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Create Account" />
    <div class="min-h-screen flex">
        <!-- Left - Branding -->
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-slate-900 via-blue-900 to-indigo-900 items-center justify-center p-12 relative overflow-hidden">
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
                <h1 class="text-4xl font-bold text-white mb-4">Join Us</h1>
                <p class="text-blue-200 text-lg leading-relaxed">Create your account and start managing your services today.</p>
            </div>
        </div>

        <!-- Right - Form -->
        <div class="flex-1 flex items-center justify-center p-6 bg-slate-50 overflow-y-auto">
            <div class="w-full max-w-lg py-8">
                <div class="lg:hidden text-center mb-8">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900">Client Portal</h1>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
                    <h2 class="text-2xl font-bold text-slate-900 mb-1">Create Account</h2>
                    <p class="text-slate-500 mb-6">Fill in your details to get started</p>

                    <form @submit.prevent="submit" class="space-y-5">
                        <!-- Personal Info -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="firstname" class="block text-sm font-medium text-slate-700 mb-1">First Name *</label>
                                <input id="firstname" v-model="form.firstname" type="text" required
                                    class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500" placeholder="John" />
                                <div v-if="form.errors.firstname" class="text-sm text-red-600 mt-1">{{ form.errors.firstname }}</div>
                            </div>
                            <div>
                                <label for="lastname" class="block text-sm font-medium text-slate-700 mb-1">Last Name *</label>
                                <input id="lastname" v-model="form.lastname" type="text" required
                                    class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500" placeholder="Doe" />
                                <div v-if="form.errors.lastname" class="text-sm text-red-600 mt-1">{{ form.errors.lastname }}</div>
                            </div>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email Address *</label>
                            <input id="email" v-model="form.email" type="email" required
                                class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500" placeholder="you@example.com" />
                            <div v-if="form.errors.email" class="text-sm text-red-600 mt-1">{{ form.errors.email }}</div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="phone" class="block text-sm font-medium text-slate-700 mb-1">Phone Number *</label>
                                <input id="phone" v-model="form.phone" type="tel" required
                                    class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500" placeholder="+1234567890" />
                                <div v-if="form.errors.phone" class="text-sm text-red-600 mt-1">{{ form.errors.phone }}</div>
                            </div>
                            <div>
                                <label for="companyname" class="block text-sm font-medium text-slate-700 mb-1">Company</label>
                                <input id="companyname" v-model="form.companyname" type="text"
                                    class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500" placeholder="Optional" />
                                <div v-if="form.errors.companyname" class="text-sm text-red-600 mt-1">{{ form.errors.companyname }}</div>
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="border-t border-slate-100 pt-5">
                            <h3 class="text-sm font-semibold text-slate-900 mb-4">Address</h3>

                            <div class="space-y-4">
                                <div>
                                    <label for="address1" class="block text-sm font-medium text-slate-700 mb-1">Address Line 1 *</label>
                                    <input id="address1" v-model="form.address1" type="text" required
                                        class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500" placeholder="Street address" />
                                    <div v-if="form.errors.address1" class="text-sm text-red-600 mt-1">{{ form.errors.address1 }}</div>
                                </div>

                                <div>
                                    <label for="address2" class="block text-sm font-medium text-slate-700 mb-1">Address Line 2</label>
                                    <input id="address2" v-model="form.address2" type="text"
                                        class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500" placeholder="Apt, suite, etc. (optional)" />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="city" class="block text-sm font-medium text-slate-700 mb-1">City *</label>
                                        <input id="city" v-model="form.city" type="text" required
                                            class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500" placeholder="City" />
                                        <div v-if="form.errors.city" class="text-sm text-red-600 mt-1">{{ form.errors.city }}</div>
                                    </div>
                                    <div>
                                        <label for="state" class="block text-sm font-medium text-slate-700 mb-1">State / Region *</label>
                                        <input id="state" v-model="form.state" type="text" required
                                            class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500" placeholder="State" />
                                        <div v-if="form.errors.state" class="text-sm text-red-600 mt-1">{{ form.errors.state }}</div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="postcode" class="block text-sm font-medium text-slate-700 mb-1">Postal Code *</label>
                                        <input id="postcode" v-model="form.postcode" type="text" required
                                            class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500" placeholder="12345" />
                                        <div v-if="form.errors.postcode" class="text-sm text-red-600 mt-1">{{ form.errors.postcode }}</div>
                                    </div>
                                    <div>
                                        <label for="country" class="block text-sm font-medium text-slate-700 mb-1">Country *</label>
                                        <select id="country" v-model="form.country" required
                                            class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500">
                                            <option v-for="c in countries" :key="c.code" :value="c.code">{{ c.name }}</option>
                                        </select>
                                        <div v-if="form.errors.country" class="text-sm text-red-600 mt-1">{{ form.errors.country }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="border-t border-slate-100 pt-5">
                            <h3 class="text-sm font-semibold text-slate-900 mb-4">Security</h3>
                            <div class="space-y-4">
                                <div>
                                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password *</label>
                                    <input id="password" v-model="form.password" type="password" required
                                        class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500" placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;" />
                                    <div v-if="form.errors.password" class="text-sm text-red-600 mt-1">{{ form.errors.password }}</div>
                                </div>
                                <div>
                                    <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">Confirm Password *</label>
                                    <input id="password_confirmation" v-model="form.password_confirmation" type="password" required
                                        class="w-full rounded-xl border-slate-300 focus:ring-blue-500 focus:border-blue-500" placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;" />
                                    <div v-if="form.errors.password_confirmation" class="text-sm text-red-600 mt-1">{{ form.errors.password_confirmation }}</div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" :disabled="form.processing"
                            class="w-full py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all disabled:opacity-50 shadow-sm">
                            {{ form.processing ? 'Creating account...' : 'Create Account' }}
                        </button>
                    </form>

                    <p class="mt-5 text-center text-[13px] text-slate-500">
                        Already have an account?
                        <Link :href="route('login')" class="text-blue-600 hover:text-blue-700 font-medium">Sign in</Link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
