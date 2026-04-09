<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { CheckIcon, XMarkIcon, UserIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    leads: Object,
    stats: Object,
    events: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const redirectType = ref(props.filters?.redirect_type || '');
const redirectStatus = ref(props.filters?.redirect_status || '');

let searchTimeout;
watch(search, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});
watch([redirectType, redirectStatus], () => applyFilters());

function applyFilters() {
    router.get('/admin/operations/incoming-leads', {
        search: search.value || undefined,
        redirect_type: redirectType.value || undefined,
        redirect_status: redirectStatus.value || undefined,
    }, { preserveState: true, replace: true });
}

const typeLabels = { model: 'Model', media: 'Media', volunteer: 'Volunteer' };
const typeColors = { model: 'bg-blue-100 text-blue-700', media: 'bg-purple-100 text-purple-700', volunteer: 'bg-green-100 text-green-700' };
const statusLabels = { new: 'New', converted: 'Converted', rejected: 'Rejected' };
const statusColors = { new: 'bg-blue-100 text-blue-700', converted: 'bg-green-100 text-green-700', rejected: 'bg-gray-100 text-gray-500' };

// Convert modal
const showConvertModal = ref(false);
const convertLead = ref(null);
const convertForm = useForm({ role: '', event_id: '' });

function openConvert(lead) {
    convertLead.value = lead;
    convertForm.role = lead.redirect_type || 'model';
    convertForm.event_id = '';
    showConvertModal.value = true;
}

function submitConvert() {
    convertForm.post(`/admin/operations/incoming-leads/${convertLead.value.id}/convert`, {
        preserveScroll: true,
        onSuccess: () => { showConvertModal.value = false; convertLead.value = null; },
    });
}

// Reject modal
const showRejectModal = ref(false);
const rejectLead = ref(null);
const rejectForm = useForm({ redirect_note: '' });

function openReject(lead) {
    rejectLead.value = lead;
    rejectForm.redirect_note = '';
    showRejectModal.value = true;
}

function submitReject() {
    rejectForm.patch(`/admin/operations/incoming-leads/${rejectLead.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => { showRejectModal.value = false; rejectLead.value = null; },
    });
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Incoming Leads</h2>
        </template>

        <div class="space-y-6">
            <!-- Stats -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg border border-gray-200 px-4 py-3">
                    <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wide">Total</p>
                    <p class="text-xl font-bold text-gray-700 mt-1">{{ stats.total }}</p>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 px-4 py-3">
                    <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wide">New</p>
                    <p class="text-xl font-bold text-blue-600 mt-1">{{ stats.new }}</p>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 px-4 py-3">
                    <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wide">Converted</p>
                    <p class="text-xl font-bold text-green-600 mt-1">{{ stats.converted }}</p>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 px-4 py-3">
                    <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wide">Rejected</p>
                    <p class="text-xl font-bold text-gray-500 mt-1">{{ stats.rejected }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3 items-center">
                <input v-model="search" type="text" placeholder="Search name, email, phone..."
                    class="flex-1 min-w-48 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                <select v-model="redirectType" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                    <option value="">All types</option>
                    <option value="model">Model</option>
                    <option value="media">Media</option>
                    <option value="volunteer">Volunteer</option>
                </select>
                <select v-model="redirectStatus" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                    <option value="">All statuses</option>
                    <option value="new">New</option>
                    <option value="converted">Converted</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-200">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Phone</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Country</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Instagram</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Sent by</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Note</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="lead in leads.data" :key="lead.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ lead.first_name }} {{ lead.last_name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ lead.email }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ lead.phone || '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ lead.country || '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ lead.instagram || '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="typeColors[lead.redirect_type] || 'bg-gray-100 text-gray-500'">
                                    {{ typeLabels[lead.redirect_type] || lead.redirect_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusColors[lead.redirect_status] || 'bg-gray-100 text-gray-500'">
                                    {{ statusLabels[lead.redirect_status] || lead.redirect_status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">
                                {{ lead.redirected_by_user ? `${lead.redirected_by_user.first_name} ${lead.redirected_by_user.last_name}` : '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(lead.redirected_at) }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500 max-w-32 truncate" :title="lead.redirect_note">{{ lead.redirect_note || '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <template v-if="lead.redirect_status === 'new'">
                                        <button @click="openConvert(lead)"
                                            class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs font-medium hover:bg-green-700 transition-colors flex items-center gap-1">
                                            <CheckIcon class="w-3.5 h-3.5" /> Convert
                                        </button>
                                        <button @click="openReject(lead)"
                                            class="px-3 py-1.5 border border-gray-300 text-gray-600 rounded-lg text-xs font-medium hover:bg-gray-100 transition-colors flex items-center gap-1">
                                            <XMarkIcon class="w-3.5 h-3.5" /> Reject
                                        </button>
                                    </template>
                                    <template v-else-if="lead.redirect_status === 'converted'">
                                        <span class="text-xs text-green-600 font-medium">Converted</span>
                                    </template>
                                    <template v-else>
                                        <span class="text-xs text-gray-400">Rejected</span>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="leads.data.length === 0">
                            <td colspan="11" class="px-6 py-12 text-center text-gray-400 text-sm">No incoming leads found.</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="leads.last_page > 1" class="border-t border-gray-200 px-4 py-3 flex items-center justify-between">
                    <p class="text-xs text-gray-500">Showing {{ leads.from }}–{{ leads.to }} of {{ leads.total }}</p>
                    <div class="flex gap-1">
                        <template v-for="link in leads.links" :key="link.label">
                            <Link v-if="link.url" :href="link.url" preserve-state
                                class="px-3 py-1 rounded text-xs transition-colors"
                                :class="link.active ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100'"
                                v-html="link.label" />
                            <span v-else class="px-3 py-1 text-xs text-gray-300" v-html="link.label" />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Convert Modal -->
        <Teleport to="body">
            <div v-if="showConvertModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showConvertModal = false">
                <div class="bg-white rounded-2xl w-full max-w-md shadow-xl">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-900">Convert Lead</h3>
                        <button @click="showConvertModal = false" class="p-1 rounded-lg hover:bg-gray-100"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
                    </div>
                    <div class="px-6 py-5 space-y-4">
                        <p class="text-sm text-gray-500">Convert <strong>{{ convertLead?.first_name }} {{ convertLead?.last_name }}</strong> ({{ convertLead?.email }}) to a new user.</p>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select v-model="convertForm.role" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="model">Model</option>
                                <option value="media">Media</option>
                                <option value="volunteer">Volunteer</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Assign to Event (optional)</label>
                            <select v-model="convertForm.event_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">— No event —</option>
                                <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                            </select>
                        </div>
                        <p v-if="convertForm.errors.email" class="text-red-500 text-xs">{{ convertForm.errors.email }}</p>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                        <button @click="showConvertModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                        <button @click="submitConvert" :disabled="convertForm.processing"
                            class="px-4 py-2 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg disabled:opacity-50">
                            {{ convertForm.processing ? 'Converting...' : 'Convert' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Reject Modal -->
        <Teleport to="body">
            <div v-if="showRejectModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showRejectModal = false">
                <div class="bg-white rounded-2xl w-full max-w-md shadow-xl">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-900">Reject Lead</h3>
                        <button @click="showRejectModal = false" class="p-1 rounded-lg hover:bg-gray-100"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
                    </div>
                    <div class="px-6 py-5 space-y-4">
                        <p class="text-sm text-gray-500">Mark <strong>{{ rejectLead?.first_name }} {{ rejectLead?.last_name }}</strong> as rejected/does not apply.</p>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reason (optional)</label>
                            <textarea v-model="rejectForm.redirect_note" rows="3" placeholder="Why does this lead not apply..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                        <button @click="showRejectModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                        <button @click="submitReject" :disabled="rejectForm.processing"
                            class="px-4 py-2 text-sm font-semibold text-white bg-gray-600 hover:bg-gray-700 rounded-lg disabled:opacity-50">
                            {{ rejectForm.processing ? 'Rejecting...' : 'Reject' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
