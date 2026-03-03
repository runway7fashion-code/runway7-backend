<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { ChevronLeftIcon, ChevronDownIcon, DocumentIcon, ArrowUpTrayIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    case: Object,
    teamMembers: Array,
    designerEmails: Array,
});

const c = computed(() => props.case);

// Status update
const statusOptions = [
    { value: 'open', label: 'Abierto' },
    { value: 'in_progress', label: 'En Proceso' },
    { value: 'resolved', label: 'Resuelto' },
    { value: 'closed', label: 'Cerrado' },
];

function updateStatus(newStatus) {
    router.put(`/admin/accounting/cases/${c.value.id}/status`, { status: newStatus }, { preserveScroll: true });
}

// Badge helpers
function statusBadge(s) {
    const map = {
        open: 'bg-yellow-100 text-yellow-700 border-yellow-200',
        in_progress: 'bg-blue-100 text-blue-700 border-blue-200',
        resolved: 'bg-green-100 text-green-700 border-green-200',
        closed: 'bg-gray-100 text-gray-600 border-gray-200',
    };
    return map[s] || 'bg-gray-100 text-gray-600 border-gray-200';
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

function fmtDate(d) {
    if (!d) return '—';
    return new Date(d + 'T00:00:00').toLocaleDateString('es-US', { day: '2-digit', month: 'short', year: 'numeric' });
}

function isImage(mime) {
    return mime && mime.startsWith('image/');
}

// New message form
const senderType = ref('team');
const msgForm = useForm({
    sender_type: 'team',
    team_member_id: '',
    message: '',
    message_date: new Date().toISOString().slice(0, 10),
    attachments: [],
});

const msgFiles = ref([]);

function onMsgFiles(e) {
    const newFiles = Array.from(e.target.files);
    msgFiles.value.push(...newFiles);
    msgForm.attachments = msgFiles.value;
}

function removeMsgFile(idx) {
    msgFiles.value.splice(idx, 1);
    msgForm.attachments = msgFiles.value;
}

function onMsgDrop(e) {
    e.preventDefault();
    const newFiles = Array.from(e.dataTransfer.files);
    msgFiles.value.push(...newFiles);
    msgForm.attachments = msgFiles.value;
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function switchSenderType(type) {
    senderType.value = type;
    msgForm.sender_type = type;
    if (type === 'designer') {
        msgForm.team_member_id = '';
    }
}

function submitMessage() {
    msgForm.post(`/admin/accounting/cases/${c.value.id}/messages`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            msgForm.reset();
            msgFiles.value = [];
            msgForm.message_date = new Date().toISOString().slice(0, 10);
            msgForm.sender_type = senderType.value;
        },
    });
}

// Delete case
const showDeleteModal = ref(false);
const showStatusDropdown = ref(false);

function doDelete() {
    router.delete(`/admin/accounting/cases/${c.value.id}`, {
        onFinish: () => { showDeleteModal.value = false; },
    });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/accounting/cases" class="text-gray-400 hover:text-gray-600">
                    <ChevronLeftIcon class="w-5 h-5" />
                </Link>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ c.case_number }}</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Detalle del caso</p>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Case header card -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <h2 class="text-xl font-bold text-gray-900">{{ c.case_number }}</h2>
                            <!-- Status dropdown -->
                            <div class="relative">
                                <button @click="showStatusDropdown = !showStatusDropdown"
                                    :class="statusBadge(c.status)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium border cursor-pointer hover:opacity-80">
                                    {{ c.status_label }}
                                    <ChevronDownIcon class="w-3 h-3" />
                                </button>
                                <div v-if="showStatusDropdown" class="absolute z-10 mt-1 bg-white rounded-lg border border-gray-200 shadow-lg py-1 min-w-[140px]">
                                    <button v-for="opt in statusOptions" :key="opt.value"
                                        @click="updateStatus(opt.value); showStatusDropdown = false"
                                        class="w-full text-left px-3 py-1.5 text-sm hover:bg-gray-50"
                                        :class="opt.value === c.status ? 'font-semibold text-black' : 'text-gray-600'">
                                        {{ opt.label }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-4 text-sm">
                            <div>
                                <span class="text-gray-500">Disenador:</span>
                                <span class="ml-1 font-medium text-gray-900">{{ c.brand_name || '—' }}</span>
                                <span class="text-gray-500 ml-1">({{ c.designer_name }})</span>
                            </div>
                            <div v-if="c.event_name">
                                <span class="text-gray-500">Evento:</span>
                                <span class="ml-1 font-medium text-gray-900">{{ c.event_name }}</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-3 text-sm">
                            <span :class="channelBadge(c.channel)" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                                {{ c.channel_label }}
                            </span>
                            <span :class="typeBadge(c.case_type)" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                                {{ c.case_type_label }}
                            </span>
                            <span class="text-gray-500">Fecha reclamo: <strong class="text-gray-700">{{ fmtDate(c.claim_date) }}</strong></span>
                            <span v-if="c.contact_email" class="text-gray-500">Correo: <strong class="text-gray-700">{{ c.contact_email }}</strong></span>
                        </div>

                        <p class="text-xs text-gray-400">Creado por {{ c.created_by }} el {{ c.created_at }}</p>
                    </div>

                    <button @click="showDeleteModal = true"
                        class="px-4 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                        Eliminar Caso
                    </button>
                </div>
            </div>

            <!-- Messages timeline -->
            <div class="space-y-4">
                <h3 class="text-base font-semibold text-gray-900">Hilo de Mensajes ({{ c.messages.length }})</h3>

                <div v-for="msg in c.messages" :key="msg.id"
                    class="bg-white rounded-xl border-l-4 border border-gray-200 p-5"
                    :class="msg.sender_type === 'designer' ? 'border-l-red-500' : 'border-l-green-500'">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span v-if="msg.sender_type === 'designer'"
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                Disenador
                            </span>
                            <span v-else
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                Equipo
                            </span>
                            <span class="text-sm font-medium text-gray-900">
                                {{ msg.sender_type === 'designer' ? c.designer_name : (msg.team_member_name || 'Equipo') }}
                            </span>
                        </div>
                        <span class="text-xs text-gray-400">{{ fmtDate(msg.message_date) }}</span>
                    </div>

                    <!-- Message body -->
                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ msg.message }}</p>

                    <!-- Attachments -->
                    <div v-if="msg.attachments.length" class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3">
                        <a v-for="att in msg.attachments" :key="att.id"
                            :href="`/storage/${att.file_url}`" target="_blank"
                            class="group block rounded-lg border border-gray-200 overflow-hidden hover:border-gray-400 transition-colors">
                            <div v-if="att.is_image" class="aspect-square bg-gray-100">
                                <img :src="`/storage/${att.file_url}`" :alt="att.file_name"
                                    class="w-full h-full object-cover" />
                            </div>
                            <div v-else class="aspect-square bg-gray-50 flex items-center justify-center">
                                <DocumentIcon class="w-8 h-8 text-gray-300" />
                            </div>
                            <div class="px-2 py-1.5 bg-white border-t border-gray-100">
                                <p class="text-xs text-gray-600 truncate group-hover:text-black">{{ att.file_name }}</p>
                                <p class="text-xs text-gray-400">{{ formatSize(att.file_size) }}</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Empty messages -->
                <div v-if="!c.messages.length" class="bg-white rounded-xl border border-gray-200 p-8 text-center">
                    <p class="text-gray-400 text-sm">No hay mensajes en este caso.</p>
                </div>
            </div>

            <!-- Add message form -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Agregar Mensaje</h3>

                <!-- Sender type toggle -->
                <div class="flex gap-2 mb-4">
                    <button type="button" @click="switchSenderType('team')"
                        class="px-4 py-2 text-sm font-medium rounded-lg border transition-colors"
                        :class="senderType === 'team' ? 'bg-green-50 border-green-300 text-green-700' : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50'">
                        Respuesta del Equipo
                    </button>
                    <button type="button" @click="switchSenderType('designer')"
                        class="px-4 py-2 text-sm font-medium rounded-lg border transition-colors"
                        :class="senderType === 'designer' ? 'bg-red-50 border-red-300 text-red-700' : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50'">
                        Mensaje del Disenador
                    </button>
                </div>

                <form @submit.prevent="submitMessage" class="space-y-4">
                    <div v-if="senderType === 'team'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quien responde</label>
                            <select v-model="msgForm.team_member_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black">
                                <option value="">Seleccionar...</option>
                                <option v-for="tm in teamMembers" :key="tm.id" :value="tm.id">
                                    {{ tm.first_name }} {{ tm.last_name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                            <input v-model="msgForm.message_date" type="date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black" />
                        </div>
                    </div>

                    <div v-if="senderType === 'designer'" class="max-w-xs">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fecha del mensaje</label>
                        <input v-model="msgForm.message_date" type="date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje *</label>
                        <textarea v-model="msgForm.message" rows="4"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black resize-y"
                            :placeholder="senderType === 'team' ? 'Respuesta del equipo...' : 'Mensaje del disenador...'"></textarea>
                        <p v-if="msgForm.errors.message" class="text-red-500 text-xs mt-1">{{ msgForm.errors.message }}</p>
                    </div>

                    <!-- File upload -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Archivos adjuntos</label>
                        <div @dragover.prevent @drop="onMsgDrop"
                            class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-gray-400 transition-colors cursor-pointer"
                            @click="$refs.msgFileInput.click()">
                            <ArrowUpTrayIcon class="mx-auto h-6 w-6 text-gray-400" />
                            <p class="mt-1 text-sm text-gray-500">Arrastra archivos o haz click</p>
                        </div>
                        <input ref="msgFileInput" type="file" multiple class="hidden"
                            accept="image/jpeg,image/png,image/gif,application/pdf,.doc,.docx"
                            @change="onMsgFiles" />
                        <div v-if="msgFiles.length" class="mt-2 space-y-1">
                            <div v-for="(file, idx) in msgFiles" :key="idx"
                                class="flex items-center gap-2 px-3 py-1.5 bg-gray-50 rounded-lg border border-gray-200 text-sm">
                                <span class="flex-1 truncate text-gray-700">{{ file.name }}</span>
                                <span class="text-xs text-gray-400">{{ formatSize(file.size) }}</span>
                                <button type="button" @click="removeMsgFile(idx)" class="text-gray-400 hover:text-red-500">
                                    <XMarkIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" :disabled="msgForm.processing"
                        class="px-5 py-2.5 text-sm font-medium text-black rounded-lg disabled:opacity-50 transition-colors"
                        style="background-color: #D4AF37;">
                        {{ msgForm.processing ? 'Enviando...' : 'Agregar Mensaje' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Delete confirmation modal -->
        <Teleport to="body">
            <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="fixed inset-0 bg-black/50" @click="showDeleteModal = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Eliminar Caso</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Estas seguro de eliminar el caso <strong>{{ c.case_number }}</strong>?
                        Se eliminaran todos los mensajes y archivos adjuntos.
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

        <!-- Close status dropdown on outside click -->
        <Teleport to="body">
            <div v-if="showStatusDropdown" class="fixed inset-0 z-0" @click="showStatusDropdown = false"></div>
        </Teleport>
    </AdminLayout>
</template>
