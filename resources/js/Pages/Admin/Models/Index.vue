<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { EnvelopeIcon, PencilSquareIcon, ArrowUpTrayIcon, ArrowDownTrayIcon, XMarkIcon, StarIcon as StarOutline, InformationCircleIcon, DevicePhoneMobileIcon } from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';
import { computed } from 'vue';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

const props = defineProps({
    models:            Object,
    events:            Array,
    designers:         Array,
    filters:           Object,
    castingTimes:      Array,
    pendingEmailCount:  Number,
    pendingSmsCount:    Number,
    rejectedEmailCount: Number,
    rejectedSmsCount:   Number,
    twilioBalance:      Object,
    stats:              Object,
});

const search         = ref(props.filters.search         ?? '');
const event          = ref(props.filters.event          ?? '');
const compcard       = ref(props.filters.compcard       ?? '');
const gender         = ref(props.filters.gender         ?? '');
const ethnicity      = ref(props.filters.ethnicity      ?? '');
const is_agency      = ref(props.filters.is_agency      ?? '');
const is_top         = ref(props.filters.is_top         ?? '');
const email_sent     = ref(props.filters.email_sent     ?? '');
const test_model     = ref(props.filters.test_model     ?? '');
const casting_time   = ref(props.filters.casting_time   ?? '');
const casting_status = ref(props.filters.casting_status ?? '');
const designer       = ref(props.filters.designer       ?? '');
const status         = ref(props.filters.status         ?? '');
const sort_name      = ref(props.filters.sort_name      ?? '');
const merch          = ref(props.filters.merch          ?? '');
const model_kit      = ref(props.filters.model_kit      ?? '');
const perPage        = ref(props.filters.per_page       ?? '20');

// Date range filters — value is [Date, Date] or null
function parseRange(from, to) {
    if (!from && !to) return null;
    return [from ? new Date(from + 'T00:00:00') : new Date(), to ? new Date(to + 'T00:00:00') : new Date()];
}
const registeredRange = ref(parseRange(props.filters.registered_from, props.filters.registered_to));
const checkinRange    = ref(parseRange(props.filters.checkin_from, props.filters.checkin_to));

function fmtDate(d) {
    if (!d) return null;
    const dt = new Date(d);
    return dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + String(dt.getDate()).padStart(2, '0');
}

// Presets para los date pickers
const today = new Date();
const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
const startOfLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
const endOfLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
const startOfYear = new Date(today.getFullYear(), 0, 1);
const yesterday = new Date(today); yesterday.setDate(today.getDate() - 1);

const datePresets = [
    { label: 'Hoy', value: [today, today] },
    { label: 'Ayer', value: [yesterday, yesterday] },
    { label: 'Este Mes', value: [startOfMonth, endOfMonth] },
    { label: 'Mes pasado', value: [startOfLastMonth, endOfLastMonth] },
    { label: 'Este año', value: [startOfYear, today] },
];

function formatDateRange(dates) {
    if (!dates) return '';
    const fmt = (d) => {
        const dt = new Date(d);
        return String(dt.getDate()).padStart(2, '0') + '/' + String(dt.getMonth() + 1).padStart(2, '0') + '/' + dt.getFullYear();
    };
    if (Array.isArray(dates)) return dates.map(fmt).join(' - ');
    return fmt(dates);
}

let timer = null;
function applyFilters() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/admin/models', {
            search:          search.value         || undefined,
            event:           event.value          || undefined,
            compcard:        compcard.value       || undefined,
            gender:          gender.value         || undefined,
            ethnicity:       ethnicity.value      || undefined,
            is_agency:       is_agency.value      || undefined,
            is_top:          is_top.value         || undefined,
            email_sent:      email_sent.value     || undefined,
            test_model:      test_model.value     || undefined,
            casting_time:    casting_time.value   || undefined,
            casting_status:  casting_status.value || undefined,
            designer:        designer.value       || undefined,
            status:          status.value         || undefined,
            sort_name:       sort_name.value      || undefined,
            merch:           merch.value          || undefined,
            model_kit:       model_kit.value      || undefined,
            per_page:        perPage.value != 20  ? perPage.value : undefined,
            registered_from: registeredRange.value ? fmtDate(registeredRange.value[0]) : undefined,
            registered_to:   registeredRange.value ? fmtDate(registeredRange.value[1]) : undefined,
            checkin_from:    checkinRange.value    ? fmtDate(checkinRange.value[0])    : undefined,
            checkin_to:      checkinRange.value    ? fmtDate(checkinRange.value[1])    : undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}
watch([search, event, compcard, gender, ethnicity, is_agency, is_top, email_sent, test_model, casting_time, casting_status, designer, status, sort_name, merch, model_kit, perPage, registeredRange, checkinRange], applyFilters);

function toggleSortName() {
    if (sort_name.value === 'asc') sort_name.value = 'desc';
    else if (sort_name.value === 'desc') sort_name.value = '';
    else sort_name.value = 'asc';
}

// Limpiar horario de casting y diseñador cuando cambia el evento
watch(event, () => {
    casting_time.value = '';
    designer.value = '';
});

// --- Export Excel ---
const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (search.value)         params.set('search',         search.value);
    if (event.value)          params.set('event',          event.value);
    if (compcard.value)       params.set('compcard',       compcard.value);
    if (gender.value)         params.set('gender',         gender.value);
    if (ethnicity.value)      params.set('ethnicity',      ethnicity.value);
    if (is_agency.value)      params.set('is_agency',      is_agency.value);
    if (is_top.value)         params.set('is_top',         is_top.value);
    if (email_sent.value)     params.set('email_sent',     email_sent.value);
    if (test_model.value)     params.set('test_model',     test_model.value);
    if (casting_time.value)   params.set('casting_time',   casting_time.value);
    if (casting_status.value) params.set('casting_status', casting_status.value);
    if (designer.value)       params.set('designer',       designer.value);
    if (status.value)         params.set('status',         status.value);
    if (merch.value)          params.set('merch',          merch.value);
    if (model_kit.value)      params.set('model_kit',      model_kit.value);
    const qs = params.toString();
    return '/admin/models/export' + (qs ? '?' + qs : '');
});

// --- Import Excel ---
const showImportModal = ref(false);
const importForm = useForm({ file: null, event_id: '' });
const fileInput = ref(null);

function handleFileChange(e) {
    importForm.file = e.target.files[0] ?? null;
}

function submitImport() {
    importForm.post('/admin/models/import', {
        forceFormData: true,
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

// --- Acciones por fila ---
function isReadyForPending(m) {
    return m.status === 'applicant'
        && (m.events_as_model_with_casting ?? []).some(e => e.pivot?.casting_time);
}

function canSendEmail(m) {
    const hasEventWithCasting = (m.events_as_model_with_casting ?? []).some(e => e.pivot?.casting_time);
    return m.status === 'pending' && !!m.email && hasEventWithCasting;
}

// Modal historial de correos
const emailHistoryModel = ref(null);

function openEmailHistory(m, e) {
    e.stopPropagation();
    emailHistoryModel.value = m;
}

function getEmailLogs(m) {
    return (m?.communication_logs ?? [])
        .filter(l => l.channel === 'welcome_email')
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
}

function commStatusLabel(status) {
    return { queued: 'En cola', sent: 'Enviado', failed: 'Fallido' }[status] ?? status;
}

function commStatusClass(status) {
    return {
        queued: 'bg-yellow-100 text-yellow-700',
        sent:   'bg-green-100 text-green-700',
        failed: 'bg-red-100 text-red-700',
    }[status] ?? 'bg-gray-100 text-gray-600';
}

// Modal de confirmación para email
const emailModalModel   = ref(null);
const showEmailInfoModal = ref(false);

function openEmailModal(m, e) {
    e.stopPropagation();
    emailModalModel.value = m;
}

function scheduledEvents(m) {
    return (m?.events_as_model_with_casting ?? []).filter(ev => ev.pivot?.casting_status === 'scheduled');
}

function confirmSendWelcomeEmail() {
    const model = emailModalModel.value;
    router.post(`/admin/models/${model.id}/send-welcome-email`, {}, { preserveScroll: true });
    emailModalModel.value = null;
}

const statusAlertModel = ref(null);

function updateModelStatus(m, newStatus) {
    if (newStatus === 'pending') {
        const hasEventWithCasting = (m.events_as_model_with_casting ?? []).some(e => e.pivot?.casting_time);
        if (!hasEventWithCasting) {
            statusAlertModel.value = m;
            // Revert select to original value
            nextTick(() => {
                const sel = document.querySelector(`[data-status-select="${m.id}"]`);
                if (sel) sel.value = m.status;
            });
            return;
        }
    }
    router.patch(`/admin/models/${m.id}/status`, { status: newStatus }, { preserveScroll: true });
}

function toggleTop(m, e) {
    e.stopPropagation();
    router.post(`/admin/models/${m.id}/toggle-top`, {}, { preserveScroll: true });
}

// --- Modal eventos ---
const selectedModel = ref(null);

function openEventsModal(model, e) {
    e.stopPropagation();
    selectedModel.value = model;
}

function updateCastingStatus(model, eventId, newStatus) {
    router.patch(`/admin/models/${model.id}/events/${eventId}/casting-status`,
        { casting_status: newStatus },
        {
            preserveScroll: true,
            onSuccess: () => {
                const fresh = props.models.data.find(m => m.id === model.id);
                if (fresh) selectedModel.value = fresh;
            },
        }
    );
}

function updateModelTag(model, eventId, newTag) {
    router.patch(`/admin/models/${model.id}/events/${eventId}/model-tag`,
        { model_tag: newTag || null },
        {
            preserveScroll: true,
            onSuccess: () => {
                const fresh = props.models.data.find(m => m.id === model.id);
                if (fresh) selectedModel.value = fresh;
            },
        }
    );
}

function sendOnboarding(model, eventId) {
    if (!confirm('¿Enviar email de onboarding personalizado?')) return;
    router.post(`/admin/models/${model.id}/events/${eventId}/send-onboarding`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            const fresh = props.models.data.find(m => m.id === model.id);
            if (fresh) selectedModel.value = fresh;
        },
    });
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
    return {
        draft:     'Borrador',
        published: 'Publicado',
        active:    'Activo',
        completed: 'Completado',
        cancelled: 'Cancelado',
    }[status] ?? status;
}

function pivotStatusBadge(status) {
    return {
        invited:    'bg-yellow-100 text-yellow-700',
        confirmed:  'bg-green-100 text-green-700',
        rejected:   'bg-red-100 text-red-600',
        checked_in: 'bg-blue-100 text-blue-700',
    }[status] ?? 'bg-gray-100 text-gray-600';
}

function pivotStatusLabel(status) {
    return {
        invited:    'Invitada',
        confirmed:  'Confirmada',
        rejected:   'Rechazada',
        checked_in: 'Check-in',
    }[status] ?? status;
}

// Formatear time HH:MM:SS → HH:MM
function formatTime(t) {
    if (!t) return '—';
    return t.length > 5 ? t.substring(0, 5) : t;
}

function castingStatusInfo(status, gender = null) {
    if (status === 'rejected') {
        const label = gender === 'male' ? 'Rechazado' : 'Rechazada';
        return { label, color: 'text-red-500', dot: 'bg-red-400' };
    }
    return {
        scheduled:  { label: 'Agendada',        color: 'text-gray-500',  dot: 'bg-gray-400' },
        checked_in: { label: 'Check-in',         color: 'text-blue-600',  dot: 'bg-blue-500' },
        selected:   { label: 'Seleccionada',      color: 'text-green-600', dot: 'bg-green-500' },
        no_show:    { label: 'No se presentó',   color: 'text-red-500',   dot: 'bg-red-400' },
    }[status] ?? { label: status ?? '—', color: 'text-gray-400', dot: 'bg-gray-300' };
}

// --- Números de participación ---
function participationNumbers(model) {
    const events = model.events_as_model_with_casting ?? [];
    return events
        .filter(ev => ev.pivot?.participation_number != null)
        .map(ev => ({
            number: ev.pivot.participation_number,
            eventName: ev.name,
            eventStatus: ev.status,
        }));
}

const participationModel = ref(null);

function openParticipationModal(model, e) {
    e.stopPropagation();
    participationModel.value = model;
}

// --- Shows por evento ---
function eventShows(model, eventId) {
    return model.shows_by_event?.[eventId] ?? [];
}

// --- Fittings por evento ---
function eventFittings(model, eventId) {
    return model.fittings_by_event?.[eventId] ?? [];
}

// --- Check-ins ---
const checkinModel = ref(null);

function checkinEvents(model) {
    const events = model.events_as_model_with_casting ?? [];
    return events
        .filter(ev => ev.pivot?.casting_checked_in_at)
        .sort((a, b) => new Date(b.pivot.casting_checked_in_at) - new Date(a.pivot.casting_checked_in_at));
}

function openCheckinModal(model, e) {
    e.stopPropagation();
    checkinModel.value = model;
}

function fmtCheckinDate(dt) {
    if (!dt) return null;
    return new Date(dt).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmtCheckinTime(dt) {
    if (!dt) return null;
    return new Date(dt).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
}

function timeAgo(dt) {
    if (!dt) return '';
    const diff = Date.now() - new Date(dt).getTime();
    const mins  = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days  = Math.floor(diff / 86400000);
    if (mins < 60)   return `hace ${mins}m`;
    if (hours < 24)  return `hace ${hours}h`;
    if (days < 30)   return `hace ${days}d`;
    return fmtCheckinDate(dt);
}

// --- Send pending emails/SMS ---
function sendPendingEmails() {
    if (!confirm(`¿Enviar email de onboarding a ${props.pendingEmailCount} modelo(s) pendiente(s)?`)) return;
    router.post('/admin/models/send-pending-emails', {}, { preserveScroll: true });
}

function sendPendingSms() {
    if (!confirm(`¿Enviar SMS de onboarding a ${props.pendingSmsCount} modelo(s) pendiente(s)?`)) return;
    router.post('/admin/models/send-bulk-onboarding-sms', {}, { preserveScroll: true });
}

function sendBulkRejectionEmails() {
    if (!confirm(`¿Enviar email de rechazo a ${props.rejectedEmailCount} modelo(s) rechazada(s)?`)) return;
    router.post('/admin/models/send-bulk-rejection-emails', {}, { preserveScroll: true });
}

function sendBulkRejectionSms() {
    if (!confirm(`¿Enviar SMS de rechazo a ${props.rejectedSmsCount} modelo(s) rechazada(s)?`)) return;
    router.post('/admin/models/send-bulk-rejection-sms', {}, { preserveScroll: true });
}

// --- Helpers ---
function progressColor(pct) {
    if (pct === 100) return 'bg-green-500';
    if (pct >= 50)   return 'bg-yellow-400';
    return 'bg-gray-300';
}

function genderLabel(g) {
    return { female: 'Femenino', male: 'Masculino', non_binary: 'No binario' }[g] ?? g ?? '—';
}

function statusBadge(status) {
    return {
        active:    'bg-green-100 text-green-700',
        inactive:  'bg-red-100 text-red-700',
        rejected:  'bg-orange-100 text-orange-700',
        pending:   'bg-yellow-100 text-yellow-700',
        applicant: 'bg-purple-100 text-purple-700',
    }[status] ?? 'bg-gray-100 text-gray-600';
}

function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}

function fmtLogin(dt) {
    if (!dt) return null;
    const d = new Date(dt);
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
        + ' ' + d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
}

function fmtEmailSent(dt) {
    if (!dt) return null;
    const d = new Date(dt);
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
}

function onNotification(e) {
    const type = e.detail?.data?.type;
    if (type === 'new_model_registered') {
        router.reload({ preserveScroll: true });
    }
}

onMounted(() => window.addEventListener('notification:received', onNotification));
onUnmounted(() => window.removeEventListener('notification:received', onNotification));
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Modelos</h2>
        </template>

        <div>
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Modelos</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ models.total }} modelos registradas</p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Botón enviar correos pendientes -->
                    <div v-if="pendingEmailCount > 0" class="flex items-center gap-1">
                        <button @click="sendPendingEmails"
                            class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors text-gray-700">
                            <EnvelopeIcon class="w-4 h-4 text-gray-500" />
                            Enviar correos
                            <span class="bg-amber-100 text-amber-700 text-xs font-bold px-1.5 py-0.5 rounded-full">{{ pendingEmailCount }}</span>
                        </button>
                        <button @click="showEmailInfoModal = true"
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            title="¿Cómo funciona el envío masivo?">
                            <InformationCircleIcon class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- SMS onboarding -->
                    <div v-if="pendingSmsCount > 0" class="flex items-center gap-1">
                        <button @click="sendPendingSms"
                            class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors text-gray-700">
                            <DevicePhoneMobileIcon class="w-4 h-4 text-gray-500" />
                            Enviar SMS
                            <span class="bg-green-100 text-green-700 text-xs font-bold px-1.5 py-0.5 rounded-full">{{ pendingSmsCount }}</span>
                        </button>
                    </div>

                    <!-- Email rechazo masivo -->
                    <div v-if="rejectedEmailCount > 0" class="flex items-center gap-1">
                        <button @click="sendBulkRejectionEmails"
                            class="flex items-center gap-2 px-4 py-2 border border-red-200 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors text-red-700">
                            <EnvelopeIcon class="w-4 h-4 text-red-400" />
                            Email rechazo
                            <span class="bg-red-100 text-red-700 text-xs font-bold px-1.5 py-0.5 rounded-full">{{ rejectedEmailCount }}</span>
                        </button>
                    </div>

                    <!-- SMS rechazo masivo -->
                    <div v-if="rejectedSmsCount > 0" class="flex items-center gap-1">
                        <button @click="sendBulkRejectionSms"
                            class="flex items-center gap-2 px-4 py-2 border border-red-200 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors text-red-700">
                            <DevicePhoneMobileIcon class="w-4 h-4 text-red-400" />
                            SMS rechazo
                            <span class="bg-red-100 text-red-700 text-xs font-bold px-1.5 py-0.5 rounded-full">{{ rejectedSmsCount }}</span>
                        </button>
                    </div>

                    <!-- Twilio Balance -->
                    <div v-if="twilioBalance" class="flex flex-col items-end px-3 py-1.5 border border-gray-200 rounded-lg bg-white">
                        <span class="text-[10px] text-gray-400 font-medium leading-tight">Twilio Balance</span>
                        <span class="text-sm font-bold text-gray-900 leading-tight">{{ twilioBalance.balance }} {{ twilioBalance.currency }}</span>
                    </div>

                    <!-- Botón exportar Excel -->
                    <a :href="exportUrl"
                        class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors text-gray-700">
                        <ArrowDownTrayIcon class="w-4 h-4 text-gray-500" />
                        Exportar Excel
                    </a>

                    <!-- Botón importar Excel -->
                    <button @click="showImportModal = true"
                        class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors text-gray-700">
                        <ArrowUpTrayIcon class="w-4 h-4 text-gray-500" />
                        Importar Excel
                    </button>

                    <Link href="/admin/models/create"
                        class="px-4 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors">
                        + Crear Modelo
                    </Link>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-8 gap-3 mb-6">
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats?.total ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Merch</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1">{{ stats?.merch ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Model Kit</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">{{ stats?.kit_paid ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Agencia</p>
                    <p class="text-2xl font-bold text-blue-600 mt-1">{{ stats?.agency ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Top</p>
                    <p class="text-2xl font-bold text-amber-500 mt-1">{{ stats?.top ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Normales</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats?.normal ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Hombres</p>
                    <p class="text-2xl font-bold text-indigo-600 mt-1">{{ stats?.male ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Mujeres</p>
                    <p class="text-2xl font-bold text-pink-500 mt-1">{{ stats?.female ?? 0 }}</p>
                </div>
            </div>

            <!-- Status Cards -->
            <div class="grid grid-cols-6 gap-3 mb-6">
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Check-in</p>
                    <p class="text-2xl font-bold text-teal-600 mt-1">{{ stats?.checkin ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Activas</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">{{ stats?.active ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Pendientes</p>
                    <p class="text-2xl font-bold text-yellow-600 mt-1">{{ stats?.pending ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Aplicantes</p>
                    <p class="text-2xl font-bold text-purple-600 mt-1">{{ stats?.applicant ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Rechazadas</p>
                    <p class="text-2xl font-bold text-orange-600 mt-1">{{ stats?.rejected ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Inactivas</p>
                    <p class="text-2xl font-bold text-red-600 mt-1">{{ stats?.inactive ?? 0 }}</p>
                </div>
            </div>

            <!-- Filtros -->
            <div class="flex flex-wrap gap-3 mb-6">
                <input v-model="search" type="text" placeholder="Buscar por nombre, email, teléfono, # participación..."
                    class="flex-1 min-w-48 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400" />

                <select v-model="event"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todos los eventos</option>
                    <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>

                <select v-model="designer"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todos los diseñadores</option>
                    <option v-for="d in designers" :key="d.id" :value="d.id">
                        {{ d.brand_name || d.name }}
                    </option>
                </select>

                <select v-model="gender"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todos los géneros</option>
                    <option value="female">Femenino</option>
                    <option value="male">Masculino</option>
                    <option value="non_binary">No binario</option>
                </select>

                <select v-model="ethnicity"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todas las etnias</option>
                    <option value="asian">Asiática</option>
                    <option value="black">Negra</option>
                    <option value="caucasian">Caucásica</option>
                    <option value="hispanic">Hispana / Latina</option>
                    <option value="middle_eastern">Medio Oriente</option>
                    <option value="mixed">Mixta</option>
                    <option value="other">Otra</option>
                </select>

                <select v-model="is_agency"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Agencia: todas</option>
                    <option value="yes">Con agencia</option>
                    <option value="no">Sin agencia</option>
                </select>

                <select v-model="is_top"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Top: todas</option>
                    <option value="yes">Solo Top</option>
                    <option value="no">No Top</option>
                </select>

                <select v-model="compcard"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Comp card: todos</option>
                    <option value="complete">Comp card completo</option>
                    <option value="incomplete">Comp card incompleto</option>
                </select>

                <select v-model="email_sent"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Correo: todos</option>
                    <option value="sent">Correo enviado</option>
                    <option value="not_sent">Correo no enviado</option>
                </select>

                <select v-model="merch"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Merch: todos</option>
                    <option value="with">Con código</option>
                    <option value="without">Sin código</option>
                </select>

                <select v-model="model_kit"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Model Kit: todos</option>
                    <option value="paid">Compraron Model Kit</option>
                    <option value="not_paid">No han comprado</option>
                </select>

                <select v-model="test_model"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Tipo: todas</option>
                    <option value="only_real">Solo reales</option>
                    <option value="only_test">Solo prueba</option>
                </select>

                <select v-model="casting_time"
                    :disabled="!event"
                    :class="[
                        'border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white',
                        !event ? 'opacity-50 cursor-not-allowed' : ''
                    ]">
                    <option value="">{{ event ? 'Horario casting: todos' : 'Seleccione evento primero' }}</option>
                    <option v-for="t in castingTimes" :key="t" :value="t">{{ t }}</option>
                </select>

                <select v-model="casting_status"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Estado casting: todos</option>
                    <option value="scheduled">Agendada</option>
                    <option value="checked_in">Check-in</option>
                    <option value="selected">Seleccionada</option>
                    <option value="no_show">No se presentó</option>
                    <option value="rejected">Rechazada/o</option>
                </select>

                <select v-model="status"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Estado: todos</option>
                    <option value="active">Activo</option>
                    <option value="inactive">Inactivo</option>
                    <option value="rejected">Rechazada</option>
                    <option value="pending">Pendiente</option>
                    <option value="applicant">Aplicante</option>
                </select>

                <div class="w-56">
                    <VueDatePicker
                        v-model="registeredRange"
                        range
                        multi-calendars
                        :preset-dates="datePresets"
                        :enable-time-picker="false"
                        auto-apply
                        :formats="{ input: formatDateRange }"
                        placeholder="Registro: fechas"
                        :clearable="true"
                        input-class-name="dp-input"
                    />
                </div>

                <div class="w-56">
                    <VueDatePicker
                        v-model="checkinRange"
                        range
                        multi-calendars
                        :preset-dates="datePresets"
                        :enable-time-picker="false"
                        auto-apply
                        :formats="{ input: formatDateRange }"
                        placeholder="Check-in: fechas"
                        :clearable="true"
                        input-class-name="dp-input"
                    />
                </div>
            </div>

            <!-- Tabla -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-5 py-3 font-medium text-gray-500">
                                <button @click="toggleSortName" class="flex items-center gap-1 hover:text-gray-800 transition-colors cursor-pointer">
                                    Modelo
                                    <svg v-if="sort_name === 'asc'" class="w-3.5 h-3.5 text-black" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                                    <svg v-else-if="sort_name === 'desc'" class="w-3.5 h-3.5 text-black" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                    <svg v-else class="w-3.5 h-3.5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" /></svg>
                                </button>
                            </th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Género / Edad</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Eventos</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500"># Part.</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Comp Card</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Estado</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Último Check-in</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Registro</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Último Login</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-500">Último Correo</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="models.data.length === 0">
                            <td colspan="11" class="text-center text-gray-400 py-12">No hay modelos registradas.</td>
                        </tr>
                        <tr v-for="m in models.data" :key="m.id"
                            class="hover:bg-gray-50 cursor-pointer transition-colors"
                            @click="router.visit(`/admin/models/${m.id}`)">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full flex-shrink-0"
                                        :class="m.model_profile?.is_top ? 'ring-2 ring-[#D4AF37] ring-offset-1' : ''">
                                        <div class="w-full h-full rounded-full overflow-hidden bg-gray-100">
                                            <img v-if="storageUrl(m.profile_picture)"
                                                :src="storageUrl(m.profile_picture)"
                                                class="w-full h-full object-cover" />
                                            <div v-else class="w-full h-full flex items-center justify-center text-xs font-bold text-gray-500">
                                                {{ m.first_name?.[0] }}{{ m.last_name?.[0] }}
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-1">
                                            <p class="font-medium text-gray-900">{{ m.first_name }} {{ m.last_name }}</p>
                                            <button @click="toggleTop(m, $event)"
                                                class="flex-shrink-0 cursor-pointer hover:scale-110 transition-transform"
                                                :title="m.model_profile?.is_top ? 'Quitar Top' : 'Marcar como Top'">
                                                <StarSolid v-if="m.model_profile?.is_top" class="w-4 h-4 text-[#D4AF37]" />
                                                <StarOutline v-else class="w-4 h-4 text-gray-300 hover:text-[#D4AF37]" />
                                            </button>
                                        </div>
                                        <p class="text-gray-400 text-xs">{{ m.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <p>{{ genderLabel(m.model_profile?.gender) }}</p>
                                <p class="text-xs text-gray-400">{{ m.model_profile?.age ? m.model_profile.age + ' años' : '—' }}</p>
                            </td>
                            <td class="px-4 py-3" @click.stop>
                                <button v-if="m.events_as_model_with_casting?.length"
                                    @click="openEventsModal(m, $event)"
                                    class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full hover:bg-blue-100 transition-colors cursor-pointer">
                                    {{ m.events_as_model_with_casting.length }} evento{{ m.events_as_model_with_casting.length !== 1 ? 's' : '' }}
                                </button>
                                <span v-else class="text-gray-400 text-xs">Sin eventos</span>
                            </td>
                            <td class="px-4 py-3" @click.stop>
                                <template v-if="participationNumbers(m).length">
                                    <div class="flex items-center gap-1">
                                        <span class="text-xs font-bold bg-black text-white px-2 py-0.5 rounded-full">
                                            #{{ participationNumbers(m).at(-1).number }}
                                        </span>
                                        <button v-if="participationNumbers(m).length > 1"
                                            @click="openParticipationModal(m, $event)"
                                            class="text-[10px] font-bold bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full hover:bg-gray-200 transition-colors cursor-pointer">
                                            +{{ participationNumbers(m).length - 1 }}
                                        </button>
                                    </div>
                                </template>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-20 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                        <div :class="progressColor(m.model_profile?.comp_card_progress ?? 0)"
                                            class="h-full rounded-full transition-all"
                                            :style="`width: ${m.model_profile?.comp_card_progress ?? 0}%`"></div>
                                    </div>
                                    <span class="text-xs text-gray-500">{{ m.model_profile?.comp_card_progress ?? 0 }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3" @click.stop>
                                <!-- Activo: badge estático (solo la app puede asignar este estado) -->
                                <span v-if="m.status === 'active'"
                                    class="text-xs font-medium rounded-full px-2 py-0.5 bg-green-100 text-green-700">
                                    Activo
                                </span>
                                <!-- Rechazada: solo permite cambiar a inactivo -->
                                <select v-else-if="m.status === 'rejected'" :value="m.status"
                                    @change="updateModelStatus(m, $event.target.value)"
                                    :data-status-select="m.id"
                                    :class="statusBadge(m.status)"
                                    class="text-xs font-medium rounded-full px-2 py-0.5 border-0 outline-none cursor-pointer appearance-none">
                                    <option value="rejected">Rechazada</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                                <!-- Pendiente / Inactivo / Aplicante: selector editable -->
                                <select v-else :value="m.status"
                                    @change="updateModelStatus(m, $event.target.value)"
                                    :data-status-select="m.id"
                                    :class="[statusBadge(m.status), isReadyForPending(m) ? 'animate-pulse ring-2 ring-purple-400 ring-offset-1' : '']"
                                    class="text-xs font-medium rounded-full px-2 py-0.5 border-0 outline-none cursor-pointer appearance-none">
                                    <option value="inactive">Inactivo</option>
                                    <option value="pending">Pendiente</option>
                                    <option value="applicant">Aplicante</option>
                                </select>
                            </td>
                            <td class="px-4 py-3" @click.stop>
                                <template v-if="checkinEvents(m).length">
                                    <button @click="openCheckinModal(m, $event)"
                                        class="group flex items-center gap-2 text-left hover:opacity-80 transition-opacity">
                                        <div>
                                            <p class="text-xs font-semibold text-gray-800 leading-tight">
                                                {{ fmtCheckinDate(checkinEvents(m)[0].pivot.casting_checked_in_at) }}
                                            </p>
                                            <p class="text-[11px] text-blue-600 font-medium leading-tight">
                                                {{ fmtCheckinTime(checkinEvents(m)[0].pivot.casting_checked_in_at) }}
                                            </p>
                                        </div>
                                        <span v-if="checkinEvents(m).length > 1"
                                            class="flex-shrink-0 text-[10px] font-bold bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full group-hover:bg-gray-200 transition-colors">
                                            +{{ checkinEvents(m).length - 1 }}
                                        </span>
                                    </button>
                                </template>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-xs text-gray-700">{{ fmtLogin(m.created_at) }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p v-if="m.last_login_at" class="text-xs text-gray-700">{{ fmtLogin(m.last_login_at) }}</p>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3" @click.stop>
                                <button @click="openEmailHistory(m, $event)" class="hover:opacity-80 transition-opacity">
                                    <template v-if="m.welcome_email_sent_at">
                                        <span class="inline-flex items-center gap-1 text-xs text-green-700 bg-green-50 px-2 py-0.5 rounded-full font-medium">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                            {{ fmtEmailSent(m.welcome_email_sent_at) }}
                                        </span>
                                    </template>
                                    <span v-else class="inline-flex items-center text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                                        No enviado
                                    </span>
                                </button>
                            </td>
                            <td class="px-4 py-3" @click.stop>
                                <div class="flex items-center gap-2">
                                    <button @click="openEmailModal(m, $event)"
                                        class="p-1.5 border border-gray-200 rounded-lg transition-colors"
                                        :class="canSendEmail(m) ? 'hover:bg-gray-50 text-gray-600' : 'opacity-40 cursor-not-allowed text-gray-400'"
                                        :disabled="!canSendEmail(m)"
                                        title="Enviar Email">
                                        <EnvelopeIcon class="w-4 h-4" />
                                    </button>
                                    <Link :href="`/admin/models/${m.id}/edit`"
                                        class="p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition-colors"
                                        title="Editar">
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Paginación -->
                <div class="border-t border-gray-100 px-5 py-3 flex items-center justify-between text-sm text-gray-500">
                    <div class="flex items-center gap-3">
                        <span>{{ models.from }}–{{ models.to }} de {{ models.total }} modelos</span>
                        <select v-model="perPage"
                            class="border border-gray-200 rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                            <option value="500">500</option>
                        </select>
                    </div>
                    <div v-if="models.last_page > 1" class="flex gap-1">
                        <Link v-if="models.prev_page_url" :href="models.prev_page_url"
                            class="px-3 py-1 border border-gray-200 rounded-lg hover:bg-gray-50">← Anterior</Link>
                        <Link v-if="models.next_page_url" :href="models.next_page_url"
                            class="px-3 py-1 border border-gray-200 rounded-lg hover:bg-gray-50">Siguiente →</Link>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>

    <!-- Modal: Alerta estado pendiente sin evento/casting -->
    <Teleport to="body">
        <div v-if="statusAlertModel" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/60" @click="statusAlertModel = null"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl max-w-sm w-full mx-4 p-6 z-10 text-center">
                <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">No se puede cambiar a Pendiente</h3>
                <p class="text-sm text-gray-600 mb-5">
                    La modelo <span class="font-medium">{{ statusAlertModel.first_name }} {{ statusAlertModel.last_name }}</span>
                    no tiene un evento con horario de casting asignado. Asígnale un evento y casting primero.
                </p>
                <button @click="statusAlertModel = null"
                    class="px-5 py-2 bg-black text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors">
                    Entendido
                </button>
            </div>
        </div>
    </Teleport>

    <!-- Modal: Historial de Check-ins -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="checkinModel" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="checkinModel = null"></div>

                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-2">
                    <div v-if="checkinModel" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">

                        <!-- Header -->
                        <div class="bg-black px-6 py-5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-full overflow-hidden flex-shrink-0 border-2 border-white/20">
                                        <img v-if="storageUrl(checkinModel.profile_picture)"
                                            :src="storageUrl(checkinModel.profile_picture)"
                                            class="w-full h-full object-cover" />
                                        <div v-else class="w-full h-full bg-white/10 flex items-center justify-center text-sm font-bold text-white">
                                            {{ checkinModel.first_name?.[0] }}{{ checkinModel.last_name?.[0] }}
                                        </div>
                                    </div>
                                    <div>
                                        <p class="font-bold text-white text-base leading-tight">
                                            {{ checkinModel.first_name }} {{ checkinModel.last_name }}
                                        </p>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                                            <p class="text-white/60 text-xs">
                                                {{ checkinEvents(checkinModel).length }} check-in{{ checkinEvents(checkinModel).length !== 1 ? 's' : '' }} registrado{{ checkinEvents(checkinModel).length !== 1 ? 's' : '' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <button @click="checkinModel = null"
                                    class="w-8 h-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-colors text-white">
                                    <XMarkIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Timeline de check-ins -->
                        <div class="p-5 space-y-0 max-h-[60vh] overflow-y-auto">
                            <div v-for="(ev, idx) in checkinEvents(checkinModel)" :key="ev.id"
                                class="relative flex gap-4">
                                <!-- Línea de timeline -->
                                <div class="flex flex-col items-center flex-shrink-0">
                                    <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center shadow-sm flex-shrink-0 z-10">
                                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </div>
                                    <div v-if="idx < checkinEvents(checkinModel).length - 1"
                                        class="w-px flex-1 bg-gray-200 my-1 min-h-[20px]"></div>
                                </div>

                                <!-- Contenido -->
                                <div class="pb-5 flex-1 min-w-0">
                                    <div class="bg-gray-50 border border-gray-100 rounded-xl p-3.5 hover:border-gray-200 transition-colors">
                                        <!-- Evento -->
                                        <div class="flex items-start justify-between gap-2 mb-2.5">
                                            <p class="font-semibold text-gray-900 text-sm leading-tight truncate">{{ ev.name }}</p>
                                            <span :class="eventStatusBadge(ev.status)"
                                                class="flex-shrink-0 text-[10px] font-medium px-1.5 py-0.5 rounded-full">
                                                {{ eventStatusLabel(ev.status) }}
                                            </span>
                                        </div>

                                        <!-- Fecha y hora del check-in -->
                                        <div class="flex items-center gap-3 mb-2.5">
                                            <div class="flex items-center gap-1.5">
                                                <div class="w-6 h-6 rounded-lg bg-blue-50 flex items-center justify-center">
                                                    <svg class="w-3 h-3 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5" />
                                                    </svg>
                                                </div>
                                                <span class="text-xs text-gray-700 font-medium">{{ fmtCheckinDate(ev.pivot.casting_checked_in_at) }}</span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <div class="w-6 h-6 rounded-lg bg-green-50 flex items-center justify-center">
                                                    <svg class="w-3 h-3 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                                <span class="text-xs font-bold text-green-600">{{ fmtCheckinTime(ev.pivot.casting_checked_in_at) }}</span>
                                            </div>
                                        </div>

                                        <!-- Datos extra -->
                                        <div class="flex items-center gap-3 flex-wrap">
                                            <div v-if="ev.pivot?.participation_number" class="flex items-center gap-1">
                                                <span class="text-[10px] text-gray-400">Participación:</span>
                                                <span class="text-xs font-bold bg-black text-white px-1.5 py-0.5 rounded-full">
                                                    #{{ ev.pivot.participation_number }}
                                                </span>
                                            </div>
                                            <div v-if="ev.pivot?.casting_time" class="flex items-center gap-1">
                                                <span class="text-[10px] text-gray-400">Horario:</span>
                                                <span class="text-xs font-medium text-gray-700">{{ formatTime(ev.pivot.casting_time) }}</span>
                                            </div>
                                            <div class="ml-auto">
                                                <span class="text-[10px] text-gray-400 italic">{{ timeAgo(ev.pivot.casting_checked_in_at) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between bg-gray-50">
                            <Link :href="`/admin/models/${checkinModel.id}`"
                                class="text-sm font-semibold text-black hover:underline underline-offset-2">
                                Ver perfil completo →
                            </Link>
                            <button @click="checkinModel = null"
                                class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">
                                Cerrar
                            </button>
                        </div>

                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>

    <!-- Modal: Eventos de la modelo -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="selectedModel" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="selectedModel = null"></div>

                <!-- Card -->
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-2">
                    <div v-if="selectedModel" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">

                        <!-- Header negro -->
                        <div class="bg-black px-6 py-5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <!-- Avatar -->
                                    <div class="w-11 h-11 rounded-full overflow-hidden flex-shrink-0 border-2 border-white/20">
                                        <img v-if="storageUrl(selectedModel.profile_picture)"
                                            :src="storageUrl(selectedModel.profile_picture)"
                                            class="w-full h-full object-cover" />
                                        <div v-else class="w-full h-full bg-white/10 flex items-center justify-center text-sm font-bold text-white">
                                            {{ selectedModel.first_name?.[0] }}{{ selectedModel.last_name?.[0] }}
                                        </div>
                                    </div>
                                    <div>
                                        <p class="font-bold text-white text-base leading-tight">
                                            {{ selectedModel.first_name }} {{ selectedModel.last_name }}
                                        </p>
                                        <p class="text-white/50 text-xs mt-0.5">
                                            {{ selectedModel.events_as_model_with_casting.length }}
                                            evento{{ selectedModel.events_as_model_with_casting.length !== 1 ? 's' : '' }} asignado{{ selectedModel.events_as_model_with_casting.length !== 1 ? 's' : '' }}
                                        </p>
                                    </div>
                                </div>
                                <button @click="selectedModel = null"
                                    class="w-8 h-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-colors text-white">
                                    <XMarkIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Lista de eventos -->
                        <div class="p-5 space-y-3 max-h-[60vh] overflow-y-auto">
                            <div v-for="(ev, idx) in selectedModel.events_as_model_with_casting" :key="ev.id"
                                class="border border-gray-100 rounded-xl p-4 hover:border-gray-200 transition-colors bg-gray-50/50">

                                <!-- Row superior: nombre evento + estado -->
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

                                    <!-- Participación -->
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-[#D4AF37]/10 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3.5 h-3.5 text-[#D4AF37]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-400 leading-none mb-0.5">Participación</p>
                                            <p class="text-xs font-semibold text-gray-800">
                                                {{ ev.pivot?.participation_number ? '#' + ev.pivot.participation_number : '—' }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Horario casting -->
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-400 leading-none mb-0.5">Horario casting</p>
                                            <p class="text-xs font-semibold text-gray-800">{{ formatTime(ev.pivot?.casting_time) }}</p>
                                        </div>
                                    </div>

                                    <!-- Estado en evento -->
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3.5 h-3.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-400 leading-none mb-0.5">Estado en evento</p>
                                            <span :class="pivotStatusBadge(ev.pivot?.status)"
                                                class="text-xs font-medium px-1.5 py-0.5 rounded-full">
                                                {{ pivotStatusLabel(ev.pivot?.status) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Estado casting (editable) -->
                                    <div class="flex items-center gap-2 col-span-2">
                                        <div class="w-7 h-7 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3.5 h-3.5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-[10px] text-gray-400 leading-none mb-1">Estado casting</p>
                                            <select
                                                :value="ev.pivot?.casting_status"
                                                @change="updateCastingStatus(selectedModel, ev.id, $event.target.value)"
                                                class="w-full border rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-yellow-400"
                                                :class="{
                                                    'border-green-300 bg-green-50 text-green-700': ev.pivot?.casting_status === 'selected',
                                                    'border-red-300 bg-red-50 text-red-600': ev.pivot?.casting_status === 'no_show' || ev.pivot?.casting_status === 'rejected',
                                                    'border-blue-300 bg-blue-50 text-blue-700': ev.pivot?.casting_status === 'checked_in',
                                                    'border-gray-300 bg-white text-gray-600': ev.pivot?.casting_status === 'scheduled',
                                                }">
                                                <option value="scheduled">Agendada</option>
                                                <option value="checked_in">Check-in</option>
                                                <option value="selected">Seleccionada</option>
                                                <option value="no_show">No se presentó</option>
                                                <option value="rejected">{{ selectedModel?.model_profile?.gender === 'male' ? 'Rechazado' : 'Rechazada' }}</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Tag de modelo (solo si tiene merch) -->
                                    <div v-if="ev.pivot?.shopify_order_number" class="flex items-center gap-2 col-span-2">
                                        <div class="w-7 h-7 rounded-lg bg-orange-50 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3.5 h-3.5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-[10px] text-gray-400 leading-none mb-1">Tag modelo</p>
                                            <select
                                                :value="ev.pivot?.model_tag || ''"
                                                @change="updateModelTag(selectedModel, ev.id, $event.target.value)"
                                                class="w-full border rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-orange-400"
                                                :class="{
                                                    'border-orange-300 bg-orange-50 text-orange-700': ev.pivot?.model_tag === 'runway_merch',
                                                    'border-green-300 bg-green-50 text-green-700': ev.pivot?.model_tag === 'runway_brand',
                                                    'border-gray-300 bg-white text-gray-500': !ev.pivot?.model_tag,
                                                }">
                                                <option value="">Sin tag</option>
                                                <option value="runway_merch">Runway Merch</option>
                                                <option value="runway_brand">Runway Brand</option>
                                            </select>
                                        </div>
                                    </div>

                                </div>

                                <!-- Botón enviar onboarding (solo si tiene tag y casting) -->
                                <div v-if="ev.pivot?.model_tag && ev.pivot?.casting_time"
                                    class="mt-3 pt-3 border-t border-gray-100">
                                    <button @click="sendOnboarding(selectedModel, ev.id)"
                                        class="w-full flex items-center justify-center gap-2 px-3 py-2 bg-black text-white text-xs font-medium rounded-lg hover:bg-gray-800 transition-colors">
                                        <EnvelopeIcon class="w-3.5 h-3.5" />
                                        Enviar onboarding {{ ev.pivot.model_tag === 'runway_merch' ? '(Merch)' : '(Brand)' }}
                                    </button>
                                </div>

                                <!-- Check-in timestamp si existe -->
                                <div v-if="ev.pivot?.casting_checked_in_at"
                                    class="mt-3 pt-3 border-t border-gray-100 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                    <p class="text-xs text-gray-500">
                                        Check-in realizado:
                                        <span class="font-medium text-gray-700">
                                            {{ new Date(ev.pivot.casting_checked_in_at).toLocaleString('es-MX', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) }}
                                        </span>
                                    </p>
                                </div>

                                <!-- Shows asignados -->
                                <div v-if="eventShows(selectedModel, ev.id).length"
                                    class="mt-3 pt-3 border-t border-gray-100 space-y-1.5">
                                    <div v-for="s in eventShows(selectedModel, ev.id)" :key="s.show_id + '-' + (s.brand_name || s.designer_name)"
                                        class="flex items-center gap-2 bg-purple-50 border border-purple-100 rounded-lg px-3 py-2">
                                        <div class="w-6 h-6 rounded-lg bg-purple-100 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3 h-3 text-purple-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" />
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-[10px] text-purple-500 leading-none mb-0.5">Show</p>
                                            <p class="text-xs font-semibold text-purple-700 truncate">
                                                {{ s.day_label }} · {{ s.formatted_time }}
                                                <span class="font-normal text-purple-500 ml-1">{{ s.brand_name || s.designer_name }}</span>
                                            </p>
                                        </div>
                                        <span :class="{
                                            'bg-green-100 text-green-700': s.status === 'confirmed',
                                            'bg-blue-100 text-blue-700': s.status === 'requested',
                                            'bg-yellow-100 text-yellow-700': s.status === 'reserved',
                                            'bg-red-100 text-red-600': s.status === 'rejected',
                                        }" class="flex-shrink-0 text-[10px] font-medium px-1.5 py-0.5 rounded-full capitalize">
                                            {{ { confirmed: 'Confirmada', requested: 'Solicitada', reserved: 'Reservada', rejected: 'Rechazada' }[s.status] ?? s.status }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Fitting schedule -->
                                <div v-if="eventFittings(selectedModel, ev.id).length"
                                    class="mt-3 pt-3 border-t border-gray-100 space-y-1.5">
                                    <div v-for="f in eventFittings(selectedModel, ev.id)" :key="f.time + f.designer_name"
                                        class="flex items-center gap-2 bg-orange-50 border border-orange-100 rounded-lg px-3 py-2">
                                        <div class="w-6 h-6 rounded-lg bg-orange-100 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3 h-3 text-orange-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-[10px] text-orange-500 leading-none mb-0.5">Fitting</p>
                                            <p class="text-xs font-semibold text-orange-700">
                                                {{ f.day_label }} · {{ f.time }}
                                                <span class="font-normal text-orange-500 ml-1">{{ f.brand_name || f.designer_name }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between bg-gray-50">
                            <Link :href="`/admin/models/${selectedModel.id}`"
                                class="text-sm font-semibold text-black hover:underline underline-offset-2">
                                Ver perfil completo →
                            </Link>
                            <button @click="selectedModel = null"
                                class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">
                                Cerrar
                            </button>
                        </div>

                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>

    <!-- Modal: Números de participación -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="participationModel" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="participationModel = null"></div>

                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-2">
                    <div v-if="participationModel" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">

                        <!-- Header -->
                        <div class="bg-black px-6 py-5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-full overflow-hidden flex-shrink-0 border-2 border-white/20">
                                        <img v-if="storageUrl(participationModel.profile_picture)"
                                            :src="storageUrl(participationModel.profile_picture)"
                                            class="w-full h-full object-cover" />
                                        <div v-else class="w-full h-full bg-white/10 flex items-center justify-center text-sm font-bold text-white">
                                            {{ participationModel.first_name?.[0] }}{{ participationModel.last_name?.[0] }}
                                        </div>
                                    </div>
                                    <div>
                                        <p class="font-bold text-white text-base leading-tight">
                                            {{ participationModel.first_name }} {{ participationModel.last_name }}
                                        </p>
                                        <p class="text-white/50 text-xs mt-0.5">
                                            {{ participationNumbers(participationModel).length }} número{{ participationNumbers(participationModel).length !== 1 ? 's' : '' }} de participación
                                        </p>
                                    </div>
                                </div>
                                <button @click="participationModel = null"
                                    class="w-8 h-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-colors text-white">
                                    <XMarkIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Lista de participaciones -->
                        <div class="p-5 space-y-2.5 max-h-[60vh] overflow-y-auto">
                            <div v-for="p in participationNumbers(participationModel)" :key="p.number"
                                class="flex items-center justify-between bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 hover:border-gray-200 transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="text-sm font-bold bg-black text-white px-2.5 py-1 rounded-full flex-shrink-0">
                                        #{{ p.number }}
                                    </span>
                                    <p class="text-sm text-gray-700 font-medium truncate">{{ p.eventName }}</p>
                                </div>
                                <span :class="eventStatusBadge(p.eventStatus)"
                                    class="flex-shrink-0 text-[10px] font-medium px-1.5 py-0.5 rounded-full ml-2">
                                    {{ eventStatusLabel(p.eventStatus) }}
                                </span>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between bg-gray-50">
                            <Link :href="`/admin/models/${participationModel.id}`"
                                class="text-sm font-semibold text-black hover:underline underline-offset-2">
                                Ver perfil completo →
                            </Link>
                            <button @click="participationModel = null"
                                class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">
                                Cerrar
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
                    <h3 class="text-lg font-bold text-gray-900">Importar Modelos desde Excel</h3>
                    <button @click="showImportModal = false" class="text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <!-- Download template -->
                <div class="mb-4">
                    <a href="/admin/models/import-template"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-xl transition-colors">
                        <ArrowDownTrayIcon class="w-4 h-4" />
                        Download Template (.xlsx)
                    </a>
                    <p class="mt-2 text-xs text-gray-500">The template includes all accepted columns with example data.</p>
                </div>

                <!-- Formato esperado -->
                <div class="bg-gray-50 rounded-xl p-4 mb-5 text-xs text-gray-600">
                    <p class="font-semibold text-gray-800 mb-2">Accepted columns:</p>
                    <div class="grid grid-cols-2 gap-1">
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">email</span> <span class="text-red-500">*required</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">first_name</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">last_name</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">phone</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">age</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">gender</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">city</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">height</span> (inches)</span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">bust</span> / <span class="font-mono bg-white border border-gray-200 px-1 rounded">waist</span> / <span class="font-mono bg-white border border-gray-200 px-1 rounded">hips</span> (in)</span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">shoe_size</span> / <span class="font-mono bg-white border border-gray-200 px-1 rounded">dress_size</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">ethnicity</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">hair</span> / <span class="font-mono bg-white border border-gray-200 px-1 rounded">body_type</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">instagram</span> / <span class="font-mono bg-white border border-gray-200 px-1 rounded">agency</span></span>
                        <span><span class="font-mono bg-white border border-gray-200 px-1 rounded">casting_time</span> (09:00)</span>
                    </div>
                    <p class="mt-2 text-gray-500">Formats: <strong>.xlsx, .xls, .csv</strong></p>
                </div>

                <!-- Selector de evento -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Asignar a un evento <span class="text-gray-400 font-normal">(opcional)</span></label>
                    <select v-model="importForm.event_id"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                        <option value="">— Sin asignar a evento —</option>
                        <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                    <p v-if="importForm.event_id" class="mt-1.5 text-xs text-blue-600">
                        Todas las modelos importadas se asignarán a este evento. Si el Excel incluye la columna <span class="font-mono">casting_time</span>, cada modelo recibirá su horario individual.
                    </p>
                    <p v-else class="mt-1.5 text-xs text-gray-400">
                        Si no seleccionas un evento, las modelos se crean sin asignación.
                    </p>
                </div>

                <!-- Input archivo -->
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Seleccionar archivo</label>
                    <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv"
                        @change="handleFileChange"
                        class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-black file:text-white hover:file:bg-gray-800 cursor-pointer" />
                    <p v-if="importForm.errors.file" class="mt-1 text-xs text-red-500">{{ importForm.errors.file }}</p>
                </div>

                <!-- Acciones -->
                <div class="flex gap-3">
                    <button @click="showImportModal = false"
                        class="flex-1 py-2.5 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancelar
                    </button>
                    <button @click="submitImport"
                        :disabled="!importForm.file || importForm.processing"
                        class="flex-1 py-2.5 bg-black text-white rounded-xl text-sm font-semibold hover:bg-gray-800 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        {{ importForm.processing ? 'Importando...' : 'Importar' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal: Historial de correos -->
    <Teleport to="body">
        <div v-if="emailHistoryModel" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="emailHistoryModel = null">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Historial de correos</h3>
                        <p class="text-sm text-gray-500">{{ emailHistoryModel.first_name }} {{ emailHistoryModel.last_name }}</p>
                    </div>
                    <button @click="emailHistoryModel = null" class="text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <div v-if="!getEmailLogs(emailHistoryModel).length" class="text-center py-8 text-gray-400 text-sm italic">
                    No se han enviado correos aún.
                </div>

                <div v-else class="space-y-3 max-h-80 overflow-y-auto">
                    <div v-for="log in getEmailLogs(emailHistoryModel)" :key="log.id"
                        class="border border-gray-100 rounded-xl p-4"
                        :class="log.status === 'failed' ? 'bg-red-50/50' : 'bg-gray-50'">
                        <div class="flex items-center justify-between mb-2">
                            <span :class="commStatusClass(log.status)"
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                                {{ commStatusLabel(log.status) }}
                            </span>
                            <span class="text-xs text-gray-400">
                                {{ new Date(log.sent_at ?? log.created_at).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' }) }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-500">
                            Enviado por <span class="font-medium text-gray-700">{{ log.sender ? `${log.sender.first_name} ${log.sender.last_name}` : 'Registro automático' }}</span>
                        </div>
                        <div v-if="log.error_message" class="mt-2 text-xs text-red-600 bg-red-100 rounded-lg p-2">
                            {{ log.error_message }}
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex justify-end">
                    <button @click="emailHistoryModel = null"
                        class="px-5 py-2 bg-black text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal: Confirmar envío de email de bienvenida -->
    <Teleport to="body">
        <div v-if="emailModalModel" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="emailModalModel = null">
            <div class="bg-white rounded-2xl p-6 w-full max-w-sm shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-gray-900">Enviar email de onboarding</h3>
                    <button @click="emailModalModel = null" class="text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>
                <p class="text-sm text-gray-500 mb-4">
                    ¿Enviar email de bienvenida a
                    <span class="font-semibold text-gray-800">{{ emailModalModel.first_name }} {{ emailModalModel.last_name }}</span>?
                </p>
                <p class="text-xs text-gray-400 mb-3">Se incluirán los siguientes eventos:</p>
                <div class="space-y-2 mb-5">
                    <div v-for="evt in scheduledEvents(emailModalModel)" :key="evt.id"
                        class="flex items-center gap-3 p-3 border border-gray-200 rounded-xl bg-gray-50">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ evt.name }}</p>
                            <p v-if="evt.pivot?.casting_time" class="text-xs text-gray-400">
                                Casting: {{ evt.pivot.casting_time }}
                            </p>
                        </div>
                    </div>
                    <p v-if="!scheduledEvents(emailModalModel).length" class="text-sm text-red-500 italic">
                        No tiene eventos con casting agendado.
                    </p>
                </div>
                <div class="flex gap-3">
                    <button @click="emailModalModel = null"
                        class="flex-1 py-2.5 border border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancelar
                    </button>
                    <button @click="confirmSendWelcomeEmail()"
                        :disabled="!scheduledEvents(emailModalModel).length"
                        class="flex-1 py-2.5 bg-black text-white rounded-xl text-sm font-semibold hover:bg-gray-800 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        Enviar Email
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
                        <h3 class="text-base font-semibold text-gray-900">¿Cómo funciona el envío masivo de emails?</h3>
                    </div>
                    <button @click="showEmailInfoModal = false" class="text-gray-400 hover:text-gray-600 ml-2">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>
                <ul class="space-y-3 text-sm text-gray-600">
                    <li class="flex items-start gap-2">
                        <span class="w-1.5 h-1.5 bg-amber-400 rounded-full mt-1.5 flex-shrink-0"></span>
                        <span>Solo se envía a modelos con estado Pendiente que tengan casting agendado y no hayan recibido email de bienvenida anteriormente.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-1.5 h-1.5 bg-amber-400 rounded-full mt-1.5 flex-shrink-0"></span>
                        <span>El email incluye todos los eventos asignados de la modelo donde su casting está agendado, con la fecha y hora correspondiente.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-1.5 h-1.5 bg-amber-400 rounded-full mt-1.5 flex-shrink-0"></span>
                        <span>Si una modelo tiene 2 eventos, el email muestra ambos con sus fechas y horarios de casting.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-1.5 h-1.5 bg-amber-400 rounded-full mt-1.5 flex-shrink-0"></span>
                        <span>El envío se procesa en cola — puede tardar unos segundos dependiendo del volumen.</span>
                    </li>
                </ul>
                <button @click="showEmailInfoModal = false"
                    class="mt-5 w-full py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors">
                    Entendido
                </button>
            </div>
        </div>
    </Teleport>
</template>

<style>
.dp-input {
    border: 1px solid #e5e7eb !important;
    border-radius: 0.5rem !important;
    padding: 0.625rem 1rem !important;
    font-size: 0.875rem !important;
    line-height: 1.25rem !important;
    background: white !important;
}
.dp-input:focus {
    outline: none !important;
    box-shadow: 0 0 0 2px rgba(0,0,0,0.1) !important;
    border-color: #9ca3af !important;
}
.dp__theme_light {
    --dp-primary-color: #000;
    --dp-primary-text-color: #fff;
}
</style>
