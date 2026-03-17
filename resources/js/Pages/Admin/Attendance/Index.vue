<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { MagnifyingGlassIcon, ArrowDownTrayIcon, PlusIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    checkins:      Object,
    filters:       Object,
    events:        Array,
    event_days:    Array,
    summary:       Object,
    allowed_roles: Array, // null = sin restricción (admin), array = roles permitidos (operation)
});

// ─── Filtros ───────────────────────────────────────────────────────────────
const search     = ref(props.filters.search     ?? '');
const eventId    = ref(props.filters.event_id   ?? '');
const eventDayId = ref(props.filters.event_day_id ?? '');
const role       = ref(props.filters.role       ?? '');
const method     = ref(props.filters.method     ?? '');
const type       = ref(props.filters.type       ?? '');

let searchTimer;
watch(search, val => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 400);
});
watch([eventId, role, method, type], () => applyFilters());

watch(eventId, async (val) => {
    eventDayId.value = '';
    if (!val) { availableDays.value = []; return; }
    const res = await fetch(`/admin/attendance/event-days/${val}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    });
    availableDays.value = await res.json();
});

watch(eventDayId, () => applyFilters());

const availableDays = ref(props.event_days ?? []);

function applyFilters() {
    router.get('/admin/attendance', {
        search:       search.value     || undefined,
        event_id:     eventId.value    || undefined,
        event_day_id: eventDayId.value || undefined,
        role:         role.value       || undefined,
        method:       method.value     || undefined,
        type:         type.value       || undefined,
    }, { preserveState: true, replace: true });
}

function clearFilters() {
    search.value = ''; eventId.value = ''; eventDayId.value = '';
    role.value = ''; method.value = ''; type.value = '';
    availableDays.value = [];
    applyFilters();
}

// ─── Export ────────────────────────────────────────────────────────────────
function exportData() {
    const params = new URLSearchParams();
    if (eventId.value)    params.set('event_id', eventId.value);
    if (eventDayId.value) params.set('event_day_id', eventDayId.value);
    if (role.value)       params.set('role', role.value);
    if (method.value)     params.set('method', method.value);
    if (search.value)     params.set('search', search.value);
    window.location.href = '/admin/attendance/export?' + params.toString();
}

// ─── Modal marcación manual ────────────────────────────────────────────────
const showModal  = ref(false);
const modalDays  = ref([]);

const manualForm = useForm({
    user_id:      '',
    event_id:     '',
    event_day_id: '',
    type:         'entry',
    checked_at:   new Date().toISOString().slice(0, 16),
    notes:        '',
});

watch(() => manualForm.event_id, async (val) => {
    manualForm.event_day_id = '';
    modalDays.value = [];
    if (!val) return;
    const res = await fetch(`/admin/attendance/event-days/${val}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    });
    modalDays.value = await res.json();
});

function submitManual() {
    manualForm.post('/admin/attendance', {
        onSuccess: () => { showModal.value = false; manualForm.reset(); },
    });
}

// ─── Eliminar ──────────────────────────────────────────────────────────────
function deleteCheckin(id) {
    if (!confirm('¿Eliminar esta marcación?')) return;
    router.delete(`/admin/attendance/${id}`, { preserveScroll: true });
}

// ─── Helpers ───────────────────────────────────────────────────────────────
const roleLabels = {
    admin: 'Admin', model: 'Modelo', designer: 'Diseñador', media: 'Media',
    volunteer: 'Voluntario', staff: 'Staff', attendee: 'Asistente',
    vip: 'VIP', press: 'Prensa', sponsor: 'Sponsor',
};
const roleColors = {
    volunteer: 'bg-purple-100 text-purple-700',
    staff:     'bg-blue-100 text-blue-700',
    model:     'bg-pink-100 text-pink-700',
    designer:  'bg-yellow-100 text-yellow-800',
    media:     'bg-green-100 text-green-700',
    vip:       'bg-amber-100 text-amber-700',
    press:     'bg-cyan-100 text-cyan-700',
    admin:     'bg-gray-100 text-gray-700',
};
const typeLabels  = { entry: 'Entrada', exit: 'Salida', single: 'Asistencia' };
const typeColors  = {
    entry:  'bg-green-100 text-green-700',
    exit:   'bg-red-100 text-red-700',
    single: 'bg-gray-100 text-gray-600',
};
const methodColors = { kiosk: 'bg-blue-50 text-blue-600', manual: 'bg-gray-100 text-gray-500' };

function formatTime(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
}
function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleDateString('es', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}
function initials(c) {
    return (c.user?.first_name?.[0] ?? '') + (c.user?.last_name?.[0] ?? '');
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Asistencia</h2>
        </template>

        <div class="space-y-5">

            <!-- Título y acciones -->
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Asistencia</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ checkins.total }} marcaciones en total</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="exportData"
                        class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors text-gray-700">
                        <ArrowDownTrayIcon class="w-4 h-4 text-gray-500" /> Exportar Excel
                    </button>
                    <button @click="showModal = true"
                        class="px-4 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors">
                        + Registrar manual
                    </button>
                </div>
            </div>

            <!-- Resumen del día -->
            <div class="grid grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ summary.todayCount }}</p>
                    <p class="text-xs text-gray-500 mt-1">Marcaciones hoy</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-green-600">{{ summary.entryCount }}</p>
                    <p class="text-xs text-gray-500 mt-1">Entradas hoy</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-red-500">{{ summary.exitCount }}</p>
                    <p class="text-xs text-gray-500 mt-1">Salidas hoy</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-gray-600">{{ summary.singleCount }}</p>
                    <p class="text-xs text-gray-500 mt-1">Asistencias hoy</p>
                </div>
            </div>

            <!-- Filtros -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4 space-y-3">
                <div class="flex gap-3 flex-wrap">
                    <!-- Búsqueda -->
                    <div class="relative flex-1 min-w-48">
                        <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input v-model="search" type="text" placeholder="Buscar por nombre o email..."
                            class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    </div>
                    <!-- Evento -->
                    <select v-model="eventId"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                        <option value="">Todos los eventos</option>
                        <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                    </select>
                    <!-- Día -->
                    <select v-model="eventDayId" :disabled="availableDays.length === 0"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 disabled:opacity-40">
                        <option value="">Todos los días</option>
                        <option v-for="d in availableDays" :key="d.id" :value="d.id">{{ d.label }}</option>
                    </select>
                    <!-- Rol -->
                    <select v-model="role"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                        <option value="">Todos los roles</option>
                        <template v-if="!allowed_roles || allowed_roles.includes('volunteer')">
                            <option value="volunteer">Voluntario</option>
                        </template>
                        <template v-if="!allowed_roles || allowed_roles.includes('staff')">
                            <option value="staff">Staff</option>
                        </template>
                        <template v-if="!allowed_roles || allowed_roles.includes('model')">
                            <option value="model">Modelo</option>
                        </template>
                        <template v-if="!allowed_roles || allowed_roles.includes('designer')">
                            <option value="designer">Diseñador</option>
                        </template>
                        <template v-if="!allowed_roles || allowed_roles.includes('media')">
                            <option value="media">Media</option>
                        </template>
                        <template v-if="!allowed_roles || allowed_roles.includes('vip')">
                            <option value="vip">VIP</option>
                        </template>
                        <template v-if="!allowed_roles || allowed_roles.includes('press')">
                            <option value="press">Prensa</option>
                        </template>
                    </select>
                    <!-- Tipo -->
                    <select v-model="type"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                        <option value="">Todos los tipos</option>
                        <option value="entry">Entrada</option>
                        <option value="exit">Salida</option>
                        <option value="single">Asistencia</option>
                    </select>
                    <!-- Método -->
                    <select v-model="method"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                        <option value="">Todos los métodos</option>
                        <option value="kiosk">Kiosco</option>
                        <option value="manual">Manual</option>
                    </select>
                    <!-- Limpiar -->
                    <button v-if="search || eventId || eventDayId || role || method || type"
                        @click="clearFilters"
                        class="flex items-center gap-1 px-3 py-2 text-sm text-gray-500 hover:text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">
                        <XMarkIcon class="w-4 h-4" /> Limpiar
                    </button>
                </div>
            </div>

            <!-- Tabla -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Usuario</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Rol / Área</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Evento / Día</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Hora</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Método</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="checkins.data.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-gray-400 text-sm">
                                No hay marcaciones registradas con estos filtros.
                            </td>
                        </tr>
                        <tr v-for="c in checkins.data" :key="c.id" class="hover:bg-gray-50 transition-colors">
                            <!-- Usuario -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-black flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                        {{ initials(c) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 leading-tight">{{ c.user?.first_name }} {{ c.user?.last_name }}</p>
                                        <p class="text-gray-400 text-xs">{{ c.user?.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <!-- Rol / Área -->
                            <td class="px-4 py-3">
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="roleColors[c.user?.role] ?? 'bg-gray-100 text-gray-600'">
                                    {{ roleLabels[c.user?.role] ?? c.user?.role }}
                                </span>
                                <p v-if="c.area" class="text-gray-400 text-xs mt-0.5">{{ c.area }}</p>
                            </td>
                            <!-- Evento / Día -->
                            <td class="px-4 py-3">
                                <p class="text-gray-800 leading-tight">{{ c.event?.name }}</p>
                                <p class="text-gray-400 text-xs">{{ c.event_day?.label }}</p>
                            </td>
                            <!-- Tipo -->
                            <td class="px-4 py-3">
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold"
                                    :class="typeColors[c.type]">
                                    {{ typeLabels[c.type] }}
                                </span>
                            </td>
                            <!-- Hora -->
                            <td class="px-4 py-3 text-gray-700 font-mono text-sm">
                                {{ formatDate(c.checked_at) }}
                            </td>
                            <!-- Método -->
                            <td class="px-4 py-3">
                                <span class="inline-block px-2 py-0.5 rounded text-xs font-medium"
                                    :class="methodColors[c.method]">
                                    {{ c.method === 'kiosk' ? 'Kiosco' : 'Manual' }}
                                </span>
                                <p v-if="c.creator" class="text-gray-400 text-xs mt-0.5">
                                    {{ c.creator.first_name }} {{ c.creator.last_name }}
                                </p>
                            </td>
                            <!-- Acciones -->
                            <td class="px-4 py-3 text-right">
                                <button @click="deleteCheckin(c.id)"
                                    class="text-gray-300 hover:text-red-500 transition-colors">
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Paginación -->
                <div v-if="checkins.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-sm text-gray-500">
                    <span>{{ checkins.from }}–{{ checkins.to }} de {{ checkins.total }} marcaciones</span>
                    <div class="flex gap-1">
                        <Link v-if="checkins.prev_page_url" :href="checkins.prev_page_url"
                            class="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50">← Anterior</Link>
                        <Link v-if="checkins.next_page_url" :href="checkins.next_page_url"
                            class="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50">Siguiente →</Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal marcación manual -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">Registrar marcación manual</h3>
                        <button @click="showModal = false; manualForm.reset()" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <form @submit.prevent="submitManual" class="space-y-3">
                        <!-- User ID -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ID o Email del usuario</label>
                            <input v-model="manualForm.user_id" type="text" placeholder="ID del usuario"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="manualForm.errors.user_id" class="text-red-500 text-xs mt-1">{{ manualForm.errors.user_id }}</p>
                        </div>
                        <!-- Evento -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Evento</label>
                            <select v-model="manualForm.event_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Seleccionar —</option>
                                <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                            </select>
                            <p v-if="manualForm.errors.event_id" class="text-red-500 text-xs mt-1">{{ manualForm.errors.event_id }}</p>
                        </div>
                        <!-- Día -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Día</label>
                            <select v-model="manualForm.event_day_id" :disabled="modalDays.length === 0"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 disabled:opacity-40">
                                <option value="">— Seleccionar —</option>
                                <option v-for="d in modalDays" :key="d.id" :value="d.id">{{ d.label }}</option>
                            </select>
                            <p v-if="manualForm.errors.event_day_id" class="text-red-500 text-xs mt-1">{{ manualForm.errors.event_day_id }}</p>
                        </div>
                        <!-- Tipo -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                            <select v-model="manualForm.type"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="entry">Entrada</option>
                                <option value="exit">Salida</option>
                                <option value="single">Asistencia única</option>
                            </select>
                        </div>
                        <!-- Fecha/hora -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha y hora</label>
                            <input v-model="manualForm.checked_at" type="datetime-local"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="manualForm.errors.checked_at" class="text-red-500 text-xs mt-1">{{ manualForm.errors.checked_at }}</p>
                        </div>
                        <!-- Notas -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notas <span class="text-gray-400 font-normal">(opcional)</span></label>
                            <input v-model="manualForm.notes" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>

                        <div class="flex gap-3 pt-1">
                            <button type="button" @click="showModal = false; manualForm.reset()"
                                class="flex-1 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="manualForm.processing"
                                class="flex-1 py-2.5 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-60">
                                Registrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
