<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    financeStats: Object,
    recentRegistrations: Array,
    salesRepStats: Array,
});

function formatMoney(val) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0 }).format(val || 0);
}

const page = usePage();
const user = page.props.auth?.user;
const isLider = user?.sales_type === 'lider' || user?.role === 'admin';

const statusCards = [
    { label: 'Registrados',  key: 'registered', color: 'text-blue-400',   bg: 'bg-gray-900' },
    { label: 'Onboarded',    key: 'onboarded',  color: 'text-purple-400', bg: 'bg-gray-900' },
    { label: 'Confirmados',  key: 'confirmed',  color: 'text-green-400',  bg: 'bg-gray-900' },
    { label: 'Cancelados',   key: 'cancelled',  color: 'text-red-400',    bg: 'bg-gray-900' },
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
        registered: 'Registrado',
        onboarded:  'Onboarded',
        confirmed:  'Confirmado',
        cancelled:  'Cancelado',
    }[status] ?? status;
}

function salesTypeLabel(type) {
    return { lider: 'Líder', asesor: 'Asesor' }[type] ?? type ?? '—';
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Panel de Ventas</h2>
        </template>

        <div>
            <!-- All cards in one row -->
            <div class="grid grid-cols-5 gap-3 mb-10">
                <div class="rounded-xl p-5 border border-gray-200 bg-black text-white">
                    <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Total Registrations</p>
                    <p class="text-3xl font-bold text-white">{{ stats.total_registrations }}</p>
                </div>
                <div
                    v-for="card in statusCards"
                    :key="card.key"
                    class="rounded-xl p-5 border border-gray-700 text-white"
                    :class="card.bg"
                >
                    <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">{{ card.label }}</p>
                    <p class="text-3xl font-bold" :class="card.color">{{ stats[card.key] }}</p>
                </div>
            </div>

            <!-- Finance stats -->
            <div v-if="financeStats" class="mb-10">
                <!-- Asesor: 4 cards en una sola fila -->
                <div v-if="!isLider" class="grid grid-cols-4 gap-3">
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Ingresos Totales</p>
                        <p class="text-2xl font-bold text-gray-900">{{ formatMoney(financeStats.total_revenue) }}</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Anticipos Cobrados</p>
                        <p class="text-2xl font-bold text-green-600">{{ formatMoney(financeStats.total_downpayments) }}</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Registrations This Month</p>
                        <p class="text-2xl font-bold text-gray-900">{{ financeStats.this_month_count }}</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Tasa de Confirmación</p>
                        <p class="text-2xl font-bold text-gray-900">{{ financeStats.confirmation_rate }}%</p>
                    </div>
                </div>
                <!-- Líder/Admin: 3 cols con Top Sellers en row-span-2 -->
                <div v-else class="grid grid-cols-3 gap-3">
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Ingresos Totales</p>
                        <p class="text-2xl font-bold text-gray-900">{{ formatMoney(financeStats.total_revenue) }}</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Anticipos Cobrados</p>
                        <p class="text-2xl font-bold text-green-600">{{ formatMoney(financeStats.total_downpayments) }}</p>
                    </div>
                    <!-- Top 3 vendedores — ocupa col 3, filas 1-2 -->
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
                        <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Tasa de Confirmación</p>
                        <p class="text-2xl font-bold text-gray-900">{{ financeStats.confirmation_rate }}%</p>
                    </div>
                </div>
            </div>

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
                                <th class="px-4 py-3 text-center">Registrados</th>
                                <th class="px-4 py-3 text-center">Onboarded</th>
                                <th class="px-4 py-3 text-center">Confirmados</th>
                                <th class="px-4 py-3 text-center">Cancelados</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="rep in salesRepStats" :key="rep.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ rep.name }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="rep.sales_type === 'lider'" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Líder</span>
                                    <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Asesor</span>
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
                    <h4 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-2">Actions Rápidas</h4>
                    <Link href="/admin/sales/designers/create" class="block p-5 bg-white rounded-xl border border-gray-200 hover:border-yellow-400 hover:shadow-md transition-all group">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-semibold text-gray-900">Register Designer</h4>
                            <span class="text-xl group-hover:scale-110 transition-transform">+</span>
                        </div>
                        <p class="text-gray-500 text-sm">Registrar un nuevo diseñador para un evento</p>
                    </Link>
                    <Link href="/admin/sales/designers" class="block p-5 bg-white rounded-xl border border-gray-200 hover:border-yellow-400 hover:shadow-md transition-all group">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-semibold text-gray-900">View Registrations</h4>
                            <span class="text-xl group-hover:scale-110 transition-transform">&rarr;</span>
                        </div>
                        <p class="text-gray-500 text-sm">Ver y gestionar todos los registrations de diseñadores</p>
                    </Link>
                </div>

                <!-- Recent registrations -->
                <div class="md:col-span-2">
                    <h4 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4">Recent Registrations</h4>
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div v-if="!recentRegistrations.length" class="p-6 text-center text-gray-400 text-sm">
                            No registrations aún
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
