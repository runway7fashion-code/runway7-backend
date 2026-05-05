<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed } from 'vue';
import { Bar, Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS, CategoryScale, LinearScale, BarElement, ArcElement,
    Title, Tooltip, Legend,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, Title, Tooltip, Legend);

const props = defineProps({
    eventStats:     Object,
    participants:   Object,
    designerStatus: Object,
    onboarding:     Object,
    materials:      Object,
    fittings:       Object,
    passes:         Object,
    activeEvents:   Array,
    recent:         Object,
    monthly:        Array,
});

// ── Helpers ──────────────────────────────────────────────────────────
const pct = (a, b) => b > 0 ? Math.round((a / b) * 100) : 0;

const today = new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

// ── Doughnut: estado de designers ────────────────────────────────────
const doughnutData = computed(() => ({
    labels: ['Registered', 'Pending', 'Active', 'Inactive'],
    datasets: [{
        data: [
            props.designerStatus.registered,
            props.designerStatus.pending,
            props.designerStatus.active,
            props.designerStatus.inactive,
        ],
        backgroundColor: ['#3B82F6', '#F59E0B', '#10B981', '#EF4444'],
        borderWidth: 0,
    }],
}));

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 } } },
    },
};

// ── Bar: registros mensuales ──────────────────────────────────────────
const barData = computed(() => ({
    labels: props.monthly.map(m => m.label),
    datasets: [
        {
            label: 'Designers',
            data: props.monthly.map(m => m.designers),
            backgroundColor: '#D4AF37',
            borderRadius: 4,
        },
        {
            label: 'Models',
            data: props.monthly.map(m => m.models),
            backgroundColor: '#6B7280',
            borderRadius: 4,
        },
    ],
}));

const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 } } } },
    scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, grid: { color: '#F3F4F6' }, ticks: { stepSize: 1 } },
    },
};

// ── Status badge ─────────────────────────────────────────────────────
function statusBadge(status) {
    return {
        active:    'bg-green-100 text-green-700',
        published: 'bg-blue-100 text-blue-700',
        draft:     'bg-gray-100 text-gray-600',
    }[status] ?? 'bg-gray-100 text-gray-600';
}
function statusLabel(status) {
    return { active: 'Active', published: 'Published', draft: 'Draft' }[status] ?? status;
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Operations Dashboard</h2>
        </template>

        <div class="space-y-8">

            <!-- Header -->
            <div>
                <h3 class="text-2xl font-bold text-gray-900 mb-1">Operations Panel</h3>
                <p class="text-gray-500 capitalize">{{ today }}</p>
            </div>

            <!-- ── Events ──────────────────────────────────────────── -->
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Events</p>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div class="bg-white rounded-2xl p-5 border border-gray-200">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Total</p>
                        <p class="text-4xl font-bold text-gray-900">{{ eventStats.total }}</p>
                    </div>
                    <div class="bg-black text-white rounded-2xl p-5 border border-gray-800">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Active</p>
                        <p class="text-4xl font-bold text-green-400">{{ eventStats.active }}</p>
                    </div>
                    <div class="bg-white rounded-2xl p-5 border border-gray-200">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Published</p>
                        <p class="text-4xl font-bold text-blue-600">{{ eventStats.published }}</p>
                    </div>
                    <div class="bg-white rounded-2xl p-5 border border-gray-200">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Draft</p>
                        <p class="text-4xl font-bold text-gray-500">{{ eventStats.draft }}</p>
                    </div>
                    <div class="bg-white rounded-2xl p-5 border border-gray-200">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Completed</p>
                        <p class="text-4xl font-bold text-emerald-600">{{ eventStats.completed }}</p>
                    </div>
                    <div class="bg-white rounded-2xl p-5 border border-gray-200">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Cancelled</p>
                        <p class="text-4xl font-bold text-red-500">{{ eventStats.cancelled }}</p>
                    </div>
                </div>
            </div>

            <!-- ── Participants ────────────────────────────────────── -->
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Event Participants</p>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div v-for="(count, key) in participants" :key="key"
                        class="bg-white rounded-2xl p-5 border border-gray-200">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1 capitalize">{{ key }}</p>
                        <p class="text-4xl font-bold text-purple-500">{{ count }}</p>
                    </div>
                </div>
            </div>

            <!-- ── Operational metrics ───────────────────────────────── -->
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Operational Progress</p>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

                    <!-- Onboarding -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-200">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-sm font-semibold text-gray-700">Onboarding</p>
                            <span class="text-xs font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">
                                {{ pct(onboarding.sent, onboarding.total) }}%
                            </span>
                        </div>
                        <p class="text-3xl font-bold text-gray-900 mb-1">{{ onboarding.sent }}<span class="text-base font-normal text-gray-400">/{{ onboarding.total }}</span></p>
                        <p class="text-xs text-gray-400 mb-3">Emails sent</p>
                        <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-amber-400 rounded-full transition-all"
                                :style="`width: ${pct(onboarding.sent, onboarding.total)}%`"></div>
                        </div>
                        <p v-if="onboarding.pending > 0" class="text-xs text-amber-600 mt-2">
                            {{ onboarding.pending }} pending to send
                        </p>
                    </div>

                    <!-- Materials -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-200">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-sm font-semibold text-gray-700">Materials</p>
                            <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">
                                {{ pct(materials.completed, materials.total) }}%
                            </span>
                        </div>
                        <p class="text-3xl font-bold text-gray-900 mb-1">{{ materials.completed }}<span class="text-base font-normal text-gray-400">/{{ materials.total }}</span></p>
                        <p class="text-xs text-gray-400 mb-3">Sent / confirmed</p>
                        <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full transition-all"
                                :style="`width: ${pct(materials.completed, materials.total)}%`"></div>
                        </div>
                    </div>

                    <!-- Fittings -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-200">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-sm font-semibold text-gray-700">Fittings</p>
                            <span class="text-xs font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded-full">
                                {{ pct(fittings.assigned, fittings.total) }}%
                            </span>
                        </div>
                        <p class="text-3xl font-bold text-gray-900 mb-1">{{ fittings.assigned }}<span class="text-base font-normal text-gray-400">/{{ fittings.total }}</span></p>
                        <p class="text-xs text-gray-400 mb-3">Designers assigned</p>
                        <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-green-500 rounded-full transition-all"
                                :style="`width: ${pct(fittings.assigned, fittings.total)}%`"></div>
                        </div>
                    </div>

                    <!-- Passes & New (last 7 days) -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-200">
                        <p class="text-sm font-semibold text-gray-700 mb-3">Last 7 days</p>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">New designers</span>
                                <span class="text-sm font-bold text-[#D4AF37]">+{{ recent.designers }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">New models</span>
                                <span class="text-sm font-bold text-purple-600">+{{ recent.models }}</span>
                            </div>
                            <div class="border-t border-gray-100 pt-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-500">Passes issued</span>
                                    <span class="text-sm font-bold text-gray-700">{{ passes.total }}</span>
                                </div>
                                <div class="flex items-center justify-between mt-1">
                                    <span class="text-xs text-gray-500">Check-ins</span>
                                    <span class="text-sm font-bold text-green-600">{{ passes.checked_in }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ── Charts ────────────────────────────────────────────── -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- Doughnut: Designer status -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="font-bold text-gray-900 mb-1">Designer Status</h4>
                    <p class="text-xs text-gray-400 mb-4">Current distribution by status</p>
                    <div class="h-56">
                        <Doughnut :data="doughnutData" :options="doughnutOptions" />
                    </div>
                </div>

                <!-- Bar: Monthly registrations -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="font-bold text-gray-900 mb-1">Registrations by Month</h4>
                    <p class="text-xs text-gray-400 mb-4">Last 6 months</p>
                    <div class="h-56">
                        <Bar :data="barData" :options="barOptions" />
                    </div>
                </div>

            </div>

            <!-- ── Active events ────────────────────────────────────── -->
            <div v-if="activeEvents.length">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Active and Published Events</p>
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                <th class="text-left px-6 py-3">Event</th>
                                <th class="text-left px-4 py-3">Status</th>
                                <th class="text-left px-4 py-3">Dates</th>
                                <th class="text-center px-4 py-3">Designers</th>
                                <th class="text-center px-4 py-3">Models</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="event in activeEvents" :key="event.id"
                                class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-3 font-medium text-gray-900">
                                    <a :href="`/admin/operations/events/${event.id}`" class="hover:text-[#D4AF37] transition-colors">
                                        {{ event.name }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="statusBadge(event.status)"
                                        class="px-2 py-0.5 rounded-full text-xs font-medium">
                                        {{ statusLabel(event.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs">
                                    {{ event.start_date }} — {{ event.end_date }}
                                </td>
                                <td class="px-4 py-3 text-center font-semibold text-[#D4AF37]">{{ event.designers_count }}</td>
                                <td class="px-4 py-3 text-center font-semibold text-purple-600">{{ event.models_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div v-else class="bg-white rounded-2xl border border-gray-200 p-8 text-center text-sm text-gray-400 italic">
                No active or published events currently.
            </div>

        </div>
    </AdminLayout>
</template>
