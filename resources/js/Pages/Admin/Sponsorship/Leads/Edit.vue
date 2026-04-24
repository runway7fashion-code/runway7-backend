<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { PlusIcon, XMarkIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    lead: Object,
    categories: Array,
    events: Array,
    tags: Array,
    sources: Array,
    advisors: Array,
    isLider: Boolean,
});

const primaryEmail = props.lead.emails?.find(e => e.is_primary)?.email || '';
const secondaryEmails = (props.lead.emails || []).filter(e => !e.is_primary).map(e => e.email);

const form = useForm({
    company_id: props.lead.company_id,
    first_name: props.lead.first_name,
    last_name: props.lead.last_name,
    email: primaryEmail,
    secondary_emails: secondaryEmails,
    phone: props.lead.phone || '',
    charge: props.lead.charge || '',
    linkedin_url: props.lead.linkedin_url || '',
    website_url: props.lead.website_url || '',
    instagram: props.lead.instagram || '',
    category_id: props.lead.category_id,
    source: props.lead.source,
    source_detail: props.lead.source_detail || '',
    event_ids: (props.lead.events || []).map(e => e.id),
    assigned_to_user_id: props.lead.assigned_to_user_id,
    notes: props.lead.notes || '',
    tag_ids: (props.lead.tags || []).map(t => t.id),
});

// Company
const companyQuery = ref('');
const companySuggestions = ref([]);
const selectedCompany = ref(null);
const showCompanyDropdown = ref(false);
let companyTimeout;

onMounted(async () => {
    // Preload the current company via search
    if (props.lead.company_id) {
        try {
            const res = await fetch(`/admin/sponsorship/companies/search?q=${encodeURIComponent('')}`);
        } catch (e) {}
        // Best: use the lead.company already provided from controller via relationship
    }
});

// Simply reuse lead.company if loaded
if (props.lead.company) {
    selectedCompany.value = { id: props.lead.company.id, name: props.lead.company.name };
}

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
}

function addSecondaryEmail() { form.secondary_emails.push(''); }
function removeSecondaryEmail(i) { form.secondary_emails.splice(i, 1); }

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
    form.put(`/admin/sponsorship/leads/${props.lead.id}`);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center space-x-2 text-sm">
                <Link href="/admin/sponsorship/leads" class="text-gray-400 hover:text-gray-600">Leads</Link>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-medium">{{ lead.first_name }} {{ lead.last_name }}</span>
            </div>
        </template>

        <div class="max-w-3xl">
            <h3 class="text-2xl font-bold text-gray-900 mb-6">Edit Lead</h3>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Company -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h4 class="font-semibold text-gray-900 mb-4">Company *</h4>
                    <div v-if="!selectedCompany" class="space-y-2">
                        <div class="relative">
                            <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" />
                            <input v-model="companyQuery" @input="searchCompanies" @focus="showCompanyDropdown = true"
                                type="text" placeholder="Search existing company..." class="input pl-10" />
                            <ul v-if="showCompanyDropdown && companySuggestions.length"
                                class="absolute z-10 bg-white border border-gray-200 rounded-lg shadow mt-1 w-full max-h-60 overflow-auto">
                                <li v-for="c in companySuggestions" :key="c.id" @click="pickCompany(c)"
                                    class="px-4 py-2 text-sm hover:bg-gray-50 cursor-pointer">{{ c.name }}</li>
                            </ul>
                        </div>
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

                <!-- Contact info -->
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
                            <input v-model="form.secondary_emails[i]" type="email" class="input" />
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
                            <input v-model="form.phone" type="tel" class="input" />
                        </div>
                        <div>
                            <label class="label">Charge</label>
                            <input v-model="form.charge" type="text" class="input" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">LinkedIn URL</label>
                            <input v-model="form.linkedin_url" type="url" class="input" />
                        </div>
                        <div>
                            <label class="label">Website</label>
                            <input v-model="form.website_url" type="url" class="input" />
                        </div>
                    </div>

                    <div>
                        <label class="label">Instagram</label>
                        <input v-model="form.instagram" type="text" class="input" />
                    </div>
                </div>

                <!-- Classification -->
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
                        <input v-model="form.source_detail" type="text" class="input" />
                    </div>

                    <div>
                        <label class="label">Events *</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <label v-for="e in events" :key="e.id"
                                class="flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50"
                                :class="form.event_ids.includes(e.id) ? 'bg-yellow-50 border-[#D4AF37]' : ''">
                                <input type="checkbox" :checked="form.event_ids.includes(e.id)" @change="toggleEvent(e.id)" class="rounded">
                                <span class="text-sm">{{ e.name }}</span>
                            </label>
                        </div>
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

                <div v-if="isLider" class="bg-white rounded-xl border border-gray-200 p-6">
                    <label class="label">Assigned to</label>
                    <select v-model="form.assigned_to_user_id" class="input bg-white">
                        <option :value="null">— Unassigned</option>
                        <option v-for="a in advisors" :key="a.id" :value="a.id">
                            {{ a.first_name }} {{ a.last_name }} {{ a.sponsorship_type === 'lider' ? '(Leader)' : '(Advisor)' }}
                        </option>
                    </select>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <label class="label">Notes</label>
                    <textarea v-model="form.notes" rows="4" class="input resize-none"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <Link :href="`/admin/sponsorship/leads/${lead.id}`" class="px-4 py-2.5 text-sm text-gray-600 hover:text-gray-800 font-medium">Cancel</Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-6 py-2.5 text-sm font-semibold text-white bg-black rounded-lg hover:bg-gray-800 disabled:opacity-60">
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<style scoped>
@reference "tailwindcss";
.input { @apply w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400; }
.label { @apply block text-sm font-medium text-gray-700 mb-1.5; }
.err { @apply mt-1 text-red-500 text-xs; }
</style>
