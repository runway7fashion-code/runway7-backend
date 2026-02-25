<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    stats: Object,
});

const overview = [
    { label: 'Total Usuarios', key: 'total_users',        color: 'text-white',        bg: 'bg-black' },
    { label: 'Equipo Interno', key: 'total_internal',      color: 'text-blue-400',     bg: 'bg-gray-900' },
    { label: 'Participantes',  key: 'total_participants',  color: 'text-purple-400',   bg: 'bg-gray-900' },
    { label: 'Asistentes',     key: 'total_attendees',     color: 'text-green-400',    bg: 'bg-gray-900' },
];

const internalCards = [
    { label: 'Admin',              key: 'admin' },
    { label: 'Contabilidad',       key: 'accounting' },
    { label: 'Operaciones',        key: 'operation' },
    { label: 'Boletos',            key: 'tickets_manager' },
    { label: 'Marketing',          key: 'marketing' },
    { label: 'Relaciones Públicas',key: 'public_relations' },
];

const participantCards = [
    { label: 'Diseñadores', key: 'designer' },
    { label: 'Modelos',     key: 'model' },
    { label: 'Medios',      key: 'media' },
    { label: 'Voluntarios', key: 'volunteer' },
    { label: 'Staff',       key: 'staff' },
];

const attendeeCards = [
    { label: 'Asistentes',    key: 'attendee' },
    { label: 'VIP',           key: 'vip' },
    { label: 'Influencers',   key: 'influencer' },
    { label: 'Prensa',        key: 'press' },
    { label: 'Patrocinadores',key: 'sponsor' },
    { label: 'Cortesía',      key: 'complementary' },
];
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Dashboard</h2>
        </template>

        <div>
            <h3 class="text-2xl font-bold text-gray-900 mb-1">Bienvenido a Runway7</h3>
            <p class="text-gray-500 mb-8">Panel de administración del sistema</p>

            <!-- Overview -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
                <div
                    v-for="card in overview"
                    :key="card.key"
                    class="rounded-xl p-6 border border-gray-200 text-white"
                    :class="card.bg"
                >
                    <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">{{ card.label }}</p>
                    <p class="text-4xl font-bold" :class="card.color">{{ stats[card.key] }}</p>
                </div>
            </div>

            <!-- Equipo Interno -->
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-400 inline-block"></span>
                    <h4 class="text-sm font-semibold uppercase tracking-widest text-gray-500">Equipo Interno</h4>
                </div>
                <div class="grid grid-cols-3 md:grid-cols-6 gap-3">
                    <div
                        v-for="card in internalCards"
                        :key="card.key"
                        class="bg-gray-900 rounded-xl p-4 border border-gray-700 text-white"
                    >
                        <p class="text-xs uppercase tracking-widest text-gray-400 mb-2 leading-tight">{{ card.label }}</p>
                        <p class="text-3xl font-bold text-blue-400">{{ stats[card.key] }}</p>
                    </div>
                </div>
            </div>

            <!-- Participantes -->
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-2.5 h-2.5 rounded-full bg-purple-400 inline-block"></span>
                    <h4 class="text-sm font-semibold uppercase tracking-widest text-gray-500">Participantes del Evento</h4>
                </div>
                <div class="grid grid-cols-3 md:grid-cols-5 gap-3">
                    <div
                        v-for="card in participantCards"
                        :key="card.key"
                        class="bg-gray-900 rounded-xl p-4 border border-gray-700 text-white"
                    >
                        <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">{{ card.label }}</p>
                        <p class="text-3xl font-bold text-purple-400">{{ stats[card.key] }}</p>
                    </div>
                </div>
            </div>

            <!-- Asistentes -->
            <div class="mb-10">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-2.5 h-2.5 rounded-full bg-green-400 inline-block"></span>
                    <h4 class="text-sm font-semibold uppercase tracking-widest text-gray-500">Asistentes / Público</h4>
                </div>
                <div class="grid grid-cols-3 md:grid-cols-6 gap-3">
                    <div
                        v-for="card in attendeeCards"
                        :key="card.key"
                        class="bg-gray-900 rounded-xl p-4 border border-gray-700 text-white"
                    >
                        <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">{{ card.label }}</p>
                        <p class="text-3xl font-bold text-green-400">{{ stats[card.key] }}</p>
                    </div>
                </div>
            </div>

            <!-- Eventos + Quick links -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-gray-900 rounded-xl p-5 border border-gray-700 text-white">
                    <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Eventos Activos</p>
                    <p class="text-4xl font-bold text-yellow-400">{{ stats.active_events }}</p>
                </div>
                <div class="bg-gray-900 rounded-xl p-5 border border-gray-700 text-white">
                    <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Total Eventos</p>
                    <p class="text-4xl font-bold text-white">{{ stats.total_events }}</p>
                </div>
                <a href="/admin/users/create" class="block p-5 bg-white rounded-xl border border-gray-200 hover:border-yellow-400 hover:shadow-md transition-all group">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-semibold text-gray-900">Nuevo Usuario</h4>
                        <span class="text-xl group-hover:scale-110 transition-transform">+</span>
                    </div>
                    <p class="text-gray-500 text-sm">Agregar modelo, diseñador, staff u otro rol</p>
                </a>
                <a href="/admin/users" class="block p-5 bg-white rounded-xl border border-gray-200 hover:border-yellow-400 hover:shadow-md transition-all group">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-semibold text-gray-900">Gestionar Usuarios</h4>
                        <span class="text-xl group-hover:scale-110 transition-transform">→</span>
                    </div>
                    <p class="text-gray-500 text-sm">Ver, editar y administrar todos los usuarios</p>
                </a>
            </div>
        </div>
    </AdminLayout>
</template>
