<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { ChevronLeftIcon, ChevronRightIcon, CalendarDaysIcon, EyeIcon, CheckCircleIcon, XMarkIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    advisors: Array,
    isLider: Boolean,
    activityTypes: Object,
});

const currentView = ref('month');
const currentDate = ref(new Date());
const events = ref([]);
const loading = ref(false);
const selectedAdvisor = ref('');
const selectedEvent = ref(null);
const showModal = ref(false);

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
    const first = new Date(d.getFullYear(), d.getMonth(), 1);
    const last = new Date(d.getFullYear(), d.getMonth() + 1, 0);
    const startBuf = new Date(first); startBuf.setDate(first.getDate() - getWeekday(first));
    const endBuf = new Date(last); endBuf.setDate(last.getDate() + (6 - getWeekday(last)) + 1);
    return { start: toDateStr(startBuf), end: toDateStr(endBuf) };
});

async function fetchEvents() {
    loading.value = true;
    try {
        const res = await axios.get('/admin/sponsorship/calendar/events', {
            params: {
                start: fetchRange.value.start,
                end: fetchRange.value.end,
                advisor: selectedAdvisor.value || undefined,
            },
        });
        events.value = res.data || [];
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

watch([currentDate, currentView, selectedAdvisor], fetchEvents, { deep: false });
onMounted(fetchEvents);

const monthGrid = computed(() => {
    const d = currentDate.value;
    const first = new Date(d.getFullYear(), d.getMonth(), 1);
    const start = new Date(first); start.setDate(first.getDate() - getWeekday(first));
    const cells = [];
    for (let i = 0; i < 42; i++) {
        const cell = new Date(start); cell.setDate(start.getDate() + i);
        cells.push(cell);
    }
    return cells;
});

function eventsOn(date) {
    return events.value.filter(e => {
        const ed = new Date(e.start);
        return sameDay(ed, date);
    }).sort((a, b) => new Date(a.start) - new Date(b.start));
}

function formatTime(d) {
    const dt = new Date(d);
    return dt.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
}

function openEvent(e) {
    selectedEvent.value = e;
    isEditing.value = false;
    showModal.value = true;
}

// Endpoint base depends on source: lead activities vs personal calendar entries.
function endpointBase(ev) {
    if (!ev) return null;
    return ev.source === 'personal'
        ? `/admin/sponsorship/calendar-activities/${ev.id}`
        : `/admin/sponsorship/activities/${ev.id}`;
}

function completeActivity(ev) {
    useForm({}).patch(`${endpointBase(ev)}/complete`, {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false; fetchEvents(); },
    });
}

function deletePersonalActivity(ev) {
    if (ev.source !== 'personal') return;
    if (!confirm('Delete this personal activity?')) return;
    useForm({}).delete(endpointBase(ev), {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false; fetchEvents(); },
    });
}

// ──────────── Edit (only for call/meeting) ────────────
const isEditing = ref(false);
const editForm = useForm({ title: '', description: '', scheduled_at: '' });

// Datetime-local helpers (TZ-safe). Mismo patrón que Leads/Show.vue.
function toLocalDatetimeInput(utcStr) {
    if (!utcStr) return '';
    const d = new Date(utcStr);
    if (isNaN(d)) return '';
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
function localDatetimeToUtcIso(localStr) {
    if (!localStr) return null;
    const d = new Date(localStr);
    if (isNaN(d)) return null;
    return d.toISOString();
}

function startEdit() {
    if (!selectedEvent.value) return;
    editForm.title        = selectedEvent.value.title || '';
    editForm.description  = selectedEvent.value.description || '';
    editForm.scheduled_at = toLocalDatetimeInput(selectedEvent.value.start);
    isEditing.value = true;
}

function saveEdit() {
    if (!selectedEvent.value) return;
    editForm.transform(d => ({
        ...d,
        scheduled_at: localDatetimeToUtcIso(d.scheduled_at),
        _method: 'PATCH',
    })).post(endpointBase(selectedEvent.value), {
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false;
            showModal.value = false;
            fetchEvents();
        },
    });
}

// ──────────── New Personal Activity (calendar_activities) ────────────
const showCreateModal = ref(false);
const createForm = useForm({ user_id: '', type: 'call', title: '', description: '', scheduled_at: '' });

function openCreateModal() {
    createForm.reset();
    createForm.type = 'call';
    createForm.user_id = '';
    showCreateModal.value = true;
}

function submitCreate() {
    createForm.transform(d => ({
        ...d,
        area: 'sponsorship',
        scheduled_at: localDatetimeToUtcIso(d.scheduled_at),
        user_id: d.user_id || null,
    })).post('/admin/sponsorship/calendar-activities', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
            fetchEvents();
        },
    });
}

const dayHours = Array.from({ length: 24 }, (_, i) => i);
function hourLabel(h) {
    if (h === 0) return '12 AM';
    if (h < 12) return `${h} AM`;
    if (h === 12) return '12 PM';
    return `${h - 12} PM`;
}
function eventsAtHour(date, hour) {
    return events.value.filter(e => {
        const ed = new Date(e.start);
        return sameDay(ed, date) && ed.getHours() === hour;
    });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Sponsorship Calendar</h2>
        </template>

        <div class="max-w-7xl mx-auto space-y-4">
            <!-- Top toolbar -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-1">
                    <button @click="navigate(-1)" class="p-2 rounded-lg hover:bg-gray-100"><ChevronLeftIcon class="w-5 h-5" /></button>
                    <button @click="goToday" class="px-3 py-1.5 text-sm font-medium border border-gray-200 rounded-lg hover:bg-gray-50">Today</button>
                    <button @click="navigate(1)" class="p-2 rounded-lg hover:bg-gray-100"><ChevronRightIcon class="w-5 h-5" /></button>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 flex-1">{{ headerLabel }}</h3>

                <div class="flex gap-1 bg-gray-100 rounded-lg p-1">
                    <button v-for="v in ['day','week','month']" :key="v" @click="currentView = v"
                        class="px-3 py-1.5 text-sm rounded-md capitalize transition-colors"
                        :class="currentView === v ? 'bg-white shadow font-semibold text-gray-900' : 'text-gray-500 hover:text-gray-700'">
                        {{ v }}
                    </button>
                </div>

                <input type="date" :value="toDateStr(currentDate)"
                    @change="e => currentDate = new Date(e.target.value + 'T12:00:00')"
                    class="px-3 py-2 border border-gray-200 rounded-lg text-sm" />

                <select v-if="isLider" v-model="selectedAdvisor"
                    class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <option value="">All advisors</option>
                    <option v-for="a in advisors" :key="a.id" :value="a.id">
                        {{ a.first_name }} {{ a.last_name }} {{ (a.sponsorship_type === 'lider' || a.extra_areas?.includes('sponsorship')) ? '(L)' : '' }}
                    </option>
                </select>

                <button @click="openCreateModal"
                    class="ml-auto inline-flex items-center gap-1 px-3 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800">
                    + New Activity
                </button>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="text-center text-sm text-gray-400 py-4">Loading...</div>

            <!-- Month view -->
            <div v-if="currentView === 'month'" class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-100">
                    <div v-for="d in DAYS" :key="d" class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ d }}</div>
                </div>
                <div class="grid grid-cols-7">
                    <div v-for="(cell, i) in monthGrid" :key="i"
                        class="min-h-[100px] border-r border-b border-gray-100 p-1.5"
                        :class="{ 'bg-gray-50/50': cell.getMonth() !== currentDate.getMonth() }">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium"
                                :class="[
                                    isToday(cell) ? 'bg-black text-white rounded-full w-5 h-5 flex items-center justify-center' : 'text-gray-600',
                                    cell.getMonth() !== currentDate.getMonth() ? 'text-gray-300' : ''
                                ]">{{ cell.getDate() }}</span>
                        </div>
                        <div class="space-y-0.5">
                            <button v-for="ev in eventsOn(cell).slice(0, 3)" :key="ev.id"
                                @click="openEvent(ev)"
                                class="w-full text-left text-xs truncate px-1.5 py-0.5 rounded text-white"
                                :style="{ backgroundColor: activityTypes[ev.type]?.color }"
                                :class="[ev.status === 'completed' ? 'opacity-60 line-through' : '', ev.source === 'personal' ? 'ring-2 ring-white/50 ring-inset' : '']">
                                {{ formatTime(ev.start) }} {{ ev.title }}
                            </button>
                            <p v-if="eventsOn(cell).length > 3" class="text-xs text-gray-400 px-1">+{{ eventsOn(cell).length - 3 }} more</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Week view -->
            <div v-else-if="currentView === 'week'" class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="grid grid-cols-7">
                    <div v-for="i in 7" :key="i" class="border-r border-gray-100 min-h-[500px]">
                        <div class="px-3 py-2 border-b border-gray-100 bg-gray-50 text-center">
                            <p class="text-xs font-semibold text-gray-500 uppercase">{{ DAYS[i-1] }}</p>
                            <p class="text-sm font-medium mt-0.5" :class="isToday(new Date(weekRange.start.getTime() + (i-1)*86400000)) ? 'text-[#D4AF37]' : 'text-gray-700'">
                                {{ new Date(weekRange.start.getTime() + (i-1)*86400000).getDate() }}
                            </p>
                        </div>
                        <div class="p-2 space-y-1">
                            <button v-for="ev in eventsOn(new Date(weekRange.start.getTime() + (i-1)*86400000))" :key="ev.id"
                                @click="openEvent(ev)"
                                class="w-full text-left text-xs p-1.5 rounded text-white"
                                :style="{ backgroundColor: activityTypes[ev.type]?.color }"
                                :class="[ev.status === 'completed' ? 'opacity-60 line-through' : '', ev.source === 'personal' ? 'ring-2 ring-white/50 ring-inset' : '']">
                                <p class="font-semibold">{{ formatTime(ev.start) }}</p>
                                <p class="truncate">{{ ev.title }}</p>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Day view -->
            <div v-else class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="divide-y divide-gray-100">
                    <div v-for="h in dayHours" :key="h" class="flex">
                        <div class="w-16 px-3 py-3 text-xs text-gray-400 text-right border-r border-gray-100">{{ hourLabel(h) }}</div>
                        <div class="flex-1 min-h-[50px] p-2 space-y-1">
                            <button v-for="ev in eventsAtHour(currentDate, h)" :key="ev.id"
                                @click="openEvent(ev)"
                                class="w-full text-left text-sm p-2 rounded text-white flex items-start gap-2"
                                :style="{ backgroundColor: activityTypes[ev.type]?.color }"
                                :class="[ev.status === 'completed' ? 'opacity-60 line-through' : '', ev.source === 'personal' ? 'ring-2 ring-white/50 ring-inset' : '']">
                                <span class="text-xs font-semibold">{{ formatTime(ev.start) }}</span>
                                <span class="flex-1">{{ ev.title }}</span>
                                <span v-if="ev.lead_name" class="text-xs opacity-80">{{ ev.lead_name }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Event modal -->
        <Teleport to="body">
            <div v-if="showModal && selectedEvent" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <button @click="showModal = false" class="absolute top-3 right-3 p-1.5 rounded-lg hover:bg-gray-100 text-gray-400">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                    <div class="mb-3 flex items-center gap-2">
                        <span class="text-xs px-2 py-0.5 rounded text-white"
                            :style="{ backgroundColor: activityTypes[selectedEvent.type]?.color }">
                            {{ activityTypes[selectedEvent.type]?.label }}
                        </span>
                        <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-700 capitalize">{{ selectedEvent.status }}</span>
                        <span v-if="selectedEvent.is_contract" class="text-xs px-2 py-0.5 rounded bg-[#D4AF37] text-white">Contract</span>
                    </div>
                    <!-- View mode -->
                    <template v-if="!isEditing">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ selectedEvent.title }}</h3>
                        <div v-if="selectedEvent.description" class="sponsorship-email-preview text-sm text-gray-600 mb-3 break-words" v-html="selectedEvent.description"></div>
                        <div class="space-y-1 text-sm text-gray-600 mb-5">
                            <p>⏰ {{ new Date(selectedEvent.start).toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</p>
                            <p v-if="selectedEvent.lead_name">👤 {{ selectedEvent.lead_name }} <span v-if="selectedEvent.company">— {{ selectedEvent.company }}</span></p>
                            <p v-if="selectedEvent.advisor">→ {{ selectedEvent.advisor }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Link v-if="selectedEvent.lead_id" :href="`/admin/sponsorship/leads/${selectedEvent.lead_id}`"
                                class="flex-1 min-w-[120px] px-3 py-2 text-sm font-medium border border-gray-200 rounded-lg hover:bg-gray-50 flex items-center justify-center gap-1.5">
                                <EyeIcon class="w-4 h-4" /> View lead
                            </Link>
                            <span v-else class="flex-1 min-w-[120px] px-3 py-2 text-xs text-center text-gray-400 italic">Personal calendar entry</span>
                            <button v-if="selectedEvent.type === 'call' || selectedEvent.type === 'meeting'"
                                @click="startEdit"
                                class="flex-1 min-w-[120px] px-3 py-2 text-sm font-medium border border-gray-200 rounded-lg hover:bg-gray-50 flex items-center justify-center gap-1.5">
                                <PencilSquareIcon class="w-4 h-4" /> Edit
                            </button>
                            <button v-if="selectedEvent.status === 'pending'" @click="completeActivity(selectedEvent)"
                                class="flex-1 min-w-[120px] px-3 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 flex items-center justify-center gap-1.5">
                                <CheckCircleIcon class="w-4 h-4" /> Complete
                            </button>
                            <button v-if="selectedEvent.source === 'personal'" @click="deletePersonalActivity(selectedEvent)"
                                class="px-3 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50">
                                Delete
                            </button>
                        </div>
                    </template>

                    <!-- Edit mode (call / meeting) -->
                    <template v-else>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Edit {{ activityTypes[selectedEvent.type]?.label }}</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Title *</label>
                                <input v-model="editForm.title" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                                <p v-if="editForm.errors.title" class="text-xs text-red-500 mt-1">{{ editForm.errors.title }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                                <RichTextEditor v-model="editForm.description" placeholder="Add details..." min-height="100px" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Scheduled at</label>
                                <input v-model="editForm.scheduled_at" type="datetime-local" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            </div>
                            <div class="flex justify-end gap-2 pt-1">
                                <button @click="isEditing = false" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancel</button>
                                <button @click="saveEdit" :disabled="editForm.processing || !editForm.title"
                                    class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40">
                                    {{ editForm.processing ? 'Saving…' : 'Save' }}
                                </button>
                            </div>
                        </div>
                    </template>
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
                        <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600"><XMarkIcon class="w-5 h-5" /></button>
                    </div>
                    <p class="text-xs text-gray-500 mb-4">This activity is independent of any lead. Useful for blocking time on someone's calendar.</p>
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Type *</label>
                                <select v-model="createForm.type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                    <option value="call">Call</option>
                                    <option value="meeting">Meeting</option>
                                    <option value="note">Note</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Scheduled at</label>
                                <input v-model="createForm.scheduled_at" type="datetime-local" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            </div>
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
                                    {{ a.first_name }} {{ a.last_name }} {{ (a.sponsorship_type === 'lider' || a.extra_areas?.includes('sponsorship')) ? '(L)' : '' }}
                                </option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button @click="showCreateModal = false" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancel</button>
                            <button @click="submitCreate" :disabled="createForm.processing || !createForm.title"
                                class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40">
                                {{ createForm.processing ? 'Saving…' : 'Create' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<style>
.sponsorship-email-preview p { margin: 0 0 0.5em 0; }
.sponsorship-email-preview p:last-child { margin-bottom: 0; }
.sponsorship-email-preview ul, .sponsorship-email-preview ol { padding-left: 1.5em; margin: 0 0 0.5em 0; }
.sponsorship-email-preview ul { list-style-type: disc; }
.sponsorship-email-preview ol { list-style-type: decimal; }
.sponsorship-email-preview a { color: #D4AF37; text-decoration: underline; }
.sponsorship-email-preview strong { font-weight: 600; color: #111827; }
</style>
