<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { XMarkIcon, PlusIcon, CalendarIcon, ClockIcon, MapPinIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    volunteer: Object,
    events: Array,
    countries: Array,
});

const profile = props.volunteer.volunteer_profile;
const activeTab = ref(1);

function parsePhone(full) {
    if (!full || !full.startsWith('+')) return { code: '+1', number: full ?? '' };
    const match = props.countries.find(c => full.startsWith(c.phone));
    if (match) return { code: match.phone, number: full.slice(match.phone.length) };
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
    form.put(`/admin/operations/volunteers/${props.volunteer.id}`);
}

// Event management
const assignedEvents = computed(() => props.volunteer.events_as_volunteer ?? []);
const schedules = computed(() => props.volunteer.volunteer_schedules ?? []);

const assignForm = useForm({ event_id: '', area: '' });
function submitAssignEvent() {
    assignForm.post(`/admin/operations/volunteers/${props.volunteer.id}/assign-event`, { preserveScroll: true });
}

function updateEventArea(eventId, area) {
    router.patch(`/admin/operations/volunteers/${props.volunteer.id}/events/${eventId}/area`, { area }, { preserveScroll: true });
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
    if (!confirm(`Remove from event "${eventName}"?`)) return;
    router.delete(`/admin/operations/volunteers/${props.volunteer.id}/remove-event/${eventId}`, { preserveScroll: true });
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
    scheduleForm.post(`/admin/operations/volunteers/${props.volunteer.id}/schedules`, {
        preserveScroll: true,
        onSuccess: () => { showScheduleModal.value = false; scheduleForm.reset(); selectedEventDays.value = []; },
    });
}

function removeSchedule(scheduleId) {
    if (!confirm('Delete this schedule?')) return;
    router.delete(`/admin/operations/volunteers/${props.volunteer.id}/schedules/${scheduleId}`, { preserveScroll: true });
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
                <Link :href="`/admin/operations/volunteers/${volunteer.id}`" class="text-gray-400 hover:text-gray-600 text-sm">&larr; {{ volunteer.first_name }} {{ volunteer.last_name }}</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Edit</h2>
            </div>
        </template>

        <div class="max-w-3xl mx-auto">
            <!-- Tabs -->
            <div class="flex gap-1 bg-gray-100 rounded-xl p-1 mb-6">
                <button v-for="tab in [
                    { n: 1, label: 'Personal Data' },
                    { n: 2, label: 'Volunteer Details' },
                    { n: 3, label: 'Events and Schedules' },
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                            <input v-model="form.first_name" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.first_name" class="mt-1 text-red-500 text-xs">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <div class="flex gap-2">
                                <select v-model="phoneCode"
                                    class="w-28 border border-gray-300 rounded-lg px-2 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                    <option v-for="c in countries" :key="c.phone" :value="c.phone">{{ c.flag }} {{ c.phone }}</option>
                                </select>
                                <input v-model="phoneNumber" type="tel" placeholder="3055550404"
                                    class="flex-1 border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <p v-if="form.errors.phone" class="mt-1 text-red-500 text-xs">{{ form.errors.phone }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Age</label>
                            <select v-model="form.age"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Select —</option>
                                <option v-for="a in ageOptions" :key="a" :value="a">{{ a }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                            <select v-model="form.gender"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="female">Female</option>
                                <option value="male">Male</option>
                                <option value="non_binary">Non-binary</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Instagram</label>
                            <input v-model="form.instagram" type="text" placeholder="@username"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                        <select v-model="form.location"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">— Select —</option>
                            <option v-for="st in usStates" :key="st" :value="st">{{ st }}</option>
                        </select>
                    </div>
                </div>

                <!-- Tab 2: Detalles Voluntario -->
                <div v-show="activeTab === 2" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="font-semibold text-gray-800">Details</h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">T-shirt Size</label>
                            <select v-model="form.tshirt_size"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Select —</option>
                                <option value="XS">XS</option><option value="S">S</option><option value="M">M</option>
                                <option value="L">L</option><option value="XL">XL</option><option value="XXL">XXL</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Experience</label>
                            <select v-model="form.experience"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Select —</option>
                                <option value="none">No experience</option>
                                <option value="some">Some experience</option>
                                <option value="experienced">Experienced</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Work style</label>
                            <select v-model="form.comfortable_fast_paced"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Select —</option>
                                <option value="multitask">Multitask / Dynamic</option>
                                <option value="structured">Structured</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Availability</label>
                            <select v-model="form.full_availability"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Select —</option>
                                <option value="yes">Full</option>
                                <option value="no">Not available</option>
                                <option value="partially">Partial</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contribution</label>
                        <textarea v-model="form.contribution" rows="3"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Resume Link</label>
                        <input v-model="form.resume_link" type="url" placeholder="https://..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Internal Notes</label>
                        <textarea v-model="form.notes" rows="3" placeholder="Notes visible only to admin/operation..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                    </div>
                </div>

                <!-- Tab 3: Eventos y Horarios -->
                <div v-show="activeTab === 3" class="space-y-5">
                    <!-- Asignar nuevo evento -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h3 class="font-semibold text-gray-800 mb-3">Assign Event</h3>
                        <div class="flex gap-2">
                            <select v-model="assignForm.event_id"
                                class="flex-1 border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                                <option value="">Select event...</option>
                                <option v-for="ev in events" :key="ev.id" :value="ev.id">
                                    {{ ev.name }} — {{ { draft: 'Draft', published: 'Published', active: 'Active', completed: 'Completed' }[ev.status] ?? ev.status }}
                                </option>
                            </select>
                            <input v-model="assignForm.area" type="text" placeholder="Area"
                                class="w-36 border border-gray-300 rounded-lg px-3 py-2.5 text-sm" />
                            <button type="button" @click="submitAssignEvent" :disabled="!assignForm.event_id"
                                class="px-4 py-2.5 bg-black text-white rounded-lg text-sm font-medium disabled:opacity-40 flex items-center gap-1 cursor-pointer">
                                <PlusIcon class="w-4 h-4" /> Assign
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
                                <p class="text-xs text-gray-400 mt-0.5">Status: {{ ev.pivot?.status || 'assigned' }}</p>
                            </div>
                            <button type="button" @click="removeEvent(ev.id, ev.name)"
                                class="text-red-400 hover:text-red-600 cursor-pointer p-1">
                                <XMarkIcon class="w-4 h-4" />
                            </button>
                        </div>

                        <!-- Área -->
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Area</label>
                            <input type="text" :value="ev.pivot?.area || ''" @input="onAreaInput(ev.id, $event.target.value)"
                                placeholder="e.g. Backstage, Front of House..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>

                        <!-- Horarios de este evento -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-xs font-medium text-gray-500">Schedules</label>
                                <button type="button" @click="scheduleForm.event_id = ev.id; onScheduleEventChange(); showScheduleModal = true"
                                    class="flex items-center gap-1 px-2 py-1 bg-gray-100 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-200 cursor-pointer">
                                    <PlusIcon class="w-3 h-3" /> Add
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
                            <p v-else class="text-xs text-gray-400 italic">No schedules assigned.</p>
                        </div>
                    </div>

                    <p v-if="!assignedEvents.length" class="bg-white rounded-2xl border border-gray-200 p-6 text-sm text-gray-400 italic text-center">
                        No events assigned.
                    </p>
                </div>

                <!-- Botones -->
                <div class="flex justify-between">
                    <Link :href="`/admin/operations/volunteers/${volunteer.id}`"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        Cancel
                    </Link>
                    <div class="flex gap-3">
                        <button v-if="activeTab > 1" type="button" @click="activeTab--"
                            class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                            &larr; Previous
                        </button>
                        <button v-if="activeTab < 3" type="button" @click="activeTab++"
                            class="px-6 py-2.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-black transition-colors">
                            Next &rarr;
                        </button>
                        <button v-else type="submit" :disabled="form.processing"
                            class="px-8 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-60 transition-colors">
                            <span v-if="form.processing">Saving...</span>
                            <span v-else>Save Changes</span>
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
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Add Schedule</h3>
                    <form @submit.prevent="submitSchedule" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Day</label>
                            <select v-model="scheduleForm.event_day_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                                <option value="">Select...</option>
                                <option v-for="day in selectedEventDays" :key="day.id" :value="day.id">
                                    {{ formatDayDate(day.date) }} — {{ day.label }}
                                </option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                                <input v-model="scheduleForm.start_time" type="time" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                                <input v-model="scheduleForm.end_time" type="time" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm" />
                            </div>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button @click="showScheduleModal = false" type="button" class="px-4 py-2 border border-gray-200 rounded-lg text-sm">Cancel</button>
                            <button type="submit" :disabled="scheduleForm.processing || !scheduleForm.event_day_id || !scheduleForm.start_time || !scheduleForm.end_time"
                                class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium disabled:opacity-40">Add</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
