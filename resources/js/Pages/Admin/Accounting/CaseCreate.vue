<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    events: Array,
    teamMembers: Array,
    nextCaseNumber: String,
});

const form = useForm({
    designer_id: null,
    event_id: '',
    channel: '',
    case_type: '',
    contact_email: '',
    save_email: false,
    claim_date: new Date().toISOString().slice(0, 10),
    message: '',
    message_date: new Date().toISOString().slice(0, 10),
    attachments: [],
    team_response: false,
    team_member_id: '',
    team_message: '',
    team_message_date: new Date().toISOString().slice(0, 10),
    team_attachments: [],
});

// Designer search
const designerQuery = ref('');
const designerResults = ref([]);
const selectedDesigner = ref(null);
const showDesignerDropdown = ref(false);
const designerEmails = ref([]);
const isNewEmail = ref(false);
let searchTimer = null;

function searchDesigner() {
    clearTimeout(searchTimer);
    if (!designerQuery.value || designerQuery.value.length < 2) {
        designerResults.value = [];
        return;
    }
    searchTimer = setTimeout(async () => {
        try {
            // Use the same search endpoint but without event_id requirement — search all designers
            const res = await fetch(`/admin/accounting/api/designers-all-events?search=${encodeURIComponent(designerQuery.value)}`);
            const data = await res.json();
            // Deduplicate by designer id
            const seen = new Set();
            designerResults.value = data.filter(d => {
                if (seen.has(d.id)) return false;
                seen.add(d.id);
                return true;
            });
        } catch (e) {
            designerResults.value = [];
        }
    }, 250);
}

async function selectDesigner(d) {
    selectedDesigner.value = d;
    form.designer_id = d.id;
    designerQuery.value = (d.brand_name || d.brand || '') + ' — ' + (d.first_name ? d.first_name + ' ' + d.last_name : d.name);
    showDesignerDropdown.value = false;
    designerResults.value = [];

    // Load designer emails
    try {
        const res = await fetch(`/admin/accounting/api/designer-emails/${d.id}`);
        designerEmails.value = await res.json();
    } catch (e) {
        designerEmails.value = [];
    }
}

function clearDesigner() {
    selectedDesigner.value = null;
    form.designer_id = null;
    designerQuery.value = '';
    designerEmails.value = [];
    form.contact_email = '';
}

watch(() => form.contact_email, (val) => {
    if (!val) { isNewEmail.value = false; return; }
    isNewEmail.value = !designerEmails.value.some(e => e.email === val);
});

// Clear contact field when channel changes
watch(() => form.channel, () => {
    form.contact_email = '';
    isNewEmail.value = false;
});

const contactConfig = computed(() => {
    switch (form.channel) {
        case 'email':
            return {
                label: 'Correo de contacto',
                placeholder: 'correo@ejemplo.com',
                type: 'email',
                hint: null,
                showSaved: true,
            };
        case 'whatsapp':
            return {
                label: 'Número de WhatsApp',
                placeholder: '+1 (555) 000-0000',
                type: 'tel',
                hint: 'Puede ser un número distinto al registrado',
                showSaved: false,
            };
        case 'sms':
        case 'phone':
            return {
                label: 'Número de teléfono',
                placeholder: '+1 (555) 000-0000',
                type: 'tel',
                hint: null,
                showSaved: false,
            };
        case 'dm':
            return {
                label: 'Usuario de Instagram',
                placeholder: '@usuario o URL del perfil',
                type: 'text',
                hint: 'Puede ser una cuenta distinta a la oficial de la marca',
                showSaved: false,
            };
        default:
            return {
                label: 'Contacto',
                placeholder: '',
                type: 'text',
                hint: null,
                showSaved: false,
            };
    }
});

// File handling
const designerFiles = ref([]);
const teamFiles = ref([]);

function onDesignerFiles(e) {
    const newFiles = Array.from(e.target.files);
    designerFiles.value.push(...newFiles);
    form.attachments = designerFiles.value;
}

function removeDesignerFile(idx) {
    designerFiles.value.splice(idx, 1);
    form.attachments = designerFiles.value;
}

function onTeamFiles(e) {
    const newFiles = Array.from(e.target.files);
    teamFiles.value.push(...newFiles);
    form.team_attachments = teamFiles.value;
}

function removeTeamFile(idx) {
    teamFiles.value.splice(idx, 1);
    form.team_attachments = teamFiles.value;
}

// Drag and drop
function onDesignerDrop(e) {
    e.preventDefault();
    const newFiles = Array.from(e.dataTransfer.files);
    designerFiles.value.push(...newFiles);
    form.attachments = designerFiles.value;
}

function onTeamDrop(e) {
    e.preventDefault();
    const newFiles = Array.from(e.dataTransfer.files);
    teamFiles.value.push(...newFiles);
    form.team_attachments = teamFiles.value;
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function submit() {
    form.post('/admin/accounting/cases', {
        forceFormData: true,
    });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Nuevo Registro</h2>
                <p class="text-sm text-gray-500 mt-0.5">Crear caso en la bitacora</p>
            </div>
        </template>

        <form @submit.prevent="submit" class="max-w-4xl space-y-8">
            <!-- Section 1: Case Data -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Datos del Caso</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Designer search -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Disenador *</label>
                        <div v-if="selectedDesigner" class="flex items-center gap-3 px-3 py-2 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="flex-1">
                                <span class="font-semibold text-sm text-gray-900">{{ selectedDesigner.brand_name || selectedDesigner.brand || '—' }}</span>
                                <span class="text-xs text-gray-500 ml-2">{{ selectedDesigner.first_name ? selectedDesigner.first_name + ' ' + selectedDesigner.last_name : selectedDesigner.name }}</span>
                            </div>
                            <button type="button" @click="clearDesigner" class="text-gray-400 hover:text-red-500">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div v-else class="relative">
                            <input v-model="designerQuery" @input="searchDesigner" @focus="showDesignerDropdown = true"
                                type="text" placeholder="Buscar por marca o nombre..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black" />
                            <div v-if="showDesignerDropdown && designerResults.length"
                                class="absolute z-20 mt-1 w-full bg-white rounded-lg border border-gray-200 shadow-lg max-h-60 overflow-y-auto">
                                <button v-for="d in designerResults" :key="d.id" type="button"
                                    @click="selectDesigner(d)"
                                    class="w-full text-left px-4 py-2.5 hover:bg-gray-50 border-b border-gray-100 last:border-0">
                                    <div class="text-sm font-semibold text-gray-900">{{ d.brand_name || d.brand || '—' }}</div>
                                    <div class="text-xs text-gray-500">{{ d.first_name ? d.first_name + ' ' + d.last_name : d.name }}</div>
                                </button>
                            </div>
                        </div>
                        <p v-if="form.errors.designer_id" class="text-red-500 text-xs mt-1">{{ form.errors.designer_id }}</p>
                    </div>

                    <!-- Event -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Evento</label>
                        <select v-model="form.event_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black">
                            <option value="">Sin evento</option>
                            <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                        </select>
                    </div>

                    <!-- Case number (read only) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID de Caso</label>
                        <input :value="nextCaseNumber" disabled class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-500" />
                    </div>

                    <!-- Channel -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Canal *</label>
                        <select v-model="form.channel" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black">
                            <option value="">Seleccionar...</option>
                            <option value="email">Email</option>
                            <option value="sms">SMS</option>
                            <option value="phone">Llamada</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="dm">DM</option>
                        </select>
                        <p v-if="form.errors.channel" class="text-red-500 text-xs mt-1">{{ form.errors.channel }}</p>
                    </div>

                    <!-- Case type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
                        <select v-model="form.case_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black">
                            <option value="">Seleccionar...</option>
                            <option value="claim">Reclamo</option>
                            <option value="complaint">Queja</option>
                            <option value="payment">Pagos</option>
                            <option value="refund">Devolucion</option>
                        </select>
                        <p v-if="form.errors.case_type" class="text-red-500 text-xs mt-1">{{ form.errors.case_type }}</p>
                    </div>
                </div>
            </div>

            <!-- Section 2: Designer Contact -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Contacto del Disenador</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Contact (dynamic by channel) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ contactConfig.label }}
                        </label>
                        <div class="space-y-2">
                            <!-- Saved emails dropdown (email channel only) -->
                            <select v-if="contactConfig.showSaved && designerEmails.length" v-model="form.contact_email"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black">
                                <option value="">Seleccionar o escribir abajo...</option>
                                <option v-for="e in designerEmails" :key="e.email" :value="e.email">
                                    {{ e.email }} ({{ e.label }})
                                </option>
                            </select>
                            <input v-model="form.contact_email"
                                :type="contactConfig.type"
                                :placeholder="contactConfig.placeholder"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black" />
                            <p v-if="contactConfig.hint" class="text-xs text-gray-400">{{ contactConfig.hint }}</p>
                            <label v-if="contactConfig.showSaved && isNewEmail && form.contact_email" class="flex items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" v-model="form.save_email" class="rounded border-gray-300 text-black focus:ring-black" />
                                Guardar este correo para futuras referencias
                            </label>
                        </div>
                    </div>

                    <!-- Claim date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de reclamo *</label>
                        <input v-model="form.claim_date" type="date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black" />
                        <p v-if="form.errors.claim_date" class="text-red-500 text-xs mt-1">{{ form.errors.claim_date }}</p>
                    </div>
                </div>
            </div>

            <!-- Section 3: Designer Message -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Mensaje del Disenador</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Que solicito/informo el cliente? *</label>
                        <textarea v-model="form.message" rows="5"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black resize-y"
                            placeholder="Describe el mensaje del disenador..."></textarea>
                        <p v-if="form.errors.message" class="text-red-500 text-xs mt-1">{{ form.errors.message }}</p>
                    </div>

                    <div class="max-w-xs">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fecha del mensaje *</label>
                        <input v-model="form.message_date" type="date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black" />
                    </div>

                    <!-- File upload -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Archivos adjuntos</label>
                        <div @dragover.prevent @drop="onDesignerDrop"
                            class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition-colors cursor-pointer"
                            @click="$refs.designerFileInput.click()">
                            <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                            <p class="mt-2 text-sm text-gray-500">Arrastra archivos aqui o haz click para seleccionar</p>
                            <p class="text-xs text-gray-400 mt-1">Imagenes (jpg, png, gif) y documentos (pdf, doc, docx)</p>
                        </div>
                        <input ref="designerFileInput" type="file" multiple class="hidden"
                            accept="image/jpeg,image/png,image/gif,application/pdf,.doc,.docx"
                            @change="onDesignerFiles" />
                        <div v-if="designerFiles.length" class="mt-3 space-y-2">
                            <div v-for="(file, idx) in designerFiles" :key="idx"
                                class="flex items-center gap-3 px-3 py-2 bg-gray-50 rounded-lg border border-gray-200">
                                <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                                </svg>
                                <span class="text-sm text-gray-700 flex-1 truncate">{{ file.name }}</span>
                                <span class="text-xs text-gray-400">{{ formatSize(file.size) }}</span>
                                <button type="button" @click="removeDesignerFile(idx)" class="text-gray-400 hover:text-red-500">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 4: Team Response (optional) -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" v-model="form.team_response" class="rounded border-gray-300 text-black focus:ring-black" />
                    <span class="text-base font-semibold text-gray-900">Agregar respuesta del equipo ahora</span>
                </label>

                <div v-if="form.team_response" class="mt-4 space-y-4 pl-0">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quien respondio</label>
                            <select v-model="form.team_member_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black">
                                <option value="">Seleccionar...</option>
                                <option v-for="tm in teamMembers" :key="tm.id" :value="tm.id">
                                    {{ tm.first_name }} {{ tm.last_name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de respuesta</label>
                            <input v-model="form.team_message_date" type="date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje de respuesta</label>
                        <textarea v-model="form.team_message" rows="4"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black resize-y"
                            placeholder="Respuesta del equipo..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Archivos adjuntos</label>
                        <div @dragover.prevent @drop="onTeamDrop"
                            class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition-colors cursor-pointer"
                            @click="$refs.teamFileInput.click()">
                            <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                            <p class="mt-2 text-sm text-gray-500">Arrastra archivos aqui o haz click para seleccionar</p>
                        </div>
                        <input ref="teamFileInput" type="file" multiple class="hidden"
                            accept="image/jpeg,image/png,image/gif,application/pdf,.doc,.docx"
                            @change="onTeamFiles" />
                        <div v-if="teamFiles.length" class="mt-3 space-y-2">
                            <div v-for="(file, idx) in teamFiles" :key="idx"
                                class="flex items-center gap-3 px-3 py-2 bg-gray-50 rounded-lg border border-gray-200">
                                <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                                </svg>
                                <span class="text-sm text-gray-700 flex-1 truncate">{{ file.name }}</span>
                                <span class="text-xs text-gray-400">{{ formatSize(file.size) }}</span>
                                <button type="button" @click="removeTeamFile(idx)" class="text-gray-400 hover:text-red-500">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error summary -->
            <div v-if="Object.keys(form.errors).length" class="bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-sm text-red-700 font-medium mb-1">Hay errores en el formulario:</p>
                <ul class="text-sm text-red-600 list-disc pl-5">
                    <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
                </ul>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-4">
                <Link href="/admin/accounting/cases"
                    class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancelar
                </Link>
                <button type="submit" :disabled="form.processing"
                    class="px-6 py-2.5 text-sm font-medium text-black rounded-lg disabled:opacity-50 transition-colors"
                    style="background-color: #D4AF37;">
                    {{ form.processing ? 'Creando...' : 'Crear Registro' }}
                </button>
            </div>
        </form>
    </AdminLayout>
</template>
