<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { PlusIcon, XMarkIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    categories: Array,
    events: Array,
    tags: Array,
    sources: Array,
    countries: Array,
    advisors: Array,
    isLider: Boolean,
});

const defaultPhoneCode = props.countries?.find(c => c.code === 'US')?.phone || props.countries?.[0]?.phone || '+1';

const form = useForm({
    company_id: null,
    first_name: '',
    last_name: '',
    email: '',
    secondary_emails: [],
    phone_code: defaultPhoneCode,
    phone: '',
    charge: '',
    linkedin_url: '',
    website_url: '',
    instagram: '',
    category_id: null,
    source: 'manual',
    source_detail: '',
    event_ids: [],
    assigned_to_user_id: null,
    notes: '',
    tag_ids: [],
});

// Company autosuggest
const companyQuery = ref('');
const companySuggestions = ref([]);
const selectedCompany = ref(null);
const showCompanyDropdown = ref(false);
let companyTimeout;

function searchCompanies() {
    clearTimeout(companyTimeout);
    companyTimeout = setTimeout(async () => {
        if (!companyQuery.value.trim()) {
            companySuggestions.value = [];
            return;
        }
        try {
            const res = await fetch(`/admin/sponsorship/companies/search?q=${encodeURIComponent(companyQuery.value)}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            companySuggestions.value = data.companies || [];
            showCompanyDropdown.value = true;
        } catch (e) {}
    }, 250);
}

function pickCompany(c) {
    selectedCompany.value = c;
    form.company_id = c.id;
    companyQuery.value = c.name;
    showCompanyDropdown.value = false;
}

function clearCompany() {
    selectedCompany.value = null;
    form.company_id = null;
    companyQuery.value = '';
    companySuggestions.value = [];
}

// Modal crear company
const showCreateCompany = ref(false);
const newCompanyName = ref('');
const creatingCompany = ref(false);
const createCompanyError = ref('');

async function createCompany() {
    if (!newCompanyName.value.trim()) return;
    creatingCompany.value = true;
    createCompanyError.value = '';
    try {
        const res = await fetch('/admin/sponsorship/companies', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
            },
            body: JSON.stringify({ name: newCompanyName.value.trim() }),
        });
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            createCompanyError.value = data?.errors?.name?.[0] || 'No se pudo crear.';
            return;
        }
        const data = await res.json();
        pickCompany(data.company);
        showCreateCompany.value = false;
        newCompanyName.value = '';
    } finally {
        creatingCompany.value = false;
    }
}

// Emails secundarios
function addSecondaryEmail() {
    form.secondary_emails.push('');
}
function removeSecondaryEmail(i) {
    form.secondary_emails.splice(i, 1);
}

// Events / Tags toggle
function toggleEvent(id) {
    const i = form.event_ids.indexOf(id);
    if (i >= 0) form.event_ids.splice(i, 1);
    else form.event_ids.push(id);
}
function toggleTag(id) {
    const i = form.tag_ids.indexOf(id);
    if (i >= 0) form.tag_ids.splice(i, 1);
    else form.tag_ids.push(id);
}

const showSourceDetail = computed(() => form.source === 'other');

function submit() {
    // Transform the phone field to include country code (e.g. "+1 555 1234")
    form.transform(data => {
        const phone = (data.phone || '').trim();
        const code = (data.phone_code || '').trim();
        const full = phone ? `${code} ${phone}`.trim() : null;
        // eslint-disable-next-line no-unused-vars
        const { phone_code, ...rest } = data;
        return { ...rest, phone: full };
    }).post('/admin/sponsorship/leads');
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center space-x-2 text-sm">
                <Link href="/admin/sponsorship/leads" class="text-gray-400 hover:text-gray-600">Leads</Link>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-medium">New Lead</span>
            </div>
        </template>

        <div class="max-w-3xl">
            <h3 class="text-2xl font-bold text-gray-900 mb-6">New Lead</h3>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Company -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h4 class="font-semibold text-gray-900 mb-4">Company *</h4>
                    <div v-if="!selectedCompany" class="space-y-2">
                        <div class="relative">
                            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" />
                            <input v-model="companyQuery" @input="searchCompanies" @focus="showCompanyDropdown = true"
                                type="text" placeholder="Search existing company..."
                                class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400" />
                            <ul v-if="showCompanyDropdown && companySuggestions.length"
                                class="absolute z-10 bg-white border border-gray-200 rounded-lg shadow mt-1 w-full max-h-60 overflow-auto">
                                <li v-for="c in companySuggestions" :key="c.id"
                                    @click="pickCompany(c)"
                                    class="px-4 py-2 text-sm hover:bg-gray-50 cursor-pointer">{{ c.name }}</li>
                            </ul>
                        </div>
                        <button type="button" @click="showCreateCompany = true"
                            class="text-sm text-blue-600 hover:underline flex items-center gap-1">
                            <PlusIcon class="w-4 h-4" /> Create new company
                        </button>
                        <p v-if="form.errors.company_id" class="err">{{ form.errors.company_id }}</p>
                    </div>
                    <div v-else class="flex items-center justify-between bg-yellow-50 border border-[#D4AF37] rounded-lg px-4 py-3">
                        <div>
                            <p class="text-xs text-gray-500">Selected company</p>
                            <p class="font-semibold text-gray-900">{{ selectedCompany.name }}</p>
                        </div>
                        <button type="button" @click="clearCompany" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <!-- Basic info -->
                <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                    <h4 class="font-semibold text-gray-900">Contact info</h4>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">First name *</label>
                            <input v-model="form.first_name" type="text" class="input" />
                            <p v-if="form.errors.first_name" class="err">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="label">Last name *</label>
                            <input v-model="form.last_name" type="text" class="input" />
                            <p v-if="form.errors.last_name" class="err">{{ form.errors.last_name }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="label">Primary email *</label>
                        <input v-model="form.email" type="email" class="input" />
                        <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
                        <p v-if="form.errors.emails" class="err">{{ form.errors.emails }}</p>
                    </div>

                    <div class="space-y-2">
                        <label class="label">Secondary emails</label>
                        <div v-for="(_, i) in form.secondary_emails" :key="i" class="flex gap-2">
                            <input v-model="form.secondary_emails[i]" type="email" class="input" placeholder="email@example.com" />
                            <button type="button" @click="removeSecondaryEmail(i)" class="p-2 text-red-500 hover:bg-red-50 rounded-lg">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>
                        <button type="button" @click="addSecondaryEmail"
                            class="text-sm text-blue-600 hover:underline flex items-center gap-1">
                            <PlusIcon class="w-4 h-4" /> Add secondary email
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Phone</label>
                            <div class="flex gap-2">
                                <select v-model="form.phone_code"
                                    class="w-32 shrink-0 border border-gray-300 rounded-lg px-2 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400">
                                    <option v-for="c in countries" :key="c.code" :value="c.phone">{{ c.flag }} {{ c.phone }}</option>
                                </select>
                                <input v-model="form.phone" type="tel" placeholder="(555) 123-4567" class="input flex-1" />
                            </div>
                        </div>
                        <div>
                            <label class="label">Charge</label>
                            <input v-model="form.charge" type="text" class="input" placeholder="CEO, Marketing Manager..." />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">LinkedIn URL</label>
                            <input v-model="form.linkedin_url" type="url" class="input" placeholder="https://linkedin.com/in/..." />
                        </div>
                        <div>
                            <label class="label">Website</label>
                            <input v-model="form.website_url" type="url" class="input" placeholder="https://" />
                        </div>
                    </div>

                    <div>
                        <label class="label">Instagram</label>
                        <input v-model="form.instagram" type="text" class="input" placeholder="@handle" />
                    </div>
                </div>

                <!-- Categoria / Source / Events / Tags -->
                <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                    <h4 class="font-semibold text-gray-900">Classification</h4>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Category</label>
                            <select v-model="form.category_id" class="input bg-white">
                                <option :value="null">—</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Source *</label>
                            <select v-model="form.source" class="input bg-white">
                                <option v-for="s in sources" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="showSourceDetail">
                        <label class="label">Specify source *</label>
                        <input v-model="form.source_detail" type="text" class="input" placeholder="WhatsApp, Event X, ..." />
                    </div>

                    <div>
                        <label class="label">Events * <span class="text-xs text-gray-400">(select at least one)</span></label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <label v-for="e in events" :key="e.id"
                                class="flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50"
                                :class="form.event_ids.includes(e.id) ? 'bg-yellow-50 border-[#D4AF37]' : ''">
                                <input type="checkbox" :checked="form.event_ids.includes(e.id)" @change="toggleEvent(e.id)" class="rounded">
                                <span class="text-sm">{{ e.name }}</span>
                            </label>
                        </div>
                        <p v-if="form.errors.event_ids" class="err">{{ form.errors.event_ids }}</p>
                    </div>

                    <div v-if="tags.length">
                        <label class="label">Tags</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="t in tags" :key="t.id" type="button" @click="toggleTag(t.id)"
                                class="px-2.5 py-1 text-xs rounded-full border transition-all"
                                :style="form.tag_ids.includes(t.id) ? { backgroundColor: t.color, color: 'white', borderColor: t.color } : {}"
                                :class="form.tag_ids.includes(t.id) ? '' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'">
                                {{ t.name }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Assignment (lider) -->
                <div v-if="isLider" class="bg-white rounded-xl border border-gray-200 p-6">
                    <label class="label">Lead Owner</label>
                    <select v-model="form.assigned_to_user_id" class="input bg-white">
                        <option :value="null">— (Me by default)</option>
                        <option v-for="a in advisors" :key="a.id" :value="a.id">
                            {{ a.first_name }} {{ a.last_name }} {{ a.sponsorship_type === 'lider' ? '(Leader)' : '(Advisor)' }}
                        </option>
                    </select>
                </div>

                <!-- Notes -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <label class="label">Notes</label>
                    <textarea v-model="form.notes" rows="4" class="input resize-none"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <Link href="/admin/sponsorship/leads" class="px-4 py-2.5 text-sm text-gray-600 hover:text-gray-800 font-medium">Cancel</Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-6 py-2.5 text-sm font-semibold text-white bg-black rounded-lg hover:bg-gray-800 disabled:opacity-60">
                        {{ form.processing ? 'Creating...' : 'Create Lead' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Create company modal -->
        <Teleport to="body">
            <div v-if="showCreateCompany" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showCreateCompany = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">New Company</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="label">Name *</label>
                            <input v-model="newCompanyName" type="text" class="input" autofocus @keyup.enter="createCompany" />
                            <p v-if="createCompanyError" class="err">{{ createCompanyError }}</p>
                            <p class="text-xs text-gray-500 mt-1">Only the name now. Other details can be completed later.</p>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="showCreateCompany = false"
                                class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancel</button>
                            <button type="button" @click="createCompany" :disabled="creatingCompany || !newCompanyName.trim()"
                                class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40">
                                Create
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<style scoped>
@reference "tailwindcss";
.input { @apply w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400; }
.label { @apply block text-sm font-medium text-gray-700 mb-1.5; }
.err { @apply mt-1 text-red-500 text-xs; }
</style>
