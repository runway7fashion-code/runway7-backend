<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import EmailComposer from '@/Components/EmailComposer.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { initEcho } from '@/echo.js';
import {
    ArrowLeftIcon, EnvelopeIcon, PhoneIcon, GlobeAltIcon, LinkIcon,
    PencilSquareIcon, CheckCircleIcon, ClockIcon, UserIcon, PlusIcon,
    ChatBubbleLeftIcon, CalendarDaysIcon, PhoneArrowUpRightIcon,
    DocumentTextIcon, StarIcon, ArrowDownTrayIcon, XMarkIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';

const props = defineProps({
    lead: Object,
    statuses: Object,
    activityTypes: Object,
    tags: Array,
    events: Array,
    advisors: Array,
    isLider: Boolean,
});

// ───────────── Helpers ─────────────
function formatDate(date) {
    if (!date) return '—';
    const d = new Date(date);
    if (isNaN(d)) return date;
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}
function formatDateTime(date) {
    if (!date) return '—';
    const d = new Date(date);
    if (isNaN(d)) return date;
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}
// ──────── Datetime-local helpers (TZ-safe) ────────
// El <input type="datetime-local"> trabaja en hora LOCAL del navegador. El backend
// guarda en UTC. Estos helpers hacen el round-trip: UTC ISO → "YYYY-MM-DDTHH:mm" local
// para precargar, y "YYYY-MM-DDTHH:mm" local → UTC ISO para enviar.
function toLocalDatetimeInput(utcStr) {
    if (!utcStr) return '';
    const d = new Date(utcStr);
    if (isNaN(d)) return '';
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
function localDatetimeToUtcIso(localStr) {
    if (!localStr) return null;
    const d = new Date(localStr); // se interpreta como hora local
    if (isNaN(d)) return null;
    return d.toISOString();
}

function formatNoteDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit' }) + ', ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
}
function relativeTime(date) {
    if (!date) return '';
    const now = new Date();
    const d = new Date(date);
    const diffMins = Math.floor((now - d) / 60000);
    if (diffMins < 1) return 'just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return `${diffHours}h ago`;
    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 30) return `${diffDays}d ago`;
    return formatDate(date);
}
function statusLabel(key) { return props.statuses?.[key]?.label || key; }
function statusBadgeStyle(key) {
    const s = props.statuses?.[key];
    if (!s) return 'bg-gray-100 text-gray-600';
    // Match bg based on the hex color converted to a soft bg
    return '';
}
function activityTypeLabel(key) { return props.activityTypes?.[key]?.label || key; }
function activityTypeBg(key) {
    const color = props.activityTypes?.[key]?.color || '#9CA3AF';
    return color + '20';
}
function activityTypeFg(key) {
    return props.activityTypes?.[key]?.color || '#6B7280';
}
function activityIcon(typeKey) {
    const icons = {
        call: PhoneArrowUpRightIcon,
        email: EnvelopeIcon,
        meeting: CalendarDaysIcon,
        note: DocumentTextIcon,
    };
    return icons[typeKey] || ChatBubbleLeftIcon;
}

const primaryEmail = computed(() => (props.lead.emails || []).find(e => e.is_primary)?.email || null);
const secondaryEmails = computed(() => (props.lead.emails || []).filter(e => !e.is_primary));

const canConvert = computed(() =>
    !props.lead.converted_user_id &&
    (props.lead.events || []).length > 0 &&
    !!primaryEmail.value
);
const convertDisabledReason = computed(() => {
    if (props.lead.converted_user_id) return 'Already converted';
    if (!primaryEmail.value) return 'Add a primary email before converting';
    if ((props.lead.events || []).length === 0) return 'Assign at least one event before converting';
    return '';
});

function instagramHandle(v) {
    if (!v) return null;
    let h = String(v).split('?')[0];
    h = h.replace(/^https?:\/\/(www\.)?instagram\.com\//i, '').replace(/\/+$/, '').replace(/^@/, '');
    return h || null;
}

// ───────────── Status / Assignment ─────────────
function changeStatus(newStatus) {
    if (newStatus === props.lead.status) return;
    router.patch(`/admin/sponsorship/leads/${props.lead.id}/status`, { status: newStatus }, { preserveScroll: true });
}
function reassignAdvisor(advisorId) {
    router.patch(`/admin/sponsorship/leads/${props.lead.id}/assign`, { assigned_to_user_id: advisorId || null }, { preserveScroll: true });
}

// ───────────── Tags ─────────────
const editingTags = ref(false);
const selectedTagIds = ref([]);
const tagSearch = ref('');
function startEditTags() {
    selectedTagIds.value = (props.lead.tags || []).map(t => t.id);
    tagSearch.value = '';
    editingTags.value = true;
}
function cancelEditTags() { editingTags.value = false; tagSearch.value = ''; }
function addTag(tagId) {
    if (!selectedTagIds.value.includes(tagId)) selectedTagIds.value.push(tagId);
    tagSearch.value = '';
}
function removeTag(tagId) { selectedTagIds.value = selectedTagIds.value.filter(id => id !== tagId); }
function saveTags() {
    router.patch(`/admin/sponsorship/leads/${props.lead.id}/tags`, { tag_ids: selectedTagIds.value }, {
        preserveScroll: true, onSuccess: () => { editingTags.value = false; },
    });
}
const filteredTags = computed(() => {
    if (!props.tags) return [];
    return props.tags.filter(t =>
        !selectedTagIds.value.includes(t.id) &&
        (!tagSearch.value || t.name.toLowerCase().includes(tagSearch.value.toLowerCase()))
    );
});
function getTagById(id) { return props.tags?.find(t => t.id === id); }

// ───────────── Events ─────────────
const showAddEventModal = ref(false);
const newEventId = ref('');
function addEvent() {
    if (!newEventId.value) return;
    router.post(`/admin/sponsorship/leads/${props.lead.id}/add-event`, { event_id: newEventId.value }, {
        preserveScroll: true,
        onSuccess: () => { showAddEventModal.value = false; newEventId.value = ''; },
    });
}
function removeEvent(eventId) {
    if (!confirm('Remove this event from the lead?')) return;
    router.delete(`/admin/sponsorship/leads/${props.lead.id}/remove-event`, { data: { event_id: eventId }, preserveScroll: true });
}
const availableEvents = computed(() => {
    const assignedIds = (props.lead.events || []).map(e => e.id);
    return (props.events || []).filter(e => !assignedIds.includes(e.id));
});

// ───────────── Notes (CRM style) ─────────────
const noteExpanded = ref(false);
const noteContent = ref('');
const noteTitle = ref('');
const noteShowTitle = ref(false);
const noteFiles = ref([]);
const noteFileInput = ref(null);

const leadNotes = computed(() =>
    (props.lead.activities || []).filter(a => a.type === 'note').sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
);

function handleNoteFileSelect(e) {
    for (const file of e.target.files) {
        noteFiles.value.push({ file, name: file.name });
    }
    e.target.value = '';
}
function removeNoteFile(i) { noteFiles.value.splice(i, 1); }

function saveNote() {
    if (!noteContent.value.trim()) return;
    const formData = new FormData();
    formData.append('type', 'note');
    formData.append('title', noteTitle.value.trim() || 'Note');
    formData.append('description', noteContent.value.trim());
    noteFiles.value.forEach((f, i) => formData.append(`files[${i}]`, f.file));

    router.post(`/admin/sponsorship/leads/${props.lead.id}/activities`, formData, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => cancelNote(),
    });
}
function cancelNote() {
    noteContent.value = ''; noteTitle.value = '';
    noteFiles.value = [];
    noteExpanded.value = false; noteShowTitle.value = false;
    if (noteFileInput.value) noteFileInput.value.value = '';
}

// ─── Edit existing note ─────────────────────────────────
const editingNoteId = ref(null);
const editingNoteTitle = ref('');
const editingNoteDescription = ref('');
const editingNoteFiles = ref([]);
const editingNoteShowTitle = ref(false);

function startEditNote(note) {
    editingNoteId.value = note.id;
    editingNoteTitle.value = note.title && note.title !== 'Note' && note.title !== 'Nota' ? note.title : '';
    editingNoteDescription.value = note.description || '';
    editingNoteFiles.value = [];
    editingNoteShowTitle.value = !!editingNoteTitle.value;
}

function cancelEditNote() {
    editingNoteId.value = null;
    editingNoteTitle.value = '';
    editingNoteDescription.value = '';
    editingNoteFiles.value = [];
    editingNoteShowTitle.value = false;
}

function addEditNoteFile(e) {
    for (const file of e.target.files) {
        editingNoteFiles.value.push({ file, name: file.name });
    }
    e.target.value = '';
}
function removeEditNoteFile(i) { editingNoteFiles.value.splice(i, 1); }

function saveEditNote(noteId) {
    if (!editingNoteDescription.value.trim()) return;
    const formData = new FormData();
    formData.append('_method', 'PATCH');
    formData.append('title', editingNoteTitle.value.trim() || 'Note');
    formData.append('description', editingNoteDescription.value.trim());
    editingNoteFiles.value.forEach((f, i) => formData.append(`files[${i}]`, f.file));

    router.post(`/admin/sponsorship/activities/${noteId}`, formData, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => cancelEditNote(),
    });
}

// ───────────── Activities ─────────────
const showActivityModal = ref(false);
const editingActivityId = ref(null); // null = create mode, otherwise editing this id
const activityForm = useForm({
    type: 'call', title: '', description: '', scheduled_at: '',
    assigned_to_user_id: null, is_contract: false,
    files: [],
});

// Tipos manuales — los demás (status_change/assignment/system) son auto-generados.
const manualActivityTypes = computed(() => {
    const allowed = ['call', 'email', 'meeting', 'note'];
    return Object.fromEntries(
        Object.entries(props.activityTypes || {}).filter(([k]) => allowed.includes(k))
    );
});

function openCreateActivity() {
    editingActivityId.value = null;
    activityForm.reset();
    activityForm.type = 'call';
    showActivityModal.value = true;
}
function openEditActivity(activity) {
    editingActivityId.value = activity.id;
    activityForm.type                = activity.type;
    activityForm.title               = activity.title || '';
    activityForm.description         = activity.description || '';
    activityForm.scheduled_at        = toLocalDatetimeInput(activity.scheduled_at);
    activityForm.assigned_to_user_id = activity.assigned_to?.id ?? null;
    activityForm.is_contract         = !!activity.is_contract;
    activityForm.files               = [];
    showActivityModal.value = true;
}
function addActivityFile(e) {
    activityForm.files = [...activityForm.files, ...Array.from(e.target.files || [])];
    e.target.value = '';
}
function removeActivityFile(i) { activityForm.files.splice(i, 1); }
function submitActivity() {
    if (editingActivityId.value) {
        // PATCH via _method spoofing porque el endpoint es PATCH y enviamos FormData (multipart).
        // Convertimos scheduled_at de local → UTC antes de enviar.
        activityForm.transform(data => ({
            ...data,
            scheduled_at: localDatetimeToUtcIso(data.scheduled_at),
            _method: 'PATCH',
        })).post(`/admin/sponsorship/activities/${editingActivityId.value}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { showActivityModal.value = false; editingActivityId.value = null; activityForm.reset(); activityForm.type = 'call'; },
        });
    } else {
        activityForm.transform(data => ({
            ...data,
            scheduled_at: localDatetimeToUtcIso(data.scheduled_at),
        })).post(`/admin/sponsorship/leads/${props.lead.id}/activities`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { activityForm.reset(); activityForm.type = 'call'; showActivityModal.value = false; },
        });
    }
}
function changeActivityStatus(activityId, status) {
    if (status === 'completed') return router.patch(`/admin/sponsorship/activities/${activityId}/complete`, {}, { preserveScroll: true });
    if (status === 'cancelled') return router.patch(`/admin/sponsorship/activities/${activityId}/cancel`, {}, { preserveScroll: true });
    if (status === 'not_completed') return router.patch(`/admin/sponsorship/activities/${activityId}/not-completed`, {}, { preserveScroll: true });
}

const sortedActivities = computed(() => {
    if (!props.lead.activities) return [];
    return [...props.lead.activities].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
});

const viewingActivity = ref(null);
function openActivityDetail(activity) { viewingActivity.value = activity; }
function closeActivityDetail() { viewingActivity.value = null; }

function emailDelivery(activity) {
    if (activity.type !== 'email') return null;

    // Si Mailgun nos confirmó un estado específico vía webhook, tiene prioridad.
    switch (activity.delivery_status) {
        case 'delivered':
            return { label: 'Delivered', cls: 'bg-green-50 text-green-700 border border-green-200' };
        case 'bounced':
            return { label: 'Bounced', cls: 'bg-red-50 text-red-700 border border-red-200' };
        case 'complained':
            return { label: 'Complained', cls: 'bg-orange-50 text-orange-700 border border-orange-200' };
        case 'rejected':
            return { label: 'Rejected', cls: 'bg-red-50 text-red-700 border border-red-200' };
        case 'temporary_fail':
            return { label: 'Retrying', cls: 'bg-amber-50 text-amber-700 border border-amber-200' };
    }

    // Fallback al status local del job.
    if (activity.status === 'completed') return { label: 'Sent', cls: 'bg-green-50 text-green-700 border border-green-200' };
    if (activity.status === 'not_completed') return { label: 'Failed', cls: 'bg-red-50 text-red-700 border border-red-200' };
    if (activity.status === 'pending') return { label: 'Queued', cls: 'bg-amber-50 text-amber-700 border border-amber-200' };
    return null;
}

// ───────────── Send Email modal ─────────────
const showEmailModal = ref(false);
const emailProcessing = ref(false);
const emailIsContract = ref(false);

function handleEmailSend({ subject, body, attachments }) {
    const formData = new FormData();
    formData.append('subject', subject);
    formData.append('body', body);
    formData.append('is_contract', emailIsContract.value ? '1' : '0');
    attachments.forEach((f) => formData.append('attachments[]', f));

    emailProcessing.value = true;
    router.post(`/admin/sponsorship/leads/${props.lead.id}/send-email`, formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { showEmailModal.value = false; emailIsContract.value = false; },
        onFinish: () => { emailProcessing.value = false; },
    });
}

function formatSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

// ───────────── File preview modal ─────────────
const previewDoc = ref(null);
function openPreview(file) {
    const url = `/storage/${file.file_path}`;
    const ext = file.file_name?.split('.').pop()?.toLowerCase();
    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext);
    const isOffice = ['docx', 'doc', 'xlsx', 'xls', 'pptx', 'ppt'].includes(ext);
    let viewerUrl = url;
    if (isOffice) {
        const fullUrl = window.location.origin + url;
        viewerUrl = `https://docs.google.com/gview?url=${encodeURIComponent(fullUrl)}&embedded=true`;
    }
    previewDoc.value = { url, viewerUrl, name: file.file_name, isImage };
}

// ───────────── Realtime: Mailgun delivery webhook → activity badge ─────────────
const page = usePage();
let echo = null;
let leadChannel = null;

onMounted(() => {
    echo = initEcho(page.props.reverb);
    if (!echo) return;

    leadChannel = echo.private(`sponsorship-lead.${props.lead.id}`);
    leadChannel.listen('.LeadActivityDeliveryUpdated', (e) => {
        if (!e?.id) return;
        const a = (props.lead.activities || []).find(x => x.id === e.id);
        if (!a) return;
        // Mutación directa — Vue reactividad propaga al template y a viewingActivity si está abierto.
        a.status           = e.status;
        a.delivery_status  = e.delivery_status;
        a.delivery_error   = e.delivery_error;
        a.delivered_at     = e.delivered_at;
    });
});

onBeforeUnmount(() => {
    if (leadChannel && echo) {
        echo.leave(`sponsorship-lead.${props.lead.id}`);
    }
});

</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/sponsorship/leads" class="flex items-center gap-1 text-gray-400 hover:text-gray-600 text-sm">
                    <ArrowLeftIcon class="w-4 h-4" /> Leads
                </Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">{{ lead.first_name }} {{ lead.last_name }}</h2>
            </div>
        </template>

        <div class="max-w-8xl mx-auto space-y-6">
            <!-- Header Card -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex flex-wrap gap-4 items-center flex-1">
                        <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0">
                            <span class="text-lg font-bold text-gray-400">{{ lead.first_name?.[0] }}{{ lead.last_name?.[0] }}</span>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                {{ lead.first_name }} {{ lead.last_name }}
                                <StarSolid v-if="lead.is_contract_winner" class="w-5 h-5 text-[#D4AF37]" title="Contract winner" />
                            </h3>
                            <p v-if="lead.company" class="text-gray-500 text-sm">{{ lead.company.name }}</p>
                        </div>
                        <div class="flex flex-col text-xs text-gray-400 ml-2">
                            <span v-if="lead.source">Source: <span class="font-medium text-gray-600">{{ lead.source }}<span v-if="lead.source_detail"> ({{ lead.source_detail }})</span></span></span>
                            <span>Registered: {{ formatDateTime(lead.created_at) }}</span>
                            <span v-if="lead.last_email_sent_at">Last email: {{ formatDateTime(lead.last_email_sent_at) }}
                                <span v-if="lead.last_email_status" class="ml-1 text-[10px] px-1 rounded"
                                    :class="lead.last_email_status === 'sent' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">{{ lead.last_email_status }}</span>
                            </span>
                        </div>
                        <div class="flex flex-col ml-2">
                            <span class="text-xs font-medium rounded-lg px-3 py-1 w-fit mb-1 text-white"
                                :style="{ backgroundColor: statuses[lead.status]?.color }">
                                Lead status: {{ statusLabel(lead.status) }}
                            </span>
                            <div v-if="isLider && advisors?.length" class="flex items-center gap-1 mb-1">
                                <span class="text-xs text-gray-400">Advisor:</span>
                                <select :value="lead.assigned_to?.id || ''" @change="reassignAdvisor($event.target.value)"
                                    class="text-xs font-medium text-gray-600 border border-gray-200 rounded px-1.5 py-0.5 bg-white focus:outline-none focus:ring-1 focus:ring-black/10">
                                    <option value="">— Unassigned —</option>
                                    <option v-for="a in advisors" :key="a.id" :value="a.id">
                                        {{ a.first_name }} {{ a.last_name }}{{ a.sponsorship_type === 'lider' ? ' (L)' : '' }}
                                    </option>
                                </select>
                            </div>
                            <span v-else-if="lead.assigned_to" class="text-xs text-gray-400">Advisor: <span class="font-medium text-gray-600">{{ lead.assigned_to.first_name }} {{ lead.assigned_to.last_name }}</span></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button @click="openCreateActivity"
                            class="px-4 py-1.5 bg-[#D4AF37] text-white rounded-lg text-xs font-medium hover:bg-[#b8962f] transition-colors flex items-center gap-1">
                            <PlusIcon class="w-3.5 h-3.5" /> Activity
                        </button>
                        <button @click="showEmailModal = true"
                            class="px-4 py-1.5 bg-gray-700 text-white rounded-lg text-xs font-medium hover:bg-gray-600 transition-colors flex items-center gap-1">
                            <EnvelopeIcon class="w-3.5 h-3.5" /> Send Email
                        </button>
                        <Link :href="`/admin/sponsorship/leads/${lead.id}/edit`"
                            class="px-4 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors flex items-center gap-1">
                            <PencilSquareIcon class="w-3.5 h-3.5" /> Edit
                        </Link>
                        <Link v-if="canConvert" :href="`/admin/sponsorship/leads/${lead.id}/convert`"
                            class="px-4 py-1.5 bg-amber-500 text-white rounded-lg text-xs font-medium hover:bg-amber-600 transition-colors flex items-center gap-1">
                            <StarIcon class="w-3.5 h-3.5" /> Close contract & Convert
                        </Link>
                        <span v-else-if="!lead.converted_user_id"
                            :title="convertDisabledReason"
                            class="px-4 py-1.5 bg-gray-200 text-gray-400 rounded-lg text-xs font-medium flex items-center gap-1 cursor-not-allowed">
                            <StarIcon class="w-3.5 h-3.5" /> Close contract & Convert
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

                <!-- Col 1: Contact + Tags + Business + Documents -->
                <div class="space-y-6">
                    <!-- Contact + Tags -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-4 space-y-5">
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-3">Contact Info</h4>
                            <div class="grid grid-cols-1 gap-3 text-sm">
                                <div v-if="primaryEmail" class="flex items-center gap-2">
                                    <EnvelopeIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
                                    <span class="text-gray-700 truncate">{{ primaryEmail }}</span>
                                </div>
                                <div v-for="em in secondaryEmails" :key="em.id" class="flex items-center gap-2 pl-6">
                                    <span class="text-xs text-gray-400">+</span>
                                    <span class="text-gray-500 text-xs truncate">{{ em.email }}</span>
                                </div>
                                <div v-if="lead.phone" class="flex items-center gap-2">
                                    <PhoneIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
                                    <a :href="`tel:${lead.phone}`" class="text-blue-600 hover:underline">{{ lead.phone }}</a>
                                </div>
                                <div v-if="lead.charge" class="flex items-center gap-2">
                                    <UserIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
                                    <span class="text-gray-700">{{ lead.charge }}</span>
                                </div>
                                <div v-if="lead.instagram" class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-pink-500 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                    </svg>
                                    <a :href="`https://instagram.com/${instagramHandle(lead.instagram)}`" target="_blank" class="text-pink-600 hover:text-pink-700">@{{ instagramHandle(lead.instagram) }}</a>
                                </div>
                                <div v-if="lead.website_url" class="flex items-center gap-2">
                                    <GlobeAltIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
                                    <a :href="lead.website_url" target="_blank" class="text-blue-600 hover:underline truncate">{{ lead.website_url }}</a>
                                </div>
                                <div v-if="lead.linkedin_url" class="flex items-center gap-2">
                                    <LinkIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
                                    <a :href="lead.linkedin_url" target="_blank" class="text-blue-600 hover:underline truncate">LinkedIn</a>
                                </div>
                            </div>
                        </div>

                        <!-- Tags -->
                        <div class="pt-4 border-t border-gray-100">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-medium text-gray-500">Tags</span>
                                <div v-if="editingTags" class="flex items-center gap-1.5">
                                    <button @click="saveTags" class="w-7 h-7 rounded-full bg-black text-white flex items-center justify-center hover:bg-gray-800 transition-colors">
                                        <CheckCircleIcon class="w-3.5 h-3.5" />
                                    </button>
                                    <button @click="cancelEditTags" class="w-7 h-7 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center hover:bg-gray-300 transition-colors">
                                        <XMarkIcon class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                            <div v-if="!editingTags" class="flex flex-wrap items-center gap-1.5">
                                <span v-for="t in lead.tags" :key="t.id"
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border border-gray-300"
                                    :style="{ backgroundColor: t.color + '50', color: '#1f2937' }">{{ t.name }}</span>
                                <button @click="startEditTags"
                                    class="w-7 h-7 rounded-full border-2 border-dashed border-gray-300 text-gray-400 flex items-center justify-center hover:border-gray-400 hover:text-gray-500 transition-colors">
                                    <PlusIcon class="w-3.5 h-3.5" />
                                </button>
                            </div>
                            <div v-else>
                                <div class="border border-gray-900 rounded-xl p-3">
                                    <div class="flex flex-wrap gap-1.5 mb-2">
                                        <span v-for="id in selectedTagIds" :key="id"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium cursor-pointer"
                                            :style="{ backgroundColor: (getTagById(id)?.color || '#6B7280') + '50', color: '#1f2937' }"
                                            @click="removeTag(id)">
                                            {{ getTagById(id)?.name }} <XMarkIcon class="w-3 h-3" />
                                        </span>
                                        <span v-if="!selectedTagIds.length" class="text-xs text-gray-400 italic py-1">No tags</span>
                                    </div>
                                    <div class="relative">
                                        <input v-model="tagSearch" type="text" placeholder="Tag Name"
                                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-black focus:border-black" />
                                        <div v-if="filteredTags.length" class="mt-1 max-h-48 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-sm">
                                            <button v-for="t in filteredTags" :key="t.id" @click="addTag(t.id)"
                                                class="w-full text-left px-3 py-2 hover:bg-gray-50 flex items-center gap-2 transition-colors">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium"
                                                    :style="{ backgroundColor: t.color + '50', color: '#1f2937' }">{{ t.name }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Business Information -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-4">
                        <h4 class="font-semibold text-gray-800 mb-4">Business Information</h4>
                        <dl class="space-y-2 text-sm">
                            <div class="flex items-center gap-2 mb-3">
                                <dt class="text-gray-500">Company:</dt>
                                <dd class="font-medium text-gray-900">{{ lead.company?.name || '—' }}</dd>
                            </div>
                            <div class="flex items-center gap-2 mb-3">
                                <dt class="text-gray-500">Category:</dt>
                                <dd class="font-medium text-gray-900">{{ lead.category?.name || '—' }}</dd>
                            </div>
                            <div class="flex items-center gap-2 mb-3">
                                <dt class="text-gray-500">Source:</dt>
                                <dd class="font-medium text-gray-900">{{ lead.source }}<span v-if="lead.source_detail" class="text-gray-500"> ({{ lead.source_detail }})</span></dd>
                            </div>
                            <div v-if="lead.converted_user" class="flex items-center gap-2 mb-3">
                                <dt class="text-gray-500">Converted to:</dt>
                                <dd class="font-medium text-green-700">{{ lead.converted_user.first_name }} {{ lead.converted_user.last_name }}</dd>
                            </div>
                            <div v-if="lead.notes" class="pt-2 border-t border-gray-100">
                                <dt class="text-gray-500 mb-1">Initial notes:</dt>
                                <dd class="text-gray-700 whitespace-pre-line">{{ lead.notes }}</dd>
                            </div>
                        </dl>
                    </div>

                </div>

                <!-- Col 2-3: Status & Events + Notes -->
                <div class="md:col-span-2 lg:col-span-2 space-y-6">
                    <!-- Status & Events -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-4 space-y-4">
                        <h4 class="font-semibold text-gray-800">Status & Assignment</h4>

                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Lead Status</label>
                            <select @change="changeStatus($event.target.value)" :value="lead.status"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-black focus:border-black">
                                <option v-for="(info, key) in statuses" :key="key" :value="key">{{ info.label }}</option>
                            </select>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-xs text-gray-400">Events of interest</label>
                                <button @click="showAddEventModal = true" class="text-xs text-blue-600 hover:text-blue-800 font-medium">+ Add Event</button>
                            </div>
                            <div v-if="lead.events?.length" class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div v-for="ev in lead.events" :key="ev.id" class="border border-gray-100 rounded-lg px-3 py-2 flex items-center justify-between">
                                    <p class="text-xs font-medium text-gray-700 truncate">{{ ev.name }}</p>
                                    <button @click="removeEvent(ev.id)" class="text-gray-300 hover:text-red-500 transition-colors" title="Remove event">
                                        <XMarkIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                            <p v-else class="text-xs text-gray-400 italic">No events assigned.</p>
                        </div>
                    </div>

                    <!-- Notes CRM -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-4">
                        <h4 class="font-semibold text-gray-800 mb-4">Notes</h4>

                        <div class="mb-5">
                            <div v-if="!noteExpanded" @click="noteExpanded = true"
                                class="border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-400 cursor-text hover:border-gray-300 transition-colors">
                                What's this note about?
                            </div>
                            <div v-else class="border-2 border-black rounded-xl overflow-hidden">
                                <div v-if="noteShowTitle" class="px-4 pt-3">
                                    <input v-model="noteTitle" type="text" placeholder="Title (optional)"
                                        class="w-full border-0 p-0 text-sm font-semibold text-gray-900 focus:ring-0 placeholder-gray-400" />
                                </div>
                                <textarea v-model="noteContent" rows="3" placeholder="What's this note about?"
                                    class="w-full border-0 px-4 py-3 text-sm text-gray-700 focus:ring-0 placeholder-gray-400 resize-none"></textarea>
                                <div v-if="noteFiles.length" class="px-4 py-2 border-t border-gray-100 space-y-1">
                                    <div v-for="(f, idx) in noteFiles" :key="idx" class="flex items-center justify-between bg-blue-50 rounded-lg px-3 py-1.5">
                                        <div class="flex items-center gap-2 text-xs text-blue-700">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                            <span class="truncate max-w-48">{{ f.name }}</span>
                                        </div>
                                        <button @click="removeNoteFile(idx)" class="text-xs text-red-500 hover:text-red-700">&times;</button>
                                    </div>
                                </div>
                                <div class="px-4 py-2 bg-gray-50 flex items-center gap-3 border-t border-gray-100">
                                    <button @click="saveNote" :disabled="!noteContent.trim()"
                                        class="px-4 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 disabled:opacity-40 transition-colors">Save</button>
                                    <button @click="cancelNote"
                                        class="px-4 py-1.5 border border-gray-200 rounded-lg text-xs font-medium hover:bg-gray-100 transition-colors">Cancel</button>
                                    <label class="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700 cursor-pointer transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                        Attach File
                                        <input type="file" ref="noteFileInput" @change="handleNoteFileSelect" multiple class="hidden" />
                                    </label>
                                    <button v-if="!noteShowTitle" @click="noteShowTitle = true"
                                        class="text-xs text-gray-500 hover:text-gray-700 transition-colors">Add a Title</button>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-0 divide-y divide-gray-100">
                            <div v-for="note in leadNotes" :key="note.id" class="py-4 first:pt-0">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-bold flex-shrink-0 mt-0.5"
                                        :class="note.creator ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'">
                                        {{ note.creator ? (note.creator.first_name?.[0] || '') + (note.creator.last_name?.[0] || '') : 'R7' }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2 mb-0.5">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-sm font-semibold text-gray-900">{{ note.creator ? note.creator.first_name + ' ' + note.creator.last_name : 'System' }}</span>
                                                <span class="text-xs text-gray-400">{{ formatNoteDate(note.created_at) }}</span>
                                            </div>
                                            <button v-if="editingNoteId !== note.id" @click="startEditNote(note)"
                                                class="text-xs text-gray-400 hover:text-gray-700 transition-colors flex items-center gap-1">
                                                <PencilSquareIcon class="w-3.5 h-3.5" /> Edit
                                            </button>
                                        </div>

                                        <!-- View mode -->
                                        <template v-if="editingNoteId !== note.id">
                                            <p v-if="note.title && note.title !== 'Note' && note.title !== 'Nota'" class="text-sm font-semibold text-gray-800 mb-0.5">{{ note.title }}</p>
                                            <p class="text-sm text-gray-600 whitespace-pre-line">{{ note.description }}</p>
                                            <div v-if="note.files?.length" class="flex flex-wrap gap-1.5 mt-2">
                                                <button v-for="f in note.files" :key="f.id" @click="openPreview(f)"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-600 hover:bg-gray-100 transition-colors cursor-pointer">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                    {{ f.file_name }}
                                                </button>
                                            </div>
                                            <p v-if="note.edited_at" class="text-[11px] text-gray-400 italic mt-2">
                                                Edited by {{ note.editor ? `${note.editor.first_name} ${note.editor.last_name}` : 'Unknown' }} on {{ formatNoteDate(note.edited_at) }}
                                            </p>
                                        </template>

                                        <!-- Edit mode -->
                                        <div v-else class="border-2 border-black rounded-xl overflow-hidden mt-1">
                                            <div v-if="editingNoteShowTitle" class="px-4 pt-3">
                                                <input v-model="editingNoteTitle" type="text" placeholder="Title (optional)"
                                                    class="w-full border-0 p-0 text-sm font-semibold text-gray-900 focus:ring-0 placeholder-gray-400" />
                                            </div>
                                            <textarea v-model="editingNoteDescription" rows="3"
                                                class="w-full border-0 px-4 py-3 text-sm text-gray-700 focus:ring-0 placeholder-gray-400 resize-none"></textarea>
                                            <div v-if="editingNoteFiles.length" class="px-4 py-2 border-t border-gray-100 space-y-1">
                                                <div v-for="(f, idx) in editingNoteFiles" :key="idx" class="flex items-center justify-between bg-blue-50 rounded-lg px-3 py-1.5">
                                                    <span class="text-xs text-blue-700 truncate max-w-48">{{ f.name }}</span>
                                                    <button @click="removeEditNoteFile(idx)" class="text-xs text-red-500">&times;</button>
                                                </div>
                                            </div>
                                            <div class="px-4 py-2 bg-gray-50 flex items-center gap-3 border-t border-gray-100">
                                                <button @click="saveEditNote(note.id)" :disabled="!editingNoteDescription.trim()"
                                                    class="px-4 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 disabled:opacity-40">Save</button>
                                                <button @click="cancelEditNote"
                                                    class="px-4 py-1.5 border border-gray-200 rounded-lg text-xs font-medium hover:bg-gray-100">Cancel</button>
                                                <label class="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700 cursor-pointer">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                    Attach File
                                                    <input type="file" multiple @change="addEditNoteFile" class="hidden" />
                                                </label>
                                                <button v-if="!editingNoteShowTitle" @click="editingNoteShowTitle = true"
                                                    class="text-xs text-gray-500 hover:text-gray-700">Add a Title</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-if="!leadNotes.length" class="text-sm text-gray-400 italic py-4">No notes yet.</p>
                        </div>
                    </div>
                </div>

                <!-- Col 4: Activity Timeline -->
                <div class="space-y-6">
                    <div class="bg-white rounded-2xl border border-gray-200 p-4">
                        <h4 class="font-semibold text-gray-800 mb-4">Activity History</h4>

                        <div v-if="sortedActivities.length" class="space-y-4">
                            <div v-for="activity in sortedActivities" :key="activity.id" class="relative pl-7">
                                <div class="absolute left-2.5 top-6 bottom-0 w-px bg-gray-100"></div>
                                <div class="absolute left-0 top-0.5 w-5 h-5 rounded-full flex items-center justify-center"
                                    :style="{ backgroundColor: activityTypeBg(activity.type) }">
                                    <component :is="activityIcon(activity.type)" class="w-3 h-3" :style="{ color: activityTypeFg(activity.type) }" />
                                </div>

                                <div class="pb-4">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-[10px] font-medium px-1.5 py-0.5 rounded"
                                                    :style="{ backgroundColor: activityTypeBg(activity.type), color: activityTypeFg(activity.type) }">
                                                    {{ activityTypeLabel(activity.type) }}
                                                </span>
                                                <span v-if="activity.is_contract"
                                                    class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-[#D4AF37] text-white">Contract</span>
                                                <span v-if="emailDelivery(activity)"
                                                    class="text-[10px] font-medium px-1.5 py-0.5 rounded"
                                                    :class="emailDelivery(activity).cls">{{ emailDelivery(activity).label }}</span>
                                                <span class="text-sm font-medium text-gray-900 line-clamp-2 break-words">{{ activity.title }}</span>
                                            </div>
                                            <div v-if="activity.description && activity.type === 'email'"
                                                class="sponsorship-email-preview text-xs text-gray-500 mt-1 line-clamp-3 break-words"
                                                v-html="activity.description"></div>
                                            <p v-else-if="activity.description" class="text-xs text-gray-500 mt-1 whitespace-pre-line line-clamp-3 break-words">{{ activity.description }}</p>
                                            <div v-if="activity.files?.length" class="flex flex-wrap gap-1 mt-2">
                                                <button v-for="f in activity.files" :key="f.id" @click="openPreview(f)"
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-50 border border-gray-200 rounded text-[10px] text-gray-600 hover:bg-gray-100 transition-colors cursor-pointer">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                    {{ f.file_name }}
                                                </button>
                                            </div>
                                        </div>

                                        <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                            <select @change="changeActivityStatus(activity.id, $event.target.value)" :value="activity.status"
                                                class="text-[10px] font-medium rounded-lg px-2 py-1 border-0 cursor-pointer focus:ring-1 focus:ring-black"
                                                :class="{
                                                    'bg-amber-50 text-amber-700': activity.status === 'pending',
                                                    'bg-green-50 text-green-700': activity.status === 'completed',
                                                    'bg-gray-100 text-gray-500': activity.status === 'cancelled',
                                                    'bg-red-50 text-red-600': activity.status === 'not_completed',
                                                }">
                                                <option value="pending">Pending</option>
                                                <option value="completed">Completed</option>
                                                <option value="cancelled">Cancelled</option>
                                                <option value="not_completed">Not completed</option>
                                            </select>
                                            <button @click="openActivityDetail(activity)"
                                                class="inline-flex items-center gap-1 text-[10px] font-medium text-gray-500 hover:text-black px-2 py-0.5 rounded border border-gray-200 hover:border-gray-400 transition-colors">
                                                View
                                            </button>
                                            <button v-if="activity.type === 'call' || activity.type === 'meeting'"
                                                @click="openEditActivity(activity)"
                                                class="inline-flex items-center gap-1 text-[10px] font-medium text-gray-500 hover:text-black px-2 py-0.5 rounded border border-gray-200 hover:border-gray-400 transition-colors">
                                                <PencilSquareIcon class="w-3 h-3" /> Edit
                                            </button>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 mt-1.5 text-[11px] text-gray-400">
                                        <span v-if="activity.creator">{{ activity.creator.first_name }} {{ activity.creator.last_name }}</span>
                                        <span>{{ relativeTime(activity.created_at) }}</span>
                                        <span v-if="activity.scheduled_at" class="flex items-center gap-0.5">
                                            <ClockIcon class="w-3 h-3" />
                                            {{ formatDateTime(activity.scheduled_at) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p v-else class="text-sm text-gray-400 italic text-center py-4">No activities recorded.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <Teleport to="body">
            <!-- Activity Modal -->
            <div v-if="showActivityModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl w-full max-w-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">{{ editingActivityId ? 'Edit Activity' : 'New Activity' }}</h3>
                        <button @click="showActivityModal = false" class="text-gray-400 hover:text-gray-600"><XMarkIcon class="w-5 h-5" /></button>
                    </div>
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Type *</label>
                                <select v-model="activityForm.type" :disabled="!!editingActivityId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white disabled:bg-gray-100 disabled:text-gray-500">
                                    <option v-for="(meta, key) in manualActivityTypes" :key="key" :value="key">{{ meta.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Scheduled at</label>
                                <input v-model="activityForm.scheduled_at" type="datetime-local" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Title *</label>
                            <input v-model="activityForm.title" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                            <textarea v-model="activityForm.description" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none"></textarea>
                        </div>
                        <div v-if="activityForm.type === 'call' || activityForm.type === 'meeting'">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Assign to</label>
                            <select v-model="activityForm.assigned_to_user_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                <option :value="null">— Me</option>
                                <option v-for="a in advisors" :key="a.id" :value="a.id">{{ a.first_name }} {{ a.last_name }}{{ a.sponsorship_type === 'lider' ? ' (L)' : '' }}</option>
                            </select>
                        </div>
                        <label v-if="activityForm.type === 'email'" class="flex items-center gap-2 text-sm bg-yellow-50 border border-[#D4AF37] rounded-lg px-3 py-2">
                            <input v-model="activityForm.is_contract" type="checkbox" class="rounded" />
                            <span>This email is the contract — status will change to <strong>Contrato</strong>.</span>
                        </label>
                        <div>
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 border border-dashed border-gray-300 rounded-lg text-xs cursor-pointer hover:bg-gray-50">
                                <PlusIcon class="w-4 h-4" /> Attach files
                                <input type="file" multiple @change="addActivityFile" class="hidden" />
                            </label>
                            <div v-if="activityForm.files.length" class="mt-2 space-y-1">
                                <div v-for="(f, i) in activityForm.files" :key="i" class="flex items-center justify-between text-xs bg-gray-50 rounded px-2 py-1">
                                    <span class="truncate">{{ f.name }}</span>
                                    <button type="button" @click="removeActivityFile(i)" class="text-red-500"><XMarkIcon class="w-3 h-3" /></button>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button @click="showActivityModal = false" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancel</button>
                            <button @click="submitActivity" :disabled="activityForm.processing || !activityForm.title"
                                class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40">Save</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Send Email Modal (rich text via EmailComposer) -->
            <div v-if="showEmailModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showEmailModal = false">
                <EmailComposer
                    :recipient-label="`${lead.first_name} ${lead.last_name}${primaryEmail ? ' <' + primaryEmail + '>' : ''}`"
                    :processing="emailProcessing"
                    :hide-schedule="true"
                    :hide-bcc-note="true"
                    send-label="Send email"
                    @send="handleEmailSend"
                    @close="showEmailModal = false"
                >
                    <template #extra-options>
                        <label class="flex items-center gap-2 text-sm bg-yellow-50 border border-[#D4AF37] rounded-lg px-3 py-2">
                            <input v-model="emailIsContract" type="checkbox" class="rounded" />
                            <span>This is the contract email. Lead status will switch to <strong>Contrato</strong>.</span>
                        </label>
                    </template>
                </EmailComposer>
            </div>

            <!-- Add Event Modal -->
            <div v-if="showAddEventModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl w-full max-w-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Add event</h3>
                        <button @click="showAddEventModal = false" class="text-gray-400 hover:text-gray-600"><XMarkIcon class="w-5 h-5" /></button>
                    </div>
                    <select v-model="newEventId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white mb-4">
                        <option value="">Select event...</option>
                        <option v-for="e in availableEvents" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                    <div class="flex justify-end gap-2">
                        <button @click="showAddEventModal = false" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancel</button>
                        <button @click="addEvent" :disabled="!newEventId" class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40">Add</button>
                    </div>
                </div>
            </div>

            <!-- Activity Detail Modal -->
            <div v-if="viewingActivity" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/60" @click="closeActivityDetail"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] mx-4 flex flex-col overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 flex-shrink-0">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-[10px] font-medium px-1.5 py-0.5 rounded"
                                :style="{ backgroundColor: activityTypeBg(viewingActivity.type), color: activityTypeFg(viewingActivity.type) }">
                                {{ activityTypeLabel(viewingActivity.type) }}
                            </span>
                            <span v-if="viewingActivity.is_contract" class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-[#D4AF37] text-white">Contract</span>
                            <span v-if="emailDelivery(viewingActivity)" class="text-[10px] font-medium px-1.5 py-0.5 rounded" :class="emailDelivery(viewingActivity).cls">{{ emailDelivery(viewingActivity).label }}</span>
                            <span class="text-[11px] font-medium px-2 py-0.5 rounded"
                                :class="{
                                    'bg-amber-50 text-amber-700': viewingActivity.status === 'pending',
                                    'bg-green-50 text-green-700': viewingActivity.status === 'completed',
                                    'bg-gray-100 text-gray-500': viewingActivity.status === 'cancelled',
                                    'bg-red-50 text-red-600': viewingActivity.status === 'not_completed',
                                }">
                                {{ viewingActivity.status }}
                            </span>
                        </div>
                        <button @click="closeActivityDetail" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg text-lg leading-none">&times;</button>
                    </div>
                    <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3">
                        <h3 class="text-base font-semibold text-gray-900 break-words">{{ viewingActivity.title }}</h3>
                        <div class="flex flex-wrap gap-3 text-[11px] text-gray-500">
                            <span v-if="viewingActivity.creator">By: <span class="text-gray-700 font-medium">{{ viewingActivity.creator.first_name }} {{ viewingActivity.creator.last_name }}</span></span>
                            <span>Created: <span class="text-gray-700">{{ formatDateTime(viewingActivity.created_at) }}</span></span>
                            <span v-if="viewingActivity.scheduled_at">Scheduled: <span class="text-gray-700">{{ formatDateTime(viewingActivity.scheduled_at) }}</span></span>
                            <span v-if="viewingActivity.completed_at">Completed: <span class="text-gray-700">{{ formatDateTime(viewingActivity.completed_at) }}</span></span>
                        </div>
                        <div v-if="viewingActivity.type === 'email' && viewingActivity.delivery_error"
                            class="border border-red-200 bg-red-50 rounded-lg px-3 py-2 text-xs text-red-700">
                            <strong class="font-semibold">Delivery error:</strong> {{ viewingActivity.delivery_error }}
                        </div>
                        <div v-if="viewingActivity.description && viewingActivity.type === 'email'"
                            class="sponsorship-email-preview text-sm text-gray-700 break-words border-t border-gray-100 pt-3"
                            v-html="viewingActivity.description"></div>
                        <div v-else-if="viewingActivity.description" class="text-sm text-gray-700 whitespace-pre-line break-words border-t border-gray-100 pt-3">{{ viewingActivity.description }}</div>
                        <div v-if="viewingActivity.files?.length" class="border-t border-gray-100 pt-3">
                            <p class="text-xs font-medium text-gray-500 mb-2">Attachments</p>
                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="f in viewingActivity.files" :key="f.id" @click="openPreview(f)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-600 hover:bg-gray-100 transition-colors cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    {{ f.file_name }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- File Preview Modal -->
            <div v-if="previewDoc" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/70" @click="previewDoc = null"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl h-[85vh] mx-4 flex flex-col overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 flex-shrink-0">
                        <div class="flex items-center gap-2 min-w-0">
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            <span class="text-sm font-medium text-gray-900 truncate">{{ previewDoc.name }}</span>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <a :href="previewDoc.url" download
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors">
                                Download
                            </a>
                            <button @click="previewDoc = null" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors text-lg leading-none">&times;</button>
                        </div>
                    </div>
                    <div class="flex-1 bg-gray-100 overflow-auto flex items-center justify-center">
                        <img v-if="previewDoc.isImage" :src="previewDoc.url" :alt="previewDoc.name" class="max-w-full max-h-full object-contain" />
                        <iframe v-else :src="previewDoc.viewerUrl" class="w-full h-full border-0"></iframe>
                    </div>
                </div>
            </div>

        </Teleport>
    </AdminLayout>
</template>

<style>
.sponsorship-email-preview p { margin: 0 0 0.5em 0; }
.sponsorship-email-preview p:last-child { margin-bottom: 0; }
.sponsorship-email-preview ul, .sponsorship-email-preview ol { padding-left: 1.25em; margin: 0 0 0.5em 0; }
.sponsorship-email-preview a { color: #D4AF37; text-decoration: underline; }
.sponsorship-email-preview img { max-width: 100%; height: auto; border-radius: 6px; margin: 6px 0; }
.sponsorship-email-preview hr { border: 0; border-top: 1px solid #e5e7eb; margin: 10px 0; }
.sponsorship-email-preview strong { font-weight: 600; color: #111827; }
</style>
