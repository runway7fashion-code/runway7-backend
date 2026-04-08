<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { ArrowLeftIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    events:     Array,
    categories: Array,
    packages:   Array,
    salesReps:  Array,
    countries:  Array,
});

const activeTab = ref(1);
const phoneCode = ref('+1');
const phoneNumber = ref('');

const form = useForm({
    // Tab 1 - Personal Info
    first_name:      '',
    last_name:       '',
    email:           '',
    phone:           '',
    brand_name:      '',
    collection_name: '',
    category_id:     '',
    sales_rep_id:    '',
    country:         '',
    bio:             '',
    tracking_link:   '',
    skype:           '',
    social_media: {
        instagram: '',
        facebook:  '',
        tiktok:    '',
        website:   '',
        other:     '',
    },
    // Tab 2 - Event & Show
    event_id:              '',
    package_id:            '',
    looks:                 '',
    model_casting_enabled: true,
    media_package:         false,
    custom_background:     false,
    courtesy_tickets:      false,
    package_price:         '',
    notes:                 '',
    shows:                 [],
    fitting_slot_id:       '',
    // Tab 3 - Assistants
    assistants: [],
});

// Selected event
const selectedEvent = computed(() => props.events?.find(e => e.id == form.event_id) ?? null);

// Selected package - auto-fill looks and price
const selectedPackage = computed(() => props.packages?.find(p => p.id == form.package_id) ?? null);

watch(() => form.package_id, (newId) => {
    const pkg = props.packages?.find(p => p.id == newId);
    if (pkg) {
        form.looks = pkg.default_looks;
        form.package_price = pkg.price;
    }
});

// Shows from selected event (grouped by day)
const eventDays = computed(() => selectedEvent.value?.days ?? []);

// Fitting slots from selected event
const eventFittingSlots = computed(() => {
    const slots = [];
    for (const day of (selectedEvent.value?.days ?? [])) {
        for (const slot of day.fitting_slots ?? []) {
            slots.push({ ...slot, day_label: day.label, day_date: day.date });
        }
    }
    return slots;
});

function isShowSelected(showId) {
    return form.shows.some(s => s.show_id === showId);
}

function toggleShow(showId) {
    const idx = form.shows.findIndex(s => s.show_id === showId);
    if (idx >= 0) {
        form.shows.splice(idx, 1);
    } else {
        form.shows.push({ show_id: showId, collection_name: '' });
    }
}

// Assistants
function addAssistant() {
    form.assistants.push({ first_name: '', last_name: '', document_id: '', phone: '', email: '' });
}

function removeAssistant(index) {
    form.assistants.splice(index, 1);
}

function submit() {
    form.phone = phoneNumber.value ? `${phoneCode.value}${phoneNumber.value.replace(/\D/g, '')}` : '';
    form.post('/admin/operations/designers');
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/operations/designers" class="text-gray-400 hover:text-gray-600 text-sm flex items-center gap-1">
                    <ArrowLeftIcon class="w-4 h-4" /> Designers
                </Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Create Designer</h2>
            </div>
        </template>

        <div class="max-w-3xl mx-auto">
            <!-- Tabs -->
            <div class="flex gap-1 bg-gray-100 rounded-xl p-1 mb-6">
                <button v-for="tab in [
                    { n: 1, label: 'Personal Info' },
                    { n: 2, label: 'Event & Show' },
                    { n: 3, label: 'Assistants' },
                ]" :key="tab.n"
                    type="button"
                    @click="activeTab = tab.n"
                    class="flex-1 py-2 text-sm font-medium rounded-lg transition-all"
                    :class="activeTab === tab.n ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                    {{ tab.label }}
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-5">

                <!-- Tab 1: Personal Info -->
                <div v-show="activeTab === 1" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        The default app password will be <strong>runway7</strong> for all designers.
                    </p>

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
                                <select v-model="phoneCode"
                                    class="w-28 border border-gray-300 rounded-lg px-2 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                    <option v-for="c in countries" :key="c.code" :value="c.phone">{{ c.flag }} {{ c.phone }}</option>
                                </select>
                                <input v-model="phoneNumber" type="tel" placeholder="3055550404"
                                    class="flex-1 border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Brand Name *</label>
                            <input v-model="form.brand_name" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.brand_name" class="mt-1 text-red-500 text-xs">{{ form.errors.brand_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Collection Name</label>
                            <input v-model="form.collection_name" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select v-model="form.category_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— No category —</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                            <select v-model="form.country"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">— Select country —</option>
                                <option v-for="c in countries" :key="c.code" :value="c.name">{{ c.flag }} {{ c.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="salesReps?.length" class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sales Rep</label>
                            <select v-model="form.sales_rep_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Unassigned —</option>
                                <option v-for="rep in salesReps" :key="rep.id" :value="rep.id">{{ rep.first_name }} {{ rep.last_name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tracking Link</label>
                            <input v-model="form.tracking_link" type="text" placeholder="https://..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Skype</label>
                            <input v-model="form.skype" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bio</label>
                        <textarea v-model="form.bio" rows="3"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                    </div>

                    <!-- Social media -->
                    <div class="border-t border-gray-100 pt-4">
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">Social Media</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Instagram</label>
                                <input v-model="form.social_media.instagram" type="text" placeholder="@username"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Facebook</label>
                                <input v-model="form.social_media.facebook" type="text"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">TikTok</label>
                                <input v-model="form.social_media.tiktok" type="text" placeholder="@username"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Website</label>
                                <input v-model="form.social_media.website" type="text" placeholder="https://..."
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs text-gray-500 mb-1">Other</label>
                                <input v-model="form.social_media.other" type="text"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Event & Show -->
                <div v-show="activeTab === 2" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assign to Event</label>
                        <select v-model="form.event_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">— Unassigned —</option>
                            <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>

                    <div v-if="form.event_id" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Package</label>
                                <select v-model="form.package_id"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                    <option value="">— No package —</option>
                                    <option v-for="p in packages" :key="p.id" :value="p.id">
                                        {{ p.name }} — ${{ Number(p.price).toLocaleString() }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Looks</label>
                                <input v-model="form.looks" type="number" min="0"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Package Price ($)</label>
                                <input v-model="form.package_price" type="number" step="0.01" min="0"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                        </div>

                        <!-- Feature flags -->
                        <div class="grid grid-cols-2 gap-x-6 gap-y-3 bg-gray-50 rounded-xl p-4">
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

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea v-model="form.notes" rows="2"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                        </div>

                        <!-- Event shows -->
                        <div v-if="eventDays.length" class="border-t border-gray-100 pt-4">
                            <h4 class="text-sm font-semibold text-gray-800 mb-3">Assign to Shows</h4>
                            <div v-for="day in eventDays" :key="day.id" class="mb-3 last:mb-0">
                                <p class="text-xs text-gray-500 mb-2">{{ day.label }} — {{ day.date }}</p>
                                <div class="flex flex-wrap gap-2">
                                    <button v-for="show in day.shows" :key="show.id"
                                        type="button"
                                        @click="toggleShow(show.id)"
                                        :class="isShowSelected(show.id)
                                            ? 'bg-black text-white border-black'
                                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                        class="px-3 py-1.5 border rounded-lg text-xs font-medium transition-all">
                                        {{ show.name }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Fitting slot selector -->
                        <div v-if="eventFittingSlots.length" class="border-t border-gray-100 pt-4">
                            <label class="block text-sm font-semibold text-orange-600 mb-2">Fitting Schedule (optional)</label>
                            <select v-model="form.fitting_slot_id"
                                class="w-full border border-orange-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400/30">
                                <option value="">— No fitting —</option>
                                <option v-for="slot in eventFittingSlots" :key="slot.id" :value="slot.id">
                                    {{ slot.day_label }} · {{ slot.time }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Assistants -->
                <div v-show="activeTab === 3" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800">Assistants</h3>
                        <button type="button" @click="addAssistant"
                            class="px-3 py-1.5 text-xs bg-black text-white rounded-lg hover:bg-gray-800 transition-colors">
                            + Add Assistant
                        </button>
                    </div>

                    <p v-if="form.assistants.length === 0" class="text-sm text-gray-400 italic">
                        No assistants yet. Click "+ Add Assistant" to add one.
                    </p>

                    <div v-for="(assistant, i) in form.assistants" :key="i"
                        class="border border-gray-200 rounded-xl p-4 space-y-3 relative">
                        <button type="button" @click="removeAssistant(i)"
                            class="absolute top-3 right-3 text-red-400 hover:text-red-600">
                            <XMarkIcon class="w-4 h-4" />
                        </button>

                        <p class="text-xs font-semibold text-gray-500">Assistant {{ i + 1 }}</p>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">First Name *</label>
                                <input v-model="assistant.first_name" type="text"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Last Name</label>
                                <input v-model="assistant.last_name" type="text"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Document ID</label>
                                <input v-model="assistant.document_id" type="text"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Phone</label>
                                <input v-model="assistant.phone" type="tel"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Email</label>
                                <input v-model="assistant.email" type="email"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                        </div>
                    </div>

                    <p v-if="selectedPackage && form.assistants.length > 0"
                        class="text-xs text-gray-500">
                        Package {{ selectedPackage.name }} includes {{ selectedPackage.default_assistants }} assistants.
                        <span v-if="form.assistants.length > selectedPackage.default_assistants" class="text-amber-600 font-medium">
                            Exceeded by {{ form.assistants.length - selectedPackage.default_assistants }}.
                        </span>
                    </p>
                </div>

                <!-- Buttons -->
                <div class="flex justify-between">
                    <Link href="/admin/operations/designers"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        Cancel
                    </Link>
                    <div class="flex gap-3">
                        <button v-if="activeTab > 1" type="button" @click="activeTab--"
                            class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                            ← Previous
                        </button>
                        <button v-if="activeTab < 3" type="button" @click="activeTab++"
                            class="px-6 py-2.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-black transition-colors">
                            Next →
                        </button>
                        <button v-else type="submit" :disabled="form.processing"
                            class="px-8 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-60 transition-colors">
                            <span v-if="form.processing">Creating...</span>
                            <span v-else>Create Designer</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
