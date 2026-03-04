<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { FunnelIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    logs: Object,
    actions: Array,
    filters: Object,
});

const filters = ref({
    role: props.filters?.role ?? '',
    search: props.filters?.search ?? '',
    action: props.filters?.action ?? '',
    date_from: props.filters?.date_from ?? '',
    date_to: props.filters?.date_to ?? '',
});

let debounceTimer = null;

function applyFilters() {
    const params = {};
    Object.entries(filters.value).forEach(([k, v]) => { if (v) params[k] = v; });
    router.get('/admin/logs', params, { preserveState: true, replace: true });
}

watch(() => filters.value.search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 400);
});

function onFilterChange() {
    applyFilters();
}

function clearFilters() {
    filters.value = { role: '', search: '', action: '', date_from: '', date_to: '' };
    applyFilters();
}

const hasActiveFilters = computed(() => Object.values(filters.value).some(v => v));

const roles = [
    { value: 'model', label: 'Modelo' },
    { value: 'designer', label: 'Diseñador' },
    { value: 'admin', label: 'Admin' },
    { value: 'media', label: 'Media' },
    { value: 'volunteer', label: 'Voluntario' },
    { value: 'staff', label: 'Staff' },
];

const colorClasses = {
    green:  'bg-green-100 text-green-700',
    blue:   'bg-blue-100 text-blue-700',
    yellow: 'bg-yellow-100 text-yellow-700',
    purple: 'bg-purple-100 text-purple-700',
    red:    'bg-red-100 text-red-700',
    indigo: 'bg-indigo-100 text-indigo-700',
    teal:   'bg-teal-100 text-teal-700',
    orange: 'bg-orange-100 text-orange-700',
    gray:   'bg-gray-100 text-gray-700',
    pink:   'bg-pink-100 text-pink-700',
};

const roleColors = {
    model: 'bg-purple-100 text-purple-700',
    designer: 'bg-blue-100 text-blue-700',
    admin: 'bg-red-100 text-red-700',
    media: 'bg-yellow-100 text-yellow-700',
    volunteer: 'bg-green-100 text-green-700',
    staff: 'bg-gray-100 text-gray-700',
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Logs de Actividad</h2>
        </template>

        <div class="space-y-6">
            <!-- Filters -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                    <select v-model="filters.role" @change="onFilterChange"
                        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                        <option value="">Todos los roles</option>
                        <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </select>

                    <select v-model="filters.action" @change="onFilterChange"
                        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                        <option value="">Todas las acciones</option>
                        <option v-for="a in actions" :key="a.value" :value="a.value">{{ a.label }}</option>
                    </select>

                    <input v-model="filters.search" type="text" placeholder="Buscar usuario..."
                        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />

                    <input v-model="filters.date_from" type="date" @change="onFilterChange"
                        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />

                    <input v-model="filters.date_to" type="date" @change="onFilterChange"
                        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                </div>
                <div v-if="hasActiveFilters" class="mt-3 flex items-center gap-2">
                    <button @click="clearFilters" class="text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1">
                        <XMarkIcon class="h-3.5 w-3.5" /> Limpiar filtros
                    </button>
                    <span class="text-xs text-gray-400">{{ logs.total }} resultado{{ logs.total !== 1 ? 's' : '' }}</span>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50/50">
                                <th class="py-3 px-4">Fecha</th>
                                <th class="py-3 px-4">Usuario</th>
                                <th class="py-3 px-4">Acción</th>
                                <th class="py-3 px-4">Descripción</th>
                                <th class="py-3 px-4">Realizado por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in logs.data" :key="log.id"
                                class="border-b border-gray-50 hover:bg-gray-50/50 transition">
                                <td class="py-3 px-4 text-gray-500 text-xs whitespace-nowrap">
                                    {{ log.created_at }}
                                </td>
                                <td class="py-3 px-4">
                                    <div v-if="log.user_name" class="flex items-center gap-2">
                                        <span class="text-gray-900 text-sm font-medium">{{ log.user_name }}</span>
                                        <span v-if="log.user_role"
                                            :class="['px-1.5 py-0.5 rounded text-[10px] font-medium', roleColors[log.user_role] || 'bg-gray-100 text-gray-700']">
                                            {{ log.user_role }}
                                        </span>
                                    </div>
                                    <span v-else class="text-gray-400 text-xs">—</span>
                                </td>
                                <td class="py-3 px-4">
                                    <span :class="['px-2 py-1 rounded-full text-xs font-medium', colorClasses[log.action_color] || 'bg-gray-100 text-gray-700']">
                                        {{ log.action_label }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-gray-600 text-sm max-w-md truncate">
                                    {{ log.description }}
                                </td>
                                <td class="py-3 px-4 text-gray-500 text-sm whitespace-nowrap">
                                    {{ log.performed_by_name }}
                                </td>
                            </tr>
                            <tr v-if="!logs.data.length">
                                <td colspan="5" class="py-12 text-center text-gray-400">
                                    <FunnelIcon class="h-8 w-8 mx-auto mb-2 text-gray-300" />
                                    No se encontraron logs
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="logs.last_page > 1" class="flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    Mostrando {{ logs.from }} a {{ logs.to }} de {{ logs.total }} registros
                </div>
                <div class="flex gap-1">
                    <Link v-if="logs.prev_page_url" :href="logs.prev_page_url"
                        class="px-3 py-1 rounded border border-gray-300 text-sm hover:bg-gray-50">
                        Anterior
                    </Link>
                    <template v-for="page in logs.links.slice(1, -1)" :key="page.label">
                        <Link v-if="page.url" :href="page.url"
                            :class="page.active
                                ? 'px-3 py-1 rounded bg-black text-white text-sm'
                                : 'px-3 py-1 rounded border border-gray-300 text-sm hover:bg-gray-50'"
                            v-html="page.label" />
                        <span v-else class="px-3 py-1 text-sm text-gray-400" v-html="page.label" />
                    </template>
                    <Link v-if="logs.next_page_url" :href="logs.next_page_url"
                        class="px-3 py-1 rounded border border-gray-300 text-sm hover:bg-gray-50">
                        Siguiente
                    </Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
