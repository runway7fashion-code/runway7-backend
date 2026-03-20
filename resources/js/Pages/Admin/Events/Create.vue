<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { formatDayLabel } from '@/utils/dates.js';
import { XMarkIcon } from '@heroicons/vue/24/outline';

const step = ref(1);

const form = useForm({
    name: '',
    city: '',
    city_custom: '',
    venue: '',
    timezone: 'America/New_York',
    start_date: '',
    end_date: '',
    description: '',
    status: 'draft',
    model_number_start: 1,
    days: [],
    time_slots: ['11:00', '13:00', '15:00', '17:00', '19:00', '21:00'],
    apply_same_schedule: true,
});

const cityOptions = ['New York', 'Los Angeles', 'Miami', 'Houston', 'Otro'];
const timezones = ['America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'America/Miami'];

const step1Errors = computed(() => {
    const e = {};
    if (!form.name) e.name = 'El nombre es requerido';
    const city = form.city === 'Otro' ? form.city_custom : form.city;
    if (!city) e.city = 'La ciudad es requerida';
    if (!form.start_date) e.start_date = 'La fecha de inicio es requerida';
    if (!form.end_date) e.end_date = 'La fecha de fin es requerida';
    if (form.start_date && form.end_date && form.end_date < form.start_date) e.end_date = 'La fecha fin debe ser mayor a la fecha inicio';
    return e;
});

// Auto-generate days when dates change
watch([() => form.start_date, () => form.end_date], () => {
    if (!form.start_date || !form.end_date) return;
    const start = new Date(form.start_date + 'T00:00:00');
    const end = new Date(form.end_date + 'T00:00:00');
    if (end < start) return;

    const generated = [];
    let current = new Date(start);
    let dayNum = 1;
    while (current <= end) {
        const dateStr = current.toISOString().split('T')[0];
        const existing = form.days.find(d => d.date === dateStr);
        generated.push(existing ?? {
            date: dateStr,
            label: `Day ${dayNum}`,
            type: 'show_day',
            start_time: '',
            end_time: '',
            casting_start: '',
            casting_end: '',
            casting_interval: 30,
            casting_capacity: 50,
            casting_slots: [],
            merch_casting_start: '',
            merch_casting_end: '',
            merch_casting_interval: 30,
            merch_casting_capacity: 50,
            merch_casting_slots: [],
            time_slots: [...form.time_slots],
            has_fitting: false,
            fitting_start: '08:00',
            fitting_end: '12:00',
            fitting_interval: 30,
            fitting_capacity: 5,
        });
        current.setDate(current.getDate() + 1);
        dayNum++;
    }
    form.days = generated;
});

// Cuando se desactiva "Aplicar a todos", sincronizar los slots globales a cada día
// filtrando según el horario de inicio/fin de cada día
watch(() => form.apply_same_schedule, (val) => {
    if (!val) {
        form.days.forEach(d => {
            if (d.type === 'show_day') {
                d.time_slots = form.time_slots.filter(t => {
                    if (d.start_time && t < d.start_time) return false;
                    if (d.end_time && t > d.end_time) return false;
                    return true;
                });
            }
        });
    }
});

const showDaysCount = computed(() => form.days.filter(d => d.type === 'show_day').length);
const totalShowsPreview = computed(() => {
    if (form.apply_same_schedule) {
        return showDaysCount.value * form.time_slots.length;
    }
    return form.days.filter(d => d.type === 'show_day').reduce((sum, d) => sum + (d.time_slots?.length ?? 0), 0);
});

function addDay() {
    form.days.push({
        date: '',
        label: `Día extra`,
        type: 'show_day',
        start_time: '',
        end_time: '',
        casting_start: '08:00',
        casting_end: '23:00',
        casting_interval: 30,
        casting_capacity: 50,
        casting_slots: [],
        merch_casting_start: '',
        merch_casting_end: '',
        merch_casting_interval: 30,
        merch_casting_capacity: 50,
        merch_casting_slots: [],
        time_slots: [...form.time_slots],
        has_fitting: false,
        fitting_start: '08:00',
        fitting_end: '12:00',
        fitting_interval: 30,
        fitting_capacity: 5,
    });
}

function removeDay(index) {
    form.days.splice(index, 1);
}

// Auto-rellenar inicio/fin casting y fitting cuando cambian start_time/end_time
function syncDayTimes(day) {
    if (day.type === 'casting') {
        if (day.start_time) day.casting_start = day.start_time;
        if (day.end_time) day.casting_end = day.end_time;
    }
    if (day.type === 'fitting' || day.has_fitting) {
        if (day.start_time) day.fitting_start = day.start_time;
        if (day.end_time) day.fitting_end = day.end_time;
    }
}

function onTypeChange(day) {
    syncDayTimes(day);
}

function addTimeSlot() {
    form.time_slots.push('12:00');
}

function removeTimeSlot(index) {
    form.time_slots.splice(index, 1);
}

function addDaySlot(day) {
    day.time_slots = day.time_slots ?? [];
    day.time_slots.push('12:00');
}

function removeDaySlot(day, index) {
    day.time_slots.splice(index, 1);
}

function generateCastingSlots(day) {
    if (!day.casting_start || !day.casting_end || !day.casting_interval) return;
    const slots = [];
    const [sh, sm] = day.casting_start.split(':').map(Number);
    const [eh, em] = day.casting_end.split(':').map(Number);
    let current = sh * 60 + sm;
    const end = eh * 60 + em;
    while (current <= end) {
        const h = String(Math.floor(current / 60)).padStart(2, '0');
        const m = String(current % 60).padStart(2, '0');
        slots.push({ time: `${h}:${m}`, capacity: day.casting_capacity || 50 });
        current += Number(day.casting_interval);
    }
    day.casting_slots = slots;
}

function addCastingSlot(day) {
    if (!day.casting_slots) day.casting_slots = [];
    const lastTime = day.casting_slots.length > 0 ? day.casting_slots[day.casting_slots.length - 1].time : '12:00';
    day.casting_slots.push({ time: lastTime, capacity: day.casting_capacity || 50 });
}

function removeCastingSlot(day, index) {
    day.casting_slots.splice(index, 1);
}

function generateMerchSlots(day) {
    if (!day.merch_casting_start || !day.merch_casting_end || !day.merch_casting_interval) return;
    const slots = [];
    const [sh, sm] = day.merch_casting_start.split(':').map(Number);
    const [eh, em] = day.merch_casting_end.split(':').map(Number);
    let current = sh * 60 + sm;
    const end = eh * 60 + em;
    while (current <= end) {
        const h = String(Math.floor(current / 60)).padStart(2, '0');
        const m = String(current % 60).padStart(2, '0');
        slots.push({ time: `${h}:${m}`, capacity: day.merch_casting_capacity || 50 });
        current += Number(day.merch_casting_interval);
    }
    day.merch_casting_slots = slots;
}

function addMerchSlot(day) {
    if (!day.merch_casting_slots) day.merch_casting_slots = [];
    const lastTime = day.merch_casting_slots.length > 0 ? day.merch_casting_slots[day.merch_casting_slots.length - 1].time : '12:00';
    day.merch_casting_slots.push({ time: lastTime, capacity: day.merch_casting_capacity || 50 });
}

function removeMerchSlot(day, index) {
    day.merch_casting_slots.splice(index, 1);
}

function nextStep() {
    if (step.value === 1 && Object.keys(step1Errors.value).length > 0) return;
    step.value++;
}

function prevStep() {
    step.value--;
}

const typeConfig = {
    setup:    { label: 'Setup',     class: 'bg-gray-700 text-gray-300' },
    casting:  { label: 'Casting',   class: 'bg-yellow-800/60 text-yellow-300' },
    show_day: { label: 'Show Day',  class: 'bg-green-800/60 text-green-300' },
    fitting:  { label: 'Fitting',   class: 'bg-orange-800/60 text-orange-300' },
    ceremony: { label: 'Ceremonia', class: 'bg-purple-800/60 text-purple-300' },
    other:    { label: 'Otro',      class: 'bg-blue-800/60 text-blue-300' },
};

function submit() {
    const payload = {
        ...form.data(),
        city: form.city === 'Otro' ? form.city_custom : form.city,
        days: form.days.map(d => {
            const day = { ...d };
            // Solo enviar datos de fitting si corresponde
            const shouldSendFitting = d.type === 'fitting' || (d.type === 'show_day' && d.has_fitting);
            if (!shouldSendFitting) {
                delete day.fitting_start;
                delete day.fitting_end;
                delete day.fitting_interval;
                delete day.fitting_capacity;
            }
            return day;
        }),
    };
    form.transform(() => payload).post('/admin/events');
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Crear Evento</h2>
        </template>

        <div class="max-w-4xl mx-auto">
            <!-- Progress bar -->
            <div class="flex items-center gap-2 mb-8">
                <template v-for="n in 3" :key="n">
                    <div class="flex items-center gap-2">
                        <div
                            class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-all"
                            :class="step >= n ? 'bg-black text-white' : 'bg-gray-200 text-gray-500'"
                        >{{ n }}</div>
                        <span class="text-sm hidden sm:block" :class="step >= n ? 'text-gray-900 font-medium' : 'text-gray-400'">
                            {{ ['Información General', 'Configurar Días', 'Configurar Shows'][n - 1] }}
                        </span>
                    </div>
                    <div v-if="n < 3" class="flex-1 h-0.5 mx-2" :class="step > n ? 'bg-black' : 'bg-gray-200'"></div>
                </template>
            </div>

            <!-- Step 1: General Info -->
            <div v-if="step === 1" class="bg-white rounded-2xl border border-gray-200 p-8">
                <h3 class="text-xl font-bold mb-6">Información General</h3>
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre del Evento *</label>
                        <input v-model="form.name" type="text" placeholder="New York Fashion Week September 2026"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        <p v-if="step1Errors.name" class="mt-1 text-red-500 text-xs">{{ step1Errors.name }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Ciudad *</label>
                            <select v-model="form.city"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">Seleccionar ciudad</option>
                                <option v-for="c in cityOptions" :key="c" :value="c">{{ c }}</option>
                            </select>
                            <input v-if="form.city === 'Otro'" v-model="form.city_custom" type="text" placeholder="Nombre de la ciudad"
                                class="mt-2 w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="step1Errors.city" class="mt-1 text-red-500 text-xs">{{ step1Errors.city }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Venue</label>
                            <input v-model="form.venue" type="text" placeholder="Spring Studios"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha Inicio *</label>
                            <input v-model="form.start_date" type="date"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="step1Errors.start_date" class="mt-1 text-red-500 text-xs">{{ step1Errors.start_date }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha Fin *</label>
                            <input v-model="form.end_date" type="date"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="step1Errors.end_date" class="mt-1 text-red-500 text-xs">{{ step1Errors.end_date }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Timezone</label>
                            <select v-model="form.timezone"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Descripción</label>
                        <textarea v-model="form.description" rows="3" placeholder="Descripción del evento..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Estado inicial</label>
                            <select v-model="form.status"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="draft">Borrador</option>
                                <option value="published">Publicado</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Numeración de modelos desde</label>
                            <input v-model.number="form.model_number_start" type="number" min="1"
                                placeholder="ej. 4058"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p class="mt-1 text-xs text-gray-400">Primera modelo de este evento recibirá este número.</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end mt-8">
                    <button @click="nextStep"
                        :disabled="Object.keys(step1Errors).length > 0"
                        class="px-6 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-40 transition-colors">
                        Siguiente →
                    </button>
                </div>
            </div>

            <!-- Step 2: Days -->
            <div v-if="step === 2" class="bg-white rounded-2xl border border-gray-200 p-8">
                <h3 class="text-xl font-bold mb-6">Configurar Días</h3>

                <div v-if="form.days.length === 0" class="text-center py-8 text-gray-400">
                    <p>Ingresa las fechas del evento en el paso anterior para generar los días automáticamente.</p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="(day, index) in form.days"
                        :key="index"
                        class="border border-gray-200 rounded-xl p-4"
                        :class="day.type === 'casting' ? 'border-yellow-300 bg-yellow-50/30' : day.type === 'show_day' ? 'border-green-200 bg-green-50/20' : day.type === 'fitting' ? 'border-orange-300 bg-orange-50/30' : ''"
                    >
                        <div class="flex items-center gap-3 flex-wrap">
                            <!-- Date -->
                            <div class="flex-shrink-0 w-48">
                                <p class="text-xs text-gray-500 mb-0.5">Fecha</p>
                                <input v-if="!day.date" v-model="day.date" type="date"
                                    class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                <p v-else class="text-sm font-medium text-gray-900">{{ formatDayLabel(day.date) }}</p>
                            </div>

                            <!-- Label -->
                            <div class="flex-1 min-w-32">
                                <p class="text-xs text-gray-500 mb-0.5">Label</p>
                                <input v-model="day.label" type="text"
                                    class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>

                            <!-- Type -->
                            <div class="w-36">
                                <p class="text-xs text-gray-500 mb-0.5">Tipo</p>
                                <select v-model="day.type" @change="onTypeChange(day)"
                                    class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                    <option value="setup">Setup</option>
                                    <option value="casting">Casting</option>
                                    <option value="show_day">Show Day</option>
                                    <option value="fitting">Fitting</option>
                                    <option value="ceremony">Ceremonia</option>
                                    <option value="other">Otro</option>
                                </select>
                            </div>

                            <!-- Start/End time -->
                            <div class="flex gap-2">
                                <div>
                                    <p class="text-xs text-gray-500 mb-0.5">Inicio</p>
                                    <input v-model="day.start_time" @change="syncDayTimes(day)" type="time"
                                        class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 w-24" />
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 mb-0.5">Fin</p>
                                    <input v-model="day.end_time" @change="syncDayTimes(day)" type="time"
                                        class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 w-24" />
                                </div>
                            </div>

                            <!-- Remove -->
                            <button @click="removeDay(index)" class="text-red-400 hover:text-red-600 mt-4 flex-shrink-0" title="Eliminar día">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>

                        <!-- Casting extra fields -->
                        <div v-if="day.type === 'casting'" class="mt-3 pt-3 border-t border-yellow-200">
                            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                                <div>
                                    <label class="text-xs text-yellow-700 font-medium mb-0.5 block">Inicio casting</label>
                                    <input v-model="day.casting_start" type="time"
                                        class="w-full border border-yellow-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/30" />
                                </div>
                                <div>
                                    <label class="text-xs text-yellow-700 font-medium mb-0.5 block">Fin casting</label>
                                    <input v-model="day.casting_end" type="time"
                                        class="w-full border border-yellow-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/30" />
                                </div>
                                <div>
                                    <label class="text-xs text-yellow-700 font-medium mb-0.5 block">Intervalo (min)</label>
                                    <select v-model="day.casting_interval"
                                        class="w-full border border-yellow-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/30">
                                        <option :value="15">15 min</option>
                                        <option :value="30">30 min</option>
                                        <option :value="45">45 min</option>
                                        <option :value="60">60 min</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-yellow-700 font-medium mb-0.5 block">Cap. por slot</label>
                                    <input v-model.number="day.casting_capacity" type="number" min="1"
                                        class="w-full border border-yellow-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/30" />
                                </div>
                                <div class="flex items-end">
                                    <button @click="generateCastingSlots(day)" type="button"
                                        class="w-full px-3 py-1.5 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700 transition-colors">
                                        Generar slots
                                    </button>
                                </div>
                            </div>

                            <!-- Casting slots preview/edit -->
                            <div v-if="day.casting_slots?.length" class="mt-3 pt-3 border-t border-yellow-200">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-xs font-semibold text-yellow-800">{{ day.casting_slots.length }} slots generados — edita los horarios según necesites</p>
                                    <button @click="addCastingSlot(day)" type="button"
                                        class="text-xs text-yellow-700 hover:text-yellow-900 font-medium">+ Agregar slot</button>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-2">
                                    <div v-for="(slot, si) in day.casting_slots" :key="si"
                                        class="flex items-center gap-1 bg-white border border-yellow-200 rounded-lg px-2 py-1">
                                        <input v-model="slot.time" type="time"
                                            class="border-0 text-sm font-medium text-gray-800 p-0 focus:outline-none focus:ring-0 w-[70px]" />
                                        <input v-model.number="slot.capacity" type="number" min="1"
                                            class="border-0 text-xs text-gray-500 p-0 focus:outline-none focus:ring-0 w-[35px] text-center"
                                            title="Capacidad" />
                                        <button @click="removeCastingSlot(day, si)" type="button" class="text-red-300 hover:text-red-500 flex-shrink-0">
                                            <XMarkIcon class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Merch casting slots -->
                        <div v-if="day.type === 'casting'" class="mt-3 pt-3 border-t border-orange-200">
                            <p class="text-xs font-bold text-orange-700 uppercase tracking-wider mb-2">Casting Merch (Runway Merch)</p>
                            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                                <div>
                                    <label class="text-xs text-orange-600 font-medium mb-0.5 block">Inicio</label>
                                    <input v-model="day.merch_casting_start" type="time"
                                        class="w-full border border-orange-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400/30" />
                                </div>
                                <div>
                                    <label class="text-xs text-orange-600 font-medium mb-0.5 block">Fin</label>
                                    <input v-model="day.merch_casting_end" type="time"
                                        class="w-full border border-orange-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400/30" />
                                </div>
                                <div>
                                    <label class="text-xs text-orange-600 font-medium mb-0.5 block">Intervalo (min)</label>
                                    <select v-model="day.merch_casting_interval"
                                        class="w-full border border-orange-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400/30">
                                        <option :value="15">15 min</option>
                                        <option :value="30">30 min</option>
                                        <option :value="45">45 min</option>
                                        <option :value="60">60 min</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-orange-600 font-medium mb-0.5 block">Cap. por slot</label>
                                    <input v-model.number="day.merch_casting_capacity" type="number" min="1"
                                        class="w-full border border-orange-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400/30" />
                                </div>
                                <div class="flex items-end">
                                    <button @click="generateMerchSlots(day)" type="button"
                                        class="w-full px-3 py-1.5 bg-orange-600 text-white text-sm font-medium rounded-lg hover:bg-orange-700 transition-colors">
                                        Generar slots
                                    </button>
                                </div>
                            </div>

                            <!-- Merch slots preview/edit -->
                            <div v-if="day.merch_casting_slots?.length" class="mt-3 pt-3 border-t border-orange-200">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-xs font-semibold text-orange-700">{{ day.merch_casting_slots.length }} slots merch generados</p>
                                    <button @click="addMerchSlot(day)" type="button"
                                        class="text-xs text-orange-600 hover:text-orange-800 font-medium">+ Agregar slot</button>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-2">
                                    <div v-for="(slot, si) in day.merch_casting_slots" :key="si"
                                        class="flex items-center gap-1 bg-white border border-orange-200 rounded-lg px-2 py-1">
                                        <input v-model="slot.time" type="time"
                                            class="border-0 text-sm font-medium text-gray-800 p-0 focus:outline-none focus:ring-0 w-[70px]" />
                                        <input v-model.number="slot.capacity" type="number" min="1"
                                            class="border-0 text-xs text-gray-500 p-0 focus:outline-none focus:ring-0 w-[35px] text-center"
                                            title="Capacidad" />
                                        <button @click="removeMerchSlot(day, si)" type="button" class="text-red-300 hover:text-red-500 flex-shrink-0">
                                            <XMarkIcon class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fitting extra fields (for type "fitting") -->
                        <div v-if="day.type === 'fitting'" class="mt-3 pt-3 border-t border-orange-200 grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div>
                                <label class="text-xs text-orange-700 font-medium mb-0.5 block">Inicio fitting</label>
                                <input v-model="day.fitting_start" type="time"
                                    class="w-full border border-orange-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400/30" />
                            </div>
                            <div>
                                <label class="text-xs text-orange-700 font-medium mb-0.5 block">Fin fitting</label>
                                <input v-model="day.fitting_end" type="time"
                                    class="w-full border border-orange-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400/30" />
                            </div>
                            <div>
                                <label class="text-xs text-orange-700 font-medium mb-0.5 block">Intervalo (min)</label>
                                <select v-model="day.fitting_interval"
                                    class="w-full border border-orange-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400/30">
                                    <option :value="15">15 min</option>
                                    <option :value="30">30 min</option>
                                    <option :value="60">60 min</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-orange-700 font-medium mb-0.5 block">Cap. por slot</label>
                                <input v-model.number="day.fitting_capacity" type="number" min="1"
                                    class="w-full border border-orange-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400/30" />
                            </div>
                        </div>

                        <!-- Show day with optional fitting -->
                        <div v-if="day.type === 'show_day'" class="mt-2 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-green-600 font-medium">Shows se configuran en el paso 3</span>
                                <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                                    <input v-model="day.has_fitting" type="checkbox" class="rounded text-orange-500 focus:ring-orange-400" />
                                    Incluir fitting en la mañana
                                </label>
                            </div>
                            <div v-if="day.has_fitting" class="pt-2 border-t border-orange-200 grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div>
                                    <label class="text-xs text-orange-700 font-medium mb-0.5 block">Inicio fitting</label>
                                    <input v-model="day.fitting_start" type="time"
                                        class="w-full border border-orange-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400/30" />
                                </div>
                                <div>
                                    <label class="text-xs text-orange-700 font-medium mb-0.5 block">Fin fitting</label>
                                    <input v-model="day.fitting_end" type="time"
                                        class="w-full border border-orange-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400/30" />
                                </div>
                                <div>
                                    <label class="text-xs text-orange-700 font-medium mb-0.5 block">Intervalo (min)</label>
                                    <select v-model="day.fitting_interval"
                                        class="w-full border border-orange-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400/30">
                                        <option :value="15">15 min</option>
                                        <option :value="30">30 min</option>
                                        <option :value="60">60 min</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-orange-700 font-medium mb-0.5 block">Cap. por slot</label>
                                    <input v-model.number="day.fitting_capacity" type="number" min="1"
                                        class="w-full border border-orange-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400/30" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <button @click="addDay" class="w-full py-3 border-2 border-dashed border-gray-300 rounded-xl text-gray-500 text-sm hover:border-gray-400 hover:text-gray-700 transition-colors">
                        + Agregar día extra
                    </button>
                </div>

                <div class="flex justify-between mt-8">
                    <button @click="prevStep" class="px-6 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">← Anterior</button>
                    <button @click="nextStep" class="px-6 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800">Siguiente →</button>
                </div>
            </div>

            <!-- Step 3: Shows -->
            <div v-if="step === 3" class="bg-white rounded-2xl border border-gray-200 p-8">
                <h3 class="text-xl font-bold mb-2">Configurar Shows</h3>

                <div v-if="showDaysCount === 0" class="py-6 text-center text-gray-400">
                    <p>No hay días tipo <strong>Show Day</strong> configurados. Regresa al paso 2.</p>
                </div>

                <div v-else class="space-y-6">
                    <!-- Global schedule -->
                    <div class="border border-gray-200 rounded-xl p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="font-semibold text-gray-900">Horarios de Shows</h4>
                            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                                <input v-model="form.apply_same_schedule" type="checkbox" class="rounded" />
                                Aplicar a todos los días
                            </label>
                        </div>

                        <div v-if="form.apply_same_schedule" class="flex flex-wrap gap-2">
                            <div v-for="(slot, i) in form.time_slots" :key="i" class="flex items-center gap-1">
                                <input v-model="form.time_slots[i]" type="time"
                                    class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-24 focus:outline-none focus:ring-2 focus:ring-black/10" />
                                <button @click="removeTimeSlot(i)" class="text-red-400 hover:text-red-600">
                                    <XMarkIcon class="w-4 h-4" />
                                </button>
                            </div>
                            <button @click="addTimeSlot" class="px-3 py-1.5 border-2 border-dashed border-gray-300 rounded-lg text-sm text-gray-500 hover:border-gray-400">+ Hora</button>
                        </div>

                        <!-- Per-day schedule -->
                        <div v-else class="space-y-4">
                            <div v-for="(day, i) in form.days.filter(d => d.type === 'show_day')" :key="i" class="border border-gray-100 rounded-lg p-3">
                                <p class="text-sm font-medium text-gray-700 mb-2">{{ formatDayLabel(day.date) }} — {{ day.label }}</p>
                                <div class="flex flex-wrap gap-2">
                                    <div v-for="(slot, si) in (day.time_slots ?? [])" :key="si" class="flex items-center gap-1">
                                        <input v-model="day.time_slots[si]" type="time"
                                            class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-24 focus:outline-none focus:ring-2 focus:ring-black/10" />
                                        <button @click="removeDaySlot(day, si)" class="text-red-400 hover:text-red-600">
                                            <XMarkIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                    <button @click="addDaySlot(day)" class="px-3 py-1.5 border-2 border-dashed border-gray-300 rounded-lg text-sm text-gray-500 hover:border-gray-400">+ Hora</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div class="bg-gray-50 rounded-xl p-4 flex items-center gap-4">
                        <div class="text-4xl font-bold text-black">{{ totalShowsPreview }}</div>
                        <div>
                            <p class="font-semibold text-gray-900">shows se crearán en total</p>
                            <p class="text-sm text-gray-500">{{ showDaysCount }} día(s) × {{ form.apply_same_schedule ? form.time_slots.length : '—' }} horarios</p>
                        </div>
                    </div>

                    <div v-if="form.errors.days || Object.keys(form.errors).length" class="text-red-500 text-sm">
                        <p v-for="(err, key) in form.errors" :key="key">{{ err }}</p>
                    </div>
                </div>

                <div class="flex justify-between mt-8">
                    <button @click="prevStep" class="px-6 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">← Anterior</button>
                    <button
                        @click="submit"
                        :disabled="form.processing"
                        class="px-8 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-60 transition-colors"
                    >
                        <span v-if="form.processing">Creando evento...</span>
                        <span v-else>Crear Evento</span>
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
