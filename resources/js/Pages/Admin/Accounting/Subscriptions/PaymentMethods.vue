<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { TrashIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    methods: Array,
});

const showModal = ref(false);
const editing = ref(null);

const form = useForm({
    nickname: '',
    card_type: 'visa',
    last_four: '',
    holder_name: '',
    notes: '',
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.card_type = 'visa';
    showModal.value = true;
}

function openEdit(method) {
    editing.value = method;
    form.nickname = method.nickname;
    form.card_type = method.card_type;
    form.last_four = method.last_four;
    form.holder_name = method.holder_name ?? '';
    form.notes = method.notes ?? '';
    showModal.value = true;
}

function submit() {
    if (editing.value) {
        form.put(`/admin/accounting/subscriptions/payment-methods/${editing.value.id}`, {
            onSuccess: () => { showModal.value = false; },
        });
    } else {
        form.post('/admin/accounting/subscriptions/payment-methods', {
            onSuccess: () => { showModal.value = false; },
        });
    }
}

function deleteMethod(method) {
    if (method.subscriptions_count > 0) {
        if (!confirm(`This card is linked to ${method.subscriptions_count} subscription(s). They will be unlinked. Continue?`)) return;
    } else {
        if (!confirm(`Delete card "${method.nickname}"?`)) return;
    }
    router.delete(`/admin/accounting/subscriptions/payment-methods/${method.id}`, { preserveScroll: true });
}

function cardTypeBadge(type) {
    return type === 'visa' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700';
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Subscription Cards</h2>
        </template>

        <div>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Cards</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ methods.length }} card(s) on file. Only last 4 digits and card type are stored.</p>
                </div>
                <button @click="openCreate" class="px-4 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors">
                    + Add Card
                </button>
            </div>

            <div v-if="methods.length === 0" class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
                <p class="text-gray-400">No cards added yet.</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div v-for="method in methods" :key="method.id"
                    class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-br from-gray-900 to-gray-700 p-5 text-white">
                        <div class="flex items-center justify-between mb-6">
                            <span class="text-xs uppercase tracking-widest opacity-70">{{ method.card_type }}</span>
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-white/20">
                                {{ method.subscriptions_count }} sub(s)
                            </span>
                        </div>
                        <p class="text-2xl font-mono tracking-wider">**** **** **** {{ method.last_four }}</p>
                        <p class="text-sm mt-3 opacity-80">{{ method.holder_name ?? '—' }}</p>
                    </div>
                    <div class="p-4">
                        <h4 class="font-bold text-gray-900">{{ method.nickname }}</h4>
                        <p v-if="method.notes" class="text-xs text-gray-500 mt-1">{{ method.notes }}</p>
                        <div class="flex items-center gap-2 pt-3 mt-3 border-t border-gray-100">
                            <div class="flex-1"></div>
                            <button @click="openEdit(method)"
                                class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 inline-flex items-center gap-1">
                                <PencilSquareIcon class="w-3.5 h-3.5" /> Edit
                            </button>
                            <button @click="deleteMethod(method)"
                                class="text-xs px-3 py-1.5 border border-red-200 text-red-500 rounded-lg hover:bg-red-50 inline-flex items-center gap-1">
                                <TrashIcon class="w-3.5 h-3.5" /> Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showModal = false">
                <div class="bg-white rounded-2xl w-full max-w-md p-6 mx-4">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-bold text-gray-900">{{ editing ? 'Edit Card' : 'Add Card' }}</h3>
                        <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nickname *</label>
                            <input v-model="form.nickname" type="text" placeholder="e.g. Joseph BAC, Company Visa"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm" />
                            <p v-if="form.errors.nickname" class="text-xs text-red-500 mt-1">{{ form.errors.nickname }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Card Type *</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label
                                    class="flex items-center justify-center p-3 border rounded-xl cursor-pointer text-sm font-medium"
                                    :class="form.card_type === 'visa' ? 'border-black bg-gray-50 ring-1 ring-black' : 'border-gray-200'">
                                    <input type="radio" v-model="form.card_type" value="visa" class="sr-only" />
                                    Visa
                                </label>
                                <label
                                    class="flex items-center justify-center p-3 border rounded-xl cursor-pointer text-sm font-medium"
                                    :class="form.card_type === 'mastercard' ? 'border-black bg-gray-50 ring-1 ring-black' : 'border-gray-200'">
                                    <input type="radio" v-model="form.card_type" value="mastercard" class="sr-only" />
                                    Mastercard
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last 4 Digits *</label>
                            <input v-model="form.last_four" type="text" maxlength="4" pattern="\d{4}" placeholder="1234"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm font-mono" />
                            <p v-if="form.errors.last_four" class="text-xs text-red-500 mt-1">{{ form.errors.last_four }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cardholder Name</label>
                            <input v-model="form.holder_name" type="text" placeholder="Optional"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea v-model="form.notes" rows="2"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm"></textarea>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Cancel</button>
                            <button type="submit" :disabled="form.processing"
                                class="px-6 py-2 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-50">
                                {{ editing ? 'Save Changes' : 'Add Card' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
