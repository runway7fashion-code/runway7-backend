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
    canUndo: Boolean,
    undoBlockReason: Array,
});

const page = usePage();
const isAdmin = computed(() => ['admin', 'operation'].includes(page.props.auth?.user?.role));
const isSales = computed(() => page.props.auth?.user?.role === 'sales');
const isLider = computed(() => {
    const u = page.props.auth?.user;
    return u?.role === 'admin'
        || (u?.role === 'sales' && u?.sales_type === 'lider')
        || !!u?.extra_areas?.includes('sales');
});
const r = computed(() => props.registration);
const showUndoModal = ref(false);
const undoProcessing = ref(false);

function confirmUndo() {
    undoProcessing.value = true;
    router.delete(`/admin/sales/designers/${r.value.id}/undo`, {
        onFinish: () => { undoProcessing.value = false; showUndoModal.value = false; },
    });
}
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
                <Link href="/admin/sales/designers" class="text-gray-400 hover:text-gray-600 text-sm">&larr; Registrations</Link>
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
                            <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Phone</p>
                            <p class="text-gray-900">{{ designer?.phone || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Country</p>
                            <p class="text-gray-900">{{ profile?.country || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Designer Status</p>
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
                            <p class="text-gray-900">{{ r.package?.name ?? 'No package' }}</p>
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
                <!-- Undo Conversion -->
                <button v-if="isLider && r.status === 'registered'"
                    @click="showUndoModal = true"
                    class="w-full px-4 py-2.5 bg-red-600 text-white text-sm font-medium rounded-xl hover:bg-red-700 transition-colors">
                    Undo Conversion
                </button>

                <!-- Timeline -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h4 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4">Timeline</h4>
                    <div class="space-y-4 text-sm">
                        <div class="flex items-start gap-3">
                            <div class="w-2 h-2 rounded-full bg-blue-400 mt-1.5 flex-shrink-0"></div>
                            <div>
                                <p class="text-gray-900 font-medium">Registered</p>
                                <p class="text-gray-400 text-xs">{{ new Date(r.created_at).toLocaleString('en-US') }}</p>
                                <p class="text-gray-500 text-xs">by {{ r.sales_rep?.first_name }} {{ r.sales_rep?.last_name }}</p>
                            </div>
                        </div>
                        <div v-if="r.onboarded_at" class="flex items-start gap-3">
                            <div class="w-2 h-2 rounded-full bg-purple-400 mt-1.5 flex-shrink-0"></div>
                            <div>
                                <p class="text-gray-900 font-medium">Onboarded</p>
                                <p class="text-gray-400 text-xs">{{ new Date(r.onboarded_at).toLocaleString('en-US') }}</p>
                                <p class="text-gray-500 text-xs">Onboarding sent by Operations</p>
                            </div>
                        </div>
                        <div v-if="r.confirmed_at" class="flex items-start gap-3">
                            <div class="w-2 h-2 rounded-full bg-green-400 mt-1.5 flex-shrink-0"></div>
                            <div>
                                <p class="text-gray-900 font-medium">Confirmed</p>
                                <p class="text-gray-400 text-xs">{{ new Date(r.confirmed_at).toLocaleString('en-US') }}</p>
                                <p class="text-gray-500 text-xs">First login in the app</p>
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
        <!-- Undo Conversion Modal -->
        <Teleport to="body">
            <div v-if="showUndoModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showUndoModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
                    <!-- Can undo -->
                    <template v-if="canUndo">
                        <div class="px-6 py-5 border-b border-gray-100">
                            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <TrashIcon class="w-6 h-6 text-red-500" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 text-center">Undo Conversion</h3>
                            <p class="text-sm text-gray-500 text-center mt-2">This will permanently delete the designer account and registration. The lead will be reverted to its previous state.</p>
                        </div>
                        <div class="px-6 py-4 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Designer</span>
                                <span class="font-medium text-gray-900">{{ designer?.first_name }} {{ designer?.last_name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Email</span>
                                <span class="font-medium text-gray-900">{{ designer?.email }}</span>
                            </div>
                            <div v-if="r.event" class="flex justify-between">
                                <span class="text-gray-500">Event</span>
                                <span class="font-medium text-gray-900">{{ r.event?.name }}</span>
                            </div>
                            <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700">
                                This action cannot be undone. The designer user will be permanently deleted.
                            </div>
                        </div>
                        <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
                            <button @click="showUndoModal = false" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button @click="confirmUndo" :disabled="undoProcessing" class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors disabled:opacity-50">
                                {{ undoProcessing ? 'Undoing...' : 'Yes, Undo Conversion' }}
                            </button>
                        </div>
                    </template>
                    <!-- Cannot undo -->
                    <template v-else>
                        <div class="px-6 py-5 border-b border-gray-100">
                            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 text-center">Cannot Undo Conversion</h3>
                            <p class="text-sm text-gray-500 text-center mt-2">This conversion cannot be reversed because other departments have already processed this designer.</p>
                        </div>
                        <div class="px-6 py-4">
                            <ul class="space-y-2">
                                <li v-for="reason in undoBlockReason" :key="reason" class="flex items-start gap-2 text-sm text-gray-700">
                                    <span class="text-red-400 mt-0.5">&#x2022;</span>
                                    {{ reason }}
                                </li>
                            </ul>
                        </div>
                        <div class="px-6 py-4 border-t border-gray-100">
                            <button @click="showUndoModal = false" class="w-full px-4 py-2.5 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors">
                                Understood
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
