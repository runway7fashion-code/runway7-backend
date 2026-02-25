<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    users: Object,
    filters: Object,
    roleCategories: Object,
});

const search = ref(props.filters?.search || '');
const category = ref(props.filters?.category || '');
const role = ref(props.filters?.role || '');

// All roles flat list for the role dropdown
const allRoles = computed(() => {
    const all = [];
    if (!category.value) {
        Object.entries(props.roleCategories).forEach(([cat, roles]) => {
            roles.forEach(r => all.push({ value: r, label: formatRole(r), category: cat }));
        });
    } else {
        (props.roleCategories[category.value] || []).forEach(r => {
            all.push({ value: r, label: formatRole(r), category: category.value });
        });
    }
    return all;
});

const categoryColors = {
    internal: 'bg-blue-100 text-blue-800',
    participant: 'bg-purple-100 text-purple-800',
    attendee: 'bg-green-100 text-green-800',
};

const statusColors = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-red-100 text-red-800',
    pending: 'bg-yellow-100 text-yellow-800',
};

function formatRole(r) {
    return r.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function getCategoryForRole(r) {
    for (const [cat, roles] of Object.entries(props.roleCategories)) {
        if (roles.includes(r)) return cat;
    }
    return 'attendee';
}

let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});
watch([category, role], () => applyFilters());

// Reset role when category changes
watch(category, () => { role.value = ''; });

function applyFilters() {
    router.get('/admin/users', {
        search: search.value,
        category: category.value,
        role: role.value,
    }, { preserveState: true, replace: true });
}

function deleteUser(user) {
    if (confirm(`¿Eliminar a ${user.first_name} ${user.last_name}?`)) {
        router.delete(`/admin/users/${user.id}`);
    }
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Usuarios</h2>
        </template>

        <div>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Usuarios</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ users.total }} usuarios registrados</p>
                </div>
                <Link href="/admin/users/create" class="px-4 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors">
                    + Nuevo Usuario
                </Link>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3 mb-6">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Buscar nombre, email o teléfono..."
                    class="flex-1 min-w-48 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400"
                />
                <select v-model="category" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todas las categorías</option>
                    <option value="internal">Equipo Interno</option>
                    <option value="participant">Participantes</option>
                    <option value="attendee">Asistentes</option>
                </select>
                <select v-model="role" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todos los roles</option>
                    <option v-for="r in allRoles" :key="r.value" :value="r.value">{{ r.label }}</option>
                </select>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Usuario</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Rol</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Categoría</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Registro</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-9 h-9 rounded-full bg-black flex items-center justify-center text-xs font-bold text-white flex-shrink-0">
                                        {{ user.first_name[0] }}{{ user.last_name[0] }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">{{ user.first_name }} {{ user.last_name }}</p>
                                        <p class="text-gray-500 text-xs">{{ user.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-gray-700 capitalize">{{ user.role.replace(/_/g, ' ') }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                    :class="categoryColors[getCategoryForRole(user.role)]">
                                    {{ getCategoryForRole(user.role) === 'internal' ? 'Interno' : getCategoryForRole(user.role) === 'participant' ? 'Participante' : 'Asistente' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize" :class="statusColors[user.status]">
                                    {{ user.status === 'active' ? 'Activo' : user.status === 'inactive' ? 'Inactivo' : 'Pendiente' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-sm">
                                {{ new Date(user.created_at).toLocaleDateString('es-US') }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end space-x-1">
                                    <Link :href="`/admin/users/${user.id}`" class="text-gray-400 hover:text-gray-700 p-1.5 rounded hover:bg-gray-100 transition-colors" title="Ver detalle">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    </Link>
                                    <Link :href="`/admin/users/${user.id}/edit`" class="text-gray-400 hover:text-gray-700 p-1.5 rounded hover:bg-gray-100 transition-colors" title="Editar">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
                                    </Link>
                                    <button @click="deleteUser(user)" class="text-gray-400 hover:text-red-500 p-1.5 rounded hover:bg-red-50 transition-colors" title="Eliminar">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="users.data.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400 text-sm">
                                No se encontraron usuarios con los filtros aplicados.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="users.last_page > 1" class="border-t border-gray-200 px-6 py-4 flex items-center justify-between">
                    <p class="text-sm text-gray-500">Mostrando {{ users.from }}–{{ users.to }} de {{ users.total }}</p>
                    <div class="flex gap-1">
                        <Link v-for="link in users.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
                            class="px-3 py-1.5 text-sm rounded-lg border transition-colors"
                            :class="link.active ? 'border-black bg-black text-white font-medium' : link.url ? 'border-gray-200 text-gray-600 hover:bg-gray-50' : 'border-gray-100 text-gray-300 cursor-not-allowed'" />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
