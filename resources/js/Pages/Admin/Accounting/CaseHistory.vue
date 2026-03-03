<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { PlusIcon, ChatBubbleLeftRightIcon, EyeIcon, TrashIcon, ClipboardDocumentListIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    cases: Object,
    events: Array,
    totalCount: Number,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');
const eventId = ref(props.filters?.event_id ?? '');
const caseType = ref(props.filters?.case_type ?? '');
const channel = ref(props.filters?.channel ?? '');
const status = ref(props.filters?.status ?? '');

function getFilterParams() {
    return {
        search: search.value || undefined,
        event_id: eventId.value || undefined,
        case_type: caseType.value || undefined,
        channel: channel.value || undefined,
        status: status.value || undefined,
    };
}

let searchTimeout = null;
function applySearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/admin/accounting/cases', getFilterParams(), { preserveState: true, replace: true });
    }, 300);
}

watch([eventId, caseType, channel, status], () => {
    clearTimeout(searchTimeout);
    router.get('/admin/accounting/cases', getFilterParams(), { preserveState: true, replace: true });
});

function fmtDate(d) {
    if (!d) return '—';
    return new Date(d + 'T00:00:00').toLocaleDateString('es-US', { day: '2-digit', month: 'short', year: 'numeric' });
}

function channelBadge(ch) {
    const map = {
        email: 'bg-blue-100 text-blue-700',
        whatsapp: 'bg-green-100 text-green-700',
        phone: 'bg-orange-100 text-orange-700',
        sms: 'bg-gray-100 text-gray-700',
        dm: 'bg-purple-100 text-purple-700',
    };
    return map[ch] || 'bg-gray-100 text-gray-600';
}

function typeBadge(t) {
    const map = {
        claim: 'bg-red-100 text-red-700',
        complaint: 'bg-yellow-100 text-yellow-700',
        payment: 'bg-blue-100 text-blue-700',
        refund: 'bg-orange-100 text-orange-700',
    };
    return map[t] || 'bg-gray-100 text-gray-600';
}

function statusBadge(s) {
    const map = {
        open: 'bg-yellow-100 text-yellow-700',
        in_progress: 'bg-blue-100 text-blue-700',
        resolved: 'bg-green-100 text-green-700',
        closed: 'bg-gray-100 text-gray-600',
    };
    return map[s] || 'bg-gray-100 text-gray-600';
}

// Delete confirmation
const showDeleteModal = ref(false);
const deletingCase = ref(null);

function confirmDelete(c) {
    deletingCase.value = c;
    showDeleteModal.value = true;
}

function doDelete() {
    if (!deletingCase.value) return;
    router.delete(`/admin/accounting/cases/${deletingCase.value.id}`, {
        preserveScroll: true,
        onFinish: () => { showDeleteModal.value = false; deletingCase.value = null; },
    });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Historial</h2>
                <p class="text-sm text-gray-500 mt-0.5">Bitacora de comunicacion con disenadores</p>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Header row -->
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">{{ totalCount }} caso{{ totalCount !== 1 ? 's' : '' }}</p>
                <Link href="/admin/accounting/cases/create"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-black rounded-lg transition-colors"
                    style="background-color: #D4AF37;"
                >
                    <PlusIcon class="w-4 h-4 mr-2" />
                    Nuevo Registro
                </Link>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <input v-model="search" @input="applySearch" type="text" placeholder="Buscar por marca, nombre, # caso..."
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black w-64" />
                <select v-model="eventId" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black">
                    <option value="">Todos los eventos</option>
                    <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                </select>
                <select v-model="caseType" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black">
                    <option value="">Todos los tipos</option>
                    <option value="claim">Reclamo</option>
                    <option value="complaint">Queja</option>
                    <option value="payment">Pagos</option>
                    <option value="refund">Devolucion</option>
                </select>
                <select v-model="channel" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black">
                    <option value="">Todos los canales</option>
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                    <option value="phone">Llamada</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="dm">DM</option>
                </select>
                <select v-model="status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black">
                    <option value="">Todos los estados</option>
                    <option value="open">Abierto</option>
                    <option value="in_progress">En Proceso</option>
                    <option value="resolved">Resuelto</option>
                    <option value="closed">Cerrado</option>
                </select>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table v-if="cases.data.length" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"># Caso</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Disenador / Marca</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Canal</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Fecha Reclamo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Mensajes</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <tr v-for="c in cases.data" :key="c.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3">
                                <Link :href="`/admin/accounting/cases/${c.id}`" class="text-sm font-semibold hover:underline" style="color: #D4AF37;">
                                    {{ c.case_number }}
                                </Link>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-semibold text-gray-900">{{ c.brand_name || '—' }}</div>
                                <div class="text-xs text-gray-500">{{ c.designer_name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="channelBadge(c.channel)" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                                    {{ c.channel_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="typeBadge(c.case_type)" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                                    {{ c.case_type_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ fmtDate(c.claim_date) }}</td>
                            <td class="px-4 py-3">
                                <span :class="statusBadge(c.status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                                    {{ c.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1 text-sm text-gray-600">
                                    <ChatBubbleLeftRightIcon class="w-4 h-4" />
                                    {{ c.messages_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <Link :href="`/admin/accounting/cases/${c.id}`"
                                        class="p-1.5 text-gray-400 hover:text-gray-700 rounded-lg hover:bg-gray-100 transition-colors"
                                        title="Ver caso">
                                        <EyeIcon class="w-4 h-4" />
                                    </Link>
                                    <button @click="confirmDelete(c)"
                                        class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition-colors"
                                        title="Eliminar">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Empty state -->
                <div v-else class="px-8 py-16 text-center">
                    <ClipboardDocumentListIcon class="mx-auto h-12 w-12 text-gray-300" />
                    <p class="mt-4 text-gray-500 text-sm">No hay casos registrados</p>
                    <Link href="/admin/accounting/cases/create"
                        class="mt-4 inline-flex items-center px-4 py-2 text-sm font-medium text-black rounded-lg transition-colors"
                        style="background-color: #D4AF37;">
                        Crear primer caso
                    </Link>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="cases.last_page > 1" class="flex justify-center gap-1">
                <template v-for="link in cases.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url"
                        class="px-3 py-1.5 text-sm rounded-lg border transition-colors"
                        :class="link.active ? 'bg-black text-white border-black' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                        v-html="link.label" />
                    <span v-else class="px-3 py-1.5 text-sm text-gray-400" v-html="link.label" />
                </template>
            </div>
        </div>

        <!-- Delete confirmation modal -->
        <Teleport to="body">
            <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="fixed inset-0 bg-black/50" @click="showDeleteModal = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Eliminar Caso</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Estas seguro de eliminar el caso <strong>{{ deletingCase?.case_number }}</strong>?
                        Se eliminaran todos los mensajes y archivos adjuntos. Esta accion no se puede deshacer.
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button @click="showDeleteModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button @click="doDelete" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
                            Eliminar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
