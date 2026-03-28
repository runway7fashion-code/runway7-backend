<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    ArrowLeftIcon, EnvelopeIcon, PhoneIcon, GlobeAltIcon, TrashIcon,
    PencilSquareIcon, CheckCircleIcon, ClockIcon, UserIcon, PlusIcon,
    ChatBubbleLeftIcon, CalendarDaysIcon, PhoneArrowUpRightIcon,
    DocumentTextIcon, ChevronDownIcon, ArrowPathIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    lead: Object,
    statuses: Object,
    activityTypes: Object,
    advisors: Array,
    events: Array,
    allTags: Array,
    isLeader: Boolean,
});

// Tags
const editingTags = ref(false);
const selectedTagIds = ref([]);
const tagSearch = ref('');

function startEditTags() {
    selectedTagIds.value = (props.lead.tags || []).map(t => t.id);
    tagSearch.value = '';
    editingTags.value = true;
}

function cancelEditTags() {
    editingTags.value = false;
    tagSearch.value = '';
}

function addTag(tagId) {
    if (!selectedTagIds.value.includes(tagId)) {
        selectedTagIds.value.push(tagId);
    }
    tagSearch.value = '';
}

function removeTag(tagId) {
    selectedTagIds.value = selectedTagIds.value.filter(id => id !== tagId);
}

function saveTags() {
    router.patch(`/admin/sales/leads/${props.lead.id}/tags`, { tag_ids: selectedTagIds.value }, {
        preserveScroll: true,
        onSuccess: () => { editingTags.value = false; },
    });
}

const filteredTags = computed(() => {
    if (!props.allTags) return [];
    return props.allTags.filter(t =>
        !selectedTagIds.value.includes(t.id) &&
        (!tagSearch.value || t.name.toLowerCase().includes(tagSearch.value.toLowerCase()))
    );
});

function getTagById(id) {
    return props.allTags?.find(t => t.id === id);
}

// Notes (CRM style)
const noteExpanded = ref(false);
const noteContent = ref('');
const noteTitle = ref('');
const noteShowTitle = ref(false);
const noteFile = ref(null);
const noteFileName = ref('');
const noteFileInput = ref(null);

const leadNotes = computed(() =>
    (props.lead.activities || []).filter(a => a.type === 'note').sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
);

function saveNote() {
    if (!noteContent.value.trim()) return;
    const formData = new FormData();
    formData.append('type', 'note');
    formData.append('title', noteTitle.value.trim() || 'Nota');
    formData.append('description', noteContent.value.trim());
    if (noteFile.value) formData.append('file', noteFile.value);

    router.post(`/admin/sales/leads/${props.lead.id}/activity`, formData, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { cancelNote(); },
    });
}

function cancelNote() {
    noteContent.value = '';
    noteTitle.value = '';
    noteFile.value = null;
    noteFileName.value = '';
    noteExpanded.value = false;
    noteShowTitle.value = false;
}

function handleFileSelect(e) {
    const file = e.target.files[0];
    if (!file) return;
    noteFile.value = file;
    noteFileName.value = file.name;
}

function removeFile() {
    noteFile.value = null;
    noteFileName.value = '';
    if (noteFileInput.value) noteFileInput.value.value = '';
}

// Document preview
const previewDoc = ref(null);

function openPreview(note) {
    const url = `/storage/${note.file_path}`;
    const ext = note.file_name?.split('.').pop()?.toLowerCase();
    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext);
    const isOffice = ['docx', 'doc', 'xlsx', 'xls', 'pptx', 'ppt'].includes(ext);
    let viewerUrl = url;
    if (isOffice) {
        const fullUrl = window.location.origin + url;
        viewerUrl = `https://docs.google.com/gview?url=${encodeURIComponent(fullUrl)}&embedded=true`;
    }
    previewDoc.value = { url, viewerUrl, name: note.file_name, isImage };
}

function formatNoteDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit' }) + ', ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
}

// Status change
function changeStatus(newStatus) {
    if (newStatus === props.lead.status) return;
    router.patch(`/admin/sales/leads/${props.lead.id}/status`, { status: newStatus }, { preserveScroll: true });
}

function changeEventStatus(eventId, newStatus) {
    router.patch(`/admin/sales/leads/${props.lead.id}/event-status`, { event_id: eventId, status: newStatus }, { preserveScroll: true });
}

// Reassign advisor
function reassignAdvisor(advisorId) {
    router.patch(`/admin/sales/leads/${props.lead.id}/assign`, { assigned_to: advisorId || null }, { preserveScroll: true });
}

// Activity form
const activityForm = useForm({
    type: 'note',
    title: '',
    description: '',
    scheduled_at: '',
});

function submitActivity() {
    activityForm.post(`/admin/sales/leads/${props.lead.id}/activity`, {
        preserveScroll: true,
        onSuccess: () => activityForm.reset(),
    });
}

// Complete activity
function completeActivity(activityId) {
    router.patch(`/admin/sales/activities/${activityId}/complete`, {}, { preserveScroll: true });
}

// Delete lead
const showDeleteModal = ref(false);
function deleteLead() {
    router.delete(`/admin/sales/leads/${props.lead.id}`, {
        onSuccess: () => { showDeleteModal.value = false; },
    });
}

// Helpers
function statusBadgeStyle(statusKey) {
    const s = props.statuses?.[statusKey];
    if (!s) return 'bg-gray-100 text-gray-600';
    const colorMap = {
        gray: 'bg-gray-100 text-gray-700',
        blue: 'bg-blue-50 text-blue-700',
        yellow: 'bg-yellow-50 text-yellow-700',
        green: 'bg-green-50 text-green-700',
        red: 'bg-red-50 text-red-700',
        purple: 'bg-purple-50 text-purple-700',
        indigo: 'bg-indigo-50 text-indigo-700',
        amber: 'bg-amber-50 text-amber-700',
        orange: 'bg-orange-50 text-orange-700',
        emerald: 'bg-emerald-50 text-emerald-700',
    };
    return colorMap[s.color] || 'bg-gray-100 text-gray-600';
}

function statusLabel(statusKey) {
    return props.statuses?.[statusKey]?.label || statusKey;
}

function activityTypeLabel(typeKey) {
    return props.activityTypes?.[typeKey]?.label || typeKey;
}

function activityTypeColor(typeKey) {
    const t = props.activityTypes?.[typeKey];
    if (!t) return 'bg-gray-100 text-gray-600';
    const colorMap = {
        blue: 'bg-blue-50 text-blue-700',
        green: 'bg-green-50 text-green-700',
        purple: 'bg-purple-50 text-purple-700',
        yellow: 'bg-yellow-50 text-yellow-700',
        gray: 'bg-gray-100 text-gray-700',
        red: 'bg-red-50 text-red-700',
        indigo: 'bg-indigo-50 text-indigo-700',
        amber: 'bg-amber-50 text-amber-700',
    };
    return colorMap[t.color] || 'bg-gray-100 text-gray-600';
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

function formatDate(date) {
    if (!date) return '—';
    const d = new Date(date);
    if (isNaN(d)) return date;
    return d.toLocaleDateString('es-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(date) {
    if (!date) return '—';
    const d = new Date(date);
    if (isNaN(d)) return date;
    return d.toLocaleDateString('es-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function relativeTime(date) {
    if (!date) return '';
    const now = new Date();
    const d = new Date(date);
    const diffMs = now - d;
    const diffMins = Math.floor(diffMs / 60000);
    if (diffMins < 1) return 'ahora';
    if (diffMins < 60) return `hace ${diffMins}m`;
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return `hace ${diffHours}h`;
    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 30) return `hace ${diffDays}d`;
    return formatDate(date);
}

const sortedActivities = computed(() => {
    if (!props.lead.activities) return [];
    return [...props.lead.activities].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
});
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/sales/leads" class="flex items-center gap-1 text-gray-400 hover:text-gray-600 text-sm">
                    <ArrowLeftIcon class="w-4 h-4" /> Leads
                </Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">{{ lead.first_name }} {{ lead.last_name }}</h2>
            </div>
        </template>

        <div class="max-w-6xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                <!-- Left Column -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- Profile Card (Header + Contact + Tags) -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
                        <!-- Header -->
                        <div class="flex items-start justify-between">
                            <div class="flex gap-4">
                                <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0">
                                    <span class="text-lg font-bold text-gray-400">{{ lead.first_name?.[0] }}{{ lead.last_name?.[0] }}</span>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900">{{ lead.first_name }} {{ lead.last_name }}</h3>
                                    <p v-if="lead.company_name" class="text-gray-500 text-sm">{{ lead.company_name }}</p>
                                    <div class="flex items-center gap-2 mt-2">
                                        <span :class="statusBadgeStyle(lead.status)" class="text-xs font-medium rounded-lg px-3 py-1">
                                            {{ statusLabel(lead.status) }}
                                        </span>
                                        <span class="text-xs text-gray-400">Registrado {{ formatDateTime(lead.created_at) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Link :href="`/admin/sales/leads/${lead.id}/edit`"
                                    class="px-4 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors flex items-center gap-1">
                                    <PencilSquareIcon class="w-3.5 h-3.5" /> Editar
                                </Link>
                                <button @click="showDeleteModal = true"
                                    class="px-3 py-1.5 border border-red-200 text-red-600 rounded-lg text-xs font-medium hover:bg-red-50 transition-colors flex items-center gap-1">
                                    <TrashIcon class="w-3.5 h-3.5" /> Eliminar
                                </button>
                            </div>
                        </div>

                        <!-- Contact -->
                        <div class="pt-4 border-t border-gray-100">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                <div v-if="lead.email" class="flex items-center gap-2">
                                    <EnvelopeIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
                                    <a :href="`mailto:${lead.email}`" class="text-blue-600 hover:underline truncate">{{ lead.email }}</a>
                                </div>
                                <div v-if="lead.phone" class="flex items-center gap-2">
                                    <PhoneIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
                                    <a :href="`tel:${lead.phone}`" class="text-blue-600 hover:underline">{{ lead.phone }}</a>
                                </div>
                                <div v-if="lead.country" class="flex items-center gap-2">
                                    <GlobeAltIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
                                    <span class="text-gray-700">{{ lead.country }}</span>
                                </div>
                                <div v-if="lead.instagram" class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-pink-500 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                    </svg>
                                    <a :href="`https://instagram.com/${lead.instagram}`" target="_blank" class="text-pink-600 hover:text-pink-700">@{{ lead.instagram }}</a>
                                </div>
                                <div v-if="lead.website_url" class="flex items-center gap-2 sm:col-span-2">
                                    <GlobeAltIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
                                    <a :href="lead.website_url" target="_blank" class="text-blue-600 hover:underline truncate">{{ lead.website_url }}</a>
                                </div>
                                <div v-if="lead.preferred_contact_time" class="flex items-center gap-2">
                                    <ClockIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
                                    <span class="text-gray-700">Prefiere contacto a las {{ lead.preferred_contact_time }}</span>
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
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div v-if="!editingTags" class="flex flex-wrap items-center gap-1.5">
                                <span v-for="t in lead.tags" :key="t.id"
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
                                    :style="{ backgroundColor: t.color + '20', color: t.color }">
                                    {{ t.name }}
                                </span>
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
                                            :style="{ backgroundColor: (getTagById(id)?.color || '#6B7280') + '20', color: getTagById(id)?.color || '#6B7280' }"
                                            @click="removeTag(id)">
                                            {{ getTagById(id)?.name }}
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </span>
                                        <span v-if="!selectedTagIds.length" class="text-xs text-gray-400 italic py-1">Sin tags</span>
                                    </div>
                                    <div class="relative">
                                        <input v-model="tagSearch" type="text" placeholder="Tag Name"
                                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-black focus:border-black" />
                                        <div v-if="filteredTags.length" class="mt-1 max-h-48 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-sm">
                                            <button v-for="t in filteredTags" :key="t.id" @click="addTag(t.id)"
                                                class="w-full text-left px-3 py-2 hover:bg-gray-50 flex items-center gap-2 transition-colors">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium"
                                                    :style="{ backgroundColor: t.color + '20', color: t.color }">
                                                    {{ t.name }}
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Business Card -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="font-semibold text-gray-800 mb-4">Informacion del Negocio</h4>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div class="flex justify-between sm:flex-col sm:gap-0.5">
                                <dt class="text-gray-500">Empresa</dt>
                                <dd class="font-medium text-gray-900">{{ lead.company_name || '—' }}</dd>
                            </div>
                            <div class="flex justify-between sm:flex-col sm:gap-0.5">
                                <dt class="text-gray-500">Categoria Retail</dt>
                                <dd class="font-medium text-gray-900">{{ lead.retail_category || '—' }}</dd>
                            </div>
                            <div class="flex justify-between sm:flex-col sm:gap-0.5">
                                <dt class="text-gray-500">Disenos listos</dt>
                                <dd class="font-medium text-gray-900">{{ lead.designs_ready ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between sm:flex-col sm:gap-0.5">
                                <dt class="text-gray-500">Presupuesto</dt>
                                <dd class="font-medium text-gray-900">{{ lead.budget || '—' }}</dd>
                            </div>
                            <div class="flex justify-between sm:flex-col sm:gap-0.5">
                                <dt class="text-gray-500">Shows pasados</dt>
                                <dd class="font-medium text-gray-900">{{ lead.past_shows || '—' }}</dd>
                            </div>
                            <div v-if="lead.events?.length" class="flex justify-between sm:flex-col sm:gap-0.5 sm:col-span-2">
                                <dt class="text-gray-500">Eventos de interes</dt>
                                <dd class="font-medium text-gray-900">
                                    <div class="flex flex-wrap gap-2 mt-1">
                                        <span v-for="ev in lead.events" :key="ev.id" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                            {{ ev.name }}
                                            <span v-if="ev.city" class="text-gray-400 ml-1">· {{ ev.city }}</span>
                                        </span>
                                    </div>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Notes Card (CRM style) -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="font-semibold text-gray-800 mb-4">Notas</h4>

                        <!-- New note input -->
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
                                <textarea v-model="noteContent" rows="3" ref="noteTextarea" placeholder="What's this note about?"
                                    class="w-full border-0 px-4 py-3 text-sm text-gray-700 focus:ring-0 placeholder-gray-400 resize-none"></textarea>
                                <!-- Attached file preview -->
                                <div v-if="noteFileName" class="px-4 py-2 bg-blue-50 border-t border-blue-100 flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-xs text-blue-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                        <span class="truncate max-w-48">{{ noteFileName }}</span>
                                    </div>
                                    <button @click="removeFile" class="text-xs text-red-500 hover:text-red-700">&times;</button>
                                </div>
                                <div class="px-4 py-2 bg-gray-50 flex items-center gap-3 border-t border-gray-100">
                                    <button @click="saveNote" :disabled="!noteContent.trim()"
                                        class="px-4 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 disabled:opacity-40 transition-colors">
                                        Save
                                    </button>
                                    <button @click="cancelNote"
                                        class="px-4 py-1.5 border border-gray-200 rounded-lg text-xs font-medium hover:bg-gray-100 transition-colors">
                                        Cancel
                                    </button>
                                    <label class="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700 cursor-pointer transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                        Attach File
                                        <input type="file" ref="noteFileInput" @change="handleFileSelect" class="hidden" />
                                    </label>
                                    <button v-if="!noteShowTitle" @click="noteShowTitle = true"
                                        class="text-xs text-gray-500 hover:text-gray-700 transition-colors">
                                        Add a Title
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Notes timeline -->
                        <div class="space-y-0 divide-y divide-gray-100">
                            <div v-for="note in leadNotes" :key="note.id" class="py-4 first:pt-0">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-bold flex-shrink-0 mt-0.5"
                                        :class="note.user ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'">
                                        {{ note.user ? (note.user.first_name?.[0] || '') + (note.user.last_name?.[0] || '') : 'R7' }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-0.5">
                                            <span class="text-sm font-semibold text-gray-900">{{ note.user ? note.user.first_name + ' ' + note.user.last_name : 'Runway 7 Fashion' }}</span>
                                            <span class="text-xs text-gray-400">{{ formatNoteDate(note.created_at) }}</span>
                                        </div>
                                        <p v-if="note.title && note.title !== 'Nota'" class="text-sm font-semibold text-gray-800 mb-0.5">{{ note.title }}</p>
                                        <p class="text-sm text-gray-600 whitespace-pre-line">{{ note.description }}</p>
                                        <button v-if="note.file_path" @click="openPreview(note)"
                                            class="inline-flex items-center gap-1.5 mt-2 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-600 hover:bg-gray-100 transition-colors cursor-pointer">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                            {{ note.file_name }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <p v-if="!leadNotes.length" class="text-sm text-gray-400 italic py-4">No hay notas aún.</p>
                        </div>
                    </div>

                </div>

                <!-- Right Column -->
                <div class="lg:col-span-4 space-y-6">

                    <!-- Status & Assignment Card -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
                        <h4 class="font-semibold text-gray-800">Estado y Asignacion</h4>

                        <!-- Lead status (global) -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1.5">Estado del lead</label>
                            <select @change="changeStatus($event.target.value)" :value="lead.status"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-black focus:border-black">
                                <option v-for="(info, key) in statuses" :key="key" :value="key">{{ info.label }}</option>
                            </select>
                        </div>

                        <!-- Status per event -->
                        <div v-if="lead.events?.length">
                            <label class="block text-xs font-medium text-gray-500 mb-2">Estado por evento</label>
                            <div class="space-y-2">
                                <div v-for="ev in lead.events" :key="ev.id" class="border border-gray-100 rounded-lg p-3">
                                    <p class="text-xs font-medium text-gray-700 mb-1.5">{{ ev.name }}</p>
                                    <select @change="changeEventStatus(ev.id, $event.target.value)" :value="ev.pivot?.status || 'new'"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-1 focus:ring-black focus:border-black">
                                        <option v-for="(info, key) in statuses" :key="key" :value="key">{{ info.label }}</option>
                                    </select>
                                    <Link v-if="ev.pivot?.status === 'negotiating' && !(lead.converted_designer && lead.converted_designer.id)"
                                        :href="`/admin/sales/designers/create?lead_id=${lead.id}&event_id=${ev.id}`"
                                        class="mt-2 w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-amber-50 text-amber-700 border border-amber-200 rounded-lg text-xs font-medium hover:bg-amber-100 transition-colors">
                                        <ArrowPathIcon class="w-3.5 h-3.5" /> Convertir a Designer
                                    </Link>
                                    <div v-if="ev.pivot?.status === 'converted'" class="mt-2 text-xs text-green-600 font-medium flex items-center gap-1">
                                        <CheckCircleIcon class="w-3.5 h-3.5" /> Venta cerrada
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Assigned to -->
                        <div v-if="isLeader">
                            <label class="block text-xs font-medium text-gray-500 mb-1.5">Asignado a</label>
                            <select @change="reassignAdvisor($event.target.value)" :value="(typeof lead.assigned_to === 'object' && lead.assigned_to) ? lead.assigned_to.id : (lead.assigned_to || '')"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-black focus:border-black">
                                <option value="">Sin asignar</option>
                                <option v-for="advisor in advisors" :key="advisor.id" :value="advisor.id">
                                    {{ advisor.first_name }} {{ advisor.last_name }}
                                </option>
                            </select>
                        </div>
                        <div v-else-if="lead.assigned_to">
                            <label class="block text-xs font-medium text-gray-500 mb-1.5">Asignado a</label>
                            <p class="text-sm text-gray-700 font-medium">{{ lead.assigned_to.first_name }} {{ lead.assigned_to.last_name }}</p>
                        </div>

                        <!-- Last contacted -->
                        <div v-if="lead.last_contacted_at">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Ultimo contacto</label>
                            <p class="text-sm text-gray-700">{{ formatDateTime(lead.last_contacted_at) }}</p>
                        </div>

                        <!-- Converted designer info -->
                        <div v-if="lead.converted_designer && lead.converted_designer.id" class="pt-3 border-t border-gray-100">
                            <p class="text-xs text-gray-500 mb-1">Convertido a Designer</p>
                            <Link :href="`/admin/designers/${lead.converted_designer.id}`"
                                class="inline-flex items-center gap-1.5 text-sm font-medium text-green-700 bg-green-50 px-3 py-1.5 rounded-lg hover:bg-green-100 transition-colors">
                                <CheckCircleIcon class="w-4 h-4" />
                                {{ lead.converted_designer.first_name }} {{ lead.converted_designer.last_name }}
                            </Link>
                        </div>
                    </div>

                    <!-- Add Activity Form -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="font-semibold text-gray-800 mb-4">Nueva Actividad</h4>
                        <form @submit.prevent="submitActivity" class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Tipo</label>
                                <select v-model="activityForm.type"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-black focus:border-black">
                                    <option v-for="(info, key) in activityTypes" :key="key" :value="key">{{ info.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Titulo *</label>
                                <input v-model="activityForm.title" type="text" placeholder="Ej: Llamada de seguimiento"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-black focus:border-black" />
                                <p v-if="activityForm.errors.title" class="text-xs text-red-500 mt-1">{{ activityForm.errors.title }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Descripcion <span class="text-gray-400">(opcional)</span></label>
                                <textarea v-model="activityForm.description" rows="2" placeholder="Detalles..."
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-black focus:border-black"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Programar para <span class="text-gray-400">(opcional)</span></label>
                                <input v-model="activityForm.scheduled_at" type="datetime-local"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-black focus:border-black" />
                            </div>
                            <button type="submit" :disabled="activityForm.processing || !activityForm.title"
                                class="w-full px-4 py-2.5 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors disabled:opacity-40">
                                Registrar Actividad
                            </button>
                        </form>
                    </div>

                    <!-- Activity Timeline -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="font-semibold text-gray-800 mb-4">Historial de Actividades</h4>

                        <div v-if="sortedActivities.length" class="space-y-4">
                            <div v-for="activity in sortedActivities" :key="activity.id" class="relative pl-7">
                                <!-- Timeline line -->
                                <div class="absolute left-2.5 top-6 bottom-0 w-px bg-gray-100"></div>

                                <!-- Icon -->
                                <div class="absolute left-0 top-0.5 w-5 h-5 rounded-full flex items-center justify-center"
                                    :class="activityTypeColor(activity.type)">
                                    <component :is="activityIcon(activity.type)" class="w-3 h-3" />
                                </div>

                                <!-- Content -->
                                <div class="pb-4">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span :class="activityTypeColor(activity.type)" class="text-[10px] font-medium px-1.5 py-0.5 rounded">
                                                    {{ activityTypeLabel(activity.type) }}
                                                </span>
                                                <span class="text-sm font-medium text-gray-900 truncate">{{ activity.title }}</span>
                                            </div>
                                            <p v-if="activity.description" class="text-xs text-gray-500 mt-1 whitespace-pre-line">{{ activity.description }}</p>
                                        </div>

                                        <!-- Complete button for scheduled pending -->
                                        <button v-if="activity.scheduled_at && activity.status === 'pending'"
                                            @click="completeActivity(activity.id)"
                                            class="flex-shrink-0 inline-flex items-center gap-1 px-2 py-1 text-[10px] font-medium bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors cursor-pointer">
                                            <CheckCircleIcon class="w-3 h-3" /> Completar
                                        </button>
                                        <span v-else-if="activity.status === 'completed'" class="flex-shrink-0 text-[10px] text-green-600 font-medium">
                                            Completada
                                        </span>
                                    </div>

                                    <!-- Meta -->
                                    <div class="flex items-center gap-2 mt-1.5 text-[11px] text-gray-400">
                                        <span v-if="activity.user">{{ activity.user.first_name }} {{ activity.user.last_name }}</span>
                                        <span>{{ relativeTime(activity.created_at) }}</span>
                                        <span v-if="activity.scheduled_at" class="flex items-center gap-0.5">
                                            <ClockIcon class="w-3 h-3" />
                                            {{ formatDateTime(activity.scheduled_at) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p v-else class="text-sm text-gray-400 italic text-center py-4">Sin actividades registradas.</p>
                    </div>

                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <Teleport to="body">
            <!-- Document Preview Modal -->
            <div v-if="previewDoc" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/70" @click="previewDoc = null"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl h-[85vh] mx-4 flex flex-col overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 flex-shrink-0">
                        <div class="flex items-center gap-2 min-w-0">
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            <span class="text-sm font-medium text-gray-900 truncate">{{ previewDoc.name }}</span>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <a :href="previewDoc.url" download
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors">
                                Descargar
                            </a>
                            <button @click="previewDoc = null" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors text-lg leading-none">&times;</button>
                        </div>
                    </div>
                    <!-- Content -->
                    <div class="flex-1 bg-gray-100 overflow-auto flex items-center justify-center">
                        <img v-if="previewDoc.isImage" :src="previewDoc.url" :alt="previewDoc.name" class="max-w-full max-h-full object-contain" />
                        <iframe v-else :src="previewDoc.viewerUrl" class="w-full h-full border-0"></iframe>
                    </div>
                </div>
            </div>

            <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showDeleteModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <TrashIcon class="w-6 h-6 text-red-500" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2 text-center">Eliminar a {{ lead.first_name }} {{ lead.last_name }}?</h3>
                    <div class="bg-red-50 border border-red-100 rounded-lg p-3 mb-4 text-sm text-gray-700">
                        <p class="font-medium text-red-700 mb-2">Se eliminara permanentemente:</p>
                        <ul class="space-y-1 text-xs text-gray-600">
                            <li>- Prospecto: <span class="font-medium">{{ lead.first_name }} {{ lead.last_name }}</span> ({{ lead.company_name }})</li>
                            <li>- {{ lead.activities?.length || 0 }} actividades registradas (llamadas, emails, reuniones, notas)</li>
                            <li v-if="lead.activities?.filter(a => a.status === 'pending').length">- <span class="text-red-600 font-medium">{{ lead.activities.filter(a => a.status === 'pending').length }} actividades pendientes en el calendario</span></li>
                            <li>- Mensajes del bot relacionados</li>
                        </ul>
                        <p v-if="lead.converted_designer" class="mt-2 text-xs text-green-700 font-medium">El diseñador convertido NO sera afectado.</p>
                    </div>
                    <div class="flex gap-3">
                        <button @click="showDeleteModal = false" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancelar</button>
                        <button @click="deleteLead" class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Eliminar</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
