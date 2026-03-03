<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import QrCode from '@/Components/QrCode.vue';
import { XMarkIcon, TicketIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    events:          { type: Array, default: () => [] },
    selectedEventId: { type: Number, default: null },
    passes:          { type: Object, default: () => ({ data: [] }) },
    stats:           { type: Object, default: () => ({}) },
    passTypes:       { type: Object, default: () => ({}) },
    eventDays:       { type: Array, default: () => [] },
    filters:         { type: Object, default: () => ({}) },
});

// ─── Filtros ────────────────────────────────────────────────────
const eventId  = ref(props.selectedEventId);
const search   = ref(props.filters.search ?? '');
const typeF    = ref(props.filters.type ?? '');
const statusF  = ref(props.filters.status ?? '');

function applyFilters() {
    router.get('/admin/passes', {
        event_id: eventId.value,
        search:   search.value || undefined,
        type:     typeF.value  || undefined,
        status:   statusF.value || undefined,
    }, { preserveScroll: true, preserveState: true });
}

watch(eventId, () => applyFilters());

// ─── Modal crear pase ────────────────────────────────────────────
const showCreate = ref(false);
const userSearch = ref('');
const userResults = ref([]);

const form = useForm({
    event_id:     props.selectedEventId,
    pass_type:    '',
    holder_name:  '',
    holder_email: '',
    user_id:      null,
    valid_days:   null,
    notes:        '',
});

watch(eventId, (val) => { form.event_id = val; });

async function searchUsers() {
    if (userSearch.value.length < 2) { userResults.value = []; return; }
    const res = await fetch(`/admin/api/passes/search-users?q=${encodeURIComponent(userSearch.value)}`);
    userResults.value = await res.json();
}

function selectUser(u) {
    form.user_id      = u.id;
    form.holder_name  = form.holder_name || u.full_name;
    form.holder_email = form.holder_email || u.email;
    userSearch.value  = u.full_name;
    userResults.value = [];
}

function submitCreate() {
    form.post('/admin/passes', {
        onSuccess: () => {
            showCreate.value = false;
            form.reset();
            userSearch.value  = '';
            userResults.value = [];
        },
    });
}

function cancelCreate() {
    showCreate.value = false;
    form.reset();
    userSearch.value  = '';
    userResults.value = [];
}

// ─── Cancelar pase ───────────────────────────────────────────────
function cancelPass(pass) {
    if (!confirm(`¿Cancelar el pase de ${pass.holder_name}?`)) return;
    router.delete(`/admin/passes/${pass.id}`, { preserveScroll: true });
}

// ─── Check-in ────────────────────────────────────────────────────
function checkIn(pass) {
    if (!confirm(`¿Registrar check-in para ${pass.holder_name}?`)) return;
    router.post(`/admin/passes/${pass.id}/check-in`, {}, { preserveScroll: true });
}

// ─── Reactivar pase ──────────────────────────────────────────────
function reactivatePass(pass) {
    if (!confirm(`¿Reactivar el pase de ${pass.holder_name}?`)) return;
    router.post(`/admin/passes/${pass.id}/reactivate`, {}, { preserveScroll: true });
}

// ─── Modal QR ────────────────────────────────────────────────────
const qrPass = ref(null);
function showQr(pass) { qrPass.value = pass; }
function closeQr()    { qrPass.value = null; }

// ─── Mapa de días del evento ─────────────────────────────────────
const dayMap = computed(() => {
    const map = {};
    props.eventDays.forEach(d => { map[d.id] = d.label; });
    return map;
});

function validDaysLabel(validDays) {
    if (!validDays || !validDays.length) return null;
    return validDays.map(id => dayMap.value[id] ?? `Día ${id}`).join(' · ');
}

// ─── UI helpers ──────────────────────────────────────────────────
const statusColors = {
    active:    'bg-green-100 text-green-700',
    used:      'bg-blue-100 text-blue-700',
    cancelled: 'bg-red-100 text-red-700',
};
const statusLabels = {
    active:    'Activo',
    used:      'Usado',
    cancelled: 'Cancelado',
};

const typeColors = {
    model:         'bg-purple-100 text-purple-700',
    designer:      'bg-yellow-100 text-yellow-800',
    staff:         'bg-gray-100 text-gray-700',
    media:         'bg-blue-100 text-blue-700',
    volunteer:     'bg-teal-100 text-teal-700',
    vip:           'bg-amber-100 text-amber-800',
    press:         'bg-indigo-100 text-indigo-700',
    sponsor:       'bg-rose-100 text-rose-700',
    complementary: 'bg-orange-100 text-orange-700',
    guest:         'bg-slate-100 text-slate-700',
};

const selectedEvent = computed(() => props.events.find(e => e.id === eventId.value));
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Pases de Evento</h2>
        </template>

        <div class="space-y-6">

            <!-- Selector de evento -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap gap-4 items-center">
                <div class="flex-1 min-w-48">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Evento</label>
                    <select v-model="eventId" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400">
                        <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                </div>

                <div class="flex-1 min-w-36">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Tipo</label>
                    <select v-model="typeF" @change="applyFilters" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400">
                        <option value="">Todos</option>
                        <option v-for="(label, key) in passTypes" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>

                <div class="flex-1 min-w-36">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Estado</label>
                    <select v-model="statusF" @change="applyFilters" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400">
                        <option value="">Todos</option>
                        <option value="active">Activo</option>
                        <option value="used">Usado</option>
                        <option value="cancelled">Cancelado</option>
                    </select>
                </div>

                <div class="flex-1 min-w-48">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Buscar</label>
                    <div class="flex gap-2">
                        <input
                            v-model="search"
                            @keyup.enter="applyFilters"
                            type="text"
                            placeholder="Nombre, email, QR..."
                            class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400"
                        />
                        <button @click="applyFilters" class="px-3 py-2 bg-gray-100 rounded-lg text-sm hover:bg-gray-200 transition-colors">
                            Buscar
                        </button>
                    </div>
                </div>

                <div class="flex items-end">
                    <button
                        @click="showCreate = true"
                        :disabled="!selectedEventId"
                        class="px-4 py-2 rounded-lg text-sm font-medium text-black transition-colors disabled:opacity-40"
                        style="background-color: #D4AF37;"
                    >
                        + Nuevo Pase
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div v-if="stats.total !== undefined" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ stats.total }}</p>
                    <p class="text-xs text-gray-500 mt-1">Total</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-green-600">{{ stats.active }}</p>
                    <p class="text-xs text-gray-500 mt-1">Activos</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-blue-600">{{ stats.used }}</p>
                    <p class="text-xs text-gray-500 mt-1">Usados</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-red-500">{{ stats.cancelled }}</p>
                    <p class="text-xs text-gray-500 mt-1">Cancelados</p>
                </div>
            </div>

            <!-- Tabla de pases -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div v-if="passes.data && passes.data.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">QR / Titular</th>
                                <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Emitido</th>
                                <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="pass in passes.data" :key="pass.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3">
                                    <p class="font-mono text-xs text-gray-400 mb-0.5">{{ pass.qr_code }}</p>
                                    <p class="font-medium text-gray-900">{{ pass.holder_name }}</p>
                                    <p v-if="pass.holder_email" class="text-xs text-gray-500">{{ pass.holder_email }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="typeColors[pass.pass_type] ?? 'bg-gray-100 text-gray-700'">
                                        {{ pass.pass_type_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="statusColors[pass.status]">
                                        {{ statusLabels[pass.status] }}
                                    </span>
                                    <p v-if="pass.checked_in_at" class="text-xs text-gray-400 mt-0.5">
                                        {{ new Date(pass.checked_in_at).toLocaleString('es-MX') }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-xs text-gray-500">{{ new Date(pass.issued_at).toLocaleDateString('es-MX') }}</p>
                                    <p v-if="pass.issued_by" class="text-xs text-gray-400">{{ pass.issued_by.full_name }}</p>
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <button
                                        @click="showQr(pass)"
                                        class="px-3 py-1 text-xs font-medium bg-gray-200 text-gray-600 rounded-lg hover:bg-gray-300 transition-colors"
                                        title="Ver QR"
                                    >
                                        QR
                                    </button>
                                    <button
                                        v-if="pass.status === 'active'"
                                        @click="checkIn(pass)"
                                        class="px-3 py-1 text-xs font-medium bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors"
                                    >
                                        Check-in
                                    </button>
                                    <button
                                        v-if="pass.status === 'active'"
                                        @click="cancelPass(pass)"
                                        class="px-3 py-1 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        v-if="pass.status === 'cancelled'"
                                        @click="reactivatePass(pass)"
                                        class="px-3 py-1 text-xs font-medium bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors"
                                    >
                                        Reactivar
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty state -->
                <div v-else class="py-16 text-center">
                    <TicketIcon class="mx-auto h-12 w-12 text-gray-300" />
                    <p class="mt-3 text-gray-500 text-sm">No hay pases{{ selectedEvent ? ` para ${selectedEvent.name}` : '' }}.</p>
                    <button @click="showCreate = true" class="mt-3 text-sm font-medium" style="color: #D4AF37;">Crear el primero</button>
                </div>

                <!-- Paginación -->
                <div v-if="passes.last_page > 1" class="px-4 py-3 border-t border-gray-200 flex items-center justify-between">
                    <p class="text-xs text-gray-500">{{ passes.from }}–{{ passes.to }} de {{ passes.total }} pases</p>
                    <div class="flex gap-1">
                        <a v-if="passes.prev_page_url" :href="passes.prev_page_url" class="px-3 py-1 text-xs border border-gray-200 rounded-lg hover:bg-gray-50">Anterior</a>
                        <a v-if="passes.next_page_url" :href="passes.next_page_url" class="px-3 py-1 text-xs border border-gray-200 rounded-lg hover:bg-gray-50">Siguiente</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Crear Pase -->
        <Teleport to="body">
            <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="cancelCreate"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900">Nuevo Pase</h3>
                        <button @click="cancelCreate" class="p-1 text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="h-5 w-5" />
                        </button>
                    </div>

                    <form @submit.prevent="submitCreate" class="space-y-4">

                        <!-- Tipo de pase -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de pase *</label>
                            <select v-model="form.pass_type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400" :class="form.errors.pass_type ? 'border-red-400' : ''">
                                <option value="">Seleccionar tipo...</option>
                                <option v-for="(label, key) in passTypes" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <p v-if="form.errors.pass_type" class="text-red-500 text-xs mt-1">{{ form.errors.pass_type }}</p>
                        </div>

                        <!-- Buscar usuario del sistema -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Usuario del sistema (opcional)</label>
                            <div class="relative">
                                <input
                                    v-model="userSearch"
                                    @input="searchUsers"
                                    type="text"
                                    placeholder="Buscar por nombre o email..."
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400"
                                />
                                <ul v-if="userResults.length" class="absolute z-10 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-1 max-h-40 overflow-y-auto">
                                    <li
                                        v-for="u in userResults"
                                        :key="u.id"
                                        @click="selectUser(u)"
                                        class="px-3 py-2 hover:bg-gray-50 cursor-pointer text-sm"
                                    >
                                        <span class="font-medium">{{ u.full_name }}</span>
                                        <span class="text-gray-400 ml-1">· {{ u.email }}</span>
                                        <span class="text-xs text-gray-400 ml-1">[{{ u.role }}]</span>
                                    </li>
                                </ul>
                            </div>
                            <p v-if="form.user_id" class="text-xs text-green-600 mt-1">Usuario vinculado (ID: {{ form.user_id }})</p>
                        </div>

                        <!-- Nombre titular -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre en el pase *</label>
                            <input
                                v-model="form.holder_name"
                                type="text"
                                placeholder="Nombre completo"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400"
                                :class="form.errors.holder_name ? 'border-red-400' : ''"
                            />
                            <p v-if="form.errors.holder_name" class="text-red-500 text-xs mt-1">{{ form.errors.holder_name }}</p>
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email (opcional)</label>
                            <input
                                v-model="form.holder_email"
                                type="email"
                                placeholder="correo@ejemplo.com"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400"
                                :class="form.errors.holder_email ? 'border-red-400' : ''"
                            />
                            <p v-if="form.errors.holder_email" class="text-red-500 text-xs mt-1">{{ form.errors.holder_email }}</p>
                        </div>

                        <!-- Notas -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notas (opcional)</label>
                            <textarea
                                v-model="form.notes"
                                rows="2"
                                placeholder="Observaciones..."
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400 resize-none"
                            ></textarea>
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button
                                type="button"
                                @click="cancelCreate"
                                class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="flex-1 px-4 py-2 text-sm font-medium text-black rounded-lg transition-colors disabled:opacity-50"
                                style="background-color: #D4AF37;"
                            >
                                {{ form.processing ? 'Creando...' : 'Crear Pase' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AdminLayout>

        <!-- Modal: Ver QR -->
        <Teleport to="body">
            <div v-if="qrPass" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/60" @click="closeQr"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6 flex flex-col items-center gap-4">

                    <!-- Cerrar -->
                    <button @click="closeQr" class="absolute top-4 right-4 p-1 text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="h-5 w-5" />
                    </button>

                    <!-- Tipo badge -->
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold" :class="typeColors[qrPass.pass_type] ?? 'bg-gray-100 text-gray-700'">
                        {{ qrPass.pass_type_label }}
                    </span>

                    <!-- QR -->
                    <div class="p-3 bg-white border-2 border-gray-100 rounded-xl">
                        <QrCode :value="qrPass.qr_code" :size="220" />
                    </div>

                    <!-- Info -->
                    <div class="text-center">
                        <p class="font-semibold text-gray-900 text-lg">{{ qrPass.holder_name }}</p>
                        <p class="font-mono text-sm text-gray-400 mt-0.5">{{ qrPass.qr_code }}</p>
                        <p v-if="qrPass.holder_email" class="text-xs text-gray-500 mt-1">{{ qrPass.holder_email }}</p>
                    </div>

                    <!-- Status -->
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium" :class="statusColors[qrPass.status]">
                        {{ statusLabels[qrPass.status] }}
                    </span>

                    <!-- Días válidos -->
                    <div class="text-center">
                        <p v-if="validDaysLabel(qrPass.valid_days)" class="text-xs text-gray-500 font-medium">
                            Días válidos
                        </p>
                        <p v-if="validDaysLabel(qrPass.valid_days)" class="text-xs text-gray-400 mt-0.5">
                            {{ validDaysLabel(qrPass.valid_days) }}
                        </p>
                        <p v-else class="text-xs text-gray-400">Válido todos los días del evento</p>
                    </div>
                </div>
            </div>
        </Teleport>
</template>
