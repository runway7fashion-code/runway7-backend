<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { EnvelopeIcon, DevicePhoneMobileIcon, XMarkIcon, InformationCircleIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    mediaUsers:         Object,
    events:             Array,
    filters:            Object,
    pendingEmailCount:  Number,
    pendingSmsCount:    Number,
    twilioBalance:      Object,
});

const statusColors = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-red-100 text-red-800',
    rejected: 'bg-orange-100 text-orange-800',
    pending: 'bg-yellow-100 text-yellow-800',
    applicant: 'bg-blue-100 text-blue-800',
};

const search   = ref(props.filters?.search   || '');
const status   = ref(props.filters?.status   || '');
const eventId  = ref(props.filters?.event_id || '');
const category = ref(props.filters?.category || '');
const perPage  = ref(props.filters?.per_page ?? '20');

let timer = null;
function applyFilters() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/admin/media', {
            search:   search.value   || undefined,
            status:   status.value   || undefined,
            event_id: eventId.value  || undefined,
            category: category.value || undefined,
            per_page: perPage.value != 20 ? perPage.value : undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}
watch([search, status, eventId, category, perPage], applyFilters);

const showEmailInfoModal = ref(false);
const showSmsInfoModal = ref(false);

function sendBulkEmails() {
    if (!confirm(`¿Enviar email de onboarding a ${props.pendingEmailCount} media pendiente(s)?`)) return;
    router.post('/admin/media/send-bulk-onboarding', {}, { preserveScroll: true });
}

function sendBulkSms() {
    if (!confirm(`¿Enviar SMS de onboarding a ${props.pendingSmsCount} media pendiente(s)?`)) return;
    router.post('/admin/media/send-bulk-onboarding-sms', {}, { preserveScroll: true });
}

function canSendEmail(m) {
    return m.status === 'pending' && !!m.email && (m.events_as_media ?? []).length > 0;
}

function canSendSms(m) {
    return m.status === 'pending' && !!m.phone && m.phone.startsWith('+') && !m.sms_sent_at && (m.events_as_media ?? []).length > 0;
}

function sendIndividualEmail(m) {
    if (!confirm(`¿Enviar email de onboarding a ${m.first_name} ${m.last_name}?`)) return;
    router.post(`/admin/media/${m.id}/send-onboarding`, {}, { preserveScroll: true });
}

function sendIndividualSms(m) {
    if (!confirm(`¿Enviar SMS de onboarding a ${m.first_name} ${m.last_name}?`)) return;
    router.post(`/admin/media/${m.id}/send-onboarding-sms`, {}, { preserveScroll: true });
}

function updateStatus(m, newStatus) {
    router.patch(`/admin/media/${m.id}/status`, { status: newStatus }, { preserveScroll: true });
}

function categoryLabel(c) {
    return { videographer: 'Videographer', photographer: 'Photographer' }[c] ?? c ?? '—';
}

// Communication logs
const emailHistoryMedia = ref(null);
function openEmailHistory(m, e) { e.stopPropagation(); emailHistoryMedia.value = m; }
function getEmailLogs(m) { return m.communication_logs ?? []; }
function commStatusLabel(s) { return { queued: 'En cola', sent: 'Enviado', failed: 'Fallido' }[s] ?? s; }
function commStatusClass(s) {
    return { queued: 'bg-yellow-100 text-yellow-700', sent: 'bg-green-100 text-green-700', failed: 'bg-red-100 text-red-700' }[s] ?? 'bg-gray-100 text-gray-600';
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Media</h2>
        </template>

        <div>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Media</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ mediaUsers.total }} registrados</p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Twilio Balance -->
                    <div v-if="twilioBalance" class="flex flex-col items-end px-3 py-1.5 border border-gray-200 rounded-lg bg-white">
                        <span class="text-[10px] text-gray-400 font-medium leading-tight">Twilio Balance</span>
                        <span class="text-sm font-bold text-gray-900 leading-tight">{{ twilioBalance.balance }} {{ twilioBalance.currency }}</span>
                    </div>

                    <!-- Bulk email -->
                    <div v-if="pendingEmailCount > 0" class="flex items-center gap-1">
                        <button @click="sendBulkEmails"
                            class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors text-gray-700">
                            <EnvelopeIcon class="w-4 h-4 text-gray-500" />
                            Enviar emails
                            <span class="bg-amber-100 text-amber-700 text-xs font-bold px-1.5 py-0.5 rounded-full">{{ pendingEmailCount }}</span>
                        </button>
                        <button @click="showEmailInfoModal = true" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                            <InformationCircleIcon class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Bulk SMS -->
                    <div v-if="pendingSmsCount > 0" class="flex items-center gap-1">
                        <button @click="sendBulkSms"
                            class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors text-gray-700">
                            <DevicePhoneMobileIcon class="w-4 h-4 text-gray-500" />
                            Enviar SMS
                            <span class="bg-green-100 text-green-700 text-xs font-bold px-1.5 py-0.5 rounded-full">{{ pendingSmsCount }}</span>
                        </button>
                        <button @click="showSmsInfoModal = true" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                            <InformationCircleIcon class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Create -->
                    <Link href="/admin/media/create"
                        class="flex items-center gap-2 px-4 py-2 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 transition-colors">
                        + Crear Media
                    </Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex items-center gap-3 mb-6 flex-wrap">
                <input v-model="search" type="text" placeholder="Buscar nombre, email o teléfono..."
                    class="flex-1 min-w-48 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400" />
                <select v-model="status" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                    <option value="">Todos los estados</option>
                    <option value="applicant">Aplicante</option>
                    <option value="pending">Pendiente</option>
                    <option value="active">Activo</option>
                    <option value="rejected">Rechazado</option>
                    <option value="inactive">Inactivo</option>
                </select>
                <select v-model="eventId" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                    <option value="">Todos los eventos</option>
                    <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
                <select v-model="category" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                    <option value="">Todas las categorías</option>
                    <option value="photographer">Photographer</option>
                    <option value="videographer">Videographer</option>
                </select>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500 uppercase tracking-wider">
                            <th class="text-left px-6 py-3 font-medium">Media</th>
                            <th class="text-left px-6 py-3 font-medium">Categoría</th>
                            <th class="text-left px-6 py-3 font-medium">Eventos</th>
                            <th class="text-left px-6 py-3 font-medium">Estado</th>
                            <th class="text-left px-6 py-3 font-medium">Registro</th>
                            <th class="text-left px-6 py-3 font-medium">Último Correo</th>
                            <th class="text-left px-6 py-3 font-medium">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="mediaUsers.data.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-gray-400 text-sm italic">No se encontraron resultados.</td>
                        </tr>
                        <tr v-for="m in mediaUsers.data" :key="m.id"
                            class="hover:bg-gray-50 transition-colors cursor-pointer"
                            @click="$inertia.visit(`/admin/media/${m.id}`)">
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-gray-900">{{ m.first_name }} {{ m.last_name }}</p>
                                <p class="text-xs text-gray-500">{{ m.email }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                                    :class="m.media_profile?.category === 'photographer' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'">
                                    {{ categoryLabel(m.media_profile?.category) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="m.events_as_media?.length" class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full">
                                    {{ m.events_as_media.length }} evento{{ m.events_as_media.length !== 1 ? 's' : '' }}
                                </span>
                                <span v-else class="text-gray-400 text-xs">Sin eventos</span>
                            </td>
                            <td class="px-6 py-4" @click.stop>
                                <span v-if="m.status === 'active'" class="text-xs font-medium rounded-full px-2.5 py-1 bg-green-100 text-green-800">Activo</span>
                                <span v-else-if="m.status === 'rejected'" class="text-xs font-medium rounded-full px-2.5 py-1 bg-orange-100 text-orange-800">Rechazado</span>
                                <select v-else :value="m.status" @change="updateStatus(m, $event.target.value)"
                                    class="text-xs font-medium rounded-full px-2.5 py-1 border-0 cursor-pointer appearance-none"
                                    :class="statusColors[m.status] || 'bg-gray-100 text-gray-800'">
                                    <option value="applicant">Aplicante</option>
                                    <option value="pending">Pendiente</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-sm">
                                {{ new Date(m.created_at).toLocaleDateString('es-US') }}
                            </td>
                            <td class="px-6 py-4 text-sm" @click.stop>
                                <button v-if="m.welcome_email_sent_at" @click="openEmailHistory(m, $event)"
                                    class="text-xs text-green-600 bg-green-50 px-2 py-0.5 rounded-full hover:bg-green-100 cursor-pointer">
                                    {{ new Date(m.welcome_email_sent_at).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) }}
                                </button>
                                <button v-else @click="openEmailHistory(m, $event)"
                                    class="text-xs text-gray-400 hover:text-gray-600 cursor-pointer">—</button>
                            </td>
                            <td class="px-6 py-4" @click.stop>
                                <div class="flex items-center gap-2">
                                    <button @click="sendIndividualEmail(m)"
                                        class="p-1.5 border border-gray-200 rounded-lg transition-colors"
                                        :class="canSendEmail(m) ? 'hover:bg-gray-50 text-gray-600' : 'opacity-40 cursor-not-allowed text-gray-400'"
                                        :disabled="!canSendEmail(m)" title="Enviar Email">
                                        <EnvelopeIcon class="w-4 h-4" />
                                    </button>
                                    <button @click="sendIndividualSms(m)"
                                        class="p-1.5 border border-gray-200 rounded-lg transition-colors"
                                        :class="canSendSms(m) ? 'hover:bg-gray-50 text-green-600' : 'opacity-40 cursor-not-allowed text-gray-400'"
                                        :disabled="!canSendSms(m)" title="Enviar SMS">
                                        <DevicePhoneMobileIcon class="w-4 h-4" />
                                    </button>
                                    <Link :href="`/admin/media/${m.id}/edit`"
                                        class="p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition-colors" title="Editar">
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="border-t border-gray-100 px-5 py-3 flex items-center justify-between text-sm text-gray-500">
                    <div class="flex items-center gap-3">
                        <span>{{ mediaUsers.from }}–{{ mediaUsers.to }} de {{ mediaUsers.total }} media</span>
                        <select v-model="perPage" class="border border-gray-200 rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    <div v-if="mediaUsers.last_page > 1" class="flex gap-1">
                        <Link v-if="mediaUsers.prev_page_url" :href="mediaUsers.prev_page_url" class="px-3 py-1 border border-gray-200 rounded-lg hover:bg-gray-50">← Anterior</Link>
                        <Link v-if="mediaUsers.next_page_url" :href="mediaUsers.next_page_url" class="px-3 py-1 border border-gray-200 rounded-lg hover:bg-gray-50">Siguiente →</Link>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>

    <!-- Email History Modal -->
    <Teleport to="body">
        <div v-if="emailHistoryMedia" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="emailHistoryMedia = null">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Historial de correos</h3>
                        <p class="text-sm text-gray-500">{{ emailHistoryMedia.first_name }} {{ emailHistoryMedia.last_name }}</p>
                    </div>
                    <button @click="emailHistoryMedia = null" class="text-gray-400 hover:text-gray-600"><XMarkIcon class="w-5 h-5" /></button>
                </div>
                <div v-if="!getEmailLogs(emailHistoryMedia).length" class="text-center py-8 text-gray-400 text-sm italic">No se han enviado correos aún.</div>
                <div v-else class="space-y-3 max-h-80 overflow-y-auto">
                    <div v-for="log in getEmailLogs(emailHistoryMedia)" :key="log.id" class="border border-gray-100 rounded-xl p-4" :class="log.status === 'failed' ? 'bg-red-50/50' : 'bg-gray-50'">
                        <div class="flex items-center justify-between mb-2">
                            <span :class="commStatusClass(log.status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">{{ commStatusLabel(log.status) }}</span>
                            <span class="text-xs text-gray-400">{{ new Date(log.sent_at ?? log.created_at).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' }) }}</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            Enviado por <span class="font-medium text-gray-700">{{ log.sender ? `${log.sender.first_name} ${log.sender.last_name}` : 'Registro automático' }}</span>
                        </div>
                        <div v-if="log.error_message" class="mt-2 text-xs text-red-600 bg-red-100 rounded-lg p-2">{{ log.error_message }}</div>
                    </div>
                </div>
                <div class="mt-5 flex justify-end">
                    <button @click="emailHistoryMedia = null" class="px-5 py-2 bg-black text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors">Cerrar</button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Email Info Modal -->
    <Teleport to="body">
        <div v-if="showEmailInfoModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" @click="showEmailInfoModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0"><EnvelopeIcon class="w-5 h-5 text-amber-600" /></div>
                        <h3 class="text-base font-semibold text-gray-900">¿Cómo funciona el envío masivo de emails?</h3>
                    </div>
                    <button @click="showEmailInfoModal = false" class="text-gray-400 hover:text-gray-600 ml-2"><XMarkIcon class="w-5 h-5" /></button>
                </div>
                <ul class="space-y-3 text-sm text-gray-600">
                    <li class="flex items-start gap-2"><span class="w-1.5 h-1.5 bg-amber-400 rounded-full mt-1.5 flex-shrink-0"></span><span>Solo se envía a media con estado Pendiente que no hayan recibido email de onboarding anteriormente.</span></li>
                    <li class="flex items-start gap-2"><span class="w-1.5 h-1.5 bg-amber-400 rounded-full mt-1.5 flex-shrink-0"></span><span>El email incluye todos los eventos asignados con sus días, credenciales de acceso y links de la app.</span></li>
                    <li class="flex items-start gap-2"><span class="w-1.5 h-1.5 bg-amber-400 rounded-full mt-1.5 flex-shrink-0"></span><span>El envío se procesa en cola — puede tardar unos segundos dependiendo del volumen.</span></li>
                </ul>
                <button @click="showEmailInfoModal = false" class="mt-5 w-full py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors">Entendido</button>
            </div>
        </div>
    </Teleport>

    <!-- SMS Info Modal -->
    <Teleport to="body">
        <div v-if="showSmsInfoModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" @click="showSmsInfoModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0"><DevicePhoneMobileIcon class="w-5 h-5 text-green-600" /></div>
                        <h3 class="text-base font-semibold text-gray-900">¿Cómo funciona el envío masivo de SMS?</h3>
                    </div>
                    <button @click="showSmsInfoModal = false" class="text-gray-400 hover:text-gray-600 ml-2"><XMarkIcon class="w-5 h-5" /></button>
                </div>
                <ul class="space-y-3 text-sm text-gray-600">
                    <li class="flex items-start gap-2"><span class="w-1.5 h-1.5 bg-green-400 rounded-full mt-1.5 flex-shrink-0"></span><span>Solo se envía a media con estado Pendiente que tengan teléfono con código de país (+1...) y no hayan recibido SMS anteriormente.</span></li>
                    <li class="flex items-start gap-2"><span class="w-1.5 h-1.5 bg-green-400 rounded-full mt-1.5 flex-shrink-0"></span><span>El SMS incluye los eventos asignados, credenciales de acceso y links de descarga de la app.</span></li>
                    <li class="flex items-start gap-2"><span class="w-1.5 h-1.5 bg-green-400 rounded-full mt-1.5 flex-shrink-0"></span><span>Requiere saldo disponible en Twilio. Si no hay saldo el envío fallará.</span></li>
                </ul>
                <button @click="showSmsInfoModal = false" class="mt-5 w-full py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors">Entendido</button>
            </div>
        </div>
    </Teleport>
</template>
