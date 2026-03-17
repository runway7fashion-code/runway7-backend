<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { MagnifyingGlassIcon, ArrowDownTrayIcon, PlusIcon, TrashIcon, XMarkIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';

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
const showModal      = ref(false);
const modalDays      = ref([]);
const modalEvents    = ref([]);
const needsEntryExit = ref(true);

const userSearch     = ref('');
const searchResults  = ref([]);
const searchLoading  = ref(false);
const selectedUser   = ref(null);

const manualForm = useForm({
    user_id:      '',
    event_id:     '',
    event_day_id: '',
    type:         'entry',
    checked_at:   new Date().toISOString().slice(0, 16),
    notes:        '',
});

function resetModal() {
    showModal.value = false;
    manualForm.reset();
    manualForm.checked_at = new Date().toISOString().slice(0, 16);
    modalDays.value = [];
    modalEvents.value = [];
    userSearch.value = '';
    searchResults.value = [];
    searchLoading.value = false;
    selectedUser.value = null;
    needsEntryExit.value = true;
}

let userSearchTimer;
watch(userSearch, val => {
    selectedUser.value = null;
    searchResults.value = [];
    manualForm.user_id = '';
    manualForm.event_id = '';
    manualForm.event_day_id = '';
    modalEvents.value = [];
    modalDays.value = [];
    clearTimeout(userSearchTimer);
    if (!val || val.length < 2) return;
    searchLoading.value = true;
    userSearchTimer = setTimeout(() => doSearch(val), 400);
});

async function doSearch(q) {
    try {
        const res = await fetch(`/admin/attendance/user-search?q=${encodeURIComponent(q)}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        searchResults.value = await res.json();
    } catch { searchResults.value = []; }
    finally { searchLoading.value = false; }
}

async function selectUser(user) {
    selectedUser.value = user;
    searchResults.value = [];
    manualForm.user_id = user.id;
    manualForm.event_id = '';
    manualForm.event_day_id = '';
    modalDays.value = [];

    try {
        const res = await fetch(`/admin/attendance/user-events?user_id=${user.id}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (!res.ok) { selectedUser.value = { ...user, error: data.error }; return; }
        modalEvents.value = data.events;
        needsEntryExit.value = data.needs_entry_exit;
        if (!data.needs_entry_exit) manualForm.type = 'single';
        // Autoseleccionar evento si solo hay uno
        if (data.events.length === 1) {
            manualForm.event_id = String(data.events[0].id);
        }
    } catch { selectedUser.value = { ...user, error: 'Error de conexión.' }; }
}

function nowInTimezone(tz) {
    try {
        const now = new Date();
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', hour12: false,
        }).formatToParts(now);
        const p = {};
        parts.forEach(({ type, value }) => p[type] = value);
        return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`;
    } catch {
        return new Date().toISOString().slice(0, 16);
    }
}

watch(() => manualForm.event_id, async (val) => {
    manualForm.event_day_id = '';
    modalDays.value = [];
    if (!val) return;

    // Actualizar fecha/hora según timezone del evento seleccionado
    const selectedEvent = modalEvents.value.find(e => String(e.id) === String(val));
    if (selectedEvent?.timezone) {
        manualForm.checked_at = nowInTimezone(selectedEvent.timezone);
    }

    const res = await fetch(`/admin/attendance/event-days/${val}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    });
    const days = await res.json();
    modalDays.value = days;
    // Autoseleccionar día si solo hay uno
    if (days.length === 1) {
        manualForm.event_day_id = String(days[0].id);
    }
});

function submitManual() {
    manualForm.post('/admin/attendance', {
        onSuccess: () => resetModal(),
    });
}

// ─── Editar marcación ──────────────────────────────────────────────────────
const showEditModal  = ref(false);
const editingCheckin = ref(null);

const editForm = useForm({
    type:       '',
    checked_at: '',
    notes:      '',
});

function openEditModal(c) {
    editingCheckin.value = c;
    const tz = c.event?.timezone;
    editForm.type       = c.type;
    editForm.notes      = c.notes ?? '';
    editForm.checked_at = tz
        ? formatForInput(c.checked_at, tz)
        : c.checked_at?.slice(0, 16) ?? '';
    showEditModal.value = true;
}

function formatForInput(dt, tz) {
    try {
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', hour12: false,
        }).formatToParts(new Date(dt));
        const p = {};
        parts.forEach(({ type, value }) => p[type] = value);
        return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`;
    } catch { return dt?.slice(0, 16) ?? ''; }
}

function submitEdit() {
    editForm.put(`/admin/attendance/${editingCheckin.value.id}`, {
        onSuccess: () => { showEditModal.value = false; editingCheckin.value = null; },
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

function formatDate(dt, tz) {
    if (!dt) return '—';
    return new Date(dt).toLocaleDateString('es', {
        timeZone: tz || undefined,
        month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
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
                                {{ formatDate(c.checked_at, c.event?.timezone) }}
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
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="openEditModal(c)"
                                        class="p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition-colors">
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </button>
                                    <button @click="deleteCheckin(c.id)"
                                        class="p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-red-50 hover:text-red-500 transition-colors">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
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
                        <!-- Buscar usuario -->
                        <div class="relative">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Buscar usuario</label>
                            <input v-if="!selectedUser" v-model="userSearch" type="text"
                                placeholder="Nombre, apellido, email, teléfono, instagram, marca..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />

                            <!-- Usuario seleccionado -->
                            <div v-if="selectedUser"
                                class="flex items-center justify-between px-3 py-2 bg-green-50 border border-green-200 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-black flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                        {{ selectedUser.name[0] }}
                                    </div>
                                    <div class="text-xs">
                                        <span class="font-medium text-gray-900">{{ selectedUser.name }}</span>
                                        <span v-if="selectedUser.brand_name" class="text-gray-500 ml-1">· {{ selectedUser.brand_name }}</span>
                                        <span class="text-gray-400 ml-1">· {{ selectedUser.role }}</span>
                                    </div>
                                </div>
                                <button type="button" @click="selectedUser = null; modalEvents = []; manualForm.user_id = ''; manualForm.event_id = ''"
                                    class="text-gray-400 hover:text-red-500 ml-2">
                                    <XMarkIcon class="w-4 h-4" />
                                </button>
                            </div>

                            <!-- Error de eventos -->
                            <p v-if="selectedUser?.error" class="text-red-500 text-xs mt-1">{{ selectedUser.error }}</p>

                            <!-- Resultados de búsqueda -->
                            <div v-if="searchLoading" class="text-gray-400 text-xs mt-1">Buscando...</div>
                            <div v-if="searchResults.length > 0"
                                class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden">
                                <button v-for="u in searchResults" :key="u.id" type="button"
                                    @click="selectUser(u)"
                                    class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 text-left border-b border-gray-100 last:border-0">
                                    <div class="w-7 h-7 rounded-full bg-black flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                        {{ u.name[0] }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">
                                            {{ u.name }}
                                            <span v-if="u.brand_name" class="text-gray-400 font-normal"> · {{ u.brand_name }}</span>
                                        </p>
                                        <p class="text-xs text-gray-400 truncate">{{ u.email }} · {{ u.role }}</p>
                                    </div>
                                </button>
                            </div>
                            <p v-if="!searchLoading && userSearch.length >= 2 && searchResults.length === 0 && !selectedUser"
                                class="text-gray-400 text-xs mt-1">No se encontraron usuarios activos.</p>
                        </div>

                        <!-- Evento (solo si usuario seleccionado) -->
                        <div v-if="selectedUser && !selectedUser.error">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Evento</label>
                            <div v-if="modalEvents.length === 0" class="text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                Este usuario no tiene eventos válidos para registrar asistencia.
                            </div>
                            <select v-else v-model="manualForm.event_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Seleccionar —</option>
                                <option v-for="ev in modalEvents" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                            </select>
                            <p v-if="manualForm.errors.event_id" class="text-red-500 text-xs mt-1">{{ manualForm.errors.event_id }}</p>
                        </div>

                        <!-- Día -->
                        <div v-if="selectedUser && manualForm.event_id">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Día</label>
                            <select v-model="manualForm.event_day_id" :disabled="modalDays.length === 0"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 disabled:opacity-40">
                                <option value="">— Seleccionar —</option>
                                <option v-for="d in modalDays" :key="d.id" :value="d.id">{{ d.label }}</option>
                            </select>
                            <p v-if="manualForm.errors.event_day_id" class="text-red-500 text-xs mt-1">{{ manualForm.errors.event_day_id }}</p>
                        </div>

                        <!-- Tipo (solo para volunteer/staff) -->
                        <div v-if="selectedUser && manualForm.event_id && needsEntryExit">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                            <select v-model="manualForm.type"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="entry">Entrada</option>
                                <option value="exit">Salida</option>
                            </select>
                        </div>

                        <!-- Fecha/hora -->
                        <div v-if="selectedUser && manualForm.event_id">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha y hora</label>
                            <input v-model="manualForm.checked_at" type="datetime-local"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="manualForm.errors.checked_at" class="text-red-500 text-xs mt-1">{{ manualForm.errors.checked_at }}</p>
                        </div>

                        <!-- Notas -->
                        <div v-if="selectedUser && manualForm.event_id">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notas <span class="text-gray-400 font-normal">(opcional)</span></label>
                            <input v-model="manualForm.notes" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>

                        <div class="flex gap-3 pt-1">
                            <button type="button" @click="resetModal"
                                class="flex-1 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit"
                                :disabled="manualForm.processing || !selectedUser || !manualForm.event_id || !manualForm.event_day_id"
                                class="flex-1 py-2.5 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40">
                                Registrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal editar marcación -->
        <Teleport to="body">
            <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showEditModal = false" />
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">Editar marcación</h3>
                        <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Usuario (solo lectura) -->
                    <div class="bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-600">
                        <span class="font-medium text-gray-800">{{ editingCheckin?.user?.first_name }} {{ editingCheckin?.user?.last_name }}</span>
                        · {{ editingCheckin?.event?.name }}
                    </div>

                    <form @submit.prevent="submitEdit" class="space-y-4">
                        <!-- Tipo -->
                        <div v-if="editingCheckin && ['volunteer','staff'].includes(editingCheckin.user?.role)">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                            <select v-model="editForm.type"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="entry">Entrada</option>
                                <option value="exit">Salida</option>
                            </select>
                        </div>

                        <!-- Fecha y hora -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha y hora</label>
                            <input v-model="editForm.checked_at" type="datetime-local"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="editForm.errors.checked_at" class="text-red-500 text-xs mt-1">{{ editForm.errors.checked_at }}</p>
                        </div>

                        <!-- Notas -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notas <span class="text-gray-400 font-normal">(opcional)</span></label>
                            <input v-model="editForm.notes" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10"
                                placeholder="Agregar nota..." />
                        </div>

                        <div class="flex gap-3 pt-1">
                            <button type="button" @click="showEditModal = false"
                                class="flex-1 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="editForm.processing"
                                class="flex-1 py-2.5 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40">
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
