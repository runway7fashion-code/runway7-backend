<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    designers:  Object,
    events:     Array,
    categories: Array,
    packages:   Array,
    salesReps:  Array,
    countries:  Array,
    filters:    Object,
});

const search   = ref(props.filters.search    ?? '');
const event    = ref(props.filters.event      ?? '');
const category = ref(props.filters.category   ?? '');
const pkg      = ref(props.filters.package    ?? '');
const salesRep  = ref(props.filters.sales_rep  ?? '');
const materials = ref(props.filters.materials  ?? '');
const country   = ref(props.filters.country    ?? '');

let timer = null;
function applyFilters() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/admin/designers', {
            search:    search.value    || undefined,
            event:     event.value     || undefined,
            category:  category.value  || undefined,
            package:   pkg.value       || undefined,
            sales_rep: salesRep.value  || undefined,
            materials: materials.value || undefined,
            country:   country.value   || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}

watch([search, event, category, pkg, salesRep, materials, country], applyFilters);

function statusBadge(status) {
    return {
        active:   'bg-green-100 text-green-700',
        inactive: 'bg-red-100 text-red-700',
        pending:  'bg-yellow-100 text-yellow-700',
    }[status] ?? 'bg-gray-100 text-gray-600';
}

function updateDesignerStatus(d, newStatus) {
    router.patch(`/admin/designers/${d.id}/status`, { status: newStatus }, { preserveScroll: true });
}

function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}

function materialsProgress(materials) {
    if (!materials || materials.length === 0) return 0;
    const done = materials.filter(m => m.status === 'confirmed' || m.status === 'submitted').length;
    return Math.round((done / materials.length) * 100);
}

function progressColor(pct) {
    if (pct === 100) return 'bg-green-500';
    if (pct >= 50)   return 'bg-yellow-400';
    return 'bg-gray-300';
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Diseñadores</h2>
        </template>

        <div>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Diseñadores</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ designers.total }} diseñadores registrados</p>
                </div>
                <Link href="/admin/designers/create" class="px-4 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors">
                    + Crear Diseñador
                </Link>
            </div>

            <!-- Filtros -->
            <div class="flex flex-wrap gap-3 mb-6">
                <input v-model="search" type="text" placeholder="Buscar por nombre, email, marca..."
                    class="flex-1 min-w-48 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400" />

                <select v-model="event"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todos los eventos</option>
                    <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>

                <select v-model="category"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todas las categorías</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>

                <select v-model="pkg"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todos los paquetes</option>
                    <option v-for="p in packages" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>

                <select v-model="salesRep"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todos los vendedores</option>
                    <option v-for="s in salesReps" :key="s.id" :value="s.id">{{ s.first_name }} {{ s.last_name }}</option>
                </select>

                <select v-model="materials"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Material: Todos</option>
                    <option value="complete">Completo</option>
                    <option value="incomplete">Incompleto</option>
                </select>

                <select v-model="country"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todos los paises</option>
                    <option v-for="c in countries" :key="c" :value="c">{{ c }}</option>
                </select>
            </div>

            <!-- Tabla -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-5 py-3 font-medium text-gray-500">Designer / Brand</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Email</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Phone</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Category</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Events</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Materials</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="designers.data.length === 0">
                            <td colspan="8" class="text-center text-gray-400 py-12">No hay diseñadores registrados.</td>
                        </tr>
                        <tr v-for="d in designers.data" :key="d.id"
                            class="hover:bg-gray-50 cursor-pointer transition-colors"
                            @click="router.visit(`/admin/designers/${d.id}`)">
                            <!-- Foto + Nombre + Brand -->
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full overflow-hidden flex-shrink-0 bg-gray-100">
                                        <img v-if="storageUrl(d.profile_picture)"
                                            :src="storageUrl(d.profile_picture)"
                                            class="w-full h-full object-cover" />
                                        <div v-else class="w-full h-full flex items-center justify-center text-xs font-bold text-gray-500">
                                            {{ d.first_name?.[0] }}{{ d.last_name?.[0] }}
                                        </div>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">{{ d.first_name }} {{ d.last_name }}</p>
                                        <p class="text-gray-400 text-xs">{{ d.designer_profile?.brand_name ?? d.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <!-- Email -->
                            <td class="px-4 py-3">
                                <span class="text-gray-500 text-xs">{{ d.email }}</span>
                            </td>
                            <!-- Teléfono -->
                            <td class="px-4 py-3">
                                <span class="text-gray-500 text-xs">{{ d.phone ?? '—' }}</span>
                            </td>
                            <!-- Categoría -->
                            <td class="px-4 py-3">
                                <span v-if="d.designer_profile?.category"
                                    class="text-xs bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full font-medium">
                                    {{ d.designer_profile.category.name }}
                                </span>
                                <span v-else class="text-gray-400 text-xs">Sin categoría</span>
                            </td>
                            <!-- Eventos -->
                            <td class="px-4 py-3">
                                <span v-if="d.events_as_designer?.length"
                                    class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full">
                                    {{ d.events_as_designer.length }} evento{{ d.events_as_designer.length !== 1 ? 's' : '' }}
                                </span>
                                <span v-else class="text-gray-400 text-xs">Sin eventos</span>
                            </td>
                            <!-- Materials -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                        <div :class="progressColor(materialsProgress(d.designer_materials))"
                                            class="h-full rounded-full transition-all"
                                            :style="{ width: materialsProgress(d.designer_materials) + '%' }"></div>
                                    </div>
                                    <span class="text-xs text-gray-500">{{ materialsProgress(d.designer_materials) }}%</span>
                                </div>
                            </td>
                            <!-- Estado -->
                            <td class="px-4 py-3" @click.stop>
                                <span v-if="d.status === 'active'"
                                    class="text-xs font-medium rounded-full px-2 py-0.5 bg-green-100 text-green-700">
                                    Activo
                                </span>
                                <select v-else :value="d.status"
                                    @change="updateDesignerStatus(d, $event.target.value)"
                                    :class="statusBadge(d.status)"
                                    class="text-xs font-medium rounded-full px-2 py-0.5 border-0 outline-none cursor-pointer appearance-none">
                                    <option value="inactive">Inactivo</option>
                                    <option value="pending">Pendiente</option>
                                </select>
                            </td>
                            <!-- Acciones -->
                            <td class="px-4 py-3" @click.stop>
                                <div class="flex gap-2 justify-end">
                                    <Link :href="`/admin/designers/${d.id}/edit`"
                                        class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                        Editar
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Paginación -->
                <div v-if="designers.last_page > 1" class="border-t border-gray-100 px-5 py-3 flex items-center justify-between text-sm text-gray-500">
                    <span>{{ designers.from }}–{{ designers.to }} de {{ designers.total }} diseñadores</span>
                    <div class="flex gap-1">
                        <Link v-if="designers.prev_page_url" :href="designers.prev_page_url"
                            class="px-3 py-1 border border-gray-200 rounded-lg hover:bg-gray-50">← Anterior</Link>
                        <Link v-if="designers.next_page_url" :href="designers.next_page_url"
                            class="px-3 py-1 border border-gray-200 rounded-lg hover:bg-gray-50">Siguiente →</Link>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
