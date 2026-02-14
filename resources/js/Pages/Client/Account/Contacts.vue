<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Card from '@/Components/Card.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

defineProps({ contacts: Array, total: Number });

const showAdd = ref(false);
const deleting = ref(null);

const form = useForm({
    firstname: '',
    lastname: '',
    email: '',
    phonenumber: '',
    companyname: '',
    address1: '',
    city: '',
    state: '',
    postcode: '',
    country: '',
});

function addContact() {
    form.post(route('client.account.contacts.store'), {
        onSuccess: () => {
            form.reset();
            showAdd.value = false;
        },
    });
}

function deleteContact() {
    router.delete(route('client.account.contacts.destroy', deleting.value), {
        onSuccess: () => deleting.value = null,
    });
}
</script>

<template>
    <ClientLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">Contacts</h1>
        </template>
        <template #actions>
            <button @click="showAdd = !showAdd"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Add Contact
            </button>
        </template>

        <!-- Add Contact Form -->
        <div v-if="showAdd" class="mb-6">
            <Card title="New Contact">
                <form @submit.prevent="addContact" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1">First Name</label>
                            <input v-model="form.firstname" type="text" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                            <p v-if="form.errors.firstname" class="mt-1 text-[12px] text-red-600">{{ form.errors.firstname }}</p>
                        </div>
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1">Last Name</label>
                            <input v-model="form.lastname" type="text" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1">Email</label>
                            <input v-model="form.email" type="email" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                            <p v-if="form.errors.email" class="mt-1 text-[12px] text-red-600">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1">Phone</label>
                            <input v-model="form.phonenumber" type="text" class="w-full text-[13px] rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="showAdd = false" class="px-3 py-2 text-[13px] text-gray-600 hover:text-gray-800">Cancel</button>
                        <button type="submit" :disabled="form.processing"
                            class="px-4 py-2 text-[13px] font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                            Add Contact
                        </button>
                    </div>
                </form>
            </Card>
        </div>

        <!-- Contacts List -->
        <div v-if="contacts.length === 0 && !showAdd" class="text-center py-12">
            <p class="text-[13px] text-gray-500">No contacts added yet.</p>
        </div>
        <div v-else class="space-y-3">
            <div v-for="c in contacts" :key="c.id" class="bg-white rounded-xl border border-gray-200 p-5 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
                        <span class="text-[13px] font-bold text-indigo-600">{{ (c.firstname || '?')[0].toUpperCase() }}</span>
                    </div>
                    <div>
                        <p class="text-[14px] font-semibold text-gray-900">{{ c.firstname }} {{ c.lastname }}</p>
                        <p class="text-[12px] text-gray-500">{{ c.email }} · {{ c.phonenumber }}</p>
                    </div>
                </div>
                <button @click="deleting = c.id"
                    class="p-2 text-gray-400 hover:text-red-500 transition-colors rounded-lg hover:bg-red-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </button>
            </div>
        </div>

        <ConfirmModal :show="!!deleting" title="Delete Contact" message="Are you sure you want to delete this contact?" confirmText="Delete" variant="danger" @confirm="deleteContact" @cancel="deleting = null" />
    </ClientLayout>
</template>
