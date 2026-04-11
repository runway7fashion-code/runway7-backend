<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import EmailComposer from '@/Components/EmailComposer.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { EnvelopeIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    users: Object,
    channel: String,
    allowedRoles: Array,
    statusesByRole: Object,
    events: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const role = ref(props.filters?.role || '');
const status = ref(props.filters?.status || '');
const eventId = ref(props.filters?.event_id || '');
const selectedUsers = ref([]);

const showEmailModal = ref(false);
const emailProcessing = ref(false);

let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});
watch([role, status, eventId], () => applyFilters());

function applyFilters() {
    router.get('/admin/communications/email', {
        search: search.value || undefined,
        role: role.value || undefined,
        status: status.value || undefined,
        event_id: eventId.value || undefined,
    }, { preserveState: true, replace: true });
}

const allSelected = computed({
    get: () => props.users.data.length > 0 && selectedUsers.value.length === props.users.data.length,
    set: (val) => { selectedUsers.value = val ? props.users.data.map(u => u.id) : []; },
});

function openEmailModal() {
    if (selectedUsers.value.length === 0) return;
    showEmailModal.value = true;
}

function handleEmailSend({ subject, body, attachments, scheduled_at }) {
    const formData = new FormData();
    formData.append('subject', subject);
    formData.append('body', body);
    if (scheduled_at) formData.append('scheduled_at', scheduled_at);
    selectedUsers.value.forEach(id => formData.append('user_ids[]', id));
    attachments.forEach(file => formData.append('attachments[]', file));

    emailProcessing.value = true;
    router.post('/admin/communications/email/send', formData, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { showEmailModal.value = false; selectedUsers.value = []; },
        onFinish: () => { emailProcessing.value = false; },
    });
}

// Dynamic status options based on selected role
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

const statusLabels = {
    active: 'Active',
    inactive: 'Inactive',
    pending: 'Pending',
    registered: 'Registered',
    applicant: 'Applicant',
};

const roleLabels = {
    model: 'Model', designer: 'Designer', media: 'Media', volunteer: 'Volunteer',
    staff: 'Staff', assistant: 'Assistant', attendee: 'Attendee', vip: 'VIP',
    influencer: 'Influencer', press: 'Press', sponsor: 'Sponsor', complementary: 'Complementary',
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
            <h2 class="text-lg font-semibold text-gray-900">Communications · Email</h2>
        </template>

        <div class="space-y-6">
            <!-- Header card -->
            <div class="bg-white rounded-xl border border-gray-200 px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-gray-900">Send Email</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Select recipients from the list below and compose your message.</p>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <Link href="/admin/communications/email" class="px-3 py-1.5 rounded-lg bg-black text-white font-medium">Email</Link>
                    <Link href="/admin/communications/sms" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 font-medium hover:bg-gray-200">SMS</Link>
                    <Link href="/admin/communications/notifications" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 font-medium hover:bg-gray-200">Notifications</Link>
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
            </div>

            <!-- Bulk actions bar -->
            <div v-if="selectedUsers.length > 0" class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2.5">
                <span class="text-sm font-medium text-amber-800">{{ selectedUsers.length }} selected</span>
                <button @click="openEmailModal"
                    class="px-3 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors flex items-center gap-1">
                    <EnvelopeIcon class="w-3.5 h-3.5" /> Compose Email
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
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Phone</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
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
                            <td class="px-4 py-3 text-sm text-gray-600">{{ user.phone || '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="roleColors[user.role] || 'bg-gray-100 text-gray-500'">
                                    {{ roleLabels[user.role] || user.role }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-gray-500">{{ user.status }}</td>
                        </tr>
                        <tr v-if="users.data.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400 text-sm">No users found with the applied filters.</td>
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

        <!-- Email Composer Modal -->
        <Teleport to="body">
            <div v-if="showEmailModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showEmailModal = false">
                <EmailComposer
                    :recipient-label="`${selectedUsers.length} recipient${selectedUsers.length > 1 ? 's' : ''}`"
                    :processing="emailProcessing"
                    @send="handleEmailSend"
                    @close="showEmailModal = false"
                />
            </div>
        </Teleport>
    </AdminLayout>
</template>
