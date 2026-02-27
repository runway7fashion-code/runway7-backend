<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    plans: Object,
    events: Array,
    stats: Object,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');
const eventId = ref(props.filters?.event_id ?? '');

function getFilterParams() {
    return {
        search: search.value || undefined,
        event_id: eventId.value || undefined,
    };
}

let searchTimeout = null;
function applyFilters() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/admin/accounting/overdue', getFilterParams(), { preserveState: true, replace: true });
    }, 300);
}

watch([eventId], () => {
    clearTimeout(searchTimeout);
    router.get('/admin/accounting/overdue', getFilterParams(), { preserveState: true, replace: true });
});

function fmt(n) {
    return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function urgencyClass(days) {
    if (days >= 60) return 'text-red-700 font-bold';
    if (days >= 30) return 'text-red-600 font-semibold';
    return 'text-orange-600';
}

function exportCsv() {
    const params = new URLSearchParams();
    if (search.value) params.set('search', search.value);
    if (eventId.value) params.set('event_id', eventId.value);
    const qs = params.toString();
    window.open(`/admin/accounting/overdue/export${qs ? '?' + qs : ''}`, '_blank');
}

// --- Modal detalle ---
const showDetailModal = ref(false);
const detailLoading = ref(false);
const detailData = ref(null);

function openDetailModal(designerId) {
    detailLoading.value = true;
    detailData.value = null;
    showDetailModal.value = true;

    fetch(`/admin/accounting/api/designer-detail/${designerId}`)
        .then(r => r.json())
        .then(data => {
            detailData.value = data;
            detailLoading.value = false;
        })
        .catch(() => { detailLoading.value = false; });
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
            <h2 class="text-lg font-semibold text-gray-900">Deudas</h2>
        </template>

        <div class="space-y-6">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-widest">Monto Vencido</p>
                            <p class="text-xl font-bold text-red-600">{{ fmt(stats.total_overdue_amount) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-widest">Disenadores</p>
                            <p class="text-xl font-bold text-gray-900">{{ stats.designers_with_overdue }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-yellow-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-widest">Cuotas Vencidas</p>
                            <p class="text-xl font-bold text-gray-900">{{ stats.overdue_installments_count }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-widest">Mas Antigua</p>
                            <p class="text-xl font-bold text-gray-900">{{ stats.oldest_overdue ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Header con contador y exportar -->
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">{{ plans.total }} disenadores con deuda</p>
                <button @click="exportCsv"
                    class="flex items-center gap-2 border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Exportar CSV
                </button>
            </div>

            <!-- Filtros -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input v-model="search" @input="applyFilters" type="text" placeholder="Buscar por marca o nombre del disenador..."
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
                </div>
            </div>

            <!-- Tabla -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50/50">
                            <th class="py-3 px-4">Disenador</th>
                            <th class="py-3 px-4">Evento</th>
                            <th class="py-3 px-4">Rep. Ventas</th>
                            <th class="py-3 px-4">Paquete</th>
                            <th class="py-3 px-4 text-center">Cuotas</th>
                            <th class="py-3 px-4 text-right">Monto Pendiente</th>
                            <th class="py-3 px-4 text-center">Dias Vencido</th>
                            <th class="py-3 px-4 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in plans.data" :key="item.plan_id"
                            class="border-b border-gray-50 hover:bg-gray-50/50 transition">
                            <td class="py-3 px-4">
                                <p class="font-semibold text-gray-900">{{ item.brand_name ?? '—' }}</p>
                                <p class="text-gray-500 text-xs">{{ item.designer_name }}</p>
                            </td>
                            <td class="py-3 px-4 text-gray-500 text-xs">{{ item.event_name ?? '—' }}</td>
                            <td class="py-3 px-4 text-gray-500">{{ item.sales_rep ?? '—' }}</td>
                            <td class="py-3 px-4 text-gray-500">{{ item.package_name }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="bg-red-50 text-red-600 px-2 py-0.5 rounded text-xs font-semibold">{{ item.overdue_count }}</span>
                            </td>
                            <td class="py-3 px-4 text-right font-medium text-red-600">{{ fmt(item.overdue_amount) }}</td>
                            <td class="py-3 px-4 text-center">
                                <span :class="urgencyClass(item.max_days_overdue)">{{ item.max_days_overdue }}d</span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center justify-center gap-2">
                                    <button @click="openDetailModal(item.designer_id)"
                                        class="border border-gray-300 text-gray-600 p-1.5 rounded-lg hover:bg-gray-100 transition" title="Ver Detalle">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                    <Link :href="`/admin/accounting/payments/designer/${item.designer_id}/event/${item.event_id}`"
                                        class="border border-gray-300 text-gray-600 p-1.5 rounded-lg hover:bg-gray-100 transition" title="Ir a Pagos">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="!plans.data.length" class="p-12 text-center">
                    <svg class="w-16 h-16 text-green-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm text-gray-500 font-medium">No hay cuotas vencidas</p>
                    <p class="text-xs text-gray-400 mt-1">Todos los pagos estan al dia.</p>
                </div>

                <!-- Paginacion -->
                <div v-if="plans.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <p class="text-xs text-gray-500">Mostrando {{ plans.from }}–{{ plans.to }} de {{ plans.total }}</p>
                    <div class="flex gap-1">
                        <Link v-for="link in plans.links" :key="link.label"
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

        <!-- Modal: Detalle del Disenador -->
        <Teleport to="body">
            <div v-if="showDetailModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="showDetailModal = false">
                <div class="bg-white rounded-2xl p-6 w-full max-w-2xl shadow-xl max-h-[85vh] overflow-y-auto">
                    <!-- Loading -->
                    <div v-if="detailLoading" class="py-12 text-center">
                        <div class="inline-block w-6 h-6 border-2 border-gray-300 border-t-black rounded-full animate-spin"></div>
                        <p class="text-sm text-gray-400 mt-2">Cargando...</p>
                    </div>

                    <!-- Contenido -->
                    <template v-else-if="detailData">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <h4 class="font-bold text-gray-900 text-lg">{{ detailData.designer.first_name }} {{ detailData.designer.last_name }}</h4>
                                <p v-if="detailData.designer.brand_name" class="text-sm text-gray-500">{{ detailData.designer.brand_name }}</p>
                            </div>
                            <button @click="showDetailModal = false" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <!-- Info basica -->
                        <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm mb-5">
                            <div>
                                <span class="text-gray-400 text-xs">Email</span>
                                <p class="text-gray-800">{{ detailData.designer.email }}</p>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">Telefono</span>
                                <p class="text-gray-800">{{ detailData.designer.phone || '—' }}</p>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">Evento</span>
                                <p class="text-gray-800">{{ detailData.event?.name || '—' }}</p>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">Paquete</span>
                                <p class="text-gray-800">{{ detailData.package?.name || '—' }} — {{ fmt(detailData.event?.package_price) }}</p>
                            </div>
                        </div>

                        <!-- CON plan de pagos -->
                        <template v-if="detailData.payment_plan">
                            <!-- Barra de progreso -->
                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-gray-500">{{ detailData.payment_plan.progress }}% completado</span>
                                    <span :class="planStatusBadge(detailData.payment_plan.status)" class="px-2.5 py-0.5 rounded-lg text-xs font-semibold">
                                        {{ planStatusLabel(detailData.payment_plan.status) }}
                                    </span>
                                </div>
                                <div class="w-full h-2.5 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all"
                                        :class="detailData.payment_plan.progress === 100 ? 'bg-green-500' : detailData.payment_plan.progress >= 50 ? 'bg-yellow-400' : 'bg-red-400'"
                                        :style="`width: ${detailData.payment_plan.progress}%`"></div>
                                </div>
                            </div>

                            <!-- Resumen -->
                            <div class="grid grid-cols-4 gap-3 mb-5">
                                <div class="bg-gray-50 rounded-xl p-3 text-center">
                                    <p class="text-[10px] text-gray-400 uppercase tracking-widest mb-0.5">Total</p>
                                    <p class="text-lg font-bold">{{ fmt(detailData.payment_plan.total_amount) }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-3 text-center">
                                    <p class="text-[10px] text-gray-400 uppercase tracking-widest mb-0.5">Downpayment</p>
                                    <p class="text-lg font-bold">{{ fmt(detailData.payment_plan.downpayment) }}</p>
                                    <span :class="planStatusBadge(detailData.payment_plan.downpayment_status)" class="text-[10px] px-1.5 py-0.5 rounded font-medium">
                                        {{ planStatusLabel(detailData.payment_plan.downpayment_status) }}
                                    </span>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-3 text-center">
                                    <p class="text-[10px] text-gray-400 uppercase tracking-widest mb-0.5">Pagado</p>
                                    <p class="text-lg font-bold text-green-600">{{ fmt(detailData.payment_plan.total_paid) }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-3 text-center">
                                    <p class="text-[10px] text-gray-400 uppercase tracking-widest mb-0.5">Pendiente</p>
                                    <p class="text-lg font-bold" style="color: #D4AF37;">{{ fmt(detailData.payment_plan.total_pending) }}</p>
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
                                        <tr v-for="inst in detailData.payment_plan.installments" :key="inst.id"
                                            class="border-b border-gray-50"
                                            :class="inst.status === 'overdue' ? 'bg-red-50/30' : ''">
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

                        <!-- SIN plan -->
                        <template v-else>
                            <div class="py-8 text-center">
                                <p class="text-sm text-gray-500">Este disenador no tiene un plan de pagos registrado.</p>
                            </div>
                        </template>

                        <div class="flex items-center justify-between mt-5">
                            <Link v-if="detailData.event" :href="`/admin/accounting/payments/designer/${detailData.designer.id}/event/${detailData.event.id}`"
                                class="text-sm font-medium hover:underline" style="color: #D4AF37;">
                                Ir a Pagos Disenadores
                            </Link>
                            <span v-else></span>
                            <button @click="showDetailModal = false"
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
