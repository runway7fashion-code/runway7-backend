<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, reactive, computed } from 'vue';

const props = defineProps({
    methods: Array,
});

const showModal = ref(false);
const editing = ref(null);
const failedImgs = reactive({});

const form = reactive({
    name: '',
    label: '',
    type: 'app',
    config: {},
    logo: null,
    qr_image: null,
    is_active: true,
});

const logoPreview = ref(null);
const qrPreview = ref(null);

const configFields = computed(() => {
    if (form.type === 'bank') {
        return [
            { key: 'bank', label: 'Bank Name', placeholder: 'JPMorgan Chase Bank' },
            { key: 'account', label: 'Account #', placeholder: '2907086168' },
            { key: 'routing', label: 'Routing #', placeholder: '021000021' },
            { key: 'swift', label: 'SWIFT', placeholder: 'CHASUS33' },
            { key: 'address', label: 'Bank Address', placeholder: '270 Park Avenue, New York' },
        ];
    }
    if (form.type === 'app') {
        return [
            { key: 'username', label: 'Username / Handle', placeholder: '@runway7fashion' },
            { key: 'link', label: 'Payment Link', placeholder: 'https://venmo.com/u/runway7fashion' },
            { key: 'email', label: 'Email (if applicable)', placeholder: 'payments@company.com' },
            { key: 'phone', label: 'Phone / ID (if applicable)', placeholder: '848.330.6796' },
        ];
    }
    return [
        { key: 'instructions', label: 'Payment Instructions', placeholder: 'Describe how to pay...' },
        { key: 'link', label: 'Link (optional)', placeholder: 'https://...' },
    ];
});

function openCreate() {
    editing.value = null;
    form.name = '';
    form.label = '';
    form.type = 'app';
    form.config = {};
    form.logo = null;
    form.qr_image = null;
    form.is_active = true;
    logoPreview.value = null;
    qrPreview.value = null;
    showModal.value = true;
}

function openEdit(method) {
    editing.value = method;
    form.name = method.name;
    form.label = method.label;
    form.type = method.type;
    form.config = { ...method.config };
    form.logo = null;
    form.qr_image = null;
    form.is_active = method.is_active;
    logoPreview.value = null;
    qrPreview.value = null;
    showModal.value = true;
}

function onLogoChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    form.logo = file;
    logoPreview.value = URL.createObjectURL(file);
}

function onQrChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    form.qr_image = file;
    qrPreview.value = URL.createObjectURL(file);
}

function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}

function submit() {
    const data = new FormData();
    if (!editing.value) data.append('name', form.name);
    data.append('label', form.label);
    data.append('type', form.type);
    data.append('is_active', form.is_active ? '1' : '0');

    // Send config fields as JSON
    const configObj = {};
    for (const field of configFields.value) {
        if (form.config[field.key]) {
            configObj[field.key] = form.config[field.key];
        }
    }
    data.append('config', JSON.stringify(configObj));

    if (form.logo) data.append('logo', form.logo);
    if (form.qr_image) data.append('qr_image', form.qr_image);

    if (editing.value) {
        data.append('_method', 'PUT');
        router.post(`/admin/accounting/payment-methods/${editing.value.id}`, data, {
            forceFormData: true,
            onSuccess: () => { showModal.value = false; },
        });
    } else {
        router.post('/admin/accounting/payment-methods', data, {
            forceFormData: true,
            onSuccess: () => { showModal.value = false; },
        });
    }
}

function deleteMethod(method) {
    if (!confirm(`Delete payment method "${method.label}"?`)) return;
    router.delete(`/admin/accounting/payment-methods/${method.id}`);
}

function toggleActive(method) {
    const data = new FormData();
    data.append('_method', 'PUT');
    data.append('label', method.label);
    data.append('type', method.type);
    data.append('config', JSON.stringify(method.config));
    data.append('is_active', method.is_active ? '0' : '1');
    router.post(`/admin/accounting/payment-methods/${method.id}`, data, {
        forceFormData: true,
        preserveScroll: true,
    });
}

function typeLabel(t) {
    return { bank: 'Bank Transfer', app: 'Payment App', other: 'Other' }[t] ?? t;
}

function typeBadgeClass(t) {
    return {
        bank: 'bg-blue-100 text-blue-700',
        app: 'bg-purple-100 text-purple-700',
        other: 'bg-gray-100 text-gray-700',
    }[t] ?? 'bg-gray-100 text-gray-700';
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Payment Methods</h2>
        </template>

        <div>
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Payment Methods</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ methods.length }} methods configured - Designers see these payment options in the app</p>
                </div>
                <button @click="openCreate" class="px-4 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors">
                    + Add Method
                </button>
            </div>

            <!-- Empty state -->
            <div v-if="methods.length === 0" class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
                <p class="text-gray-400">No payment methods configured yet.</p>
            </div>

            <!-- Grid -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div v-for="method in methods" :key="method.id"
                    class="bg-white rounded-2xl border border-gray-200 overflow-hidden transition-shadow hover:shadow-lg"
                    :class="!method.is_active ? 'opacity-60' : ''">

                    <!-- Logo header -->
                    <div class="h-32 bg-gray-50 flex items-center justify-center relative">
                        <img v-if="storageUrl(method.logo_url) && !failedImgs[method.id]"
                            :src="storageUrl(method.logo_url)"
                            @error="failedImgs[method.id] = true"
                            class="max-h-20 max-w-[80%] object-contain" />
                        <span v-else class="text-2xl font-bold text-gray-300">{{ method.label }}</span>

                        <!-- Status toggle -->
                        <button @click="toggleActive(method)"
                            class="absolute top-2 left-2 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full cursor-pointer"
                            :class="method.is_active ? 'bg-green-500 text-white' : 'bg-gray-400 text-white'">
                            {{ method.is_active ? 'Active' : 'Inactive' }}
                        </button>

                        <!-- Type badge -->
                        <span class="absolute top-2 right-2 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full"
                            :class="typeBadgeClass(method.type)">
                            {{ typeLabel(method.type) }}
                        </span>
                    </div>

                    <!-- Info -->
                    <div class="p-4">
                        <h4 class="font-bold text-gray-900 mb-2">{{ method.label }}</h4>

                        <!-- Config details -->
                        <div class="space-y-1 mb-3">
                            <div v-for="(value, key) in method.config" :key="key"
                                class="flex items-center gap-2 text-xs">
                                <span class="text-gray-400 uppercase font-medium w-20 shrink-0">{{ key }}:</span>
                                <span class="text-gray-700 truncate">{{ value }}</span>
                            </div>
                        </div>

                        <!-- QR if exists -->
                        <div v-if="method.qr_image_url" class="mb-3">
                            <img :src="storageUrl(method.qr_image_url)" class="w-20 h-20 rounded-lg border border-gray-200" />
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
                            <div class="flex-1"></div>
                            <button @click="openEdit(method)"
                                class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                Edit
                            </button>
                            <button @click="deleteMethod(method)"
                                class="text-xs px-3 py-1.5 border border-red-200 text-red-500 rounded-lg hover:bg-red-50 transition-colors">
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showModal = false">
                <div class="bg-white rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6 mx-4">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-bold text-gray-900">{{ editing ? 'Edit' : 'Create' }} Payment Method</h3>
                        <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <!-- Name (only on create) -->
                        <div v-if="!editing">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Internal Name *</label>
                            <input v-model="form.name" type="text" placeholder="e.g. wire_transfer, venmo, cashapp"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p class="text-xs text-gray-400 mt-1">Lowercase, no spaces. Used as identifier.</p>
                        </div>

                        <!-- Label -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Display Name *</label>
                            <input v-model="form.label" type="text" placeholder="e.g. Wire Transfer, Venmo, CashApp"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>

                        <!-- Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Type *</label>
                            <div class="flex gap-3">
                                <label v-for="opt in [
                                    { value: 'bank', label: 'Bank Transfer' },
                                    { value: 'app', label: 'Payment App' },
                                    { value: 'other', label: 'Other' },
                                ]" :key="opt.value"
                                    class="flex-1 flex items-center justify-center p-3 border rounded-xl cursor-pointer transition-all text-sm font-medium"
                                    :class="form.type === opt.value ? 'border-black bg-gray-50 ring-1 ring-black' : 'border-gray-200 hover:border-gray-300'">
                                    <input type="radio" v-model="form.type" :value="opt.value" class="sr-only" />
                                    {{ opt.label }}
                                </label>
                            </div>
                        </div>

                        <!-- Dynamic config fields -->
                        <div class="bg-gray-50 rounded-xl p-4 space-y-3">
                            <h4 class="text-sm font-bold text-gray-700">Payment Details</h4>
                            <div v-for="field in configFields" :key="field.key">
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ field.label }}</label>
                                <input v-model="form.config[field.key]" type="text" :placeholder="field.placeholder"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                        </div>

                        <!-- Logo -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Logo Image</label>
                            <div v-if="logoPreview || (editing && storageUrl(editing.logo_url))" class="mb-2">
                                <img :src="logoPreview || storageUrl(editing.logo_url)" class="h-16 object-contain rounded-lg border border-gray-200" />
                            </div>
                            <input type="file" accept="image/*" @change="onLogoChange"
                                class="text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-black file:text-white file:text-xs file:font-medium hover:file:bg-gray-800 file:cursor-pointer" />
                        </div>

                        <!-- QR Image -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">QR Code Image (optional)</label>
                            <div v-if="qrPreview || (editing && storageUrl(editing.qr_image_url))" class="mb-2">
                                <img :src="qrPreview || storageUrl(editing.qr_image_url)" class="h-24 object-contain rounded-lg border border-gray-200" />
                            </div>
                            <input type="file" accept="image/*" @change="onQrChange"
                                class="text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-black file:text-white file:text-xs file:font-medium hover:file:bg-gray-800 file:cursor-pointer" />
                        </div>

                        <!-- Active -->
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.is_active"
                                class="rounded border-gray-300 text-black focus:ring-black/20" />
                            <span class="text-sm text-gray-700">Active (visible in app)</span>
                        </label>

                        <!-- Buttons -->
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-6 py-2 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 transition-colors">
                                {{ editing ? 'Save Changes' : 'Create Method' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
