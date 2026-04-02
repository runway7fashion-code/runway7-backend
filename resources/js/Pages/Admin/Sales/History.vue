<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { Bar, Line, Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale, LinearScale, BarElement, LineElement,
    PointElement, ArcElement, Tooltip, Legend, Filler,
} from 'chart.js';

ChartJS.register(
    CategoryScale, LinearScale, BarElement, LineElement,
    PointElement, ArcElement, Tooltip, Legend, Filler,
);

const props = defineProps({
    kpis:              Object,
    monthly_regs:      Array,
    monthly_revenue:   Array,
    rep_ranking:       Array,
    status_dist:       Object,
    package_breakdown: Array,
    table:             Object,
    filters:           Object,
    available_years:   Array,
    available_events:  Array,
    available_reps:    Array,
});

// ── Filters ──────────────────────────────────────────────────────────────────
const year   = ref(props.filters.year   ?? new Date().getFullYear());
const event  = ref(props.filters.event  ?? '');
const rep    = ref(props.filters.rep    ?? '');
const search = ref(props.filters.search ?? '');

let searchTimer = null;
watch(search, (val) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 700);
});

function applyFilters() {
    router.get('/admin/sales/history', {
        year:   year.value,
        event:  event.value  || undefined,
        rep:    rep.value    || undefined,
        search: search.value || undefined,
    }, { preserveScroll: true, replace: true });
}

function exportCsv() {
    const params = new URLSearchParams({ year: year.value });
    if (event.value) params.set('event', event.value);
    if (rep.value)   params.set('rep', rep.value);
    window.location.href = '/admin/sales/history/export?' + params.toString();
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmt(val) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0 }).format(val || 0);
}

function statusBadge(s) {
    return {
        registered: 'bg-blue-100 text-blue-700',
        onboarded:  'bg-purple-100 text-purple-700',
        confirmed:  'bg-green-100 text-green-700',
        cancelled:  'bg-red-100 text-red-600',
    }[s] ?? 'bg-gray-100 text-gray-600';
}

function statusLabel(s) {
    return { registered: 'Registered', onboarded: 'Onboarded', confirmed: 'Confirmed', cancelled: 'Cancelled' }[s] ?? s;
}

const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// ── Chart: Registrations per month ───────────────────────────────────────────
const barRegsData = computed(() => ({
    labels: months,
    datasets: [{
        label: 'Registrations',
        data: props.monthly_regs,
        backgroundColor: props.monthly_regs.map((_, i) =>
            i === props.monthly_regs.indexOf(Math.max(...props.monthly_regs)) ? '#D4AF37' : '#000000'
        ),
        borderRadius: 6,
        borderSkipped: false,
    }],
}));

const barRegsOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { callbacks: {
        label: ctx => ` ${ctx.parsed.y} registrations`,
    }}},
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
        y: { grid: { color: '#f0f0f0' }, ticks: { stepSize: 1, font: { size: 11 } }, beginAtZero: true },
    },
};

// ── Chart: Revenue per month ──────────────────────────────────────────────────
const lineRevData = computed(() => ({
    labels: months,
    datasets: [{
        label: 'Revenue',
        data: props.monthly_revenue,
        borderColor: '#D4AF37',
        backgroundColor: 'rgba(212,175,55,0.08)',
        borderWidth: 2.5,
        pointBackgroundColor: '#D4AF37',
        pointRadius: 4,
        pointHoverRadius: 6,
        fill: true,
        tension: 0.4,
    }],
}));

const lineRevOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { callbacks: {
        label: ctx => ` ${new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0 }).format(ctx.parsed.y)}`,
    }}},
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
        y: {
            grid: { color: '#f0f0f0' },
            ticks: { font: { size: 11 }, callback: v => '$' + (v >= 1000 ? (v/1000).toFixed(0) + 'k' : v) },
            beginAtZero: true,
        },
    },
};

// ── Chart: Status donut ───────────────────────────────────────────────────────
const donutData = computed(() => ({
    labels: ['Registered', 'Onboarded', 'Confirmed', 'Cancelled'],
    datasets: [{
        data: [
            props.status_dist.registered,
            props.status_dist.onboarded,
            props.status_dist.confirmed,
            props.status_dist.cancelled,
        ],
        backgroundColor: ['#3b82f6', '#a855f7', '#22c55e', '#ef4444'],
        borderWidth: 0,
        hoverOffset: 6,
    }],
}));

const donutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '68%',
    plugins: {
        legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 } } },
        tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed}` } },
    },
};

// ── Chart: Packages horizontal bar ───────────────────────────────────────────
const barPkgData = computed(() => ({
    labels: props.package_breakdown.map(p => p.name),
    datasets: [{
        label: 'Registrations',
        data: props.package_breakdown.map(p => p.count),
        backgroundColor: '#000000',
        borderRadius: 4,
    }],
}));

const barPkgOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { callbacks: {
        label: ctx => ` ${ctx.parsed.x} registrations`,
    }}},
    scales: {
        x: { grid: { color: '#f0f0f0' }, ticks: { stepSize: 1, font: { size: 11 } }, beginAtZero: true },
        y: { grid: { display: false }, ticks: { font: { size: 12 } } },
    },
};

// ── Podium ────────────────────────────────────────────────────────────────────
const podium = computed(() => {
    const top = props.rep_ranking.slice(0, 3);
    // Reorder: 2nd, 1st, 3rd for visual podium
    if (top.length === 0) return [];
    if (top.length === 1) return [null, top[0], null];
    if (top.length === 2) return [top[1], top[0], null];
    return [top[1], top[0], top[2]];
});

const podiumColors = ['#94a3b8', '#D4AF37', '#cd7f32']; // silver, gold, bronze
const podiumHeights = ['h-20', 'h-28', 'h-14'];
const podiumMedals  = ['🥈', '🥇', '🥉'];
const podiumLabels  = ['2nd', '1st', '3rd'];
</script>

<template>
    <AdminLayout title="Sales History">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 space-y-8">

            <!-- Header -->
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Sales History</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Full performance overview for your sales team</p>
                </div>
                <button @click="exportCsv"
                    class="inline-flex items-center gap-2 bg-black text-white text-sm font-semibold px-4 py-2.5 rounded-xl hover:bg-gray-800 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Export CSV
                </button>
            </div>

            <!-- Filters -->
            <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
                <div class="flex flex-wrap gap-3 items-end">
                    <!-- Year -->
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Year</label>
                        <select v-model="year" @change="applyFilters"
                            class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-black/10 min-w-[90px]">
                            <option v-for="y in available_years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>

                    <!-- Event -->
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Event</label>
                        <select v-model="event" @change="applyFilters"
                            class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-black/10 min-w-[160px]">
                            <option value="">All events</option>
                            <option v-for="e in available_events" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>

                    <!-- Sales Rep -->
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Sales Rep</label>
                        <select v-model="rep" @change="applyFilters"
                            class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-black/10 min-w-[160px]">
                            <option value="">All reps</option>
                            <option v-for="r in available_reps" :key="r.id" :value="r.id">
                                {{ r.first_name }} {{ r.last_name }}
                            </option>
                        </select>
                    </div>

                    <!-- Clear -->
                    <button v-if="event || rep"
                        @click="event = ''; rep = ''; applyFilters()"
                        class="text-xs text-gray-400 hover:text-gray-700 underline self-end pb-2">
                        Clear filters
                    </button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                <div class="bg-black text-white rounded-2xl p-4 flex flex-col gap-1">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Total Registrations</p>
                    <p class="text-3xl font-black">{{ kpis.total }}</p>
                </div>
                <div class="bg-black text-white rounded-2xl p-4 flex flex-col gap-1">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Total Revenue</p>
                    <p class="text-2xl font-black leading-tight" style="color:#D4AF37;">{{ fmt(kpis.revenue) }}</p>
                </div>
                <div class="bg-black text-white rounded-2xl p-4 flex flex-col gap-1">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Downpayments</p>
                    <p class="text-2xl font-black leading-tight text-purple-400">{{ fmt(kpis.downpayments) }}</p>
                </div>
                <div class="bg-black text-white rounded-2xl p-4 flex flex-col gap-1">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Avg Deal Size</p>
                    <p class="text-2xl font-black leading-tight text-blue-400">{{ fmt(kpis.avg_deal) }}</p>
                </div>
                <div class="bg-black text-white rounded-2xl p-4 flex flex-col gap-1">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Conversion Rate</p>
                    <p class="text-3xl font-black" :class="kpis.conversion_rate >= 60 ? 'text-green-400' : kpis.conversion_rate >= 30 ? 'text-yellow-400' : 'text-red-400'">
                        {{ kpis.conversion_rate }}%
                    </p>
                </div>
                <div class="bg-black text-white rounded-2xl p-4 flex flex-col gap-1">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Best Month</p>
                    <p class="text-3xl font-black text-white">{{ kpis.best_month }}</p>
                </div>
            </div>

            <!-- Charts row 1 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Registrations per month -->
                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Registrations per Month</h2>
                            <p class="text-xs text-gray-400">{{ year }}</p>
                        </div>
                        <span class="text-2xl font-black text-gray-900">{{ kpis.total }}</span>
                    </div>
                    <div class="h-52">
                        <Bar :data="barRegsData" :options="barRegsOptions" />
                    </div>
                </div>

                <!-- Revenue per month -->
                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Revenue per Month</h2>
                            <p class="text-xs text-gray-400">Excludes cancelled</p>
                        </div>
                        <span class="text-2xl font-black" style="color:#D4AF37;">{{ fmt(kpis.revenue) }}</span>
                    </div>
                    <div class="h-52">
                        <Line :data="lineRevData" :options="lineRevOptions" />
                    </div>
                </div>
            </div>

            <!-- Charts row 2 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Status donut -->
                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                    <h2 class="text-sm font-bold text-gray-900 mb-1">Status Distribution</h2>
                    <p class="text-xs text-gray-400 mb-5">Breakdown of all registrations</p>
                    <div class="h-56 flex items-center justify-center">
                        <Doughnut :data="donutData" :options="donutOptions" />
                    </div>
                </div>

                <!-- Packages bar -->
                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                    <h2 class="text-sm font-bold text-gray-900 mb-1">Top Packages</h2>
                    <p class="text-xs text-gray-400 mb-5">Most sold packages this period</p>
                    <div class="h-56">
                        <Bar v-if="package_breakdown.length" :data="barPkgData" :options="barPkgOptions" />
                        <div v-else class="h-full flex items-center justify-center text-sm text-gray-400">No data</div>
                    </div>
                </div>
            </div>

            <!-- Podium + Rep Ranking -->
            <div v-if="rep_ranking.length > 0" class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                <h2 class="text-sm font-bold text-gray-900 mb-1">Sales Rep Ranking</h2>
                <p class="text-xs text-gray-400 mb-6">Who's closing the most deals in {{ year }}</p>

                <!-- Podium visual -->
                <div v-if="rep_ranking.length >= 2" class="flex items-end justify-center gap-4 mb-8">
                    <div v-for="(person, idx) in podium" :key="idx"
                        class="flex flex-col items-center gap-2 w-28">
                        <template v-if="person">
                            <!-- Avatar -->
                            <div class="w-12 h-12 rounded-full flex items-center justify-center text-xl font-black text-white flex-shrink-0"
                                :style="{ backgroundColor: podiumColors[idx] }">
                                {{ person.name.charAt(0) }}
                            </div>
                            <p class="text-xs font-semibold text-center text-gray-800 leading-tight">{{ person.name }}</p>
                            <p class="text-xs text-gray-400">{{ person.total }} deals</p>
                            <!-- Podium block -->
                            <div class="w-full rounded-t-xl flex items-center justify-center text-2xl"
                                :class="podiumHeights[idx]"
                                :style="{ backgroundColor: podiumColors[idx] + '22', border: `2px solid ${podiumColors[idx]}` }">
                                <span>{{ podiumMedals[idx] }}</span>
                            </div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ podiumLabels[idx] }}</p>
                        </template>
                        <template v-else>
                            <div class="w-28 h-14"></div>
                        </template>
                    </div>
                </div>

                <!-- Full ranking table -->
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left text-[10px] font-bold uppercase tracking-wider text-gray-400 pb-2">#</th>
                            <th class="text-left text-[10px] font-bold uppercase tracking-wider text-gray-400 pb-2">Rep</th>
                            <th class="text-right text-[10px] font-bold uppercase tracking-wider text-gray-400 pb-2">Total</th>
                            <th class="text-right text-[10px] font-bold uppercase tracking-wider text-gray-400 pb-2">Confirmed</th>
                            <th class="text-right text-[10px] font-bold uppercase tracking-wider text-gray-400 pb-2">Cancelled</th>
                            <th class="text-right text-[10px] font-bold uppercase tracking-wider text-gray-400 pb-2">Rate</th>
                            <th class="text-right text-[10px] font-bold uppercase tracking-wider text-gray-400 pb-2">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="(r, i) in rep_ranking" :key="r.id"
                            class="hover:bg-gray-50 transition-colors">
                            <td class="py-3 pr-3">
                                <span class="text-xs font-black"
                                    :style="i === 0 ? 'color:#D4AF37' : i === 1 ? 'color:#94a3b8' : i === 2 ? 'color:#cd7f32' : 'color:#9ca3af'">
                                    {{ i + 1 }}
                                </span>
                            </td>
                            <td class="py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-gray-900 flex items-center justify-center text-[11px] font-bold text-white flex-shrink-0">
                                        {{ r.name.charAt(0) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900 text-sm leading-none">{{ r.name }}</p>
                                        <p class="text-[10px] text-gray-400 capitalize">{{ r.sales_type }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 text-right font-bold text-gray-900">{{ r.total }}</td>
                            <td class="py-3 text-right text-green-600 font-semibold">{{ r.confirmed }}</td>
                            <td class="py-3 text-right text-red-500 font-semibold">{{ r.cancelled }}</td>
                            <td class="py-3 text-right">
                                <span class="text-xs font-bold px-2 py-0.5 rounded-full"
                                    :class="r.conversion_rate >= 60 ? 'bg-green-100 text-green-700' : r.conversion_rate >= 30 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-600'">
                                    {{ r.conversion_rate }}%
                                </span>
                            </td>
                            <td class="py-3 text-right font-bold text-gray-900">{{ fmt(r.revenue) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Table -->
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <!-- Table header -->
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">Registration Log</h2>
                        <p class="text-xs text-gray-400">{{ table.total }} records found</p>
                    </div>
                    <div class="relative">
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <input v-model="search" type="text" placeholder="Search designer or brand..."
                            class="pl-9 py-2 text-sm border border-gray-200 rounded-xl w-64 focus:outline-none focus:ring-2 focus:ring-black/10"
                            :class="search ? 'pr-8' : 'pr-4'" />
                        <button v-if="search" @click="search = ''; applyFilters()"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Table body -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left text-[10px] font-bold uppercase tracking-wider text-gray-400 px-6 py-3">Date</th>
                                <th class="text-left text-[10px] font-bold uppercase tracking-wider text-gray-400 px-4 py-3">Designer / Brand</th>
                                <th class="text-left text-[10px] font-bold uppercase tracking-wider text-gray-400 px-4 py-3">Event</th>
                                <th class="text-left text-[10px] font-bold uppercase tracking-wider text-gray-400 px-4 py-3">Package</th>
                                <th class="text-left text-[10px] font-bold uppercase tracking-wider text-gray-400 px-4 py-3">Sales Rep</th>
                                <th class="text-left text-[10px] font-bold uppercase tracking-wider text-gray-400 px-4 py-3">Status</th>
                                <th class="text-right text-[10px] font-bold uppercase tracking-wider text-gray-400 px-6 py-3">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-if="table.data.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-gray-400 text-sm">No registrations found for this period.</td>
                            </tr>
                            <tr v-for="row in table.data" :key="row.id"
                                class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-3.5 text-xs text-gray-500 whitespace-nowrap">
                                    {{ new Date(row.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="font-semibold text-gray-900 leading-none">
                                        {{ row.designer?.first_name }} {{ row.designer?.last_name }}
                                    </p>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ row.designer?.designer_profile?.brand_name ?? row.designer?.designerProfile?.brand_name ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-3.5 text-xs text-gray-600 max-w-[140px] truncate">{{ row.event?.name ?? '—' }}</td>
                                <td class="px-4 py-3.5 text-xs text-gray-600">{{ row.package?.name ?? '—' }}</td>
                                <td class="px-4 py-3.5 text-xs text-gray-600">{{ row.sales_rep ? row.sales_rep.first_name + ' ' + row.sales_rep.last_name : '—' }}</td>
                                <td class="px-4 py-3.5">
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full" :class="statusBadge(row.status)">
                                        {{ statusLabel(row.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3.5 text-right font-bold text-gray-900 whitespace-nowrap">{{ fmt(row.agreed_price) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="table.last_page > 1" class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                    <p class="text-xs text-gray-400">
                        Showing {{ table.from }}–{{ table.to }} of {{ table.total }}
                    </p>
                    <div class="flex gap-1">
                        <Link v-if="table.prev_page_url" :href="table.prev_page_url"
                            class="px-3 py-1.5 text-xs font-medium border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            ← Prev
                        </Link>
                        <template v-for="link in table.links.slice(1, -1)" :key="link.label">
                            <Link v-if="link.url" :href="link.url"
                                class="px-3 py-1.5 text-xs font-medium border rounded-lg transition-colors"
                                :class="link.active ? 'bg-black text-white border-black' : 'border-gray-200 hover:bg-gray-50'">
                                {{ link.label }}
                            </Link>
                            <span v-else class="px-3 py-1.5 text-xs text-gray-400">{{ link.label }}</span>
                        </template>
                        <Link v-if="table.next_page_url" :href="table.next_page_url"
                            class="px-3 py-1.5 text-xs font-medium border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            Next →
                        </Link>
                    </div>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
