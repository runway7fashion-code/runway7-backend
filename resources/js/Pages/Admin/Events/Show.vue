<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { formatDateShort, formatDateRange, formatTime } from '@/utils/dates.js';
import { ArrowLeftIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    event: Object,
    designers: Array,
    stats: Object,
});

const statusConfig = {
    draft:     { label: 'Borrador',   class: 'bg-gray-700 text-gray-300' },
    published: { label: 'Publicado',  class: 'bg-blue-800/50 text-blue-300' },
    active:    { label: 'Activo',     class: 'bg-green-800/50 text-green-300' },
    completed: { label: 'Completado', class: 'bg-purple-800/50 text-purple-300' },
    cancelled: { label: 'Cancelado',  class: 'bg-red-800/50 text-red-300' },
};

const dayTypeConfig = {
    setup:    { label: 'Setup',     class: 'bg-gray-700 text-gray-300' },
    casting:  { label: 'Casting',   class: 'bg-yellow-800/60 text-yellow-300' },
    show_day: { label: 'Show Day',  class: 'bg-green-800/60 text-green-300' },
    fitting:  { label: 'Fitting',   class: 'bg-orange-800/60 text-orange-300' },
    ceremony: { label: 'Ceremonia', class: 'bg-purple-800/60 text-purple-300' },
    other:    { label: 'Otro',      class: 'bg-blue-800/60 text-blue-300' },
};


// ── Assign designer modal ──
const assignModal = ref(false);
const selectedShow = ref(null);
const assignForm = ref({ designer_id: '', collection_name: '', fitting_slot_id: '' });

// Todos los fitting slots del evento (de cualquier día con fitting)
const allFittingSlots = computed(() => {
    const slots = [];
    for (const day of props.event.event_days ?? []) {
        if (day.fitting_slots?.length) {
            for (const slot of day.fitting_slots) {
                slots.push({
                    ...slot,
                    day_label: day.label,
                    day_date: day.date,
                });
            }
        }
    }
    return slots;
});

function openAssignModal(show) {
    selectedShow.value = show;
    assignForm.value = { designer_id: '', collection_name: '', fitting_slot_id: '' };
    assignModal.value = true;
}

// Filter out designers already assigned to this show
const availableDesigners = computed(() => {
    if (!selectedShow.value) return props.designers;
    const assignedIds = (selectedShow.value.designers ?? []).map(d => d.id);
    return props.designers.filter(d => !assignedIds.includes(d.id));
});

function submitAssign() {
    if (!assignForm.value.designer_id) return;
    router.post(`/admin/shows/${selectedShow.value.id}/assign-designer`, assignForm.value, {
        onSuccess: () => { assignModal.value = false; },
        preserveScroll: true,
    });
}

function removeDesigner(show, designerId) {
    if (!confirm('¿Remover este diseñador del show?')) return;
    router.post(`/admin/shows/${show.id}/remove-designer`, { designer_id: designerId }, { preserveScroll: true });
}

// ── Duplicate event modal ──
const dupModal = ref(false);
const dupForm = useForm({ name: props.event.name + ' (Copia)', start_date: '', end_date: '' });

function submitDuplicate() {
    dupForm.post(`/admin/events/${props.event.id}/duplicate`, {
        onSuccess: () => { dupModal.value = false; },
    });
}

// ── Casting slots modal ──
const slotsModal = ref(null);

function openSlotsModal(day) {
    slotsModal.value = day;
}

// ── Fitting designer assignment modal ──
const fittingModal = ref(false);
const selectedFittingSlot = ref(null);
const fittingAssignForm = ref({ designer_id: '' });

function openFittingAssignModal(slot) {
    selectedFittingSlot.value = slot;
    fittingAssignForm.value = { designer_id: '' };
    fittingModal.value = true;
}

const availableFittingDesigners = computed(() => {
    if (!selectedFittingSlot.value) return props.designers;
    const assignedIds = (selectedFittingSlot.value.assignments ?? []).map(a => a.designer_id);
    return props.designers.filter(d => !assignedIds.includes(d.id));
});

function submitFittingAssign() {
    if (!fittingAssignForm.value.designer_id) return;
    router.post(`/admin/fitting-slots/${selectedFittingSlot.value.id}/assign-designer`, fittingAssignForm.value, {
        onSuccess: () => { fittingModal.value = false; },
        preserveScroll: true,
    });
}

function removeFittingDesigner(slot, designerId) {
    if (!confirm('¿Remover este diseñador del fitting?')) return;
    router.delete(`/admin/fitting-slots/${slot.id}/remove-designer/${designerId}`, { preserveScroll: true });
}

// Check if a day has fitting (type fitting or show_day with fitting_slots)
function dayHasFitting(day) {
    return day.type === 'fitting' || (day.fitting_slots && day.fitting_slots.length > 0);
}

// Buscar el fitting asignado de un diseñador en el evento
function getDesignerFitting(designerId) {
    for (const day of props.event.event_days ?? []) {
        for (const slot of day.fitting_slots ?? []) {
            const assignment = (slot.assignments ?? []).find(a => a.designer_id === designerId);
            if (assignment) {
                return { day_label: day.label, time: slot.time };
            }
        }
    }
    return null;
}

// Designer badge color: yellow = none, green = has designers
function slotBadgeClass(show) {
    return (show.designers ?? []).length === 0
        ? 'bg-yellow-100 text-yellow-700 border-yellow-300'
        : 'bg-green-100 text-green-700 border-green-300';
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/events" class="flex items-center gap-1 text-gray-400 hover:text-gray-600 text-sm">
                    <ArrowLeftIcon class="w-4 h-4" /> Eventos
                </Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900 truncate">{{ event.name }}</h2>
            </div>
        </template>

        <div>
            <!-- Event header -->
            <div class="bg-black rounded-2xl p-6 mb-6">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <div class="flex items-center gap-3 mb-2 flex-wrap">
                            <h1 class="text-white text-2xl font-bold">{{ event.name }}</h1>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                                :class="statusConfig[event.status]?.class">
                                {{ statusConfig[event.status]?.label }}
                            </span>
                        </div>
                        <p class="text-gray-400 text-sm">{{ event.city }}<span v-if="event.venue"> · {{ event.venue }}</span></p>
                        <p class="text-yellow-400 text-sm mt-1 font-medium">{{ formatDateRange(event.start_date, event.end_date) }}</p>
                        <p v-if="event.description" class="text-gray-500 text-sm mt-2 max-w-xl">{{ event.description }}</p>
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <Link :href="`/admin/events/${event.id}/edit`"
                            class="px-4 py-2 border border-white/20 text-white rounded-lg text-sm hover:bg-white/10 transition-colors">
                            Editar
                        </Link>
                        <button @click="dupModal = true"
                            class="px-4 py-2 border border-white/20 text-white rounded-lg text-sm hover:bg-white/10 transition-colors">
                            Duplicar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats row -->
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-3xl font-bold text-gray-900">{{ stats.days_count }}</p>
                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Days</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-3xl font-bold text-gray-900">{{ stats.total_shows }}</p>
                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Shows</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-3xl font-bold text-yellow-600">{{ stats.unique_designers }}</p>
                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Designers</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-3xl font-bold text-yellow-600">{{ stats.shows_with_designers }}/{{ stats.total_shows }}</p>
                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Designer Shows</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-3xl font-bold text-purple-600">{{ stats.total_models }}</p>
                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Models</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-3xl font-bold text-yellow-600">{{ stats.casting_checked_in }}/{{ stats.casting_models }}</p>
                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Check-in Model Casting</p>
                </div>
            </div>

            <!-- Timeline -->
            <div class="space-y-4">
                <div v-for="day in event.event_days" :key="day.id"
                    class="bg-white rounded-2xl border border-gray-200 overflow-hidden">

                    <!-- Day header -->
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 bg-gray-50">
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full"
                            :class="dayTypeConfig[day.type]?.class">
                            {{ dayTypeConfig[day.type]?.label }}
                        </span>
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-900">{{ day.label }}</h3>
                            <p class="text-xs text-gray-500">
                                {{ formatDateShort(day.date) }}
                                <span v-if="day.start_time"> · {{ day.start_time }}<span v-if="day.end_time"> – {{ day.end_time }}</span></span>
                            </p>
                        </div>
                        <div v-if="day.type === 'show_day'" class="text-xs text-gray-400">
                            {{ (day.shows ?? []).length }} show{{ (day.shows ?? []).length !== 1 ? 's' : '' }}
                        </div>
                    </div>

                    <!-- CASTING -->
                    <div v-if="day.type === 'casting'" class="px-6 py-5">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                            <div class="bg-yellow-50 rounded-xl p-3 text-center border border-yellow-200">
                                <p class="text-xl font-bold text-yellow-700">{{ day.casting_slots?.length ?? 0 }}</p>
                                <p class="text-xs text-yellow-600">Total Slots</p>
                            </div>
                            <button @click="openSlotsModal(day)" class="bg-yellow-50 rounded-xl p-3 text-center border border-yellow-200 hover:bg-yellow-100 hover:border-yellow-300 transition-colors cursor-pointer">
                                <p class="text-xl font-bold text-yellow-700">{{ day.casting_slots?.filter(c => c.booked >= c.capacity).length ?? 0 }}/{{ day.casting_slots?.length ?? 0 }}</p>
                                <p class="text-xs text-yellow-600">slots occupied</p>
                            </button>
                            <div class="bg-yellow-50 rounded-xl p-3 text-center border border-yellow-200">
                                <p class="text-xl font-bold text-yellow-700">{{ day.casting_slots?.[0]?.time ? formatTime(day.casting_slots[0].time) : '—' }}</p>
                                <p class="text-xs text-yellow-600">First Slot</p>
                            </div>
                            <div class="bg-yellow-50 rounded-xl p-3 text-center border border-yellow-200">
                                <p class="text-xl font-bold text-yellow-700">{{ day.casting_slots?.length ? formatTime(day.casting_slots[day.casting_slots.length - 1].time) : '—' }}</p>
                                <p class="text-xs text-yellow-600">Last Slot</p>
                            </div>
                        </div>
                        <p v-if="day.description" class="text-sm text-gray-500">{{ day.description }}</p>
                    </div>

                    <!-- FITTING (standalone day) -->
                    <div v-else-if="day.type === 'fitting'" class="px-6 py-5">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
                            <div class="bg-orange-50 rounded-xl p-3 text-center border border-orange-200">
                                <p class="text-xl font-bold text-orange-700">{{ day.fitting_slots?.length ?? 0 }}</p>
                                <p class="text-xs text-orange-600">Total Slots</p>
                            </div>
                            <div class="bg-orange-50 rounded-xl p-3 text-center border border-orange-200">
                                <p class="text-xl font-bold text-orange-700">{{ day.fitting_slots?.reduce((sum, s) => sum + (s.assignments?.length ?? 0), 0) ?? 0 }}</p>
                                <p class="text-xs text-orange-600">Diseñadores Asignados</p>
                            </div>
                            <div class="bg-orange-50 rounded-xl p-3 text-center border border-orange-200">
                                <p class="text-xl font-bold text-orange-700">{{ day.fitting_slots?.[0]?.time ? formatTime(day.fitting_slots[0].time) : '—' }} – {{ day.fitting_slots?.length ? formatTime(day.fitting_slots[day.fitting_slots.length - 1].time) : '—' }}</p>
                                <p class="text-xs text-orange-600">Rango Horario</p>
                            </div>
                        </div>

                        <!-- Fitting slots with designer assignments -->
                        <div class="space-y-2">
                            <div v-for="slot in day.fitting_slots" :key="slot.id"
                                class="flex items-start gap-3 bg-orange-50/50 border border-orange-100 rounded-xl px-4 py-3">
                                <div class="flex-shrink-0 pt-0.5">
                                    <span class="text-sm font-bold text-orange-800">{{ formatTime(slot.time) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div v-if="(slot.assignments ?? []).length > 0" class="flex flex-wrap gap-1.5">
                                        <div v-for="assignment in slot.assignments" :key="assignment.id"
                                            class="flex items-center gap-1 bg-white border border-orange-200 rounded-lg px-2 py-1 text-xs">
                                            <span class="font-medium text-gray-900">{{ assignment.designer?.full_name }}</span>
                                            <span v-if="assignment.designer?.designer_profile?.brand_name" class="text-gray-400">· {{ assignment.designer.designer_profile.brand_name }}</span>
                                            <button @click="removeFittingDesigner(slot, assignment.designer_id)"
                                                class="text-red-400 hover:text-red-600 ml-0.5">
                                                <XMarkIcon class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                    <p v-else class="text-xs text-orange-500 italic">Sin diseñadores</p>
                                </div>
                                <button @click="openFittingAssignModal(slot)"
                                    class="flex-shrink-0 px-2.5 py-1 bg-orange-600 text-white rounded-lg text-xs font-medium hover:bg-orange-700 transition-colors">
                                    + Diseñador
                                </button>
                            </div>
                        </div>
                        <p v-if="day.description" class="text-sm text-gray-500 mt-3">{{ day.description }}</p>
                    </div>

                    <!-- SHOW DAY -->
                    <div v-else-if="day.type === 'show_day'" class="p-4">
                        <!-- Fitting section (if show_day has fitting in the morning) -->
                        <div v-if="day.fitting_slots?.length" class="mb-4 bg-orange-50/50 rounded-xl border border-orange-200 p-4">
                            <h4 class="text-sm font-bold text-orange-800 mb-3 flex items-center gap-2">
                                <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-orange-800/60 text-orange-300">Fitting</span>
                                {{ day.fitting_slots.length }} slots · {{ day.fitting_slots.reduce((sum, s) => sum + (s.assignments?.length ?? 0), 0) }} diseñadores
                            </h4>
                            <div class="space-y-1.5">
                                <div v-for="slot in day.fitting_slots" :key="slot.id"
                                    class="flex items-center gap-3 bg-white border border-orange-100 rounded-lg px-3 py-2">
                                    <span class="text-xs font-bold text-orange-800 w-16">{{ formatTime(slot.time) }}</span>
                                    <div class="flex-1 flex flex-wrap gap-1">
                                        <span v-for="assignment in slot.assignments" :key="assignment.id"
                                            class="inline-flex items-center gap-1 bg-orange-100 rounded px-1.5 py-0.5 text-xs text-orange-800">
                                            {{ assignment.designer?.full_name }}
                                            <button @click="removeFittingDesigner(slot, assignment.designer_id)"
                                                class="text-red-400 hover:text-red-600">
                                                <XMarkIcon class="w-3 h-3" />
                                            </button>
                                        </span>
                                        <span v-if="!(slot.assignments?.length)" class="text-xs text-orange-400 italic">vacío</span>
                                    </div>
                                    <button @click="openFittingAssignModal(slot)"
                                        class="text-xs text-orange-600 hover:text-orange-800 font-medium">+ Diseñador</button>
                                </div>
                            </div>
                        </div>

                        <div v-if="day.shows?.length" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                            <div v-for="show in day.shows" :key="show.id"
                                class="rounded-xl border-2 p-4 transition-all"
                                :class="(show.designers ?? []).length > 0 ? 'border-gray-200 bg-white' : 'border-dashed border-yellow-400 bg-yellow-50/20'">

                                <!-- Show time + occupancy badge -->
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-xl font-bold text-gray-900">{{ show.formatted_time }}</span>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full border"
                                        :class="slotBadgeClass(show)">
                                        {{ (show.designers ?? []).length }} diseñadores
                                    </span>
                                </div>

                                <!-- Designer list -->
                                <div v-if="(show.designers ?? []).length > 0" class="space-y-2 mb-3">
                                    <div v-for="(designer, di) in show.designers" :key="designer.id"
                                        class="flex items-center gap-2 bg-gray-50 rounded-lg px-2.5 py-2">
                                        <!-- Order badge -->
                                        <span class="w-5 h-5 rounded-full bg-black text-white text-xs flex items-center justify-center flex-shrink-0 font-bold">
                                            {{ designer.pivot?.order ?? di + 1 }}
                                        </span>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 truncate">{{ designer.full_name }}</p>
                                            <p v-if="designer.designer_profile?.brand_name" class="text-xs text-gray-500 truncate">
                                                {{ designer.designer_profile.brand_name }}
                                                <span v-if="designer.pivot?.collection_name" class="text-gray-400">· {{ designer.pivot.collection_name }}</span>
                                            </p>
                                            <p v-if="getDesignerFitting(designer.id)" class="text-xs text-orange-600 truncate">
                                                Fitting: {{ getDesignerFitting(designer.id).day_label }} · {{ formatTime(getDesignerFitting(designer.id).time) }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-1 flex-shrink-0">
                                            <span class="text-xs text-green-600">{{ show.models_count ?? 0 }} ♀</span>
                                            <button @click="removeDesigner(show, designer.id)"
                                                class="text-red-400 hover:text-red-600 ml-1"
                                                title="Remover diseñador">
                                                <XMarkIcon class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="mb-3">
                                    <p class="text-xs text-yellow-700 font-medium">Sin diseñadores asignados</p>
                                </div>

                                <!-- Add designer button -->
                                <button @click="openAssignModal(show)"
                                    class="w-full py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors">
                                    + Agregar Diseñador
                                </button>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-400 px-2 py-3">No hay shows para este día.</p>
                    </div>

                    <!-- SETUP / CEREMONY / OTHER -->
                    <div v-else class="px-6 py-4">
                        <p v-if="day.description" class="text-sm text-gray-500">{{ day.description }}</p>
                        <p v-else class="text-sm text-gray-400 italic">Sin descripción</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assign Designer Modal -->
        <div v-if="assignModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl p-6 w-full max-w-sm shadow-2xl">
                <h3 class="text-lg font-bold mb-1">Agregar Diseñador</h3>
                <p class="text-sm text-gray-500 mb-4">Show: {{ selectedShow?.formatted_time ?? selectedShow?.scheduled_time }}</p>

                <div class="space-y-3 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Diseñador</label>
                        <select v-model="assignForm.designer_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">— Seleccionar —</option>
                            <option v-for="d in availableDesigners" :key="d.id" :value="d.id">
                                {{ d.name }}<span v-if="d.brand_name"> · {{ d.brand_name }}</span>
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Nombre de Colección (opcional)</label>
                        <input v-model="assignForm.collection_name" type="text"
                            placeholder="Dark Elegance SS26"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    </div>
                    <div v-if="allFittingSlots.length > 0">
                        <label class="block text-xs font-medium text-orange-600 mb-1">Horario de Fitting (opcional)</label>
                        <select v-model="assignForm.fitting_slot_id"
                            class="w-full border border-orange-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400/30">
                            <option value="">— Sin fitting —</option>
                            <option v-for="slot in allFittingSlots" :key="slot.id" :value="slot.id">
                                {{ slot.day_label }} · {{ formatTime(slot.time) }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button @click="assignModal = false"
                        class="flex-1 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Cancelar</button>
                    <button @click="submitAssign" :disabled="!assignForm.designer_id"
                        class="flex-1 py-2 bg-black text-white rounded-lg text-sm font-semibold disabled:opacity-40 hover:bg-gray-800">
                        Asignar
                    </button>
                </div>
            </div>
        </div>

        <!-- Casting Slots Modal -->
        <div v-if="slotsModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl max-h-[80vh] flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Casting Slots</h3>
                        <p class="text-sm text-gray-500">{{ slotsModal.label }}</p>
                    </div>
                    <button @click="slotsModal = null" class="p-1 text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <div class="overflow-y-auto flex-1 -mx-2 px-2">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-white">
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2 text-xs font-medium text-gray-500 uppercase">Horario</th>
                                <th class="text-center py-2 text-xs font-medium text-gray-500 uppercase">Registradas</th>
                                <th class="text-center py-2 text-xs font-medium text-gray-500 uppercase">Capacidad</th>
                                <th class="text-center py-2 text-xs font-medium text-gray-500 uppercase">Disponibles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="slot in slotsModal.casting_slots" :key="slot.id"
                                class="border-b border-gray-50"
                                :class="slot.booked >= slot.capacity ? 'bg-red-50' : slot.booked > 0 ? 'bg-yellow-50' : ''">
                                <td class="py-2.5 font-medium text-gray-900">{{ formatTime(slot.time) }}</td>
                                <td class="py-2.5 text-center font-bold" :class="slot.booked >= slot.capacity ? 'text-red-600' : slot.booked > 0 ? 'text-yellow-700' : 'text-gray-400'">
                                    {{ slot.booked }}
                                </td>
                                <td class="py-2.5 text-center text-gray-500">{{ slot.capacity }}</td>
                                <td class="py-2.5 text-center font-semibold" :class="(slot.capacity - slot.booked) <= 0 ? 'text-red-500' : 'text-green-600'">
                                    {{ slot.capacity - slot.booked }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 pt-3 border-t border-gray-200 flex items-center justify-between text-sm">
                    <span class="text-gray-500">
                        Total registradas: <strong class="text-gray-900">{{ slotsModal.casting_slots?.reduce((sum, s) => sum + s.booked, 0) ?? 0 }}</strong>
                    </span>
                    <button @click="slotsModal = null"
                        class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>

        <!-- Fitting Assign Designer Modal -->
        <div v-if="fittingModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl p-6 w-full max-w-sm shadow-2xl">
                <h3 class="text-lg font-bold mb-1">Asignar Diseñador al Fitting</h3>
                <p class="text-sm text-gray-500 mb-4">Horario: {{ selectedFittingSlot ? formatTime(selectedFittingSlot.time) : '' }}</p>

                <div class="space-y-3 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Diseñador</label>
                        <select v-model="fittingAssignForm.designer_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400/30">
                            <option value="">— Seleccionar —</option>
                            <option v-for="d in availableFittingDesigners" :key="d.id" :value="d.id">
                                {{ d.name }}<template v-if="d.brand_name"> · {{ d.brand_name }}</template>
                            </option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button @click="fittingModal = false"
                        class="flex-1 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Cancelar</button>
                    <button @click="submitFittingAssign" :disabled="!fittingAssignForm.designer_id"
                        class="flex-1 py-2 bg-orange-600 text-white rounded-lg text-sm font-semibold disabled:opacity-40 hover:bg-orange-700">
                        Asignar
                    </button>
                </div>
            </div>
        </div>

        <!-- Duplicate Modal -->
        <div v-if="dupModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl">
                <h3 class="text-lg font-bold mb-4">Duplicar Evento</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre nuevo evento</label>
                        <input v-model="dupForm.name" type="text"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/20" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha inicio</label>
                            <input v-model="dupForm.start_date" type="date"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/20" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha fin</label>
                            <input v-model="dupForm.end_date" type="date"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/20" />
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button @click="dupModal = false"
                        class="flex-1 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Cancelar</button>
                    <button @click="submitDuplicate" :disabled="dupForm.processing"
                        class="flex-1 py-2 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-60">
                        Duplicar
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
