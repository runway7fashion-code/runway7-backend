<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import { Bar, Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, ArcElement, Title, Tooltip, Legend } from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, Title, Tooltip, Legend);

const props = defineProps({
    stats: Object,
    events: Array,
    selectedEvent: Number,
});

const selectedEventId = ref(props.selectedEvent ?? '');

function filterByEvent() {
    const params = {};
    if (selectedEventId.value) params.event = selectedEventId.value;
    router.get('/admin/accounting/dashboard', params, { preserveState: true });
}

function fmt(n) {
    return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

// Gráfico barras - Cobro por mes
const monthLabels = computed(() => props.stats.by_month?.map(m => {
    const [y, mo] = m.month.split('-');
    const months = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    return months[parseInt(mo) - 1] + ' ' + y;
}) ?? []);

const barChartData = computed(() => ({
    labels: monthLabels.value,
    datasets: [
        {
            label: 'Pagado',
            data: props.stats.by_month?.map(m => m.paid) ?? [],
            backgroundColor: '#22c55e',
            borderRadius: 4,
        },
        {
            label: 'Pendiente',
            data: props.stats.by_month?.map(m => m.pending) ?? [],
            backgroundColor: '#d1d5db',
            borderRadius: 4,
        },
    ],
}));

const barChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } },
        tooltip: {
            callbacks: {
                label: (ctx) => ctx.dataset.label + ': $' + Number(ctx.raw).toLocaleString(),
            },
        },
    },
    scales: {
        x: { stacked: true, grid: { display: false } },
        y: { stacked: true, ticks: { callback: (v) => '$' + (v / 1000) + 'k' } },
    },
};

// Gráfico dona - Por paquete
const packageColors = ['#000000', '#D4AF37', '#6366f1', '#22c55e', '#f59e0b', '#ef4444'];

const doughnutData = computed(() => ({
    labels: props.stats.by_package?.map(p => p.package) ?? [],
    datasets: [{
        data: props.stats.by_package?.map(p => p.total) ?? [],
        backgroundColor: packageColors.slice(0, props.stats.by_package?.length ?? 0),
        borderWidth: 2,
        borderColor: '#fff',
    }],
}));

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16 } },
        tooltip: {
            callbacks: {
                label: (ctx) => ctx.label + ': $' + Number(ctx.raw).toLocaleString() + ' (' + ctx.dataset.data.length + ' planes)',
            },
        },
    },
};

// Gráfico barras horizontales - Progreso por paquete
const progressBarData = computed(() => ({
    labels: props.stats.by_package?.map(p => p.package) ?? [],
    datasets: [
        {
            label: 'Cobrado',
            data: props.stats.by_package?.map(p => p.collected) ?? [],
            backgroundColor: '#22c55e',
            borderRadius: 4,
        },
        {
            label: 'Pendiente',
            data: props.stats.by_package?.map(p => p.pending) ?? [],
            backgroundColor: '#e5e7eb',
            borderRadius: 4,
        },
    ],
}));

const progressBarOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } },
        tooltip: {
            callbacks: {
                label: (ctx) => ctx.dataset.label + ': $' + Number(ctx.raw).toLocaleString(),
            },
        },
    },
    scales: {
        x: { stacked: true, ticks: { callback: (v) => '$' + (v / 1000) + 'k' }, grid: { display: false } },
        y: { stacked: true, grid: { display: false } },
    },
};

function statusLabel(s) {
    return { active: 'Activo', completed: 'Completado', cancelled: 'Cancelado' }[s] ?? s;
}
function statusClass(s) {
    return { active: 'bg-blue-50 text-blue-700', completed: 'bg-green-50 text-green-700', cancelled: 'bg-red-50 text-red-600' }[s] ?? 'bg-gray-50 text-gray-600';
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h2 class="text-lg font-semibold text-gray-900">Dashboard Contabilidad</h2>
                <select v-model="selectedEventId" @change="filterByEvent"
                    class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                    <option value="">Todos los eventos</option>
                    <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Stat Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="rounded-xl p-6 bg-black text-white">
                    <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Total Esperado</p>
                    <p class="text-3xl font-bold">{{ fmt(stats.total_expected) }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ stats.plans_count }} planes</p>
                </div>
                <div class="rounded-xl p-6 bg-green-900 text-white">
                    <p class="text-xs uppercase tracking-widest text-green-300 mb-2">Total Cobrado</p>
                    <p class="text-3xl font-bold text-green-400">{{ fmt(stats.total_collected) }}</p>
                    <p class="text-xs text-green-300 mt-1">{{ stats.collection_percentage }}%</p>
                </div>
                <div class="rounded-xl p-6 border border-gray-200 bg-white">
                    <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Total Pendiente</p>
                    <p class="text-3xl font-bold" style="color: #D4AF37;">{{ fmt(stats.total_pending) }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ stats.plans_count - stats.completed_plans }} activos</p>
                </div>
                <div class="rounded-xl p-6 bg-red-900 text-white">
                    <p class="text-xs uppercase tracking-widest text-red-300 mb-2">Total Vencido</p>
                    <p class="text-3xl font-bold text-red-400">{{ fmt(stats.total_overdue) }}</p>
                </div>
            </div>

            <!-- Gráficos -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Cobro por mes -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="font-bold text-gray-900 mb-4">Cobro por Mes</h4>
                    <div class="h-72">
                        <Bar v-if="stats.by_month?.length" :data="barChartData" :options="barChartOptions" />
                        <p v-else class="text-sm text-gray-400 italic pt-20 text-center">Sin datos de cuotas.</p>
                    </div>
                </div>

                <!-- Por paquete (dona) -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="font-bold text-gray-900 mb-4">Distribucion por Paquete</h4>
                    <div class="h-72">
                        <Doughnut v-if="stats.by_package?.length" :data="doughnutData" :options="doughnutOptions" />
                        <p v-else class="text-sm text-gray-400 italic pt-20 text-center">Sin datos de paquetes.</p>
                    </div>
                </div>
            </div>

            <!-- Progreso por paquete -->
            <div v-if="stats.by_package?.length" class="bg-white rounded-2xl border border-gray-200 p-6">
                <h4 class="font-bold text-gray-900 mb-4">Progreso de Cobro por Paquete</h4>
                <div :style="`height: ${Math.max(stats.by_package.length * 50, 150)}px`">
                    <Bar :data="progressBarData" :options="progressBarOptions" />
                </div>
            </div>

            <!-- Tabla planes recientes -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h4 class="font-bold text-gray-900 mb-4">Planes de Pago Recientes</h4>

                <div v-if="stats.recent_plans?.length === 0" class="text-sm text-gray-400 italic">Sin planes de pago registrados.</div>

                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100">
                            <th class="py-3 pr-4">Disenador</th>
                            <th class="py-3 pr-4">Brand</th>
                            <th class="py-3 pr-4">Paquete</th>
                            <th class="py-3 pr-4 text-right">Total</th>
                            <th class="py-3 pr-4 text-right">Pagado</th>
                            <th class="py-3 pr-4 text-right">Pendiente</th>
                            <th class="py-3 pr-4">Progreso</th>
                            <th class="py-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="plan in stats.recent_plans" :key="plan.id"
                            class="border-b border-gray-50 hover:bg-gray-50 cursor-pointer"
                            @click="$inertia?.visit?.(`/admin/accounting/payments/designer/${plan.designer_id}/event/${plan.event_id}`) ?? router.get(`/admin/accounting/payments/designer/${plan.designer_id}/event/${plan.event_id}`)">
                            <td class="py-3 pr-4 font-medium text-gray-900">{{ plan.designer_name }}</td>
                            <td class="py-3 pr-4 text-gray-500">{{ plan.brand }}</td>
                            <td class="py-3 pr-4 text-gray-500">{{ plan.package }}</td>
                            <td class="py-3 pr-4 text-right font-medium">{{ fmt(plan.total) }}</td>
                            <td class="py-3 pr-4 text-right text-green-600 font-medium">{{ fmt(plan.paid) }}</td>
                            <td class="py-3 pr-4 text-right" style="color: #D4AF37;">{{ fmt(plan.pending) }}</td>
                            <td class="py-3 pr-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all"
                                            :class="plan.progress === 100 ? 'bg-green-500' : plan.progress >= 50 ? 'bg-yellow-400' : 'bg-red-300'"
                                            :style="`width: ${plan.progress}%`"></div>
                                    </div>
                                    <span class="text-xs text-gray-500">{{ plan.progress }}%</span>
                                </div>
                            </td>
                            <td class="py-3">
                                <span :class="statusClass(plan.status)" class="px-2 py-0.5 rounded text-xs font-medium">
                                    {{ statusLabel(plan.status) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
