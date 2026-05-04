<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { XMarkIcon, ArrowUpTrayIcon, ArrowDownTrayIcon, DevicePhoneMobileIcon, EnvelopeIcon, PencilSquareIcon, InformationCircleIcon, ExclamationTriangleIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    designers:         Object,
    events:            Array,
    categories:        Array,
    packages:          Array,
    salesReps:         Array,
    countries:         Array,
    pendingEmailCount: Number,
    pendingSmsCount:   Number,
    twilioBalance:     Object,
    filters:           Object,
});

const search   = ref(props.filters.search    ?? '');
const event    = ref(props.filters.event      ?? '');
const category = ref(props.filters.category   ?? '');
const pkg      = ref(props.filters.package    ?? '');
const salesRep  = ref(props.filters.sales_rep  ?? '');
const materials = ref(props.filters.materials  ?? '');
const country   = ref(props.filters.country    ?? '');
const checkin   = ref(props.filters.checkin    ?? '');

let timer = null;
function applyFilters() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/admin/operations/designers', {
            search:    search.value    || undefined,
            event:     event.value     || undefined,
            category:  category.value  || undefined,
            package:   pkg.value       || undefined,
            sales_rep: salesRep.value  || undefined,
            materials: materials.value || undefined,
            country:   country.value   || undefined,
            checkin:   checkin.value    || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}

watch([search, event, category, pkg, salesRep, materials, country, checkin], applyFilters);

function onNotification(e) {
    const type = e.detail?.data?.type;
    if (['payment_plan_assigned', 'designer_status_changed', 'new_designer_registered'].includes(type)) {
        router.reload({ preserveScroll: true });
    }
}
onMounted(() => window.addEventListener('notification:received', onNotification));
onUnmounted(() => window.removeEventListener('notification:received', onNotification));

function statusBadge(status) {
    return {
        active:     'bg-green-100 text-green-700',
        inactive:   'bg-red-100 text-red-700',
        pending:    'bg-yellow-100 text-yellow-700',
        registered: 'bg-blue-100 text-blue-700',
    }[status] ?? 'bg-gray-100 text-gray-600';
}

const showNoPlanModal = ref(false);
const noPlanDesigner = ref(null);
const showNoEventModal = ref(false);
const noEventDesigner = ref(null);
const noEventMissing = ref([]);

function updateDesignerStatus(d, newStatus, event) {
    if (newStatus === 'pending') {
        // Priority 1: Payment plan
        if (!d.has_payment_plan) {
            if (event?.target) event.target.value = d.status;
            noPlanDesigner.value = d;
            showNoPlanModal.value = true;
            return;
        }
        // Priority 2: Event (includes show and fitting)
        const missing = [];
        if (!d.has_event) missing.push('No event assigned.');
        if (!d.has_show) missing.push('No show assigned (day and time).');
        if (missing.length) {
            if (event?.target) event.target.value = d.status;
            noEventDesigner.value = d;
            noEventMissing.value = missing;
            showNoEventModal.value = true;
            return;
        }
    }
    router.patch(`/admin/operations/designers/${d.id}/status`, { status: newStatus }, { preserveScroll: true });
}

// Communication log modals
const showCommModal = ref(false);
const commModalDesigner = ref(null);
const commModalType = ref(null); // 'email' or 'sms'

function getCommLogs(d, type) {
    const channel = type === 'email' ? 'onboarding_email' : 'onboarding_sms';
    return (d.communication_logs ?? [])
        .filter(l => l.channel === channel)
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
}

function openCommModal(d, type, e) {
    e.stopPropagation();
    commModalDesigner.value = d;
    commModalType.value = type;
    showCommModal.value = true;
}

function commStatusLabel(status) {
    return { queued: 'In queue', sent: 'Sent', failed: 'Failed' }[status] ?? status;
}

function commStatusClass(status) {
    return {
        queued: 'bg-yellow-100 text-yellow-700',
        sent:   'bg-green-100 text-green-700',
        failed: 'bg-red-100 text-red-700',
    }[status] ?? 'bg-gray-100 text-gray-600';
}

function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}

function materialsProgress(materials) {
    if (!materials || materials.length === 0) return 0;
    const done = materials.filter(m => m.status === 'completed' || m.status === 'confirmed').length;
    return Math.round((done / materials.length) * 100);
}

// Progress for a designer scoped to a specific event.
function materialsProgressForEvent(materials, eventId) {
    const filtered = (materials ?? []).filter(m => m.event_id === eventId);
    if (filtered.length === 0) return 0;
    const done = filtered.filter(m => m.status === 'completed' || m.status === 'confirmed').length;
    return Math.round((done / filtered.length) * 100);
}

// The "current" event of a designer:
// - If there are upcoming events (start_date >= today), the soonest one.
// - Otherwise the most recent past event.
function nearestEvent(designer) {
    const events = designer.events_as_designer ?? [];
    if (events.length === 0) return null;
    const today = new Date().toISOString().slice(0, 10);
    const upcoming = events.filter(e => e.start_date && e.start_date >= today)
        .sort((a, b) => a.start_date.localeCompare(b.start_date));
    if (upcoming.length) return upcoming[0];
    return [...events].filter(e => e.start_date)
        .sort((a, b) => b.start_date.localeCompare(a.start_date))[0] ?? events[0];
}

function displayProgress(designer) {
    const ev = nearestEvent(designer);
    if (!ev) return 0;
    return materialsProgressForEvent(designer.designer_materials, ev.id);
}

const showMaterialsModal = ref(false);
const materialsModalDesigner = ref(null);

function openMaterialsModal(d, e) {
    e?.stopPropagation();
    materialsModalDesigner.value = d;
    showMaterialsModal.value = true;
}

function progressColor(pct) {
    if (pct === 100) return 'bg-green-500';
    if (pct >= 50)   return 'bg-yellow-400';
    return 'bg-gray-300';
}

const checkinDesigner = ref(null);

function checkinPasses(d) {
    return (d.event_passes ?? [])
        .filter(p => p.checked_in_at)
        .sort((a, b) => new Date(b.checked_in_at) - new Date(a.checked_in_at));
}

function openCheckinModal(d, e) {
    e.stopPropagation();
    checkinDesigner.value = d;
}

function timeAgo(dt) {
    if (!dt) return '';
    const diff = Date.now() - new Date(dt).getTime();
    const mins  = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days  = Math.floor(diff / 86400000);
    if (mins < 60)   return `${mins}m ago`;
    if (hours < 24)  return `${hours}h ago`;
    if (days < 30)   return `${days}d ago`;
    return fmtDate(dt);
}

function fmtDate(dt) {
    if (!dt) return null;
    return new Date(dt).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmtTime(dt) {
    if (!dt) return null;
    return new Date(dt).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function fmtDateTime(dt) {
    if (!dt) return null;
    const d = new Date(dt);
    return d.toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })
        + ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function fmtEmailSent(dt) {
    if (!dt) return null;
    const d = new Date(dt);
    return d.toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' });
}

// --- Email / SMS send modals ---
const emailModalDesigner = ref(null);
const emailModalEventId  = ref(null);
const smsModalDesigner   = ref(null);
const smsModalEventId    = ref(null);
const showEmailInfoModal = ref(false);
const showSmsInfoModal   = ref(false);

function sendOnboardingEmail(d, e) {
    e.stopPropagation();
    const events = d.events_as_designer ?? [];
    if (events.length <= 1) {
        // Single event or none — send directly
        router.post(`/admin/operations/designers/${d.id}/send-onboarding`,
            { event_id: events[0]?.id ?? null },
            { preserveScroll: true }
        );
    } else {
        emailModalEventId.value = events[0]?.id ?? null;
        emailModalDesigner.value = d;
    }
}

function confirmSendEmail() {
    const d = emailModalDesigner.value;
    router.post(`/admin/operations/designers/${d.id}/send-onboarding`,
        { event_id: emailModalEventId.value },
        { preserveScroll: true, onSuccess: () => { emailModalDesigner.value = null; } }
    );
}

function sendPendingOnboarding() {
    if (!confirm(`Send onboarding email to ${props.pendingEmailCount} pending designer(s)? Emails will be queued.`)) return;
    router.post('/admin/operations/designers/send-bulk-onboarding', {}, { preserveScroll: true });
}

function canSendEmail(d) {
    return d.status === 'pending' && !!d.email;
}

function canSendSms(d) {
    return d.status === 'pending' && !!d.phone;
}

function sendOnboardingSms(d, e) {
    e.stopPropagation();
    if (!d.phone) return alert(`${d.first_name} has no registered phone number.`);
    const events = d.events_as_designer ?? [];
    if (events.length <= 1) {
        router.post(`/admin/operations/designers/${d.id}/send-onboarding-sms`,
            { event_id: events[0]?.id ?? null },
            { preserveScroll: true }
        );
    } else {
        smsModalEventId.value = events[0]?.id ?? null;
        smsModalDesigner.value = d;
    }
}

function confirmSendSms() {
    const d = smsModalDesigner.value;
    router.post(`/admin/operations/designers/${d.id}/send-onboarding-sms`,
        { event_id: smsModalEventId.value },
        { preserveScroll: true, onSuccess: () => { smsModalDesigner.value = null; } }
    );
}

function sendPendingSms() {
    if (!confirm(`Send onboarding SMS to ${props.pendingSmsCount} designer(s) with phone? SMS will be queued.`)) return;
    router.post('/admin/operations/designers/send-bulk-onboarding-sms', {}, { preserveScroll: true });
}

// --- Modal eventos ---
const selectedDesigner = ref(null);

function openEventsModal(d, e) {
    e.stopPropagation();
    selectedDesigner.value = d;
}

function eventStatusBadge(status) {
    return {
        draft:     'bg-gray-100 text-gray-600',
        published: 'bg-blue-100 text-blue-700',
        active:    'bg-green-100 text-green-700',
        completed: 'bg-purple-100 text-purple-700',
        cancelled: 'bg-red-100 text-red-600',
    }[status] ?? 'bg-gray-100 text-gray-600';
}

function eventStatusLabel(status) {
    return { draft: 'Draft', published: 'Published', active: 'Active', completed: 'Completed', cancelled: 'Cancelled' }[status] ?? status;
}

function pivotStatusLabel(status) {
    return { confirmed: 'Confirmed', cancelled: 'Cancelled', pending: 'Pending' }[status] ?? status;
}

function pivotStatusBadge(status) {
    return { confirmed: 'bg-green-100 text-green-700', cancelled: 'bg-red-100 text-red-600', pending: 'bg-yellow-100 text-yellow-700' }[status] ?? 'bg-gray-100 text-gray-600';
}

// --- Export Excel ---
const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (search.value)    params.set('search',    search.value);
    if (event.value)     params.set('event',     event.value);
    if (category.value)  params.set('category',  category.value);
    if (pkg.value)       params.set('package',   pkg.value);
    if (salesRep.value)  params.set('sales_rep', salesRep.value);
    if (materials.value) params.set('materials', materials.value);
    if (country.value)   params.set('country',   country.value);
    const qs = params.toString();
    return '/admin/operations/designers/export' + (qs ? '?' + qs : '');
});

// --- Import Excel ---
const showImportModal = ref(false);
const importForm = useForm({ file: null, event_id: '' });
const fileInput = ref(null);

function handleFileChange(e) {
    importForm.file = e.target.files[0] ?? null;
}

function submitImport() {
    importForm.post('/admin/operations/designers/import', {
        forceFormData: true,
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Designers</h2>
        </template>

        <div>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Designers</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ designers.total }} registered designers</p>
                </div>
                <div class="flex items-center gap-3">
                    <div v-if="twilioBalance" class="flex flex-col items-end px-3 py-1.5 border border-gray-200 rounded-lg bg-white">
                        <span class="text-[10px] text-gray-400 font-medium leading-tight">Twilio Balance</span>
                        <span class="text-sm font-bold text-gray-900 leading-tight">{{ twilioBalance.balance }} {{ twilioBalance.currency }}</span>
                    </div>
                    <div v-if="pendingSmsCount > 0" class="flex items-center gap-1">
                        <button @click="sendPendingSms"
                            class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors text-gray-700">
                            <DevicePhoneMobileIcon class="w-4 h-4 text-gray-500" />
                            Send SMS
                            <span class="bg-green-100 text-green-700 text-xs font-bold px-1.5 py-0.5 rounded-full">{{ pendingSmsCount }}</span>
                        </button>
                        <button @click="showSmsInfoModal = true"
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            title="How does bulk send work?">
                            <InformationCircleIcon class="w-4 h-4" />
                        </button>
                    </div>
                    <div v-if="pendingEmailCount > 0" class="flex items-center gap-1">
                        <button @click="sendPendingOnboarding"
                            class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors text-gray-700">
                            <EnvelopeIcon class="w-4 h-4 text-gray-500" />
                            Send emails
                            <span class="bg-amber-100 text-amber-700 text-xs font-bold px-1.5 py-0.5 rounded-full">{{ pendingEmailCount }}</span>
                        </button>
                        <button @click="showEmailInfoModal = true"
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            title="How does bulk send work?">
                            <InformationCircleIcon class="w-4 h-4" />
                        </button>
                    </div>
                    <!-- Overdue materials -->
                    <Link href="/admin/operations/designers/overdue-materials"
                        class="flex items-center gap-2 px-4 py-2 border border-red-200 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors text-red-600">
                        <ExclamationTriangleIcon class="w-4 h-4" />
                        Overdue Materials
                    </Link>

                    <!-- Material instructions -->
                    <Link href="/admin/operations/designers/material-instructions"
                        class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors text-gray-700">
                        <DocumentTextIcon class="w-4 h-4 text-gray-500" />
                        Material Instructions
                    </Link>

                    <!-- Export Excel -->
                    <a :href="exportUrl"
                        class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors text-gray-700">
                        <ArrowDownTrayIcon class="w-4 h-4 text-gray-500" />
                        Export Excel
                    </a>

                    <!-- Import Excel -->
                    <button @click="showImportModal = true"
                        class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors text-gray-700">
                        <ArrowUpTrayIcon class="w-4 h-4 text-gray-500" />
                        Import Excel
                    </button>

                    <Link href="/admin/operations/designers/create" class="px-4 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors">
                        + Create Designer
                    </Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3 mb-6">
                <input v-model="search" type="text" placeholder="Search by name, email, brand..."
                    class="flex-1 min-w-48 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400" />

                <select v-model="event"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">All events</option>
                    <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>

                <select v-model="category"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">All categories</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>

                <select v-model="pkg"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">All packages</option>
                    <option v-for="p in packages" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>

                <select v-model="salesRep"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">All sales reps</option>
                    <option v-for="s in salesReps" :key="s.id" :value="s.id">{{ s.first_name }} {{ s.last_name }}</option>
                </select>

                <select v-model="materials"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Materials: All</option>
                    <option value="complete">Complete</option>
                    <option value="incomplete">Incomplete</option>
                </select>

                <select v-model="country"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">All countries</option>
                    <option v-for="c in countries" :key="c" :value="c">{{ c }}</option>
                </select>

                <select v-model="checkin"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Check-in: All</option>
                    <option value="yes">Checked in</option>
                    <option value="no">Not checked in</option>
                </select>
            </div>

            <!-- Tabla -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Registration</th>
                            <th class="text-left px-5 py-3 font-medium text-gray-500">Designer / Brand</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Contact</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Materials</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Events</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Status</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Last Login</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Last Check-in</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Last Email</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Last SMS</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="designers.data.length === 0">
                            <td colspan="12" class="text-center text-gray-400 py-12">No registered designers.</td>
                        </tr>
                        <tr v-for="d in designers.data" :key="d.id"
                            class="hover:bg-gray-50 cursor-pointer transition-colors"
                            @click="router.visit(`/admin/operations/designers/${d.id}`)">
                            <!-- Registro -->
                            <td class="px-4 py-3">
                                <p class="text-xs text-gray-700">{{ fmtDate(d.created_at) }}</p>
                                <p class="text-[11px] text-gray-400">{{ fmtTime(d.created_at) }}</p>
                            </td>
                            <!-- Foto + Nombre + Brand -->
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full overflow-hidden flex-shrink-0 bg-gray-100">
                                        <img v-if="storageUrl(d.profile_picture)"
                                            :src="storageUrl(d.profile_picture)"
                                            class="w-full h-full object-cover" />
                                        <div v-else class="w-full h-full flex items-center justify-center text-xs font-bold text-gray-500">
                                            {{ d.first_name?.[0] }}{{ d.last_name?.[0] }}
                                        </div>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">{{ d.first_name }} {{ d.last_name }}</p>
                                        <p class="text-gray-400 text-xs">{{ d.designer_profile?.brand_name ?? d.email }}</p>
                                        <span v-if="d.designer_profile?.category"
                                            class="inline-block mt-0.5 text-[10px] bg-amber-50 text-amber-700 px-1.5 py-0.5 rounded-full font-medium">
                                            {{ d.designer_profile.category.name }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <!-- Contacto -->
                            <td class="px-4 py-3">
                                <p class="text-gray-500 text-xs">{{ d.email }}</p>
                                <p v-if="d.phone" class="text-gray-400 text-xs mt-0.5">{{ d.phone }}</p>
                            </td>
                            <!-- Materials -->
                            <td class="px-4 py-3" @click.stop>
                                <div v-if="nearestEvent(d)" class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <div class="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                            <div :class="progressColor(displayProgress(d))"
                                                class="h-full rounded-full transition-all"
                                                :style="{ width: displayProgress(d) + '%' }"></div>
                                        </div>
                                        <span class="text-xs text-gray-500">{{ displayProgress(d) }}%</span>
                                    </div>
                                    <button v-if="(d.events_as_designer?.length ?? 0) > 1"
                                        @click="openMaterialsModal(d, $event)"
                                        class="text-[10px] text-blue-600 hover:underline cursor-pointer">
                                        {{ d.events_as_designer.length }} events
                                    </button>
                                </div>
                                <span v-else class="text-gray-400 text-xs">—</span>
                            </td>
                            <!-- Eventos -->
                            <td class="px-4 py-3" @click.stop>
                                <button v-if="d.events_as_designer?.length"
                                    @click="openEventsModal(d, $event)"
                                    class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full hover:bg-blue-100 transition-colors cursor-pointer">
                                    {{ d.events_as_designer.length }} event{{ d.events_as_designer.length !== 1 ? 's' : '' }}
                                </button>
                                <span v-else class="text-gray-400 text-xs">No events</span>
                            </td>
                            <!-- Estado -->
                            <td class="px-4 py-3" @click.stop>
                                <div>
                                    <span v-if="d.status === 'active'"
                                        class="text-xs font-medium rounded-full px-2 py-0.5 bg-green-100 text-green-700">
                                        Active
                                    </span>
                                    <select v-else :value="d.status"
                                        @change="updateDesignerStatus(d, $event.target.value, $event)"
                                        :class="statusBadge(d.status)"
                                        class="text-xs font-medium rounded-full px-2 py-0.5 border-0 outline-none cursor-pointer appearance-none">
                                        <option value="registered">Registered</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="pending">Pending</option>
                                    </select>
                                    <p class="text-[11px] mt-1" :class="d.has_payment_plan ? 'text-green-600' : 'text-red-500'">
                                        {{ d.has_payment_plan ? 'With a plan' : 'Without a plan' }}
                                    </p>
                                </div>
                            </td>
                            <!-- Último Login -->
                            <td class="px-4 py-3">
                                <p v-if="d.last_login_at" class="text-xs text-gray-700">{{ fmtDateTime(d.last_login_at) }}</p>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <!-- Último Check-in -->
                            <td class="px-4 py-3" @click.stop>
                                <template v-if="checkinPasses(d).length">
                                    <button @click="openCheckinModal(d, $event)"
                                        class="group flex items-center gap-2 text-left hover:opacity-80 transition-opacity">
                                        <div>
                                            <p class="text-xs font-semibold text-gray-800 leading-tight">
                                                {{ fmtDate(checkinPasses(d)[0].checked_in_at) }}
                                            </p>
                                            <p class="text-[11px] text-blue-600 font-medium leading-tight">
                                                {{ fmtTime(checkinPasses(d)[0].checked_in_at) }}
                                            </p>
                                        </div>
                                        <span v-if="checkinPasses(d).length > 1"
                                            class="flex-shrink-0 text-[10px] font-bold bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full group-hover:bg-gray-200 transition-colors">
                                            +{{ checkinPasses(d).length - 1 }}
                                        </span>
                                    </button>
                                </template>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <!-- Último Correo -->
                            <td class="px-4 py-3" @click.stop>
                                <button @click="openCommModal(d, 'email', $event)" class="cursor-pointer hover:opacity-80 transition-opacity">
                                    <template v-if="d.welcome_email_sent_at">
                                        <span class="inline-flex items-center gap-1 text-xs text-green-700 bg-green-50 px-2 py-0.5 rounded-full font-medium">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                            {{ fmtEmailSent(d.welcome_email_sent_at) }}
                                        </span>
                                    </template>
                                    <span v-else class="inline-flex items-center text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                                        Not sent
                                    </span>
                                </button>
                            </td>
                            <!-- Último SMS -->
                            <td class="px-4 py-3" @click.stop>
                                <button @click="openCommModal(d, 'sms', $event)" class="cursor-pointer hover:opacity-80 transition-opacity">
                                    <template v-if="d.sms_sent_at">
                                        <span class="inline-flex items-center gap-1 text-xs text-green-700 bg-green-50 px-2 py-0.5 rounded-full font-medium">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                            {{ fmtEmailSent(d.sms_sent_at) }}
                                        </span>
                                    </template>
                                    <span v-else class="inline-flex items-center text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                                        Not sent
                                    </span>
                                </button>
                            </td>
                            <!-- Acciones -->
                            <td class="px-4 py-3" @click.stop>
                                <div class="flex items-center gap-2">
                                    <button @click="sendOnboardingEmail(d, $event)"
                                        class="p-1.5 border border-gray-200 rounded-lg transition-colors"
                                        :class="canSendEmail(d) ? 'hover:bg-gray-50 text-gray-600' : 'opacity-40 cursor-not-allowed text-gray-400'"
                                        :disabled="!canSendEmail(d)"
                                        title="Send Email">
                                        <EnvelopeIcon class="w-4 h-4" />
                                    </button>
                                    <button @click="sendOnboardingSms(d, $event)"
                                        class="p-1.5 border border-gray-200 rounded-lg transition-colors"
                                        :class="canSendSms(d) ? 'hover:bg-gray-50 text-gray-600' : 'opacity-40 cursor-not-allowed text-gray-400'"
                                        :disabled="!canSendSms(d)"
                                        title="Send SMS">
                                        <DevicePhoneMobileIcon class="w-4 h-4" />
                                    </button>
                                    <Link :href="`/admin/operations/designers/${d.id}/edit`"
                                        class="p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition-colors"
                                        title="Edit">
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="designers.last_page > 1" class="border-t border-gray-100 px-5 py-3 flex items-center justify-between text-sm text-gray-500">
                    <span>{{ designers.from }}–{{ designers.to }} of {{ designers.total }} designers</span>
                    <div class="flex gap-1">
                        <Link v-if="designers.prev_page_url" :href="designers.prev_page_url"
                            class="px-3 py-1 border border-gray-200 rounded-lg hover:bg-gray-50">← Previous</Link>
                        <Link v-if="designers.next_page_url" :href="designers.next_page_url"
                            class="px-3 py-1 border border-gray-200 rounded-lg hover:bg-gray-50">Next →</Link>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>

    <!-- Modal: Historial de Check-ins -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
            leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="checkinDesigner" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="checkinDesigner = null"></div>

                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-2">
                    <div v-if="checkinDesigner" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">

                        <!-- Header -->
                        <div class="bg-black px-6 py-5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-full overflow-hidden flex-shrink-0 border-2 border-white/20">
                                        <img v-if="storageUrl(checkinDesigner.profile_picture)"
                                            :src="storageUrl(checkinDesigner.profile_picture)"
                                            class="w-full h-full object-cover" />
                                        <div v-else class="w-full h-full bg-white/10 flex items-center justify-center text-sm font-bold text-white">
                                            {{ checkinDesigner.first_name?.[0] }}{{ checkinDesigner.last_name?.[0] }}
                                        </div>
                                    </div>
                                    <div>
                                        <p class="font-bold text-white text-base leading-tight">
                                            {{ checkinDesigner.first_name }} {{ checkinDesigner.last_name }}
                                        </p>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                                            <p class="text-white/60 text-xs">
                                                {{ checkinPasses(checkinDesigner).length }} check-in{{ checkinPasses(checkinDesigner).length !== 1 ? 's' : '' }} recorded
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <button @click="checkinDesigner = null"
                                    class="w-8 h-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-colors text-white">
                                    <XMarkIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Timeline de check-ins -->
                        <div class="p-5 space-y-0 max-h-[60vh] overflow-y-auto">
                            <div v-for="(pass, idx) in checkinPasses(checkinDesigner)" :key="pass.id"
                                class="relative flex gap-4">
                                <!-- Línea de timeline -->
                                <div class="flex flex-col items-center flex-shrink-0">
                                    <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center shadow-sm flex-shrink-0 z-10">
                                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </div>
                                    <div v-if="idx < checkinPasses(checkinDesigner).length - 1"
                                        class="w-px flex-1 bg-gray-200 my-1 min-h-[20px]"></div>
                                </div>

                                <!-- Contenido -->
                                <div class="pb-5 flex-1 min-w-0">
                                    <div class="bg-gray-50 border border-gray-100 rounded-xl p-3.5 hover:border-gray-200 transition-colors">
                                        <!-- Evento -->
                                        <div class="flex items-start justify-between gap-2 mb-2.5">
                                            <p class="font-semibold text-gray-900 text-sm leading-tight truncate">{{ pass.event?.name ?? 'Event' }}</p>
                                        </div>

                                        <!-- Fecha y hora del check-in -->
                                        <div class="flex items-center gap-3 mb-2.5">
                                            <div class="flex items-center gap-1.5">
                                                <div class="w-6 h-6 rounded-lg bg-blue-50 flex items-center justify-center">
                                                    <svg class="w-3 h-3 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5" />
                                                    </svg>
                                                </div>
                                                <span class="text-xs text-gray-700 font-medium">{{ fmtDate(pass.checked_in_at) }}</span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <div class="w-6 h-6 rounded-lg bg-green-50 flex items-center justify-center">
                                                    <svg class="w-3 h-3 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                                <span class="text-xs font-bold text-green-600">{{ fmtTime(pass.checked_in_at) }}</span>
                                            </div>
                                        </div>

                                        <!-- Time ago -->
                                        <div class="flex items-center">
                                            <span class="text-[10px] text-gray-400 italic ml-auto">{{ timeAgo(pass.checked_in_at) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between bg-gray-50">
                            <Link :href="`/admin/operations/designers/${checkinDesigner.id}`"
                                class="text-sm font-semibold text-black hover:underline underline-offset-2">
                                View full profile →
                            </Link>
                            <button @click="checkinDesigner = null"
                                class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">
                                Close
                            </button>
                        </div>

                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>

    <!-- Modal: Eventos del diseñador -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="selectedDesigner" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="selectedDesigner = null"></div>
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-2">
                    <div v-if="selectedDesigner" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">

                        <!-- Header negro -->
                        <div class="bg-black px-6 py-5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-full overflow-hidden flex-shrink-0 border-2 border-white/20">
                                        <img v-if="storageUrl(selectedDesigner.profile_picture)"
                                            :src="storageUrl(selectedDesigner.profile_picture)"
                                            class="w-full h-full object-cover" />
                                        <div v-else class="w-full h-full bg-white/10 flex items-center justify-center text-sm font-bold text-white">
                                            {{ selectedDesigner.first_name?.[0] }}{{ selectedDesigner.last_name?.[0] }}
                                        </div>
                                    </div>
                                    <div>
                                        <p class="font-bold text-white text-base leading-tight">
                                            {{ selectedDesigner.first_name }} {{ selectedDesigner.last_name }}
                                        </p>
                                        <p class="text-white/50 text-xs mt-0.5">
                                            {{ selectedDesigner.events_as_designer.length }}
                                            event{{ selectedDesigner.events_as_designer.length !== 1 ? 's' : '' }} assigned
                                        </p>
                                    </div>
                                </div>
                                <button @click="selectedDesigner = null"
                                    class="w-8 h-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-colors text-white">
                                    <XMarkIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Lista de eventos -->
                        <div class="p-5 space-y-3 max-h-[60vh] overflow-y-auto">
                            <div v-for="(ev, idx) in selectedDesigner.events_as_designer" :key="ev.id"
                                class="border border-gray-100 rounded-xl p-4 hover:border-gray-200 transition-colors bg-gray-50/50">

                                <!-- Nombre evento + estado -->
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-black text-white text-xs font-bold flex items-center justify-center">
                                            {{ idx + 1 }}
                                        </span>
                                        <p class="font-semibold text-gray-900 text-sm leading-tight truncate">{{ ev.name }}</p>
                                    </div>
                                    <span :class="eventStatusBadge(ev.status)"
                                        class="flex-shrink-0 text-xs font-medium px-2 py-0.5 rounded-full">
                                        {{ eventStatusLabel(ev.status) }}
                                    </span>
                                </div>

                                <!-- Grid de datos -->
                                <div class="grid grid-cols-2 gap-x-4 gap-y-2.5">

                                    <!-- Estado en evento -->
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3.5 h-3.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-400 leading-none mb-0.5">Status</p>
                                            <span :class="pivotStatusBadge(ev.pivot?.status)"
                                                class="text-xs font-medium px-1.5 py-0.5 rounded-full">
                                                {{ pivotStatusLabel(ev.pivot?.status) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Looks -->
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3.5 h-3.5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-400 leading-none mb-0.5">Looks</p>
                                            <p class="text-xs font-semibold text-gray-800">{{ ev.pivot?.looks ?? '—' }}</p>
                                        </div>
                                    </div>

                                    <!-- Paquete -->
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-[#D4AF37]/10 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3.5 h-3.5 text-[#D4AF37]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-400 leading-none mb-0.5">Package</p>
                                            <p class="text-xs font-semibold text-gray-800">
                                                {{ ev.pivot?.package_price ? '$' + Number(ev.pivot.package_price).toLocaleString() : '—' }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Casting habilitado -->
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5 col-span-2">
                                        <span v-for="feat in [
                                            { label: 'Casting', value: ev.pivot?.model_casting_enabled },
                                            { label: 'Media', value: ev.pivot?.media_package },
                                            { label: 'BG', value: ev.pivot?.custom_background },
                                            { label: 'Tickets', value: ev.pivot?.courtesy_tickets },
                                        ]" :key="feat.label"
                                            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-semibold"
                                            :class="feat.value ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'">
                                            <span class="w-1 h-1 rounded-full" :class="feat.value ? 'bg-green-500' : 'bg-gray-300'"></span>
                                            {{ feat.label }}
                                        </span>
                                    </div>

                                </div>

                                <!-- Notes -->
                                <p v-if="ev.pivot?.notes" class="mt-3 pt-3 border-t border-gray-100 text-xs text-gray-500">
                                    {{ ev.pivot.notes }}
                                </p>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between bg-gray-50">
                            <Link :href="`/admin/operations/designers/${selectedDesigner.id}`"
                                class="text-sm font-semibold text-black hover:underline underline-offset-2">
                                View full profile →
                            </Link>
                            <button @click="selectedDesigner = null"
                                class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">
                                Close
                            </button>
                        </div>

                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>

    <!-- Modal: Materials progress per event -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="showMaterialsModal && materialsModalDesigner" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showMaterialsModal = false"></div>
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-2">
                    <div v-if="showMaterialsModal" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">

                        <div class="bg-black px-6 py-5">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-white text-base leading-tight">Materials progress</p>
                                    <p class="text-white/50 text-xs mt-0.5">
                                        {{ materialsModalDesigner.first_name }} {{ materialsModalDesigner.last_name }}
                                        · {{ materialsModalDesigner.events_as_designer?.length ?? 0 }} events
                                    </p>
                                </div>
                                <button @click="showMaterialsModal = false"
                                    class="w-8 h-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-colors text-white">
                                    <XMarkIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <div class="p-5 space-y-3 max-h-[60vh] overflow-y-auto">
                            <div v-for="ev in materialsModalDesigner.events_as_designer ?? []" :key="ev.id"
                                class="border border-gray-100 rounded-xl p-4 bg-gray-50/50">
                                <div class="mb-2">
                                    <p class="font-semibold text-gray-900 text-sm leading-snug">{{ ev.name }}</p>
                                    <p v-if="ev.start_date" class="text-[11px] text-gray-400 mt-0.5">Starts {{ fmtDate(ev.start_date) }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                        <div :class="progressColor(materialsProgressForEvent(materialsModalDesigner.designer_materials, ev.id))"
                                            class="h-full rounded-full transition-all"
                                            :style="{ width: materialsProgressForEvent(materialsModalDesigner.designer_materials, ev.id) + '%' }"></div>
                                    </div>
                                    <span class="text-xs font-medium text-gray-700 flex-shrink-0">
                                        {{ materialsProgressForEvent(materialsModalDesigner.designer_materials, ev.id) }}%
                                    </span>
                                </div>
                                <p class="text-[11px] text-gray-500 mt-1.5">
                                    {{ (materialsModalDesigner.designer_materials ?? []).filter(m => m.event_id === ev.id && (m.status === 'completed' || m.status === 'confirmed')).length }}
                                    of
                                    {{ (materialsModalDesigner.designer_materials ?? []).filter(m => m.event_id === ev.id).length }}
                                    materials completed
                                </p>
                            </div>
                            <p v-if="!(materialsModalDesigner.events_as_designer ?? []).length" class="text-center text-sm text-gray-400 italic py-4">
                                No events assigned.
                            </p>
                        </div>

                        <div class="border-t border-gray-100 px-5 py-3 flex justify-end">
                            <button @click="showMaterialsModal = false"
                                class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">
                                Close
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>

    <!-- Modal: Importar Excel -->
    <Teleport to="body">
        <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" @click="showImportModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
                <!-- Header -->
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold text-gray-900">Import Designers from Excel</h3>
                    <button @click="showImportModal = false" class="text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <!-- Formato esperado -->
                <div class="bg-gray-50 rounded-xl p-4 mb-5 text-xs text-gray-600">
                    <p class="font-semibold text-gray-800 mb-2">Excel columns:</p>
                    <div class="grid grid-cols-2 gap-1">
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">email</span> <span class="text-red-500">*required</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">first_name</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">last_name</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">phone</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">brand_name</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">country</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">website</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">instagram</span></span>
                    </div>
                    <p class="mt-2 text-gray-500">Formats: <strong>.xlsx, .xls, .csv</strong></p>
                </div>

                <!-- Event selector -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Assign to an event <span class="text-gray-400 font-normal">(optional)</span></label>
                    <select v-model="importForm.event_id"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                        <option value="">— Do not assign to event —</option>
                        <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                    <p v-if="importForm.event_id" class="mt-1.5 text-xs text-blue-600">
                        All imported designers will be assigned to this event with default materials and display.
                    </p>
                    <p v-else class="mt-1.5 text-xs text-gray-400">
                        If you don't select an event, designers are created without assignment.
                    </p>
                </div>

                <!-- File input -->
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select file</label>
                    <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv"
                        @change="handleFileChange"
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

    <!-- Modal: Plan de pagos requerido -->
    <Teleport to="body">
        <div v-if="showNoPlanModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="showNoPlanModal = false">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
                <div class="text-center">
                    <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Payment plan required</h3>
                    <p class="text-sm text-gray-500 mb-1">
                        Cannot change designer
                        <span class="font-semibold text-gray-800">{{ noPlanDesigner?.first_name }} {{ noPlanDesigner?.last_name }}</span>
                        to <span class="font-medium text-yellow-700">Pending</span>.
                    </p>
                    <p class="text-sm text-gray-500 mb-6">
                        Accounting must assign a payment plan before changing status.
                    </p>
                    <button @click="showNoPlanModal = false"
                        class="px-6 py-2.5 bg-black text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors">
                        Got it
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal: Evento requerido (incluye show y fitting) -->
    <Teleport to="body">
        <div v-if="showNoEventModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="showNoEventModal = false">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
                <div class="text-center">
                    <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Incomplete event</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Cannot change designer
                        <span class="font-semibold text-gray-800">{{ noEventDesigner?.first_name }} {{ noEventDesigner?.last_name }}</span>
                        to <span class="font-medium text-yellow-700">Pending</span>:
                    </p>
                    <ul class="text-left text-sm space-y-2 mb-6">
                        <li v-for="(msg, i) in noEventMissing" :key="i" class="flex items-start gap-2">
                            <span class="mt-0.5 w-4 h-4 rounded-full bg-red-100 text-red-500 flex items-center justify-center flex-shrink-0 text-xs font-bold">&times;</span>
                            <span class="text-gray-600">{{ msg }}</span>
                        </li>
                    </ul>
                    <button @click="showNoEventModal = false"
                        class="px-6 py-2.5 bg-black text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors">
                        Got it
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal: Communication Log (Email / SMS) -->
    <Teleport to="body">
        <div v-if="showCommModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="showCommModal = false">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ commModalType === 'email' ? 'Email History' : 'SMS History' }}
                        </h3>
                        <p class="text-sm text-gray-500">{{ commModalDesigner?.first_name }} {{ commModalDesigner?.last_name }}</p>
                    </div>
                    <button @click="showCommModal = false" class="text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <!-- Empty state -->
                <div v-if="!getCommLogs(commModalDesigner, commModalType).length" class="text-center py-8 text-gray-400 text-sm">
                    No {{ commModalType === 'email' ? 'emails' : 'SMS' }} sent yet
                </div>

                <!-- Log list -->
                <div v-else class="space-y-3 max-h-80 overflow-y-auto">
                    <div v-for="log in getCommLogs(commModalDesigner, commModalType)" :key="log.id"
                        class="border border-gray-100 rounded-xl p-4"
                        :class="log.status === 'failed' ? 'bg-red-50/50' : 'bg-gray-50'">
                        <div class="flex items-center justify-between mb-2">
                            <span :class="commStatusClass(log.status)"
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                                {{ commStatusLabel(log.status) }}
                            </span>
                            <span class="text-xs text-gray-400">
                                {{ new Date(log.sent_at ?? log.created_at).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' }) }}
                            </span>
                        </div>
                        <div v-if="log.sender" class="text-xs text-gray-500">
                            Sent by <span class="font-medium text-gray-700">{{ log.sender.first_name }} {{ log.sender.last_name }}</span>
                        </div>
                        <div v-if="log.error_message" class="mt-2 text-xs text-red-600 bg-red-100 rounded-lg p-2">
                            {{ log.error_message }}
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex justify-end">
                    <button @click="showCommModal = false"
                        class="px-5 py-2 bg-black text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal: Selección de evento para EMAIL -->
    <Teleport to="body">
        <div v-if="emailModalDesigner" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="emailModalDesigner = null">
            <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden">
                <div class="bg-black px-6 py-5">
                    <h3 class="text-base font-semibold text-white">Send Onboarding Email</h3>
                    <p class="text-white/60 text-sm mt-0.5">{{ emailModalDesigner.first_name }} {{ emailModalDesigner.last_name }}</p>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Select Event</label>
                        <select v-model="emailModalEventId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400 bg-white">
                            <option v-for="ev in emailModalDesigner.events_as_designer" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                        </select>
                    </div>
                    <!-- Attendees note -->
                    <div class="flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm text-amber-700">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                        <span>The email will also be sent to <strong>registered attendees</strong> for this event.</span>
                    </div>
                </div>
                <div class="px-6 pb-6 flex gap-3">
                    <button @click="confirmSendEmail"
                        :disabled="!emailModalEventId"
                        class="flex-1 px-4 py-2.5 bg-black text-white text-sm font-medium rounded-lg hover:bg-gray-800 disabled:opacity-40 transition-colors">
                        Send Email
                    </button>
                    <button @click="emailModalDesigner = null"
                        class="px-4 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal: Selección de evento para SMS -->
    <Teleport to="body">
        <div v-if="smsModalDesigner" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="smsModalDesigner = null">
            <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden">
                <div class="bg-black px-6 py-5">
                    <h3 class="text-base font-semibold text-white">Send Onboarding SMS</h3>
                    <p class="text-white/60 text-sm mt-0.5">{{ smsModalDesigner.first_name }} {{ smsModalDesigner.last_name }}</p>
                </div>
                <div class="p-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Select Event</label>
                    <select v-model="smsModalEventId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400 bg-white">
                        <option v-for="ev in smsModalDesigner.events_as_designer" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                    </select>
                </div>
                <div class="px-6 pb-6 flex gap-3">
                    <button @click="confirmSendSms"
                        :disabled="!smsModalEventId"
                        class="flex-1 px-4 py-2.5 bg-black text-white text-sm font-medium rounded-lg hover:bg-gray-800 disabled:opacity-40 transition-colors">
                        Send SMS
                    </button>
                    <button @click="smsModalDesigner = null"
                        class="px-4 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal info Email masivo -->
    <Teleport to="body">
        <div v-if="showEmailInfoModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" @click="showEmailInfoModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <EnvelopeIcon class="w-5 h-5 text-amber-600" />
                        </div>
                        <h3 class="text-base font-semibold text-gray-900">How does bulk email sending work?</h3>
                    </div>
                    <button @click="showEmailInfoModal = false" class="text-gray-400 hover:text-gray-600 ml-2">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>
                <ul class="space-y-3 text-sm text-gray-600">
                    <li class="flex items-start gap-2">
                        <span class="w-1.5 h-1.5 bg-amber-400 rounded-full mt-1.5 flex-shrink-0"></span>
                        <span>Only sent to designers with Pending status who have not previously received an onboarding email.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-1.5 h-1.5 bg-amber-400 rounded-full mt-1.5 flex-shrink-0"></span>
                        <span>The email includes all the designer's assigned events with their shows and times.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-1.5 h-1.5 bg-amber-400 rounded-full mt-1.5 flex-shrink-0"></span>
                        <span>If a designer has 2 events, the email shows both with their corresponding shows.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-1.5 h-1.5 bg-amber-400 rounded-full mt-1.5 flex-shrink-0"></span>
                        <span>Sending is queued — it may take a few seconds depending on volume.</span>
                    </li>
                </ul>
                <button @click="showEmailInfoModal = false"
                    class="mt-5 w-full py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors">
                    Got it
                </button>
            </div>
        </div>
    </Teleport>

    <!-- Modal info SMS masivo -->
    <Teleport to="body">
        <div v-if="showSmsInfoModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" @click="showSmsInfoModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <DevicePhoneMobileIcon class="w-5 h-5 text-green-600" />
                        </div>
                        <h3 class="text-base font-semibold text-gray-900">How does bulk SMS sending work?</h3>
                    </div>
                    <button @click="showSmsInfoModal = false" class="text-gray-400 hover:text-gray-600 ml-2">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>
                <ul class="space-y-3 text-sm text-gray-600">
                    <li class="flex items-start gap-2">
                        <span class="w-1.5 h-1.5 bg-green-400 rounded-full mt-1.5 flex-shrink-0"></span>
                        <span>Only sent to designers with Pending status who have a phone with country code (+1...) and have not previously received SMS.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-1.5 h-1.5 bg-green-400 rounded-full mt-1.5 flex-shrink-0"></span>
                        <span>The SMS mentions all the designer's assigned events.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-1.5 h-1.5 bg-green-400 rounded-full mt-1.5 flex-shrink-0"></span>
                        <span>The message includes app access credentials and download links.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-1.5 h-1.5 bg-green-400 rounded-full mt-1.5 flex-shrink-0"></span>
                        <span>Requires Twilio balance. If there's no balance, sending will fail.</span>
                    </li>
                </ul>
                <button @click="showSmsInfoModal = false"
                    class="mt-5 w-full py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors">
                    Got it
                </button>
            </div>
        </div>
    </Teleport>
</template>
