<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

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
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
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
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                                    </svg>
                                    {{ c.messages_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <Link :href="`/admin/accounting/cases/${c.id}`"
                                        class="p-1.5 text-gray-400 hover:text-gray-700 rounded-lg hover:bg-gray-100 transition-colors"
                                        title="Ver caso">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </Link>
                                    <button @click="confirmDelete(c)"
                                        class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition-colors"
                                        title="Eliminar">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Empty state -->
                <div v-else class="px-8 py-16 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
                    </svg>
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
