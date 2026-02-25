<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    models:  Object,
    events:  Array,
    filters: Object,
});

const search    = ref(props.filters.search    ?? '');
const event     = ref(props.filters.event     ?? '');
const compcard  = ref(props.filters.compcard  ?? '');
const gender    = ref(props.filters.gender    ?? '');

let timer = null;
function applyFilters() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/admin/models', {
            search:   search.value   || undefined,
            event:    event.value    || undefined,
            compcard: compcard.value || undefined,
            gender:   gender.value   || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}

watch([search, event, compcard, gender], applyFilters);

function progressColor(pct) {
    if (pct === 100) return 'bg-green-500';
    if (pct >= 50)   return 'bg-yellow-400';
    return 'bg-gray-300';
}

function genderLabel(g) {
    return { female: 'Femenino', male: 'Masculino', non_binary: 'No binario' }[g] ?? g ?? '—';
}

function statusBadge(status) {
    return {
        active:   'bg-green-100 text-green-700',
        inactive: 'bg-gray-100 text-gray-600',
        pending:  'bg-yellow-100 text-yellow-700',
    }[status] ?? 'bg-gray-100 text-gray-600';
}

function statusLabel(status) {
    return { active: 'Activa', inactive: 'Inactiva', pending: 'Pendiente' }[status] ?? status;
}

function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Modelos</h2>
        </template>

        <div>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Modelos</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ models.total }} modelos registrados</p>
                </div>
                <Link href="/admin/models/create" class="px-4 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors">
                    + Crear Modelo
                </Link>
            </div>

        <!-- Filtros -->
        <div class="flex flex-wrap gap-3 mb-6">
            <input v-model="search" type="text" placeholder="Buscar por nombre, email, teléfono..."
                class="flex-1 min-w-48 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400" />

            <select v-model="event"
                class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                <option value="">Todos los eventos</option>
                <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>

            <select v-model="gender"
                class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                <option value="">Todos los géneros</option>
                <option value="female">Femenino</option>
                <option value="male">Masculino</option>
                <option value="non_binary">No binario</option>
            </select>

            <select v-model="compcard"
                class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                <option value="">Comp card: todos</option>
                <option value="complete">Comp card completo</option>
                <option value="incomplete">Comp card incompleto</option>
            </select>
        </div>

        <!-- Tabla -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-5 py-3 font-medium text-gray-500">Modelo</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Género / Edad</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Eventos</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Comp Card</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="models.data.length === 0">
                        <td colspan="6" class="text-center text-gray-400 py-12">No hay modelos registradas.</td>
                    </tr>
                    <tr v-for="m in models.data" :key="m.id"
                        class="hover:bg-gray-50 cursor-pointer transition-colors"
                        @click="router.visit(`/admin/models/${m.id}`)">
                        <!-- Foto + Nombre -->
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full overflow-hidden flex-shrink-0 bg-gray-100">
                                    <img v-if="storageUrl(m.profile_picture)"
                                        :src="storageUrl(m.profile_picture)"
                                        class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-xs font-bold text-gray-500">
                                        {{ m.first_name?.[0] }}{{ m.last_name?.[0] }}
                                    </div>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ m.first_name }} {{ m.last_name }}</p>
                                    <p class="text-gray-400 text-xs">{{ m.email }}</p>
                                </div>
                            </div>
                        </td>
                        <!-- Género / Edad -->
                        <td class="px-4 py-3 text-gray-600">
                            <p>{{ genderLabel(m.model_profile?.gender) }}</p>
                            <p class="text-xs text-gray-400">{{ m.model_profile?.age ? m.model_profile.age + ' años' : '—' }}</p>
                        </td>
                        <!-- Eventos -->
                        <td class="px-4 py-3">
                            <span v-if="m.events_as_model_with_casting?.length"
                                class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full">
                                {{ m.events_as_model_with_casting.length }} evento{{ m.events_as_model_with_casting.length !== 1 ? 's' : '' }}
                            </span>
                            <span v-else class="text-gray-400 text-xs">Sin eventos</span>
                        </td>
                        <!-- Comp Card -->
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-20 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                    <div :class="progressColor(m.model_profile?.comp_card_progress ?? 0)"
                                        class="h-full rounded-full transition-all"
                                        :style="`width: ${m.model_profile?.comp_card_progress ?? 0}%`"></div>
                                </div>
                                <span class="text-xs text-gray-500">{{ m.model_profile?.comp_card_progress ?? 0 }}%</span>
                            </div>
                        </td>
                        <!-- Estado -->
                        <td class="px-4 py-3">
                            <span :class="statusBadge(m.status)" class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ statusLabel(m.status) }}
                            </span>
                        </td>
                        <!-- Acciones -->
                        <td class="px-4 py-3" @click.stop>
                            <div class="flex gap-2 justify-end">
                                <Link :href="`/admin/models/${m.id}/edit`"
                                    class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                    Editar
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Paginación -->
            <div v-if="models.last_page > 1" class="border-t border-gray-100 px-5 py-3 flex items-center justify-between text-sm text-gray-500">
                <span>{{ models.from }}–{{ models.to }} de {{ models.total }} modelos</span>
                <div class="flex gap-1">
                    <Link v-if="models.prev_page_url" :href="models.prev_page_url"
                        class="px-3 py-1 border border-gray-200 rounded-lg hover:bg-gray-50">← Anterior</Link>
                    <Link v-if="models.next_page_url" :href="models.next_page_url"
                        class="px-3 py-1 border border-gray-200 rounded-lg hover:bg-gray-50">Siguiente →</Link>
                </div>
            </div>
        </div>

        </div>
    </AdminLayout>
</template>
