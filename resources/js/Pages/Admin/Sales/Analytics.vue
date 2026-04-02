<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { Bar, Doughnut, Line } from 'vue-chartjs';
import {
    Chart as ChartJS, CategoryScale, LinearScale, BarElement, PointElement, LineElement,
    ArcElement, Title, Tooltip, Legend, Filler
} from 'chart.js';
import {
    ArrowDownTrayIcon, FunnelIcon, ArrowTrendingUpIcon, ArrowTrendingDownIcon,
    ClockIcon, UserGroupIcon, GlobeAltIcon, TagIcon,
} from '@heroicons/vue/24/outline';

ChartJS.register(CategoryScale, LinearScale, BarElement, PointElement, LineElement, ArcElement, Title, Tooltip, Legend, Filler);

const props = defineProps({
    kpis: Object,
    funnel: Array,
    pipeline: Array,
    sourceData: Array,
    advisorPerformance: Array,
    activityByDay: Array,
    activityByType: Array,
    countryData: Array,
    tagsData: Array,
    leadsOverTime: Array,
    events: Array,
    advisors: Array,
    filters: Object,
    isLeader: Boolean,
});

// Filters
const dateFrom = ref(props.filters.from);
const dateTo = ref(props.filters.to);
const selectedEvent = ref(props.filters.event || '');
const selectedAdvisor = ref(props.filters.advisor || '');

function applyFilters() {
    const params = {};
    if (dateFrom.value) params.from = dateFrom.value;
    if (dateTo.value) params.to = dateTo.value;
    if (selectedEvent.value) params.event = selectedEvent.value;
    if (selectedAdvisor.value) params.advisor = selectedAdvisor.value;
    router.get('/admin/sales/analytics', params, { preserveState: true, preserveScroll: true });
}

function resetFilters() {
    dateFrom.value = '';
    dateTo.value = '';
    selectedEvent.value = '';
    selectedAdvisor.value = '';
    router.get('/admin/sales/analytics');
}

function exportCsv() {
    const params = new URLSearchParams();
    if (dateFrom.value) params.set('from', dateFrom.value);
    if (dateTo.value) params.set('to', dateTo.value);
    if (selectedAdvisor.value) params.set('advisor', selectedAdvisor.value);
    window.location.href = `/admin/sales/analytics/export?${params.toString()}`;
}

// ── Chart configs ──

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
};

// Funnel chart
const funnelChart = computed(() => ({
    data: {
        labels: props.funnel.map(f => f.label),
        datasets: [{
            data: props.funnel.map(f => f.count),
            backgroundColor: props.funnel.map(f => f.color),
            borderRadius: 6,
            barPercentage: 0.7,
        }],
    },
    options: { ...chartOptions, plugins: { ...chartOptions.plugins, legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
}));

// Pipeline chart (horizontal bars)
const pipelineChart = computed(() => ({
    data: {
        labels: props.pipeline.map(p => p.label),
        datasets: [{
            data: props.pipeline.map(p => p.count),
            backgroundColor: props.pipeline.map(p => p.color),
            borderRadius: 6,
            barPercentage: 0.7,
        }],
    },
    options: { ...chartOptions, indexAxis: 'y', scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } },
}));

// Source doughnut
const sourceChart = computed(() => {
    const colors = ['#3B82F6', '#EF4444', '#F97316', '#EAB308', '#22C55E', '#10B981', '#8B5CF6', '#EC4899', '#14B8A6', '#6366F1', '#F43F5E', '#0EA5E9', '#84CC16'];
    return {
        data: {
            labels: (props.sourceData || []).map(s => s.label),
            datasets: [{
                data: (props.sourceData || []).map(s => s.total),
                backgroundColor: colors.slice(0, (props.sourceData || []).length),
                borderWidth: 0,
            }],
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 12, padding: 10, font: { size: 11 } } } } },
    };
});

// Activity line chart
const activityLineChart = computed(() => ({
    data: {
        labels: props.activityByDay.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }),
        datasets: [
            {
                label: 'Total',
                data: props.activityByDay.map(d => d.total),
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59,130,246,0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 2,
            },
            {
                label: 'Completed',
                data: props.activityByDay.map(d => d.completed),
                borderColor: '#10B981',
                backgroundColor: 'rgba(16,185,129,0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 2,
            },
        ],
    },
    options: {
        ...chartOptions,
        plugins: { legend: { display: true, position: 'top', labels: { boxWidth: 12, padding: 15 } } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
}));

// Leads over time line chart
const leadsLineChart = computed(() => ({
    data: {
        labels: props.leadsOverTime.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }),
        datasets: [{
            label: 'New Leads',
            data: props.leadsOverTime.map(d => d.total),
            borderColor: '#8B5CF6',
            backgroundColor: 'rgba(139,92,246,0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 3,
        }],
    },
    options: {
        ...chartOptions,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
}));

// Activity by type chart
const activityTypeChart = computed(() => ({
    data: {
        labels: (props.activityByType || []).map(a => a.label),
        datasets: [{
            data: (props.activityByType || []).map(a => a.total),
            backgroundColor: (props.activityByType || []).map(a => a.color),
            borderWidth: 0,
        }],
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } } } },
}));
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Lead Analytics</h2>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Filters -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
                        <input v-model="dateFrom" type="date" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
                        <input v-model="dateTo" type="date" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Event</label>
                        <select v-model="selectedEvent" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">All Events</option>
                            <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                        </select>
                    </div>
                    <div v-if="isLeader">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Advisor</label>
                        <select v-model="selectedAdvisor" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">All Advisors</option>
                            <option v-for="adv in advisors" :key="adv.id" :value="adv.id">{{ adv.first_name }} {{ adv.last_name }}</option>
                        </select>
                    </div>
                    <button @click="applyFilters" class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors">
                        <FunnelIcon class="w-4 h-4 inline mr-1" /> Apply
                    </button>
                    <button @click="resetFilters" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                        Reset
                    </button>
                    <button @click="exportCsv" class="inline-flex items-center gap-1.5 px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors">
                        <ArrowDownTrayIcon class="w-4 h-4" /> Export CSV
                    </button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Leads (Period)</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ kpis.total_period }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Qualified</p>
                    <p class="text-2xl font-bold text-purple-600 mt-1">{{ kpis.qualified }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Conversion Rate</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1">{{ kpis.conversion_rate }}%</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider flex items-center gap-1"><ArrowTrendingUpIcon class="w-3.5 h-3.5" /> Won</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1">{{ kpis.opp_converted }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider flex items-center gap-1"><ArrowTrendingDownIcon class="w-3.5 h-3.5" /> Lost</p>
                    <p class="text-2xl font-bold text-red-500 mt-1">{{ kpis.opp_lost }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider flex items-center gap-1"><ClockIcon class="w-3.5 h-3.5" /> Avg. Days</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ kpis.avg_conversion_days ?? '—' }}</p>
                </div>
            </div>

            <!-- Leads Over Time + Funnel -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Leads Over Time -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37] mb-4">New Leads Over Time</h3>
                    <div class="h-64">
                        <Line v-if="leadsOverTime.length" :data="leadsLineChart.data" :options="leadsLineChart.options" />
                        <div v-else class="h-full flex items-center justify-center text-gray-400 text-sm">No data for this period</div>
                    </div>
                </div>

                <!-- Lead Funnel -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37] mb-4">Lead Funnel (All Time)</h3>
                    <div class="h-64">
                        <Bar :data="funnelChart.data" :options="funnelChart.options" />
                    </div>
                </div>
            </div>

            <!-- Pipeline + Source -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Opportunity Pipeline -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37] mb-4">Opportunity Pipeline</h3>
                    <div class="h-64">
                        <Bar :data="pipelineChart.data" :options="pipelineChart.options" />
                    </div>
                </div>

                <!-- Leads by Source -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37] mb-4">Leads by Source</h3>
                    <div class="h-64">
                        <Doughnut v-if="sourceData?.length" :data="sourceChart.data" :options="sourceChart.options" />
                        <div v-else class="h-full flex items-center justify-center text-gray-400 text-sm">No source data</div>
                    </div>
                </div>
            </div>

            <!-- Source Table -->
            <div v-if="sourceData?.length" class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Source Performance</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Source</th>
                                <th class="px-6 py-3 text-center font-medium text-gray-500 uppercase tracking-wider text-xs">Leads</th>
                                <th class="px-6 py-3 text-center font-medium text-gray-500 uppercase tracking-wider text-xs">Converted</th>
                                <th class="px-6 py-3 text-center font-medium text-gray-500 uppercase tracking-wider text-xs">Conv. Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="s in sourceData" :key="s.source" class="hover:bg-gray-50">
                                <td class="px-6 py-3 font-medium text-gray-900">{{ s.label }}</td>
                                <td class="px-6 py-3 text-center text-gray-700">{{ s.total }}</td>
                                <td class="px-6 py-3 text-center text-emerald-600 font-medium">{{ s.converted }}</td>
                                <td class="px-6 py-3 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium" :class="s.rate >= 20 ? 'bg-emerald-100 text-emerald-700' : s.rate >= 10 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600'">
                                        {{ s.rate }}%
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Activity Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Activity Over Time -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37] mb-4">Activity Over Time</h3>
                    <div class="h-64">
                        <Line v-if="activityByDay.length" :data="activityLineChart.data" :options="activityLineChart.options" />
                        <div v-else class="h-full flex items-center justify-center text-gray-400 text-sm">No activities in this period</div>
                    </div>
                </div>

                <!-- Activity by Type -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37] mb-4">Activity Types</h3>
                    <div class="h-64">
                        <Doughnut v-if="activityByType?.length" :data="activityTypeChart.data" :options="activityTypeChart.options" />
                        <div v-else class="h-full flex items-center justify-center text-gray-400 text-sm">No data</div>
                    </div>
                </div>
            </div>

            <!-- Advisor Performance (leaders only) -->
            <div v-if="isLeader && advisorPerformance.length" class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
                    <UserGroupIcon class="w-5 h-5 text-gray-400" />
                    <h3 class="font-semibold text-gray-800">Advisor Performance</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Advisor</th>
                                <th class="px-6 py-3 text-center font-medium text-gray-500 uppercase tracking-wider text-xs">Leads (Period)</th>
                                <th class="px-6 py-3 text-center font-medium text-gray-500 uppercase tracking-wider text-xs">Total Leads</th>
                                <th class="px-6 py-3 text-center font-medium text-gray-500 uppercase tracking-wider text-xs">Clients</th>
                                <th class="px-6 py-3 text-center font-medium text-gray-500 uppercase tracking-wider text-xs">Activities</th>
                                <th class="px-6 py-3 text-center font-medium text-gray-500 uppercase tracking-wider text-xs">Completed</th>
                                <th class="px-6 py-3 text-center font-medium text-gray-500 uppercase tracking-wider text-xs">Conv. Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="adv in advisorPerformance" :key="adv.id" class="hover:bg-gray-50">
                                <td class="px-6 py-3 font-medium text-gray-900">{{ adv.name }}</td>
                                <td class="px-6 py-3 text-center text-gray-700">{{ adv.leads_period }}</td>
                                <td class="px-6 py-3 text-center text-gray-700">{{ adv.leads_total }}</td>
                                <td class="px-6 py-3 text-center text-emerald-600 font-medium">{{ adv.clients }}</td>
                                <td class="px-6 py-3 text-center text-gray-700">{{ adv.activities }}</td>
                                <td class="px-6 py-3 text-center text-gray-700">{{ adv.completed_activities }}</td>
                                <td class="px-6 py-3 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium" :class="adv.conversion_rate >= 20 ? 'bg-emerald-100 text-emerald-700' : adv.conversion_rate >= 10 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600'">
                                        {{ adv.conversion_rate }}%
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Country + Tags -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Leads by Country -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
                        <GlobeAltIcon class="w-5 h-5 text-gray-400" />
                        <h3 class="font-semibold text-gray-800">Top Countries</h3>
                    </div>
                    <div v-if="countryData?.length" class="divide-y divide-gray-100">
                        <div v-for="(c, i) in countryData" :key="c.country" class="px-6 py-2.5 flex items-center justify-between hover:bg-gray-50 text-sm">
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-gray-400 w-5 text-right">{{ i + 1 }}</span>
                                <span class="font-medium text-gray-900">{{ c.country }}</span>
                            </div>
                            <span class="text-gray-600 font-medium">{{ c.total }} leads</span>
                        </div>
                    </div>
                    <div v-else class="px-6 py-8 text-center text-gray-400 text-sm">No country data</div>
                </div>

                <!-- Tags Performance -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
                        <TagIcon class="w-5 h-5 text-gray-400" />
                        <h3 class="font-semibold text-gray-800">Tag Performance</h3>
                    </div>
                    <div v-if="tagsData?.length" class="divide-y divide-gray-100">
                        <div v-for="tag in tagsData" :key="tag.id" class="px-6 py-2.5 flex items-center justify-between hover:bg-gray-50 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: tag.color }"></span>
                                <span class="font-medium text-gray-900">{{ tag.name }}</span>
                            </div>
                            <span class="text-gray-600 font-medium">{{ tag.leads_count }} leads</span>
                        </div>
                    </div>
                    <div v-else class="px-6 py-8 text-center text-gray-400 text-sm">No tags created</div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
