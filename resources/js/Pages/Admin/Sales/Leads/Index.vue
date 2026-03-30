<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { EyeIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';

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
    if (diff < 60) return 'Hace un momento';
    if (diff < 3600) return `Hace ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `Hace ${Math.floor(diff / 3600)}h`;
    if (diff < 604800) return `Hace ${Math.floor(diff / 86400)}d`;
    return date.toLocaleDateString('es-US');
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('es-US') + '\n' + d.toLocaleTimeString('es-US', { hour: '2-digit', minute: '2-digit' });
}

// Stats cards config
// Lead stats (marketing)
const leadCards = computed(() => {
    const cards = [
        { key: 'total', label: 'Total', value: props.stats?.total ?? 0, color: '#6B7280' },
        { key: 'new', label: 'Nuevos', value: props.stats?.new ?? 0, color: props.statuses?.new?.color ?? '#3B82F6' },
        { key: 'qualified', label: 'Calificados', value: props.stats?.qualified ?? 0, color: props.statuses?.qualified?.color ?? '#8B5CF6' },
        { key: 'client', label: 'Clientes', value: props.stats?.client ?? 0, color: props.statuses?.client?.color ?? '#10B981' },
        { key: 'lost', label: 'Perdidos', value: props.stats?.lost ?? 0, color: props.statuses?.lost?.color ?? '#EF4444' },
        { key: 'spam', label: 'Spam', value: props.stats?.spam ?? 0, color: props.statuses?.spam?.color ?? '#1F2937' },
    ];
    if (props.isLeader) {
        cards.push({ key: 'unassigned', label: 'Sin asignar', value: props.stats?.unassigned ?? 0, color: '#9CA3AF' });
    }
    return cards;
});

// Opportunity stats (ventas)
const oppCards = computed(() => [
    { key: 'opp_total', label: 'Total', value: props.opportunityStats?.opp_total ?? 0, color: '#6B7280' },
    { key: 'opp_negotiating', label: 'Negociando', value: props.opportunityStats?.opp_negotiating ?? 0, color: '#8B5CF6' },
    { key: 'opp_converted', label: 'Ventas', value: props.opportunityStats?.opp_converted ?? 0, color: '#10B981' },
    { key: 'opp_follow_up', label: 'Seguimiento', value: props.opportunityStats?.opp_follow_up ?? 0, color: '#F97316' },
    { key: 'opp_contacted', label: 'Contactados', value: props.opportunityStats?.opp_contacted ?? 0, color: '#EAB308' },
    { key: 'opp_lost', label: 'Perdidos', value: props.opportunityStats?.opp_lost ?? 0, color: '#EF4444' },
]);

function advisorName(lead) {
    if (!lead.assigned_to || typeof lead.assigned_to !== 'object') return null;
    return `${lead.assigned_to.first_name} ${lead.assigned_to.last_name}`;
}

// Auto-refresh when new lead notification arrives
function onNotification(e) {
    if (e.detail?.data?.type === 'new_designer_lead') {
        router.reload({ only: ['leads', 'stats'], preserveScroll: true });
    }
}
onMounted(() => window.addEventListener('notification:received', onNotification));
onUnmounted(() => window.removeEventListener('notification:received', onNotification));
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-semibold text-gray-900">Prospectos</h2>
                <button @click="showStatusInfo = true" class="w-5 h-5 rounded-full border border-gray-300 text-gray-400 flex items-center justify-center hover:bg-gray-100 hover:text-gray-600 transition-colors text-[10px] font-bold">?</button>
            </div>
        </template>

        <div @click="closeDropdowns">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Prospectos</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ leads.total }} prospectos registrados</p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Availability toggle for sales users -->
                    <div v-if="!isLeader" class="flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-lg bg-white">
                        <span class="text-sm text-gray-600">Disponible para leads</span>
                        <button @click.stop="toggleAvailability"
                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                            :class="$page.props.auth?.user?.is_available ? 'bg-green-500' : 'bg-gray-300'"
                            role="switch"
                            :aria-checked="$page.props.auth?.user?.is_available">
                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                :class="$page.props.auth?.user?.is_available ? 'translate-x-5' : 'translate-x-0'" />
                        </button>
                    </div>

                    <Link href="/admin/sales/leads/create"
                        class="px-4 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors">
                        + Nuevo Prospecto
                    </Link>
                </div>
            </div>

            <!-- Lead Stats (Marketing) -->
            <div class="mb-2">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Leads</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
                    <div v-for="card in leadCards" :key="card.key"
                        class="bg-white rounded-lg border border-gray-200 px-4 py-3 cursor-pointer hover:shadow-sm transition-shadow"
                        @click.stop="status = status === card.key ? '' : (card.key === 'total' ? '' : card.key)">
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
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Oportunidades</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div v-for="card in oppCards" :key="card.key"
                        class="bg-white rounded-lg border border-gray-200 px-4 py-3 cursor-pointer hover:shadow-sm transition-shadow"
                        @click.stop="oppStatus = oppStatus === card.key.replace('opp_','') ? '' : card.key.replace('opp_','')">
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
                    placeholder="Buscar nombre, email, empresa, telefono..."
                    class="flex-1 min-w-48 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400"
                />
                <select v-model="status" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todos los estados</option>
                    <option v-for="(info, key) in statuses" :key="key" :value="key">{{ info.label }}</option>
                </select>
                <select v-model="event" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todos los eventos</option>
                    <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                </select>
                <select v-if="isLeader" v-model="assignedTo" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todos los asesores</option>
                    <option value="unassigned">Sin asignar</option>
                    <option v-for="adv in advisors" :key="adv.id" :value="adv.id">{{ adv.first_name }} {{ adv.last_name }}</option>
                </select>
                <select v-model="budget" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Presupuesto</option>
                    <option value="low">Bajo</option>
                    <option value="medium">Medio</option>
                    <option value="high">Alto</option>
                    <option value="premium">Premium</option>
                </select>
                <select v-model="tag" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todos los tags</option>
                    <option v-for="t in allTags" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <select v-model="source" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todas las fuentes</option>
                    <option v-for="(label, key) in sources" :key="key" :value="key">{{ label }}</option>
                </select>
                <select v-model="perPage" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="20">20 por pagina</option>
                    <option value="50">50 por pagina</option>
                    <option value="100">100 por pagina</option>
                    <option value="200">200 por pagina</option>
                </select>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-200">
                <div>
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Lead</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Empresa</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Instagram</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Presupuesto</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Evento</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Tags</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Asesor</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Registro</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Fuente</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="lead in leads.data" :key="lead.id" class="hover:bg-gray-50 transition-colors cursor-pointer" @click="router.visit(`/admin/sales/leads/${lead.id}`)">
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
                                        {{ lead.events.length }} {{ lead.events.length === 1 ? 'evento' : 'eventos' }}
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
                                            Varios ({{ lead.tags.length }})
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
                                            {{ advisorName(lead) || 'Sin asignar' }}
                                        </button>
                                        <span v-else class="text-xs font-medium rounded-full px-2.5 py-1"
                                            :class="advisorName(lead) ? 'bg-blue-50 text-blue-700' : 'bg-orange-50 text-orange-600'">
                                            {{ advisorName(lead) || 'Sin asignar' }}
                                        </span>
                                        <!-- Advisor dropdown (leader only) -->
                                        <div v-if="isLeader && openAdvisorDropdown === lead.id"
                                            class="absolute z-20 mt-1 left-0 bg-white rounded-lg shadow-lg border border-gray-200 py-1 min-w-44">
                                            <button @click="changeAdvisor(lead, null); openAdvisorDropdown = null"
                                                class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50 text-orange-600 transition-colors">
                                                Sin asignar
                                            </button>
                                            <button v-for="adv in advisors" :key="adv.id"
                                                @click="changeAdvisor(lead, adv.id); openAdvisorDropdown = null"
                                                class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50 flex items-center gap-2 transition-colors"
                                                :class="lead.assigned_to?.id === adv.id ? 'font-semibold text-blue-700' : 'text-gray-700'">
                                                <span class="w-2 h-2 rounded-full flex-shrink-0"
                                                    :class="adv.is_available ? 'bg-green-500' : 'bg-gray-300'"></span>
                                                {{ adv.first_name }} {{ adv.last_name }}
                                                <span v-if="!adv.is_available" class="text-gray-400 text-[10px]">(no disponible)</span>
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
                                            title="Editar">
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </Link>
                                        <Link :href="`/admin/sales/leads/${lead.id}`"
                                            class="p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition-colors cursor-pointer"
                                            title="Ver detalle">
                                            <EyeIcon class="w-4 h-4" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="leads.data.length === 0">
                                <td colspan="9" class="px-6 py-12 text-center text-gray-400 text-sm">
                                    No se encontraron prospectos con los filtros aplicados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="leads.last_page > 1" class="border-t border-gray-200 px-4 py-4 flex items-center justify-between">
                    <p class="text-sm text-gray-500">Mostrando {{ leads.from }}–{{ leads.to }} de {{ leads.total }}</p>
                    <div class="flex gap-1">
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
                        <h3 class="text-lg font-semibold text-gray-900">Guía de Estados</h3>
                        <button @click="showStatusInfo = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                    </div>
                    <div class="px-6 py-5 space-y-6 text-sm">
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3">Estado del Lead (persona)</h4>
                            <p class="text-gray-500 text-xs mb-3">Representa el ciclo de vida del contacto como prospecto.</p>
                            <div class="space-y-2">
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full bg-blue-500 mt-1 flex-shrink-0"></span><div><span class="font-medium text-gray-900">Nuevo</span><span class="text-gray-500"> — Acaba de registrarse. El líder debe revisar y calificar.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full bg-purple-500 mt-1 flex-shrink-0"></span><div><span class="font-medium text-gray-900">Calificado</span><span class="text-gray-500"> — Es un prospecto real. Se asigna automáticamente a un asesor.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full bg-green-500 mt-1 flex-shrink-0"></span><div><span class="font-medium text-gray-900">Cliente</span><span class="text-gray-500"> — Se cerró al menos 1 venta. Cambia automáticamente.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full bg-red-500 mt-1 flex-shrink-0"></span><div><span class="font-medium text-gray-900">Perdido</span><span class="text-gray-500"> — No tiene interés. Cambia automáticamente si todos los eventos son negativos.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full bg-gray-800 mt-1 flex-shrink-0"></span><div><span class="font-medium text-gray-900">Spam</span><span class="text-gray-500"> — No es un prospecto real.</span></div></div>
                            </div>
                        </div>
                        <div class="border-t pt-5">
                            <h4 class="font-semibold text-gray-900 mb-3">Estado por Evento (oportunidad)</h4>
                            <p class="text-gray-500 text-xs mb-3">Progreso de la negociación para cada evento.</p>
                            <div class="space-y-2">
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full bg-blue-500 mt-1 flex-shrink-0"></span><div><span class="font-medium text-gray-900">Nuevo</span><span class="text-gray-500"> — Registrado, sin contactar.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full bg-yellow-500 mt-1 flex-shrink-0"></span><div><span class="font-medium text-gray-900">Contactado</span><span class="text-gray-500"> — Primer contacto realizado.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full bg-orange-500 mt-1 flex-shrink-0"></span><div><span class="font-medium text-gray-900">Seguimiento</span><span class="text-gray-500"> — Pendiente de respuesta.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full bg-purple-500 mt-1 flex-shrink-0"></span><div><span class="font-medium text-gray-900">Negociando</span><span class="text-gray-500"> — Discutiendo paquete. Se habilita convertir.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full bg-green-500 mt-1 flex-shrink-0"></span><div><span class="font-medium text-gray-900">Venta</span><span class="text-gray-500"> — Venta cerrada.</span></div></div>
                                <div class="flex items-start gap-3"><span class="w-3 h-3 rounded-full bg-red-500 mt-1 flex-shrink-0"></span><div><span class="font-medium text-gray-900">No Venta</span><span class="text-gray-500"> — No se concretó.</span></div></div>
                            </div>
                        </div>
                        <div class="border-t pt-5">
                            <h4 class="font-semibold text-gray-900 mb-3">Cambios Automáticos</h4>
                            <div class="space-y-2 text-gray-600 text-xs">
                                <p>• Al calificar → se asigna asesor automáticamente</p>
                                <p>• Al convertir a designer → lead pasa a <span class="font-medium text-green-600">Cliente</span>, evento a <span class="font-medium text-green-600">Venta</span></p>
                                <p>• Todos los eventos negativos → lead pasa a <span class="font-medium text-red-600">Perdido</span></p>
                                <p>• Lead perdido se re-registra → vuelve a <span class="font-medium text-purple-600">Calificado</span></p>
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
                            <h3 class="font-semibold text-gray-900">Tags de {{ tagsModalLead.first_name }} {{ tagsModalLead.last_name }}</h3>
                            <p class="text-xs text-gray-500">{{ tagsModalLead.tags?.length }} tags asignados</p>
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
                        <Link :href="`/admin/sales/leads/${tagsModalLead.id}`" class="text-sm font-medium text-gray-700 hover:text-black">Ver perfil →</Link>
                        <button @click="tagsModalLead = null" class="text-sm text-gray-500 hover:text-gray-700">Cerrar</button>
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
                            <p class="text-xs text-gray-500">{{ eventsModalLead.events?.length }} {{ eventsModalLead.events?.length === 1 ? 'evento asignado' : 'eventos asignados' }}</p>
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
                                <label class="text-xs text-gray-500 mb-1 block">Estado</label>
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
                        <Link :href="`/admin/sales/leads/${eventsModalLead.id}`" class="text-sm font-medium text-gray-700 hover:text-black">Ver perfil completo →</Link>
                        <button @click="eventsModalLead = null" class="text-sm text-gray-500 hover:text-gray-700">Cerrar</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
