<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    stats: Object,
    financeStats: Object,
    recentRegistrations: Array,
    salesRepStats: Array,
    repRanking: Array,
    currentYear: Number,
});

function formatMoney(val) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0 }).format(val || 0);
}

const page = usePage();
const user = page.props.auth?.user;
const isLider = user?.sales_type === 'lider' || user?.role === 'admin';

const statusCards = [
    { label: 'Registered',  key: 'registered', color: 'text-blue-400',   bg: 'bg-gray-900' },
    { label: 'Onboarded',   key: 'onboarded',  color: 'text-purple-400', bg: 'bg-gray-900' },
    { label: 'Confirmed',   key: 'confirmed',  color: 'text-green-400',  bg: 'bg-gray-900' },
    { label: 'Cancelled',   key: 'cancelled',  color: 'text-red-400',    bg: 'bg-gray-900' },
];

function statusBadge(status) {
    return {
        registered: 'bg-blue-100 text-blue-700',
        onboarded:  'bg-purple-100 text-purple-700',
        confirmed:  'bg-green-100 text-green-700',
        cancelled:  'bg-red-100 text-red-700',
    }[status] ?? 'bg-gray-100 text-gray-600';
}

function statusLabel(status) {
    return {
        registered: 'Registered',
        onboarded:  'Onboarded',
        confirmed:  'Confirmed',
        cancelled:  'Cancelled',
    }[status] ?? status;
}

function salesTypeLabel(type) {
    return { lider: 'Leader', asesor: 'Advisor' }[type] ?? type ?? '—';
}

function fmt(val) {
    return formatMoney(val);
}

// Podium
const podium = computed(() => {
    const top = (props.repRanking || []).slice(0, 3);
    if (top.length === 0) return [];
    if (top.length === 1) return [null, top[0], null];
    if (top.length === 2) return [top[1], top[0], null];
    return [top[1], top[0], top[2]];
});
const podiumColors = ['#94a3b8', '#D4AF37', '#cd7f32'];
const podiumHeights = ['h-20', 'h-28', 'h-14'];
const podiumMedals  = ['🥈', '🥇', '🥉'];
const podiumLabels  = ['2nd', '1st', '3rd'];
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Sales Dashboard</h2>
        </template>

        <div>
            <!-- Asesor layout: 2 columns — KPIs left, Ranking right -->
            <div v-if="!isLider" class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">
                <!-- Left: KPI cards stacked -->
                <div class="lg:col-span-2 space-y-3">
                    <!-- Row 1: Registration stats -->
                    <div class="grid grid-cols-5 gap-3">
                        <div class="rounded-xl p-5 border border-gray-200 bg-black text-white">
                            <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Total Registrations</p>
                            <p class="text-3xl font-bold text-white">{{ stats.total_registrations }}</p>
                        </div>
                        <div v-for="card in statusCards" :key="card.key"
                            class="rounded-xl p-5 border border-gray-700 text-white" :class="card.bg">
                            <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">{{ card.label }}</p>
                            <p class="text-3xl font-bold" :class="card.color">{{ stats[card.key] }}</p>
                        </div>
                    </div>
                    <!-- Row 2: Finance stats -->
                    <div v-if="financeStats" class="grid grid-cols-4 gap-3">
                        <div class="bg-white rounded-xl border border-gray-200 p-5">
                            <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Total Revenue</p>
                            <p class="text-2xl font-bold text-gray-900">{{ formatMoney(financeStats.total_revenue) }}</p>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 p-5">
                            <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Downpayments Collected</p>
                            <p class="text-2xl font-bold text-green-600">{{ formatMoney(financeStats.total_downpayments) }}</p>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 p-5">
                            <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Registrations This Month</p>
                            <p class="text-2xl font-bold text-gray-900">{{ financeStats.this_month_count }}</p>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 p-5">
                            <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Conversion Rate</p>
                            <p class="text-2xl font-bold text-gray-900">{{ financeStats.conversion_rate }}%</p>
                        </div>
                    </div>
                </div>

                <!-- Right: Sales Rep Ranking -->
                <div v-if="repRanking?.length > 0" class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                    <h2 class="text-sm font-bold text-gray-900 mb-1">Sales Rep Ranking</h2>
                    <p class="text-xs text-gray-400 mb-4">Who's closing the most deals in {{ currentYear }}</p>

                    <!-- Podium visual -->
                    <div v-if="repRanking.length >= 2" class="flex items-end justify-center gap-3 mb-6">
                        <div v-for="(person, idx) in podium" :key="idx"
                            class="flex flex-col items-center gap-1.5 w-24">
                            <template v-if="person">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-base font-black text-white flex-shrink-0"
                                    :style="{ backgroundColor: podiumColors[idx] }">
                                    {{ person.name.charAt(0) }}
                                </div>
                                <p class="text-[10px] font-semibold text-center text-gray-800 leading-tight">{{ person.name }}</p>
                                <p class="text-[10px] text-gray-400">{{ person.total }} deals</p>
                                <div class="w-full rounded-t-xl flex items-center justify-center text-xl"
                                    :class="podiumHeights[idx]"
                                    :style="{ backgroundColor: podiumColors[idx] + '22', border: `2px solid ${podiumColors[idx]}` }">
                                    <span>{{ podiumMedals[idx] }}</span>
                                </div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ podiumLabels[idx] }}</p>
                            </template>
                            <template v-else><div class="w-24 h-14"></div></template>
                        </div>
                    </div>

                    <!-- Compact ranking table -->
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left text-[10px] font-bold uppercase tracking-wider text-gray-400 pb-2">#</th>
                                <th class="text-left text-[10px] font-bold uppercase tracking-wider text-gray-400 pb-2">Rep</th>
                                <th class="text-right text-[10px] font-bold uppercase tracking-wider text-gray-400 pb-2">Deals</th>
                                <th class="text-right text-[10px] font-bold uppercase tracking-wider text-gray-400 pb-2">Rate</th>
                                <th class="text-right text-[10px] font-bold uppercase tracking-wider text-gray-400 pb-2">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="(r, i) in repRanking" :key="r.id" class="hover:bg-gray-50 transition-colors">
                                <td class="py-2 pr-2">
                                    <span class="text-xs font-black"
                                        :style="i === 0 ? 'color:#D4AF37' : i === 1 ? 'color:#94a3b8' : i === 2 ? 'color:#cd7f32' : 'color:#9ca3af'">
                                        {{ i + 1 }}
                                    </span>
                                </td>
                                <td class="py-2">
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-6 h-6 rounded-full bg-gray-900 flex items-center justify-center text-[10px] font-bold text-white flex-shrink-0">
                                            {{ r.name.charAt(0) }}
                                        </div>
                                        <p class="font-semibold text-gray-900 text-xs leading-none truncate">{{ r.name }}</p>
                                    </div>
                                </td>
                                <td class="py-2 text-right font-bold text-gray-900">{{ r.total }}</td>
                                <td class="py-2 text-right">
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                                        :class="r.conversion_rate >= 60 ? 'bg-green-100 text-green-700' : r.conversion_rate >= 30 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-600'">
                                        {{ r.conversion_rate }}%
                                    </span>
                                </td>
                                <td class="py-2 text-right font-bold text-gray-900">{{ fmt(r.revenue) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Líder/Admin layout: original full-width -->
            <template v-if="isLider">
                <!-- All cards in one row -->
                <div class="grid grid-cols-5 gap-3 mb-10">
                    <div class="rounded-xl p-5 border border-gray-200 bg-black text-white">
                        <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Total Registrations</p>
                        <p class="text-3xl font-bold text-white">{{ stats.total_registrations }}</p>
                    </div>
                    <div v-for="card in statusCards" :key="card.key"
                        class="rounded-xl p-5 border border-gray-700 text-white" :class="card.bg">
                        <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">{{ card.label }}</p>
                        <p class="text-3xl font-bold" :class="card.color">{{ stats[card.key] }}</p>
                    </div>
                </div>

                <!-- Finance stats: 3 cols con Top Sellers en row-span-2 -->
                <div v-if="financeStats" class="mb-10">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="bg-white rounded-xl border border-gray-200 p-5">
                            <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Total Revenue</p>
                            <p class="text-2xl font-bold text-gray-900">{{ formatMoney(financeStats.total_revenue) }}</p>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 p-5">
                            <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Downpayments Collected</p>
                            <p class="text-2xl font-bold text-green-600">{{ formatMoney(financeStats.total_downpayments) }}</p>
                        </div>
                        <div v-if="financeStats.top_sellers?.length" class="bg-white rounded-xl border border-gray-200 p-5 row-span-2 flex flex-col">
                            <p class="text-xs uppercase tracking-widest text-gray-400 mb-4">Top Sellers</p>
                            <div class="space-y-3 flex-1">
                                <div v-for="(seller, i) in financeStats.top_sellers" :key="i" class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                                            :class="i === 0 ? 'bg-yellow-100 text-yellow-700' : i === 1 ? 'bg-gray-100 text-gray-600' : 'bg-amber-50 text-amber-600'">
                                            {{ i + 1 }}
                                        </span>
                                        <span class="text-sm font-medium text-gray-900">{{ seller.name }}</span>
                                    </div>
                                    <span class="text-sm font-bold text-gray-900">{{ seller.total }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 p-5">
                            <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Registrations This Month</p>
                            <p class="text-2xl font-bold text-gray-900">{{ financeStats.this_month_count }}</p>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 p-5">
                            <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Conversion Rate</p>
                            <p class="text-2xl font-bold text-gray-900">{{ financeStats.conversion_rate }}%</p>
                        </div>
                    </div>
                </div>

                <!-- Sales Rep Ranking full-width for líder -->
                <div v-if="repRanking?.length > 0" class="mb-10">
                    <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                        <h2 class="text-sm font-bold text-gray-900 mb-1">Sales Rep Ranking</h2>
                        <p class="text-xs text-gray-400 mb-6">Who's closing the most deals in {{ currentYear }}</p>
                        <div v-if="repRanking.length >= 2" class="flex items-end justify-center gap-4 mb-8">
                            <div v-for="(person, idx) in podium" :key="idx" class="flex flex-col items-center gap-2 w-28">
                                <template v-if="person">
                                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-xl font-black text-white flex-shrink-0"
                                        :style="{ backgroundColor: podiumColors[idx] }">{{ person.name.charAt(0) }}</div>
                                    <p class="text-xs font-semibold text-center text-gray-800 leading-tight">{{ person.name }}</p>
                                    <p class="text-xs text-gray-400">{{ person.total }} deals</p>
                                    <div class="w-full rounded-t-xl flex items-center justify-center text-2xl"
                                        :class="podiumHeights[idx]"
                                        :style="{ backgroundColor: podiumColors[idx] + '22', border: `2px solid ${podiumColors[idx]}` }">
                                        <span>{{ podiumMedals[idx] }}</span>
                                    </div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ podiumLabels[idx] }}</p>
                                </template>
                                <template v-else><div class="w-28 h-14"></div></template>
                            </div>
                        </div>
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
                                <tr v-for="(r, i) in repRanking" :key="r.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="py-3 pr-3">
                                        <span class="text-xs font-black"
                                            :style="i === 0 ? 'color:#D4AF37' : i === 1 ? 'color:#94a3b8' : i === 2 ? 'color:#cd7f32' : 'color:#9ca3af'">{{ i + 1 }}</span>
                                    </td>
                                    <td class="py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-gray-900 flex items-center justify-center text-[11px] font-bold text-white flex-shrink-0">{{ r.name.charAt(0) }}</div>
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
                                            :class="r.conversion_rate >= 60 ? 'bg-green-100 text-green-700' : r.conversion_rate >= 30 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-600'">{{ r.conversion_rate }}%</span>
                                    </td>
                                    <td class="py-3 text-right font-bold text-gray-900">{{ fmt(r.revenue) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <!-- Sales Rep Stats (solo líder y admin) -->
            <div v-if="isLider && salesRepStats?.length" class="mb-10">
                <h4 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4">Sales by Advisor</h4>
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-widest">
                            <tr>
                                <th class="px-4 py-3 text-left">Advisor</th>
                                <th class="px-4 py-3 text-left">Tipo</th>
                                <th class="px-4 py-3 text-center">Total</th>
                                <th class="px-4 py-3 text-center">Registered</th>
                                <th class="px-4 py-3 text-center">Onboarded</th>
                                <th class="px-4 py-3 text-center">Confirmed</th>
                                <th class="px-4 py-3 text-center">Cancelled</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="rep in salesRepStats" :key="rep.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ rep.name }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="rep.sales_type === 'lider'" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Leader</span>
                                    <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Advisor</span>
                                </td>
                                <td class="px-4 py-3 text-center font-bold text-gray-900">{{ rep.total }}</td>
                                <td class="px-4 py-3 text-center text-blue-600">{{ rep.registered }}</td>
                                <td class="px-4 py-3 text-center text-purple-600">{{ rep.onboarded }}</td>
                                <td class="px-4 py-3 text-center text-green-600">{{ rep.confirmed }}</td>
                                <td class="px-4 py-3 text-center text-red-500">{{ rep.cancelled }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick actions + recent -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Quick actions -->
                <div class="space-y-4">
                    <h4 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-2">Quick Actions</h4>
                    <Link href="/admin/sales/designers/create" class="block p-5 bg-white rounded-xl border border-gray-200 hover:border-yellow-400 hover:shadow-md transition-all group">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-semibold text-gray-900">Register Designer</h4>
                            <span class="text-xl group-hover:scale-110 transition-transform">+</span>
                        </div>
                        <p class="text-gray-500 text-sm">Register a new designer for an event</p>
                    </Link>
                    <Link href="/admin/sales/designers" class="block p-5 bg-white rounded-xl border border-gray-200 hover:border-yellow-400 hover:shadow-md transition-all group">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-semibold text-gray-900">View Registrations</h4>
                            <span class="text-xl group-hover:scale-110 transition-transform">&rarr;</span>
                        </div>
                        <p class="text-gray-500 text-sm">View and manage all designer registrations</p>
                    </Link>
                </div>

                <!-- Recent registrations -->
                <div class="md:col-span-2">
                    <h4 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4">Recent Registrations</h4>
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div v-if="!recentRegistrations.length" class="p-6 text-center text-gray-400 text-sm">
                            No registrations yet
                        </div>
                        <table v-else class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-widest">
                                <tr>
                                    <th class="px-4 py-3 text-left">Designer</th>
                                    <th class="px-4 py-3 text-left">Event</th>
                                    <th v-if="isLider" class="px-4 py-3 text-left">Advisor</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="r in recentRegistrations" :key="r.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <Link :href="`/admin/sales/designers/${r.id}`" class="text-gray-900 font-medium hover:text-yellow-600">
                                            {{ r.designer?.first_name }} {{ r.designer?.last_name }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ r.event?.name }}</td>
                                    <td v-if="isLider" class="px-4 py-3 text-gray-600">{{ r.sales_rep?.first_name }} {{ r.sales_rep?.last_name }}</td>
                                    <td class="px-4 py-3">
                                        <span :class="statusBadge(r.status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                                            {{ statusLabel(r.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-400 text-xs">{{ new Date(r.created_at).toLocaleDateString('es-US') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
