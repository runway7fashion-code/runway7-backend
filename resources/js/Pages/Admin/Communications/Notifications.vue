<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import NotificationComposer from '@/Components/NotificationComposer.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { BellAlertIcon, MagnifyingGlassIcon, XMarkIcon, ExclamationTriangleIcon, DevicePhoneMobileIcon } from '@heroicons/vue/24/outline';
import axios from 'axios';

const props = defineProps({
    users: Object,
    channel: String,
    allowedRoles: Array,
    statusesByRole: Object,
    events: Array,
    filters: Object,
    variables: Array,
    deepLinks: Array,
});

const search = ref(props.filters?.search || '');
const role = ref(props.filters?.role || '');
const status = ref(props.filters?.status || '');
const eventId = ref(props.filters?.event_id || '');
const hasDevice = ref(props.filters?.has_device || '');
const selectedUsers = ref([]);

const showComposerModal = ref(false);
const showPreviewModal = ref(false);
const loading = ref(false);
const previewData = ref(null);
const pendingPayload = ref(null);

let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});
watch([role, status, eventId, hasDevice], () => applyFilters());

function applyFilters() {
    router.get('/admin/communications/notifications', {
        search: search.value || undefined,
        role: role.value || undefined,
        status: status.value || undefined,
        event_id: eventId.value || undefined,
        has_device: hasDevice.value || undefined,
    }, { preserveState: true, replace: true });
}

const allSelected = computed({
    get: () => props.users.data.length > 0 && selectedUsers.value.length === props.users.data.length,
    set: (val) => { selectedUsers.value = val ? props.users.data.map(u => u.id) : []; },
});

// Dynamic status options
const availableStatuses = computed(() => {
    if (role.value && props.statusesByRole?.[role.value]) {
        return props.statusesByRole[role.value];
    }
    const all = new Set();
    Object.values(props.statusesByRole || {}).forEach(arr => arr.forEach(s => all.add(s)));
    return Array.from(all);
});

watch(role, () => {
    if (status.value && !availableStatuses.value.includes(status.value)) {
        status.value = '';
    }
});

function openComposer() {
    if (selectedUsers.value.length === 0) return;
    showComposerModal.value = true;
}

async function handlePreview(payload) {
    pendingPayload.value = payload;
    loading.value = true;
    try {
        const { data } = await axios.post('/admin/communications/notifications/preview', {
            user_ids: selectedUsers.value,
            title: payload.title,
            body: payload.body,
        });
        previewData.value = data;
        showComposerModal.value = false;
        showPreviewModal.value = true;
    } catch (e) {
        alert('Error loading preview: ' + (e.response?.data?.message || e.message));
    } finally {
        loading.value = false;
    }
}

function confirmSend() {
    loading.value = true;
    router.post('/admin/communications/notifications/send', {
        user_ids: selectedUsers.value,
        title: pendingPayload.value.title,
        body: pendingPayload.value.body,
        deep_link: pendingPayload.value.deep_link,
        scheduled_at: pendingPayload.value.scheduled_at,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showPreviewModal.value = false;
            selectedUsers.value = [];
            previewData.value = null;
            pendingPayload.value = null;
        },
        onFinish: () => { loading.value = false; },
    });
}

function cancelPreview() {
    showPreviewModal.value = false;
    showComposerModal.value = true;
}

const statusLabels = {
    active: 'Active', inactive: 'Inactive', pending: 'Pending',
    registered: 'Registered', applicant: 'Applicant',
};

const roleLabels = {
    model: 'Model', designer: 'Designer', media: 'Media', volunteer: 'Volunteer',
    staff: 'Staff', assistant: 'Assistant', attendee: 'Attendee', vip: 'VIP',
    influencer: 'Influencer', press: 'Press', sponsor: 'Sponsor',
};
const roleColors = {
    model: 'bg-blue-100 text-blue-700',
    designer: 'bg-purple-100 text-purple-700',
    media: 'bg-pink-100 text-pink-700',
    volunteer: 'bg-green-100 text-green-700',
    staff: 'bg-gray-100 text-gray-700',
    attendee: 'bg-amber-100 text-amber-700',
    vip: 'bg-yellow-100 text-yellow-800',
    influencer: 'bg-fuchsia-100 text-fuchsia-700',
    press: 'bg-rose-100 text-rose-700',
    sponsor: 'bg-indigo-100 text-indigo-700',
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Communications · Notifications</h2>
        </template>

        <div class="space-y-6">
            <!-- Header card -->
            <div class="bg-white rounded-xl border border-gray-200 px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-gray-900">Send Push Notifications</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Send push notifications to users with the mobile app installed.</p>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <Link href="/admin/communications/email" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 font-medium hover:bg-gray-200">Email</Link>
                    <Link href="/admin/communications/sms" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 font-medium hover:bg-gray-200">SMS</Link>
                    <Link href="/admin/communications/notifications" class="px-3 py-1.5 rounded-lg bg-black text-white font-medium">Notifications</Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3 items-center">
                <div class="relative flex-1 min-w-48">
                    <MagnifyingGlassIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input v-model="search" type="text" placeholder="Search by name, email, phone..."
                        class="w-full border border-gray-200 rounded-lg pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                </div>
                <select v-model="role" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                    <option value="">All roles</option>
                    <option v-for="r in allowedRoles" :key="r" :value="r">{{ roleLabels[r] || r }}</option>
                </select>
                <select v-model="status" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                    <option value="">All statuses</option>
                    <option v-for="s in availableStatuses" :key="s" :value="s">{{ statusLabels[s] || s }}</option>
                </select>
                <select v-model="eventId" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                    <option value="">All events</option>
                    <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                </select>
                <select v-model="hasDevice" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                    <option value="">All users</option>
                    <option value="with">With app installed</option>
                    <option value="without">Without app</option>
                </select>
            </div>

            <!-- Bulk actions bar -->
            <div v-if="selectedUsers.length > 0" class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2.5">
                <span class="text-sm font-medium text-amber-800">{{ selectedUsers.length }} selected</span>
                <button @click="openComposer"
                    class="px-3 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors flex items-center gap-1">
                    <BellAlertIcon class="w-3.5 h-3.5" /> Compose Notification
                </button>
                <button @click="selectedUsers = []" class="text-xs text-gray-500 hover:text-gray-700">Clear</button>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-3 py-3 w-10"><input type="checkbox" v-model="allSelected" class="accent-black w-4 h-4 cursor-pointer" /></th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">User</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Devices</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50 transition-colors cursor-pointer" @click="selectedUsers.includes(user.id) ? selectedUsers.splice(selectedUsers.indexOf(user.id), 1) : selectedUsers.push(user.id)">
                            <td class="px-3 py-3" @click.stop>
                                <input type="checkbox" :value="user.id" v-model="selectedUsers" class="accent-black w-4 h-4 cursor-pointer" />
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-black flex items-center justify-center text-xs font-bold text-white">
                                        {{ user.first_name?.[0] }}{{ user.last_name?.[0] }}
                                    </div>
                                    <span class="text-sm font-medium text-gray-900">{{ user.first_name }} {{ user.last_name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ user.email }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="roleColors[user.role] || 'bg-gray-100 text-gray-500'">
                                    {{ roleLabels[user.role] || user.role }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-gray-500">{{ user.status }}</td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="user.device_count > 0"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                    <DevicePhoneMobileIcon class="w-3 h-3" /> {{ user.device_count }}
                                </span>
                                <span v-else class="text-xs text-gray-400">No app</span>
                            </td>
                        </tr>
                        <tr v-if="users.data.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400 text-sm">No users found.</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="users.last_page > 1" class="border-t border-gray-200 px-4 py-3 flex items-center justify-between">
                    <p class="text-xs text-gray-500">Showing {{ users.from }}–{{ users.to }} of {{ users.total }}</p>
                    <div class="flex gap-1">
                        <template v-for="link in users.links" :key="link.label">
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

        <!-- Composer Modal -->
        <Teleport to="body">
            <div v-if="showComposerModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showComposerModal = false">
                <NotificationComposer
                    :recipient-label="`${selectedUsers.length} recipient${selectedUsers.length > 1 ? 's' : ''}`"
                    :variables="variables"
                    :deep-links="deepLinks"
                    :processing="loading"
                    @preview="handlePreview"
                    @close="showComposerModal = false"
                />
            </div>
        </Teleport>

        <!-- Preview Modal -->
        <Teleport to="body">
            <div v-if="showPreviewModal && previewData" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="cancelPreview">
                <div class="bg-white rounded-2xl w-full max-w-lg shadow-xl">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-900">Confirm Notification</h3>
                        <button @click="cancelPreview" class="p-1 rounded-lg hover:bg-gray-100"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
                    </div>

                    <div class="px-6 py-5 space-y-4">
                        <!-- Stats -->
                        <div class="grid grid-cols-3 gap-3">
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <p class="text-xs text-gray-500">Selected</p>
                                <p class="text-2xl font-bold text-gray-900">{{ previewData.total_selected }}</p>
                            </div>
                            <div class="bg-green-50 rounded-lg p-3 text-center">
                                <p class="text-xs text-green-700">Will receive</p>
                                <p class="text-2xl font-bold text-green-700">{{ previewData.with_devices }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <p class="text-xs text-gray-500">Total devices</p>
                                <p class="text-2xl font-bold text-gray-700">{{ previewData.total_devices }}</p>
                            </div>
                        </div>

                        <!-- Preview card -->
                        <div class="bg-gray-100 rounded-2xl p-3 flex items-start gap-3">
                            <div class="w-10 h-10 rounded-lg bg-black flex items-center justify-center flex-shrink-0">
                                <BellAlertIcon class="w-5 h-5 text-[#D4AF37]" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-0.5">
                                    <span class="text-xs font-semibold text-gray-900">RUNWAY 7</span>
                                    <span class="text-[10px] text-gray-500">now</span>
                                </div>
                                <p class="text-sm font-semibold text-gray-900 leading-tight">{{ previewData.sample_title }}</p>
                                <p class="text-xs text-gray-600 leading-snug mt-0.5">{{ previewData.sample_body }}</p>
                            </div>
                        </div>

                        <p class="text-[10px] text-gray-400 uppercase font-semibold">Sample with variables replaced</p>

                        <!-- Warning for users without devices -->
                        <div v-if="previewData.without_devices > 0" class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <p class="text-xs text-amber-800 font-semibold mb-1">
                                <ExclamationTriangleIcon class="w-3.5 h-3.5 inline" /> {{ previewData.without_devices }} user(s) don't have the app installed and will be skipped:
                            </p>
                            <ul class="text-xs text-amber-700 space-y-0.5">
                                <li v-for="(name, i) in previewData.without_samples" :key="i">• {{ name }}</li>
                                <li v-if="previewData.without_devices > previewData.without_samples.length">• ... and {{ previewData.without_devices - previewData.without_samples.length }} more</li>
                            </ul>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                        <button @click="cancelPreview" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Back to edit</button>
                        <button @click="confirmSend" :disabled="loading || previewData.with_devices === 0"
                            class="px-5 py-2 text-sm font-semibold text-white bg-black hover:bg-gray-800 rounded-lg disabled:opacity-50">
                            {{ loading ? 'Sending...' : (pendingPayload?.scheduled_at ? `Schedule ${previewData.with_devices}` : `Send ${previewData.with_devices}`) }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
