<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    events: Array,
    advisors: Array,
    sources: Object,
    isLeader: Boolean,
});

const phoneCodes = [
    { code: '+1', flag: '🇺🇸' }, { code: '+52', flag: '🇲🇽' }, { code: '+44', flag: '🇬🇧' },
    { code: '+33', flag: '🇫🇷' }, { code: '+39', flag: '🇮🇹' }, { code: '+34', flag: '🇪🇸' },
    { code: '+49', flag: '🇩🇪' }, { code: '+55', flag: '🇧🇷' }, { code: '+57', flag: '🇨🇴' },
    { code: '+51', flag: '🇵🇪' }, { code: '+54', flag: '🇦🇷' }, { code: '+56', flag: '🇨🇱' },
    { code: '+58', flag: '🇻🇪' }, { code: '+593', flag: '🇪🇨' }, { code: '+91', flag: '🇮🇳' },
    { code: '+86', flag: '🇨🇳' }, { code: '+81', flag: '🇯🇵' }, { code: '+82', flag: '🇰🇷' },
    { code: '+234', flag: '🇳🇬' }, { code: '+27', flag: '🇿🇦' }, { code: '+971', flag: '🇦🇪' },
];
const phoneCode = ref('+1');
const phoneNumber = ref('');

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    country: '',
    company_name: '',
    retail_category: '',
    website_url: '',
    instagram: '',
    designs_ready: '',
    budget: '',
    past_shows: '',
    event_ids: [],
    preferred_contact_time: '',
    assigned_to: '',
    source: 'manual',
    notes: '',
    note_title: '',
    note_file: null,
});

const noteShowTitle = ref(false);
const noteFiles = ref([]);
const noteFileInput = ref(null);

function handleNoteFile(e) {
    for (const file of e.target.files) {
        noteFiles.value.push({ file, name: file.name });
    }
    e.target.value = '';
}

function removeNoteFile(index) {
    noteFiles.value.splice(index, 1);
}

const countryOptions = ['United States','Canada','Mexico','United Kingdom','France','Germany','Italy','Spain','Portugal','Netherlands','Belgium','Switzerland','Sweden','Norway','Denmark','Finland','Ireland','Austria','Poland','Greece','Turkey','Brazil','Argentina','Colombia','Chile','Peru','Venezuela','Ecuador','Dominican Republic','Puerto Rico','Costa Rica','Panama','Guatemala','Cuba','Japan','South Korea','China','India','Indonesia','Philippines','Thailand','Vietnam','Malaysia','Singapore','United Arab Emirates','Saudi Arabia','Israel','Lebanon','Egypt','Morocco','Nigeria','South Africa','Kenya','Ghana','Australia','New Zealand','Russia','Ukraine','Other'];
const retailCategoryOptions = ['Athleisure','Accessories','Activewear/Sportswear','Bridal','Eveningwear/Gowns','Indigenous','Kids/Youth','Lingerie','Resort/Swimwear','Streetwear','Suits','Upcycle/Organic','Other'];
const designsReadyOptions = ['Under 10', 'Under 25', 'Over 25'];
const budgetOptions = ['$5,000 to $10,000', '$10,000 to $25,000', '$25,000 to $75,000', '$75,000+'];
const pastShowsOptions = ['0', '1', '2', '3', '4', '5+'];
const contactTimeOptions = [
    '9:00 AM', '10:00 AM', '11:00 AM', '12:00 PM',
    '1:00 PM', '2:00 PM', '3:00 PM', '4:00 PM', '5:00 PM',
];

function submit() {
    form.phone = phoneNumber.value ? `${phoneCode.value} ${phoneNumber.value}` : '';
    noteFiles.value.forEach((f, i) => { form[`note_files[${i}]`] = f.file; });
    form.post('/admin/sales/leads', { forceFormData: true });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/sales/leads" class="text-gray-400 hover:text-gray-600 text-sm flex items-center gap-1">
                    <ArrowLeftIcon class="w-4 h-4" /> Leads
                </Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Create Lead</h2>
            </div>
        </template>

        <div class="max-w-3xl mx-auto">
            <form @submit.prevent="submit" class="space-y-6">

                <!-- Section 1: Personal Information -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37]">Personal Information</h3>

                    <div class="grid grid-cols-2 gap-4">
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

                    <div class="grid grid-cols-2 gap-4">
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
                                    <option v-for="pc in phoneCodes" :key="pc.code" :value="pc.code">{{ pc.flag }} {{ pc.code }}</option>
                                </select>
                                <input v-model="phoneNumber" type="tel" placeholder="926807963"
                                    class="flex-1 border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <p v-if="form.errors.phone" class="mt-1 text-red-500 text-xs">{{ form.errors.phone }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
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

                    <div class="grid grid-cols-2 gap-4">
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

                    <div class="grid grid-cols-2 gap-4">
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

                    <div class="grid grid-cols-2 gap-4">
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

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Past Shows</label>
                            <select v-model="form.past_shows"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">-- Select --</option>
                                <option v-for="opt in pastShowsOptions" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                            <p v-if="form.errors.past_shows" class="mt-1 text-red-500 text-xs">{{ form.errors.past_shows }}</p>
                        </div>
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

                    <div class="grid grid-cols-1 gap-4">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Events * <span class="text-gray-400 font-normal">(select one or more)</span></label>
                            <div class="space-y-2">
                                <label v-for="e in events" :key="e.id" class="flex items-center gap-3 p-3 border rounded-xl cursor-pointer transition-colors"
                                    :class="form.event_ids.includes(e.id) ? 'border-black bg-gray-50' : 'border-gray-200 hover:bg-gray-50'">
                                    <input type="checkbox" :value="e.id" v-model="form.event_ids" class="accent-black w-4 h-4" />
                                    <span class="text-sm font-medium text-gray-900">{{ e.name }}</span>
                                </label>
                            </div>
                            <p v-if="form.errors.event_ids" class="mt-1 text-red-500 text-xs">{{ form.errors.event_ids }}</p>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Asignacion + Nota inicial -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37]">{{ isLeader ? 'Assignment & Note' : 'Initial Note' }}</h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Source</label>
                            <select v-model="form.source"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option v-for="(label, key) in sources" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div v-if="isLeader">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Assign to</label>
                            <select v-model="form.assigned_to"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">-- Unassigned --</option>
                                <option v-for="a in advisors" :key="a.id" :value="a.id">{{ a.first_name }} {{ a.last_name }}</option>
                            </select>
                            <p v-if="form.errors.assigned_to" class="mt-1 text-red-500 text-xs">{{ form.errors.assigned_to }}</p>
                        </div>
                    </div>

                    <!-- Nota inicial con titulo y archivo -->
                    <div>
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <div v-if="noteShowTitle" class="px-4 pt-3">
                                <input v-model="form.note_title" type="text" placeholder="Title (optional)"
                                    class="w-full border-0 p-0 text-sm font-semibold text-gray-900 focus:ring-0 placeholder-gray-400 focus:outline-none" />
                            </div>
                            <textarea v-model="form.notes" rows="2" placeholder="What's this note about? (optional)"
                                class="w-full border-0 px-4 py-3 text-sm text-gray-700 focus:ring-0 focus:outline-none placeholder-gray-400 resize-none"></textarea>
                            <!-- Attached files preview -->
                            <div v-if="noteFiles.length" class="px-4 py-2 border-t border-gray-100 space-y-1">
                                <div v-for="(f, idx) in noteFiles" :key="idx" class="flex items-center justify-between bg-blue-50 rounded-lg px-3 py-1.5">
                                    <div class="flex items-center gap-2 text-xs text-blue-700">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                        <span class="truncate max-w-48">{{ f.name }}</span>
                                    </div>
                                    <button type="button" @click="removeNoteFile(idx)" class="text-xs text-red-500 hover:text-red-700">&times;</button>
                                </div>
                            </div>
                            <div class="px-4 py-2 bg-gray-50 flex items-center gap-3 border-t border-gray-100">
                                <label class="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700 cursor-pointer transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    Attach File
                                    <input type="file" ref="noteFileInput" @change="handleNoteFile" multiple class="hidden" />
                                </label>
                                <button v-if="!noteShowTitle" type="button" @click="noteShowTitle = true"
                                    class="text-xs text-gray-500 hover:text-gray-700 transition-colors">
                                    Add a Title
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex justify-between">
                    <Link href="/admin/sales/leads"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-8 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-60 transition-colors">
                        <span v-if="form.processing">Creating...</span>
                        <span v-else>Create Lead</span>
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
