<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { ChevronLeftIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    designer: Object,
    event: Object,
    plan: Object,
    packages: Array,
    categories: Array,
    salesReps: Array,
});

// --- Crear Plan ---
const planForm = useForm({
    designer_id: props.designer.id,
    event_id: props.event.id,
    total_amount: props.event.package_price || 0,
    downpayment: props.event.suggested_downpayment || 0,
    installments_count: props.event.suggested_installments_count || 3,
    notes: '',
    custom_amounts: null,
    custom_dates: null,
});

const customizeCreate = ref(false);
const createAmounts = ref([]);
const createDates = ref([]);

function defaultDates(count) {
    const start = new Date();
    start.setDate(1);
    start.setMonth(start.getMonth() + 1);
    return Array.from({ length: count }, (_, i) => {
        const d = new Date(start);
        d.setMonth(d.getMonth() + i);
        return d.toISOString().slice(0, 10);
    });
}

function initCreateAmounts() {
    const remaining = planForm.total_amount - planForm.downpayment;
    const count = planForm.installments_count;
    if (remaining <= 0 || count < 1) { createAmounts.value = []; createDates.value = []; return; }
    const amt = Math.round((remaining / count) * 100) / 100;
    createAmounts.value = Array.from({ length: count }, (_, i) =>
        i === count - 1 ? Math.round((remaining - amt * (count - 1)) * 100) / 100 : amt
    );
    createDates.value = defaultDates(count);
}

watch([() => planForm.total_amount, () => planForm.downpayment, () => planForm.installments_count], () => {
    if (customizeCreate.value) initCreateAmounts();
});

watch(customizeCreate, (val) => { if (val) initCreateAmounts(); });

const createAmountsRemaining = computed(() => {
    const target = planForm.total_amount - planForm.downpayment;
    const sum = createAmounts.value.reduce((s, v) => s + (Number(v) || 0), 0);
    return Math.round((target - sum) * 100) / 100;
});

const previewInstallments = computed(() => {
    const remaining = planForm.total_amount - planForm.downpayment;
    if (remaining <= 0 || planForm.installments_count < 1) return [];
    if (customizeCreate.value) {
        return createAmounts.value.map((amt, i) => ({ number: i + 1, amount: Number(amt) || 0 }));
    }
    const amt = Math.round((remaining / planForm.installments_count) * 100) / 100;
    const items = [];
    for (let i = 1; i <= planForm.installments_count; i++) {
        const isLast = i === planForm.installments_count;
        items.push({
            number: i,
            amount: isLast ? Math.round((remaining - amt * (planForm.installments_count - 1)) * 100) / 100 : amt,
        });
    }
    return items;
});

function submitPlan() {
    if (customizeCreate.value) {
        planForm.custom_amounts = createAmounts.value.map(Number);
        planForm.custom_dates = createDates.value;
    } else {
        planForm.custom_amounts = null;
        planForm.custom_dates = null;
    }
    planForm.post('/admin/accounting/payments/create-plan');
}

// --- Editar Plan ---
const editing = ref(false);
const editForm = useForm({
    total_amount: props.plan?.total_amount ?? 0,
    downpayment: props.plan?.downpayment ?? 0,
    installments_count: props.plan?.installments_count ?? 3,
    notes: props.plan?.notes ?? '',
    custom_amounts: null,
    custom_dates: null,
});

const customizeEdit = ref(false);
const editAmounts = ref([]);
const editDates = ref([]);
const paidCount = computed(() => props.plan?.installments?.filter(i => i.status === 'paid').length ?? 0);
const paidTotal = computed(() => props.plan?.installments?.filter(i => i.status === 'paid').reduce((s, i) => s + i.amount, 0) ?? 0);

function initEditAmounts() {
    const remaining = editForm.total_amount - editForm.downpayment;
    const newCount = editForm.installments_count - paidCount.value;
    const newRemaining = remaining - paidTotal.value;
    if (newCount <= 0 || newRemaining <= 0) { editAmounts.value = []; editDates.value = []; return; }
    const amt = Math.round((newRemaining / newCount) * 100) / 100;
    editAmounts.value = Array.from({ length: newCount }, (_, i) =>
        i === newCount - 1 ? Math.round((newRemaining - amt * (newCount - 1)) * 100) / 100 : amt
    );
    // Use existing unpaid installment dates when available, fallback to defaults
    const unpaidInstallments = (props.plan?.installments ?? []).filter(i => i.status !== 'paid');
    const fallback = defaultDates(newCount);
    editDates.value = Array.from({ length: newCount }, (_, i) =>
        unpaidInstallments[i]?.due_date ?? fallback[i]
    );
}

watch([() => editForm.total_amount, () => editForm.downpayment, () => editForm.installments_count], () => {
    if (customizeEdit.value) initEditAmounts();
});

watch(customizeEdit, (val) => { if (val) initEditAmounts(); });

const editAmountsRemaining = computed(() => {
    const remaining = editForm.total_amount - editForm.downpayment;
    const target = remaining - paidTotal.value;
    const sum = editAmounts.value.reduce((s, v) => s + (Number(v) || 0), 0);
    return Math.round((target - sum) * 100) / 100;
});

const editPreviewInstallments = computed(() => {
    const remaining = editForm.total_amount - editForm.downpayment;
    const newCount = editForm.installments_count - paidCount.value;
    const newRemaining = remaining - paidTotal.value;
    if (newCount <= 0 || newRemaining <= 0) return [];
    if (customizeEdit.value) {
        return editAmounts.value.map((amt, i) => ({ number: paidCount.value + i + 1, amount: Number(amt) || 0 }));
    }
    const amt = Math.round((newRemaining / newCount) * 100) / 100;
    const items = [];
    for (let i = 1; i <= newCount; i++) {
        const isLast = i === newCount;
        items.push({
            number: paidCount.value + i,
            amount: isLast ? Math.round((newRemaining - amt * (newCount - 1)) * 100) / 100 : amt,
        });
    }
    return items;
});

function startEditing() {
    editForm.total_amount = props.plan.total_amount;
    editForm.downpayment = props.plan.downpayment;
    editForm.installments_count = props.plan.installments_count;
    editForm.notes = props.plan.notes ?? '';
    customizeEdit.value = false;
    editAmounts.value = [];
    editDates.value = [];
    editing.value = true;
}

function cancelEditing() {
    editing.value = false;
    customizeEdit.value = false;
}

function submitEdit() {
    if (customizeEdit.value) {
        editForm.custom_amounts = editAmounts.value.map(Number);
        editForm.custom_dates = editDates.value;
    } else {
        editForm.custom_amounts = null;
        editForm.custom_dates = null;
    }
    editForm.put(`/admin/accounting/payments/plans/${props.plan.id}`, {
        onSuccess: () => { editing.value = false; customizeEdit.value = false; },
    });
}

// --- Downpayment Modal ---
const showDownpaymentModal = ref(false);
const downpaymentForm = useForm({ receipt: null });

function submitDownpayment() {
    downpaymentForm.post(`/admin/accounting/payments/plans/${props.plan?.id}/downpayment-paid`, {
        onSuccess: () => { showDownpaymentModal.value = false; },
        forceFormData: true,
    });
}

// --- Installment Modal ---
const showInstallmentModal = ref(false);
const selectedInstallment = ref(null);
const installmentForm = useForm({
    payment_method: '',
    payment_reference: '',
    receipt: null,
    notes: '',
});

function openInstallmentModal(inst) {
    selectedInstallment.value = inst;
    installmentForm.reset();
    showInstallmentModal.value = true;
}

function submitInstallment() {
    installmentForm.post(`/admin/accounting/payments/installments/${selectedInstallment.value.id}/mark-paid`, {
        onSuccess: () => { showInstallmentModal.value = false; },
        forceFormData: true,
    });
}

// --- Upload Receipt Modal ---
const showUploadModal = ref(false);
const uploadInstallment = ref(null);
const uploadForm = useForm({ receipt: null });

function openUploadModal(inst) {
    uploadInstallment.value = inst;
    uploadForm.reset();
    showUploadModal.value = true;
}

function submitUpload() {
    uploadForm.post(`/admin/accounting/payments/installments/${uploadInstallment.value.id}/upload-receipt`, {
        onSuccess: () => { showUploadModal.value = false; },
        forceFormData: true,
    });
}

// --- Helpers ---
function fmt(n) {
    return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function statusBadge(status) {
    return {
        pending: 'bg-yellow-50 text-yellow-700',
        partial: 'bg-blue-50 text-blue-700',
        paid: 'bg-green-50 text-green-700',
        overdue: 'bg-red-50 text-red-600',
        cancelled: 'bg-gray-100 text-gray-500',
    }[status] ?? 'bg-gray-50 text-gray-600';
}

function statusLabel(status) {
    return {
        pending: 'Pendiente',
        partial: 'Parcial',
        paid: 'Pagado',
        overdue: 'Vencido',
        cancelled: 'Cancelado',
        active: 'Activo',
        completed: 'Completado',
    }[status] ?? status;
}

function methodLabel(method) {
    return {
        wire_transfer: 'Transferencia',
        venmo: 'Venmo',
        zelle: 'Zelle',
        cash: 'Efectivo',
        check: 'Cheque',
        other: 'Otro',
    }[method] ?? method;
}

// --- Editar Datos Diseñador ---
const editingDesigner = ref(false);
const designerForm = useForm({
    first_name: props.designer.first_name,
    last_name: props.designer.last_name,
    email: props.designer.email,
    phone: props.designer.phone ?? '',
    status: props.designer.status ?? 'pending',
    brand_name: props.designer.brand_name ?? '',
    category_id: props.designer.category_id ?? '',
    sales_rep_id: props.designer.sales_rep_id ?? '',
    looks: props.event.looks ?? 0,
    package_id: props.event.package_id ?? '',
});

function startEditingDesigner() {
    designerForm.first_name = props.designer.first_name;
    designerForm.last_name = props.designer.last_name;
    designerForm.email = props.designer.email;
    designerForm.phone = props.designer.phone ?? '';
    designerForm.status = props.designer.status ?? 'pending';
    designerForm.brand_name = props.designer.brand_name ?? '';
    designerForm.category_id = props.designer.category_id ?? '';
    designerForm.sales_rep_id = props.designer.sales_rep_id ?? '';
    designerForm.looks = props.event.looks ?? 0;
    designerForm.package_id = props.event.package_id ?? '';
    editingDesigner.value = true;
}

function cancelEditingDesigner() {
    editingDesigner.value = false;
}

function submitDesignerEdit() {
    designerForm.put(`/admin/accounting/payments/designer/${props.designer.id}/event/${props.event.id}`, {
        onSuccess: () => { editingDesigner.value = false; },
    });
}

const paymentMethods = [
    { value: 'wire_transfer', label: 'Transferencia bancaria' },
    { value: 'venmo', label: 'Venmo' },
    { value: 'zelle', label: 'Zelle' },
    { value: 'cash', label: 'Efectivo' },
    { value: 'check', label: 'Cheque' },
    { value: 'other', label: 'Otro' },
];
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/accounting/payments" class="text-gray-400 hover:text-gray-600 transition">
                    <ChevronLeftIcon class="w-5 h-5" />
                </Link>
                <h2 class="text-lg font-semibold text-gray-900">Pago de Disenador</h2>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Header: Info diseñador -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <!-- Modo vista -->
                <div v-if="!editingDesigner" class="flex items-start gap-4">
                    <div v-if="designer.profile_picture"
                        class="w-16 h-16 rounded-full bg-cover bg-center border-2 border-gray-200 flex-shrink-0"
                        :style="`background-image: url('/storage/${designer.profile_picture}')`"></div>
                    <div v-else class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center text-lg font-bold text-gray-400 flex-shrink-0">
                        {{ (designer.first_name?.[0] ?? '') + (designer.last_name?.[0] ?? '') }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-xl font-bold text-gray-900">{{ designer.first_name }} {{ designer.last_name }}</h3>
                        <p v-if="designer.brand_name" class="text-sm text-gray-500">{{ designer.brand_name }}</p>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-xs text-gray-500">
                            <span v-if="designer.email">{{ designer.email }}</span>
                            <span v-if="designer.phone">{{ designer.phone }}</span>
                            <span v-if="designer.category" class="bg-gray-100 px-2 py-0.5 rounded">{{ designer.category }}</span>
                            <span v-if="designer.sales_rep" class="bg-green-50 text-green-700 px-2 py-0.5 rounded">Rep: {{ designer.sales_rep.name }}</span>
                            <span :class="{
                                'bg-green-50 text-green-700': designer.status === 'active',
                                'bg-gray-100 text-gray-500': designer.status === 'inactive',
                                'bg-yellow-50 text-yellow-700': designer.status === 'pending',
                            }" class="px-2 py-0.5 rounded font-medium">{{ { active: 'Activo', inactive: 'Inactivo', pending: 'Pendiente' }[designer.status] ?? designer.status }}</span>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-xs text-gray-400 uppercase tracking-widest">Evento</p>
                        <p class="font-semibold text-gray-900">{{ event.name }}</p>
                        <p v-if="event.looks" class="text-xs text-gray-500 mt-1">{{ event.looks }} looks</p>
                        <p v-if="event.package_name || event.package_price" class="text-sm font-medium mt-0.5" style="color: #D4AF37;">{{ [event.package_name, event.package_price ? fmt(event.package_price) : null].filter(Boolean).join(' - ') }}</p>
                    </div>
                    <button @click="startEditingDesigner"
                        class="flex-shrink-0 border border-gray-300 text-gray-700 p-2 rounded-lg hover:bg-gray-50 transition"
                        title="Editar datos">
                        <PencilSquareIcon class="w-4 h-4" />
                    </button>
                </div>

                <!-- Modo edición -->
                <form v-else @submit.prevent="submitDesignerEdit" class="space-y-4">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-bold text-gray-900">Editar Datos del Diseñador</h4>
                        <button type="button" @click="cancelEditingDesigner" class="text-sm text-gray-500 hover:text-gray-700">Cancelar</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Nombre</label>
                            <input v-model="designerForm.first_name" type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="designerForm.errors.first_name" class="text-xs text-red-500 mt-1">{{ designerForm.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Apellido</label>
                            <input v-model="designerForm.last_name" type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="designerForm.errors.last_name" class="text-xs text-red-500 mt-1">{{ designerForm.errors.last_name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Marca</label>
                            <input v-model="designerForm.brand_name" type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Email</label>
                            <input v-model="designerForm.email" type="email"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="designerForm.errors.email" class="text-xs text-red-500 mt-1">{{ designerForm.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Teléfono</label>
                            <input v-model="designerForm.phone" type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Estado</label>
                            <select v-model="designerForm.status"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                                <option value="pending">Pendiente</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Categoría</label>
                            <select v-model="designerForm.category_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">Sin categoría</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Representante de Ventas</label>
                            <select v-model="designerForm.sales_rep_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">Sin representante</option>
                                <option v-for="rep in salesReps" :key="rep.id" :value="rep.id">{{ rep.first_name }} {{ rep.last_name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Looks</label>
                            <input v-model.number="designerForm.looks" type="number" min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Paquete</label>
                            <select v-model="designerForm.package_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">Sin paquete</option>
                                <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }} — {{ fmt(pkg.price) }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="cancelEditingDesigner"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
                        <button type="submit" :disabled="designerForm.processing"
                            class="bg-black text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-gray-800 transition disabled:opacity-50">
                            {{ designerForm.processing ? 'Guardando...' : 'Guardar Cambios' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- SIN PLAN: Formulario de creación -->
            <div v-if="!plan" class="bg-white rounded-2xl border border-gray-200 p-6">
                <h4 class="font-bold text-gray-900 mb-6">Crear Plan de Pagos</h4>

                <form @submit.prevent="submitPlan" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Monto Total</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                <input v-model.number="planForm.total_amount" type="number" step="0.01" min="0"
                                    class="w-full pl-7 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <p v-if="planForm.errors.total_amount" class="text-xs text-red-500 mt-1">{{ planForm.errors.total_amount }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Downpayment</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                <input v-model.number="planForm.downpayment" type="number" step="0.01" min="0"
                                    class="w-full pl-7 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <p v-if="planForm.errors.downpayment" class="text-xs text-red-500 mt-1">{{ planForm.errors.downpayment }}</p>
                            <p v-if="event.suggested_downpayment" class="text-xs text-blue-600 mt-1">Monto sugerido por vendedor: ${{ Number(event.suggested_downpayment).toLocaleString() }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Numero de Cuotas</label>
                            <input v-model.number="planForm.installments_count" type="number" min="1" max="12"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="planForm.errors.installments_count" class="text-xs text-red-500 mt-1">{{ planForm.errors.installments_count }}</p>
                            <p v-if="event.suggested_installments_count" class="text-xs text-blue-600 mt-1">Cuotas sugeridas por vendedor: {{ event.suggested_installments_count }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notas (opcional)</label>
                        <textarea v-model="planForm.notes" rows="2"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10"></textarea>
                    </div>

                    <!-- Preview cuotas -->
                    <div v-if="previewInstallments.length" class="bg-gray-50 rounded-xl p-4">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-widest">Vista previa del plan</p>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="customizeCreate" class="rounded border-gray-300 text-black focus:ring-black/20" />
                                <span class="text-xs text-gray-600">Personalizar</span>
                            </label>
                        </div>
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-sm text-gray-700">Downpayment:</span>
                            <span class="font-bold">{{ fmt(planForm.downpayment) }}</span>
                        </div>
                        <div class="space-y-2">
                            <div v-for="(inst, idx) in previewInstallments" :key="inst.number" class="flex items-center gap-3 text-sm">
                                <span class="text-gray-500 w-20">Cuota {{ inst.number }}:</span>
                                <template v-if="customizeCreate">
                                    <div class="relative w-32">
                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                                        <input v-model.number="createAmounts[idx]" type="number" step="0.01" min="0"
                                            class="w-full pl-6 pr-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                    </div>
                                    <input v-model="createDates[idx]" type="date"
                                        class="w-40 px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                </template>
                                <span v-else class="font-medium">{{ fmt(inst.amount) }}</span>
                            </div>
                        </div>
                        <!-- Validación suma -->
                        <div v-if="customizeCreate" class="mt-3 pt-3 border-t border-gray-200">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">Diferencia:</span>
                                <span :class="createAmountsRemaining === 0 ? 'text-green-600 font-bold' : 'text-red-500 font-bold'">
                                    {{ createAmountsRemaining === 0 ? 'Cuadra correctamente' : fmt(createAmountsRemaining) + ' por asignar' }}
                                </span>
                            </div>
                        </div>
                        <div class="border-t border-gray-200 mt-3 pt-3 flex items-center gap-3 text-sm">
                            <span class="text-gray-700 font-medium">Total:</span>
                            <span class="font-bold text-lg">{{ fmt(planForm.total_amount) }}</span>
                        </div>
                    </div>

                    <p v-if="planForm.errors.plan" class="text-sm text-red-500">{{ planForm.errors.plan }}</p>

                    <div class="flex justify-end">
                        <button type="submit" :disabled="planForm.processing || (customizeCreate && createAmountsRemaining !== 0)"
                            class="bg-black text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-800 transition disabled:opacity-50">
                            {{ planForm.processing ? 'Creando...' : 'Crear Plan de Pagos' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- CON PLAN: Vista completa -->
            <template v-if="plan">
                <!-- Barra de progreso general -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-bold text-gray-900">Progreso de Pago</h4>
                        <div class="flex items-center gap-3">
                            <button v-if="!editing && plan.status !== 'completed'"
                                @click="startEditing"
                                class="border border-gray-300 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-gray-50 transition flex items-center gap-1.5">
                                <PencilSquareIcon class="w-3.5 h-3.5" />
                                Editar Plan
                            </button>
                            <span :class="statusBadge(plan.status)" class="px-3 py-1 rounded-lg text-xs font-semibold">
                                {{ statusLabel(plan.status) }}
                            </span>
                        </div>
                    </div>
                    <div class="w-full h-3 bg-gray-200 rounded-full overflow-hidden mb-2">
                        <div class="h-full rounded-full transition-all duration-500"
                            :class="plan.progress === 100 ? 'bg-green-500' : plan.progress >= 50 ? 'bg-yellow-400' : 'bg-red-400'"
                            :style="`width: ${plan.progress}%`"></div>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">{{ plan.progress }}% completado</span>
                        <span class="font-medium">{{ fmt(plan.total_paid) }} / {{ fmt(plan.total_amount) }}</span>
                    </div>
                </div>

                <!-- Resumen -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                        <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">Total</p>
                        <p class="text-xl font-bold">{{ fmt(plan.total_amount) }}</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                        <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">Downpayment</p>
                        <p class="text-xl font-bold">{{ fmt(plan.downpayment) }}</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                        <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">Cuotas</p>
                        <p class="text-xl font-bold">{{ plan.installments_count }}</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                        <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">Pagado</p>
                        <p class="text-xl font-bold text-green-600">{{ fmt(plan.total_paid) }}</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                        <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">Pendiente</p>
                        <p class="text-xl font-bold" style="color: #D4AF37;">{{ fmt(plan.total_pending) }}</p>
                    </div>
                </div>

                <!-- Formulario de edición -->
                <div v-if="editing" class="bg-white rounded-2xl border-2 border-yellow-400 p-6">
                    <h4 class="font-bold text-gray-900 mb-6">Editar Plan de Pagos</h4>
                    <form @submit.prevent="submitEdit" class="space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Monto Total</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                    <input v-model.number="editForm.total_amount" type="number" step="0.01" min="0"
                                        class="w-full pl-7 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                </div>
                                <p v-if="editForm.errors.total_amount" class="text-xs text-red-500 mt-1">{{ editForm.errors.total_amount }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Downpayment</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                    <input v-model.number="editForm.downpayment" type="number" step="0.01" min="0"
                                        class="w-full pl-7 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                </div>
                                <p v-if="editForm.errors.downpayment" class="text-xs text-red-500 mt-1">{{ editForm.errors.downpayment }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Numero Total de Cuotas</label>
                                <input v-model.number="editForm.installments_count" type="number" min="1" max="12"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                <p v-if="editForm.errors.installments_count" class="text-xs text-red-500 mt-1">{{ editForm.errors.installments_count }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notas (opcional)</label>
                            <textarea v-model="editForm.notes" rows="2"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10"></textarea>
                        </div>

                        <!-- Info cuotas pagadas -->
                        <div v-if="plan.installments.filter(i => i.status === 'paid').length" class="bg-blue-50 rounded-xl p-4 text-sm text-blue-700">
                            <strong>{{ plan.installments.filter(i => i.status === 'paid').length }}</strong> cuota(s) ya pagada(s) se mantendran sin cambios. Solo se regeneraran las cuotas pendientes.
                        </div>

                        <!-- Preview nuevas cuotas -->
                        <div v-if="editPreviewInstallments.length" class="bg-gray-50 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-widest">Nuevas cuotas pendientes</p>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="customizeEdit" class="rounded border-gray-300 text-black focus:ring-black/20" />
                                    <span class="text-xs text-gray-600">Personalizar</span>
                                </label>
                            </div>
                            <div class="space-y-2">
                                <div v-for="(inst, idx) in editPreviewInstallments" :key="inst.number" class="flex items-center gap-3 text-sm">
                                    <span class="text-gray-500 w-20">Cuota {{ inst.number }}:</span>
                                    <template v-if="customizeEdit">
                                        <div class="relative w-32">
                                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                                            <input v-model.number="editAmounts[idx]" type="number" step="0.01" min="0"
                                                class="w-full pl-6 pr-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                        </div>
                                        <input v-model="editDates[idx]" type="date"
                                            class="w-40 px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                    </template>
                                    <span v-else class="font-medium">{{ fmt(inst.amount) }}</span>
                                </div>
                            </div>
                            <!-- Validación suma -->
                            <div v-if="customizeEdit" class="mt-3 pt-3 border-t border-gray-200">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600">Diferencia:</span>
                                    <span :class="editAmountsRemaining === 0 ? 'text-green-600 font-bold' : 'text-red-500 font-bold'">
                                        {{ editAmountsRemaining === 0 ? 'Cuadra correctamente' : fmt(editAmountsRemaining) + ' por asignar' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <p v-if="editForm.errors.plan" class="text-sm text-red-500">{{ editForm.errors.plan }}</p>

                        <div class="flex justify-end gap-3">
                            <button type="button" @click="cancelEditing"
                                class="px-4 py-2.5 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
                            <button type="submit" :disabled="editForm.processing || (customizeEdit && editAmountsRemaining !== 0)"
                                class="bg-black text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-800 transition disabled:opacity-50">
                                {{ editForm.processing ? 'Guardando...' : 'Guardar Cambios' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Downpayment -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="font-bold text-gray-900 mb-4">Downpayment</h4>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <p class="text-2xl font-bold">{{ fmt(plan.downpayment) }}</p>
                            <span :class="statusBadge(plan.downpayment_status)" class="px-3 py-1 rounded-lg text-xs font-semibold">
                                {{ statusLabel(plan.downpayment_status) }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span v-if="plan.downpayment_paid_at" class="text-xs text-gray-400">{{ plan.downpayment_paid_at }}</span>
                            <a v-if="plan.downpayment_receipt" :href="`/storage/${plan.downpayment_receipt}`" target="_blank"
                                class="text-xs text-blue-600 hover:underline">Ver comprobante</a>
                            <button v-if="plan.downpayment_status === 'pending'"
                                @click="showDownpaymentModal = true"
                                class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition">
                                Marcar Pagado
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla de cuotas -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="font-bold text-gray-900 mb-4">Cuotas</h4>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100">
                                <th class="py-3 pr-3">#</th>
                                <th class="py-3 pr-3">Monto</th>
                                <th class="py-3 pr-3">Fecha Limite</th>
                                <th class="py-3 pr-3">Estado</th>
                                <th class="py-3 pr-3">Metodo</th>
                                <th class="py-3 pr-3">Referencia</th>
                                <th class="py-3 pr-3">Comprobante</th>
                                <th class="py-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="inst in plan.installments" :key="inst.id" class="border-b border-gray-50">
                                <td class="py-3 pr-3 font-medium">{{ inst.number }}</td>
                                <td class="py-3 pr-3 font-medium">
                                    <template v-if="inst.paid_amount > 0 && inst.status !== 'paid'">
                                        <span class="text-blue-600">{{ fmt(inst.paid_amount) }}</span>
                                        <span class="text-gray-400"> / {{ fmt(inst.amount) }}</span>
                                    </template>
                                    <template v-else>{{ fmt(inst.amount) }}</template>
                                </td>
                                <td class="py-3 pr-3 text-gray-500">{{ inst.due_date }}</td>
                                <td class="py-3 pr-3">
                                    <span :class="statusBadge(inst.status)" class="px-2 py-0.5 rounded text-xs font-medium">
                                        {{ statusLabel(inst.status) }}
                                    </span>
                                </td>
                                <td class="py-3 pr-3 text-gray-500">{{ inst.payment_method ? methodLabel(inst.payment_method) : '—' }}</td>
                                <td class="py-3 pr-3 text-gray-500 text-xs">{{ inst.payment_reference || '—' }}</td>
                                <td class="py-3 pr-3">
                                    <a v-if="inst.receipt_url" :href="`/storage/${inst.receipt_url}`" target="_blank"
                                        class="text-xs text-blue-600 hover:underline">Ver</a>
                                    <span v-else class="text-xs text-gray-300">—</span>
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center gap-2">
                                        <button v-if="inst.status === 'pending' || inst.status === 'overdue' || inst.status === 'partial'"
                                            @click="openInstallmentModal(inst)"
                                            class="bg-green-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-green-700 transition">
                                            Registrar Pago
                                        </button>
                                        <button v-if="inst.status === 'paid' && !inst.receipt_url"
                                            @click="openUploadModal(inst)"
                                            class="border border-gray-300 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-gray-50 transition">
                                            Subir Recibo
                                        </button>
                                        <div v-if="inst.status === 'paid' && inst.marked_by" class="text-xs text-gray-400">
                                            por {{ inst.marked_by }}
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="plan.notes" class="mt-4 text-sm text-gray-500 italic">Notas: {{ plan.notes }}</p>
                </div>
            </template>
        </div>

        <!-- Modal: Downpayment -->
        <Teleport to="body">
            <div v-if="showDownpaymentModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="showDownpaymentModal = false">
                <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
                    <h4 class="font-bold text-gray-900 mb-4">Marcar Downpayment como Pagado</h4>
                    <p class="text-sm text-gray-500 mb-4">Monto: <strong>{{ fmt(plan?.downpayment) }}</strong></p>
                    <form @submit.prevent="submitDownpayment">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Comprobante (opcional)</label>
                            <input type="file" accept=".jpg,.jpeg,.png,.pdf" @input="downpaymentForm.receipt = $event.target.files[0]"
                                class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200" />
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="showDownpaymentModal = false"
                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
                            <button type="submit" :disabled="downpaymentForm.processing"
                                class="bg-green-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition disabled:opacity-50">
                                {{ downpaymentForm.processing ? 'Guardando...' : 'Confirmar Pago' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal: Registrar pago cuota -->
        <Teleport to="body">
            <div v-if="showInstallmentModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="showInstallmentModal = false">
                <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
                    <h4 class="font-bold text-gray-900 mb-1">Registrar Pago</h4>
                    <p class="text-sm text-gray-500 mb-4">Cuota #{{ selectedInstallment?.number }} — <strong>{{ fmt(selectedInstallment?.amount) }}</strong></p>
                    <form @submit.prevent="submitInstallment" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Metodo de Pago *</label>
                            <select v-model="installmentForm.payment_method"
                                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">Seleccionar...</option>
                                <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                            </select>
                            <p v-if="installmentForm.errors.payment_method" class="text-xs text-red-500 mt-1">{{ installmentForm.errors.payment_method }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Referencia (opcional)</label>
                            <input v-model="installmentForm.payment_reference" type="text" placeholder="Numero de transaccion..."
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Comprobante (opcional)</label>
                            <input type="file" accept=".jpg,.jpeg,.png,.pdf" @input="installmentForm.receipt = $event.target.files[0]"
                                class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notas (opcional)</label>
                            <textarea v-model="installmentForm.notes" rows="2"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10"></textarea>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showInstallmentModal = false"
                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
                            <button type="submit" :disabled="installmentForm.processing"
                                class="bg-green-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition disabled:opacity-50">
                                {{ installmentForm.processing ? 'Guardando...' : 'Confirmar Pago' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal: Subir recibo -->
        <Teleport to="body">
            <div v-if="showUploadModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="showUploadModal = false">
                <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
                    <h4 class="font-bold text-gray-900 mb-4">Subir Comprobante</h4>
                    <form @submit.prevent="submitUpload">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Archivo *</label>
                            <input type="file" accept=".jpg,.jpeg,.png,.pdf" @input="uploadForm.receipt = $event.target.files[0]"
                                class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200" />
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="showUploadModal = false"
                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
                            <button type="submit" :disabled="uploadForm.processing || !uploadForm.receipt"
                                class="bg-black text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-gray-800 transition disabled:opacity-50">
                                {{ uploadForm.processing ? 'Subiendo...' : 'Subir Comprobante' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
