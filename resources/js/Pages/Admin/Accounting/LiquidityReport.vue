<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { ArrowDownTrayIcon, CheckCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    dates: Array,
    filters: Object,
    events: Array,
    totals: Object,
});

const dateFrom = ref(props.filters.date_from);
const dateTo = ref(props.filters.date_to);
const eventId = ref(props.filters.event_id ?? '');
const statusFilter = ref(props.filters.status ?? '');

function getParams() {
    return {
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        event_id: eventId.value || undefined,
        status: statusFilter.value || undefined,
    };
}

let timer = null;
function applyFilters() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/admin/accounting/liquidity', getParams(), { preserveState: true, replace: true });
    }, 300);
}

watch([eventId, statusFilter], () => {
    clearTimeout(timer);
    router.get('/admin/accounting/liquidity', getParams(), { preserveState: true, replace: true });
});

function fmt(n) {
    return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDate(d) {
    if (!d) return '—';
    return new Date(d + 'T00:00:00').toLocaleDateString('es-US', { day: '2-digit', month: 'short', year: 'numeric' });
}

function isToday(d) {
    return d === new Date().toISOString().slice(0, 10);
}

function statusLabel(row) {
    if (isToday(row.date)) return 'Hoy';
    if (row.is_overdue) return 'Vencido';
    return 'Pendiente';
}

function statusBadge(row) {
    if (isToday(row.date)) return 'bg-yellow-600/10 text-yellow-700 border border-yellow-300';
    if (row.is_overdue) return 'bg-red-100 text-red-700';
    return 'bg-yellow-100 text-yellow-700';
}

// Detail modal
const showModal = ref(false);
const modalRow = ref(null);

function openDetail(row) {
    modalRow.value = row;
    showModal.value = true;
}

function detailStatusBadge(s) {
    const map = {
        overdue: 'bg-red-100 text-red-700',
        partial: 'bg-blue-100 text-blue-700',
        pending: 'bg-yellow-100 text-yellow-700',
    };
    return map[s] || 'bg-gray-100 text-gray-600';
}

function detailStatusLabel(s) {
    const map = { overdue: 'Vencida', partial: 'Parcial', pending: 'Pendiente' };
    return map[s] || s;
}

// Totals row
function totalDesigners() {
    const ids = new Set();
    (props.dates || []).forEach(row => {
        (row.designers || []).forEach(d => ids.add(d.designer_id));
    });
    return ids.size;
}

function totalInstallments() {
    return (props.dates || []).reduce((acc, row) => acc + row.installments_count, 0);
}

// Export
function exportCsv() {
    const params = new URLSearchParams();
    if (dateFrom.value) params.set('date_from', dateFrom.value);
    if (dateTo.value) params.set('date_to', dateTo.value);
    if (eventId.value) params.set('event_id', eventId.value);
    if (statusFilter.value) params.set('status', statusFilter.value);
    const qs = params.toString();
    window.location.href = '/admin/accounting/liquidity/export' + (qs ? '?' + qs : '');
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Reporte de Liquidez</h2>
                <p class="text-sm text-gray-500 mt-0.5">Proyeccion de ingresos por cuotas</p>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Header row with export -->
            <div class="flex items-center justify-end">
                <button @click="exportCsv"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    <ArrowDownTrayIcon class="w-4 h-4 mr-2" />
                    Exportar CSV
                </button>
            </div>

            <!-- Summary cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Pendiente en Rango</p>
                    <p class="mt-2 text-2xl font-bold" style="color: #D4AF37;">{{ fmt(totals.total_pending) }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Ingresos Proyectados</p>
                    <p class="mt-2 text-2xl font-bold text-green-600">{{ fmt(totals.total_upcoming) }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Deuda Vencida</p>
                    <p class="mt-2 text-2xl font-bold text-red-600">{{ fmt(totals.total_overdue) }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-500">Desde</label>
                    <input v-model="dateFrom" @change="applyFilters" type="date"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black" />
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-500">Hasta</label>
                    <input v-model="dateTo" @change="applyFilters" type="date"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black" />
                </div>
                <select v-model="eventId" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black">
                    <option value="">Todos los eventos</option>
                    <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                </select>
                <select v-model="statusFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black">
                    <option value="">Todos los estados</option>
                    <option value="overdue">Vencido</option>
                    <option value="pending">Pendiente</option>
                </select>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table v-if="dates.length" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Fecha</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Monto Pendiente</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Disenadores</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Cuotas</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <tr v-for="row in dates" :key="row.date" class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ fmtDate(row.date) }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="text-sm font-semibold text-gray-900">
                                    {{ fmt(row.total_pending) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-gray-600">
                                {{ row.designers_count }} disenador{{ row.designers_count !== 1 ? 'es' : '' }}
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-gray-600">
                                {{ row.installments_count }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="statusBadge(row)" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                                    {{ statusLabel(row) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button @click="openDetail(row)"
                                    class="text-sm font-medium hover:underline" style="color: #D4AF37;">
                                    Ver Detalle
                                </button>
                            </td>
                        </tr>

                        <!-- Totals row -->
                        <tr class="bg-gray-50 font-semibold">
                            <td class="px-4 py-3 text-sm text-gray-900 uppercase">Total</td>
                            <td class="px-4 py-3 text-right text-sm text-gray-900">{{ fmt(totals.total_pending) }}</td>
                            <td class="px-4 py-3 text-center text-sm text-gray-900">{{ totalDesigners() }}</td>
                            <td class="px-4 py-3 text-center text-sm text-gray-900">{{ totalInstallments() }}</td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3"></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Empty state -->
                <div v-else class="px-8 py-16 text-center">
                    <CheckCircleIcon class="mx-auto h-12 w-12 text-green-400" />
                    <p class="mt-4 text-gray-700 text-sm font-medium">No hay cuotas pendientes en este periodo</p>
                    <p class="mt-1 text-gray-400 text-xs">Selecciona otro rango de fechas para ver proyecciones</p>
                </div>
            </div>
        </div>

        <!-- Detail modal -->
        <Teleport to="body">
            <div v-if="showModal && modalRow" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="fixed inset-0 bg-black/50" @click="showModal = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-3xl w-full mx-4 max-h-[85vh] flex flex-col">
                    <!-- Modal header -->
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-3">
                                <h3 class="text-lg font-semibold text-gray-900">Cuotas del {{ fmtDate(modalRow.date) }}</h3>
                                <span :class="statusBadge(modalRow)" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                                    {{ statusLabel(modalRow) }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">Monto total: <strong>{{ fmt(modalRow.total_pending) }}</strong></p>
                        </div>
                        <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Modal body -->
                    <div class="px-6 py-4 overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Disenador</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Marca</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Evento</th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase">Cuota #</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Monto</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Pagado</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Pendiente</th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(d, idx) in modalRow.designers" :key="idx" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-sm text-gray-900">{{ d.designer_name }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-700">{{ d.brand_name }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-700">{{ d.event_name }}</td>
                                    <td class="px-3 py-2 text-center text-sm text-gray-600">#{{ d.installment_number }}</td>
                                    <td class="px-3 py-2 text-right text-sm text-gray-900">{{ fmt(d.amount) }}</td>
                                    <td class="px-3 py-2 text-right text-sm" :class="d.paid_amount > 0 ? 'text-blue-600' : 'text-gray-400'">
                                        <template v-if="d.status === 'partial'">{{ fmt(d.paid_amount) }} / {{ fmt(d.amount) }}</template>
                                        <template v-else>{{ fmt(d.paid_amount) }}</template>
                                    </td>
                                    <td class="px-3 py-2 text-right text-sm font-semibold text-gray-900">{{ fmt(d.pending) }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <span :class="detailStatusBadge(d.status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                                            {{ detailStatusLabel(d.status) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Modal footer -->
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
                        <button @click="showModal = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
