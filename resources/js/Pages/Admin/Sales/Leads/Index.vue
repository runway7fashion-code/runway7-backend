<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import EmailComposer from '@/Components/EmailComposer.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { EyeIcon, PencilSquareIcon, ArrowDownTrayIcon, ArrowUpTrayIcon, XMarkIcon, ArrowTopRightOnSquareIcon, EnvelopeIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    leads: Object,
    stats: Object,
    opportunityStats: Object,
    statuses: Object,
    opportunityStatuses: Object,
    sources: Object,
    advisors: Array,
    events: Array,
    allTags: Array,
    filters: Object,
    isLeader: Boolean,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const event = ref(props.filters?.event || '');
const assignedTo = ref(props.filters?.assigned_to || '');
const budget = ref(props.filters?.budget || '');
const tag = ref(props.filters?.tag || '');
const oppStatus = ref(props.filters?.opp_status || '');
const source = ref(props.filters?.source || '');
const perPage = ref(props.filters?.per_page || '20');
const isAvailable = ref(null);

// Debounced search
let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});

// Immediate filters
watch([status, event, assignedTo, budget, tag, oppStatus, source, perPage], () => applyFilters());

function applyFilters() {
    router.get('/admin/sales/leads', {
        search: search.value || undefined,
        status: status.value || undefined,
        event: event.value || undefined,
        assigned_to: assignedTo.value || undefined,
        budget: budget.value || undefined,
        tag: tag.value || undefined,
        opp_status: oppStatus.value || undefined,
        source: source.value || undefined,
        per_page: perPage.value !== '20' ? perPage.value : undefined,
    }, { preserveState: true, replace: true });
}

function exportCsv() {
    const params = new URLSearchParams();
    if (search.value) params.set('search', search.value);
    if (status.value) params.set('status', status.value);
    if (source.value) params.set('source', source.value);
    if (assignedTo.value) params.set('assigned_to', assignedTo.value);
    window.location.href = `/admin/sales/leads/export?${params.toString()}`;
}

// Email selection & modal
const selectedLeads = ref([]);
const showEmailModal = ref(false);
const emailProcessing = ref(false);

const allSelected = computed({
    get: () => props.leads.data.length > 0 && selectedLeads.value.length === props.leads.data.length,
    set: (val) => { selectedLeads.value = val ? props.leads.data.map(l => l.id) : []; },
});

function openBulkEmail() {
    if (selectedLeads.value.length === 0) return;
    showEmailModal.value = true;
}

function handleBulkEmailSend({ subject, body, attachments, scheduled_at }) {
    const formData = new FormData();
    formData.append('subject', subject);
    formData.append('body', body);
    if (scheduled_at) formData.append('scheduled_at', scheduled_at);
    selectedLeads.value.forEach(id => formData.append('lead_ids[]', id));
    attachments.forEach(file => formData.append('attachments[]', file));

    emailProcessing.value = true;
    router.post('/admin/sales/leads/send-bulk-email', formData, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { showEmailModal.value = false; selectedLeads.value = []; },
        onFinish: () => { emailProcessing.value = false; },
    });
}

// Redirect to Operations modal
const showRedirectModal = ref(false);
const redirectLead = ref(null);
const redirectForm = useForm({ redirect_type: 'model', redirect_note: '' });

function openRedirectModal(lead) {
    redirectLead.value = lead;
    redirectForm.redirect_type = 'model';
    redirectForm.redirect_note = '';
    showRedirectModal.value = true;
}

function submitRedirect() {
    redirectForm.patch(`/admin/sales/leads/${redirectLead.value.id}/redirect`, {
        preserveScroll: true,
        onSuccess: () => { showRedirectModal.value = false; redirectLead.value = null; },
    });
}

// Import modal
const showImportModal = ref(false);
const importForm = useForm({ file: null, event_id: '', assigned_to: '', source: '' });

function handleImportFileChange(e) {
    importForm.file = e.target.files[0] ?? null;
}

function submitImport() {
    importForm.post('/admin/sales/leads/import', {
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset();
        },
    });
}

// Inline status change
function changeStatus(lead, newStatus) {
    router.patch(`/admin/sales/leads/${lead.id}/status`, { status: newStatus }, { preserveScroll: true });
}

// Inline advisor assignment (leader only)
function changeAdvisor(lead, advisorId) {
    router.patch(`/admin/sales/leads/${lead.id}/assign`, { assigned_to: advisorId }, { preserveScroll: true });
}

// Toggle availability for sales users
function toggleAvailability() {
    router.post('/admin/sales/toggle-availability', {}, { preserveScroll: true });
}

// Events modal
const eventsModalLead = ref(null);
const showStatusInfo = ref(false);
const tagsModalLead = ref(null);

function openEventsModal(lead) {
    eventsModalLead.value = lead;
}

function changeEventStatus(lead, eventId, newStatus) {
    router.patch(`/admin/sales/leads/${lead.id}/event-status`, { event_id: eventId, status: newStatus }, {
        preserveScroll: true,
        onSuccess: () => { eventsModalLead.value = null; },
    });
}

// Status dropdown management
const openStatusDropdown = ref(null);
const openAdvisorDropdown = ref(null);

function toggleStatusDropdown(leadId) {
    openStatusDropdown.value = openStatusDropdown.value === leadId ? null : leadId;
    openAdvisorDropdown.value = null;
}

function toggleAdvisorDropdown(leadId) {
    openAdvisorDropdown.value = openAdvisorDropdown.value === leadId ? null : leadId;
    openStatusDropdown.value = null;
}

function closeDropdowns() {
    openStatusDropdown.value = null;
    openAdvisorDropdown.value = null;
}

// Relative time
function timeAgo(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)} min ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return date.toLocaleDateString('en-US');
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US') + '\n' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

// Stats cards config
// Lead stats (marketing)
const leadCards = computed(() => {
    const cards = [
        { key: 'total', label: 'Total', value: props.stats?.total ?? 0, color: '#6B7280' },
        { key: 'new', label: 'New', value: props.stats?.new ?? 0, color: props.statuses?.new?.color ?? '#3B82F6' },
        { key: 'qualified', label: 'Qualified', value: props.stats?.qualified ?? 0, color: props.statuses?.qualified?.color ?? '#8B5CF6' },
        { key: 'client', label: 'Clients', value: props.stats?.client ?? 0, color: props.statuses?.client?.color ?? '#10B981' },
        { key: 'lost', label: 'Lost', value: props.stats?.lost ?? 0, color: props.statuses?.lost?.color ?? '#EF4444' },
        { key: 'spam', label: 'Spam', value: props.stats?.spam ?? 0, color: props.statuses?.spam?.color ?? '#1F2937' },
    ];
    if (props.isLeader) {
        cards.push({ key: 'unassigned', label: 'Unassigned', value: props.stats?.unassigned ?? 0, color: '#9CA3AF' });
    }
    cards.push({ key: 'redirected', label: 'Redirected', value: props.stats?.redirected ?? 0, color: '#F59E0B' });
    return cards;
});

// Opportunity stats (ventas)
const oppCards = computed(() => [
    { key: 'opp_total', label: 'Total', value: props.opportunityStats?.opp_total ?? 0, color: '#6B7280' },
    { key: 'opp_negotiating', label: 'Negotiating', value: props.opportunityStats?.opp_negotiating ?? 0, color: '#8B5CF6' },
    { key: 'opp_converted', label: 'Sales', value: props.opportunityStats?.opp_converted ?? 0, color: '#10B981' },
    { key: 'opp_follow_up', label: 'Follow Up', value: props.opportunityStats?.opp_follow_up ?? 0, color: '#F97316' },
    { key: 'opp_contacted', label: 'Contacted', value: props.opportunityStats?.opp_contacted ?? 0, color: '#EAB308' },
    { key: 'opp_lost', label: 'Lost', value: props.opportunityStats?.opp_lost ?? 0, color: '#EF4444' },
]);

function advisorName(lead) {
    if (!lead.assigned_to || typeof lead.assigned_to !== 'object') return null;
    return `${lead.assigned_to.first_name} ${lead.assigned_to.last_name}`;
}

// Auto-refresh when new lead notification arrives
function onNotification(e) {
    if (e.detail?.data?.type === 'new_designer_lead') {
        router.reload({ only: ['leads', 'stats', 'opportunityStats'], preserveScroll: true });
    }
}
onMounted(() => window.addEventListener('notification:received', onNotification));
onUnmounted(() => window.removeEventListener('notification:received', onNotification));
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-semibold text-gray-900">Prospects</h2>
                <button @click="showStatusInfo = true" class="w-5 h-5 rounded-full border border-gray-300 text-gray-400 flex items-center justify-center hover:bg-gray-100 hover:text-gray-600 transition-colors text-[10px] font-bold">?</button>
            </div>
        </template>

        <div @click="closeDropdowns">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Prospects</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ leads.total }} registered prospects</p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Availability toggle for sales users -->
                    <div v-if="!isLeader" class="flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-lg bg-white">
                        <span class="text-sm text-gray-600">Available for leads</span>
                        <button @click.stop="toggleAvailability"
                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                            :class="$page.props.auth?.user?.is_available ? 'bg-green-500' : 'bg-gray-300'"
                            role="switch"
                            :aria-checked="$page.props.auth?.user?.is_available">
                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                :class="$page.props.auth?.user?.is_available ? 'translate-x-5' : 'translate-x-0'" />
                        </button>
                    </div>

                    <button @click.stop="exportCsv" class="inline-flex items-center gap-1.5 px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        <ArrowDownTrayIcon class="h-4 w-4" /> Export
                    </button>
                    <button @click.stop="showImportModal = true" class="inline-flex items-center gap-1.5 px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        <ArrowUpTrayIcon class="h-4 w-4" /> Import
                    </button>
                    <Link href="/admin/sales/leads/create"
                        class="px-4 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors">
                        + New Prospect
                    </Link>
                </div>
            </div>

            <!-- Lead Stats (Marketing) -->
            <div class="mb-2">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Leads</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
                    <div v-for="card in leadCards" :key="card.key"
                        class="bg-white rounded-lg border border-gray-200 px-4 py-3">
                        <div class="flex items-center justify-between">
                            <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wide">{{ card.label }}</p>
                            <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: card.color }"></span>
                        </div>
                        <p class="text-xl font-bold mt-1" :style="{ color: card.color }">{{ card.value }}</p>
                    </div>
                </div>
            </div>

            <!-- Opportunity Stats (Ventas) -->
            <div class="mb-6">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Opportunities</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div v-for="card in oppCards" :key="card.key"
                        class="bg-white rounded-lg border border-gray-200 px-4 py-3">
                        <div class="flex items-center justify-between">
                            <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wide">{{ card.label }}</p>
                            <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: card.color }"></span>
                        </div>
                        <p class="text-xl font-bold mt-1" :style="{ color: card.color }">{{ card.value }}</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3 mb-6">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search name, email, company, phone..."
                    class="flex-1 min-w-48 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400"
                />
                
                <select v-model="event" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">All events</option>
                    <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                </select>
                <select v-if="isLeader" v-model="assignedTo" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">All advisors</option>
                    <option value="unassigned">Unassigned</option>
                    <option v-for="adv in advisors" :key="adv.id" :value="adv.id">{{ adv.first_name }} {{ adv.last_name }}</option>
                </select>
                <select v-model="status" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">All statuses</option>
                    <option v-for="(info, key) in statuses" :key="key" :value="key">{{ info.label }}</option>
                </select>
                <select v-model="oppStatus" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">All opportunities</option>
                    <option v-for="(info, key) in opportunityStatuses" :key="key" :value="key">{{ info.label }}</option>
                </select>
                <select v-model="tag" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">All tags</option>
                    <option v-for="t in allTags" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <select v-model="source" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">All sources</option>
                    <option v-for="(label, key) in sources" :key="key" :value="key">{{ label }}</option>
                </select>
            </div>

            <!-- Bulk actions bar -->
            <div v-if="selectedLeads.length > 0" class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2.5">
                <span class="text-sm font-medium text-amber-800">{{ selectedLeads.length }} selected</span>
                <button @click="openBulkEmail"
                    class="px-3 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors flex items-center gap-1">
                    <EnvelopeIcon class="w-3.5 h-3.5" /> Send Email
                </button>
                <button @click="selectedLeads = []" class="text-xs text-gray-500 hover:text-gray-700">Clear</button>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-200">
                <div>
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-3 py-3 w-10"><input type="checkbox" v-model="allSelected" class="accent-black w-4 h-4 cursor-pointer" /></th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Lead</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Company</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Instagram</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Budget</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Event</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Tags</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Advisor</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Registered</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Source</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="lead in leads.data" :key="lead.id" class="hover:bg-gray-50 transition-colors cursor-pointer" @click="router.visit(`/admin/sales/leads/${lead.id}`)">
                                <td class="px-3 py-4" @click.stop>
                                    <input type="checkbox" :value="lead.id" v-model="selectedLeads" class="accent-black w-4 h-4 cursor-pointer" />
                                </td>
                                <!-- Lead info -->
                                <td class="px-4 py-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-9 h-9 rounded-full bg-black flex items-center justify-center text-xs font-bold text-white flex-shrink-0">
                                            {{ lead.first_name?.[0] || '' }}{{ lead.last_name?.[0] || '' }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-medium text-gray-900 text-sm">{{ lead.first_name }} {{ lead.last_name }}</p>
                                            <p class="text-gray-500 text-xs truncate">{{ lead.email }}</p>
                                            <p v-if="lead.phone" class="text-gray-400 text-xs truncate">{{ lead.phone }}</p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Company -->
                                <td class="px-4 py-4 text-sm text-gray-700">
                                    <span v-if="lead.company_name">{{ lead.company_name }}</span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>


                                <!-- Instagram -->
                                <td class="px-4 py-4 text-sm" @click.stop>
                                    <a v-if="lead.instagram" :href="`https://instagram.com/${lead.instagram}`" target="_blank"
                                        class="text-pink-600 hover:text-pink-700 hover:underline">
                                        @{{ lead.instagram }}
                                    </a>
                                    <span v-else class="text-gray-400">—</span>
                                </td>

                                <!-- Budget -->
                                <td class="px-4 py-4 text-sm text-gray-600 whitespace-nowrap">
                                    {{ lead.budget || '—' }}
                                </td>

                                <!-- Events -->
                                <td class="px-4 py-4" @click.stop>
                                    <button v-if="lead.events?.length" @click="openEventsModal(lead)" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors cursor-pointer">
                                        {{ lead.events.length }} {{ lead.events.length === 1 ? 'event' : 'events' }}
                                    </button>
                                    <span v-else class="text-gray-400 text-xs">—</span>
                                </td>

                                <!-- Tags -->
                                <td class="px-4 py-4" @click.stop>
                                    <div v-if="lead.tags?.length === 1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium"
                                            :style="{ backgroundColor: lead.tags[0].color + '30', color: '#1f2937' }">
                                            {{ lead.tags[0].name }}
                                        </span>
                                    </div>
                                    <div v-else-if="lead.tags?.length > 1">
                                        <button @click="tagsModalLead = lead"
                                            class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
                                            Multiple ({{ lead.tags.length }})
                                        </button>
                                    </div>
                                    <span v-else class="text-gray-400 text-xs">—</span>
                                </td>

                                <!-- Status (clickable badge with dropdown) -->
                                <td class="px-4 py-4" @click.stop>
                                    <div class="relative">
                                        <button @click="toggleStatusDropdown(lead.id)"
                                            class="text-xs font-medium rounded-full px-2.5 py-1 cursor-pointer border border-transparent hover:border-gray-300 transition-colors"
                                            :style="{
                                                backgroundColor: (statuses[lead.status]?.color || '#6B7280') + '20',
                                                color: statuses[lead.status]?.color || '#6B7280',
                                            }">
                                            {{ statuses[lead.status]?.label || lead.status }}
                                        </button>
                                        <!-- Status dropdown -->
                                        <div v-if="openStatusDropdown === lead.id"
                                            class="absolute z-20 mt-1 left-0 bg-white rounded-lg shadow-lg border border-gray-200 py-1 min-w-36">
                                            <button v-for="(info, key) in statuses" :key="key"
                                                @click="changeStatus(lead, key); openStatusDropdown = null"
                                                class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50 flex items-center gap-2 transition-colors"
                                                :class="lead.status === key ? 'font-semibold' : ''">
                                                <span class="w-2 h-2 rounded-full flex-shrink-0" :style="{ backgroundColor: info.color }"></span>
                                                {{ info.label }}
                                            </button>
                                        </div>
                                    </div>
                                </td>

                                <!-- Advisor -->
                                <td class="px-4 py-4" @click.stop>
                                    <div class="relative">
                                        <button v-if="isLeader" @click="toggleAdvisorDropdown(lead.id)"
                                            class="text-xs font-medium rounded-full px-2.5 py-1 cursor-pointer transition-colors"
                                            :class="advisorName(lead)
                                                ? 'bg-blue-50 text-blue-700 hover:bg-blue-100'
                                                : 'bg-orange-50 text-orange-600 hover:bg-orange-100'">
                                            {{ advisorName(lead) || 'Unassigned' }}
                                        </button>
                                        <span v-else class="text-xs font-medium rounded-full px-2.5 py-1"
                                            :class="advisorName(lead) ? 'bg-blue-50 text-blue-700' : 'bg-orange-50 text-orange-600'">
                                            {{ advisorName(lead) || 'Unassigned' }}
                                        </span>
                                        <!-- Advisor dropdown (leader only) -->
                                        <div v-if="isLeader && openAdvisorDropdown === lead.id"
                                            class="absolute z-20 mt-1 left-0 bg-white rounded-lg shadow-lg border border-gray-200 py-1 min-w-44">
                                            <button @click="changeAdvisor(lead, null); openAdvisorDropdown = null"
                                                class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50 text-orange-600 transition-colors">
                                                Unassigned
                                            </button>
                                            <button v-for="adv in advisors" :key="adv.id"
                                                @click="changeAdvisor(lead, adv.id); openAdvisorDropdown = null"
                                                class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50 flex items-center gap-2 transition-colors"
                                                :class="lead.assigned_to?.id === adv.id ? 'font-semibold text-blue-700' : 'text-gray-700'">
                                                <span class="w-2 h-2 rounded-full flex-shrink-0"
                                                    :class="adv.is_available ? 'bg-green-500' : 'bg-gray-300'"></span>
                                                {{ adv.first_name }} {{ adv.last_name }}
                                                <span v-if="!adv.is_available" class="text-gray-400 text-[10px]">(unavailable)</span>
                                            </button>
                                        </div>
                                    </div>
                                </td>

                                <!-- Created at -->
                                <td class="px-4 py-4 text-gray-500 text-xs whitespace-pre-line">
                                    {{ formatDate(lead.created_at) }}
                                </td>

                                <!-- Source -->
                                <td class="px-4 py-4 text-xs text-gray-500">
                                    {{ sources[lead.source] || lead.source || '—' }}
                                </td>

                                <!-- Actions -->
                                <td class="px-4 py-4" @click.stop>
                                    <div class="flex items-center justify-end gap-1">
                                        <Link :href="`/admin/sales/leads/${lead.id}/edit`"
                                            class="p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition-colors cursor-pointer"
                                            title="Edit">
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </Link>
                                        <Link :href="`/admin/sales/leads/${lead.id}`"
                                            class="p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition-colors cursor-pointer"
                                            title="View details">
                                            <EyeIcon class="w-4 h-4" />
                                        </Link>
                                        <button v-if="lead.status !== 'redirected'"
                                            @click="openRedirectModal(lead)"
                                            class="p-1.5 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 hover:text-amber-700 transition-colors"
                                            title="Send to Operations">
                                            <ArrowTopRightOnSquareIcon class="w-4 h-4" />
                                        </button>
                                        <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-100 text-amber-700">Redirected</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="leads.data.length === 0">
                                <td colspan="12" class="px-6 py-12 text-center text-gray-400 text-sm">
                                    No prospects found with the applied filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="border-t border-gray-200 px-4 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <p class="text-sm text-gray-500">Showing {{ leads.from }}–{{ leads.to }} of {{ leads.total }}</p>
                        <select v-model="perPage" class="border border-gray-200 rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-black/10 bg-white">
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                    </div>
                    <div v-if="leads.last_page > 1" class="flex gap-1">
                        <Link v-for="link in leads.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
                            class="px-3 py-1.5 text-sm rounded-lg border transition-colors"
                            :class="link.active ? 'border-black bg-black text-white font-medium' : link.url ? 'border-gray-200 text-gray-600 hover:bg-gray-50' : 'border-gray-100 text-gray-300 cursor-not-allowed'" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Info Modal -->
        <Teleport to="body">
            <div v-if="showStatusInfo" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showStatusInfo = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] overflow-y-auto">
                    <div class="sticky top-0 bg-white px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Status Guide</h3>
                        <button @click="showStatusInfo = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                    </div>
                    <div class="px-6 py-5 space-y-6 text-sm">
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3">Lead Status (person)</h4>
                            <p class="text-gray-500 text-xs mb-3">Represents the contact's lifecycle as a prospect.</p>
                            <div class="space-y-2">
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full mt-1 flex-shrink-0" style="background:#3B82F6"></span><div><span class="font-medium text-gray-900">New</span><span class="text-gray-500"> — Just registered. The leader must review and qualify.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full mt-1 flex-shrink-0" style="background:#8B5CF6"></span><div><span class="font-medium text-gray-900">Qualified</span><span class="text-gray-500"> — A real prospect. Auto-assigned to an advisor via round-robin.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full mt-1 flex-shrink-0" style="background:#10B981"></span><div><span class="font-medium text-gray-900">Client</span><span class="text-gray-500"> — At least 1 event was converted into a sale. Automatic when a designer registration is created.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full mt-1 flex-shrink-0" style="background:#EF4444"></span><div><span class="font-medium text-gray-900">Lost</span><span class="text-gray-500"> — Not interested. Set automatically if every event opportunity ends as Lost.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full mt-1 flex-shrink-0" style="background:#1F2937"></span><div><span class="font-medium text-gray-900">Spam</span><span class="text-gray-500"> — Not a real prospect. Marked manually by the leader to skip round-robin.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full mt-1 flex-shrink-0" style="background:#F59E0B"></span><div><span class="font-medium text-gray-900">Redirected</span><span class="text-gray-500"> — Sent to Operations as a model / media / volunteer (not a designer prospect).</span></div></div>
                            </div>
                        </div>
                        <div class="border-t pt-5">
                            <h4 class="font-semibold text-gray-900 mb-3">Status per Event (opportunity)</h4>
                            <p class="text-gray-500 text-xs mb-3">Represents the negotiation progress for each specific event the lead is interested in.</p>
                            <div class="space-y-2">
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full mt-1 flex-shrink-0" style="background:#3B82F6"></span><div><span class="font-medium text-gray-900">New</span><span class="text-gray-500"> — Registered for this event, not contacted yet.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full mt-1 flex-shrink-0" style="background:#EAB308"></span><div><span class="font-medium text-gray-900">Contacted</span><span class="text-gray-500"> — First contact was made.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full mt-1 flex-shrink-0" style="background:#F97316"></span><div><span class="font-medium text-gray-900">Follow Up</span><span class="text-gray-500"> — Needs another touchpoint, awaiting response.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full mt-1 flex-shrink-0" style="background:#8B5CF6"></span><div><span class="font-medium text-gray-900">Negotiating</span><span class="text-gray-500"> — Discussing package and price. The Convert button becomes enabled.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full mt-1 flex-shrink-0" style="background:#10B981"></span><div><span class="font-medium text-gray-900">Sale</span><span class="text-gray-500"> — Sale closed for this event. The designer registration is created automatically.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full mt-1 flex-shrink-0" style="background:#EF4444"></span><div><span class="font-medium text-gray-900">Lost</span><span class="text-gray-500"> — Sale not completed for this event. The lead can still be active for other events.</span></div></div>
                            </div>
                        </div>
                        <div class="border-t pt-5">
                            <h4 class="font-semibold text-gray-900 mb-3">Automatic Changes</h4>
                            <div class="space-y-2 text-gray-600 text-xs">
                                <p>• When the leader sets a lead to <span class="font-medium text-purple-600">Qualified</span> → it gets auto-assigned to an available advisor.</p>
                                <p>• When the lead is converted (designer registration created) → the lead becomes <span class="font-medium text-green-600">Client</span> and that event opportunity becomes <span class="font-medium text-green-600">Sale</span>.</p>
                                <p>• If every event opportunity ends as <span class="font-medium text-red-600">Lost</span> → the lead status flips to <span class="font-medium text-red-600">Lost</span>.</p>
                                <p>• If a previously-lost lead registers for a new event → it returns to <span class="font-medium text-purple-600">Qualified</span> for re-engagement.</p>
                                <p>• Leader marks the lead as <span style="color:#F59E0B" class="font-medium">Redirected</span> → moves out of the sales funnel into Operations (model / media / volunteer track).</p>
                            </div>
                        </div>
                        <div class="border-t pt-5">
                            <h4 class="font-semibold text-gray-900 mb-3">Assignment</h4>
                            <div class="space-y-2 text-gray-600 text-xs">
                                <p>• <span class="font-medium text-blue-600">USA leads</span> — priority rotation (equal distribution)</p>
                                <p>• <span class="font-medium text-gray-700">Other countries</span> — standard rotation</p>
                                <p>• 3+ unassigned → email alert sent</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Tags Modal -->
        <Teleport to="body">
            <div v-if="tagsModalLead" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="tagsModalLead = null"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-b">
                        <div>
                            <h3 class="font-semibold text-gray-900">Tags for {{ tagsModalLead.first_name }} {{ tagsModalLead.last_name }}</h3>
                            <p class="text-xs text-gray-500">{{ tagsModalLead.tags?.length }} assigned tags</p>
                        </div>
                        <button @click="tagsModalLead = null" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                    </div>
                    <div class="px-6 py-4">
                        <div class="flex flex-wrap gap-2">
                            <span v-for="t in tagsModalLead.tags" :key="t.id"
                                class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium"
                                :style="{ backgroundColor: t.color + '30', color: '#1f2937' }">
                                {{ t.name }}
                            </span>
                        </div>
                    </div>
                    <div class="border-t px-6 py-3 flex justify-between">
                        <Link :href="`/admin/sales/leads/${tagsModalLead.id}`" class="text-sm font-medium text-gray-700 hover:text-black">View profile →</Link>
                        <button @click="tagsModalLead = null" class="text-sm text-gray-500 hover:text-gray-700">Close</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Events Modal -->
        <Teleport to="body">
            <div v-if="eventsModalLead" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="eventsModalLead = null"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                    <!-- Header -->
                    <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-b">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ eventsModalLead.first_name }} {{ eventsModalLead.last_name }}</h3>
                            <p class="text-xs text-gray-500">{{ eventsModalLead.events?.length }} {{ eventsModalLead.events?.length === 1 ? 'assigned event' : 'assigned events' }}</p>
                        </div>
                        <button @click="eventsModalLead = null" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                    </div>
                    <!-- Events list -->
                    <div class="px-6 py-4 space-y-4 max-h-96 overflow-y-auto">
                        <div v-for="(ev, idx) in eventsModalLead.events" :key="ev.id" class="border border-gray-200 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full bg-black text-white text-xs font-bold flex items-center justify-center">{{ idx + 1 }}</span>
                                    <span class="font-medium text-sm text-gray-900">{{ ev.name }}</span>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block">Status</label>
                                <select
                                    :value="ev.pivot?.status || 'new'"
                                    @change="changeEventStatus(eventsModalLead, ev.id, $event.target.value)"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-black focus:border-black">
                                    <option v-for="(info, key) in opportunityStatuses" :key="key" :value="key">{{ info.label }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <!-- Footer -->
                    <div class="border-t px-6 py-3 flex items-center justify-between">
                        <Link :href="`/admin/sales/leads/${eventsModalLead.id}`" class="text-sm font-medium text-gray-700 hover:text-black">View full profile →</Link>
                        <button @click="eventsModalLead = null" class="text-sm text-gray-500 hover:text-gray-700">Close</button>
                    </div>
                </div>
            </div>
        </Teleport>
    <!-- Modal: Import Leads -->
    <Teleport to="body">
        <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" @click="showImportModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
                <!-- Header -->
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold text-gray-900">Import Leads from Excel</h3>
                    <button @click="showImportModal = false" class="text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <!-- Download template -->
                <div class="mb-4">
                    <a href="/admin/sales/leads/import-template"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-xl transition-colors">
                        <ArrowDownTrayIcon class="w-4 h-4" />
                        Download Template (.xlsx)
                    </a>
                    <p class="mt-2 text-xs text-gray-500">The template includes all accepted columns with example data.</p>
                </div>

                <!-- Accepted columns -->
                <div class="bg-gray-50 rounded-xl p-4 mb-5 text-xs text-gray-600">
                    <p class="font-semibold text-gray-800 mb-2">Accepted columns:</p>
                    <div class="grid grid-cols-2 gap-1">
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">email</span> <span class="text-red-500">*required</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">first_name</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">last_name</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">phone</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">country</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">company_name</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">retail_category</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">website_url</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">instagram</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">budget</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">past_shows</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">notes</span></span>
                    </div>
                    <p class="mt-2 text-gray-500">Formats: <strong>.xlsx, .xls, .csv</strong></p>
                </div>

                <!-- Event selector -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Assign to event <span class="text-gray-400 font-normal">(optional)</span></label>
                    <select v-model="importForm.event_id"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                        <option value="">— No event —</option>
                        <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                </div>

                <!-- Advisor selector (leader only) -->
                <div v-if="isLeader && advisors.length" class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Assign to advisor <span class="text-gray-400 font-normal">(optional)</span></label>
                    <select v-model="importForm.assigned_to"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                        <option value="">— No advisor —</option>
                        <option v-for="a in advisors" :key="a.id" :value="a.id">{{ a.first_name }} {{ a.last_name }}</option>
                    </select>
                </div>

                <!-- Source selector -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Source <span class="text-gray-400 font-normal">(optional)</span></label>
                    <select v-model="importForm.source"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                        <option value="">— Default (Manual) —</option>
                        <option v-for="(label, key) in sources" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>

                <!-- File input -->
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select file</label>
                    <input type="file" accept=".xlsx,.xls,.csv"
                        @change="handleImportFileChange"
                        class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-black file:text-white hover:file:bg-gray-800 cursor-pointer" />
                    <p v-if="importForm.errors.file" class="mt-1 text-xs text-red-500">{{ importForm.errors.file }}</p>
                </div>

                <!-- Actions -->
                <div class="flex gap-3">
                    <button @click="showImportModal = false"
                        class="flex-1 py-2.5 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button @click="submitImport"
                        :disabled="!importForm.file || importForm.processing"
                        class="flex-1 py-2.5 bg-black text-white rounded-xl text-sm font-semibold hover:bg-gray-800 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        {{ importForm.processing ? 'Importing...' : 'Import' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Send Email Modal -->
    <Teleport to="body">
        <div v-if="showEmailModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showEmailModal = false">
            <EmailComposer
                :recipient-label="`${selectedLeads.length} recipient${selectedLeads.length > 1 ? 's' : ''}`"
                :processing="emailProcessing"
                @send="handleBulkEmailSend"
                @close="showEmailModal = false"
            />
        </div>
    </Teleport>

    <!-- Redirect to Operations Modal -->
    <Teleport to="body">
        <div v-if="showRedirectModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showRedirectModal = false">
            <div class="bg-white rounded-2xl w-full max-w-md shadow-xl">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">Send to Operations</h3>
                    <button @click="showRedirectModal = false" class="p-1 rounded-lg hover:bg-gray-100"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <p class="text-sm text-gray-500">Send <strong>{{ redirectLead?.first_name }} {{ redirectLead?.last_name }}</strong> to Operations for registration.</p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select v-model="redirectForm.redirect_type" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                            <option value="model">Model</option>
                            <option value="media">Media</option>
                            <option value="volunteer">Volunteer</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Note (optional)</label>
                        <textarea v-model="redirectForm.redirect_note" rows="2" placeholder="Additional info for Operations..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                    <button @click="showRedirectModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                    <button @click="submitRedirect" :disabled="redirectForm.processing"
                        class="px-4 py-2 text-sm font-semibold text-white bg-amber-500 hover:bg-amber-600 rounded-lg disabled:opacity-50">
                        {{ redirectForm.processing ? 'Sending...' : 'Send to Operations' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    </AdminLayout>
</template>
