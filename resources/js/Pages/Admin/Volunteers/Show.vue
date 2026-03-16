<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import QrCode from '@/Components/QrCode.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    ArrowLeftIcon, EnvelopeIcon, PhoneIcon, TrashIcon, DevicePhoneMobileIcon,
    PlusIcon, XMarkIcon, CalendarIcon, ClockIcon, MapPinIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    volunteer: Object,
    events: Array,
});

const profile = props.volunteer.volunteer_profile;
const assignedEvents = props.volunteer.events_as_staff ?? [];
const schedules = props.volunteer.volunteer_schedules ?? [];
const commLogs = props.volunteer.communication_logs ?? [];
const passes = props.volunteer.event_passes ?? [];

function getPassForEvent(eventId) {
    return passes.find(p => p.event_id === eventId && p.status !== 'cancelled');
}

// Pass modal
const passModal = ref(null);
function openPassModal(ev) {
    const pass = getPassForEvent(ev.id);
    if (pass) passModal.value = { ...pass, event_name: ev.name, schedules: getSchedulesForEvent(ev.id) };
}
function closePassModal() { passModal.value = null; }

function passStatusClass(s) {
    return { active: 'bg-green-50 text-green-700', cancelled: 'bg-red-50 text-red-600', used: 'bg-gray-100 text-gray-500' }[s] ?? 'bg-gray-100 text-gray-500';
}
function passStatusLabel(s) {
    return { active: 'Activo', cancelled: 'Cancelado', used: 'Usado' }[s] ?? s;
}

// Labels
function genderLabel(g) {
    return { female: 'Femenino', male: 'Masculino', non_binary: 'No binario' }[g] ?? '—';
}
function experienceLabel(e) {
    return { none: 'Sin experiencia', some: 'Algo de experiencia', experienced: 'Con experiencia' }[e] ?? '—';
}
function workStyleLabel(w) {
    return { multitask: 'Multitarea / Dinámico', structured: 'Estructurado' }[w] ?? '—';
}
function availabilityLabel(a) {
    return { yes: 'Completa', no: 'No disponible', partially: 'Parcial' }[a] ?? '—';
}
function availabilityColor(a) {
    return { yes: 'text-green-600', no: 'text-red-600', partially: 'text-yellow-600' }[a] ?? 'text-gray-600';
}
function statusBadgeClass(s) {
    return { active: 'bg-green-50 text-green-700', inactive: 'bg-red-50 text-red-600', pending: 'bg-yellow-50 text-yellow-700', applicant: 'bg-purple-50 text-purple-700' }[s] ?? 'bg-gray-50 text-gray-600';
}
function statusBadgeLabel(s) {
    return { active: 'Activo', inactive: 'Inactivo', pending: 'Pendiente', applicant: 'Aplicante' }[s] ?? s;
}
function commStatusClass(s) {
    return { queued: 'bg-yellow-100 text-yellow-700', sent: 'bg-green-100 text-green-700', failed: 'bg-red-100 text-red-700' }[s] ?? 'bg-gray-100 text-gray-600';
}
function commStatusLabel(s) {
    return { queued: 'En cola', sent: 'Enviado', failed: 'Fallido' }[s] ?? s;
}
function formatTime(t) {
    if (!t) return '—';
    const [h, m] = t.split(':');
    const hour = parseInt(h);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const h12 = hour % 12 || 12;
    return `${h12}:${m} ${ampm}`;
}

function getSchedulesForEvent(eventId) {
    return schedules.filter(s => s.event_id === eventId);
}

function formatDayDate(date) {
    if (!date) return '—';
    const d = new Date(date);
    if (isNaN(d)) return date;
    return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
}

// Status change
function updateStatus(newStatus) {
    if (!confirm(`¿Cambiar estado a "${statusBadgeLabel(newStatus)}"?`)) return;
    router.patch(`/admin/volunteers/${props.volunteer.id}/status`, { status: newStatus }, { preserveScroll: true });
}

// Assign event
const showAssignModal = ref(false);
const assignForm = useForm({ event_id: '', area: '' });
function submitAssignEvent() {
    assignForm.post(`/admin/volunteers/${props.volunteer.id}/assign-event`, {
        preserveScroll: true,
        onSuccess: () => { showAssignModal.value = false; assignForm.reset(); },
    });
}

// Remove event
function removeEvent(eventId, eventName) {
    if (!confirm(`¿Quitar a ${props.volunteer.first_name} del evento "${eventName}"?`)) return;
    router.delete(`/admin/volunteers/${props.volunteer.id}/remove-event/${eventId}`, { preserveScroll: true });
}

// Add schedule
const showScheduleModal = ref(false);
const scheduleForm = useForm({ event_id: '', event_day_id: '', start_time: '', end_time: '' });
const selectedEventDays = ref([]);

function onScheduleEventChange() {
    const ev = props.events.find(e => e.id == scheduleForm.event_id);
    selectedEventDays.value = ev?.event_days ?? [];
    scheduleForm.event_day_id = '';
}

function submitSchedule() {
    scheduleForm.post(`/admin/volunteers/${props.volunteer.id}/schedules`, {
        preserveScroll: true,
        onSuccess: () => { showScheduleModal.value = false; scheduleForm.reset(); selectedEventDays.value = []; },
    });
}

function removeSchedule(scheduleId) {
    if (!confirm('¿Eliminar este horario?')) return;
    router.delete(`/admin/volunteers/${props.volunteer.id}/schedules/${scheduleId}`, { preserveScroll: true });
}

// Onboarding email
function sendOnboarding() {
    if (!confirm(`¿Enviar email de onboarding a ${props.volunteer.first_name}?`)) return;
    router.post(`/admin/volunteers/${props.volunteer.id}/send-onboarding`, {}, { preserveScroll: true });
}

// Onboarding SMS
function sendSms() {
    if (!confirm(`¿Enviar SMS de onboarding a ${props.volunteer.first_name}?`)) return;
    router.post(`/admin/volunteers/${props.volunteer.id}/send-onboarding-sms`, {}, { preserveScroll: true });
}

// Delete
const showDeleteModal = ref(false);
function deleteVolunteer() {
    router.delete(`/admin/volunteers/${props.volunteer.id}`, {
        onSuccess: () => { showDeleteModal.value = false; },
    });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/volunteers" class="flex items-center gap-1 text-gray-400 hover:text-gray-600 text-sm">
                    <ArrowLeftIcon class="w-4 h-4" /> Voluntarios
                </Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">{{ volunteer.first_name }} {{ volunteer.last_name }}</h2>
            </div>
        </template>

        <div class="max-w-5xl mx-auto space-y-6">

            <!-- Header -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex gap-6">
                    <!-- Avatar -->
                    <div class="flex-shrink-0">
                        <div class="w-24 h-24 rounded-full overflow-hidden bg-gray-100 flex items-center justify-center">
                            <span class="text-2xl font-bold text-gray-400">{{ volunteer.first_name?.[0] }}{{ volunteer.last_name?.[0] }}</span>
                        </div>
                    </div>
                    <!-- Info -->
                    <div class="flex-1">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">{{ volunteer.first_name }} {{ volunteer.last_name }}</h3>
                                <p class="text-gray-500 text-sm">
                                    {{ genderLabel(profile?.gender) }}
                                    <span v-if="profile?.age"> · {{ profile.age }} años</span>
                                    <span v-if="profile?.location"> · {{ profile.location }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span :class="statusBadgeClass(volunteer.status)" class="text-xs font-medium rounded-lg px-3 py-1.5">
                                    {{ statusBadgeLabel(volunteer.status) }}
                                </span>
                                <Link :href="`/admin/volunteers/${volunteer.id}/edit`"
                                    class="px-4 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors">
                                    Editar
                                </Link>
                                <button @click="showDeleteModal = true"
                                    class="px-3 py-1.5 border border-red-200 text-red-600 rounded-lg text-xs font-medium hover:bg-red-50 transition-colors flex items-center gap-1">
                                    <TrashIcon class="w-3.5 h-3.5" /> Eliminar
                                </button>
                            </div>
                        </div>

                        <!-- Contact -->
                        <div class="mt-3 flex flex-wrap gap-3 text-sm">
                            <a :href="`mailto:${volunteer.email}`" class="flex items-center gap-1.5 text-gray-600 hover:text-black">
                                <EnvelopeIcon class="w-4 h-4" /> {{ volunteer.email }}
                            </a>
                            <a v-if="volunteer.phone" :href="`tel:${volunteer.phone}`" class="flex items-center gap-1.5 text-gray-600 hover:text-black">
                                <PhoneIcon class="w-4 h-4" /> {{ volunteer.phone }}
                            </a>
                            <a v-if="profile?.instagram" :href="`https://instagram.com/${profile.instagram}`" target="_blank"
                                class="flex items-center gap-1 text-pink-600 hover:text-pink-700">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                </svg>
                                {{ profile.instagram }}
                            </a>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Perfil detallado -->
            <div class="grid grid-cols-2 gap-6">
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="font-semibold text-gray-800 mb-4">Detalles del Voluntario</h4>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Talla Camiseta</dt><dd class="font-medium">{{ profile?.tshirt_size || '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Experiencia</dt><dd class="font-medium">{{ experienceLabel(profile?.experience) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Estilo de Trabajo</dt><dd class="font-medium">{{ workStyleLabel(profile?.comfortable_fast_paced) }}</dd></div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Disponibilidad</dt>
                            <dd class="font-medium" :class="availabilityColor(profile?.full_availability)">{{ availabilityLabel(profile?.full_availability) }}</dd>
                        </div>
                        <div v-if="profile?.resume_link" class="flex justify-between">
                            <dt class="text-gray-500">Resume</dt>
                            <dd><a :href="profile.resume_link" target="_blank" class="text-blue-600 hover:underline text-xs">Ver resume</a></dd>
                        </div>
                        <div class="flex justify-between"><dt class="text-gray-500">Registro</dt><dd class="font-medium">{{ new Date(volunteer.created_at).toLocaleDateString('es-US') }}</dd></div>
                    </dl>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="font-semibold text-gray-800 mb-4">Contribución</h4>
                    <p class="text-sm text-gray-600 whitespace-pre-line">{{ profile?.contribution || 'No especificado.' }}</p>

                    <h4 class="font-semibold text-gray-800 mt-6 mb-2">Notas Internas</h4>
                    <p class="text-sm text-gray-500 italic whitespace-pre-line">{{ profile?.notes || 'Sin notas.' }}</p>
                </div>
            </div>

            <!-- Eventos asignados (un card por evento) -->
            <div v-for="ev in assignedEvents" :key="ev.id" class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h4 class="font-semibold text-gray-900">{{ ev.name }}</h4>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Estado: {{ { assigned: 'Agendado', checked_in: 'Check-in', completed: 'Completado', rejected: 'Rechazado', no_show: 'No se presentó' }[ev.pivot?.status] || ev.pivot?.status }}
                        </p>
                    </div>
                </div>

                <!-- Área -->
                <div v-if="ev.pivot?.area" class="flex items-center gap-2 mb-4 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                    <MapPinIcon class="w-4 h-4 text-amber-500 flex-shrink-0" />
                    <span class="text-sm font-medium text-amber-700">{{ ev.pivot.area }}</span>
                </div>

                <!-- Pase -->
                <div v-if="getPassForEvent(ev.id)" class="flex items-center gap-2 bg-gray-50 rounded-lg px-3 py-2 mb-4">
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5z" />
                    </svg>
                    <span class="font-mono text-[11px] text-gray-500 tracking-wide">{{ getPassForEvent(ev.id).qr_code }}</span>
                    <span :class="passStatusClass(getPassForEvent(ev.id).status)"
                        class="text-[10px] font-medium px-1.5 py-0.5 rounded">
                        {{ passStatusLabel(getPassForEvent(ev.id).status) }}
                    </span>
                    <button @click="openPassModal(ev)"
                        class="ml-auto flex items-center gap-0.5 text-[11px] text-indigo-500 hover:text-indigo-700 font-medium cursor-pointer">
                        Ver QR →
                    </button>
                </div>

                <!-- Horarios -->
                <div>
                    <p class="text-xs font-medium text-gray-500 mb-2">Horarios</p>
                    <div v-if="getSchedulesForEvent(ev.id).length" class="space-y-1.5">
                        <div v-for="sch in getSchedulesForEvent(ev.id)" :key="sch.id"
                            class="flex items-center gap-3 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2">
                            <CalendarIcon class="w-4 h-4 text-blue-500 flex-shrink-0" />
                            <span class="text-sm text-blue-700">{{ formatDayDate(sch.event_day?.date) }}</span>
                            <ClockIcon class="w-4 h-4 text-blue-500 flex-shrink-0" />
                            <span class="text-sm font-medium text-blue-700">{{ formatTime(sch.start_time) }} — {{ formatTime(sch.end_time) }}</span>
                        </div>
                    </div>
                    <p v-else class="text-xs text-gray-400 italic">Sin horarios asignados.</p>
                </div>
            </div>

            <div v-if="!assignedEvents.length" class="bg-white rounded-2xl border border-gray-200 p-6">
                <p class="text-sm text-gray-400 italic text-center">No hay eventos asignados.</p>
            </div>

            <!-- Historial de comunicaciones -->
            <div v-if="commLogs.length" class="bg-white rounded-2xl border border-gray-200 p-6">
                <h4 class="font-semibold text-gray-800 mb-4">Historial de Comunicaciones</h4>
                <div class="space-y-2">
                    <div v-for="log in commLogs" :key="log.id" class="flex items-center justify-between text-sm py-2 border-b border-gray-50 last:border-0">
                        <div class="flex items-center gap-2">
                            <EnvelopeIcon v-if="log.type === 'email'" class="w-4 h-4 text-gray-400" />
                            <DevicePhoneMobileIcon v-else class="w-4 h-4 text-gray-400" />
                            <span class="text-gray-600 capitalize">{{ log.channel?.replace(/_/g, ' ') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span :class="commStatusClass(log.status)" class="text-xs font-medium rounded-full px-2 py-0.5">
                                {{ commStatusLabel(log.status) }}
                            </span>
                            <span class="text-gray-400 text-xs">{{ new Date(log.created_at).toLocaleString('es-US') }}</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Modal: Asignar Evento -->
        <Teleport to="body">
            <div v-if="showAssignModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showAssignModal = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Asignar a Evento</h3>
                    <form @submit.prevent="submitAssignEvent" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Evento</label>
                            <select v-model="assignForm.event_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                                <option value="">Seleccionar...</option>
                                <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Área</label>
                            <input v-model="assignForm.area" type="text" placeholder="ej: Backstage, Front of House..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm" />
                        </div>
                        <div class="flex justify-end gap-3">
                            <button @click="showAssignModal = false" type="button" class="px-4 py-2 border border-gray-200 rounded-lg text-sm">Cancelar</button>
                            <button type="submit" :disabled="assignForm.processing || !assignForm.event_id"
                                class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium disabled:opacity-40">Asignar</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal: Agregar Horario -->
        <Teleport to="body">
            <div v-if="showScheduleModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showScheduleModal = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Agregar Horario</h3>
                    <form @submit.prevent="submitSchedule" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Evento</label>
                            <select v-model="scheduleForm.event_id" @change="onScheduleEventChange"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                                <option value="">Seleccionar...</option>
                                <option v-for="ev in assignedEvents" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Día</label>
                            <select v-model="scheduleForm.event_day_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                                <option value="">Seleccionar...</option>
                                <option v-for="day in selectedEventDays" :key="day.id" :value="day.id">
                                    {{ new Date(day.date + 'T12:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' }) }} — {{ day.type }}
                                </option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Hora Inicio</label>
                                <input v-model="scheduleForm.start_time" type="time" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Hora Fin</label>
                                <input v-model="scheduleForm.end_time" type="time" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm" />
                            </div>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button @click="showScheduleModal = false" type="button" class="px-4 py-2 border border-gray-200 rounded-lg text-sm">Cancelar</button>
                            <button type="submit" :disabled="scheduleForm.processing || !scheduleForm.event_day_id || !scheduleForm.start_time || !scheduleForm.end_time"
                                class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium disabled:opacity-40">Agregar</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal: QR del pase -->
        <Teleport to="body">
            <div v-if="passModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/60" @click="closePassModal"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6 flex flex-col items-center gap-4">
                    <button @click="closePassModal" class="absolute top-4 right-4 p-1 text-gray-400 hover:text-gray-600 cursor-pointer">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                    <p class="text-xs font-medium text-gray-400 text-center">{{ passModal.event_name }}</p>
                    <div class="p-3 bg-white border-2 border-gray-100 rounded-xl">
                        <QrCode :value="passModal.qr_code" :size="220" />
                    </div>
                    <div class="text-center">
                        <p class="font-mono text-sm text-gray-400">{{ passModal.qr_code }}</p>
                    </div>
                    <span :class="passStatusClass(passModal.status)"
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium">
                        {{ passStatusLabel(passModal.status) }}
                    </span>
                    <div v-if="passModal.schedules?.length" class="text-center">
                        <p class="text-xs text-gray-500 font-medium mb-1">Días asignados</p>
                        <p v-for="sch in passModal.schedules" :key="sch.id" class="text-xs text-gray-400">
                            {{ formatDayDate(sch.event_day?.date) }} · {{ formatTime(sch.start_time) }} — {{ formatTime(sch.end_time) }}
                        </p>
                    </div>
                    <p v-else class="text-xs text-gray-400">Sin horarios asignados</p>
                </div>
            </div>
        </Teleport>

        <!-- Modal: Confirmar eliminar -->
        <Teleport to="body">
            <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showDeleteModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <TrashIcon class="w-6 h-6 text-red-500" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">¿Eliminar a {{ volunteer.first_name }} {{ volunteer.last_name }}?</h3>
                    <p class="text-sm text-gray-500 mb-4">Esta acción es permanente y no se puede deshacer.</p>
                    <div class="bg-red-50 border border-red-100 rounded-xl p-4 mb-5 text-left">
                        <p class="text-xs font-semibold text-red-700 mb-2">Se eliminará de forma definitiva:</p>
                        <ul class="text-xs text-red-600 space-y-1">
                            <li>• Cuenta de usuario y datos personales</li>
                            <li>• Perfil de voluntario y notas internas</li>
                            <li>• Asignaciones a eventos y horarios</li>
                            <li>• Historial de comunicaciones</li>
                            <li>• Pases de acceso generados</li>
                        </ul>
                    </div>
                    <div class="flex gap-3">
                        <button @click="showDeleteModal = false" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancelar</button>
                        <button @click="deleteVolunteer" class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Eliminar definitivamente</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
