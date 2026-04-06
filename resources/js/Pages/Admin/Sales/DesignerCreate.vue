<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import { DocumentArrowUpIcon, TrashIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import axios from 'axios';

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

// Lead search autocomplete
const leadSearch = ref('');
const leadResults = ref([]);
const showLeadDropdown = ref(false);
const selectedLead = ref(null);
let leadSearchTimeout = null;

watch(leadSearch, (val) => {
    clearTimeout(leadSearchTimeout);
    if (!val || val.length < 2) { leadResults.value = []; showLeadDropdown.value = false; return; }
    leadSearchTimeout = setTimeout(async () => {
        try {
            const { data } = await axios.get('/admin/sales/leads/search', { params: { q: val } });
            leadResults.value = data;
            showLeadDropdown.value = data.length > 0;
        } catch(e) { leadResults.value = []; }
    }, 300);
});

function selectLead(lead) {
    selectedLead.value = lead;
    leadSearch.value = '';
    showLeadDropdown.value = false;
    form.lead_id = lead.id;
    form.first_name = lead.first_name || '';
    form.last_name = lead.last_name || '';
    form.email = lead.email || '';
    form.brand_name = lead.company_name || '';
    if (lead.country) form.country = lead.country;
    if (lead.phone) {
        const match = lead.phone.match(/^(\+\d+)\s*(.*)$/);
        if (match) { phoneCode.value = match[1]; phoneNumber.value = match[2]; }
        else phoneNumber.value = lead.phone;
    }
    // Event from URL param or lead's first event
    const urlEventId = new URLSearchParams(window.location.search).get('event_id');
    if (urlEventId) {
        form.event_id = urlEventId;
    } else if (lead.events?.length) {
        form.event_id = lead.events[0].id;
    }
    // Sales rep from lead's assigned_to
    if (lead.assigned_to) {
        form.sales_rep_id = typeof lead.assigned_to === 'object' ? lead.assigned_to.id : lead.assigned_to;
    }
}

function clearLead() {
    selectedLead.value = null;
    form.lead_id = ''; form.first_name = ''; form.last_name = ''; form.email = ''; form.brand_name = '';
    form.country = ''; form.event_id = ''; form.sales_rep_id = '';
    phoneNumber.value = ''; phoneCode.value = '+1';
}

// Check for lead_id in URL params
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const leadId = params.get('lead_id');
    if (leadId) {
        axios.get('/admin/sales/leads/search', { params: { id: leadId } }).then(({ data }) => {
            if (data.length) selectLead(data[0]);
        });
    }
});

const form = useForm({
    lead_id: '',
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
    looks: '',
    assistants: '',
    model_casting_enabled: true,
    media_package: false,
    custom_background: false,
    courtesy_tickets: false,
    notes: '',
    sales_rep_id: '',
    documents: [],
});

const selectedPackage = computed(() => props.packages?.find(p => p.id == form.package_id) ?? null);

watch(() => form.package_id, () => {
    if (selectedPackage.value) {
        form.looks = selectedPackage.value.default_looks;
        form.assistants = selectedPackage.value.default_assistants;
        if (!form.agreed_price) form.agreed_price = selectedPackage.value.price;
    }
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

const showConfirmModal = ref(false);

function submit() {
    form.phone = phoneNumber.value ? `${phoneCode.value}${phoneNumber.value}` : '';
    form.documents = pendingDocs.value.map(d => ({ file: d.file, type: d.type, notes: d.notes }));
    showConfirmModal.value = true;
}

function confirmSubmit() {
    showConfirmModal.value = false;
    form.post('/admin/sales/designers', {
        preserveScroll: true,
        forceFormData: true,
    });
}

const selectedEvent = computed(() => props.events?.find(e => e.id == form.event_id) ?? null);
const selectedRep = computed(() => props.salesReps?.find(r => r.id == form.sales_rep_id) ?? null);
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link href="/admin/sales/designers" class="text-gray-400 hover:text-gray-600 text-sm">&larr; Registrations</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Register Designer</h2>
            </div>
        </template>

        <div>
            <!-- Lead autocomplete -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
                <div class="flex items-center gap-3">
                    <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 flex-shrink-0" />
                    <div class="flex-1 relative">
                        <input v-if="!selectedLead" v-model="leadSearch" type="text" placeholder="Search existing prospect to autocomplete..." class="w-full border-0 p-0 text-sm focus:ring-0 placeholder-gray-400" />
                        <div v-else class="flex items-center gap-2">
                            <span class="text-sm font-medium">{{ selectedLead.first_name }} {{ selectedLead.last_name }}</span>
                            <span class="text-xs text-gray-500">{{ selectedLead.company_name }} — {{ selectedLead.email }}</span>
                            <button type="button" @click="clearLead" class="text-xs text-red-500 hover:text-red-700 ml-2">Remove</button>
                        </div>
                        <div v-if="showLeadDropdown" class="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-30 max-h-48 overflow-y-auto">
                            <button v-for="l in leadResults" :key="l.id" type="button" @click="selectLead(l)" class="w-full text-left px-3 py-2 hover:bg-gray-50 flex justify-between items-center">
                                <div>
                                    <span class="text-sm font-medium">{{ l.first_name }} {{ l.last_name }}</span>
                                    <span class="text-xs text-gray-500 ml-2">{{ l.company_name }}</span>
                                </div>
                                <span class="text-xs text-gray-400">{{ l.email }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit">
                <div class="flex flex-col lg:grid lg:grid-cols-2 gap-6 lg:items-start">
                    <!-- Columna izquierda: Info del Diseñador + Documents (lg) -->
                    <div class="contents lg:block lg:space-y-6">
                    <div class="bg-white rounded-xl border border-gray-200 p-6 order-1">
                        <h3 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4">Designer Information</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                                <input v-model="form.first_name" type="text" :disabled="!!selectedLead" :class="selectedLead ? 'input bg-gray-100 cursor-not-allowed' : 'input'" />
                                <p v-if="form.errors.first_name" class="err">{{ form.errors.first_name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                                <input v-model="form.last_name" type="text" :disabled="!!selectedLead" :class="selectedLead ? 'input bg-gray-100 cursor-not-allowed' : 'input'" />
                                <p v-if="form.errors.last_name" class="err">{{ form.errors.last_name }}</p>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                <input v-model="form.email" type="email" :disabled="!!selectedLead" :class="selectedLead ? 'input bg-gray-100 cursor-not-allowed' : 'input'" />
                                <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <div class="flex gap-2">
                                    <select v-model="phoneCode" :disabled="!!selectedLead" :class="selectedLead ? 'w-28 border border-gray-300 rounded-lg px-2 py-2 text-sm bg-gray-100 cursor-not-allowed' : 'w-28 border border-gray-300 rounded-lg px-2 py-2 text-sm focus:ring-2 focus:ring-yellow-400'">
                                        <option v-for="pc in phoneCodes" :key="pc.code" :value="pc.code">{{ pc.flag }} {{ pc.code }}</option>
                                    </select>
                                    <input v-model="phoneNumber" type="text" placeholder="Number..." :disabled="!!selectedLead" :class="selectedLead ? 'input flex-1 bg-gray-100 cursor-not-allowed' : 'input flex-1'" />
                                </div>
                                <p v-if="form.errors.phone" class="err">{{ form.errors.phone }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Brand *</label>
                                <input v-model="form.brand_name" type="text" :disabled="!!selectedLead" :class="selectedLead ? 'input bg-gray-100 cursor-not-allowed' : 'input'" />
                                <p v-if="form.errors.brand_name" class="err">{{ form.errors.brand_name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Country *</label>
                                <select v-model="form.country" :disabled="!!selectedLead" :class="selectedLead ? 'input bg-gray-100 cursor-not-allowed' : 'input bg-white'">
                                    <option value="">Select...</option>
                                    <option v-for="c in countries" :key="c" :value="c">{{ c }}</option>
                                </select>
                                <p v-if="form.errors.country" class="err">{{ form.errors.country }}</p>
                            </div>
                        </div>
                    </div>

                        <!-- Documents (columna izquierda, fila 2) -->
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
                        <!-- Event & Package -->
                        <div class="bg-white rounded-xl border border-gray-200 p-6 order-2">
                            <h3 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4">Event & Package</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Event *</label>
                                    <select v-model="form.event_id" :disabled="!!selectedLead && !!form.event_id" :class="selectedLead && form.event_id ? 'input bg-gray-100 cursor-not-allowed' : 'input bg-white'">
                                        <option value="">Select event...</option>
                                        <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                                    </select>
                                    <p v-if="form.errors.event_id" class="err">{{ form.errors.event_id }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Package *</label>
                                    <select v-model="form.package_id" class="input bg-white">
                                        <option value="">Select package...</option>
                                        <option v-for="p in packages" :key="p.id" :value="p.id">{{ p.name }} — ${{ Number(p.price).toLocaleString() }}</option>
                                    </select>
                                    <p v-if="form.errors.package_id" class="err">{{ form.errors.package_id }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Negotiated Looks *</label>
                                    <input v-model="form.looks" type="number" min="1" max="100" class="input" placeholder="Ej. 10" />
                                    <p v-if="form.errors.looks" class="err">{{ form.errors.looks }}</p>
                                    <p v-if="selectedPackage" class="text-xs text-gray-400 mt-1">Package default: {{ selectedPackage.default_looks }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Negotiated Assistants *</label>
                                    <input v-model="form.assistants" type="number" min="0" max="20" class="input" placeholder="Ej. 2" />
                                    <p v-if="form.errors.assistants" class="err">{{ form.errors.assistants }}</p>
                                    <p v-if="selectedPackage" class="text-xs text-gray-400 mt-1">Package default: {{ selectedPackage.default_assistants }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Agreed Price ($) *</label>
                                    <input v-model="form.agreed_price" type="number" step="0.01" min="0" class="input" />
                                    <p v-if="form.errors.agreed_price" class="err">{{ form.errors.agreed_price }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Downpayment ($) *</label>
                                    <input v-model="form.downpayment" type="number" step="0.01" min="0" class="input" />
                                    <p v-if="form.errors.downpayment" class="err">{{ form.errors.downpayment }}</p>
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Number of Installments *</label>
                                    <input v-model="form.installments_count" type="number" min="1" class="input" />
                                    <p v-if="form.errors.installments_count" class="err">{{ form.errors.installments_count }}</p>
                                </div>
                                <div v-if="salesReps?.length" class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Assign to Advisor</label>
                                    <select v-model="form.sales_rep_id" class="input bg-white">
                                        <option value="">— Unassigned (myself) —</option>
                                        <option v-for="rep in salesReps" :key="rep.id" :value="rep.id">{{ rep.first_name }} {{ rep.last_name }}</option>
                                    </select>
                                </div>
                                <div class="col-span-2 grid grid-cols-2 gap-x-6 gap-y-2 bg-gray-50 rounded-xl p-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input v-model="form.model_casting_enabled" type="checkbox"
                                            class="rounded border-gray-300 text-black focus:ring-black/20 w-4 h-4" />
                                        <span class="text-sm text-gray-700">Model Casting</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input v-model="form.media_package" type="checkbox"
                                            class="rounded border-gray-300 text-black focus:ring-black/20 w-4 h-4" />
                                        <span class="text-sm text-gray-700">Media Package</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input v-model="form.custom_background" type="checkbox"
                                            class="rounded border-gray-300 text-black focus:ring-black/20 w-4 h-4" />
                                        <span class="text-sm text-gray-700">Custom Background</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input v-model="form.courtesy_tickets" type="checkbox"
                                            class="rounded border-gray-300 text-black focus:ring-black/20 w-4 h-4" />
                                        <span class="text-sm text-gray-700">Courtesy Tickets</span>
                                    </label>
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                    <textarea v-model="form.notes" rows="3" class="input resize-none"></textarea>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3 mt-6">
                    <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-black text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors disabled:opacity-50">
                        {{ form.processing ? 'Registering...' : 'Register Designer' }}
                    </button>
                    <Link href="/admin/sales/designers" class="px-6 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
        <!-- Confirmation Modal -->
        <Teleport to="body">
            <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showConfirmModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
                    <div class="px-6 py-5 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900">Confirm Registration</h3>
                        <p class="text-sm text-gray-500 mt-1">Please review the details before registering this designer. This action cannot be undone.</p>
                    </div>
                    <div class="px-6 py-4 space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Designer</span>
                            <span class="font-medium text-gray-900">{{ form.first_name }} {{ form.last_name }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Email</span>
                            <span class="font-medium text-gray-900">{{ form.email }}</span>
                        </div>
                        <div v-if="form.brand_name" class="flex justify-between text-sm">
                            <span class="text-gray-500">Brand</span>
                            <span class="font-medium text-gray-900">{{ form.brand_name }}</span>
                        </div>
                        <div v-if="selectedEvent" class="flex justify-between text-sm">
                            <span class="text-gray-500">Event</span>
                            <span class="font-medium text-gray-900">{{ selectedEvent.name }}</span>
                        </div>
                        <div v-if="selectedPackage" class="flex justify-between text-sm">
                            <span class="text-gray-500">Package</span>
                            <span class="font-medium text-gray-900">{{ selectedPackage.name }}</span>
                        </div>
                        <div v-if="form.agreed_price" class="flex justify-between text-sm">
                            <span class="text-gray-500">Price</span>
                            <span class="font-bold text-gray-900">${{ Number(form.agreed_price).toLocaleString() }}</span>
                        </div>
                        <div v-if="form.downpayment" class="flex justify-between text-sm">
                            <span class="text-gray-500">Down Payment</span>
                            <span class="font-medium text-emerald-600">${{ Number(form.downpayment).toLocaleString() }}</span>
                        </div>
                        <div v-if="selectedLead" class="flex justify-between text-sm">
                            <span class="text-gray-500">From Lead</span>
                            <span class="font-medium text-blue-600">{{ selectedLead.first_name }} {{ selectedLead.last_name }} #{{ selectedLead.id }}</span>
                        </div>
                        <div v-if="selectedRep" class="flex justify-between text-sm">
                            <span class="text-gray-500">Sales Rep</span>
                            <span class="font-medium text-gray-900">{{ selectedRep.first_name }} {{ selectedRep.last_name }}</span>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
                        <button @click="showConfirmModal = false" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            Go Back
                        </button>
                        <button @click="confirmSubmit" :disabled="form.processing" class="flex-1 px-4 py-2.5 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors disabled:opacity-50">
                            {{ form.processing ? 'Registering...' : 'Confirm & Register' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<style scoped>
@reference "tailwindcss";
.input { @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400; }
.err  { @apply text-red-500 text-xs mt-1; }
</style>
