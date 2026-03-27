<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    advisors: Array,
    isLeader: Boolean,
    activityTypes: Object,
});

// ── State ──────────────────────────────────────────────────────────
const currentView = ref('month');
const currentDate = ref(new Date());
const events = ref([]);
const loading = ref(false);
const selectedAdvisor = ref('');
const selectedEvent = ref(null);
const showModal = ref(false);

// ── Date helpers ───────────────────────────────────────────────────
const DAYS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

function toDateStr(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function sameDay(a, b) {
    return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

function isToday(d) {
    return sameDay(d, new Date());
}

// ── Navigation ─────────────────────────────────────────────────────
const headerLabel = computed(() => {
    const d = currentDate.value;
    if (currentView.value === 'day') {
        return `${DAYS[getWeekday(d)]} ${d.getDate()} de ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
    }
    if (currentView.value === 'week') {
        const { start, end } = weekRange.value;
        if (start.getMonth() === end.getMonth()) {
            return `${start.getDate()} – ${end.getDate()} de ${MONTHS[start.getMonth()]} ${start.getFullYear()}`;
        }
        return `${start.getDate()} ${MONTHS[start.getMonth()].slice(0,3)} – ${end.getDate()} ${MONTHS[end.getMonth()].slice(0,3)} ${end.getFullYear()}`;
    }
    return `${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
});

function navigate(dir) {
    const d = new Date(currentDate.value);
    if (currentView.value === 'month') d.setMonth(d.getMonth() + dir);
    else if (currentView.value === 'week') d.setDate(d.getDate() + dir * 7);
    else d.setDate(d.getDate() + dir);
    currentDate.value = d;
}

function goToday() {
    currentDate.value = new Date();
}

// ── Date ranges ────────────────────────────────────────────────────
function getWeekday(d) {
    return (d.getDay() + 6) % 7; // Mon=0
}

const weekRange = computed(() => {
    const d = new Date(currentDate.value);
    const wd = getWeekday(d);
    const start = new Date(d); start.setDate(d.getDate() - wd);
    const end = new Date(start); end.setDate(start.getDate() + 6);
    return { start, end };
});

const fetchRange = computed(() => {
    const d = currentDate.value;
    if (currentView.value === 'day') {
        const start = new Date(d.getFullYear(), d.getMonth(), d.getDate());
        const end = new Date(start); end.setDate(start.getDate() + 1);
        return { start: toDateStr(start), end: toDateStr(end) };
    }
    if (currentView.value === 'week') {
        const { start, end } = weekRange.value;
        const endNext = new Date(end); endNext.setDate(end.getDate() + 1);
        return { start: toDateStr(start), end: toDateStr(endNext) };
    }
    // month — include buffer for partial weeks
    const first = new Date(d.getFullYear(), d.getMonth(), 1);
    const last = new Date(d.getFullYear(), d.getMonth() + 1, 0);
    const startBuf = new Date(first); startBuf.setDate(first.getDate() - getWeekday(first));
    const endBuf = new Date(last); endBuf.setDate(last.getDate() + (6 - getWeekday(last)) + 1);
    return { start: toDateStr(startBuf), end: toDateStr(endBuf) };
});

// ── Monthly grid ───────────────────────────────────────────────────
const monthGrid = computed(() => {
    const d = currentDate.value;
    const first = new Date(d.getFullYear(), d.getMonth(), 1);
    const last = new Date(d.getFullYear(), d.getMonth() + 1, 0);
    const startDay = new Date(first); startDay.setDate(first.getDate() - getWeekday(first));

    const weeks = [];
    let cursor = new Date(startDay);
    while (cursor <= last || getWeekday(cursor) !== 0) {
        const week = [];
        for (let i = 0; i < 7; i++) {
            week.push({
                date: new Date(cursor),
                inMonth: cursor.getMonth() === d.getMonth(),
                today: isToday(cursor),
            });
            cursor.setDate(cursor.getDate() + 1);
        }
        weeks.push(week);
        if (weeks.length > 6) break;
    }
    return weeks;
});

// ── Weekly columns ─────────────────────────────────────────────────
const weekDays = computed(() => {
    const { start } = weekRange.value;
    const days = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        days.push(d);
    }
    return days;
});

const HOURS = Array.from({ length: 11 }, (_, i) => i + 8); // 8..18

// ── Events per day lookup ──────────────────────────────────────────
function eventsForDay(date) {
    return events.value.filter(e => {
        const ed = new Date(e.start);
        return sameDay(ed, date);
    });
}

function eventsForHour(date, hour) {
    return events.value.filter(e => {
        const ed = new Date(e.start);
        return sameDay(ed, date) && ed.getHours() === hour;
    });
}

// ── Event styling ──────────────────────────────────────────────────
const TYPE_COLORS = {
    call:    { bg: 'bg-blue-100',   text: 'text-blue-700',   border: 'border-blue-300',   dot: 'bg-blue-500' },
    email:   { bg: 'bg-purple-100', text: 'text-purple-700', border: 'border-purple-300', dot: 'bg-purple-500' },
    meeting: { bg: 'bg-green-100',  text: 'text-green-700',  border: 'border-green-300',  dot: 'bg-green-500' },
    note:    { bg: 'bg-gray-100',   text: 'text-gray-700',   border: 'border-gray-300',   dot: 'bg-gray-500' },
};

function typeStyle(type) {
    return TYPE_COLORS[type] || { bg: 'bg-orange-100', text: 'text-orange-700', border: 'border-orange-300', dot: 'bg-orange-500' };
}

function typeLabel(type) {
    return props.activityTypes?.[type]?.label || type;
}

function typeIcon(type) {
    const icons = { call: '📞', email: '📧', meeting: '👥', note: '📝', status_change: '🔄', assignment: '👤', system: '⚙️' };
    return icons[type] || '📌';
}

// ── Format helpers ─────────────────────────────────────────────────
function formatTime(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', hour12: true });
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
}

function hourLabel(h) {
    if (h === 0) return '12 AM';
    if (h < 12) return `${h} AM`;
    if (h === 12) return '12 PM';
    return `${h - 12} PM`;
}

// ── Fetch events ───────────────────────────────────────────────────
async function fetchEvents() {
    loading.value = true;
    try {
        const params = { start: fetchRange.value.start, end: fetchRange.value.end };
        if (selectedAdvisor.value) params.advisor = selectedAdvisor.value;
        const { data } = await axios.get('/admin/sales/calendar/events', { params });
        events.value = data;
    } catch (e) {
        console.error('Error fetching calendar events', e);
    } finally {
        loading.value = false;
    }
}

watch([fetchRange, selectedAdvisor], fetchEvents, { immediate: true });

// ── Modal ──────────────────────────────────────────────────────────
function openEvent(evt) {
    selectedEvent.value = evt;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    selectedEvent.value = null;
}

// ── Complete activity ──────────────────────────────────────────────
async function completeActivity(evt) {
    if (!evt || !evt.id) return;
    try {
        const token = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1];
        await axios.patch(`/admin/sales/activities/${evt.id}/complete`, {}, {
            headers: {
                'X-XSRF-TOKEN': token ? decodeURIComponent(token) : '',
                'Accept': 'application/json',
            }
        });
        evt.status = 'completed';
        showModal.value = false;
        fetchEvents();
    } catch (e) {
        console.error('Error completing activity', e);
    }
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Calendario de Ventas</h2>
        </template>

        <div>
            <!-- ═══ Toolbar ═══ -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex flex-wrap items-center justify-between gap-4">
                <!-- View toggle -->
                <div class="inline-flex rounded-lg border border-gray-200 overflow-hidden">
                    <button
                        v-for="v in [{ key: 'day', label: 'Día' }, { key: 'week', label: 'Semana' }, { key: 'month', label: 'Mes' }]"
                        :key="v.key"
                        @click="currentView = v.key"
                        class="px-4 py-2 text-sm font-medium transition-colors"
                        :class="currentView === v.key ? 'bg-black text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                    >
                        {{ v.label }}
                    </button>
                </div>

                <!-- Navigation -->
                <div class="flex items-center gap-3">
                    <button @click="navigate(-1)" class="p-2 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button @click="goToday" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition-colors">Hoy</button>
                    <span class="text-sm font-semibold text-gray-900 min-w-[220px] text-center">{{ headerLabel }}</span>
                    <button @click="navigate(1)" class="p-2 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>

                <!-- Advisor filter (leader only) -->
                <div v-if="isLeader" class="flex items-center gap-2">
                    <select v-model="selectedAdvisor" class="border border-gray-200 rounded-lg text-sm px-3 py-2 text-gray-700 focus:ring-1 focus:ring-black focus:border-black">
                        <option value="">Todos los asesores</option>
                        <option v-for="a in advisors" :key="a.id" :value="a.id">{{ a.first_name }} {{ a.last_name }}</option>
                    </select>
                </div>
            </div>

            <!-- Loading overlay -->
            <div v-if="loading" class="flex justify-center py-12">
                <svg class="animate-spin h-8 w-8 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
            </div>

            <!-- ═══ MONTHLY VIEW ═══ -->
            <div v-if="!loading && currentView === 'month'" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <!-- Day headers -->
                <div class="grid grid-cols-7 border-b border-gray-200">
                    <div v-for="day in DAYS" :key="day" class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-center">
                        {{ day }}
                    </div>
                </div>
                <!-- Weeks -->
                <div v-for="(week, wi) in monthGrid" :key="wi" class="grid grid-cols-7 border-b border-gray-100 last:border-b-0">
                    <div
                        v-for="(cell, ci) in week"
                        :key="ci"
                        class="min-h-[100px] p-2 border-r border-gray-100 last:border-r-0 transition-colors"
                        :class="{ 'bg-gray-50/60': !cell.inMonth }"
                    >
                        <!-- Date number -->
                        <div class="flex items-center justify-between mb-1">
                            <span
                                class="text-xs font-medium leading-none"
                                :class="[
                                    cell.today ? 'bg-black text-white rounded-full w-6 h-6 flex items-center justify-center' : '',
                                    cell.inMonth ? 'text-gray-900' : 'text-gray-400',
                                ]"
                            >
                                {{ cell.date.getDate() }}
                            </span>
                        </div>
                        <!-- Event pills -->
                        <div class="space-y-0.5">
                            <button
                                v-for="evt in eventsForDay(cell.date).slice(0, 3)"
                                :key="evt.id"
                                @click="openEvent(evt)"
                                class="w-full text-left px-1.5 py-0.5 rounded text-[10px] font-medium truncate block border transition-opacity hover:opacity-80"
                                :class="[typeStyle(evt.type).bg, typeStyle(evt.type).text, typeStyle(evt.type).border]"
                            >
                                {{ evt.title || typeLabel(evt.type) }}
                            </button>
                            <span
                                v-if="eventsForDay(cell.date).length > 3"
                                class="text-[10px] text-gray-400 font-medium px-1"
                            >
                                +{{ eventsForDay(cell.date).length - 3 }} más
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ WEEKLY VIEW ═══ -->
            <div v-if="!loading && currentView === 'week'" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <!-- Header row -->
                <div class="grid grid-cols-[60px_repeat(7,1fr)] border-b border-gray-200">
                    <div class="border-r border-gray-200"></div>
                    <div
                        v-for="day in weekDays"
                        :key="day.toISOString()"
                        class="px-2 py-3 text-center border-r border-gray-100 last:border-r-0"
                    >
                        <div class="text-xs font-semibold text-gray-500 uppercase">{{ DAYS[getWeekday(day)] }}</div>
                        <div
                            class="text-sm font-bold mt-0.5"
                            :class="isToday(day) ? 'bg-black text-white rounded-full w-7 h-7 flex items-center justify-center mx-auto' : 'text-gray-900'"
                        >
                            {{ day.getDate() }}
                        </div>
                    </div>
                </div>
                <!-- Time grid -->
                <div class="max-h-[600px] overflow-y-auto">
                    <div v-for="hour in HOURS" :key="hour" class="grid grid-cols-[60px_repeat(7,1fr)] border-b border-gray-50 min-h-[56px]">
                        <div class="px-2 py-1 text-[11px] text-gray-400 font-medium border-r border-gray-200 text-right pr-3 pt-2">
                            {{ hourLabel(hour) }}
                        </div>
                        <div
                            v-for="day in weekDays"
                            :key="day.toISOString() + hour"
                            class="border-r border-gray-50 last:border-r-0 px-1 py-0.5"
                        >
                            <button
                                v-for="evt in eventsForHour(day, hour)"
                                :key="evt.id"
                                @click="openEvent(evt)"
                                class="w-full text-left px-2 py-1 rounded border mb-0.5 transition-opacity hover:opacity-80"
                                :class="[typeStyle(evt.type).bg, typeStyle(evt.type).text, typeStyle(evt.type).border]"
                            >
                                <div class="text-[11px] font-semibold truncate">{{ evt.title || typeLabel(evt.type) }}</div>
                                <div v-if="evt.lead_name" class="text-[10px] opacity-70 truncate">{{ evt.lead_name }}</div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ DAILY VIEW ═══ -->
            <div v-if="!loading && currentView === 'day'" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="max-h-[700px] overflow-y-auto">
                    <div v-for="hour in HOURS" :key="hour" class="border-b border-gray-50 last:border-b-0">
                        <div class="grid grid-cols-[70px_1fr] min-h-[64px]">
                            <div class="px-3 py-3 text-xs text-gray-400 font-medium text-right border-r border-gray-200">
                                {{ hourLabel(hour) }}
                            </div>
                            <div class="px-3 py-2 space-y-2">
                                <div
                                    v-for="evt in eventsForHour(currentDate, hour)"
                                    :key="evt.id"
                                    @click="openEvent(evt)"
                                    class="rounded-lg border p-3 cursor-pointer transition-all hover:shadow-sm"
                                    :class="[typeStyle(evt.type).bg, typeStyle(evt.type).border]"
                                >
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full" :class="[typeStyle(evt.type).bg, typeStyle(evt.type).text]">
                                                <span v-if="typeIcon(evt.type)" v-text="typeIcon(evt.type)"></span>
                                                {{ typeLabel(evt.type) }}
                                            </span>
                                            <span class="text-xs text-gray-500">{{ formatTime(evt.scheduled_at) }}</span>
                                        </div>
                                        <span
                                            v-if="evt.status === 'pending'"
                                            class="text-[10px] font-medium bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full"
                                        >Pendiente</span>
                                        <span
                                            v-else-if="evt.status === 'completed'"
                                            class="text-[10px] font-medium bg-green-100 text-green-700 px-2 py-0.5 rounded-full"
                                        >Completada</span>
                                    </div>
                                    <h4 class="text-sm font-semibold text-gray-900">{{ evt.title || typeLabel(evt.type) }}</h4>
                                    <div v-if="evt.lead_name" class="text-xs text-gray-600 mt-0.5">
                                        {{ evt.lead_name }}
                                        <span v-if="evt.company" class="text-gray-400"> · {{ evt.company }}</span>
                                    </div>
                                    <p v-if="evt.description" class="text-xs text-gray-500 mt-1 line-clamp-2">{{ evt.description }}</p>
                                    <div class="flex items-center justify-end mt-2" v-if="evt.status === 'pending'">
                                        <button
                                            @click.stop="completeActivity(evt)"
                                            class="text-xs font-medium bg-black text-white px-3 py-1 rounded-lg hover:bg-gray-800 transition-colors"
                                        >
                                            Completar
                                        </button>
                                    </div>
                                </div>
                                <!-- Empty hour -->
                                <div v-if="eventsForHour(currentDate, hour).length === 0" class="h-full"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="!loading && events.length === 0" class="text-center py-16">
                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="mt-3 text-sm text-gray-500">No hay actividades programadas en este periodo</p>
            </div>
        </div>

        <!-- ═══ EVENT DETAIL MODAL ═══ -->
        <Teleport to="body">
            <div v-if="showModal && selectedEvent" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/40" @click="closeModal"></div>

                <!-- Panel -->
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 z-10">
                    <!-- Close button -->
                    <button @click="closeModal" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>

                    <!-- Type badge -->
                    <div class="mb-4">
                        <span
                            class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1 rounded-full border"
                            :class="[typeStyle(selectedEvent.type).bg, typeStyle(selectedEvent.type).text, typeStyle(selectedEvent.type).border]"
                        >
                            <span v-if="typeIcon(selectedEvent.type)" v-text="typeIcon(selectedEvent.type)"></span>
                            {{ typeLabel(selectedEvent.type) }}
                        </span>
                        <span
                            v-if="selectedEvent.status === 'pending'"
                            class="ml-2 text-xs font-medium bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full"
                        >Pendiente</span>
                        <span
                            v-else-if="selectedEvent.status === 'completed'"
                            class="ml-2 text-xs font-medium bg-green-100 text-green-700 px-2 py-0.5 rounded-full"
                        >Completada</span>
                    </div>

                    <!-- Title -->
                    <h3 class="text-lg font-bold text-gray-900 mb-3">{{ selectedEvent.title || typeLabel(selectedEvent.type) }}</h3>

                    <!-- Details -->
                    <div class="space-y-2 text-sm mb-5">
                        <div v-if="selectedEvent.description" class="text-gray-600">{{ selectedEvent.description }}</div>

                        <div class="flex items-center gap-2 text-gray-500">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ formatDateTime(selectedEvent.scheduled_at) }}
                        </div>

                        <div v-if="selectedEvent.lead_name" class="flex items-center gap-2 text-gray-500">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span>
                                {{ selectedEvent.lead_name }}
                                <span v-if="selectedEvent.company" class="text-gray-400"> · {{ selectedEvent.company }}</span>
                            </span>
                        </div>

                        <div v-if="selectedEvent.advisor" class="flex items-center gap-2 text-gray-500">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
                            <span>Asesor: {{ selectedEvent.advisor }}</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-3 border-t border-gray-100 pt-4">
                        <a
                            v-if="selectedEvent.lead_id"
                            :href="`/admin/sales/leads/${selectedEvent.lead_id}`"
                            class="flex-1 text-center text-sm font-medium py-2 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Ver lead
                        </a>
                        <button
                            v-if="selectedEvent.status === 'pending'"
                            @click="completeActivity(selectedEvent)"
                            class="flex-1 text-center text-sm font-medium py-2 rounded-lg bg-black text-white hover:bg-gray-800 transition-colors"
                        >
                            Completar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
