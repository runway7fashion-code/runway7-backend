<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    lead: Object,
    events: Array,
    advisors: Array,
    sources: Object,
    opportunityStatuses: Object,
    categories: Array,
    phoneCodes: Array,
});

// Parse existing phone into code + number
function parsePhone(phone) {
    if (!phone) return { code: '+1', number: '' };
    // Try matching with space first
    const spaceMatch = phone.match(/^(\+\d+)\s+(.+)$/);
    if (spaceMatch) return { code: spaceMatch[1], number: spaceMatch[2] };
    // Match against known phone codes (longest first to match +852 before +8)
    const codes = props.phoneCodes.map(c => c.phone).sort((a, b) => b.length - a.length);
    for (const code of codes) {
        if (phone.startsWith(code)) {
            return { code, number: phone.slice(code.length) };
        }
    }
    return { code: '+1', number: phone.replace(/^\+\d+/, '') };
}
const parsed = parsePhone(props.lead.phone);
const phoneCode = ref(parsed.code);
const phoneNumber = ref(parsed.number);

const fromParam = new URLSearchParams(window.location.search).get('from');
const cancelUrl = fromParam === 'show' ? `/admin/sales/leads/${props.lead.id}` : '/admin/sales/leads';

const form = useForm({
    first_name: props.lead.first_name || '',
    last_name: props.lead.last_name || '',
    email: props.lead.email || '',
    phone: props.lead.phone || '',
    country: props.lead.country || '',
    company_name: props.lead.company_name || '',
    retail_category: props.lead.retail_category || '',
    website_url: props.lead.website_url || '',
    instagram: props.lead.instagram || '',
    designs_ready: props.lead.designs_ready || '',
    budget: props.lead.budget || '',
    past_shows: props.lead.past_shows || '',
    preferred_contact_time: props.lead.preferred_contact_time || '',
    source: props.lead.source || 'manual',
    event_ids: (props.lead.events || []).map(e => e.id),
    event_statuses: Object.fromEntries((props.lead.events || []).map(e => [e.id, e.pivot?.status || 'new'])),
    notes: props.lead.notes || '',
});

const countryOptions = props.phoneCodes.map(c => c.name);
const retailCategoryOptions = props.categories.map(c => c.name);
const designsReadyOptions = ['Under 10', 'Under 25', 'Over 25'];
const budgetOptions = ['$5,000 to $10,000', '$10,000 to $25,000', '$25,000 to $75,000', '$75,000+'];
const pastShowsOptions = ['0', '1', '2', '3', '4', '5+'];
const contactTimeOptions = [
    '9:00 AM', '10:00 AM', '11:00 AM', '12:00 PM',
    '1:00 PM', '2:00 PM', '3:00 PM', '4:00 PM', '5:00 PM',
];

// Default event status to 'new' when a new event is checked
watch(() => form.event_ids, (ids) => {
    ids.forEach(id => {
        if (!form.event_statuses[id]) {
            form.event_statuses[id] = 'new';
        }
    });
}, { deep: true });

function submit() {
    form.phone = phoneNumber.value ? `${phoneCode.value} ${phoneNumber.value}` : '';
    form.put(`/admin/sales/leads/${props.lead.id}`);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="cancelUrl" class="text-gray-400 hover:text-gray-600 text-sm flex items-center gap-1">
                    <ArrowLeftIcon class="w-4 h-4" /> Leads
                </Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Edit Lead</h2>
            </div>
        </template>

        <div class="max-w-3xl mx-auto">
            <form @submit.prevent="submit" class="space-y-6">

                <!-- Section 1: Personal Information -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37]">Personal Information</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                            <input v-model="form.first_name" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.first_name" class="mt-1 text-red-500 text-xs">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                            <input v-model="form.last_name" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.last_name" class="mt-1 text-red-500 text-xs">{{ form.errors.last_name }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input v-model="form.email" type="email"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.email" class="mt-1 text-red-500 text-xs">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <div class="flex gap-2">
                                <select v-model="phoneCode" class="w-28 border border-gray-300 rounded-lg px-2 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white flex-shrink-0">
                                    <option v-for="pc in phoneCodes" :key="pc.code" :value="pc.phone">{{ pc.flag }} {{ pc.phone }}</option>
                                </select>
                                <input v-model="phoneNumber" type="tel" placeholder="926807963"
                                    class="flex-1 border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <p v-if="form.errors.phone" class="mt-1 text-red-500 text-xs">{{ form.errors.phone }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                            <select v-model="form.country"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">-- Select --</option>
                                <option v-for="c in countryOptions" :key="c" :value="c">{{ c }}</option>
                            </select>
                            <p v-if="form.errors.country" class="mt-1 text-red-500 text-xs">{{ form.errors.country }}</p>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Business Information -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37]">Business Information</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                            <input v-model="form.company_name" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.company_name" class="mt-1 text-red-500 text-xs">{{ form.errors.company_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Retail Category</label>
                            <select v-model="form.retail_category"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">-- Select --</option>
                                <option v-for="c in retailCategoryOptions" :key="c" :value="c">{{ c }}</option>
                            </select>
                            <p v-if="form.errors.retail_category" class="mt-1 text-red-500 text-xs">{{ form.errors.retail_category }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Website URL</label>
                            <input v-model="form.website_url" type="url" placeholder="https://..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.website_url" class="mt-1 text-red-500 text-xs">{{ form.errors.website_url }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Instagram</label>
                            <input v-model="form.instagram" type="text" placeholder="@username"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.instagram" class="mt-1 text-red-500 text-xs">{{ form.errors.instagram }}</p>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Detalles -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37]">Details</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Designs Ready</label>
                            <select v-model="form.designs_ready"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">-- Select --</option>
                                <option v-for="opt in designsReadyOptions" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                            <p v-if="form.errors.designs_ready" class="mt-1 text-red-500 text-xs">{{ form.errors.designs_ready }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Budget</label>
                            <select v-model="form.budget"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">-- Select --</option>
                                <option v-for="opt in budgetOptions" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                            <p v-if="form.errors.budget" class="mt-1 text-red-500 text-xs">{{ form.errors.budget }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Past Shows</label>
                            <select v-model="form.past_shows"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">-- Select --</option>
                                <option v-for="opt in pastShowsOptions" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                            <p v-if="form.errors.past_shows" class="mt-1 text-red-500 text-xs">{{ form.errors.past_shows }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Contact Time</label>
                            <select v-model="form.preferred_contact_time"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">-- Select --</option>
                                <option v-for="t in contactTimeOptions" :key="t" :value="t">{{ t }}</option>
                            </select>
                            <p v-if="form.errors.preferred_contact_time" class="mt-1 text-red-500 text-xs">{{ form.errors.preferred_contact_time }}</p>
                        </div>
                    </div>
                </div>

                <!-- Eventos -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37]">Events</h3>
                    <div class="space-y-2">
                        <label v-for="e in events" :key="e.id"
                            class="flex items-center justify-between p-3 border rounded-xl transition-colors"
                            :class="form.event_ids.includes(e.id) ? 'border-black bg-gray-50' : 'border-gray-200 hover:bg-gray-50'">
                            <div class="flex items-center gap-3">
                                <input type="checkbox" :value="e.id" v-model="form.event_ids" class="accent-black w-4 h-4 cursor-pointer" />
                                <span class="text-sm font-medium text-gray-900">{{ e.name }}</span>
                            </div>
                            <select v-if="form.event_ids.includes(e.id)"
                                v-model="form.event_statuses[e.id]"
                                @click.stop
                                :disabled="form.event_statuses[e.id] === 'converted'"
                                :class="form.event_statuses[e.id] === 'converted' ? 'border border-gray-200 rounded-lg px-2 py-1 text-xs bg-gray-100 text-green-700 cursor-not-allowed' : 'border border-gray-300 rounded-lg px-2 py-1 text-xs focus:ring-1 focus:ring-black'">
                                <option v-for="(info, key) in opportunityStatuses" :key="key" :value="key">{{ info.label }}</option>
                            </select>
                        </label>
                    </div>
                    <p v-if="form.errors.event_ids" class="text-red-500 text-xs">{{ form.errors.event_ids }}</p>
                </div>

                <!-- Source -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37]">Source</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Where does this lead come from?</label>
                        <select v-model="form.source"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                            <option v-for="(label, key) in sources" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex justify-between">
                    <Link :href="cancelUrl"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-8 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-60 transition-colors">
                        <span v-if="form.processing">Saving...</span>
                        <span v-else>Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
