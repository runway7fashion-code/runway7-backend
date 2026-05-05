<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    events: Array,
    countries: Array,
});

const activeTab = ref(1);
const phoneCode = ref('+1');
const phoneNumber = ref('');

const usStates = [
    'Alabama','Alaska','Arizona','Arkansas','California','Colorado','Connecticut','Delaware',
    'Florida','Georgia','Hawaii','Idaho','Illinois','Indiana','Iowa','Kansas','Kentucky',
    'Louisiana','Maine','Maryland','Massachusetts','Michigan','Minnesota','Mississippi',
    'Missouri','Montana','Nebraska','Nevada','New Hampshire','New Jersey','New Mexico',
    'New York','North Carolina','North Dakota','Ohio','Oklahoma','Oregon','Pennsylvania',
    'Rhode Island','South Carolina','South Dakota','Tennessee','Texas','Utah','Vermont',
    'Virginia','Washington','Washington D.C.','West Virginia','Wisconsin','Wyoming',
    'Puerto Rico','Outside the U.S.',
];

const form = useForm({
    // Pestaña 1 - Datos personales
    first_name:   '',
    last_name:    '',
    email:        '',
    phone:        '',
    instagram:    '',
    age:          '',
    gender:       'female',
    location:     '',
    ethnicity:    '',
    hair:         '',
    body_type:    '',
    // Pestaña 2 - Medidas
    height:       '',
    bust:         '',
    chest:        '',
    waist:        '',
    hips:         '',
    shoe_size:    '',
    dress_size:   '',
    // Agencia
    agency:       '',
    is_agency:    false,
    is_test_model: false,
    notes:                 '',
    referral_source:       '',
    referral_source_other: '',
    walk_video_url:        '',
    // Pestaña 3 - Evento
    event_id:     '',
    casting_time: '',
});

const ageOptions = Array.from({ length: 63 }, (_, i) => i + 18); // 18-80

const selectedEvent = computed(() => props.events.find(e => e.id == form.event_id) ?? null);
const castingSlots  = computed(() => selectedEvent.value?.casting_day?.slots ?? []);

function slotColor(slot) {
    if (slot.available === 0) return 'bg-red-50 border-red-300 text-red-600 opacity-60 cursor-not-allowed';
    if (slot.available <= 10) return 'bg-yellow-50 border-yellow-300 text-yellow-700 hover:bg-yellow-100 cursor-pointer';
    return 'bg-green-50 border-green-300 text-green-700 hover:bg-green-100 cursor-pointer';
}

function selectSlot(slot) {
    if (slot.available === 0) return;
    form.casting_time = form.casting_time === slot.time ? '' : slot.time;
}

function formatSlotTime(t) {
    const [h, m] = t.split(':');
    const hour = parseInt(h);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const h12  = hour % 12 || 12;
    return `${h12}:${m} ${ampm}`;
}

function submit() {
    form.phone = phoneNumber.value ? `${phoneCode.value}${phoneNumber.value.replace(/\D/g, '')}` : '';
    form.post('/admin/operations/models');
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/operations/models" class="text-gray-400 hover:text-gray-600 text-sm">← Models</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Create Model</h2>
            </div>
        </template>

        <div class="max-w-3xl mx-auto">
            <!-- Tabs -->
            <div class="flex gap-1 bg-gray-100 rounded-xl p-1 mb-6">
                <button v-for="tab in [
                    { n: 1, label: 'Personal Info' },
                    { n: 2, label: 'Measurements' },
                    { n: 3, label: 'Event and Casting' },
                ]" :key="tab.n"
                    type="button"
                    @click="activeTab = tab.n"
                    class="flex-1 py-2 text-sm font-medium rounded-lg transition-all"
                    :class="activeTab === tab.n ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                    {{ tab.label }}
                </button>
            </div>

            <form @submit.prevent="submit" novalidate class="space-y-5">

                <!-- Tab 1: Personal Info -->
                <div v-show="activeTab === 1" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        The app login password will be <strong>runway7</strong> for all models.
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
                                    <option v-for="c in countries" :key="c.phone" :value="c.phone">{{ c.flag }} {{ c.phone }}</option>
                                </select>
                                <input v-model="phoneNumber" type="tel" placeholder="3055550404"
                                    class="flex-1 border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Age</label>
                            <select v-model="form.age"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Select —</option>
                                <option v-for="a in ageOptions" :key="a" :value="a">{{ a }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                            <select v-model="form.gender"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="female">Female</option>
                                <option value="male">Male</option>
                                <option value="non_binary">Non-binary</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Instagram</label>
                            <input v-model="form.instagram" type="text" placeholder="@username"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                        <select v-model="form.location"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">Select...</option>
                            <option v-for="s in usStates" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ethnicity</label>
                            <select v-model="form.ethnicity"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Unspecified —</option>
                                <option value="asian">Asian</option>
                                <option value="black">Black</option>
                                <option value="caucasian">Caucasian</option>
                                <option value="hispanic">Hispanic</option>
                                <option value="middle_eastern">Middle Eastern</option>
                                <option value="mixed">Mixed</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Hair color</label>
                            <select v-model="form.hair"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Unspecified —</option>
                                <option value="black">Black</option>
                                <option value="brown">Brown</option>
                                <option value="blonde">Blonde</option>
                                <option value="red">Red</option>
                                <option value="gray">Gray</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Body type</label>
                            <select v-model="form.body_type"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Unspecified —</option>
                                <option value="slim">Slim</option>
                                <option value="athletic">Athletic</option>
                                <option value="average">Average</option>
                                <option value="curvy">Curvy</option>
                                <option value="plus_size">Plus Size</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-6 pt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.is_test_model" type="checkbox"
                                class="rounded border-gray-300 text-black focus:ring-black/20" />
                            <span class="text-sm text-gray-700">Test model</span>
                        </label>
                    </div>
                </div>

                <!-- Tab 2: Measurements -->
                <div v-show="activeTab === 2" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="font-semibold text-gray-800">Measurements</h3>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Height (in)</label>
                            <input v-model="form.height" type="number" step="0.1" min="55" max="87"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bust / Chest (in)</label>
                            <input v-model="form.bust" type="number" step="0.1"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Waist (in)</label>
                            <input v-model="form.waist" type="number" step="0.1"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Hips (in)</label>
                            <input v-model="form.hips" type="number" step="0.1"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Shoe size</label>
                            <input v-model="form.shoe_size" type="text" placeholder="e.g. 8.5"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Dress size</label>
                            <input v-model="form.dress_size" type="text" placeholder="e.g. S, M, 4"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <label class="flex items-center gap-2 cursor-pointer mb-3">
                            <input v-model="form.is_agency" type="checkbox"
                                class="rounded border-gray-300 text-black focus:ring-black/20" />
                            <span class="text-sm font-medium text-gray-700">Comes from an agency</span>
                        </label>
                        <div v-if="form.is_agency">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Agency name</label>
                            <input v-model="form.agency" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Internal notes</label>
                        <textarea v-model="form.notes" rows="3"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">How did she hear about us?</label>
                        <select v-model="form.referral_source"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">— Unspecified —</option>
                            <option value="instagram">Instagram</option>
                            <option value="tiktok">TikTok</option>
                            <option value="facebook">Facebook</option>
                            <option value="friends_family">Friends or Family</option>
                            <option value="agency">Agency</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div v-if="form.referral_source === 'other'">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Specify</label>
                        <input v-model="form.referral_source_other" type="text"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Walk Video URL</label>
                        <input v-model="form.walk_video_url" type="url" placeholder="https://..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        <p class="text-xs text-gray-400 mt-1">Public link where her runway walk can be viewed</p>
                    </div>
                </div>

                <!-- Tab 3: Event and Casting -->
                <div v-show="activeTab === 3" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assign to event</label>
                        <select v-model="form.event_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">— Unassigned —</option>
                            <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>

                    <!-- Casting slots for the selected event -->
                    <div v-if="selectedEvent && castingSlots.length">
                        <p class="text-sm font-medium text-gray-700 mb-1">
                            Casting:
                            <span class="text-gray-500 font-normal">{{ selectedEvent.casting_day?.date }}</span>
                        </p>
                        <p class="text-xs text-gray-400 mb-3">Select the model's casting time:</p>

                        <div class="grid grid-cols-4 gap-2">
                            <button
                                v-for="slot in castingSlots"
                                :key="slot.id"
                                type="button"
                                :disabled="slot.available === 0"
                                @click="selectSlot(slot)"
                                :class="[
                                    slotColor(slot),
                                    form.casting_time === slot.time ? 'ring-2 ring-black ring-offset-1' : '',
                                    'border rounded-lg p-2 text-center text-xs transition-all'
                                ]">
                                <p class="font-semibold">{{ formatSlotTime(slot.time) }}</p>
                                <p class="text-[10px] mt-0.5 opacity-80">{{ slot.booked }}/{{ slot.capacity }}</p>
                            </button>
                        </div>

                        <p v-if="form.casting_time" class="mt-2 text-sm text-green-700">
                            ✓ Time selected: <strong>{{ formatSlotTime(form.casting_time) }}</strong>
                        </p>
                    </div>

                    <div v-else-if="selectedEvent && !castingSlots.length" class="text-sm text-gray-400 italic">
                        This event has no casting day configured.
                    </div>

                </div>

                <!-- Buttons -->
                <div class="flex justify-between">
                    <Link href="/admin/operations/models"
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
                            <span v-else>Create Model</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
