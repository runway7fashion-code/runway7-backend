<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    EnvelopeIcon, PhoneIcon, BuildingOffice2Icon, GlobeAltIcon, CheckIcon,
    ArrowDownTrayIcon, DocumentTextIcon, UserPlusIcon, XMarkIcon, TrashIcon,
    StarIcon, PlusIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    sponsor: Object,
    registrations: Array,
    guests: Array,
    totalAllowedGuests: Number,
    events: Array,
    eventDays: Array,
    shows: Array,
    isLider: Boolean,
});

const guestsLeft = computed(() => Math.max(0, (props.totalAllowedGuests || 0) - (props.guests?.length || 0)));

// Onboarding email
function sendOnboarding(regId = null) {
    if (!confirm('Enviar onboarding email?')) return;
    useForm({ registration_id: regId }).post(`/admin/sponsorship/sponsors/${props.sponsor.id}/send-onboarding`, { preserveScroll: true });
}

// Add guest modal
const showGuestModal = ref(false);
const guestForm = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    event_id: '',
    event_day_id: '',
    show_id: '',
    notes: '',
});

const filteredEventDays = computed(() => {
    if (!guestForm.event_id) return [];
    return props.eventDays.filter(d => d.event_id === Number(guestForm.event_id));
});
const filteredShows = computed(() => {
    if (!guestForm.event_day_id) return [];
    return props.shows.filter(s => s.event_day_id === Number(guestForm.event_day_id));
});

function submitGuest() {
    guestForm.post(`/admin/sponsorship/sponsors/${props.sponsor.id}/guests`, {
        preserveScroll: true,
        onSuccess: () => {
            showGuestModal.value = false;
            guestForm.reset();
        },
    });
}

function removeGuest(guest) {
    if (!confirm(`Remove guest ${guest.guest?.first_name} ${guest.guest?.last_name}?`)) return;
    useForm({}).delete(`/admin/sponsorship/guests/${guest.id}`, { preserveScroll: true });
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
function formatPrice(v) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(v) || 0);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center space-x-2 text-sm">
                <Link href="/admin/sponsorship/sponsors" class="text-gray-400 hover:text-gray-600">Sponsors</Link>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-medium">{{ sponsor.first_name }} {{ sponsor.last_name }}</span>
            </div>
        </template>

        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Header -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-full bg-[#D4AF37] flex items-center justify-center text-xl font-bold text-black">
                            {{ sponsor.first_name[0] }}{{ sponsor.last_name[0] }}
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                                {{ sponsor.first_name }} {{ sponsor.last_name }}
                                <StarIcon class="w-5 h-5 text-[#D4AF37]" />
                            </h3>
                            <p class="text-gray-500 text-sm">{{ sponsor.sponsor_profile?.company_name }}</p>
                        </div>
                    </div>
                    <button @click="sendOnboarding()"
                        class="px-4 py-2 text-sm font-semibold text-black bg-[#D4AF37] rounded-lg hover:bg-yellow-600 flex items-center gap-1.5">
                        <EnvelopeIcon class="w-4 h-4" />
                        {{ sponsor.welcome_email_sent_at ? 'Re-send onboarding' : 'Send onboarding email' }}
                    </button>
                </div>

                <div class="mt-5 pt-5 border-t border-gray-100 grid grid-cols-2 gap-3 text-sm">
                    <div class="flex items-start gap-2">
                        <EnvelopeIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                        <div>
                            <p class="text-xs text-gray-400">Email</p>
                            <p class="font-medium text-gray-900">{{ sponsor.email }}</p>
                        </div>
                    </div>
                    <div v-if="sponsor.phone" class="flex items-start gap-2">
                        <PhoneIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                        <div>
                            <p class="text-xs text-gray-400">Phone</p>
                            <p class="font-medium text-gray-900">{{ sponsor.phone }}</p>
                        </div>
                    </div>
                    <div v-if="sponsor.sponsor_profile?.website" class="flex items-start gap-2">
                        <GlobeAltIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                        <div>
                            <p class="text-xs text-gray-400">Website</p>
                            <a :href="sponsor.sponsor_profile.website" target="_blank" class="font-medium text-blue-600 hover:underline truncate block">{{ sponsor.sponsor_profile.website }}</a>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <CheckIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                        <div>
                            <p class="text-xs text-gray-400">Onboarding</p>
                            <p class="font-medium" :class="sponsor.welcome_email_sent_at ? 'text-green-600' : 'text-gray-400'">
                                {{ sponsor.welcome_email_sent_at ? `Sent ${formatDate(sponsor.welcome_email_sent_at)}` : 'Not sent yet' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Registrations -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h4 class="font-semibold text-gray-900 mb-4">Contracts / Registrations ({{ registrations.length }})</h4>
                <div class="space-y-3">
                    <div v-for="r in registrations" :key="r.id" class="border border-gray-100 rounded-xl p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ r.event?.name }}</p>
                                <p class="text-xs text-gray-500">Package: <span class="font-medium">{{ r.package?.name || '—' }}</span></p>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize"
                                :class="{
                                    'bg-green-100 text-green-700': r.status === 'confirmed',
                                    'bg-blue-100 text-blue-700': r.status === 'onboarded',
                                    'bg-yellow-100 text-yellow-700': r.status === 'registered',
                                    'bg-gray-100 text-gray-500': r.status === 'cancelled'
                                }">
                                {{ r.status }}
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-3 text-xs">
                            <div>
                                <p class="text-gray-400">Price</p>
                                <p class="font-semibold text-gray-900">{{ formatPrice(r.agreed_price) }}</p>
                            </div>
                            <div>
                                <p class="text-gray-400">Downpayment</p>
                                <p class="font-semibold text-gray-900">{{ formatPrice(r.downpayment) }}</p>
                            </div>
                            <div>
                                <p class="text-gray-400">Installments</p>
                                <p class="font-semibold text-gray-900">{{ r.installments_count }}</p>
                            </div>
                        </div>
                        <div v-if="r.documents?.length" class="mt-3 pt-3 border-t border-gray-100 space-y-1">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Documents</p>
                            <div v-for="d in r.documents" :key="d.id" class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <DocumentTextIcon class="w-4 h-4 text-gray-400" />
                                    <span class="text-gray-700">{{ d.original_name }}</span>
                                </div>
                                <a :href="`/storage/${d.path}`" target="_blank" class="text-blue-600 hover:underline">
                                    <ArrowDownTrayIcon class="w-4 h-4" />
                                </a>
                            </div>
                        </div>
                        <p v-if="r.onboarding_email_sent_at" class="mt-2 text-xs text-green-600">
                            ✓ Onboarding email sent {{ formatDate(r.onboarding_email_sent_at) }}
                        </p>
                    </div>
                    <p v-if="!registrations.length" class="text-sm text-gray-400">No registrations yet.</p>
                </div>
            </div>

            <!-- Guests -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h4 class="font-semibold text-gray-900">Guests ({{ guests.length }} / {{ totalAllowedGuests }})</h4>
                        <p class="text-xs text-gray-500">{{ guestsLeft }} slots remaining</p>
                    </div>
                    <button @click="showGuestModal = true" :disabled="guestsLeft === 0"
                        class="px-3 py-1.5 text-xs font-medium text-white bg-black rounded-lg hover:bg-gray-800 disabled:opacity-40 flex items-center gap-1">
                        <UserPlusIcon class="w-4 h-4" /> Add guest
                    </button>
                </div>
                <div v-if="guests.length" class="divide-y divide-gray-100">
                    <div v-for="g in guests" :key="g.id" class="py-3 flex items-start justify-between">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900">{{ g.guest?.first_name }} {{ g.guest?.last_name }}</p>
                            <p class="text-xs text-gray-500">{{ g.guest?.email }} <span v-if="g.guest?.phone"> · {{ g.guest.phone }}</span></p>
                            <p v-if="g.event || g.event_day || g.show" class="text-xs text-gray-400 mt-1">
                                <span v-if="g.event">{{ g.event.name }}</span>
                                <span v-if="g.event_day"> · {{ formatDate(g.event_day.date) }}<span v-if="g.event_day.label"> ({{ g.event_day.label }})</span></span>
                                <span v-if="g.show"> · {{ g.show.name }}</span>
                            </p>
                            <p v-if="g.notes" class="text-xs text-gray-400 italic mt-1">{{ g.notes }}</p>
                        </div>
                        <button @click="removeGuest(g)" class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500">
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400">No guests registered yet.</p>
            </div>
        </div>

        <!-- Add guest modal -->
        <Teleport to="body">
            <div v-if="showGuestModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showGuestModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 max-h-[90vh] overflow-auto">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">New guest</h3>
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label">First name *</label>
                                <input v-model="guestForm.first_name" type="text" class="input" />
                                <p v-if="guestForm.errors.first_name" class="err">{{ guestForm.errors.first_name }}</p>
                            </div>
                            <div>
                                <label class="label">Last name *</label>
                                <input v-model="guestForm.last_name" type="text" class="input" />
                                <p v-if="guestForm.errors.last_name" class="err">{{ guestForm.errors.last_name }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="label">Email *</label>
                            <input v-model="guestForm.email" type="email" class="input" />
                            <p v-if="guestForm.errors.email" class="err">{{ guestForm.errors.email }}</p>
                            <p v-if="guestForm.errors.quota" class="err">{{ guestForm.errors.quota }}</p>
                        </div>

                        <div>
                            <label class="label">Phone</label>
                            <input v-model="guestForm.phone" type="tel" class="input" />
                        </div>

                        <div class="grid grid-cols-1 gap-3 pt-2 border-t border-gray-100">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Show assignment (optional)</p>
                            <div>
                                <label class="label">Event</label>
                                <select v-model="guestForm.event_id" @change="() => { guestForm.event_day_id = ''; guestForm.show_id = ''; }" class="input bg-white">
                                    <option value="">—</option>
                                    <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                                </select>
                            </div>
                            <div v-if="filteredEventDays.length">
                                <label class="label">Day</label>
                                <select v-model="guestForm.event_day_id" @change="() => guestForm.show_id = ''" class="input bg-white">
                                    <option value="">—</option>
                                    <option v-for="d in filteredEventDays" :key="d.id" :value="d.id">
                                        {{ formatDate(d.date) }} <span v-if="d.label">— {{ d.label }}</span>
                                    </option>
                                </select>
                            </div>
                            <div v-if="filteredShows.length">
                                <label class="label">Show</label>
                                <select v-model="guestForm.show_id" class="input bg-white">
                                    <option value="">—</option>
                                    <option v-for="s in filteredShows" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="label">Notes</label>
                            <input v-model="guestForm.notes" type="text" class="input" />
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="showGuestModal = false"
                                class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancel</button>
                            <button type="button" @click="submitGuest" :disabled="guestForm.processing"
                                class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40">
                                Add guest
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
