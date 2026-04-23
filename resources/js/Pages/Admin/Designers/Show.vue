<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import QrCode from '@/Components/QrCode.vue';
import { ArrowLeftIcon, EnvelopeIcon, ChevronLeftIcon, ChevronRightIcon, XMarkIcon, ArrowRightIcon, TrashIcon, DevicePhoneMobileIcon, ArrowDownTrayIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    designer: Object,
    salesDocs: { type: Array, default: () => [] },
});

const docTypeLabel = (type) => ({
    contract:    'Contract',
    invoice:     'Invoice',
    id_document: 'ID',
    other:       'Other',
})[type] ?? type;

const profile    = computed(() => props.designer.designer_profile);
const events     = computed(() => props.designer.events     ?? []);
const shows      = computed(() => props.designer.shows      ?? []);
const fittings   = computed(() => props.designer.fittings   ?? []);
const assistants = computed(() => props.designer.assistants  ?? []);
const materials  = computed(() => props.designer.materials   ?? []);
const displays   = computed(() => props.designer.displays    ?? []);

// ── Tab seleccionado ─────────────────────────────────────────────
const firstActive = (props.designer.events ?? []).find(e => e.designer_status === 'confirmed') ?? (props.designer.events ?? [])[0];
const selectedEventId = ref(firstActive?.id ?? null);
const selectedEvent   = computed(() => events.value.find(e => e.id === selectedEventId.value) ?? null);

// ── Tab bar scroll ────────────────────────────────────────────────
const tabScroll = ref(null);
function scrollTabs(dir) {
    if (tabScroll.value) tabScroll.value.scrollBy({ left: dir * 200, behavior: 'smooth' });
}

// ── Datos filtrados por evento ───────────────────────────────────
const tabShows = computed(() =>
    shows.value.filter(s => s.event_day?.event_id === selectedEventId.value)
);
const tabFitting = computed(() =>
    fittings.value.find(f => f.event_id === selectedEventId.value) ?? null
);
const tabMaterials = computed(() =>
    materials.value.filter(m => m.event_id === selectedEventId.value)
);
const tabDisplays = computed(() =>
    displays.value.filter(d => d.event_id === selectedEventId.value)
);
const tabAssistants = computed(() =>
    assistants.value.filter(a => a.event_id === selectedEventId.value)
);
const tabProgress = computed(() => {
    if (!tabMaterials.value.length) return 0;
    const done = tabMaterials.value.filter(m => m.status === 'confirmed' || m.status === 'submitted').length;
    return Math.round((done / tabMaterials.value.length) * 100);
});

// ── Helpers ──────────────────────────────────────────────────────
function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}

function removeFromEvent(eventId, eventName) {
    if (!confirm(`Remove ${props.designer.first_name} from event "${eventName}"?`)) return;
    router.delete(`/admin/operations/designers/${props.designer.id}/remove-event/${eventId}`, { preserveScroll: true });
}

function toggleFeature(eventId, field) {
    router.post(`/admin/operations/designers/${props.designer.id}/events/${eventId}/toggle-feature`, { field }, { preserveScroll: true });
}

// Onboarding email
const sendingOnboarding = ref(false);
function sendOnboardingEmail() {
    if (!confirm(`Send onboarding email to ${props.designer.first_name} ${props.designer.last_name}?`)) return;
    sendingOnboarding.value = true;
    router.post(`/admin/operations/designers/${props.designer.id}/send-onboarding`, {}, {
        preserveScroll: true,
        onFinish: () => sendingOnboarding.value = false,
    });
}

// Status badge
function statusBadgeClass() {
    return { active: 'bg-green-50 text-green-700 border-green-200', pending: 'bg-yellow-50 text-yellow-700 border-yellow-200', inactive: 'bg-red-50 text-red-600 border-red-200', registered: 'bg-blue-50 text-blue-700 border-blue-200' }[props.designer.status] ?? 'bg-gray-50 text-gray-600 border-gray-200';
}
function statusBadgeLabel() {
    return { active: 'Active', pending: 'Pending', inactive: 'Inactive', registered: 'Registered' }[props.designer.status] ?? props.designer.status;
}

// Onboarding SMS
const sendingSms = ref(false);
function sendOnboardingSms() {
    if (!props.designer.phone) return alert('This designer has no registered phone number.');
    if (!confirm(`Send onboarding SMS to ${props.designer.first_name} ${props.designer.last_name}?`)) return;
    sendingSms.value = true;
    router.post(`/admin/operations/designers/${props.designer.id}/send-onboarding-sms`, {}, {
        preserveScroll: true,
        onFinish: () => sendingSms.value = false,
    });
}

const previewDoc = ref(null);

// Modal pase
const passModal = ref(null);
function openPassModal(evt) { passModal.value = { ...evt.pass, event_name: evt.name }; }

// Modal modelos
const modelsModal = ref(null);
function closePassModal()   { passModal.value = null; }

function progressColor(pct) {
    if (pct === 100) return 'bg-green-500';
    if (pct >= 50)   return 'bg-yellow-400';
    return 'bg-red-300';
}

function materialStatusClass(status) {
    return {
        pending:   'text-yellow-700 bg-yellow-50',
        submitted: 'text-blue-700 bg-blue-50',
        confirmed: 'text-green-700 bg-green-50',
        rejected:  'text-red-600 bg-red-50',
    }[status] ?? 'text-gray-600 bg-gray-50';
}
function materialStatusLabel(status) {
    return { pending: 'Pending', submitted: 'Submitted', confirmed: 'Confirmed', rejected: 'Rejected' }[status] ?? status;
}

function displayStatusClass(status) {
    return {
        pending:   'text-yellow-700 bg-yellow-50',
        ready:     'text-blue-700 bg-blue-50',
        confirmed: 'text-green-700 bg-green-50',
    }[status] ?? 'text-gray-600 bg-gray-50';
}
function displayStatusLabel(status) {
    return { pending: 'Pending', ready: 'Ready', confirmed: 'Confirmed' }[status] ?? status;
}

function showStatusClass(s) {
    return { confirmed: 'text-green-700 bg-green-50', cancelled: 'text-red-600 bg-red-50' }[s] ?? 'text-gray-600 bg-gray-50';
}
function showStatusLabel(s) {
    return { confirmed: 'Confirmed', cancelled: 'Cancelled' }[s] ?? s;
}

function passStatusClass(s) {
    return {
        active:    'bg-green-50 text-green-700',
        cancelled: 'bg-red-50 text-red-600',
        used:      'bg-gray-100 text-gray-500',
    }[s] ?? 'bg-gray-100 text-gray-500';
}
function passStatusLabel(s) {
    return { active: 'Active', cancelled: 'Cancelled', used: 'Used' }[s] ?? s;
}

function designerStatusClass(s) {
    return { confirmed: 'text-green-700', cancelled: 'text-red-500' }[s] ?? 'text-gray-500';
}

// Social media links
const socialLinks = computed(() => {
    const p = profile.value;
    const sm = p?.social_media ?? {};
    const links = [];
    const ig = p?.instagram || sm.instagram;
    const web = p?.website || sm.website;
    if (ig) links.push({ label: ig, url: `https://instagram.com/${ig.replace('@', '')}`, color: 'text-pink-600 hover:text-pink-700' });
    if (sm.facebook)  links.push({ label: 'Facebook', url: sm.facebook.startsWith('http') ? sm.facebook : `https://facebook.com/${sm.facebook}`, color: 'text-blue-600 hover:text-blue-700' });
    if (sm.tiktok)    links.push({ label: sm.tiktok, url: `https://tiktok.com/${sm.tiktok.replace('@', '')}`, color: 'text-gray-800 hover:text-black' });
    if (web)   links.push({ label: 'Website', url: web.startsWith('http') ? web : `https://${web}`, color: 'text-indigo-600 hover:text-indigo-700' });
    return links;
});
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/operations/designers" class="text-gray-400 hover:text-gray-600 text-sm flex items-center gap-1">
                    <ArrowLeftIcon class="w-4 h-4" /> Designers
                </Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">{{ designer.first_name }} {{ designer.last_name }}</h2>
            </div>
        </template>

        <div class="max-w-5xl mx-auto space-y-4">

            <!-- Hero card -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex gap-6">
                    <div class="flex-shrink-0">
                        <div class="w-24 h-24 rounded-full overflow-hidden bg-gray-100">
                            <img v-if="storageUrl(designer.profile_picture)"
                                :src="storageUrl(designer.profile_picture)"
                                class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center text-2xl font-bold text-gray-400">
                                {{ designer.first_name?.[0] }}{{ designer.last_name?.[0] }}
                            </div>
                        </div>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">{{ designer.first_name }} {{ designer.last_name }}</h3>
                                <p class="text-gray-500 text-sm">
                                    {{ profile?.brand_name }}
                                    <span v-if="profile?.country"> · {{ profile.country }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span :class="statusBadgeClass()"
                                    class="px-2.5 py-1 rounded-full text-xs font-medium border">
                                    {{ statusBadgeLabel() }}
                                </span>
                                <Link :href="`/admin/operations/designers/${designer.id}/edit`"
                                    class="px-4 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors">
                                    Edit
                                </Link>
                            </div>
                        </div>

                        <!-- Documentos de Sales -->
                        <div v-if="salesDocs.length" class="mt-3 flex flex-wrap gap-2">
                            <button v-for="doc in salesDocs" :key="doc.id"
                                @click="previewDoc = doc"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-700 hover:bg-gray-100 hover:border-gray-300 transition-colors cursor-pointer">
                                <DocumentTextIcon class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" />
                                <span class="font-medium text-gray-500">{{ docTypeLabel(doc.type) }}:</span>
                                <span class="truncate max-w-[140px]">{{ doc.original_name }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-3 text-sm">
                            <span class="text-gray-600 flex items-center gap-1">
                                <EnvelopeIcon class="w-4 h-4 text-gray-400" />
                                {{ designer.email }}
                            </span>
                            <span v-if="designer.phone" class="text-gray-600">Phone: {{ designer.phone }}</span>
                            <a v-for="link in socialLinks" :key="link.label"
                                :href="link.url" target="_blank" :class="link.color" class="text-sm">
                                {{ link.label }}
                            </a>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <span v-if="profile?.category"
                                class="text-xs bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full font-medium">
                                {{ profile.category.name }}
                            </span>
                            <span v-if="profile?.skype"
                                class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full">
                                Skype: {{ profile.skype }}
                            </span>
                            <span v-if="profile?.tracking_link"
                                class="text-xs bg-purple-50 text-purple-700 px-2 py-0.5 rounded-full">
                                Tracking
                            </span>
                            <span v-if="profile?.sales_rep"
                                class="text-xs bg-green-50 text-green-700 border border-green-200 px-2 py-0.5 rounded-full font-medium">
                                Rep: {{ profile.sales_rep.first_name }} {{ profile.sales_rep.last_name }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bio (global) -->
            <div v-if="profile?.bio" class="bg-white rounded-2xl border border-gray-200 p-6">
                <h4 class="font-bold text-gray-900 mb-3">Bio</h4>
                <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">{{ profile.bio }}</p>
            </div>

            <!-- Tab bar de eventos -->
            <div v-if="events.length > 0" class="bg-white rounded-2xl border border-gray-200 flex items-center">
                <!-- Flecha izquierda -->
                <button v-if="events.length > 3"
                    @click="scrollTabs(-1)"
                    class="flex-shrink-0 px-2 h-full text-gray-400 hover:text-gray-700 hover:bg-gray-50 rounded-l-2xl transition-colors py-3 border-r border-gray-100">
                    <ChevronLeftIcon class="w-4 h-4" />
                </button>

                <!-- Tabs con fade -->
                <div class="relative flex-1 overflow-hidden">
                    <!-- Fade derecho -->
                    <div class="pointer-events-none absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-white to-transparent z-10 rounded-r-xl"></div>
                    <!-- Scroll container -->
                    <div ref="tabScroll"
                        class="flex gap-1 p-1.5 overflow-x-auto tab-scroll-hide">
                        <button v-for="evt in events" :key="evt.id"
                            @click="selectedEventId = evt.id"
                            :class="[
                                'flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium transition-colors whitespace-nowrap flex-shrink-0',
                                selectedEventId === evt.id
                                    ? 'bg-black text-white'
                                    : 'text-gray-600 border border-gray-200 bg-gray-100 hover:bg-gray-300',
                            ]">
                            <span :class="evt.designer_status === 'cancelled' ? 'line-through opacity-60' : ''">
                                {{ evt.name }}
                            </span>
                            <span v-if="evt.designer_status === 'cancelled'"
                                class="text-[10px] font-normal opacity-70">(Cancelled)</span>
                        </button>
                    </div>
                </div>

                <!-- Flecha derecha -->
                <button v-if="events.length > 3"
                    @click="scrollTabs(1)"
                    class="flex-shrink-0 px-2 h-full text-gray-400 hover:text-gray-700 hover:bg-gray-50 rounded-r-2xl transition-colors py-3 border-l border-gray-100">
                    <ChevronRightIcon class="w-4 h-4" />
                </button>
            </div>
            <div v-else class="bg-white rounded-2xl border border-gray-200 p-6 text-center text-sm text-gray-400 italic">
                No events assigned.
            </div>

            <!-- Contenido del evento seleccionado -->
            <div v-if="selectedEvent" class="grid grid-cols-3 gap-4">

                <!-- Columna izquierda (2 cols) -->
                <div class="col-span-2 space-y-4">

                    <!-- Shows del evento -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="font-bold text-gray-900">Shows</h4>
                            <!-- Fitting badge inline -->
                            <div v-if="tabFitting" class="flex items-center gap-1.5 bg-orange-50 border border-orange-200 rounded-lg px-2.5 py-1">
                                <span class="text-[10px] font-semibold text-orange-600 uppercase tracking-wide">Fitting</span>
                                <span class="text-xs font-medium text-orange-700">{{ tabFitting.day_label }} · {{ tabFitting.time }}</span>
                            </div>
                        </div>
                        <div v-if="tabShows.length === 0" class="text-sm text-gray-400 italic">No shows assigned to this event.</div>
                        <div class="space-y-2">
                            <div v-for="s in tabShows" :key="s.id"
                                class="flex items-center gap-3 bg-gray-50 rounded-lg px-3 py-2.5 text-sm">
                                <span class="text-gray-500 text-xs">{{ s.event_day?.label }} · {{ s.event_day?.date }}</span>
                                <span class="font-medium text-gray-900">{{ s.name }}</span>
                                <span v-if="s.collection_name" class="text-gray-400 text-xs">{{ s.collection_name }}</span>
                                <span :class="showStatusClass(s.status)"
                                    class="ml-auto px-2 py-0.5 rounded text-xs font-medium">
                                    {{ showStatusLabel(s.status) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Materials -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="font-bold text-gray-900">Materials</h4>
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-28 h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div :class="progressColor(tabProgress)"
                                            class="h-full rounded-full transition-all"
                                            :style="`width: ${tabProgress}%`"></div>
                                    </div>
                                    <span class="text-sm font-medium text-gray-600">{{ tabProgress }}%</span>
                                </div>
                                <Link :href="`/admin/operations/designers/${designer.id}/materials/${selectedEventId}`"
                                    class="px-3 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800">
                                    Manage Materials
                                </Link>
                            </div>
                        </div>
                        <div v-if="tabMaterials.length" class="space-y-1.5">
                            <div v-for="m in tabMaterials" :key="m.id"
                                class="flex items-center gap-3 text-sm px-3 py-2 bg-gray-50 rounded-lg">
                                <span class="text-gray-700 font-medium flex-1">{{ m.name }}</span>
                                <a v-if="m.drive_folder_url" :href="m.drive_folder_url" target="_blank"
                                    class="text-xs text-blue-600 hover:text-blue-700 underline">Drive</a>
                                <span :class="materialStatusClass(m.status)"
                                    class="px-2 py-0.5 rounded text-xs font-medium">
                                    {{ materialStatusLabel(m.status) }}
                                </span>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-400 italic">No materials.</p>
                    </div>

                    <!-- Display del evento -->
                    <div v-if="tabDisplays.length" class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="font-bold text-gray-900 mb-4">Display</h4>
                        <div v-for="d in tabDisplays" :key="d.id" class="space-y-3">
                            <div class="flex items-center gap-3 text-sm">
                                <span class="text-gray-500 w-12">Video:</span>
                                <span v-if="d.background_video_url" class="text-blue-600 text-xs truncate max-w-xs">{{ d.background_video_url }}</span>
                                <span v-else class="text-gray-400 text-xs italic">No video</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <span class="text-gray-500 w-12">Audio:</span>
                                <span v-if="d.music_audio_url" class="text-blue-600 text-xs truncate max-w-xs">{{ d.music_audio_url }}</span>
                                <span v-else class="text-gray-400 text-xs italic">No audio</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <span class="text-gray-500 w-12">Status:</span>
                                <span :class="displayStatusClass(d.status)" class="px-2 py-0.5 rounded text-xs font-medium">
                                    {{ displayStatusLabel(d.status) }}
                                </span>
                            </div>
                            <p v-if="d.notes" class="text-xs text-gray-500">{{ d.notes }}</p>
                        </div>
                    </div>

                </div>

                <!-- Columna derecha (1 col) -->
                <div class="space-y-4">

                    <!-- Info del evento -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <div class="flex items-start justify-between mb-3">
                            <Link :href="`/admin/operations/events/${selectedEvent.id}`"
                                class="text-sm font-bold text-gray-900 hover:underline leading-snug">
                                {{ selectedEvent.name }}
                            </Link>
                            <button @click="removeFromEvent(selectedEvent.id, selectedEvent.name)"
                                class="text-red-400 hover:text-red-600 ml-2 flex-shrink-0">
                                <XMarkIcon class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="space-y-1">
                            <p class="text-xs">
                                <span :class="designerStatusClass(selectedEvent.designer_status)" class="font-medium capitalize">
                                    {{ selectedEvent.designer_status === 'confirmed' ? 'Confirmed' : 'Cancelled' }}
                                </span>
                            </p>
                            <p v-if="selectedEvent.package_id" class="text-xs text-gray-500">
                                Package: <span class="font-medium text-gray-700">${{ Number(selectedEvent.package_price).toLocaleString() }}</span>
                            </p>
                            <p class="text-xs text-gray-500">
                                Looks: <span class="font-medium text-gray-700">{{ selectedEvent.looks }}</span>
                            </p>
                            <p class="text-xs text-gray-500 flex items-center gap-1">
                                Models: <span class="font-medium text-gray-700">{{ selectedEvent.models_count ?? 0 }}</span>
                                <button v-if="(selectedEvent.models_count ?? 0) > 0"
                                    @click="modelsModal = selectedEvent"
                                    class="text-indigo-500 hover:text-indigo-700 font-medium ml-1">
                                    View
                                </button>
                            </p>
                            <div class="flex flex-wrap gap-1.5 mt-1">
                                <button v-for="feat in [
                                    { field: 'model_casting_enabled', label: 'Model Casting', value: selectedEvent.model_casting_enabled },
                                    { field: 'media_package', label: 'Media Package', value: selectedEvent.media_package },
                                    { field: 'custom_background', label: 'Custom BG', value: selectedEvent.custom_background },
                                    { field: 'courtesy_tickets', label: 'Courtesy Tickets', value: selectedEvent.courtesy_tickets },
                                ]" :key="feat.field"
                                    @click="toggleFeature(selectedEvent.id, feat.field)"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium cursor-pointer transition-colors"
                                    :class="feat.value ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-400 hover:bg-gray-200'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="feat.value ? 'bg-green-500' : 'bg-gray-300'"></span>
                                    {{ feat.label }}
                                </button>
                            </div>
                            <p v-if="tabFitting" class="text-xs text-gray-500">
                                Fitting: <span class="font-medium text-orange-600">{{ tabFitting.day_label }} · {{ tabFitting.time }}</span>
                            </p>
                            <p v-if="selectedEvent.notes" class="text-xs text-gray-400 pt-1">{{ selectedEvent.notes }}</p>
                        </div>

                        <!-- Pase inline -->
                        <div v-if="selectedEvent.pass" class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-100">
                            <span class="font-mono text-[11px] text-gray-400 tracking-wide">{{ selectedEvent.pass.qr_code }}</span>
                            <span :class="passStatusClass(selectedEvent.pass.status)"
                                class="text-[10px] font-medium px-1.5 py-0.5 rounded">
                                {{ passStatusLabel(selectedEvent.pass.status) }}
                            </span>
                            <button @click="openPassModal(selectedEvent)"
                                class="text-[11px] text-indigo-500 hover:text-indigo-700 font-medium ml-auto flex items-center gap-0.5">
                                View QR <ArrowRightIcon class="w-3 h-3" />
                            </button>
                        </div>
                    </div>

                    <!-- Asistentes del evento -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="font-bold text-gray-900">Assistants</h4>
                            <span v-if="selectedEvent?.assistants != null"
                                :class="tabAssistants.length >= selectedEvent.assistants ? 'bg-red-50 text-red-600' : 'bg-gray-50 text-gray-500'"
                                class="text-xs font-medium px-2 py-0.5 rounded-full">
                                {{ tabAssistants.length }} / {{ selectedEvent.assistants }}
                            </span>
                        </div>
                        <div v-if="tabAssistants.length === 0" class="text-sm text-gray-400 italic">No assistants.</div>
                        <div v-for="a in tabAssistants" :key="a.id"
                            class="flex items-center justify-between py-1.5 border-b border-gray-50 last:border-0">
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ a.full_name }}</p>
                                <p v-if="a.phone || a.email" class="text-xs text-gray-400">
                                    {{ a.phone }}{{ a.phone && a.email ? ' · ' : '' }}{{ a.email }}
                                </p>
                            </div>
                            <span class="text-xs px-1.5 py-0.5 rounded"
                                :class="a.status === 'checked_in' ? 'bg-green-50 text-green-700' : 'bg-gray-50 text-gray-500'">
                                {{ a.status === 'checked_in' ? 'Check-in' : 'Registered' }}
                            </span>
                        </div>
                    </div>

                    <!-- Redes y enlaces (global) -->
                    <div v-if="socialLinks.length || profile?.skype || profile?.tracking_link"
                        class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h4 class="font-bold text-gray-900 mb-3">Social & Links</h4>
                        <div class="space-y-2 text-sm">
                            <a v-for="link in socialLinks" :key="link.label"
                                :href="link.url" target="_blank"
                                :class="link.color" class="block hover:underline">
                                {{ link.label }}
                            </a>
                            <p v-if="profile?.skype" class="text-gray-600">Skype: {{ profile.skype }}</p>
                            <a v-if="profile?.tracking_link" :href="profile.tracking_link" target="_blank"
                                class="block text-purple-600 hover:text-purple-700 hover:underline">
                                Tracking Link
                            </a>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </AdminLayout>

    <!-- Modal: Ver Pase QR -->
    <Teleport to="body">
        <div v-if="passModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/60" @click="closePassModal"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6 flex flex-col items-center gap-4">
                <button @click="closePassModal" class="absolute top-4 right-4 p-1 text-gray-400 hover:text-gray-600">
                    <XMarkIcon class="h-5 w-5" />
                </button>
                <p class="text-xs font-medium text-gray-400 text-center">{{ passModal.event_name }}</p>
                <div class="p-3 bg-white border-2 border-gray-100 rounded-xl">
                    <QrCode :value="passModal.qr_code" :size="220" />
                </div>
                <p class="font-mono text-sm text-gray-400">{{ passModal.qr_code }}</p>
                <span :class="passStatusClass(passModal.status)"
                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium">
                    {{ passStatusLabel(passModal.status) }}
                </span>
                <div class="text-center">
                    <p v-if="passModal.valid_days_labels" class="text-xs text-gray-500 font-medium">Valid days</p>
                    <p v-if="passModal.valid_days_labels" class="text-xs text-gray-400 mt-0.5">{{ passModal.valid_days_labels }}</p>
                    <p v-else class="text-xs text-gray-400">Valid all days</p>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal: Previsualización de documento -->
    <Teleport to="body">
        <div v-if="previewDoc" class="fixed inset-0 z-50 flex items-center justify-center p-6">
            <div class="absolute inset-0 bg-black/60" @click="previewDoc = null"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl h-[85vh] flex flex-col overflow-hidden z-10">
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                    <div class="flex items-center gap-2 min-w-0">
                        <DocumentTextIcon class="w-5 h-5 text-gray-400 flex-shrink-0" />
                        <span class="text-sm font-medium text-gray-500">{{ docTypeLabel(previewDoc.type) }}:</span>
                        <span class="text-sm text-gray-800 truncate">{{ previewDoc.original_name }}</span>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <a :href="previewDoc.url" download
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors">
                            <ArrowDownTrayIcon class="w-3.5 h-3.5" />
                            Download
                        </a>
                        <button @click="previewDoc = null" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                </div>
                <!-- PDF Viewer -->
                <div class="flex-1 bg-gray-100">
                    <iframe :src="previewDoc.url" class="w-full h-full border-0"></iframe>
                </div>
            </div>
        </div>
    </Teleport>


    <!-- Modal: Modelos asignadas -->
    <Teleport to="body">
        <div v-if="modelsModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/60" @click="modelsModal = null"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6 max-h-[80vh] flex flex-col">
                <button @click="modelsModal = null" class="absolute top-4 right-4 p-1 text-gray-400 hover:text-gray-600">
                    <XMarkIcon class="h-5 w-5" />
                </button>
                <h3 class="text-lg font-bold text-gray-900 mb-1">Assigned Models</h3>
                <p class="text-sm text-gray-500 mb-4">{{ modelsModal.name }}</p>

                <div class="overflow-y-auto space-y-2">
                    <div v-if="!modelsModal.assigned_models?.length" class="text-sm text-gray-400 italic text-center py-4">
                        No models assigned.
                    </div>
                    <Link v-for="m in modelsModal.assigned_models" :key="m.id"
                        :href="`/admin/models/${m.id}`"
                        class="flex items-center gap-3 bg-gray-50 hover:bg-gray-100 rounded-xl px-3 py-2.5 transition-colors">
                        <div class="w-9 h-9 rounded-full overflow-hidden bg-gray-200 flex-shrink-0">
                            <img v-if="m.profile_picture" :src="m.profile_picture.startsWith('http') ? m.profile_picture : `/storage/${m.profile_picture}`"
                                class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center text-xs font-bold text-gray-400">
                                {{ m.first_name?.[0] }}{{ m.last_name?.[0] }}
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ m.first_name }} {{ m.last_name }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ m.email }}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span v-if="m.participation_number"
                                class="text-xs font-bold bg-black text-white px-2 py-0.5 rounded-full">
                                #{{ m.participation_number }}
                            </span>
                            <span class="text-xs px-1.5 py-0.5 rounded"
                                :class="m.status === 'confirmed' ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700'">
                                {{ m.status === 'confirmed' ? 'Confirmed' : m.status }}
                            </span>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.tab-scroll-hide { scrollbar-width: none; -ms-overflow-style: none; }
.tab-scroll-hide::-webkit-scrollbar { display: none; }
</style>
