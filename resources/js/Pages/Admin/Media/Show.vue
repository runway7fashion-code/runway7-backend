<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import QrCode from '@/Components/QrCode.vue';
import { ArrowLeftIcon, EnvelopeIcon, DevicePhoneMobileIcon, XMarkIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ media: Object, events: Array });

const profile    = computed(() => props.media.media_profile);
const events     = computed(() => props.media.events ?? []);
const assistants = computed(() => props.media.assistants ?? []);
const commLogs   = computed(() => props.media.communication_logs ?? []);

function statusBadgeClass(s) {
    return { active: 'bg-green-50 text-green-700', inactive: 'bg-red-50 text-red-600', rejected: 'bg-orange-50 text-orange-700', pending: 'bg-yellow-50 text-yellow-700', applicant: 'bg-purple-50 text-purple-700' }[s] ?? 'bg-gray-50 text-gray-600';
}
function statusBadgeLabel(s) {
    return { active: 'Active', inactive: 'Inactive', rejected: 'Rejected', pending: 'Pending', applicant: 'Applicant' }[s] ?? s;
}
function eventStatusLabel(s) {
    return { assigned: 'Scheduled', checked_in: 'Check-in', no_show: 'No show', rejected: 'Rejected', pending_payment: 'Pending payment' }[s] ?? s;
}
function eventStatusClass(s) {
    return { assigned: 'bg-blue-100 text-blue-700', checked_in: 'bg-green-100 text-green-700', no_show: 'bg-yellow-100 text-yellow-700', rejected: 'bg-red-100 text-red-600', pending_payment: 'bg-orange-100 text-orange-700' }[s] ?? 'bg-gray-100 text-gray-600';
}
function paymentLabel(s) {
    return { paid: 'Paid', pending: 'Pending', expired: 'Expired', manual: 'Manual' }[s] ?? s;
}
function paymentClass(s) {
    return { paid: 'bg-green-50 text-green-700', pending: 'bg-yellow-50 text-yellow-700', expired: 'bg-red-50 text-red-600', manual: 'bg-gray-50 text-gray-700' }[s] ?? 'bg-gray-50 text-gray-600';
}
function shopifyOrderUrl(orderNumber) {
    if (!orderNumber) return null;
    const clean = String(orderNumber).replace(/^#/, '');
    return `https://admin.shopify.com/store/b40827-9f/orders?query=${encodeURIComponent(clean)}`;
}
function fmtMoney(n) {
    return n != null ? '$' + Number(n).toFixed(2) : '—';
}
function fmtDate(d) {
    return d ? new Date(d).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}
function commStatusLabel(s) { return { queued: 'Queued', sent: 'Sent', failed: 'Failed' }[s] ?? s; }
function commStatusClass(s) {
    return { queued: 'bg-yellow-100 text-yellow-700', sent: 'bg-green-100 text-green-700', failed: 'bg-red-100 text-red-700' }[s] ?? 'bg-gray-100 text-gray-600';
}

function sendOnboardingEmail() {
    if (!confirm('Send onboarding email?')) return;
    router.post(`/admin/operations/media/${props.media.id}/send-onboarding`, {}, { preserveScroll: true });
}
function sendOnboardingSms() {
    if (!confirm('Send onboarding SMS?')) return;
    router.post(`/admin/operations/media/${props.media.id}/send-onboarding-sms`, {}, { preserveScroll: true });
}

function updateEventStatus(eventId, newStatus) {
    router.patch(`/admin/operations/media/${props.media.id}/events/${eventId}/status`, { status: newStatus }, { preserveScroll: true });
}
function removeFromEvent(eventId, eventName) {
    if (!confirm(`Remove ${props.media.first_name} from event "${eventName}"?`)) return;
    router.delete(`/admin/operations/media/${props.media.id}/remove-event/${eventId}`, { preserveScroll: true });
}

// Assign event
const assignEventId = ref('');
function assignEvent() {
    if (!assignEventId.value) return;
    router.post(`/admin/operations/media/${props.media.id}/assign-event`, { event_id: assignEventId.value }, {
        preserveScroll: true,
        onSuccess: () => { assignEventId.value = ''; },
    });
}

// Assistant
const assistantForm = ref({ full_name: '', document_id: '', phone: '', email: '', event_id: '' });
function addAssistant() {
    if (!assistantForm.value.full_name || !assistantForm.value.event_id) return;
    router.post(`/admin/operations/media/${props.media.id}/assistants`, assistantForm.value, {
        preserveScroll: true,
        onSuccess: () => { assistantForm.value = { full_name: '', document_id: '', phone: '', email: '', event_id: '' }; },
    });
}
function removeAssistant(id) {
    if (!confirm('Delete assistant?')) return;
    router.delete(`/admin/operations/media/${props.media.id}/assistants/${id}`, { preserveScroll: true });
}

// Pass modal
const passModal = ref(null);
function openPassModal(evt) { passModal.value = evt.pass; }
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link href="/admin/operations/media" class="text-gray-400 hover:text-gray-600"><ArrowLeftIcon class="w-5 h-5" /></Link>
                <h2 class="text-lg font-semibold text-gray-900">{{ media.first_name }} {{ media.last_name }}</h2>
            </div>
        </template>

        <div class="max-w-5xl mx-auto">
            <div class="grid grid-cols-3 gap-6">
                <!-- Left column (2/3) -->
                <div class="col-span-2 space-y-6">
                    <!-- Header card -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">{{ media.first_name }} {{ media.last_name }}</h3>
                                <p class="text-gray-500 text-sm mt-1">{{ media.email }}</p>
                                <p v-if="media.phone" class="text-gray-400 text-sm">{{ media.phone }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span :class="statusBadgeClass(media.status)" class="text-xs font-medium rounded-lg px-3 py-1.5">
                                    {{ statusBadgeLabel(media.status) }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 mt-4 flex-wrap">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                                :class="profile?.category === 'photographer' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'">
                                {{ profile?.category === 'photographer' ? 'Photographer' : 'Videographer' }}
                            </span>
                            <span v-if="profile?.importance" class="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">
                                Importance: {{ profile.importance }}
                            </span>
                            <a v-if="profile?.instagram" :href="`https://instagram.com/${profile.instagram}`" target="_blank"
                                class="text-xs text-pink-600 hover:text-pink-800 font-medium">
                                @{{ profile.instagram }}
                            </a>
                        </div>
                        <div class="flex gap-2 mt-4">
                            <button @click="sendOnboardingEmail" class="px-3 py-1.5 bg-black text-white text-xs font-medium rounded-lg hover:bg-gray-800">
                                <EnvelopeIcon class="w-3.5 h-3.5 inline mr-1" /> Send Email
                            </button>
                            <button @click="sendOnboardingSms" class="px-3 py-1.5 border border-gray-200 text-xs font-medium rounded-lg hover:bg-gray-50 text-gray-700">
                                <DevicePhoneMobileIcon class="w-3.5 h-3.5 inline mr-1" /> Send SMS
                            </button>
                            <Link :href="`/admin/operations/media/${media.id}/edit`" class="px-3 py-1.5 border border-gray-200 text-xs font-medium rounded-lg hover:bg-gray-50 text-gray-700">Edit</Link>
                        </div>
                    </div>

                    <!-- Events -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h4 class="font-bold text-gray-900 mb-4">Events</h4>
                        <div v-if="events.length === 0" class="text-sm text-gray-400 italic">No events assigned.</div>
                        <div v-for="evt in events" :key="evt.id" class="border border-gray-100 rounded-xl mb-3 last:mb-0">
                            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 rounded-t-xl">
                                <span class="text-sm font-semibold text-gray-800">{{ evt.name }}</span>
                                <button @click="removeFromEvent(evt.id, evt.name)" class="text-red-400 hover:text-red-600"><XMarkIcon class="w-4 h-4" /></button>
                            </div>
                            <div class="px-4 py-3 space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <p class="text-[10px] text-gray-400 uppercase tracking-wider mb-1">Event status</p>
                                        <select :value="evt.media_status" @change="updateEventStatus(evt.id, $event.target.value)"
                                            class="w-full border rounded-lg px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-black/10"
                                            :class="eventStatusClass(evt.media_status)">
                                            <option value="assigned">Scheduled</option>
                                            <option value="checked_in">Check-in</option>
                                            <option value="no_show">No show</option>
                                            <option value="rejected">Rejected</option>
                                        </select>
                                    </div>
                                    <div v-if="evt.pass">
                                        <p class="text-[10px] text-gray-400 uppercase tracking-wider mb-1">Pass</p>
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-[11px] text-gray-500">{{ evt.pass.qr_code }}</span>
                                            <button @click="openPassModal(evt)" class="text-[11px] text-indigo-500 hover:text-indigo-700 font-medium">View QR</button>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="evt.days?.length" class="text-xs text-gray-500">
                                    <span v-for="(d, i) in evt.days" :key="d.id">{{ d.label }}<span v-if="i < evt.days.length - 1"> · </span></span>
                                </div>

                                <!-- Purchase details -->
                                <div v-if="evt.purchase" class="mt-3 pt-3 border-t border-gray-100">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Purchase</p>
                                        <span :class="paymentClass(evt.purchase.payment_status)" class="text-[10px] font-medium rounded-full px-2 py-0.5">
                                            {{ paymentLabel(evt.purchase.payment_status) }}
                                        </span>
                                    </div>
                                    <div class="space-y-1.5 text-xs">
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-500">Kit</span>
                                            <span class="font-medium text-gray-800">{{ evt.purchase.kit_name }}</span>
                                        </div>
                                        <div v-if="evt.purchase.addons?.length" class="flex items-start justify-between gap-2">
                                            <span class="text-gray-500 flex-shrink-0">Add-ons</span>
                                            <div class="flex flex-wrap gap-1 justify-end">
                                                <span v-for="a in evt.purchase.addons" :key="a.key" class="bg-amber-50 text-amber-700 rounded px-1.5 py-0.5 text-[10px] font-medium">{{ a.name }}</span>
                                            </div>
                                        </div>
                                        <div v-if="evt.purchase.total_amount != null" class="flex items-center justify-between">
                                            <span class="text-gray-500">Total</span>
                                            <span class="font-semibold text-gray-900">{{ fmtMoney(evt.purchase.total_amount) }}</span>
                                        </div>
                                        <div v-if="evt.purchase.shopify_order_number" class="flex items-center justify-between">
                                            <span class="text-gray-500">Order #</span>
                                            <a :href="shopifyOrderUrl(evt.purchase.shopify_order_number)" target="_blank"
                                                class="font-mono text-blue-600 hover:text-blue-800">#{{ evt.purchase.shopify_order_number }}</a>
                                        </div>
                                        <div v-if="evt.purchase.paid_at" class="flex items-center justify-between">
                                            <span class="text-gray-500">Paid at</span>
                                            <span class="text-gray-700">{{ fmtDate(evt.purchase.paid_at) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Assign event -->
                        <div class="mt-4 pt-4 border-t border-gray-100 flex gap-2">
                            <select v-model="assignEventId" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                <option value="">Assign event...</option>
                                <option v-for="e in $page.props.events" :key="e.id" :value="e.id">{{ e.name }}</option>
                            </select>
                            <button @click="assignEvent" :disabled="!assignEventId" class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40">Assign</button>
                        </div>
                    </div>

                    <!-- Assistants -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h4 class="font-bold text-gray-900 mb-4">Assistants <span class="text-gray-400 font-normal text-sm">({{ assistants.length }}/{{ profile?.max_assistants ?? 0 }})</span></h4>
                        <div v-if="assistants.length === 0" class="text-sm text-gray-400 italic mb-4">No assistants registered.</div>
                        <div v-for="a in assistants" :key="a.id" class="flex items-center justify-between bg-gray-50 rounded-lg px-4 py-3 mb-2">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ a.full_name }}</p>
                                <p class="text-xs text-gray-500">{{ a.email || '—' }} · {{ a.event_name }}</p>
                            </div>
                            <button @click="removeAssistant(a.id)" class="text-red-400 hover:text-red-600"><TrashIcon class="w-4 h-4" /></button>
                        </div>
                        <!-- Add assistant form -->
                        <div v-if="(profile?.max_assistants ?? 0) > assistants.length" class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-2 gap-3">
                            <input v-model="assistantForm.full_name" placeholder="Full name *" class="border border-gray-200 rounded-lg px-3 py-2 text-sm" />
                            <input v-model="assistantForm.email" type="email" placeholder="Email" class="border border-gray-200 rounded-lg px-3 py-2 text-sm" />
                            <input v-model="assistantForm.phone" placeholder="Phone" class="border border-gray-200 rounded-lg px-3 py-2 text-sm" />
                            <select v-model="assistantForm.event_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                <option value="">Event *</option>
                                <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                            </select>
                            <div class="col-span-2">
                                <button @click="addAssistant" :disabled="!assistantForm.full_name || !assistantForm.event_id"
                                    class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40">Add Assistant</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right column (1/3) -->
                <div class="space-y-6">
                    <!-- Profile details -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h4 class="font-bold text-gray-900 mb-3">Details</h4>
                        <div class="space-y-3 text-sm">
                            <div><span class="text-gray-400 text-xs block">Category</span><span class="text-gray-800">{{ profile?.category === 'photographer' ? 'Photographer' : 'Videographer' }}</span></div>
                            <div><span class="text-gray-400 text-xs block">Location</span><span class="text-gray-800">{{ profile?.location ?? '—' }}</span></div>
                            <div><span class="text-gray-400 text-xs block">Traveling for the event</span><span class="text-gray-800">{{ profile?.will_travel === 'yes' ? 'Yes' : 'No' }}</span></div>
                            <div v-if="profile?.portfolio_url"><span class="text-gray-400 text-xs block">Portfolio</span><a :href="profile.portfolio_url" target="_blank" class="text-blue-600 hover:text-blue-800 break-all text-xs">{{ profile.portfolio_url }}</a></div>
                            <div><span class="text-gray-400 text-xs block">Importance</span><span class="text-gray-800">{{ profile?.importance ?? '—' }}</span></div>
                            <div><span class="text-gray-400 text-xs block">Max Assistants</span><span class="text-gray-800">{{ profile?.max_assistants ?? 0 }}</span></div>
                        </div>
                    </div>

                    <!-- Media Links -->
                    <div v-if="profile?.media_link_1 || profile?.media_link_2 || profile?.media_link_3" class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h4 class="font-bold text-gray-900 mb-3">Media Links</h4>
                        <div class="space-y-2">
                            <a v-if="profile.media_link_1" :href="profile.media_link_1" target="_blank" class="block text-xs text-blue-600 hover:text-blue-800 break-all bg-blue-50 rounded-lg px-3 py-2">{{ profile.media_link_1 }}</a>
                            <a v-if="profile.media_link_2" :href="profile.media_link_2" target="_blank" class="block text-xs text-blue-600 hover:text-blue-800 break-all bg-blue-50 rounded-lg px-3 py-2">{{ profile.media_link_2 }}</a>
                            <a v-if="profile.media_link_3" :href="profile.media_link_3" target="_blank" class="block text-xs text-blue-600 hover:text-blue-800 break-all bg-blue-50 rounded-lg px-3 py-2">{{ profile.media_link_3 }}</a>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div v-if="profile?.notes" class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h4 class="font-bold text-gray-900 mb-2">Notes</h4>
                        <p class="text-sm text-gray-600 leading-relaxed">{{ profile.notes }}</p>
                    </div>

                    <!-- Communication logs -->
                    <div v-if="commLogs.length" class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h4 class="font-bold text-gray-900 mb-3">Communication History</h4>
                        <div class="space-y-2">
                            <div v-for="log in commLogs" :key="log.id" class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <EnvelopeIcon v-if="log.type === 'email'" class="w-3.5 h-3.5 text-gray-400" />
                                    <DevicePhoneMobileIcon v-else class="w-3.5 h-3.5 text-gray-400" />
                                    <span :class="commStatusClass(log.status)" class="text-[10px] font-medium px-1.5 py-0.5 rounded-full">{{ commStatusLabel(log.status) }}</span>
                                </div>
                                <span class="text-[10px] text-gray-400">{{ new Date(log.sent_at ?? log.created_at).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' }) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>

    <!-- QR Pass Modal -->
    <Teleport to="body">
        <div v-if="passModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/60" @click="passModal = null"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl max-w-xs w-full mx-4 p-6 z-10 text-center">
                <QrCode :value="passModal.qr_code" :size="200" class="mx-auto mb-3" />
                <p class="font-mono text-sm text-gray-500">{{ passModal.qr_code }}</p>
                <button @click="passModal = null" class="mt-4 px-5 py-2 bg-black text-white text-sm font-medium rounded-lg hover:bg-gray-800">Close</button>
            </div>
        </div>
    </Teleport>
</template>
