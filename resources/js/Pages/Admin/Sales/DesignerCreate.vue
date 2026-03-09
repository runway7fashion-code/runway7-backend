<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { DocumentArrowUpIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    events: Array,
    packages: Array,
    countries: Array,
    salesReps: Array,
});

const phoneCodes = [
    { code: '+1', country: 'US/CA', flag: '🇺🇸' },
    { code: '+52', country: 'MX', flag: '🇲🇽' },
    { code: '+44', country: 'UK', flag: '🇬🇧' },
    { code: '+33', country: 'FR', flag: '🇫🇷' },
    { code: '+39', country: 'IT', flag: '🇮🇹' },
    { code: '+34', country: 'ES', flag: '🇪🇸' },
    { code: '+49', country: 'DE', flag: '🇩🇪' },
    { code: '+55', country: 'BR', flag: '🇧🇷' },
    { code: '+57', country: 'CO', flag: '🇨🇴' },
    { code: '+51', country: 'PE', flag: '🇵🇪' },
    { code: '+54', country: 'AR', flag: '🇦🇷' },
    { code: '+56', country: 'CL', flag: '🇨🇱' },
    { code: '+58', country: 'VE', flag: '🇻🇪' },
    { code: '+593', country: 'EC', flag: '🇪🇨' },
    { code: '+91', country: 'IN', flag: '🇮🇳' },
    { code: '+86', country: 'CN', flag: '🇨🇳' },
    { code: '+81', country: 'JP', flag: '🇯🇵' },
    { code: '+82', country: 'KR', flag: '🇰🇷' },
    { code: '+234', country: 'NG', flag: '🇳🇬' },
    { code: '+27', country: 'ZA', flag: '🇿🇦' },
    { code: '+971', country: 'AE', flag: '🇦🇪' },
];

const phoneCode = ref('+1');
const phoneNumber = ref('');

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    brand_name: '',
    country: '',
    event_id: '',
    package_id: '',
    agreed_price: '',
    downpayment: '',
    installments_count: 3,
    notes: '',
    sales_rep_id: '',
    documents: [],
});

// Documents
const pendingDocs = ref([]); // [{ file, type, notes, preview }]
const docFileInput = ref(null);
const newDocType = ref('contract');
const newDocNotes = ref('');

function handleDocFileSelect(e) {
    const file = e.target.files[0];
    if (!file) return;
    pendingDocs.value.push({ file, type: newDocType.value, notes: newDocNotes.value, name: file.name });
    newDocNotes.value = '';
    e.target.value = '';
}

function removeDoc(index) {
    pendingDocs.value.splice(index, 1);
}

const docTypeLabel = (type) => ({ contract: 'Contract', payment_proof: 'Payment Proof', other: 'Other' }[type] ?? type);

function submit() {
    form.phone = phoneNumber.value ? `${phoneCode.value}${phoneNumber.value}` : '';
    form.documents = pendingDocs.value.map(d => ({ file: d.file, type: d.type, notes: d.notes }));
    form.post('/admin/sales/designers', {
        preserveScroll: true,
        forceFormData: true,
    });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link href="/admin/sales/designers" class="text-gray-400 hover:text-gray-600 text-sm">&larr; Registros</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Registrar Diseñador</h2>
            </div>
        </template>

        <div>
            <form @submit.prevent="submit">
                <div class="flex flex-col lg:grid lg:grid-cols-2 gap-6 lg:items-start">
                    <!-- Columna izquierda: Info del Diseñador + Documentos (lg) -->
                    <div class="contents lg:block lg:space-y-6">
                    <div class="bg-white rounded-xl border border-gray-200 p-6 order-1">
                        <h3 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4">Información del Diseñador</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                                <input v-model="form.first_name" type="text" class="input" />
                                <p v-if="form.errors.first_name" class="err">{{ form.errors.first_name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Apellido *</label>
                                <input v-model="form.last_name" type="text" class="input" />
                                <p v-if="form.errors.last_name" class="err">{{ form.errors.last_name }}</p>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                <input v-model="form.email" type="email" class="input" />
                                <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                                <div class="flex gap-2">
                                    <select v-model="phoneCode" class="w-28 border border-gray-300 rounded-lg px-2 py-2 text-sm focus:ring-2 focus:ring-yellow-400">
                                        <option v-for="pc in phoneCodes" :key="pc.code" :value="pc.code">{{ pc.flag }} {{ pc.code }}</option>
                                    </select>
                                    <input v-model="phoneNumber" type="text" placeholder="Número..." class="input flex-1" />
                                </div>
                                <p v-if="form.errors.phone" class="err">{{ form.errors.phone }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Marca / Brand *</label>
                                <input v-model="form.brand_name" type="text" class="input" />
                                <p v-if="form.errors.brand_name" class="err">{{ form.errors.brand_name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">País *</label>
                                <select v-model="form.country" class="input bg-white">
                                    <option value="">Seleccionar...</option>
                                    <option v-for="c in countries" :key="c" :value="c">{{ c }}</option>
                                </select>
                                <p v-if="form.errors.country" class="err">{{ form.errors.country }}</p>
                            </div>
                        </div>
                    </div>

                        <!-- Documentos (columna izquierda, fila 2) -->
                        <div class="bg-white rounded-xl border border-gray-200 p-6 order-3">
                            <h3 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4">Documents <span class="normal-case font-normal text-gray-400">(optional)</span></h3>
                            <div class="flex flex-wrap items-end gap-3 mb-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                                    <select v-model="newDocType" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-400 bg-white">
                                        <option value="contract">Contract</option>
                                        <option value="payment_proof">Payment Proof</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="flex-1 min-w-[120px]">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                                    <input v-model="newDocNotes" type="text" placeholder="Optional notes..." class="input" />
                                </div>
                                <div>
                                    <input ref="docFileInput" type="file" class="hidden" @change="handleDocFileSelect" />
                                    <button type="button" @click="docFileInput.click()"
                                        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-white hover:border-yellow-400 transition-colors">
                                        <DocumentArrowUpIcon class="h-4 w-4" />
                                        Select File
                                    </button>
                                </div>
                            </div>
                            <div v-if="pendingDocs.length" class="space-y-2">
                                <div v-for="(doc, i) in pendingDocs" :key="i"
                                    class="flex items-center justify-between px-4 py-2.5 bg-white border border-gray-200 rounded-lg">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <DocumentArrowUpIcon class="h-4 w-4 text-gray-400 shrink-0" />
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">{{ doc.name }}</p>
                                            <p class="text-xs text-gray-500">{{ docTypeLabel(doc.type) }}<span v-if="doc.notes"> · {{ doc.notes }}</span></p>
                                        </div>
                                    </div>
                                    <button type="button" @click="removeDoc(i)" class="ml-4 text-gray-400 hover:text-red-500 transition-colors shrink-0">
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <p v-else class="text-sm text-gray-400">No documents added yet.</p>
                        </div>
                    </div>

                    <!-- Columna derecha: solo Evento/Paquete -->
                    <div class="contents lg:block">
                        <!-- Evento y Paquete -->
                        <div class="bg-white rounded-xl border border-gray-200 p-6 order-2">
                            <h3 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4">Evento y Paquete</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Evento *</label>
                                    <select v-model="form.event_id" class="input bg-white">
                                        <option value="">Seleccionar evento...</option>
                                        <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                                    </select>
                                    <p v-if="form.errors.event_id" class="err">{{ form.errors.event_id }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Paquete *</label>
                                    <select v-model="form.package_id" class="input bg-white">
                                        <option value="">Seleccionar paquete...</option>
                                        <option v-for="p in packages" :key="p.id" :value="p.id">{{ p.name }} — ${{ Number(p.price).toLocaleString() }}</option>
                                    </select>
                                    <p v-if="form.errors.package_id" class="err">{{ form.errors.package_id }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Precio Acordado ($) *</label>
                                    <input v-model="form.agreed_price" type="number" step="0.01" min="0" class="input" />
                                    <p v-if="form.errors.agreed_price" class="err">{{ form.errors.agreed_price }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Inicial / Downpayment ($) *</label>
                                    <input v-model="form.downpayment" type="number" step="0.01" min="0" class="input" />
                                    <p v-if="form.errors.downpayment" class="err">{{ form.errors.downpayment }}</p>
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Número de Cuotas *</label>
                                    <input v-model="form.installments_count" type="number" min="1" max="12" class="input" />
                                    <p v-if="form.errors.installments_count" class="err">{{ form.errors.installments_count }}</p>
                                </div>
                                <div v-if="salesReps?.length" class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Asignar a Asesor</label>
                                    <select v-model="form.sales_rep_id" class="input bg-white">
                                        <option value="">— Sin asignar (yo mismo) —</option>
                                        <option v-for="rep in salesReps" :key="rep.id" :value="rep.id">{{ rep.first_name }} {{ rep.last_name }}</option>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                                    <textarea v-model="form.notes" rows="3" class="input resize-none"></textarea>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3 mt-6">
                    <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-black text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors disabled:opacity-50">
                        {{ form.processing ? 'Registrando...' : 'Registrar Diseñador' }}
                    </button>
                    <Link href="/admin/sales/designers" class="px-6 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        Cancelar
                    </Link>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<style scoped>
@reference "tailwindcss";
.input { @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400; }
.err  { @apply text-red-500 text-xs mt-1; }
</style>
