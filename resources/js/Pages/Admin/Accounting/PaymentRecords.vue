<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { PaperClipIcon, PencilSquareIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    records: Object,
    events: Array,
    totals: Object,
    filters: Object,
});

// --- Filtros ---
const filters = ref({
    event_id: props.filters?.event_id ?? '',
    payment_type: props.filters?.payment_type ?? '',
    payment_method: props.filters?.payment_method ?? '',
    search: props.filters?.search ?? '',
    date_from: props.filters?.date_from ?? '',
    date_to: props.filters?.date_to ?? '',
});

let debounceTimer = null;
function applyFilters() {
    const params = {};
    Object.entries(filters.value).forEach(([k, v]) => { if (v) params[k] = v; });
    router.get('/admin/accounting/payment-records', params, { preserveState: true, replace: true });
}

watch(() => filters.value.search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 400);
});

function onFilterChange() {
    applyFilters();
}

function clearFilters() {
    filters.value = { event_id: '', payment_type: '', payment_method: '', search: '', date_from: '', date_to: '' };
    applyFilters();
}

const hasActiveFilters = computed(() => Object.values(filters.value).some(v => v));

// --- Crear Pago ---
const showCreateModal = ref(false);
const createForm = useForm({
    event_id: '',
    designer_id: '',
    amount: '',
    payment_type: 'downpayment',
    payment_method: '',
    reference: '',
    receipt: null,
    notes: '',
    payment_date: new Date().toISOString().slice(0, 16),
});

const designerSearch = ref('');
const designerResults = ref([]);
const selectedDesigner = ref(null);
const searchingDesigners = ref(false);
let designerDebounce = null;

watch(() => createForm.event_id, () => {
    designerSearch.value = '';
    designerResults.value = [];
    selectedDesigner.value = null;
    createForm.designer_id = '';
});

watch(designerSearch, (val) => {
    clearTimeout(designerDebounce);
    if (!createForm.event_id || val.length < 1) {
        designerResults.value = [];
        return;
    }
    designerDebounce = setTimeout(async () => {
        searchingDesigners.value = true;
        try {
            const res = await fetch(`/admin/accounting/api/search-designers?event_id=${createForm.event_id}&query=${encodeURIComponent(val)}`);
            designerResults.value = await res.json();
        } finally {
            searchingDesigners.value = false;
        }
    }, 300);
});

function selectDesigner(d) {
    selectedDesigner.value = d;
    createForm.designer_id = d.id;
    designerSearch.value = '';
    designerResults.value = [];
}

function clearSelectedDesigner() {
    selectedDesigner.value = null;
    createForm.designer_id = '';
}

function openCreateModal() {
    createForm.reset();
    createForm.payment_date = new Date().toISOString().slice(0, 16);
    selectedDesigner.value = null;
    designerSearch.value = '';
    designerResults.value = [];
    showCreateModal.value = true;
}

function submitCreate() {
    createForm.post('/admin/accounting/payment-records', {
        onSuccess: () => { showCreateModal.value = false; },
        forceFormData: true,
    });
}

// --- Editar Pago ---
const showEditModal = ref(false);
const editingRecord = ref(null);
const editForm = useForm({
    amount: '',
    payment_type: '',
    payment_method: '',
    reference: '',
    receipt: null,
    notes: '',
    payment_date: '',
});

function openEditModal(record) {
    editingRecord.value = record;
    editForm.amount = record.amount;
    editForm.payment_type = record.payment_type;
    editForm.payment_method = record.payment_method;
    editForm.reference = record.reference ?? '';
    editForm.receipt = null;
    editForm.notes = record.notes ?? '';
    editForm.payment_date = record.payment_date;
    showEditModal.value = true;
}

function submitEdit() {
    editForm.post(`/admin/accounting/payment-records/${editingRecord.value.id}`, {
        onSuccess: () => { showEditModal.value = false; },
        forceFormData: true,
        headers: { 'X-HTTP-Method-Override': 'PUT' },
    });
}

// --- Eliminar Pago ---
const showDeleteModal = ref(false);
const deletingRecord = ref(null);

function openDeleteModal(record) {
    deletingRecord.value = record;
    showDeleteModal.value = true;
}

function submitDelete() {
    router.delete(`/admin/accounting/payment-records/${deletingRecord.value.id}`, {
        onSuccess: () => { showDeleteModal.value = false; },
    });
}

// --- Helpers ---
function fmt(n) {
    return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function fmtFull(n) {
    return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const paymentMethods = [
    { value: 'wire_transfer', label: 'Transferencia' },
    { value: 'venmo', label: 'Venmo' },
    { value: 'zelle', label: 'Zelle' },
    { value: 'stripe', label: 'Stripe' },
    { value: 'cash', label: 'Efectivo' },
    { value: 'check', label: 'Cheque' },
    { value: 'other', label: 'Otro' },
];

function methodBadge(method) {
    return {
        wire_transfer: 'bg-blue-50 text-blue-700',
        venmo: 'bg-cyan-50 text-cyan-700',
        zelle: 'bg-purple-50 text-purple-700',
        stripe: 'bg-indigo-50 text-indigo-700',
        cash: 'bg-green-50 text-green-700',
        check: 'bg-orange-50 text-orange-700',
        other: 'bg-gray-100 text-gray-600',
    }[method] ?? 'bg-gray-100 text-gray-600';
}

function typeBadge(type) {
    return type === 'downpayment' ? 'bg-yellow-50 text-yellow-700' : 'bg-blue-50 text-blue-700';
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Registro de Pagos</h2>
        </template>

        <div class="space-y-4">
            <!-- Header: título + botón -->
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Registro de Pagos</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ totals.count }} pagos registrados</p>
                </div>
                <button @click="openCreateModal"
                    class="px-4 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors">
                    + Registrar Pago
                </button>
            </div>
            <!-- Filtros -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                    <select v-model="filters.event_id" @change="onFilterChange"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                        <option value="">Todos los eventos</option>
                        <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                    </select>
                    <select v-model="filters.payment_type" @change="onFilterChange"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                        <option value="">Todos los tipos</option>
                        <option value="downpayment">Downpayment</option>
                        <option value="installment">Cuota</option>
                    </select>
                    <select v-model="filters.payment_method" @change="onFilterChange"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                        <option value="">Todos los métodos</option>
                        <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                    <input v-model="filters.search" type="text" placeholder="Buscar marca, nombre, referencia..."
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    <input v-model="filters.date_from" type="date" @change="onFilterChange"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    <input v-model="filters.date_to" type="date" @change="onFilterChange"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                </div>
                <button v-if="hasActiveFilters" @click="clearFilters" class="mt-2 text-xs text-gray-500 hover:text-gray-700">
                    Limpiar filtros
                </button>
            </div>

            <!-- Resumen -->
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">Total Registrado</p>
                    <p class="text-xl font-bold">{{ fmt(totals.total) }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">Downpayments</p>
                    <p class="text-xl font-bold" style="color: #D4AF37;">{{ fmt(totals.downpayments) }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">Cuotas</p>
                    <p class="text-xl font-bold text-blue-600">{{ fmt(totals.installments) }}</p>
                </div>
            </div>

            <!-- Tabla -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50/50">
                                <th class="py-3 px-4">Fecha</th>
                                <th class="py-3 px-4">Monto</th>
                                <th class="py-3 px-4">Método</th>
                                <th class="py-3 px-4">Tipo</th>
                                <th class="py-3 px-4">Marca</th>
                                <th class="py-3 px-4">Referencia</th>
                                <th class="py-3 px-4">Evento</th>
                                <th class="py-3 px-4 text-center">Comp.</th>
                                <th class="py-3 px-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in records.data" :key="r.id" class="border-b border-gray-50 hover:bg-gray-50/50 transition">
                                <td class="py-3 px-4 text-gray-500 text-xs whitespace-nowrap">{{ r.payment_date_formatted }}</td>
                                <td class="py-3 px-4 font-semibold whitespace-nowrap">{{ fmtFull(r.amount) }}</td>
                                <td class="py-3 px-4">
                                    <span :class="methodBadge(r.payment_method)" class="px-2 py-0.5 rounded text-xs font-medium whitespace-nowrap">{{ r.payment_method_label }}</span>
                                </td>
                                <td class="py-3 px-4">
                                    <span :class="typeBadge(r.payment_type)" class="px-2 py-0.5 rounded text-xs font-medium">{{ r.payment_type_label }}</span>
                                </td>
                                <td class="py-3 px-4">
                                    <div>
                                        <p class="font-medium text-gray-900">{{ r.designer_brand || r.designer_name }}</p>
                                        <p v-if="r.designer_brand" class="text-xs text-gray-400">{{ r.designer_name }}</p>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-gray-500 text-xs">{{ r.reference || '—' }}</td>
                                <td class="py-3 px-4 text-gray-500 text-xs whitespace-nowrap">{{ r.event_name }}</td>
                                <td class="py-3 px-4 text-center">
                                    <a v-if="r.receipt_url" :href="`/storage/${r.receipt_url}`" target="_blank"
                                        class="text-blue-600 hover:text-blue-800" title="Ver comprobante">
                                        <PaperClipIcon class="w-4 h-4 inline" />
                                    </a>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-1">
                                        <button @click="openEditModal(r)" class="p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition-colors" title="Editar">
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </button>
                                        <button @click="openDeleteModal(r)" class="p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-red-50 hover:text-red-500 transition-colors" title="Eliminar">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!records.data.length">
                                <td colspan="9" class="py-12 text-center text-gray-400">
                                    No hay registros de pago{{ hasActiveFilters ? ' con los filtros seleccionados' : '' }}.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div v-if="records.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <p class="text-xs text-gray-500">Mostrando {{ records.from }}–{{ records.to }} de {{ records.total }}</p>
                    <div class="flex gap-1">
                        <template v-for="link in records.links" :key="link.label">
                            <button v-if="link.url"
                                @click="router.get(link.url, {}, { preserveState: true })"
                                :class="link.active ? 'bg-black text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                                class="px-3 py-1 text-xs rounded border border-gray-200" v-html="link.label" />
                            <span v-else class="px-3 py-1 text-xs text-gray-300" v-html="link.label" />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Crear Pago -->
        <Teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="showCreateModal = false">
                <div class="bg-white rounded-2xl p-6 w-full max-w-lg shadow-xl max-h-[90vh] overflow-y-auto">
                    <h4 class="font-bold text-gray-900 mb-4">Registrar Pago</h4>
                    <form @submit.prevent="submitCreate" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Evento *</label>
                            <select v-model="createForm.event_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">Seleccionar evento...</option>
                                <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                            </select>
                            <p v-if="createForm.errors.event_id" class="text-xs text-red-500 mt-1">{{ createForm.errors.event_id }}</p>
                        </div>

                        <div v-if="createForm.event_id">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Diseñador *</label>
                            <div v-if="selectedDesigner" class="flex items-center gap-2 bg-gray-50 rounded-lg px-3 py-2">
                                <div class="flex-1">
                                    <p class="text-sm font-medium">{{ selectedDesigner.name }}</p>
                                    <p v-if="selectedDesigner.brand" class="text-xs text-gray-500">{{ selectedDesigner.brand }}</p>
                                </div>
                                <button type="button" @click="clearSelectedDesigner" class="text-gray-400 hover:text-gray-600">
                                    <XMarkIcon class="w-4 h-4" />
                                </button>
                            </div>
                            <div v-else class="relative">
                                <input v-model="designerSearch" type="text" placeholder="Buscar diseñador..."
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                <div v-if="designerResults.length" class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-40 overflow-y-auto">
                                    <button v-for="d in designerResults" :key="d.id" type="button"
                                        @click="selectDesigner(d)"
                                        class="w-full text-left px-3 py-2 hover:bg-gray-50 text-sm">
                                        <span class="font-medium">{{ d.name }}</span>
                                        <span v-if="d.brand" class="text-gray-400 ml-1">— {{ d.brand }}</span>
                                    </button>
                                </div>
                                <div v-if="searchingDesigners" class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg p-3 text-center text-xs text-gray-400">
                                    Buscando...
                                </div>
                            </div>
                            <p v-if="createForm.errors.designer_id" class="text-xs text-red-500 mt-1">{{ createForm.errors.designer_id }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Monto *</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                    <input v-model.number="createForm.amount" type="number" step="0.01" min="0.01"
                                        class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                </div>
                                <p v-if="createForm.errors.amount" class="text-xs text-red-500 mt-1">{{ createForm.errors.amount }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Tipo de Pago *</label>
                                <select v-model="createForm.payment_type"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                    <option value="downpayment">Downpayment</option>
                                    <option value="installment">Cuota</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Método de Pago *</label>
                                <select v-model="createForm.payment_method"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                    <option value="">Seleccionar...</option>
                                    <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                                </select>
                                <p v-if="createForm.errors.payment_method" class="text-xs text-red-500 mt-1">{{ createForm.errors.payment_method }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Referencia</label>
                                <input v-model="createForm.reference" type="text" placeholder="Nombre según banco/Stripe..."
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Fecha y Hora del Pago *</label>
                            <input v-model="createForm.payment_date" type="datetime-local"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="createForm.errors.payment_date" class="text-xs text-red-500 mt-1">{{ createForm.errors.payment_date }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Comprobante (opcional)</label>
                            <input type="file" accept=".jpg,.jpeg,.png,.pdf" @input="createForm.receipt = $event.target.files[0]"
                                class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200" />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Notas (opcional)</label>
                            <textarea v-model="createForm.notes" rows="2"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10"></textarea>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showCreateModal = false"
                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
                            <button type="submit" :disabled="createForm.processing"
                                class="bg-black text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-gray-800 transition disabled:opacity-50">
                                {{ createForm.processing ? 'Registrando...' : 'Registrar Pago' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal: Editar Pago -->
        <Teleport to="body">
            <div v-if="showEditModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="showEditModal = false">
                <div class="bg-white rounded-2xl p-6 w-full max-w-lg shadow-xl max-h-[90vh] overflow-y-auto">
                    <h4 class="font-bold text-gray-900 mb-1">Editar Registro de Pago</h4>
                    <p class="text-sm text-gray-500 mb-4">
                        {{ editingRecord?.designer_brand || editingRecord?.designer_name }} — {{ editingRecord?.event_name }}
                    </p>
                    <form @submit.prevent="submitEdit" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Monto *</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                    <input v-model.number="editForm.amount" type="number" step="0.01" min="0.01"
                                        class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                </div>
                                <p v-if="editForm.errors.amount" class="text-xs text-red-500 mt-1">{{ editForm.errors.amount }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Tipo de Pago *</label>
                                <select v-model="editForm.payment_type"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                    <option value="downpayment">Downpayment</option>
                                    <option value="installment">Cuota</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Método de Pago *</label>
                                <select v-model="editForm.payment_method"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                    <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Referencia</label>
                                <input v-model="editForm.reference" type="text"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Fecha y Hora del Pago *</label>
                            <input v-model="editForm.payment_date" type="datetime-local"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Comprobante</label>
                            <div v-if="editingRecord?.receipt_url" class="mb-2">
                                <a :href="`/storage/${editingRecord.receipt_url}`" target="_blank" class="text-xs text-blue-600 hover:underline">Ver comprobante actual</a>
                            </div>
                            <input type="file" accept=".jpg,.jpeg,.png,.pdf" @input="editForm.receipt = $event.target.files[0]"
                                class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200" />
                            <p class="text-xs text-gray-400 mt-1">Sube un nuevo archivo para reemplazar el actual</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Notas</label>
                            <textarea v-model="editForm.notes" rows="2"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10"></textarea>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showEditModal = false"
                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
                            <button type="submit" :disabled="editForm.processing"
                                class="bg-black text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-gray-800 transition disabled:opacity-50">
                                {{ editForm.processing ? 'Guardando...' : 'Guardar Cambios' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal: Confirmar Eliminar -->
        <Teleport to="body">
            <div v-if="showDeleteModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="showDeleteModal = false">
                <div class="bg-white rounded-2xl p-6 w-full max-w-sm shadow-xl">
                    <h4 class="font-bold text-gray-900 mb-2">Eliminar Registro</h4>
                    <p class="text-sm text-gray-500 mb-4">¿Estás seguro de eliminar este registro de pago?</p>
                    <div v-if="deletingRecord" class="bg-gray-50 rounded-lg p-3 mb-4 text-sm space-y-1">
                        <p><span class="text-gray-500">Fecha:</span> {{ deletingRecord.payment_date_formatted }}</p>
                        <p><span class="text-gray-500">Monto:</span> <strong>{{ fmtFull(deletingRecord.amount) }}</strong></p>
                        <p><span class="text-gray-500">Marca:</span> {{ deletingRecord.designer_brand || deletingRecord.designer_name }}</p>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button @click="showDeleteModal = false"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
                        <button @click="submitDelete"
                            class="bg-red-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition">
                            Eliminar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
