<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { TrashIcon, ArrowDownTrayIcon, ArrowUpTrayIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    volunteers: Object,
    filters: Object,
    events: Array,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const eventId = ref(props.filters?.event_id || '');

const statusColors = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-red-100 text-red-800',
    pending: 'bg-yellow-100 text-yellow-800',
    applicant: 'bg-blue-100 text-blue-800',
};

const statusLabels = {
    active: 'Activo',
    inactive: 'Inactivo',
    pending: 'Pendiente',
    applicant: 'Aplicante',
};

const experienceLabels = {
    none: 'Sin experiencia',
    some: 'Algo de experiencia',
    experienced: 'Con experiencia',
};

const availabilityLabels = {
    yes: 'Completa',
    no: 'No disponible',
    partially: 'Parcial',
};

const availabilityColors = {
    yes: 'text-green-600',
    no: 'text-red-600',
    partially: 'text-yellow-600',
};

let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});
watch([status, eventId], () => applyFilters());

function applyFilters() {
    router.get('/admin/volunteers', {
        search: search.value,
        status: status.value,
        event_id: eventId.value,
    }, { preserveState: true, replace: true });
}

// --- Export ---
const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (search.value) params.set('search', search.value);
    if (status.value) params.set('status', status.value);
    if (eventId.value) params.set('event_id', eventId.value);
    const qs = params.toString();
    return '/admin/volunteers/export' + (qs ? '?' + qs : '');
});

// --- Import ---
const showImportModal = ref(false);
const importForm = useForm({ file: null, event_id: '' });
const fileInput = ref(null);

function handleFileChange(e) {
    importForm.file = e.target.files[0] ?? null;
}

function submitImport() {
    importForm.post('/admin/volunteers/import', {
        forceFormData: true,
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

function deleteVolunteer(vol) {
    if (confirm(`¿Eliminar a ${vol.first_name} ${vol.last_name}?`)) {
        router.delete(`/admin/volunteers/${vol.id}`);
    }
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Voluntarios</h2>
        </template>

        <div>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Voluntarios</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ volunteers.total }} voluntarios registrados</p>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="exportUrl"
                        class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors text-gray-700">
                        <ArrowDownTrayIcon class="w-4 h-4 text-gray-500" />
                        Exportar Excel
                    </a>
                    <button @click="showImportModal = true"
                        class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors text-gray-700">
                        <ArrowUpTrayIcon class="w-4 h-4 text-gray-500" />
                        Importar Excel
                    </button>
                    <Link href="/admin/volunteers/create"
                        class="px-4 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors">
                        + Crear Voluntario
                    </Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3 mb-6">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Buscar nombre, email o teléfono..."
                    class="flex-1 min-w-48 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400"
                />
                <select v-model="status" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todos los estados</option>
                    <option value="applicant">Aplicante</option>
                    <option value="pending">Pendiente</option>
                    <option value="active">Activo</option>
                    <option value="inactive">Inactivo</option>
                </select>
                <select v-model="eventId" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todos los eventos</option>
                    <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                </select>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-12">ID</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Voluntario</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Ubicación</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Talla</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Experiencia</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Disponibilidad</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Eventos</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Registro</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="vol in volunteers.data" :key="vol.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-sm text-gray-500 font-mono">{{ vol.id }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-9 h-9 rounded-full bg-black flex items-center justify-center text-xs font-bold text-white flex-shrink-0">
                                        {{ vol.first_name?.[0] || '' }}{{ vol.last_name?.[0] || '' }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">{{ vol.first_name }} {{ vol.last_name }}</p>
                                        <p class="text-gray-500 text-xs">{{ vol.email }}</p>
                                        <p v-if="vol.volunteer_profile?.instagram" class="text-gray-400 text-xs">@{{ vol.volunteer_profile.instagram }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ vol.volunteer_profile?.location || '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ vol.volunteer_profile?.tshirt_size || '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ experienceLabels[vol.volunteer_profile?.experience] || '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm" :class="availabilityColors[vol.volunteer_profile?.full_availability] || 'text-gray-600'">
                                {{ availabilityLabels[vol.volunteer_profile?.full_availability] || '—' }}
                            </td>
                            <td class="px-6 py-4">
                                <div v-if="vol.events_as_staff?.length" class="flex flex-wrap gap-1">
                                    <span v-for="ev in vol.events_as_staff" :key="ev.id"
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                        {{ ev.name.length > 20 ? ev.name.substring(0, 20) + '...' : ev.name }}
                                    </span>
                                </div>
                                <span v-else class="text-gray-400 text-sm">—</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                    :class="statusColors[vol.status] || 'bg-gray-100 text-gray-800'">
                                    {{ statusLabels[vol.status] || vol.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-sm">
                                {{ new Date(vol.created_at).toLocaleDateString('es-US') }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end space-x-1">
                                    <button @click="deleteVolunteer(vol)" class="text-gray-400 hover:text-red-500 p-1.5 rounded hover:bg-red-50 transition-colors" title="Eliminar">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="volunteers.data.length === 0">
                            <td colspan="10" class="px-6 py-12 text-center text-gray-400 text-sm">
                                No se encontraron voluntarios con los filtros aplicados.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="volunteers.last_page > 1" class="border-t border-gray-200 px-6 py-4 flex items-center justify-between">
                    <p class="text-sm text-gray-500">Mostrando {{ volunteers.from }}–{{ volunteers.to }} de {{ volunteers.total }}</p>
                    <div class="flex gap-1">
                        <Link v-for="link in volunteers.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
                            class="px-3 py-1.5 text-sm rounded-lg border transition-colors"
                            :class="link.active ? 'border-black bg-black text-white font-medium' : link.url ? 'border-gray-200 text-gray-600 hover:bg-gray-50' : 'border-gray-100 text-gray-300 cursor-not-allowed'" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Importar Excel -->
        <Teleport to="body">
            <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showImportModal = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Importar Voluntarios desde Excel</h3>
                        <button @click="showImportModal = false" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 mb-4">
                        El archivo debe tener columnas: <strong>email</strong> (obligatorio), first_name, last_name, phone, instagram, location, tshirt_size, experience, availability.
                    </p>
                    <form @submit.prevent="submitImport" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Archivo Excel *</label>
                            <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv" @change="handleFileChange"
                                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2" />
                            <p v-if="importForm.errors.file" class="text-red-500 text-xs mt-1">{{ importForm.errors.file }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Asignar a evento</label>
                            <select v-model="importForm.event_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white">
                                <option value="">Sin asignar</option>
                                <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button @click="showImportModal = false" type="button"
                                class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="importForm.processing || !importForm.file"
                                class="px-4 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 disabled:opacity-40">
                                {{ importForm.processing ? 'Importando...' : 'Importar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
