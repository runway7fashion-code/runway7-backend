<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import { formatDayLabel } from '@/utils/dates.js';
import { ArrowLeftIcon, XMarkIcon, CheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    event: Object,
});

const form = useForm({
    name: props.event.name,
    city: props.event.city,
    venue: props.event.venue ?? '',
    timezone: props.event.timezone,
    start_date: props.event.start_date?.split('T')[0] ?? '',
    end_date: props.event.end_date?.split('T')[0] ?? '',
    description: props.event.description ?? '',
    status: props.event.status,
    model_number_start: props.event.model_number_start ?? 1,
    days: [...props.event.event_days].sort((a, b) => (a.date ?? '').localeCompare(b.date ?? '')).map(d => {
        const allSlots = d.casting_slots ?? [];
        const slots = allSlots.filter(s => (s.slot_type ?? 'normal') === 'normal');
        const merchSlots = allSlots.filter(s => s.slot_type === 'merch');
        const fSlots = d.fitting_slots ?? [];
        // Derivar config de casting desde los slots existentes
        const firstSlot = slots[0];
        const lastSlot  = slots[slots.length - 1];
        let interval = 30;
        if (slots.length >= 2) {
            const [h1, m1] = slots[0].time.split(':').map(Number);
            const [h2, m2] = slots[1].time.split(':').map(Number);
            interval = (h2 * 60 + m2) - (h1 * 60 + m1);
        }
        // Derivar config de merch casting
        const firstMerch = merchSlots[0];
        const lastMerch  = merchSlots[merchSlots.length - 1];
        let merchInterval = 30;
        if (merchSlots.length >= 2) {
            const [mh1, mm1] = merchSlots[0].time.split(':').map(Number);
            const [mh2, mm2] = merchSlots[1].time.split(':').map(Number);
            merchInterval = (mh2 * 60 + mm2) - (mh1 * 60 + mm1);
        }
        // Derivar config de fitting
        const firstFSlot = fSlots[0];
        const lastFSlot  = fSlots[fSlots.length - 1];
        let fInterval = 30;
        if (fSlots.length >= 2) {
            const [fh1, fm1] = fSlots[0].time.split(':').map(Number);
            const [fh2, fm2] = fSlots[1].time.split(':').map(Number);
            fInterval = (fh2 * 60 + fm2) - (fh1 * 60 + fm1);
        }
        return {
            id: d.id,
            date: d.date?.split('T')[0] ?? '',
            label: d.label,
            type: d.type,
            start_time: d.start_time ?? '',
            end_time: d.end_time ?? '',
            description: d.description ?? '',
            casting_start: firstSlot?.time ?? '08:00',
            casting_end: lastSlot?.time ?? '23:00',
            casting_interval: interval,
            casting_capacity: firstSlot?.capacity ?? 50,
            casting_slots: slots.map(s => ({ time: s.time.substring(0, 5), capacity: s.capacity })),
            merch_casting_start: firstMerch?.time?.substring(0, 5) ?? '',
            merch_casting_end: lastMerch?.time?.substring(0, 5) ?? '',
            merch_casting_interval: merchInterval,
            merch_casting_capacity: firstMerch?.capacity ?? 50,
            merch_casting_slots: merchSlots.map(s => ({ time: s.time.substring(0, 5), capacity: s.capacity })),
            has_fitting: !!(d.fitting_start || fSlots.length > 0),
            fitting_start: d.fitting_start ?? firstFSlot?.time ?? '08:00',
            fitting_end: d.fitting_end ?? lastFSlot?.time ?? '12:00',
            fitting_interval: d.fitting_interval ?? fInterval,
            fitting_capacity: firstFSlot?.capacity ?? 5,
            shows: d.shows ?? [],
            has_assigned_shows: (d.shows ?? []).some(s => s.designers_count > 0),
        };
    }),
});

const statusOptions = [
    { value: 'draft', label: 'Borrador' },
    { value: 'published', label: 'Publicado' },
    { value: 'active', label: 'Activo' },
    { value: 'completed', label: 'Completado' },
    { value: 'cancelled', label: 'Cancelado' },
];

function addDay() {
    form.days.push({
        date: '',
        label: 'Nuevo Día',
        type: 'show_day',
        start_time: '',
        end_time: '',
        description: '',
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
        has_fitting: false,
        fitting_start: '08:00',
        fitting_end: '12:00',
        fitting_interval: 30,
        fitting_capacity: 5,
        shows: [],
        has_assigned_shows: false,
    });
}

function removeDay(index) {
    const day = form.days[index];
    if (day.has_assigned_shows) return;
    if (day.id) {
        router.delete(`/admin/operations/events/${props.event.id}/days/${day.id}`, { preserveScroll: true });
    }
    form.days.splice(index, 1);
}

// Auto-rellenar inicio/fin casting y fitting cuando cambian start_time/end_time de un día
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

// Cuando cambia el tipo de un día, auto-rellenar con start_time/end_time existentes
function onTypeChange(day) {
    syncDayTimes(day);
}

function deleteShow(day, show) {
    if (!confirm('¿Eliminar este show?')) return;
    router.delete(`/admin/operations/shows/${show.id}`, { preserveScroll: true });
    day.shows = day.shows.filter(s => s.id !== show.id);
}

// Track new show time inputs per day (keyed by day.id)
const newShowTimes = reactive({});

function addShow(day) {
    const time = newShowTimes[day.id];
    if (!time) return;
    router.post(
        `/admin/operations/events/${props.event.id}/days/${day.id}/shows`,
        { scheduled_time: time },
        {
            preserveScroll: true,
            onSuccess: () => { newShowTimes[day.id] = ''; },
        }
    );
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

function submit() {
    form.transform(data => ({
        ...data,
        days: data.days.map(d => {
            const day = { ...d };
            const shouldSendFitting = d.type === 'fitting' || (d.type === 'show_day' && d.has_fitting);
            if (!shouldSendFitting) {
                delete day.fitting_start;
                delete day.fitting_end;
                delete day.fitting_interval;
                delete day.fitting_capacity;
            }
            return day;
        }),
    })).put(`/admin/operations/events/${props.event.id}`);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="`/admin/operations/events/${event.id}`" class="flex items-center gap-1 text-gray-400 hover:text-gray-600 text-sm">
                    <ArrowLeftIcon class="w-4 h-4" /> Volver
                </Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Editar Evento</h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto">
            <form @submit.prevent="submit" class="space-y-6">

                <!-- General info -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h3 class="text-lg font-bold mb-5">Información General</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                            <input v-model="form.name" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.name" class="mt-1 text-red-500 text-xs">{{ form.errors.name }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ciudad</label>
                                <input v-model="form.city" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Venue</label>
                                <input v-model="form.venue" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Inicio</label>
                                <input v-model="form.start_date" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Fin</label>
                                <input v-model="form.end_date" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                                <select v-model="form.status" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                    <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                                <textarea v-model="form.description" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Numeración de modelos desde</label>
                                <input v-model.number="form.model_number_start" type="number" min="1"
                                    placeholder="ej. 4058"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                <p class="mt-1 text-xs text-gray-400">Primera modelo asignada recibirá este número.</p>
                                <p v-if="form.errors.model_number_start" class="mt-1 text-red-500 text-xs">{{ form.errors.model_number_start }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Days & Shows -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h3 class="text-lg font-bold mb-5">Días del Evento</h3>

                    <div class="space-y-3">
                        <div
                            v-for="(day, i) in form.days"
                            :key="day.id ?? `new-${i}`"
                            class="border border-gray-200 rounded-xl p-4"
                            :class="day.type === 'casting' ? 'border-yellow-300' : day.type === 'show_day' ? 'border-green-200' : day.type === 'fitting' ? 'border-orange-300' : ''"
                        >
                            <!-- Day fields -->
                            <div class="flex items-end gap-3 flex-wrap">
                                <div class="flex-shrink-0">
                                    <p class="text-xs text-gray-500 mb-0.5">Fecha</p>
                                    <p v-if="day.id" class="text-sm font-medium text-gray-700 py-2">{{ formatDayLabel(day.date) }}</p>
                                    <input v-else v-model="day.date" type="date"
                                        class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                </div>
                                <div class="flex-1 min-w-28">
                                    <label class="text-xs text-gray-500 mb-0.5 block">Label</label>
                                    <input v-model="day.label" type="text"
                                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                </div>
                                <div class="w-32">
                                    <label class="text-xs text-gray-500 mb-0.5 block">Tipo</label>
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
                                <div class="flex gap-2">
                                    <div>
                                        <label class="text-xs text-gray-500 mb-0.5 block">Inicio</label>
                                        <input v-model="day.start_time" @change="syncDayTimes(day)" type="time" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-24 focus:outline-none" />
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 mb-0.5 block">Fin</label>
                                        <input v-model="day.end_time" @change="syncDayTimes(day)" type="time" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-24 focus:outline-none" />
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <button
                                        type="button"
                                        @click="removeDay(i)"
                                        :disabled="day.has_assigned_shows"
                                        :title="day.has_assigned_shows ? 'No se puede eliminar: tiene shows con diseñadores asignados' : 'Eliminar día'"
                                        class="text-red-400 hover:text-red-600 disabled:opacity-30 disabled:cursor-not-allowed"
                                    >
                                        <XMarkIcon class="w-5 h-5" />
                                    </button>
                                </div>
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
                                        <p class="text-xs font-semibold text-yellow-800">{{ day.casting_slots.length }} slots — edita los horarios según necesites</p>
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
                                        <p class="text-xs font-semibold text-orange-700">{{ day.merch_casting_slots.length }} slots merch</p>
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

                            <!-- Fitting fields (for type "fitting") -->
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

                            <!-- Shows for this day (only for existing saved days) -->
                            <div v-if="day.id && day.type === 'show_day'" class="mt-3 pt-3 border-t border-gray-100">
                                <p class="text-xs text-gray-500 mb-2 font-medium">Shows ({{ day.shows?.length ?? 0 }})</p>
                                <div class="flex flex-wrap gap-2 mb-2">
                                    <div
                                        v-for="show in day.shows"
                                        :key="show.id"
                                        class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs"
                                    >
                                        <span class="font-medium">{{ show.formatted_time ?? show.scheduled_time }}</span>
                                        <CheckIcon v-if="show.designers_count > 0" class="w-3.5 h-3.5 text-green-600" />
                                        <button
                                            type="button"
                                            @click="deleteShow(day, show)"
                                            :disabled="show.designers_count > 0"
                                            :title="show.designers_count > 0 ? 'Tiene diseñadores asignados' : 'Eliminar show'"
                                            class="text-red-400 hover:text-red-600 disabled:opacity-30 disabled:cursor-not-allowed"
                                        >
                                            <XMarkIcon class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                                <!-- Add show -->
                                <div class="flex items-center gap-2 mt-1">
                                    <input
                                        v-model="newShowTimes[day.id]"
                                        type="time"
                                        class="border border-gray-300 rounded-lg px-2 py-1 text-sm w-28 focus:outline-none focus:ring-2 focus:ring-black/10"
                                    />
                                    <button
                                        type="button"
                                        @click="addShow(day)"
                                        :disabled="!newShowTimes[day.id]"
                                        class="px-3 py-1 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 disabled:opacity-40 transition-colors"
                                    >+ Show</button>
                                </div>

                                <!-- Fitting toggle for show_day -->
                                <div class="mt-3 pt-3 border-t border-orange-100">
                                    <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                                        <input v-model="day.has_fitting" type="checkbox" class="rounded text-orange-500 focus:ring-orange-400" />
                                        Incluir fitting en la mañana
                                    </label>
                                    <div v-if="day.has_fitting" class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-3">
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
                        </div>

                        <button type="button" @click="addDay"
                            class="w-full py-3 border-2 border-dashed border-gray-300 rounded-xl text-gray-500 text-sm hover:border-gray-400 hover:text-gray-700 transition-colors">
                            + Agregar día
                        </button>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-between">
                    <Link :href="`/admin/operations/events/${event.id}`" class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        Cancelar
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-8 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-60 transition-colors"
                    >
                        <span v-if="form.processing">Guardando...</span>
                        <span v-else>Guardar Cambios</span>
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
