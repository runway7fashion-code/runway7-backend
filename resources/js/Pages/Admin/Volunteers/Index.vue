<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed, nextTick, onMounted, onUnmounted } from 'vue';
import { ArrowDownTrayIcon, ArrowUpTrayIcon, XMarkIcon, PencilSquareIcon, EnvelopeIcon, DevicePhoneMobileIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    volunteers: Object,
    filters: Object,
    events: Array,
    pendingEmailCount: Number,
    pendingSmsCount: Number,
    twilioBalance: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const eventId = ref(props.filters?.event_id || '');

const statusColors = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-red-100 text-red-800',
    rejected: 'bg-orange-100 text-orange-800',
    pending: 'bg-yellow-100 text-yellow-800',
    applicant: 'bg-blue-100 text-blue-800',
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
    router.get('/admin/operations/volunteers', {
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
    return '/admin/operations/volunteers/export' + (qs ? '?' + qs : '');
});

// --- Import ---
const showImportModal = ref(false);
const importForm = useForm({ file: null, event_id: '' });
const fileInput = ref(null);

function handleFileChange(e) {
    importForm.file = e.target.files[0] ?? null;
}

function submitImport() {
    importForm.post('/admin/operations/volunteers/import', {
        forceFormData: true,
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

function isReadyForPending(vol) {
    return vol.status === 'applicant'
        && (vol.events_as_volunteer ?? []).length > 0
        && (vol.volunteer_schedules ?? []).length > 0;
}

const selectedVol = ref(null);

function openEventsModal(vol, e) {
    e.stopPropagation();
    selectedVol.value = vol;
}

function getSchedulesForEvent(vol, eventId) {
    return (vol.volunteer_schedules ?? []).filter(s => s.event_id === eventId);
}

function getPassForEvent(vol, eventId) {
    return (vol.event_passes ?? []).find(p => p.event_id === eventId && p.status !== 'cancelled');
}

function passStatusLabel(s) {
    return { active: 'Activo', cancelled: 'Cancelado', used: 'Usado' }[s] ?? s;
}

function passStatusClass(s) {
    return { active: 'bg-green-100 text-green-700', cancelled: 'bg-red-100 text-red-600', used: 'bg-gray-100 text-gray-500' }[s] ?? 'bg-gray-100 text-gray-500';
}

function formatTime(t) {
    if (!t) return '—';
    const [h, m] = t.split(':');
    const hour = parseInt(h);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const h12 = hour % 12 || 12;
    return `${h12}:${m} ${ampm}`;
}

function updateEventStatus(vol, eventId, newStatus) {
    router.patch(`/admin/operations/volunteers/${vol.id}/events/${eventId}/status`,
        { status: newStatus },
        { preserveScroll: true, onSuccess: () => {
            const fresh = props.volunteers.data.find(v => v.id === vol.id);
            if (fresh) selectedVol.value = fresh;
        }});
}

const statusAlertVol = ref(null);
const statusAlertReason = ref('');

const showEmailInfoModal = ref(false);
const showSmsInfoModal = ref(false);

function updateStatus(vol, newStatus) {
    if (newStatus === 'pending') {
        const hasEvent = (vol.events_as_volunteer ?? []).length > 0;
        const hasSchedules = (vol.volunteer_schedules ?? []).length > 0;
        if (!hasEvent || !hasSchedules) {
            statusAlertVol.value = vol;
            statusAlertReason.value = !hasEvent
                ? 'no tiene un evento asignado. Asígnale un evento primero.'
                : 'no tiene horarios asignados. Asígnale horarios primero.';
            nextTick(() => {
                const sel = document.querySelector(`[data-status-select="${vol.id}"]`);
                if (sel) sel.value = vol.status;
            });
            return;
        }
    }
    router.patch(`/admin/operations/volunteers/${vol.id}/status`, { status: newStatus }, { preserveScroll: true });
}

function sendBulkOnboarding() {
    if (!confirm(`¿Enviar email de onboarding a ${props.pendingEmailCount} voluntarios pendientes?`)) return;
    router.post('/admin/operations/volunteers/send-bulk-onboarding', {}, { preserveScroll: true });
}

function sendBulkSms() {
    if (!confirm(`¿Enviar SMS de onboarding a ${props.pendingSmsCount} voluntarios pendientes?`)) return;
    router.post('/admin/operations/volunteers/send-bulk-onboarding-sms', {}, { preserveScroll: true });
}

function sendOnboardingEmail(vol) {
    if (!confirm(`¿Enviar email de onboarding a ${vol.first_name} ${vol.last_name}?`)) return;
    router.post(`/admin/operations/volunteers/${vol.id}/send-onboarding`, {}, { preserveScroll: true });
}

function sendOnboardingSms(vol) {
    if (!vol.phone) { alert('Este voluntario no tiene teléfono registrado.'); return; }
    if (!confirm(`¿Enviar SMS de onboarding a ${vol.first_name} ${vol.last_name}?`)) return;
    router.post(`/admin/operations/volunteers/${vol.id}/send-onboarding-sms`, {}, { preserveScroll: true });
}

// Auto-refresh cuando llega notificación de nuevo voluntario
function onNotification(e) {
    const type = e.detail?.data?.type;
    if (type === 'new_volunteer_registered') {
        router.reload({ preserveScroll: true });
    }
}
onMounted(() => window.addEventListener('notification:received', onNotification));
onUnmounted(() => window.removeEventListener('notification:received', onNotification));
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
                    <div v-if="twilioBalance" class="flex flex-col items-end px-3 py-1.5 border border-gray-200 rounded-lg bg-white">
                        <span class="text-[10px] text-gray-400 font-medium leading-tight">Twilio Balance</span>
                        <span class="text-sm font-bold text-gray-900 leading-tight">{{ twilioBalance.balance }} {{ twilioBalance.currency }}</span>
                    </div>
                    <div v-if="pendingEmailCount > 0" class="flex items-center gap-1">
                        <button @click="sendBulkOnboarding"
                            class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors text-gray-700">
                            <EnvelopeIcon class="w-4 h-4 text-gray-500" />
                            Enviar emails
                            <span class="bg-amber-100 text-amber-700 text-xs font-bold px-1.5 py-0.5 rounded-full">{{ pendingEmailCount }}</span>
                        </button>
                        <button @click="showEmailInfoModal = true"
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            title="¿Cómo funciona el envío masivo?">
                            <InformationCircleIcon class="w-4 h-4" />
                        </button>
                    </div>

                    <div v-if="pendingSmsCount > 0" class="flex items-center gap-1">
                        <button @click="sendBulkSms"
                            class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors text-gray-700">
                            <DevicePhoneMobileIcon class="w-4 h-4 text-gray-500" />
                            Enviar SMS
                            <span class="bg-green-100 text-green-700 text-xs font-bold px-1.5 py-0.5 rounded-full">{{ pendingSmsCount }}</span>
                        </button>
                        <button @click="showSmsInfoModal = true"
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            title="¿Cómo funciona el envío masivo?">
                            <InformationCircleIcon class="w-4 h-4" />
                        </button>
                    </div>

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
                    <Link href="/admin/operations/volunteers/create"
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
                    <option value="rejected">Rechazado</option>
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
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Voluntario</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Ubicación</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Experiencia</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Disponibilidad</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Eventos</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Registro</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Último Login</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Último Correo</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="vol in volunteers.data" :key="vol.id" class="hover:bg-gray-50 transition-colors cursor-pointer" @click="router.visit(`/admin/operations/volunteers/${vol.id}`)">
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-9 h-9 rounded-full bg-black flex items-center justify-center text-xs font-bold text-white flex-shrink-0">
                                        {{ vol.first_name?.[0] || '' }}{{ vol.last_name?.[0] || '' }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">{{ vol.first_name }} {{ vol.last_name }}</p>
                                        <p class="text-gray-500 text-xs">{{ vol.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ vol.volunteer_profile?.location || '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ experienceLabels[vol.volunteer_profile?.experience] || '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm" :class="availabilityColors[vol.volunteer_profile?.full_availability] || 'text-gray-600'">
                                {{ availabilityLabels[vol.volunteer_profile?.full_availability] || '—' }}
                            </td>
                            <td class="px-6 py-4" @click.stop>
                                <button v-if="vol.events_as_volunteer?.length" @click="openEventsModal(vol, $event)"
                                    class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full hover:bg-blue-100 transition-colors cursor-pointer">
                                    {{ vol.events_as_volunteer.length }} evento{{ vol.events_as_volunteer.length !== 1 ? 's' : '' }}
                                </button>
                                <span v-else class="text-gray-400 text-xs">Sin eventos</span>
                            </td>
                            <td class="px-6 py-4" @click.stop>
                                <!-- Activo: badge estático -->
                                <span v-if="vol.status === 'active'"
                                    class="text-xs font-medium rounded-full px-2.5 py-1 bg-green-100 text-green-800">
                                    Activo
                                </span>
                                <!-- Rechazado: solo puede ir a inactivo -->
                                <select v-else-if="vol.status === 'rejected'" :value="vol.status"
                                    :data-status-select="vol.id" @change="updateStatus(vol, $event.target.value)"
                                    class="text-xs font-medium rounded-full px-2.5 py-1 border-0 cursor-pointer focus:ring-2 focus:ring-black/10 appearance-none text-center bg-orange-100 text-orange-800">
                                    <option value="rejected">Rechazado</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                                <!-- Otros: selector editable -->
                                <select v-else :value="vol.status" :data-status-select="vol.id" @change="updateStatus(vol, $event.target.value)"
                                    class="text-xs font-medium rounded-full px-2.5 py-1 border-0 cursor-pointer focus:ring-2 focus:ring-black/10 appearance-none text-center"
                                    :class="[
                                        statusColors[vol.status] || 'bg-gray-100 text-gray-800',
                                        isReadyForPending(vol) ? 'animate-pulse ring-2 ring-blue-400 ring-offset-1' : '',
                                    ]">
                                    <option value="applicant">Aplicante</option>
                                    <option value="pending">Pendiente</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-sm">
                                {{ new Date(vol.created_at).toLocaleDateString('es-US') }}<br><span class="text-gray-400 text-xs">{{ new Date(vol.created_at).toLocaleTimeString('es-US', { hour: '2-digit', minute: '2-digit' }) }}</span>
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-sm">
                                <template v-if="vol.last_login_at">
                                    {{ new Date(vol.last_login_at).toLocaleDateString('es-US') }}<br><span class="text-gray-400 text-xs">{{ new Date(vol.last_login_at).toLocaleTimeString('es-US', { hour: '2-digit', minute: '2-digit' }) }}</span>
                                </template>
                                <span v-else>—</span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span v-if="vol.welcome_email_sent_at" class="text-green-600">{{ new Date(vol.welcome_email_sent_at).toLocaleDateString('es-US') }}<br><span class="text-green-400 text-xs">{{ new Date(vol.welcome_email_sent_at).toLocaleTimeString('es-US', { hour: '2-digit', minute: '2-digit' }) }}</span></span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-6 py-4" @click.stop>
                                <div class="flex items-center justify-end space-x-1">
                                    <button @click="sendOnboardingEmail(vol)" :disabled="vol.status !== 'pending'"
                                        class="p-1.5 rounded transition-colors"
                                        :class="vol.status === 'pending' ? 'text-gray-400 hover:text-blue-600 hover:bg-blue-50 cursor-pointer' : 'text-gray-200 cursor-not-allowed'"
                                        title="Enviar email onboarding">
                                        <EnvelopeIcon class="w-4 h-4" />
                                    </button>
                                    <button @click="sendOnboardingSms(vol)" :disabled="vol.status !== 'pending'"
                                        class="p-1.5 rounded transition-colors"
                                        :class="vol.status === 'pending' ? 'text-gray-400 hover:text-green-600 hover:bg-green-50 cursor-pointer' : 'text-gray-200 cursor-not-allowed'"
                                        title="Enviar SMS onboarding">
                                        <DevicePhoneMobileIcon class="w-4 h-4" />
                                    </button>
                                    <Link :href="`/admin/operations/volunteers/${vol.id}/edit`" class="p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition-colors cursor-pointer" title="Editar">
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </Link>
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

        <!-- Modal: Eventos del voluntario -->
        <Teleport to="body">
            <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="selectedVol" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="selectedVol = null"></div>
                <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 scale-95 translate-y-2" enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 scale-100 translate-y-0" leave-to-class="opacity-0 scale-95 translate-y-2">
                    <div v-if="selectedVol" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">

                        <!-- Header negro -->
                        <div class="bg-black px-6 py-5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-full overflow-hidden flex-shrink-0 border-2 border-white/20 bg-white/10 flex items-center justify-center text-sm font-bold text-white">
                                        {{ selectedVol.first_name?.[0] }}{{ selectedVol.last_name?.[0] }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-white text-base leading-tight">
                                            {{ selectedVol.first_name }} {{ selectedVol.last_name }}
                                        </p>
                                        <p class="text-white/50 text-xs mt-0.5">
                                            {{ selectedVol.events_as_volunteer.length }}
                                            evento{{ selectedVol.events_as_volunteer.length !== 1 ? 's' : '' }} asignado{{ selectedVol.events_as_volunteer.length !== 1 ? 's' : '' }}
                                        </p>
                                    </div>
                                </div>
                                <button @click="selectedVol = null"
                                    class="w-8 h-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-colors text-white">
                                    <XMarkIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Lista de eventos -->
                        <div class="p-5 space-y-3 max-h-[60vh] overflow-y-auto">
                            <div v-for="(ev, idx) in selectedVol.events_as_volunteer" :key="ev.id"
                                class="border border-gray-100 rounded-xl p-4 hover:border-gray-200 transition-colors bg-gray-50/50">

                                <!-- Nombre evento + estado -->
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-black text-white text-xs font-bold flex items-center justify-center">
                                            {{ idx + 1 }}
                                        </span>
                                        <p class="font-semibold text-gray-900 text-sm leading-tight truncate">{{ ev.name }}</p>
                                    </div>
                                    <span class="flex-shrink-0 text-xs font-medium px-2 py-0.5 rounded-full"
                                        :class="{ 'bg-green-50 text-green-700': ev.status === 'active', 'bg-yellow-50 text-yellow-700': ev.status === 'draft', 'bg-blue-50 text-blue-700': ev.status === 'published' }">
                                        {{ { active: 'Activo', draft: 'Borrador', published: 'Publicado' }[ev.status] || ev.status }}
                                    </span>
                                </div>

                                <!-- Estado en evento (editable) + Rol -->
                                <div class="grid grid-cols-2 gap-x-4 gap-y-2.5 mb-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3.5 h-3.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-[10px] text-gray-400 leading-none mb-1">Estado en evento</p>
                                            <select
                                                :value="ev.pivot?.status || 'assigned'"
                                                @change="updateEventStatus(selectedVol, ev.id, $event.target.value)"
                                                class="w-full border rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-yellow-400 cursor-pointer appearance-none"
                                                :class="{
                                                    'border-red-300 bg-red-50 text-red-600': ev.pivot?.status === 'no_show' || ev.pivot?.status === 'rejected',
                                                    'border-blue-300 bg-blue-50 text-blue-700': ev.pivot?.status === 'checked_in',
                                                    'border-gray-300 bg-white text-gray-600': ev.pivot?.status === 'assigned',
                                                }">
                                                <option value="assigned">Agendado</option>
                                                <option value="checked_in">Check-in</option>
                                                <option value="no_show">No se presentó</option>
                                                <option value="rejected">Rechazado</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3.5 h-3.5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-400 leading-none mb-0.5">Rol</p>
                                            <p class="text-xs font-semibold text-gray-800">{{ ev.pivot?.assigned_role || 'volunteer' }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Área + Pass -->
                                <div class="grid mb-3" :class="ev.pivot?.area && getPassForEvent(selectedVol, ev.id) ? 'grid-cols-2 gap-x-4' : 'grid-cols-1'">
                                    <div v-if="ev.pivot?.area" class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3.5 h-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-400 leading-none mb-0.5">Área</p>
                                            <p class="text-xs font-semibold text-gray-800">{{ ev.pivot.area }}</p>
                                        </div>
                                    </div>
                                    <div v-if="getPassForEvent(selectedVol, ev.id)" class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-400 leading-none mb-0.5">Pase</p>
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-mono text-[10px] text-gray-500">{{ getPassForEvent(selectedVol, ev.id).qr_code }}</span>
                                                <span :class="passStatusClass(getPassForEvent(selectedVol, ev.id).status)"
                                                    class="text-[10px] font-medium px-1.5 py-0.5 rounded">
                                                    {{ passStatusLabel(getPassForEvent(selectedVol, ev.id).status) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Horarios asignados para este evento -->
                                <div v-if="getSchedulesForEvent(selectedVol, ev.id).length"
                                    class="space-y-1.5 border-t border-gray-100 pt-3">
                                    <div v-for="sch in getSchedulesForEvent(selectedVol, ev.id)" :key="sch.id"
                                        class="flex items-center gap-2 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2">
                                        <div class="w-6 h-6 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3 h-3 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-[10px] text-blue-500 leading-none mb-0.5">
                                                {{ sch.event_day?.date ? new Date(sch.event_day.date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }) : 'Día' }}
                                            </p>
                                            <p class="text-xs font-semibold text-blue-700">
                                                {{ formatTime(sch.start_time) }} — {{ formatTime(sch.end_time) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-xs text-gray-400 italic border-t border-gray-100 pt-3">Sin horarios asignados</p>

                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between bg-gray-50">
                            <Link :href="`/admin/operations/volunteers/${selectedVol.id}`"
                                class="text-sm font-semibold text-black hover:underline underline-offset-2">
                                Ver perfil completo →
                            </Link>
                            <button @click="selectedVol = null"
                                class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">
                                Cerrar
                            </button>
                        </div>

                    </div>
                </Transition>
            </div>
            </Transition>
        </Teleport>

        <!-- Modal: Alerta estado pendiente sin evento/horarios -->
        <Teleport to="body">
            <div v-if="statusAlertVol" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/60" @click="statusAlertVol = null"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl max-w-sm w-full mx-4 p-6 z-10 text-center">
                    <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No se puede cambiar a Pendiente</h3>
                    <p class="text-sm text-gray-600 mb-5">
                        El voluntario <span class="font-medium">{{ statusAlertVol.first_name }} {{ statusAlertVol.last_name }}</span>
                        {{ statusAlertReason }}
                    </p>
                    <button @click="statusAlertVol = null"
                        class="px-5 py-2 bg-black text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors">
                        Entendido
                    </button>
                </div>
            </div>
        </Teleport>

        <!-- Modal: Importar Excel -->
        <Teleport to="body">
            <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showImportModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-lg font-bold text-gray-900">Importar Voluntarios desde Excel</h3>
                        <button @click="showImportModal = false" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Download template -->
                    <div class="mb-4">
                        <a href="/admin/operations/volunteers/import-template"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-xl transition-colors">
                            <ArrowDownTrayIcon class="w-4 h-4" />
                            Download Template (.xlsx)
                        </a>
                        <p class="mt-2 text-xs text-gray-500">The template includes all accepted columns with example data.</p>
                    </div>

                    <!-- Formato esperado -->
                    <div class="bg-gray-50 rounded-xl p-4 mb-5 text-xs text-gray-600">
                        <p class="font-semibold text-gray-800 mb-2">Accepted columns:</p>
                        <div class="grid grid-cols-2 gap-1">
                            <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">email</span> <span class="text-red-500">*required</span></span>
                            <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">first_name</span></span>
                            <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">last_name</span></span>
                            <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">phone</span></span>
                            <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">age</span></span>
                            <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">gender</span></span>
                            <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">instagram</span></span>
                            <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">location</span></span>
                            <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">tshirt_size</span></span>
                            <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">experience</span></span>
                            <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">work_style</span></span>
                            <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">availability</span></span>
                            <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">contribution</span></span>
                            <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">resume_link</span></span>
                            <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">notes</span></span>
                        </div>
                        <p class="mt-2 text-gray-500">Solo <strong>email</strong> es obligatorio. Formats: <strong>.xlsx, .xls, .csv</strong></p>
                    </div>

                    <!-- Selector de evento -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Asignar a un evento <span class="text-gray-400 font-normal">(opcional)</span></label>
                        <select v-model="importForm.event_id"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                            <option value="">— Sin asignar a evento —</option>
                            <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                        </select>
                        <p class="mt-1.5 text-xs text-gray-400">
                            Si no seleccionas un evento, los voluntarios se crean sin asignación.
                        </p>
                    </div>

                    <!-- Input archivo -->
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Seleccionar archivo</label>
                        <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv"
                            @change="handleFileChange"
                            class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-black file:text-white hover:file:bg-gray-800 cursor-pointer" />
                        <p v-if="importForm.errors.file" class="mt-1 text-xs text-red-500">{{ importForm.errors.file }}</p>
                    </div>

                    <!-- Acciones -->
                    <div class="flex gap-3">
                        <button @click="showImportModal = false"
                            class="flex-1 py-2.5 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancelar
                        </button>
                        <button @click="submitImport"
                            :disabled="!importForm.file || importForm.processing"
                            class="flex-1 py-2.5 bg-black text-white rounded-xl text-sm font-semibold hover:bg-gray-800 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                            {{ importForm.processing ? 'Importando...' : 'Importar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal info email masivo -->
        <Teleport to="body">
            <div v-if="showEmailInfoModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showEmailInfoModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <EnvelopeIcon class="w-5 h-5 text-amber-600" />
                            </div>
                            <h3 class="text-base font-semibold text-gray-900">¿Cómo funciona el envío masivo de emails?</h3>
                        </div>
                        <button @click="showEmailInfoModal = false" class="text-gray-400 hover:text-gray-600 ml-2">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                    <ul class="space-y-3 text-sm text-gray-600">
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 bg-amber-400 rounded-full mt-1.5 flex-shrink-0"></span>
                            <span>Solo se envía a voluntarios con estado Pendiente que no hayan recibido email de onboarding anteriormente.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 bg-amber-400 rounded-full mt-1.5 flex-shrink-0"></span>
                            <span>El email incluye todos los eventos asignados del voluntario donde su estado en el evento es Agendado.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 bg-amber-400 rounded-full mt-1.5 flex-shrink-0"></span>
                            <span>Si un voluntario tiene 2 eventos, el email muestra ambos con su área y horarios correspondientes.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 bg-amber-400 rounded-full mt-1.5 flex-shrink-0"></span>
                            <span>El envío se procesa en cola — puede tardar unos segundos dependiendo del volumen.</span>
                        </li>
                    </ul>
                    <button @click="showEmailInfoModal = false"
                        class="mt-5 w-full py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors">
                        Entendido
                    </button>
                </div>
            </div>
        </Teleport>

        <!-- Modal info SMS masivo -->
        <Teleport to="body">
            <div v-if="showSmsInfoModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showSmsInfoModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <DevicePhoneMobileIcon class="w-5 h-5 text-green-600" />
                            </div>
                            <h3 class="text-base font-semibold text-gray-900">¿Cómo funciona el envío masivo de SMS?</h3>
                        </div>
                        <button @click="showSmsInfoModal = false" class="text-gray-400 hover:text-gray-600 ml-2">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                    <ul class="space-y-3 text-sm text-gray-600">
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 bg-green-400 rounded-full mt-1.5 flex-shrink-0"></span>
                            <span>Solo se envía a voluntarios con estado Pendiente que tengan teléfono con código de país (+1...) y no hayan recibido SMS anteriormente.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 bg-green-400 rounded-full mt-1.5 flex-shrink-0"></span>
                            <span>El SMS menciona todos los eventos asignados del voluntario donde su estado en el evento es Agendado.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 bg-green-400 rounded-full mt-1.5 flex-shrink-0"></span>
                            <span>El mensaje incluye las credenciales de acceso a la app y los enlaces de descarga.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 bg-green-400 rounded-full mt-1.5 flex-shrink-0"></span>
                            <span>Requiere saldo disponible en Twilio. Si no hay saldo el envío fallará.</span>
                        </li>
                    </ul>
                    <button @click="showSmsInfoModal = false"
                        class="mt-5 w-full py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors">
                        Entendido
                    </button>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
