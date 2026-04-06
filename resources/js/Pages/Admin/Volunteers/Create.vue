<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    events: Array,
});

const activeTab = ref(1);
const phoneCode = ref('+1');
const phoneNumber = ref('');

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

const form = useForm({
    // Pestaña 1 - Datos personales
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    age: '',
    gender: '',
    location: '',
    instagram: '',
    // Pestaña 2 - Detalles voluntario
    tshirt_size: '',
    experience: '',
    comfortable_fast_paced: '',
    full_availability: '',
    contribution: '',
    resume_link: '',
    notes: '',
    // Pestaña 3 - Eventos
    assignments: [],
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

const eventStatusLabels = { draft: 'Borrador', published: 'Publicado', active: 'Activo', completed: 'Completado' };

// IDs ya seleccionados (para evitar duplicados)
const selectedEventIds = computed(() => form.assignments.map(a => a.event_id).filter(Boolean));

function availableEventsFor(idx) {
    const current = form.assignments[idx]?.event_id;
    return props.events.filter(e => e.id == current || !selectedEventIds.value.includes(e.id));
}

function getEventDays(eventId) {
    const ev = props.events.find(e => e.id == eventId);
    return ev?.event_days ?? [];
}

function addAssignment() {
    form.assignments.push({ event_id: '', area: '', schedules: [] });
}

function removeAssignment(idx) {
    form.assignments.splice(idx, 1);
}

function addSchedule(idx) {
    form.assignments[idx].schedules.push({ event_day_id: '', start_time: '', end_time: '' });
}

function removeSchedule(idx, si) {
    form.assignments[idx].schedules.splice(si, 1);
}

function formatDayDate(date) {
    if (!date) return '—';
    const d = new Date(String(date).substring(0, 10) + 'T12:00:00');
    if (isNaN(d)) return date;
    return d.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });
}

function submit() {
    form.phone = phoneNumber.value ? `${phoneCode.value}${phoneNumber.value.replace(/\D/g, '')}` : '';
    form.post('/admin/operations/volunteers');
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/operations/volunteers" class="text-gray-400 hover:text-gray-600 text-sm">&larr; Voluntarios</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Crear Voluntario</h2>
            </div>
        </template>

        <div class="max-w-3xl mx-auto">
            <!-- Tabs -->
            <div class="flex gap-1 bg-gray-100 rounded-xl p-1 mb-6">
                <button v-for="tab in [
                    { n: 1, label: 'Datos Personales' },
                    { n: 2, label: 'Detalles Voluntario' },
                    { n: 3, label: 'Eventos' },
                ]" :key="tab.n"
                    type="button"
                    @click="activeTab = tab.n"
                    class="flex-1 py-2 text-sm font-medium rounded-lg transition-all"
                    :class="activeTab === tab.n ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                    {{ tab.label }}
                    <span v-if="tab.n === 3 && form.assignments.length > 0"
                        class="ml-1 bg-black text-white text-xs font-bold px-1.5 py-0.5 rounded-full">
                        {{ form.assignments.length }}
                    </span>
                </button>
            </div>

            <form @submit.prevent="submit" novalidate class="space-y-5">

                <!-- Pestaña 1: Datos Personales -->
                <div v-show="activeTab === 1" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                            <input v-model="form.first_name" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.first_name" class="mt-1 text-red-500 text-xs">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Apellido *</label>
                            <input v-model="form.last_name" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.last_name" class="mt-1 text-red-500 text-xs">{{ form.errors.last_name }}</p>
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
                            <p v-if="form.errors.age" class="mt-1 text-red-500 text-xs">{{ form.errors.age }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Género</label>
                            <select v-model="form.gender"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Seleccionar —</option>
                                <option value="female">Femenino</option>
                                <option value="male">Masculino</option>
                                <option value="non_binary">No binario</option>
                            </select>
                            <p v-if="form.errors.gender" class="mt-1 text-red-500 text-xs">{{ form.errors.gender }}</p>
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
                        <p v-if="form.errors.location" class="mt-1 text-red-500 text-xs">{{ form.errors.location }}</p>
                    </div>
                </div>

                <!-- Pestaña 2: Detalles Voluntario -->
                <div v-show="activeTab === 2" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="font-semibold text-gray-800">Detalles</h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Talla Camiseta</label>
                            <select v-model="form.tshirt_size"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Seleccionar —</option>
                                <option value="XS">XS</option>
                                <option value="S">S</option>
                                <option value="M">M</option>
                                <option value="L">L</option>
                                <option value="XL">XL</option>
                                <option value="XXL">XXL</option>
                            </select>
                            <p v-if="form.errors.tshirt_size" class="mt-1 text-red-500 text-xs">{{ form.errors.tshirt_size }}</p>
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
                            <p v-if="form.errors.experience" class="mt-1 text-red-500 text-xs">{{ form.errors.experience }}</p>
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
                            <p v-if="form.errors.comfortable_fast_paced" class="mt-1 text-red-500 text-xs">{{ form.errors.comfortable_fast_paced }}</p>
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
                            <p v-if="form.errors.full_availability" class="mt-1 text-red-500 text-xs">{{ form.errors.full_availability }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contribución</label>
                        <textarea v-model="form.contribution" rows="3"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                        <p v-if="form.errors.contribution" class="mt-1 text-red-500 text-xs">{{ form.errors.contribution }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Resume Link</label>
                        <input v-model="form.resume_link" type="url" placeholder="https://docs.google.com/..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        <p v-if="form.errors.resume_link" class="mt-1 text-red-500 text-xs">{{ form.errors.resume_link }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notas Internas</label>
                        <textarea v-model="form.notes" rows="3" placeholder="Notas visibles solo para admin/operation..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                    </div>
                </div>

                <!-- Pestaña 3: Eventos -->
                <div v-show="activeTab === 3" class="space-y-4">

                    <!-- Estado vacío -->
                    <div v-if="form.assignments.length === 0"
                        class="bg-white rounded-2xl border border-dashed border-gray-300 p-8 text-center">
                        <p class="text-gray-400 text-sm mb-3">No hay eventos asignados todavía</p>
                        <button type="button" @click="addAssignment"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors">
                            <PlusIcon class="w-4 h-4" /> Agregar evento
                        </button>
                    </div>

                    <!-- Card por evento -->
                    <div v-for="(assignment, idx) in form.assignments" :key="idx"
                        class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">

                        <!-- Header card -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-700">Evento {{ idx + 1 }}</span>
                            <button type="button" @click="removeAssignment(idx)"
                                class="text-red-400 hover:text-red-600 transition-colors">
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </div>

                        <!-- Selector de evento -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Evento</label>
                            <select v-model="assignment.event_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Seleccionar —</option>
                                <option v-for="e in availableEventsFor(idx)" :key="e.id" :value="e.id">
                                    {{ e.name }} — {{ eventStatusLabels[e.status] ?? e.status }}
                                </option>
                            </select>
                            <p v-if="form.errors[`assignments.${idx}.event_id`]" class="mt-1 text-red-500 text-xs">
                                {{ form.errors[`assignments.${idx}.event_id`] }}
                            </p>
                        </div>

                        <!-- Área (solo si hay evento seleccionado) -->
                        <div v-if="assignment.event_id">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Área <span class="text-gray-400 font-normal">(opcional)</span></label>
                            <input v-model="assignment.area" type="text"
                                placeholder="ej: Backstage, Registration, Front of House..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>

                        <!-- Horarios (solo si hay evento seleccionado) -->
                        <div v-if="assignment.event_id">
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-sm font-medium text-gray-700">Horarios <span class="text-gray-400 font-normal">(opcional)</span></label>
                                <button type="button" @click="addSchedule(idx)"
                                    class="inline-flex items-center gap-1 text-xs text-gray-600 hover:text-black border border-gray-300 rounded-lg px-2.5 py-1.5 hover:bg-gray-50 transition-colors">
                                    <PlusIcon class="w-3 h-3" /> Agregar día
                                </button>
                            </div>

                            <!-- Mensaje si no hay event_days -->
                            <p v-if="getEventDays(assignment.event_id).length === 0"
                                class="text-xs text-gray-400 italic">Este evento no tiene días configurados.</p>

                            <!-- Lista de horarios -->
                            <div v-for="(sch, si) in assignment.schedules" :key="si"
                                class="flex items-center gap-2 mb-2">
                                <select v-model="sch.event_day_id"
                                    class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                    <option value="">— Día —</option>
                                    <option v-for="day in getEventDays(assignment.event_id)" :key="day.id" :value="day.id">
                                        {{ formatDayDate(day.date) }} — {{ day.label }}
                                    </option>
                                </select>
                                <input v-model="sch.start_time" type="time"
                                    class="w-28 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                <span class="text-gray-400 text-xs">a</span>
                                <input v-model="sch.end_time" type="time"
                                    class="w-28 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                <button type="button" @click="removeSchedule(idx, si)"
                                    class="text-red-400 hover:text-red-600 transition-colors flex-shrink-0">
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Botón agregar otro evento -->
                    <button v-if="form.assignments.length > 0" type="button" @click="addAssignment"
                        class="w-full py-2.5 border border-dashed border-gray-300 rounded-xl text-sm text-gray-500 hover:border-gray-400 hover:text-gray-700 hover:bg-gray-50 transition-colors flex items-center justify-center gap-2">
                        <PlusIcon class="w-4 h-4" /> Agregar otro evento
                    </button>
                </div>

                <!-- Botones -->
                <div class="flex justify-between">
                    <Link href="/admin/operations/volunteers"
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
                            <span v-if="form.processing">Creando...</span>
                            <span v-else>Crear Voluntario</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
