<script setup>
import { useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';

const props = defineProps({ client: Object });

const form = useForm({
    firstname: props.client.firstname || '',
    lastname: props.client.lastname || '',
    email: props.client.email || '',
    companyname: props.client.companyname || '',
    address1: props.client.address1 || '',
    address2: props.client.address2 || '',
    city: props.client.city || '',
    state: props.client.state || '',
    postcode: props.client.postcode || '',
    country: props.client.country || '',
    phonenumber: props.client.phonenumber || '',
});

function submit() {
    form.put(route('client.account.profile.update'));
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Profile</h1>
        </template>

        <div class="max-w-2xl">
            <Card>
                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5">First Name</label>
                            <input v-model="form.firstname" type="text" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                            <p v-if="form.errors.firstname" class="mt-1 text-[12px] text-red-600">{{ form.errors.firstname }}</p>
                        </div>
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Last Name</label>
                            <input v-model="form.lastname" type="text" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                            <p v-if="form.errors.lastname" class="mt-1 text-[12px] text-red-600">{{ form.errors.lastname }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Email</label>
                        <input v-model="form.email" type="email" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                        <p v-if="form.errors.email" class="mt-1 text-[12px] text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Company</label>
                        <input v-model="form.companyname" type="text" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Address Line 1</label>
                        <input v-model="form.address1" type="text" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Address Line 2</label>
                        <input v-model="form.address2" type="text" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5">City</label>
                            <input v-model="form.city" type="text" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5">State</label>
                            <input v-model="form.state" type="text" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Postcode</label>
                            <input v-model="form.postcode" type="text" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Country</label>
                            <input v-model="form.country" type="text" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="US" />
                        </div>
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Phone</label>
                            <input v-model="form.phonenumber" type="text" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" :disabled="form.processing"
                            class="inline-flex items-center gap-1.5 px-5 py-2.5 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm">
                            Save Changes
                        </button>
                    </div>
                </form>
            </Card>
        </div>
    </ClientLayout>
</template>
