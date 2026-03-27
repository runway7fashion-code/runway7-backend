<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { EyeIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    leads: Object,
    stats: Object,
    statuses: Object,
    advisors: Array,
    events: Array,
    filters: Object,
    isLeader: Boolean,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const event = ref(props.filters?.event || '');
const assignedTo = ref(props.filters?.assigned_to || '');
const budget = ref(props.filters?.budget || '');
const perPage = ref(props.filters?.per_page || '20');
const isAvailable = ref(null);

// Debounced search
let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});

// Immediate filters
watch([status, event, assignedTo, budget, perPage], () => applyFilters());

function applyFilters() {
    router.get('/admin/sales/leads', {
        search: search.value || undefined,
        status: status.value || undefined,
        event: event.value || undefined,
        assigned_to: assignedTo.value || undefined,
        budget: budget.value || undefined,
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
    return d.toLocaleDateString('es-US');
}

// Stats cards config
const statsCards = computed(() => {
    const cards = [
        { key: 'total', label: 'Total', value: props.stats?.total ?? 0, color: '#6B7280' },
        { key: 'new', label: 'Nuevos', value: props.stats?.new ?? 0, color: props.statuses?.new?.color ?? '#3B82F6' },
        { key: 'contacted', label: 'Contactados', value: props.stats?.contacted ?? 0, color: props.statuses?.contacted?.color ?? '#8B5CF6' },
        { key: 'follow_up', label: 'Seguimiento', value: props.stats?.follow_up ?? 0, color: props.statuses?.follow_up?.color ?? '#F59E0B' },
        { key: 'interested', label: 'Interesados', value: props.stats?.interested ?? 0, color: props.statuses?.interested?.color ?? '#10B981' },
        { key: 'negotiating', label: 'Negociando', value: props.stats?.negotiating ?? 0, color: props.statuses?.negotiating?.color ?? '#EC4899' },
        { key: 'converted', label: 'Convertidos', value: props.stats?.converted ?? 0, color: props.statuses?.converted?.color ?? '#059669' },
        { key: 'lost', label: 'Perdidos', value: props.stats?.lost ?? 0, color: props.statuses?.lost?.color ?? '#EF4444' },
    ];
    if (props.isLeader) {
        cards.push({ key: 'unassigned', label: 'Sin asignar', value: props.stats?.unassigned ?? 0, color: '#9CA3AF' });
    }
    return cards;
});

function advisorName(lead) {
    if (!lead.assigned_to || typeof lead.assigned_to !== 'object') return null;
    return `${lead.assigned_to.first_name} ${lead.assigned_to.last_name}`;
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Prospectos</h2>
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

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-3 mb-6">
                <div v-for="card in statsCards" :key="card.key"
                    class="bg-white rounded-lg border border-gray-200 px-4 py-3 cursor-pointer hover:shadow-sm transition-shadow"
                    @click.stop="status = status === card.key ? '' : (card.key === 'total' ? '' : card.key)">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ card.label }}</p>
                        <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: card.color }"></span>
                    </div>
                    <p class="text-2xl font-bold mt-1" :style="{ color: card.color }">{{ card.value }}</p>
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
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Teléfono</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Presupuesto</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Evento</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Asesor</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Registro</th>
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
                                        </div>
                                    </div>
                                </td>

                                <!-- Company -->
                                <td class="px-4 py-4 text-sm text-gray-700">
                                    <span v-if="lead.company_name">{{ lead.company_name }}</span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>

                                <!-- Phone -->
                                <td class="px-4 py-4 text-sm text-gray-600 whitespace-nowrap">
                                    {{ lead.phone || '—' }}
                                </td>

                                <!-- Budget -->
                                <td class="px-4 py-4 text-sm text-gray-600 whitespace-nowrap">
                                    {{ lead.budget || '—' }}
                                </td>

                                <!-- Event -->
                                <td class="px-4 py-4 text-sm text-gray-600">
                                    <span v-if="lead.event">{{ lead.event.name }}</span>
                                    <span v-else class="text-gray-400">—</span>
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
                                <td class="px-4 py-4 text-gray-500 text-sm whitespace-nowrap">
                                    {{ formatDate(lead.created_at) }}
                                </td>

                                <!-- Actions -->
                                <td class="px-4 py-4" @click.stop>
                                    <div class="flex items-center justify-end">
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
    </AdminLayout>
</template>
