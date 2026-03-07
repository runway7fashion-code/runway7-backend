<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    recentRegistrations: Array,
});

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
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Panel de Ventas</h2>
        </template>

        <div>
            <h3 class="text-2xl font-bold text-gray-900 mb-1">Panel de Ventas</h3>
            <p class="text-gray-500 mb-8">Gestión de registros de diseñadores</p>

            <!-- Overview cards -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                <div class="rounded-xl p-6 border border-gray-200 bg-black text-white">
                    <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Total Registros</p>
                    <p class="text-4xl font-bold text-white">{{ stats.total_registrations }}</p>
                </div>
                <div class="rounded-xl p-6 border border-gray-200 bg-gray-900 text-white">
                    <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Eventos Activos</p>
                    <p class="text-4xl font-bold text-yellow-400">{{ stats.active_events }}</p>
                </div>
            </div>

            <!-- Status breakdown -->
            <div class="grid grid-cols-3 md:grid-cols-5 gap-3 mb-10">
                <div
                    v-for="card in statusCards"
                    :key="card.key"
                    class="rounded-xl p-4 border border-gray-700 text-white"
                    :class="card.bg"
                >
                    <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">{{ card.label }}</p>
                    <p class="text-3xl font-bold" :class="card.color">{{ stats[card.key] }}</p>
                </div>
            </div>

            <!-- Quick actions + recent -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Quick actions -->
                <div class="space-y-4">
                    <h4 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-2">Acciones Rápidas</h4>
                    <Link href="/admin/sales/designers/create" class="block p-5 bg-white rounded-xl border border-gray-200 hover:border-yellow-400 hover:shadow-md transition-all group">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-semibold text-gray-900">Registrar Diseñador</h4>
                            <span class="text-xl group-hover:scale-110 transition-transform">+</span>
                        </div>
                        <p class="text-gray-500 text-sm">Registrar un nuevo diseñador para un evento</p>
                    </Link>
                    <Link href="/admin/sales/designers" class="block p-5 bg-white rounded-xl border border-gray-200 hover:border-yellow-400 hover:shadow-md transition-all group">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-semibold text-gray-900">Ver Registros</h4>
                            <span class="text-xl group-hover:scale-110 transition-transform">&rarr;</span>
                        </div>
                        <p class="text-gray-500 text-sm">Ver y gestionar todos los registros de diseñadores</p>
                    </Link>
                </div>

                <!-- Recent registrations -->
                <div class="md:col-span-2">
                    <h4 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4">Registros Recientes</h4>
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div v-if="!recentRegistrations.length" class="p-6 text-center text-gray-400 text-sm">
                            Sin registros aún
                        </div>
                        <table v-else class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-widest">
                                <tr>
                                    <th class="px-4 py-3 text-left">Diseñador</th>
                                    <th class="px-4 py-3 text-left">Evento</th>
                                    <th class="px-4 py-3 text-left">Estado</th>
                                    <th class="px-4 py-3 text-left">Fecha</th>
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
