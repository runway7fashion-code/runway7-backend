<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    PencilSquareIcon, TrashIcon, BuildingOffice2Icon, EnvelopeIcon, PhoneIcon,
    LinkIcon, GlobeAltIcon, StarIcon, ArrowDownTrayIcon, XMarkIcon, PlusIcon,
    CheckCircleIcon, ClockIcon, NoSymbolIcon, XCircleIcon, DocumentTextIcon,
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

// Status change
const statusForm = useForm({ status: props.lead.status });
function updateStatus() {
    statusForm.patch(`/admin/sponsorship/leads/${props.lead.id}/status`, { preserveScroll: true });
}

// Assignment (lider)
const assignForm = useForm({ assigned_to_user_id: props.lead.assigned_to_user_id });
function updateAssignment() {
    assignForm.patch(`/admin/sponsorship/leads/${props.lead.id}/assign`, { preserveScroll: true });
}

// Tags
const tagsForm = useForm({ tag_ids: (props.lead.tags || []).map(t => t.id) });
function toggleTag(id) {
    const i = tagsForm.tag_ids.indexOf(id);
    if (i >= 0) tagsForm.tag_ids.splice(i, 1);
    else tagsForm.tag_ids.push(id);
}
function saveTags() {
    tagsForm.patch(`/admin/sponsorship/leads/${props.lead.id}/tags`, { preserveScroll: true });
}

// Event management
const addEventForm = useForm({ event_id: '' });
function addEvent() {
    if (!addEventForm.event_id) return;
    addEventForm.post(`/admin/sponsorship/leads/${props.lead.id}/add-event`, {
        preserveScroll: true,
        onSuccess: () => addEventForm.reset(),
    });
}
function removeEvent(eventId) {
    useForm({ event_id: eventId }).delete(`/admin/sponsorship/leads/${props.lead.id}/remove-event`, { preserveScroll: true });
}

const availableEvents = computed(() => {
    const currentIds = (props.lead.events || []).map(e => e.id);
    return props.events.filter(e => !currentIds.includes(e.id));
});

// Documents
const showUploadModal = ref(false);
const uploadForm = useForm({ file: null, note: '' });
function uploadDocument() {
    if (!uploadForm.file) return;
    uploadForm.post(`/admin/sponsorship/leads/${props.lead.id}/documents`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { showUploadModal.value = false; uploadForm.reset(); },
    });
}
function deleteDocument(doc) {
    if (!confirm(`Delete "${doc.original_name}"?`)) return;
    useForm({}).delete(`/admin/sponsorship/lead-documents/${doc.id}`, { preserveScroll: true });
}

// Delete lead
const showDeleteModal = ref(false);
function deleteLead() {
    useForm({}).delete(`/admin/sponsorship/leads/${props.lead.id}`);
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

// ─── Activities ─────────────────────────────────────────
const showActivityModal = ref(false);
const activityForm = useForm({
    type: 'call',
    title: '',
    description: '',
    scheduled_at: '',
    assigned_to_user_id: null,
    is_contract: false,
});

function submitActivity() {
    activityForm.post(`/admin/sponsorship/leads/${props.lead.id}/activities`, {
        preserveScroll: true,
        onSuccess: () => {
            showActivityModal.value = false;
            activityForm.reset();
            activityForm.type = 'call';
        },
    });
}

function completeActivity(id) {
    useForm({}).patch(`/admin/sponsorship/activities/${id}/complete`, { preserveScroll: true });
}
function cancelActivity(id) {
    useForm({}).patch(`/admin/sponsorship/activities/${id}/cancel`, { preserveScroll: true });
}
function notCompletedActivity(id) {
    useForm({}).patch(`/admin/sponsorship/activities/${id}/not-completed`, { preserveScroll: true });
}
function deleteActivity(id) {
    if (!confirm('Delete this activity?')) return;
    useForm({}).delete(`/admin/sponsorship/activities/${id}`, { preserveScroll: true });
}

const activityStatusColors = {
    pending:       'bg-yellow-100 text-yellow-700',
    completed:     'bg-green-100 text-green-700',
    cancelled:     'bg-gray-100 text-gray-500',
    not_completed: 'bg-red-100 text-red-700',
};

// ─── Send email ─────────────────────────────────────────
const showEmailModal = ref(false);
const emailForm = useForm({
    subject: '',
    body: '',
    is_contract: false,
    attachments: [],
});
function addAttachment(e) {
    const files = Array.from(e.target.files || []);
    emailForm.attachments = [...emailForm.attachments, ...files];
    e.target.value = '';
}
function removeAttachment(i) {
    emailForm.attachments.splice(i, 1);
}
function submitEmail() {
    emailForm.post(`/admin/sponsorship/leads/${props.lead.id}/send-email`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showEmailModal.value = false;
            emailForm.reset();
        },
    });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center space-x-2 text-sm">
                <Link href="/admin/sponsorship/leads" class="text-gray-400 hover:text-gray-600">Leads</Link>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-medium">{{ lead.first_name }} {{ lead.last_name }}</span>
            </div>
        </template>

        <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main column -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Header -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <StarSolid v-if="lead.is_contract_winner" class="w-6 h-6 text-[#D4AF37]" title="Contract winner" />
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900">{{ lead.first_name }} {{ lead.last_name }}</h3>
                                <p v-if="lead.charge" class="text-sm text-gray-500">{{ lead.charge }}</p>
                            </div>
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <button @click="showEmailModal = true"
                                class="px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 flex items-center gap-1.5">
                                <EnvelopeIcon class="w-4 h-4" /> Send email
                            </button>
                            <Link v-if="!lead.converted_user_id" :href="`/admin/sponsorship/leads/${lead.id}/convert`"
                                class="px-3 py-2 text-sm font-semibold text-white bg-[#D4AF37] rounded-lg hover:bg-yellow-600 flex items-center gap-1.5">
                                <StarIcon class="w-4 h-4" /> Close contract & Convert
                            </Link>
                            <Link :href="`/admin/sponsorship/leads/${lead.id}/edit`"
                                class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 flex items-center gap-1.5">
                                <PencilSquareIcon class="w-4 h-4" /> Edit
                            </Link>
                            <button v-if="isLider" @click="showDeleteModal = true"
                                class="px-3 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 flex items-center gap-1.5">
                                <TrashIcon class="w-4 h-4" /> Delete
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 pt-5 border-t border-gray-100 grid grid-cols-2 gap-3 text-sm">
                        <div class="flex items-start gap-2">
                            <BuildingOffice2Icon class="w-4 h-4 text-gray-400 mt-0.5" />
                            <div>
                                <p class="text-xs text-gray-400">Company</p>
                                <p class="font-medium text-gray-900">{{ lead.company?.name }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <PhoneIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                            <div>
                                <p class="text-xs text-gray-400">Phone</p>
                                <p class="font-medium text-gray-900">{{ lead.phone || '—' }}</p>
                            </div>
                        </div>
                        <div v-if="lead.linkedin_url" class="flex items-start gap-2">
                            <LinkIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                            <div>
                                <p class="text-xs text-gray-400">LinkedIn</p>
                                <a :href="lead.linkedin_url" target="_blank" class="font-medium text-blue-600 hover:underline truncate block">{{ lead.linkedin_url }}</a>
                            </div>
                        </div>
                        <div v-if="lead.website_url" class="flex items-start gap-2">
                            <GlobeAltIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                            <div>
                                <p class="text-xs text-gray-400">Website</p>
                                <a :href="lead.website_url" target="_blank" class="font-medium text-blue-600 hover:underline truncate block">{{ lead.website_url }}</a>
                            </div>
                        </div>
                        <div v-if="lead.instagram" class="flex items-start gap-2">
                            <span class="w-4 h-4 text-gray-400 text-xs font-bold mt-0.5">IG</span>
                            <div>
                                <p class="text-xs text-gray-400">Instagram</p>
                                <p class="font-medium text-gray-900">{{ lead.instagram }}</p>
                            </div>
                        </div>
                        <div v-if="lead.category" class="flex items-start gap-2">
                            <span class="w-4 h-4 rounded bg-gray-200 mt-0.5"></span>
                            <div>
                                <p class="text-xs text-gray-400">Category</p>
                                <p class="font-medium text-gray-900">{{ lead.category.name }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Emails -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="font-semibold text-gray-900 mb-4">Emails</h4>
                    <div class="space-y-2">
                        <div v-for="em in lead.emails" :key="em.id" class="flex items-center gap-3 px-3 py-2 bg-gray-50 rounded-lg">
                            <EnvelopeIcon class="w-4 h-4 text-gray-400" />
                            <span class="text-sm text-gray-900">{{ em.email }}</span>
                            <span v-if="em.is_primary" class="ml-auto text-xs px-2 py-0.5 bg-[#D4AF37] text-white rounded">Primary</span>
                        </div>
                    </div>
                </div>

                <!-- Events -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="font-semibold text-gray-900 mb-4">Events ({{ lead.events?.length || 0 }})</h4>
                    <div class="space-y-2 mb-4">
                        <div v-for="e in lead.events" :key="e.id" class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-lg">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ e.name }}</p>
                                <p v-if="e.start_date" class="text-xs text-gray-500">{{ formatDate(e.start_date) }}</p>
                            </div>
                            <button @click="removeEvent(e.id)" class="text-red-500 hover:text-red-700">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                    <div v-if="availableEvents.length" class="flex gap-2">
                        <select v-model="addEventForm.event_id" class="input-sm flex-1">
                            <option value="">Add event...</option>
                            <option v-for="e in availableEvents" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                        <button @click="addEvent" :disabled="!addEventForm.event_id"
                            class="px-3 py-2 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 disabled:opacity-40 flex items-center gap-1">
                            <PlusIcon class="w-4 h-4" /> Add
                        </button>
                    </div>
                </div>

                <!-- Documents -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-semibold text-gray-900">Documents ({{ lead.documents?.length || 0 }})</h4>
                        <button @click="showUploadModal = true"
                            class="px-3 py-1.5 text-xs font-medium text-white bg-black rounded-lg hover:bg-gray-800 flex items-center gap-1">
                            <PlusIcon class="w-4 h-4" /> Upload
                        </button>
                    </div>
                    <div v-if="lead.documents?.length" class="space-y-2">
                        <div v-for="d in lead.documents" :key="d.id"
                            class="flex items-center justify-between px-3 py-2 border border-gray-100 rounded-lg">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ d.original_name }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ formatSize(d.size) }} ·
                                    {{ d.uploader ? `${d.uploader.first_name} ${d.uploader.last_name}` : 'Unknown' }} ·
                                    {{ formatDate(d.created_at) }}
                                </p>
                                <p v-if="d.note" class="text-xs text-gray-400 italic">{{ d.note }}</p>
                            </div>
                            <div class="flex gap-1">
                                <a :href="`/storage/${d.path}`" target="_blank" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600">
                                    <ArrowDownTrayIcon class="w-4 h-4" />
                                </a>
                                <button @click="deleteDocument(d)" class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500">
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">No documents uploaded.</p>
                </div>

                <!-- Notes -->
                <div v-if="lead.notes" class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="font-semibold text-gray-900 mb-2">Notes</h4>
                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ lead.notes }}</p>
                </div>

                <!-- Activities / Timeline -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-semibold text-gray-900">Timeline ({{ lead.activities?.length || 0 }})</h4>
                        <button @click="showActivityModal = true"
                            class="px-3 py-1.5 text-xs font-medium text-white bg-black rounded-lg hover:bg-gray-800 flex items-center gap-1">
                            <PlusIcon class="w-4 h-4" /> Add activity
                        </button>
                    </div>
                    <div v-if="lead.activities?.length" class="space-y-3">
                        <div v-for="a in lead.activities" :key="a.id"
                            class="flex gap-3 border-l-4 pl-4 py-2"
                            :style="{ borderColor: activityTypes[a.type]?.color || '#ccc' }">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-xs px-1.5 py-0.5 rounded text-white"
                                        :style="{ backgroundColor: activityTypes[a.type]?.color }">
                                        {{ activityTypes[a.type]?.label || a.type }}
                                    </span>
                                    <span class="text-xs px-1.5 py-0.5 rounded"
                                        :class="activityStatusColors[a.status]">
                                        {{ a.status.replace('_', ' ') }}
                                    </span>
                                    <span v-if="a.is_contract"
                                        class="text-xs px-1.5 py-0.5 rounded bg-[#D4AF37] text-white">Contract</span>
                                </div>
                                <p class="text-sm font-medium text-gray-900 mt-1">{{ a.title }}</p>
                                <p v-if="a.description" class="text-xs text-gray-600 mt-1 whitespace-pre-wrap">{{ a.description }}</p>
                                <div class="text-xs text-gray-500 mt-1 space-x-2">
                                    <span v-if="a.scheduled_at">⏰ {{ formatDate(a.scheduled_at) }}</span>
                                    <span v-if="a.completed_at">✓ {{ formatDate(a.completed_at) }}</span>
                                    <span v-if="a.assigned_to">→ {{ a.assigned_to.first_name }} {{ a.assigned_to.last_name }}</span>
                                    <span v-if="a.creator">by {{ a.creator.first_name }} {{ a.creator.last_name }}</span>
                                </div>
                            </div>
                            <div class="flex gap-1 items-start">
                                <button v-if="a.status === 'pending'" @click="completeActivity(a.id)"
                                    title="Complete" class="p-1.5 rounded-lg hover:bg-green-50 text-gray-400 hover:text-green-600">
                                    <CheckCircleIcon class="w-5 h-5" />
                                </button>
                                <button v-if="a.status === 'pending'" @click="notCompletedActivity(a.id)"
                                    title="Not completed" class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500">
                                    <XCircleIcon class="w-5 h-5" />
                                </button>
                                <button v-if="a.status === 'pending'" @click="cancelActivity(a.id)"
                                    title="Cancel" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600">
                                    <NoSymbolIcon class="w-5 h-5" />
                                </button>
                                <button @click="deleteActivity(a.id)"
                                    title="Delete" class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500">
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">No activities yet.</p>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Status -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <label class="block text-xs uppercase tracking-wider text-gray-400 mb-2">Status</label>
                    <select v-model="statusForm.status" @change="updateStatus" class="input-sm w-full">
                        <option v-for="(meta, key) in statuses" :key="key" :value="key">{{ meta.label }}</option>
                    </select>
                    <div class="mt-3 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium text-white"
                        :style="{ backgroundColor: statuses[lead.status]?.color }">
                        {{ statuses[lead.status]?.label }}
                    </div>
                </div>

                <!-- Assignment -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <label class="block text-xs uppercase tracking-wider text-gray-400 mb-2">Assigned to</label>
                    <select v-if="isLider" v-model="assignForm.assigned_to_user_id" @change="updateAssignment" class="input-sm w-full">
                        <option :value="null">— Unassigned</option>
                        <option v-for="a in advisors" :key="a.id" :value="a.id">
                            {{ a.first_name }} {{ a.last_name }} {{ a.sponsorship_type === 'lider' ? '(L)' : '' }}
                        </option>
                    </select>
                    <p v-else class="text-sm font-medium text-gray-900">
                        {{ lead.assigned_to ? `${lead.assigned_to.first_name} ${lead.assigned_to.last_name}` : 'Unassigned' }}
                    </p>
                </div>

                <!-- Tags -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <label class="block text-xs uppercase tracking-wider text-gray-400 mb-3">Tags</label>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <button v-for="t in tags" :key="t.id" type="button" @click="toggleTag(t.id)"
                            class="px-2 py-0.5 text-xs rounded-full border transition-all"
                            :style="tagsForm.tag_ids.includes(t.id) ? { backgroundColor: t.color, color: 'white', borderColor: t.color } : {}"
                            :class="tagsForm.tag_ids.includes(t.id) ? '' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'">
                            {{ t.name }}
                        </button>
                    </div>
                    <button @click="saveTags" class="text-xs text-blue-600 hover:underline">Save tags</button>
                </div>

                <!-- Meta -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-2 text-xs">
                    <div>
                        <span class="text-gray-400">Source: </span>
                        <span class="font-medium text-gray-700">{{ lead.source }}<span v-if="lead.source_detail"> ({{ lead.source_detail }})</span></span>
                    </div>
                    <div>
                        <span class="text-gray-400">Registered by: </span>
                        <span class="font-medium text-gray-700">
                            {{ lead.registered_by ? `${lead.registered_by.first_name} ${lead.registered_by.last_name}` : '—' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-400">Created: </span>
                        <span class="font-medium text-gray-700">{{ formatDate(lead.created_at) }}</span>
                    </div>
                    <div v-if="lead.last_contacted_at">
                        <span class="text-gray-400">Last contacted: </span>
                        <span class="font-medium text-gray-700">{{ formatDate(lead.last_contacted_at) }}</span>
                    </div>
                    <div v-if="lead.last_email_sent_at">
                        <span class="text-gray-400">Last email: </span>
                        <span class="font-medium text-gray-700">{{ formatDate(lead.last_email_sent_at) }}</span>
                        <span class="ml-1 px-1.5 py-0.5 rounded text-xs"
                            :class="lead.last_email_status === 'sent' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                            {{ lead.last_email_status }}
                        </span>
                    </div>
                    <div v-if="lead.converted_user">
                        <span class="text-gray-400">Converted to sponsor: </span>
                        <span class="font-medium text-gray-700">{{ lead.converted_user.first_name }} {{ lead.converted_user.last_name }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload modal -->
        <Teleport to="body">
            <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showUploadModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Upload document</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">File *</label>
                            <input type="file" @change="e => uploadForm.file = e.target.files[0]" class="w-full text-sm" />
                            <p v-if="uploadForm.errors.file" class="text-xs text-red-500 mt-1">{{ uploadForm.errors.file }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                            <input v-model="uploadForm.note" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <button @click="showUploadModal = false" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancel</button>
                            <button @click="uploadDocument" :disabled="uploadForm.processing || !uploadForm.file"
                                class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40">
                                Upload
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete modal -->
            <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showDeleteModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <TrashIcon class="w-6 h-6 text-red-500" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Delete lead?</h3>
                    <p class="text-sm text-gray-500 mb-5">All emails, events, tags and documents will be deleted. This cannot be undone.</p>
                    <div class="flex gap-3">
                        <button @click="showDeleteModal = false" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancel</button>
                        <button @click="deleteLead" class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Delete</button>
                    </div>
                </div>
            </div>

            <!-- Send email modal -->
            <div v-if="showEmailModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showEmailModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-auto">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Send email to {{ lead.first_name }} {{ lead.last_name }}</h3>
                    <p class="text-xs text-gray-500 mb-4">Will be sent from your email. Creates a completed activity in the timeline.</p>
                    <div class="space-y-3">
                        <div>
                            <label class="text-xs font-medium text-gray-600">Subject *</label>
                            <input v-model="emailForm.subject" type="text" class="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            <p v-if="emailForm.errors.subject" class="text-xs text-red-500 mt-1">{{ emailForm.errors.subject }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-600">Body *</label>
                            <textarea v-model="emailForm.body" rows="8" class="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none"></textarea>
                            <p v-if="emailForm.errors.body" class="text-xs text-red-500 mt-1">{{ emailForm.errors.body }}</p>
                        </div>
                        <div>
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 border border-dashed border-gray-300 rounded-lg text-xs cursor-pointer hover:bg-gray-50">
                                <PlusIcon class="w-4 h-4" /> Attach files
                                <input type="file" multiple @change="addAttachment" class="hidden" />
                            </label>
                            <div v-if="emailForm.attachments.length" class="mt-2 space-y-1">
                                <div v-for="(f, i) in emailForm.attachments" :key="i" class="flex items-center justify-between text-xs bg-gray-50 rounded px-2 py-1">
                                    <span class="truncate">{{ f.name }}</span>
                                    <button type="button" @click="removeAttachment(i)" class="text-red-500">
                                        <XMarkIcon class="w-3 h-3" />
                                    </button>
                                </div>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-sm bg-yellow-50 border border-[#D4AF37] rounded-lg px-3 py-2">
                            <input v-model="emailForm.is_contract" type="checkbox" class="rounded" />
                            <span>This is the contract email. Lead status will switch to <strong>Contrato</strong>.</span>
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="showEmailModal = false"
                                class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancel</button>
                            <button type="button" @click="submitEmail" :disabled="emailForm.processing || !emailForm.subject || !emailForm.body"
                                class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40">
                                {{ emailForm.processing ? 'Sending...' : 'Send email' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add activity modal -->
            <div v-if="showActivityModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showActivityModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">New activity</h3>
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Type *</label>
                                <select v-model="activityForm.type" class="input-sm w-full">
                                    <option v-for="(meta, key) in activityTypes" :key="key" :value="key">{{ meta.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Scheduled at</label>
                                <input v-model="activityForm.scheduled_at" type="datetime-local" class="input-sm w-full" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Title *</label>
                            <input v-model="activityForm.title" type="text" class="input-sm w-full" />
                            <p v-if="activityForm.errors.title" class="text-xs text-red-500 mt-1">{{ activityForm.errors.title }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                            <textarea v-model="activityForm.description" rows="3" class="input-sm w-full resize-none"></textarea>
                        </div>

                        <div v-if="activityForm.type === 'call' || activityForm.type === 'meeting'">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Assign to</label>
                            <select v-model="activityForm.assigned_to_user_id" class="input-sm w-full">
                                <option :value="null">— Me</option>
                                <option v-for="a in advisors" :key="a.id" :value="a.id">
                                    {{ a.first_name }} {{ a.last_name }} {{ a.sponsorship_type === 'lider' ? '(L)' : '' }}
                                </option>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">The assigned person will see this activity on their calendar.</p>
                        </div>

                        <label v-if="activityForm.type === 'email'" class="flex items-center gap-2 text-sm bg-yellow-50 border border-[#D4AF37] rounded-lg px-3 py-2">
                            <input v-model="activityForm.is_contract" type="checkbox" class="rounded" />
                            <span>This email is the contract. When marked completed, the lead status will change to <strong>Contrato</strong> automatically.</span>
                        </label>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="showActivityModal = false"
                                class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancel</button>
                            <button type="button" @click="submitActivity" :disabled="activityForm.processing || !activityForm.title"
                                class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40">
                                Save
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<style scoped>
@reference "tailwindcss";
.input-sm { @apply border border-gray-300 rounded-lg px-2.5 py-2 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-black/10; }
</style>
