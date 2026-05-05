<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    advisors: Array,
    isLeader: Boolean,
    crossArea: Boolean,
    activityTypes: Object,
});

const currentUserId = computed(() => usePage().props.auth?.user?.id);

// Etiqueta para distinguir el área de cada chip cuando el user es cross-area.
const AREA_LABEL = { sales: 'SA', sponsorship: 'SP' };
function areaLabel(ev) { return AREA_LABEL[ev?.area] || 'SA'; }

// El user puede ACTUAR (editar/completar/borrar/ir al lead) sobre un evento si:
//  - es cross-area, o
//  - el evento pertenece a su home area (sales aquí), o
//  - es una actividad personal global y él la creó/le pertenece.
function canActOnEvent(ev) {
    if (!ev) return false;
    if (props.crossArea) return true;
    if (ev.area === 'sales') return true;
    if (!ev.area && ev.source === 'personal') {
        return ev.advisor_id === currentUserId.value;
    }
    return false;
}
// (L) si es líder en cualquier área (sales, sponsorship o cross-area).
function isAnyLeader(a) {
    return a.sales_type === 'lider'
        || a.sponsorship_type === 'lider'
        || (a.extra_areas || []).length > 0;
}

// ── State ──────────────────────────────────────────────────────────
const currentView = ref('month');
const currentDate = ref(new Date());
const events = ref([]);
const loading = ref(false);
const selectedAdvisor = ref('');
const selectedEvent = ref(null);
const showModal = ref(false);

// ── Date helpers ───────────────────────────────────────────────────
const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

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
        return `${DAYS[getWeekday(d)]} ${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
    }
    if (currentView.value === 'week') {
        const { start, end } = weekRange.value;
        if (start.getMonth() === end.getMonth()) {
            return `${start.getDate()} – ${end.getDate()} ${MONTHS[start.getMonth()]} ${start.getFullYear()}`;
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

// "+N more" abre un modal con todos los eventos del día y scroll interno.
const showMoreModal = ref(false);
const moreModalDate = ref(null);
const moreModalEvents = computed(() =>
    moreModalDate.value ? eventsForDay(moreModalDate.value) : []
);
const moreModalDateLabel = computed(() => {
    if (!moreModalDate.value) return '';
    return moreModalDate.value.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' });
});
function openMoreModal(date) {
    moreModalDate.value = new Date(date);
    showMoreModal.value = true;
}
function openEventFromMore(evt) {
    showMoreModal.value = false;
    openEvent(evt);
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

const HOURS = Array.from({ length: 18 }, (_, i) => i + 6); // 6..23

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

// Override visual: actividades not_completed siempre se pintan en rojo suave
// para que se distingan a primera vista (el resto sigue por tipo).
function eventStyle(evt) {
    if (evt?.status === 'not_completed') {
        return { bg: 'bg-red-100', text: 'text-red-700', border: 'border-red-300', dot: 'bg-red-500' };
    }
    return typeStyle(evt?.type);
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
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
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

// Endpoint base depends on (source, area). Cross-area: una actividad de
// sponsorship vista en el calendar de sales debe pegarle a las rutas de sponsorship.
function endpointBase(ev) {
    if (!ev) return null;
    const area = ev.area || 'sales';
    if (ev.source === 'personal') {
        return `/admin/${area}/calendar-activities/${ev.id}`;
    }
    return area === 'sponsorship'
        ? `/admin/sponsorship/activities/${ev.id}`
        : `/admin/sales/activities/${ev.id}`;
}

// ── Complete activity ──────────────────────────────────────────────
async function completeActivity(evt) {
    if (!evt || !evt.id) return;
    try {
        const token = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1];
        await axios.patch(`${endpointBase(evt)}/complete`, {}, {
            headers: {
                'X-XSRF-TOKEN': token ? decodeURIComponent(token) : '',
                'Accept': 'application/json',
            }
        });
        const idx = events.value.findIndex(e => e.id === evt.id && e.source === evt.source);
        if (idx !== -1) {
            events.value[idx] = { ...events.value[idx], status: 'completed' };
        }
        if (selectedEvent.value?.id === evt.id) {
            selectedEvent.value = { ...selectedEvent.value, status: 'completed' };
        }
        showModal.value = false;
    } catch (e) {
        console.error('Error completing activity', e);
    }
}

async function deletePersonalActivity(evt) {
    if (evt?.source !== 'personal') return;
    if (!confirm('Delete this personal activity?')) return;
    try {
        const token = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1];
        await axios.delete(endpointBase(evt), {
            headers: {
                'X-XSRF-TOKEN': token ? decodeURIComponent(token) : '',
                'Accept': 'application/json',
            },
        });
        showModal.value = false;
        fetchEvents();
    } catch (e) {
        console.error('Error deleting personal activity', e);
    }
}

// ── New Personal Activity (calendar_activities) ───────────────────
// Las personales son GLOBALES (sin área) — bloquean disponibilidad del user
// en ambos calendars. Solo se permiten call/meeting (las notas pertenecen al
// timeline del lead, no al calendario).
const showCreateModal = ref(false);
const createForm = useForm({ user_id: '', type: 'call', title: '', description: '', scheduled_at: '', has_end_time: false, ends_at_time: '' });

function openCreateModal() {
    createForm.reset();
    createForm.type = 'call';
    createForm.user_id = '';
    createForm.has_end_time = false;
    createForm.ends_at_time = '';
    availabilityConflicts.value = [];
    showCreateModal.value = true;
}

function localDatetimeToUtcIso(s) {
    if (!s) return null;
    const d = new Date(s);
    return isNaN(d) ? null : d.toISOString();
}

function combineEndTimeIso(scheduledLocal, endTimeStr) {
    if (!endTimeStr || !scheduledLocal) return null;
    const datePart = scheduledLocal.split('T')[0];
    const d = new Date(`${datePart}T${endTimeStr}`);
    return isNaN(d) ? null : d.toISOString();
}

// ── Availability check (overlap real) ──
const availabilityConflicts = ref([]);
let availabilityTimer = null;
async function checkAvailability() {
    clearTimeout(availabilityTimer);
    availabilityTimer = setTimeout(async () => {
        const userId = createForm.user_id;
        const scheduled = createForm.scheduled_at;
        if (!userId || !scheduled) {
            availabilityConflicts.value = [];
            return;
        }
        const startIso = localDatetimeToUtcIso(scheduled);
        if (!startIso) return;
        const endIso = createForm.has_end_time ? combineEndTimeIso(scheduled, createForm.ends_at_time) : null;
        try {
            const res = await axios.get('/admin/sales/calendar/availability', {
                params: { user_id: userId, scheduled_at: startIso, ends_at: endIso || undefined },
            });
            availabilityConflicts.value = res.data?.conflicts ?? [];
        } catch (e) {
            availabilityConflicts.value = [];
        }
    }, 350);
}
watch([() => createForm.user_id, () => createForm.scheduled_at, () => createForm.has_end_time, () => createForm.ends_at_time], checkAvailability);

function submitCreate() {
    // Personal entry global: no enviamos `area` para que quede null.
    createForm.transform(d => ({
        ...d,
        scheduled_at: localDatetimeToUtcIso(d.scheduled_at),
        ends_at: d.has_end_time ? combineEndTimeIso(d.scheduled_at, d.ends_at_time) : null,
        user_id: d.user_id || null,
    })).post('/admin/sales/calendar-activities', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
            fetchEvents();
        },
    });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">{{ crossArea ? 'Calendar' : 'Sales Calendar' }}</h2>
        </template>

        <div>
            <!-- ═══ Toolbar ═══ -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex flex-wrap items-center justify-between gap-4">
                <!-- View toggle -->
                <div class="inline-flex rounded-lg border border-gray-200 overflow-hidden">
                    <button
                        v-for="v in [{ key: 'day', label: 'Day' }, { key: 'week', label: 'Week' }, { key: 'month', label: 'Month' }]"
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
                    <button @click="goToday" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition-colors">Today</button>
                    <span class="text-sm font-semibold text-gray-900 min-w-[220px] text-center">{{ headerLabel }}</span>
                    <button @click="navigate(1)" class="p-2 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>

                <!-- Advisor filter (leader only) -->
                <div v-if="isLeader" class="flex items-center gap-2">
                    <select v-model="selectedAdvisor" class="border border-gray-200 rounded-lg text-sm px-3 py-2 text-gray-700 focus:ring-1 focus:ring-black focus:border-black">
                        <option value="">All advisors</option>
                        <option v-for="a in advisors" :key="a.id" :value="a.id">
                            {{ a.first_name }} {{ a.last_name }} {{ (a.sales_type === 'lider' || a.sponsorship_type === 'lider' || a.extra_areas?.length) ? '(L)' : '' }}
                        </option>
                    </select>
                </div>

                <button @click="openCreateModal"
                    class="inline-flex items-center gap-1 px-3 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800">
                    + New Activity
                </button>
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
                                :class="[eventStyle(evt).bg, eventStyle(evt).text, eventStyle(evt).border]"
                            >
                                <span v-if="evt.area === 'sponsorship'" class="inline-block bg-white px-1 mr-0.5 rounded text-[9px] font-bold border" :class="eventStyle(evt).border">{{ areaLabel(evt) }}</span>{{ evt.title || typeLabel(evt.type) }}
                            </button>
                            <button
                                type="button"
                                v-if="eventsForDay(cell.date).length > 3"
                                @click="openMoreModal(cell.date)"
                                class="text-[10px] text-gray-500 font-medium px-1 hover:text-black hover:underline cursor-pointer"
                            >
                                +{{ eventsForDay(cell.date).length - 3 }} more
                            </button>
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
                                :class="[eventStyle(evt).bg, eventStyle(evt).text, eventStyle(evt).border]"
                            >
                                <div class="text-[11px] font-semibold truncate flex items-center gap-1">
                                    <span v-if="evt.area === 'sponsorship'" class="bg-white px-1 rounded text-[9px] border" :class="eventStyle(evt).border">{{ areaLabel(evt) }}</span>
                                    {{ evt.title || typeLabel(evt.type) }}
                                </div>
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
                                    :class="[eventStyle(evt).bg, eventStyle(evt).border]"
                                >
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="flex items-center gap-2">
                                            <span v-if="evt.area === 'sponsorship'" class="inline-block px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-gray-200 text-gray-700">{{ areaLabel(evt) }}</span>
                                            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full" :class="[eventStyle(evt).bg, eventStyle(evt).text]">
                                                <span v-if="typeIcon(evt.type)" v-text="typeIcon(evt.type)"></span>
                                                {{ typeLabel(evt.type) }}
                                            </span>
                                            <span class="text-xs text-gray-500">{{ evt.all_day ? 'All day' : formatTime(evt.start) }}</span>
                                        </div>
                                        <span
                                            v-if="evt.status === 'pending'"
                                            class="text-[10px] font-medium bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full"
                                        >Pending</span>
                                        <span
                                            v-else-if="evt.status === 'completed'"
                                            class="text-[10px] font-medium bg-green-100 text-green-700 px-2 py-0.5 rounded-full"
                                        >Completed</span>
                                    </div>
                                    <h4 class="text-sm font-semibold text-gray-900">{{ evt.title || typeLabel(evt.type) }}</h4>
                                    <div v-if="evt.lead_name" class="text-xs text-gray-600 mt-0.5">
                                        {{ evt.lead_name }}
                                        <span v-if="evt.company" class="text-gray-400"> · {{ evt.company }}</span>
                                    </div>
                                    <div v-if="evt.description" class="rich-text-preview text-xs text-gray-500 mt-1 line-clamp-2 break-words" v-html="evt.description"></div>
                                    <div class="flex items-center justify-end mt-2" v-if="evt.status === 'pending' && canActOnEvent(evt)">
                                        <button
                                            @click.stop="completeActivity(evt)"
                                            class="text-xs font-medium bg-black text-white px-3 py-1 rounded-lg hover:bg-gray-800 transition-colors"
                                        >
                                            Complete
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
                <p class="mt-3 text-sm text-gray-500">No activities scheduled in this period</p>
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
                            :class="[eventStyle(selectedEvent).bg, eventStyle(selectedEvent).text, eventStyle(selectedEvent).border]"
                        >
                            <span v-if="typeIcon(selectedEvent.type)" v-text="typeIcon(selectedEvent.type)"></span>
                            {{ typeLabel(selectedEvent.type) }}
                        </span>
                        <span
                            v-if="selectedEvent.status === 'pending'"
                            class="ml-2 text-xs font-medium bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full"
                        >Pending</span>
                        <span
                            v-else-if="selectedEvent.status === 'completed'"
                            class="ml-2 text-xs font-medium bg-green-100 text-green-700 px-2 py-0.5 rounded-full"
                        >Completed</span>
                    </div>

                    <!-- Title -->
                    <h3 class="text-lg font-bold text-gray-900 mb-3">{{ selectedEvent.title || typeLabel(selectedEvent.type) }}</h3>

                    <!-- Details -->
                    <div class="space-y-2 text-sm mb-5">
                        <div v-if="selectedEvent.description" class="rich-text-preview text-gray-600 break-words" v-html="selectedEvent.description"></div>

                        <div class="flex items-center gap-2 text-gray-500">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <template v-if="selectedEvent.all_day">{{ new Date(selectedEvent.start).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' }) }} <span class="text-gray-400">(All day)</span></template>
                            <template v-else>{{ formatDateTime(selectedEvent.start) }}<template v-if="selectedEvent.ends_at"> – {{ new Date(selectedEvent.ends_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) }}</template></template>
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
                            <span>Advisor: {{ selectedEvent.advisor }}</span>
                        </div>
                    </div>

                    <!-- Read-only banner para eventos cross-area -->
                    <div v-if="!canActOnEvent(selectedEvent)" class="bg-blue-50 border border-blue-200 rounded-lg px-3 py-2 text-xs text-blue-800 mb-3">
                        Read-only: this activity belongs to the <strong class="capitalize">{{ selectedEvent.area }}</strong> team.
                    </div>
                    <!-- Actions -->
                    <div v-if="canActOnEvent(selectedEvent)" class="flex items-center gap-3 border-t border-gray-100 pt-4">
                        <a
                            v-if="selectedEvent.lead_id"
                            :href="`/admin/${selectedEvent.area || 'sales'}/leads/${selectedEvent.lead_id}`"
                            class="flex-1 text-center text-sm font-medium py-2 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            View lead
                        </a>
                        <button
                            v-if="selectedEvent.status === 'pending'"
                            @click="completeActivity(selectedEvent)"
                            class="flex-1 text-center text-sm font-medium py-2 rounded-lg bg-black text-white hover:bg-gray-800 transition-colors"
                        >
                            Complete
                        </button>
                        <button v-if="selectedEvent.source === 'personal'" @click="deletePersonalActivity(selectedEvent)"
                            class="px-3 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Create Personal Activity Modal -->
        <Teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showCreateModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">New Activity (calendar)</h3>
                        <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">×</button>
                    </div>
                    <p class="text-xs text-gray-500 mb-4">This activity is independent of any lead. It blocks the user's calendar for both areas.</p>
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Type *</label>
                                <select v-model="createForm.type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                    <option value="call">Call</option>
                                    <option value="meeting">Meeting</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Scheduled at</label>
                                <input v-model="createForm.scheduled_at" type="datetime-local" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            </div>
                        </div>
                        <div v-if="createForm.scheduled_at" class="flex items-center gap-2">
                            <label class="inline-flex items-center gap-2 text-xs text-gray-600">
                                <input v-model="createForm.has_end_time" type="checkbox" class="rounded" />
                                Specify end time
                            </label>
                            <input v-if="createForm.has_end_time" v-model="createForm.ends_at_time" type="time"
                                class="border border-gray-300 rounded-lg px-2 py-1 text-sm" />
                            <p v-if="createForm.errors.ends_at" class="text-xs text-red-500">{{ createForm.errors.ends_at }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Title *</label>
                            <input v-model="createForm.title" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            <p v-if="createForm.errors.title" class="text-xs text-red-500 mt-1">{{ createForm.errors.title }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                            <RichTextEditor v-model="createForm.description" placeholder="Add details..." min-height="100px" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Assign to</label>
                            <select v-model="createForm.user_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                <option value="">— Me</option>
                                <option v-for="a in advisors" :key="a.id" :value="a.id">
                                    {{ a.first_name }} {{ a.last_name }} {{ isAnyLeader(a) ? '(L)' : '' }}
                                </option>
                            </select>
                        </div>
                        <div v-if="availabilityConflicts.length" class="bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-xs">
                            <p class="font-medium text-red-800 mb-1">✕ {{ availabilityConflicts.length }} conflict{{ availabilityConflicts.length > 1 ? 's' : '' }} in ±30 min — pick another time:</p>
                            <ul class="space-y-0.5 text-red-700">
                                <li v-for="c in availabilityConflicts" :key="`${c.source}-${c.id}`" class="truncate">
                                    • {{ new Date(c.start).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) }} — {{ c.title }} <span class="text-red-500">({{ c.type }})</span>
                                </li>
                            </ul>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button @click="showCreateModal = false" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancel</button>
                            <button @click="submitCreate"
                                :disabled="createForm.processing || !createForm.title || availabilityConflicts.length > 0"
                                :title="availabilityConflicts.length > 0 ? 'Hay conflictos de horario' : ''"
                                class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed">
                                {{ createForm.processing ? 'Saving…' : 'Create' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ═══ MORE EVENTS MODAL — list of all events for a given day ═══ -->
        <Teleport to="body">
            <div v-if="showMoreModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="showMoreModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[80vh] flex flex-col z-10">
                    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
                        <h3 class="text-base font-bold text-gray-900">{{ moreModalDateLabel }}</h3>
                        <button @click="showMoreModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-3 space-y-2">
                        <button v-for="evt in moreModalEvents" :key="`${evt.source}-${evt.id}`"
                            @click="openEventFromMore(evt)"
                            class="w-full flex items-start gap-3 p-3 rounded-xl border text-left transition-colors hover:bg-gray-50"
                            :class="[eventStyle(evt).bg, eventStyle(evt).border]">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 bg-white/60" :class="eventStyle(evt).text">
                                <span v-text="typeIcon(evt.type)"></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span v-if="evt.area === 'sponsorship'" class="inline-block px-1 rounded text-[9px] font-bold bg-white border" :class="eventStyle(evt).border">SP</span>
                                    <p class="font-semibold text-gray-900 text-sm truncate">{{ evt.title || typeLabel(evt.type) }}</p>
                                </div>
                                <p class="text-xs text-gray-600 mt-0.5">
                                    <template v-if="evt.all_day">All day</template>
                                    <template v-else>{{ formatTime(evt.start) }}<template v-if="evt.ends_at"> – {{ new Date(evt.ends_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) }}</template></template>
                                </p>
                                <p v-if="evt.advisor" class="text-xs text-gray-500 mt-0.5">→ {{ evt.advisor }}</p>
                                <p v-if="evt.lead_name" class="text-xs text-gray-500 truncate">{{ evt.lead_name }}<span v-if="evt.company" class="text-gray-400"> · {{ evt.company }}</span></p>
                            </div>
                        </button>
                        <p v-if="!moreModalEvents.length" class="text-sm text-gray-400 text-center py-6">No activities.</p>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<style>
/* Render TipTap-stored descriptions as proper HTML — listas, links, formato.
   Misma intención que .sponsorship-email-preview en el otro panel. */
.rich-text-preview p { margin: 0 0 0.5em 0; }
.rich-text-preview p:last-child { margin-bottom: 0; }
.rich-text-preview ul, .rich-text-preview ol { padding-left: 1.5em; margin: 0 0 0.5em 0; }
.rich-text-preview ul { list-style-type: disc; }
.rich-text-preview ol { list-style-type: decimal; }
.rich-text-preview a { color: #2563eb; text-decoration: underline; word-break: break-all; }
.rich-text-preview strong { font-weight: 600; color: #111827; }
.rich-text-preview em { font-style: italic; }
.rich-text-preview u { text-decoration: underline; }
.rich-text-preview s { text-decoration: line-through; }
</style>
