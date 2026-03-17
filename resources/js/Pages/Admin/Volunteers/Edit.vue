<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { XMarkIcon, PlusIcon, CalendarIcon, ClockIcon, MapPinIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    volunteer: Object,
    events: Array,
});

const profile = props.volunteer.volunteer_profile;
const activeTab = ref(1);

const countryCodes = [
    { code: '+1',   label: 'US/CA +1' },
    { code: '+44',  label: 'UK +44' },
    { code: '+33',  label: 'FR +33' },
    { code: '+39',  label: 'IT +39' },
    { code: '+34',  label: 'ES +34' },
    { code: '+49',  label: 'DE +49' },
    { code: '+55',  label: 'BR +55' },
    { code: '+52',  label: 'MX +52' },
    { code: '+57',  label: 'CO +57' },
    { code: '+51',  label: 'PE +51' },
    { code: '+54',  label: 'AR +54' },
    { code: '+56',  label: 'CL +56' },
    { code: '+91',  label: 'IN +91' },
    { code: '+86',  label: 'CN +86' },
    { code: '+81',  label: 'JP +81' },
    { code: '+82',  label: 'KR +82' },
    { code: '+61',  label: 'AU +61' },
    { code: '+971', label: 'AE +971' },
    { code: '+234', label: 'NG +234' },
    { code: '+27',  label: 'ZA +27' },
];

function parsePhone(full) {
    if (!full || !full.startsWith('+')) return { code: '+1', number: full ?? '' };
    const match = countryCodes.find(c => full.startsWith(c.code));
    if (match) return { code: match.code, number: full.slice(match.code.length) };
    return { code: '+1', number: full.replace(/^\+/, '') };
}

const parsed = parsePhone(props.volunteer.phone);
const phoneCode = ref(parsed.code);
const phoneNumber = ref(parsed.number);

const form = useForm({
    first_name: props.volunteer.first_name || '',
    last_name: props.volunteer.last_name || '',
    email: props.volunteer.email || '',
    phone: props.volunteer.phone || '',
    age: profile?.age || '',
    gender: profile?.gender || 'female',
    location: profile?.location || '',
    instagram: profile?.instagram || '',
    tshirt_size: profile?.tshirt_size || '',
    experience: profile?.experience || '',
    comfortable_fast_paced: profile?.comfortable_fast_paced || '',
    full_availability: profile?.full_availability || '',
    contribution: profile?.contribution || '',
    resume_link: profile?.resume_link || '',
    notes: profile?.notes || '',
});

const ageOptions = Array.from({ length: 63 }, (_, i) => i + 18);

const usStates = [
    'Alabama','Alaska','Arizona','Arkansas','California','Colorado','Connecticut','Delaware',
    'Florida','Georgia','Hawaii','Idaho','Illinois','Indiana','Iowa','Kansas','Kentucky',
    'Louisiana','Maine','Maryland','Massachusetts','Michigan','Minnesota','Mississippi',
    'Missouri','Montana','Nebraska','Nevada','New Hampshire','New Jersey','New Mexico',
    'New York','North Carolina','North Dakota','Ohio','Oklahoma','Oregon','Pennsylvania',
    'Rhode Island','South Carolina','South Dakota','Tennessee','Texas','Utah','Vermont',
    'Virginia','Washington','Washington D.C.','West Virginia','Wisconsin','Wyoming',
    'Puerto Rico','Outside the U.S.',
];

function submit() {
    form.phone = phoneNumber.value ? `${phoneCode.value}${phoneNumber.value.replace(/\D/g, '')}` : '';
    form.put(`/admin/volunteers/${props.volunteer.id}`);
}

// Event management
const assignedEvents = computed(() => props.volunteer.events_as_staff ?? []);
const schedules = computed(() => props.volunteer.volunteer_schedules ?? []);

const assignForm = useForm({ event_id: '', area: '' });
function submitAssignEvent() {
    assignForm.post(`/admin/volunteers/${props.volunteer.id}/assign-event`, { preserveScroll: true });
}

function updateEventArea(eventId, area) {
    router.patch(`/admin/volunteers/${props.volunteer.id}/events/${eventId}/area`, { area }, { preserveScroll: true });
}

let areaTimeout = null;
function onAreaInput(eventId, value) {
    clearTimeout(areaTimeout);
    areaTimeout = setTimeout(() => updateEventArea(eventId, value), 600);
}

function getSchedulesForEvent(eventId) {
    return schedules.value.filter(s => s.event_id === eventId);
}

function removeEvent(eventId, eventName) {
    if (!confirm(`¿Quitar del evento "${eventName}"?`)) return;
    router.delete(`/admin/volunteers/${props.volunteer.id}/remove-event/${eventId}`, { preserveScroll: true });
}

// Schedule management
const showScheduleModal = ref(false);
const scheduleForm = useForm({ event_id: '', event_day_id: '', start_time: '', end_time: '' });
const selectedEventDays = ref([]);

function onScheduleEventChange() {
    // Buscar event_days del evento asignado (que tiene los days cargados)
    const assigned = assignedEvents.value.find(e => e.id == scheduleForm.event_id);
    selectedEventDays.value = assigned?.event_days ?? [];
    scheduleForm.event_day_id = '';
}

function formatDayDate(date) {
    if (!date) return '—';
    const d = new Date(date);
    if (isNaN(d)) return date;
    return d.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });
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

function formatTime(t) {
    if (!t) return '—';
    const [h, m] = t.split(':');
    const hour = parseInt(h);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const h12 = hour % 12 || 12;
    return `${h12}:${m} ${ampm}`;
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="`/admin/volunteers/${volunteer.id}`" class="text-gray-400 hover:text-gray-600 text-sm">&larr; {{ volunteer.first_name }} {{ volunteer.last_name }}</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Editar</h2>
            </div>
        </template>

        <div class="max-w-3xl mx-auto">
            <!-- Tabs -->
            <div class="flex gap-1 bg-gray-100 rounded-xl p-1 mb-6">
                <button v-for="tab in [
                    { n: 1, label: 'Datos Personales' },
                    { n: 2, label: 'Detalles Voluntario' },
                    { n: 3, label: 'Eventos y Horarios' },
                ]" :key="tab.n"
                    type="button"
                    @click="activeTab = tab.n"
                    class="flex-1 py-2 text-sm font-medium rounded-lg transition-all"
                    :class="activeTab === tab.n ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                    {{ tab.label }}
                </button>
            </div>

            <form @submit.prevent="submit" novalidate class="space-y-5">

                <!-- Tab 1: Datos Personales -->
                <div v-show="activeTab === 1" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                            <input v-model="form.first_name" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.first_name" class="mt-1 text-red-500 text-xs">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Apellido</label>
                            <input v-model="form.last_name" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input v-model="form.email" type="email"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.email" class="mt-1 text-red-500 text-xs">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                            <div class="flex gap-2">
                                <select v-model="phoneCode"
                                    class="w-28 border border-gray-300 rounded-lg px-2 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                    <option v-for="c in countryCodes" :key="c.code" :value="c.code">{{ c.label }}</option>
                                </select>
                                <input v-model="phoneNumber" type="tel" placeholder="3055550404"
                                    class="flex-1 border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <p v-if="form.errors.phone" class="mt-1 text-red-500 text-xs">{{ form.errors.phone }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Edad</label>
                            <select v-model="form.age"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Seleccionar —</option>
                                <option v-for="a in ageOptions" :key="a" :value="a">{{ a }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Género</label>
                            <select v-model="form.gender"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="female">Femenino</option>
                                <option value="male">Masculino</option>
                                <option value="non_binary">No binario</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Instagram</label>
                            <input v-model="form.instagram" type="text" placeholder="@usuario"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ubicación</label>
                        <select v-model="form.location"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">— Seleccionar —</option>
                            <option v-for="st in usStates" :key="st" :value="st">{{ st }}</option>
                        </select>
                    </div>
                </div>

                <!-- Tab 2: Detalles Voluntario -->
                <div v-show="activeTab === 2" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="font-semibold text-gray-800">Detalles</h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Talla Camiseta</label>
                            <select v-model="form.tshirt_size"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Seleccionar —</option>
                                <option value="XS">XS</option><option value="S">S</option><option value="M">M</option>
                                <option value="L">L</option><option value="XL">XL</option><option value="XXL">XXL</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Experiencia</label>
                            <select v-model="form.experience"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Seleccionar —</option>
                                <option value="none">Sin experiencia</option>
                                <option value="some">Algo de experiencia</option>
                                <option value="experienced">Con experiencia</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estilo de trabajo</label>
                            <select v-model="form.comfortable_fast_paced"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Seleccionar —</option>
                                <option value="multitask">Multitarea / Dinámico</option>
                                <option value="structured">Estructurado</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Disponibilidad</label>
                            <select v-model="form.full_availability"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Seleccionar —</option>
                                <option value="yes">Completa</option>
                                <option value="no">No disponible</option>
                                <option value="partially">Parcial</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contribución</label>
                        <textarea v-model="form.contribution" rows="3"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Resume Link</label>
                        <input v-model="form.resume_link" type="url" placeholder="https://..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notas Internas</label>
                        <textarea v-model="form.notes" rows="3" placeholder="Notas visibles solo para admin/operation..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                    </div>
                </div>

                <!-- Tab 3: Eventos y Horarios -->
                <div v-show="activeTab === 3" class="space-y-5">
                    <!-- Asignar nuevo evento -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h3 class="font-semibold text-gray-800 mb-3">Asignar Evento</h3>
                        <div class="flex gap-2">
                            <select v-model="assignForm.event_id"
                                class="flex-1 border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                                <option value="">Seleccionar evento...</option>
                                <option v-for="ev in events" :key="ev.id" :value="ev.id">
                                    {{ ev.name }} — {{ { draft: 'Borrador', published: 'Publicado', active: 'Activo', completed: 'Completado' }[ev.status] ?? ev.status }}
                                </option>
                            </select>
                            <input v-model="assignForm.area" type="text" placeholder="Área"
                                class="w-36 border border-gray-300 rounded-lg px-3 py-2.5 text-sm" />
                            <button type="button" @click="submitAssignEvent" :disabled="!assignForm.event_id"
                                class="px-4 py-2.5 bg-black text-white rounded-lg text-sm font-medium disabled:opacity-40 flex items-center gap-1 cursor-pointer">
                                <PlusIcon class="w-4 h-4" /> Asignar
                            </button>
                        </div>
                    </div>

                    <!-- Card por cada evento asignado -->
                    <div v-for="ev in assignedEvents" :key="ev.id"
                        class="bg-white rounded-2xl border border-gray-200 p-6">
                        <!-- Header evento -->
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ ev.name }}</h3>
                                <p class="text-xs text-gray-400 mt-0.5">Estado: {{ ev.pivot?.status || 'assigned' }}</p>
                            </div>
                            <button type="button" @click="removeEvent(ev.id, ev.name)"
                                class="text-red-400 hover:text-red-600 cursor-pointer p-1">
                                <XMarkIcon class="w-4 h-4" />
                            </button>
                        </div>

                        <!-- Área -->
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Área</label>
                            <input type="text" :value="ev.pivot?.area || ''" @input="onAreaInput(ev.id, $event.target.value)"
                                placeholder="ej: Backstage, Front of House..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>

                        <!-- Horarios de este evento -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-xs font-medium text-gray-500">Horarios</label>
                                <button type="button" @click="scheduleForm.event_id = ev.id; onScheduleEventChange(); showScheduleModal = true"
                                    class="flex items-center gap-1 px-2 py-1 bg-gray-100 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-200 cursor-pointer">
                                    <PlusIcon class="w-3 h-3" /> Agregar
                                </button>
                            </div>
                            <div v-if="getSchedulesForEvent(ev.id).length" class="space-y-1.5">
                                <div v-for="sch in getSchedulesForEvent(ev.id)" :key="sch.id"
                                    class="flex items-center justify-between p-2 rounded-lg bg-blue-50 border border-blue-100">
                                    <div class="flex items-center gap-3 text-sm">
                                        <span class="flex items-center gap-1 text-blue-700">
                                            <CalendarIcon class="w-3.5 h-3.5" />
                                            {{ formatDayDate(sch.event_day?.date) }}
                                        </span>
                                        <span class="text-blue-600 font-medium">{{ formatTime(sch.start_time) }} — {{ formatTime(sch.end_time) }}</span>
                                    </div>
                                    <button type="button" @click="removeSchedule(sch.id)" class="text-red-400 hover:text-red-600 cursor-pointer">
                                        <XMarkIcon class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                            <p v-else class="text-xs text-gray-400 italic">Sin horarios asignados.</p>
                        </div>
                    </div>

                    <p v-if="!assignedEvents.length" class="bg-white rounded-2xl border border-gray-200 p-6 text-sm text-gray-400 italic text-center">
                        Sin eventos asignados.
                    </p>
                </div>

                <!-- Botones -->
                <div class="flex justify-between">
                    <Link :href="`/admin/volunteers/${volunteer.id}`"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        Cancelar
                    </Link>
                    <div class="flex gap-3">
                        <button v-if="activeTab > 1" type="button" @click="activeTab--"
                            class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                            &larr; Anterior
                        </button>
                        <button v-if="activeTab < 3" type="button" @click="activeTab++"
                            class="px-6 py-2.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-black transition-colors">
                            Siguiente &rarr;
                        </button>
                        <button v-else type="submit" :disabled="form.processing"
                            class="px-8 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-60 transition-colors">
                            <span v-if="form.processing">Guardando...</span>
                            <span v-else>Guardar Cambios</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Modal: Agregar Horario -->
        <Teleport to="body">
            <div v-if="showScheduleModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showScheduleModal = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Agregar Horario</h3>
                    <form @submit.prevent="submitSchedule" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Día</label>
                            <select v-model="scheduleForm.event_day_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                                <option value="">Seleccionar...</option>
                                <option v-for="day in selectedEventDays" :key="day.id" :value="day.id">
                                    {{ formatDayDate(day.date) }} — {{ day.label }}
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
    </AdminLayout>
</template>
