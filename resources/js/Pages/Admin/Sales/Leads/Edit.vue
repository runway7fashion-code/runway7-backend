<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    lead: Object,
    events: Array,
    advisors: Array,
    sources: Object,
    opportunityStatuses: Object,
});

const phoneCodes = [
    { code: '+1', flag: '🇺🇸', label: 'US/CA' }, { code: '+52', flag: '🇲🇽', label: 'MX' },
    { code: '+44', flag: '🇬🇧', label: 'GB' }, { code: '+33', flag: '🇫🇷', label: 'FR' },
    { code: '+39', flag: '🇮🇹', label: 'IT' }, { code: '+34', flag: '🇪🇸', label: 'ES' },
    { code: '+49', flag: '🇩🇪', label: 'DE' }, { code: '+55', flag: '🇧🇷', label: 'BR' },
    { code: '+57', flag: '🇨🇴', label: 'CO' }, { code: '+51', flag: '🇵🇪', label: 'PE' },
    { code: '+54', flag: '🇦🇷', label: 'AR' }, { code: '+56', flag: '🇨🇱', label: 'CL' },
    { code: '+58', flag: '🇻🇪', label: 'VE' }, { code: '+593', flag: '🇪🇨', label: 'EC' },
    { code: '+503', flag: '🇸🇻', label: 'SV' }, { code: '+502', flag: '🇬🇹', label: 'GT' },
    { code: '+504', flag: '🇭🇳', label: 'HN' }, { code: '+505', flag: '🇳🇮', label: 'NI' },
    { code: '+506', flag: '🇨🇷', label: 'CR' }, { code: '+507', flag: '🇵🇦', label: 'PA' },
    { code: '+509', flag: '🇭🇹', label: 'HT' }, { code: '+53', flag: '🇨🇺', label: 'CU' },
    { code: '+1-809', flag: '🇩🇴', label: 'DO' }, { code: '+1-787', flag: '🇵🇷', label: 'PR' },
    { code: '+591', flag: '🇧🇴', label: 'BO' }, { code: '+595', flag: '🇵🇾', label: 'PY' },
    { code: '+598', flag: '🇺🇾', label: 'UY' }, { code: '+592', flag: '🇬🇾', label: 'GY' },
    { code: '+597', flag: '🇸🇷', label: 'SR' }, { code: '+501', flag: '🇧🇿', label: 'BZ' },
    { code: '+91', flag: '🇮🇳', label: 'IN' }, { code: '+86', flag: '🇨🇳', label: 'CN' },
    { code: '+81', flag: '🇯🇵', label: 'JP' }, { code: '+82', flag: '🇰🇷', label: 'KR' },
    { code: '+62', flag: '🇮🇩', label: 'ID' }, { code: '+63', flag: '🇵🇭', label: 'PH' },
    { code: '+66', flag: '🇹🇭', label: 'TH' }, { code: '+84', flag: '🇻🇳', label: 'VN' },
    { code: '+60', flag: '🇲🇾', label: 'MY' }, { code: '+65', flag: '🇸🇬', label: 'SG' },
    { code: '+880', flag: '🇧🇩', label: 'BD' }, { code: '+92', flag: '🇵🇰', label: 'PK' },
    { code: '+94', flag: '🇱🇰', label: 'LK' }, { code: '+95', flag: '🇲🇲', label: 'MM' },
    { code: '+977', flag: '🇳🇵', label: 'NP' }, { code: '+855', flag: '🇰🇭', label: 'KH' },
    { code: '+856', flag: '🇱🇦', label: 'LA' }, { code: '+852', flag: '🇭🇰', label: 'HK' },
    { code: '+886', flag: '🇹🇼', label: 'TW' }, { code: '+971', flag: '🇦🇪', label: 'AE' },
    { code: '+966', flag: '🇸🇦', label: 'SA' }, { code: '+972', flag: '🇮🇱', label: 'IL' },
    { code: '+961', flag: '🇱🇧', label: 'LB' }, { code: '+962', flag: '🇯🇴', label: 'JO' },
    { code: '+964', flag: '🇮🇶', label: 'IQ' }, { code: '+965', flag: '🇰🇼', label: 'KW' },
    { code: '+968', flag: '🇴🇲', label: 'OM' }, { code: '+973', flag: '🇧🇭', label: 'BH' },
    { code: '+974', flag: '🇶🇦', label: 'QA' }, { code: '+90', flag: '🇹🇷', label: 'TR' },
    { code: '+98', flag: '🇮🇷', label: 'IR' }, { code: '+993', flag: '🇹🇲', label: 'TM' },
    { code: '+994', flag: '🇦🇿', label: 'AZ' }, { code: '+995', flag: '🇬🇪', label: 'GE' },
    { code: '+996', flag: '🇰🇬', label: 'KG' }, { code: '+998', flag: '🇺🇿', label: 'UZ' },
    { code: '+234', flag: '🇳🇬', label: 'NG' }, { code: '+27', flag: '🇿🇦', label: 'ZA' },
    { code: '+254', flag: '🇰🇪', label: 'KE' }, { code: '+233', flag: '🇬🇭', label: 'GH' },
    { code: '+20', flag: '🇪🇬', label: 'EG' }, { code: '+212', flag: '🇲🇦', label: 'MA' },
    { code: '+213', flag: '🇩🇿', label: 'DZ' }, { code: '+216', flag: '🇹🇳', label: 'TN' },
    { code: '+218', flag: '🇱🇾', label: 'LY' }, { code: '+221', flag: '🇸🇳', label: 'SN' },
    { code: '+225', flag: '🇨🇮', label: 'CI' }, { code: '+237', flag: '🇨🇲', label: 'CM' },
    { code: '+243', flag: '🇨🇩', label: 'CD' }, { code: '+244', flag: '🇦🇴', label: 'AO' },
    { code: '+249', flag: '🇸🇩', label: 'SD' }, { code: '+251', flag: '🇪🇹', label: 'ET' },
    { code: '+255', flag: '🇹🇿', label: 'TZ' }, { code: '+256', flag: '🇺🇬', label: 'UG' },
    { code: '+258', flag: '🇲🇿', label: 'MZ' }, { code: '+260', flag: '🇿🇲', label: 'ZM' },
    { code: '+263', flag: '🇿🇼', label: 'ZW' },
    { code: '+61', flag: '🇦🇺', label: 'AU' }, { code: '+64', flag: '🇳🇿', label: 'NZ' },
    { code: '+679', flag: '🇫🇯', label: 'FJ' }, { code: '+675', flag: '🇵🇬', label: 'PG' },
    { code: '+7', flag: '🇷🇺', label: 'RU' }, { code: '+380', flag: '🇺🇦', label: 'UA' },
    { code: '+48', flag: '🇵🇱', label: 'PL' }, { code: '+31', flag: '🇳🇱', label: 'NL' },
    { code: '+32', flag: '🇧🇪', label: 'BE' }, { code: '+41', flag: '🇨🇭', label: 'CH' },
    { code: '+43', flag: '🇦🇹', label: 'AT' }, { code: '+45', flag: '🇩🇰', label: 'DK' },
    { code: '+46', flag: '🇸🇪', label: 'SE' }, { code: '+47', flag: '🇳🇴', label: 'NO' },
    { code: '+358', flag: '🇫🇮', label: 'FI' }, { code: '+353', flag: '🇮🇪', label: 'IE' },
    { code: '+351', flag: '🇵🇹', label: 'PT' }, { code: '+30', flag: '🇬🇷', label: 'GR' },
    { code: '+36', flag: '🇭🇺', label: 'HU' }, { code: '+40', flag: '🇷🇴', label: 'RO' },
    { code: '+420', flag: '🇨🇿', label: 'CZ' }, { code: '+421', flag: '🇸🇰', label: 'SK' },
    { code: '+385', flag: '🇭🇷', label: 'HR' }, { code: '+381', flag: '🇷🇸', label: 'RS' },
    { code: '+359', flag: '🇧🇬', label: 'BG' }, { code: '+370', flag: '🇱🇹', label: 'LT' },
    { code: '+371', flag: '🇱🇻', label: 'LV' }, { code: '+372', flag: '🇪🇪', label: 'EE' },
    { code: '+354', flag: '🇮🇸', label: 'IS' }, { code: '+352', flag: '🇱🇺', label: 'LU' },
    { code: '+356', flag: '🇲🇹', label: 'MT' }, { code: '+357', flag: '🇨🇾', label: 'CY' },
    { code: '+355', flag: '🇦🇱', label: 'AL' }, { code: '+382', flag: '🇲🇪', label: 'ME' },
    { code: '+389', flag: '🇲🇰', label: 'MK' }, { code: '+387', flag: '🇧🇦', label: 'BA' },
    { code: '+386', flag: '🇸🇮', label: 'SI' },
];

// Parse existing phone into code + number
function parsePhone(phone) {
    if (!phone) return { code: '+1', number: '' };
    const match = phone.match(/^(\+\d+)\s*(.*)$/);
    if (match) return { code: match[1], number: match[2] };
    return { code: '+1', number: phone };
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

const countryOptions = ['Afghanistan','Albania','Algeria','Andorra','Angola','Antigua and Barbuda','Argentina','Armenia','Australia','Austria','Azerbaijan','Bahamas','Bahrain','Bangladesh','Barbados','Belarus','Belgium','Belize','Benin','Bhutan','Bolivia','Bosnia and Herzegovina','Botswana','Brazil','Brunei','Bulgaria','Burkina Faso','Burundi','Cabo Verde','Cambodia','Cameroon','Canada','Central African Republic','Chad','Chile','China','Colombia','Comoros','Congo','Costa Rica','Croatia','Cuba','Cyprus','Czech Republic','Denmark','Djibouti','Dominica','Dominican Republic','East Timor','Ecuador','Egypt','El Salvador','Equatorial Guinea','Eritrea','Estonia','Eswatini','Ethiopia','Fiji','Finland','France','Gabon','Gambia','Georgia','Germany','Ghana','Greece','Grenada','Guatemala','Guinea','Guinea-Bissau','Guyana','Haiti','Honduras','Hungary','Iceland','India','Indonesia','Iran','Iraq','Ireland','Israel','Italy','Ivory Coast','Jamaica','Japan','Jordan','Kazakhstan','Kenya','Kiribati','Kosovo','Kuwait','Kyrgyzstan','Laos','Latvia','Lebanon','Lesotho','Liberia','Libya','Liechtenstein','Lithuania','Luxembourg','Madagascar','Malawi','Malaysia','Maldives','Mali','Malta','Marshall Islands','Mauritania','Mauritius','Mexico','Micronesia','Moldova','Monaco','Mongolia','Montenegro','Morocco','Mozambique','Myanmar','Namibia','Nauru','Nepal','Netherlands','New Zealand','Nicaragua','Niger','Nigeria','North Korea','North Macedonia','Norway','Oman','Pakistan','Palau','Palestine','Panama','Papua New Guinea','Paraguay','Peru','Philippines','Poland','Portugal','Puerto Rico','Qatar','Romania','Russia','Rwanda','Saint Kitts and Nevis','Saint Lucia','Saint Vincent and the Grenadines','Samoa','San Marino','Sao Tome and Principe','Saudi Arabia','Senegal','Serbia','Seychelles','Sierra Leone','Singapore','Slovakia','Slovenia','Solomon Islands','Somalia','South Africa','South Korea','South Sudan','Spain','Sri Lanka','Sudan','Suriname','Sweden','Switzerland','Syria','Taiwan','Tajikistan','Tanzania','Thailand','Togo','Tonga','Trinidad and Tobago','Tunisia','Turkey','Turkmenistan','Tuvalu','Uganda','Ukraine','United Arab Emirates','United Kingdom','United States','Uruguay','Uzbekistan','Vanuatu','Vatican City','Venezuela','Vietnam','Yemen','Zambia','Zimbabwe','Other'];
const retailCategoryOptions = ['Athleisure','Accessories','Activewear/Sportswear','Bridal','Eveningwear/Gowns','Indigenous','Kids/Youth','Lingerie','Ready to Wear','Resort/Swimwear','Streetwear','Suits','Upcycle/Organic','Other'];
const designsReadyOptions = ['Under 10', 'Under 25', 'Over 25'];
const budgetOptions = ['$5,000 to $10,000', '$10,000 to $25,000', '$25,000 to $75,000', '$75,000+'];
const pastShowsOptions = ['0', '1', '2', '3', '4', '5+'];
const contactTimeOptions = [
    '9:00 AM', '10:00 AM', '11:00 AM', '12:00 PM',
    '1:00 PM', '2:00 PM', '3:00 PM', '4:00 PM', '5:00 PM',
];

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
                                    <option v-for="pc in phoneCodes" :key="pc.code" :value="pc.code">{{ pc.flag }} {{ pc.code }}</option>
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
