<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    DocumentArrowUpIcon,
    TrashIcon,
    ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    registration: Object,
    salesReps: Array,
});

const page = usePage();
const isAdmin = computed(() => ['admin', 'operation'].includes(page.props.auth?.user?.role));
const isSales = computed(() => page.props.auth?.user?.role === 'sales');
const isLider = computed(() => {
    const u = page.props.auth?.user;
    return u?.role === 'admin' || u?.sales_type === 'lider';
});
const r = computed(() => props.registration);
const designer = computed(() => r.value.designer);
const profile = computed(() => designer.value?.designer_profile);

function statusBadge(s) {
    return {
        registered: 'bg-blue-100 text-blue-700 border-blue-200',
        onboarded:  'bg-purple-100 text-purple-700 border-purple-200',
        confirmed:  'bg-green-100 text-green-700 border-green-200',
        cancelled:  'bg-red-100 text-red-700 border-red-200',
    }[s] ?? 'bg-gray-100 text-gray-600 border-gray-200';
}

function statusLabel(s) {
    return {
        registered: 'Registered',
        onboarded:  'Onboarded',
        confirmed:  'Confirmed',
        cancelled:  'Cancelled',
    }[s] ?? s;
}

// Edit registration (lider only)
const editForm = useForm({
    sales_rep_id: props.registration?.sales_rep_id ?? '',
    notes: props.registration?.notes ?? '',
});
const editing = ref(false);

function submitEdit() {
    editForm.patch(`/admin/sales/designers/${props.registration.id}`, {
        preserveScroll: true,
        onSuccess: () => { editing.value = false; },
    });
}

// Document upload
const showUploadModal = ref(false);
const docFile       = ref(null);
const docType       = ref('contract');
const docNotes      = ref('');
const docUploading  = ref(false);
const docErrors     = ref({});
const fileInput     = ref(null);

function handleFileChange(e) {
    docFile.value = e.target.files[0] ?? null;
}

function submitDocument() {
    if (!docFile.value) return;
    docErrors.value = {};
    docUploading.value = true;

    const formData = new FormData();
    formData.append('file',  docFile.value);
    formData.append('type',  docType.value);
    formData.append('notes', docNotes.value);

    router.post(`/admin/sales/designers/${r.value.id}/documents`, formData, {
        preserveScroll: true,
        onSuccess: () => {
            showUploadModal.value = false;
            docFile.value  = null;
            docType.value  = 'contract';
            docNotes.value = '';
            if (fileInput.value) fileInput.value.value = '';
        },
        onError: (errors) => { docErrors.value = errors; },
        onFinish: () => { docUploading.value = false; },
    });
}

function deleteDocument(doc) {
    if (!confirm(`¿Delete el documento "${doc.original_name}"?`)) return;
    router.delete(`/admin/sales/documents/${doc.id}`, { preserveScroll: true });
}

function docTypeLabel(type) {
    return { contract: 'Contract', payment_proof: 'Comprobante de Pago', other: 'Other' }[type] ?? type;
}

function storageUrl(path) {
    return `/storage/${path}`;
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link href="/admin/sales/designers" class="text-gray-400 hover:text-gray-600 text-sm">&larr; Registros</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">{{ designer?.first_name }} {{ designer?.last_name }}</h2>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Hero card -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">{{ designer?.first_name }} {{ designer?.last_name }}</h3>
                            <p v-if="profile?.brand_name" class="text-gray-500 text-sm">{{ profile.brand_name }}</p>
                        </div>
                        <span :class="statusBadge(r.status)" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border">
                            {{ statusLabel(r.status) }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Email</p>
                            <p class="text-gray-900">{{ designer?.email }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Teléfono</p>
                            <p class="text-gray-900">{{ designer?.phone || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">País</p>
                            <p class="text-gray-900">{{ profile?.country || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Status del Diseñador</p>
                            <p class="text-gray-900 capitalize">{{ designer?.status }}</p>
                        </div>
                    </div>
                </div>

                <!-- Event & Package -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h4 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4">Event y Package</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Event</p>
                            <p class="text-gray-900 font-medium">{{ r.event?.name }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Package</p>
                            <p class="text-gray-900">{{ r.package?.name ?? 'Sin paquete' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Agreed Price</p>
                            <p class="text-gray-900 font-bold text-lg">${{ Number(r.agreed_price).toLocaleString() }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Downpayment / Downpayment</p>
                            <p class="text-gray-900 font-bold text-lg">{{ r.downpayment ? `$${Number(r.downpayment).toLocaleString()}` : '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Installments</p>
                            <p class="text-gray-900 font-medium">{{ r.installments_count ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Vendedor</p>
                            <template v-if="isLider && editing">
                                <select v-model="editForm.sales_rep_id"
                                    class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400">
                                    <option value="">— Sin asignar —</option>
                                    <option v-for="rep in salesReps" :key="rep.id" :value="rep.id">{{ rep.first_name }} {{ rep.last_name }}</option>
                                </select>
                            </template>
                            <p v-else class="text-gray-900">{{ r.sales_rep?.first_name }} {{ r.sales_rep?.last_name ?? '—' }}</p>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mt-4">
                        <template v-if="isLider && editing">
                            <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Notes</p>
                            <textarea v-model="editForm.notes" rows="3"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400 resize-none"></textarea>
                        </template>
                        <div v-else-if="r.notes" class="p-3 bg-gray-50 rounded-lg text-sm text-gray-700">
                            <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Notes</p>
                            {{ r.notes }}
                        </div>
                    </div>

                    <!-- Acciones edición (lider) -->
                    <div v-if="isLider" class="mt-4 flex items-center gap-2">
                        <template v-if="editing">
                            <button @click="submitEdit" :disabled="editForm.processing"
                                class="px-4 py-1.5 bg-black text-white text-xs font-medium rounded-lg hover:bg-gray-800 transition-colors disabled:opacity-50">
                                {{ editForm.processing ? 'Guardando...' : 'Save' }}
                            </button>
                            <button @click="editing = false; editForm.reset()"
                                class="px-4 py-1.5 border border-gray-300 text-gray-600 text-xs rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                        </template>
                        <button v-else @click="editing = true"
                            class="px-4 py-1.5 border border-gray-300 text-gray-600 text-xs rounded-lg hover:bg-gray-50 transition-colors">
                            Editar vendedor / notas
                        </button>
                    </div>
                </div>

                <!-- Documents -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-semibold uppercase tracking-widest text-gray-500">Documents</h4>
                        <button @click="showUploadModal = true" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-black text-white text-xs font-medium rounded-lg hover:bg-gray-800 transition-colors">
                            <DocumentArrowUpIcon class="h-4 w-4" />
                            Upload Document
                        </button>
                    </div>

                    <div v-if="!r.documents?.length" class="text-center py-8 text-gray-400 text-sm">
                        No documents adjuntos
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="doc in r.documents" :key="doc.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ doc.original_name }}</p>
                                <div class="flex items-center gap-2 text-xs text-gray-400 mt-0.5">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-200 text-gray-600 font-medium">{{ docTypeLabel(doc.type) }}</span>
                                    <span>{{ doc.uploader?.first_name }} {{ doc.uploader?.last_name }}</span>
                                    <span>{{ new Date(doc.created_at).toLocaleDateString('es-US') }}</span>
                                </div>
                                <p v-if="doc.notes" class="text-xs text-gray-500 mt-1">{{ doc.notes }}</p>
                            </div>
                            <div class="flex items-center gap-2 ml-3">
                                <a :href="storageUrl(doc.file_path)" target="_blank" class="p-1.5 text-gray-400 hover:text-blue-600 transition-colors" title="Download">
                                    <ArrowDownTrayIcon class="h-4 w-4" />
                                </a>
                                <button @click="deleteDocument(doc)" class="p-1.5 text-gray-400 hover:text-red-600 transition-colors" title="Delete">
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Timeline -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h4 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4">Línea de Tiempo</h4>
                    <div class="space-y-4 text-sm">
                        <div class="flex items-start gap-3">
                            <div class="w-2 h-2 rounded-full bg-blue-400 mt-1.5 flex-shrink-0"></div>
                            <div>
                                <p class="text-gray-900 font-medium">Registrado</p>
                                <p class="text-gray-400 text-xs">{{ new Date(r.created_at).toLocaleString('es-US') }}</p>
                                <p class="text-gray-500 text-xs">por {{ r.sales_rep?.first_name }} {{ r.sales_rep?.last_name }}</p>
                            </div>
                        </div>
                        <div v-if="r.onboarded_at" class="flex items-start gap-3">
                            <div class="w-2 h-2 rounded-full bg-purple-400 mt-1.5 flex-shrink-0"></div>
                            <div>
                                <p class="text-gray-900 font-medium">Onboarded</p>
                                <p class="text-gray-400 text-xs">{{ new Date(r.onboarded_at).toLocaleString('es-US') }}</p>
                                <p class="text-gray-500 text-xs">Onboarding enviado por operaciones</p>
                            </div>
                        </div>
                        <div v-if="r.confirmed_at" class="flex items-start gap-3">
                            <div class="w-2 h-2 rounded-full bg-green-400 mt-1.5 flex-shrink-0"></div>
                            <div>
                                <p class="text-gray-900 font-medium">Confirmado</p>
                                <p class="text-gray-400 text-xs">{{ new Date(r.confirmed_at).toLocaleString('es-US') }}</p>
                                <p class="text-gray-500 text-xs">Primer inicio de sesión en la app</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload document modal -->
        <Teleport to="body">
            <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="showUploadModal = false">
                <div class="fixed inset-0 bg-black/50"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 z-10">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Upload Document</h3>
                    <form @submit.prevent="submitDocument" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type de documento *</label>
                            <select v-model="docType" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-400">
                                <option value="contract">Contract</option>
                                <option value="payment_proof">Comprobante de Pago</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Archivo *</label>
                            <input ref="fileInput" type="file" @change="handleFileChange" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            <p v-if="docErrors.file" class="text-red-500 text-xs mt-1">{{ docErrors.file }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea v-model="docNotes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-400"></textarea>
                        </div>
                        <div class="flex items-center gap-3 pt-2">
                            <button type="submit" :disabled="docUploading || !docFile" class="px-5 py-2 bg-black text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors disabled:opacity-50">
                                {{ docUploading ? 'Subiendo...' : 'Subir' }}
                            </button>
                            <button type="button" @click="showUploadModal = false" class="px-5 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
