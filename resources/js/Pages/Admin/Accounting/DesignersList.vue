<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { ArrowDownTrayIcon, MagnifyingGlassIcon, InformationCircleIcon, CurrencyDollarIcon, XMarkIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    designers: Object,
    events: Array,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const eventId = ref(props.filters?.event_id ?? '');

function getFilterParams() {
    return {
        search: search.value || undefined,
        status: status.value || undefined,
        event_id: eventId.value || undefined,
    };
}

let searchTimeout = null;
function applyFilters() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/admin/accounting/designers-list', getFilterParams(), { preserveState: true, replace: true });
    }, 300);
}

watch([status, eventId], () => {
    clearTimeout(searchTimeout);
    router.get('/admin/accounting/designers-list', getFilterParams(), { preserveState: true, replace: true });
});

function fmt(n) {
    return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function statusLabel(s) {
    return { active: 'Activo', inactive: 'Inactivo', pending: 'Pendiente' }[s] ?? s;
}

function statusClass(s) {
    return {
        active: 'bg-green-50 text-green-700',
        inactive: 'bg-gray-100 text-gray-500',
        pending: 'bg-yellow-50 text-yellow-700',
    }[s] ?? 'bg-gray-50 text-gray-600';
}

function exportCsv() {
    const params = new URLSearchParams();
    if (search.value) params.set('search', search.value);
    if (status.value) params.set('status', status.value);
    if (eventId.value) params.set('event_id', eventId.value);
    const qs = params.toString();
    window.open(`/admin/accounting/designers-list/export${qs ? '?' + qs : ''}`, '_blank');
}

// --- Modal Info ---
const showInfoModal = ref(false);
const infoLoading = ref(false);
const infoData = ref(null);

function openInfoModal(designerId) {
    infoLoading.value = true;
    infoData.value = null;
    showInfoModal.value = true;

    fetch(`/admin/accounting/api/designer-detail/${designerId}`)
        .then(r => r.json())
        .then(data => {
            infoData.value = data;
            infoLoading.value = false;
        })
        .catch(() => { infoLoading.value = false; });
}

// --- Modal Pagos ---
const showPaymentModal = ref(false);
const paymentLoading = ref(false);
const paymentData = ref(null);

function openPaymentModal(designerId) {
    paymentLoading.value = true;
    paymentData.value = null;
    showPaymentModal.value = true;

    fetch(`/admin/accounting/api/designer-detail/${designerId}`)
        .then(r => r.json())
        .then(data => {
            paymentData.value = data;
            paymentLoading.value = false;
        })
        .catch(() => { paymentLoading.value = false; });
}

function planStatusBadge(s) {
    return {
        pending: 'bg-yellow-50 text-yellow-700',
        partial: 'bg-blue-50 text-blue-700',
        paid: 'bg-green-50 text-green-700',
        overdue: 'bg-red-50 text-red-600',
        active: 'bg-blue-50 text-blue-700',
        completed: 'bg-green-50 text-green-700',
    }[s] ?? 'bg-gray-50 text-gray-600';
}

function planStatusLabel(s) {
    return {
        pending: 'Pendiente',
        partial: 'Parcial',
        paid: 'Pagado',
        overdue: 'Vencido',
        active: 'Activo',
        completed: 'Completado',
    }[s] ?? s;
}

function methodLabel(m) {
    return {
        wire_transfer: 'Transferencia',
        venmo: 'Venmo',
        zelle: 'Zelle',
        cash: 'Efectivo',
        check: 'Cheque',
        stripe: 'Stripe',
        other: 'Otro',
    }[m] ?? m ?? '—';
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Diseñadores</h2>
        </template>

        <div class="space-y-6">
            <!-- Header con contador y exportar -->
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">{{ designers.total }} diseñadores</p>
                <button @click="exportCsv"
                    class="flex items-center gap-2 border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                    <ArrowDownTrayIcon class="w-4 h-4" />
                    Exportar CSV
                </button>
            </div>

            <!-- Filtros -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <input v-model="search" @input="applyFilters" type="text" placeholder="Buscar por marca, nombre o email..."
                                class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>
                    <div class="md:w-56">
                        <select v-model="eventId"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">Todos los eventos</option>
                            <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                        </select>
                    </div>
                    <div class="md:w-48">
                        <select v-model="status"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">Todos los estados</option>
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                            <option value="pending">Pendiente</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tabla -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50/50">
                            <th class="py-3 px-4">Diseñador</th>
                            <th class="py-3 px-4">Estado</th>
                            <th class="py-3 px-4">Evento</th>
                            <th class="py-3 px-4">Rep. Ventas</th>
                            <th class="py-3 px-4">Paquete</th>
                            <th class="py-3 px-4 text-right">Monto Paquete</th>
                            <th class="py-3 px-4 text-right">Monto Pendiente</th>
                            <th class="py-3 px-4 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in designers.data" :key="d.id"
                            class="border-b border-gray-50 hover:bg-gray-50/50 transition">
                            <td class="py-3 px-4">
                                <p class="font-semibold text-gray-900">{{ d.designer_profile?.brand_name ?? '—' }}</p>
                                <p class="text-gray-500 text-xs">{{ d.first_name }} {{ d.last_name }}</p>
                            </td>
                            <td class="py-3 px-4">
                                <span :class="statusClass(d.status)" class="px-2 py-0.5 rounded text-xs font-medium">{{ statusLabel(d.status) }}</span>
                            </td>
                            <td class="py-3 px-4 text-gray-500 text-xs">{{ d.event_name ?? '—' }}</td>
                            <td class="py-3 px-4 text-gray-500">{{ d.designer_profile?.sales_rep ? d.designer_profile.sales_rep.first_name + ' ' + d.designer_profile.sales_rep.last_name : '—' }}</td>
                            <td class="py-3 px-4 text-gray-500">{{ d.current_package?.name ?? '—' }}</td>
                            <td class="py-3 px-4 text-right font-medium">{{ fmt(d.package_price) }}</td>
                            <td class="py-3 px-4 text-right font-medium" :class="d.amount_pending > 0 ? 'text-red-600' : 'text-green-600'">
                                {{ fmt(d.amount_pending) }}
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center justify-center gap-2">
                                    <button @click="openInfoModal(d.id)"
                                        class="border border-gray-300 text-gray-600 p-1.5 rounded-lg hover:bg-gray-100 transition" title="Ver Info">
                                        <InformationCircleIcon class="w-4 h-4" />
                                    </button>
                                    <button @click="openPaymentModal(d.id)"
                                        class="border border-gray-300 text-gray-600 p-1.5 rounded-lg hover:bg-gray-100 transition" title="Ver Pagos">
                                        <CurrencyDollarIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="!designers.data.length" class="p-12 text-center">
                    <p class="text-sm text-gray-400 italic">No se encontraron diseñadores.</p>
                </div>

                <!-- Paginación -->
                <div v-if="designers.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <p class="text-xs text-gray-500">Mostrando {{ designers.from }}–{{ designers.to }} de {{ designers.total }}</p>
                    <div class="flex gap-1">
                        <Link v-for="link in designers.links" :key="link.label"
                            :href="link.url || ''"
                            :class="[
                                'px-3 py-1.5 text-xs rounded-lg transition',
                                link.active ? 'bg-black text-white' : link.url ? 'text-gray-600 hover:bg-gray-100' : 'text-gray-300 pointer-events-none'
                            ]"
                            v-html="link.label"
                            preserve-state />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Info del Diseñador -->
        <Teleport to="body">
            <div v-if="showInfoModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="showInfoModal = false">
                <div class="bg-white rounded-2xl p-6 w-full max-w-lg shadow-xl max-h-[85vh] overflow-y-auto">
                    <!-- Loading -->
                    <div v-if="infoLoading" class="py-12 text-center">
                        <div class="inline-block w-6 h-6 border-2 border-gray-300 border-t-black rounded-full animate-spin"></div>
                        <p class="text-sm text-gray-400 mt-2">Cargando...</p>
                    </div>

                    <!-- Contenido -->
                    <template v-else-if="infoData">
                        <div class="flex items-center justify-between mb-5">
                            <h4 class="font-bold text-gray-900 text-lg">Info del Diseñador</h4>
                            <button @click="showInfoModal = false" class="text-gray-400 hover:text-gray-600">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>

                        <!-- Header -->
                        <div class="flex items-center gap-3 mb-5">
                            <div v-if="infoData.designer.profile_picture"
                                class="w-14 h-14 rounded-full bg-cover bg-center border-2 border-gray-200 flex-shrink-0"
                                :style="`background-image: url('/storage/${infoData.designer.profile_picture}')`"></div>
                            <div v-else class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center text-lg font-bold text-gray-400 flex-shrink-0">
                                {{ (infoData.designer.first_name?.[0] ?? '') + (infoData.designer.last_name?.[0] ?? '') }}
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">{{ infoData.designer.first_name }} {{ infoData.designer.last_name }}</h3>
                                <p v-if="infoData.designer.brand_name" class="text-sm text-gray-500">{{ infoData.designer.brand_name }}</p>
                            </div>
                            <span :class="statusClass(infoData.designer.status)" class="ml-auto px-2.5 py-1 rounded-lg text-xs font-semibold">
                                {{ statusLabel(infoData.designer.status) }}
                            </span>
                        </div>

                        <!-- Grid de datos -->
                        <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div>
                                <span class="text-gray-400 text-xs">Email</span>
                                <p class="text-gray-800">{{ infoData.designer.email }}</p>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">Telefono</span>
                                <p class="text-gray-800">{{ infoData.designer.phone || '—' }}</p>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">Categoria</span>
                                <p class="text-gray-800">{{ infoData.designer.category || '—' }}</p>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">Pais</span>
                                <p class="text-gray-800">{{ infoData.designer.country || '—' }}</p>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">Representante de Ventas</span>
                                <p class="text-gray-800">{{ infoData.designer.sales_rep?.name || '—' }}</p>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">Evento Asignado</span>
                                <p class="text-gray-800">{{ infoData.event?.name || '—' }}</p>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">Paquete</span>
                                <p class="text-gray-800">{{ infoData.package?.name || '—' }}</p>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">Monto Paquete</span>
                                <p class="text-gray-800 font-medium">{{ fmt(infoData.event?.package_price) }}</p>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">Looks</span>
                                <p class="text-gray-800">{{ infoData.event?.looks ?? '—' }}</p>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">Casting</span>
                                <p class="text-gray-800">{{ infoData.event?.model_casting_enabled ? 'Si' : 'No' }}</p>
                            </div>
                        </div>

                        <!-- Redes sociales -->
                        <div v-if="infoData.designer.social_media && Object.values(infoData.designer.social_media).some(v => v)" class="mt-4 pt-4 border-t border-gray-100">
                            <p class="text-xs text-gray-400 mb-2">Redes Sociales</p>
                            <div class="flex flex-wrap gap-2">
                                <span v-if="infoData.designer.social_media.instagram" class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-xs">IG: {{ infoData.designer.social_media.instagram }}</span>
                                <span v-if="infoData.designer.social_media.facebook" class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-xs">FB: {{ infoData.designer.social_media.facebook }}</span>
                                <span v-if="infoData.designer.social_media.tiktok" class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-xs">TT: {{ infoData.designer.social_media.tiktok }}</span>
                                <span v-if="infoData.designer.social_media.website" class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-xs">Web: {{ infoData.designer.social_media.website }}</span>
                            </div>
                        </div>

                        <div class="flex justify-end mt-5">
                            <button @click="showInfoModal = false"
                                class="px-5 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition">
                                Cerrar
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </Teleport>

        <!-- Modal: Ver Pagos -->
        <Teleport to="body">
            <div v-if="showPaymentModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="showPaymentModal = false">
                <div class="bg-white rounded-2xl p-6 w-full max-w-2xl shadow-xl max-h-[85vh] overflow-y-auto">
                    <!-- Loading -->
                    <div v-if="paymentLoading" class="py-12 text-center">
                        <div class="inline-block w-6 h-6 border-2 border-gray-300 border-t-black rounded-full animate-spin"></div>
                        <p class="text-sm text-gray-400 mt-2">Cargando...</p>
                    </div>

                    <!-- Contenido -->
                    <template v-else-if="paymentData">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <h4 class="font-bold text-gray-900 text-lg">Pagos — {{ paymentData.designer.first_name }} {{ paymentData.designer.last_name }}</h4>
                                <p v-if="paymentData.designer.brand_name" class="text-sm text-gray-500">{{ paymentData.designer.brand_name }}</p>
                            </div>
                            <button @click="showPaymentModal = false" class="text-gray-400 hover:text-gray-600">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>

                        <!-- CON plan de pagos -->
                        <template v-if="paymentData.payment_plan">
                            <!-- Barra de progreso -->
                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-gray-500">{{ paymentData.payment_plan.progress }}% completado</span>
                                    <span :class="planStatusBadge(paymentData.payment_plan.status)" class="px-2.5 py-0.5 rounded-lg text-xs font-semibold">
                                        {{ planStatusLabel(paymentData.payment_plan.status) }}
                                    </span>
                                </div>
                                <div class="w-full h-2.5 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all"
                                        :class="paymentData.payment_plan.progress === 100 ? 'bg-green-500' : paymentData.payment_plan.progress >= 50 ? 'bg-yellow-400' : 'bg-red-400'"
                                        :style="`width: ${paymentData.payment_plan.progress}%`"></div>
                                </div>
                            </div>

                            <!-- Resumen -->
                            <div class="grid grid-cols-4 gap-3 mb-5">
                                <div class="bg-gray-50 rounded-xl p-3 text-center">
                                    <p class="text-[10px] text-gray-400 uppercase tracking-widest mb-0.5">Total</p>
                                    <p class="text-lg font-bold">{{ fmt(paymentData.payment_plan.total_amount) }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-3 text-center">
                                    <p class="text-[10px] text-gray-400 uppercase tracking-widest mb-0.5">Downpayment</p>
                                    <p class="text-lg font-bold">{{ fmt(paymentData.payment_plan.downpayment) }}</p>
                                    <span :class="planStatusBadge(paymentData.payment_plan.downpayment_status)" class="text-[10px] px-1.5 py-0.5 rounded font-medium">
                                        {{ planStatusLabel(paymentData.payment_plan.downpayment_status) }}
                                    </span>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-3 text-center">
                                    <p class="text-[10px] text-gray-400 uppercase tracking-widest mb-0.5">Pagado</p>
                                    <p class="text-lg font-bold text-green-600">{{ fmt(paymentData.payment_plan.total_paid) }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-3 text-center">
                                    <p class="text-[10px] text-gray-400 uppercase tracking-widest mb-0.5">Pendiente</p>
                                    <p class="text-lg font-bold" style="color: #D4AF37;">{{ fmt(paymentData.payment_plan.total_pending) }}</p>
                                </div>
                            </div>

                            <!-- Tabla de cuotas -->
                            <div class="border border-gray-200 rounded-xl overflow-hidden">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs text-gray-500 uppercase tracking-wider bg-gray-50 border-b border-gray-100">
                                            <th class="py-2.5 px-3">#</th>
                                            <th class="py-2.5 px-3">Monto</th>
                                            <th class="py-2.5 px-3">Fecha Limite</th>
                                            <th class="py-2.5 px-3">Estado</th>
                                            <th class="py-2.5 px-3">Metodo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="inst in paymentData.payment_plan.installments" :key="inst.id" class="border-b border-gray-50">
                                            <td class="py-2.5 px-3 font-medium">{{ inst.number }}</td>
                                            <td class="py-2.5 px-3 font-medium">
                                                <template v-if="inst.paid_amount > 0 && inst.status !== 'paid'">
                                                    <span class="text-blue-600">{{ fmt(inst.paid_amount) }}</span>
                                                    <span class="text-gray-400"> / {{ fmt(inst.amount) }}</span>
                                                </template>
                                                <template v-else>{{ fmt(inst.amount) }}</template>
                                            </td>
                                            <td class="py-2.5 px-3 text-gray-500">{{ inst.due_date }}</td>
                                            <td class="py-2.5 px-3">
                                                <span :class="planStatusBadge(inst.status)" class="px-2 py-0.5 rounded text-xs font-medium">
                                                    {{ planStatusLabel(inst.status) }}
                                                </span>
                                            </td>
                                            <td class="py-2.5 px-3 text-gray-500 text-xs">{{ methodLabel(inst.payment_method) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <!-- SIN plan de pagos -->
                        <template v-else>
                            <div class="py-8 text-center">
                                <DocumentTextIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                                <p class="text-sm text-gray-500 mb-4">Este diseñador no tiene un plan de pagos registrado.</p>
                                <Link v-if="paymentData.event" :href="`/admin/accounting/payments/designer/${paymentData.designer.id}/event/${paymentData.event.id}`"
                                    class="inline-flex items-center gap-2 bg-black text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-800 transition">
                                    Ir a Pagos Diseñadores
                                </Link>
                                <Link v-else href="/admin/accounting/payments"
                                    class="inline-flex items-center gap-2 bg-black text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-800 transition">
                                    Ir a Pagos Diseñadores
                                </Link>
                            </div>
                        </template>

                        <div class="flex justify-end mt-5">
                            <button @click="showPaymentModal = false"
                                class="px-5 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition">
                                Cerrar
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
