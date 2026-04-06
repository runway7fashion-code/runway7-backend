<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { FunnelIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    logs: Object,
    salesUsers: Array,
    actions: Array,
    entityTypes: Array,
    filters: Object,
});

const userId = ref(props.filters?.user_id || '');
const action = ref(props.filters?.action || '');
const entityType = ref(props.filters?.entity_type || '');
const dateFrom = ref(props.filters?.from || '');
const dateTo = ref(props.filters?.to || '');

function applyFilters() {
    router.get('/admin/sales/logs', {
        user_id: userId.value || undefined,
        action: action.value || undefined,
        entity_type: entityType.value || undefined,
        from: dateFrom.value || undefined,
        to: dateTo.value || undefined,
    }, { preserveState: true, replace: true });
}

function resetFilters() {
    userId.value = '';
    action.value = '';
    entityType.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    router.get('/admin/sales/logs');
}

watch([userId, action, entityType, dateFrom, dateTo], applyFilters);

const detailLog = ref(null);

const actionColors = {
    created: 'bg-emerald-100 text-emerald-700',
    updated: 'bg-blue-100 text-blue-700',
    deleted: 'bg-red-100 text-red-700',
    status_changed: 'bg-yellow-100 text-yellow-700',
    assigned: 'bg-purple-100 text-purple-700',
    event_status_changed: 'bg-orange-100 text-orange-700',
    activity_added: 'bg-indigo-100 text-indigo-700',
    activity_completed: 'bg-emerald-100 text-emerald-700',
    activity_cancelled: 'bg-red-100 text-red-700',
    activity_not_completed: 'bg-gray-100 text-gray-700',
    tags_synced: 'bg-pink-100 text-pink-700',
    event_added: 'bg-teal-100 text-teal-700',
    event_removed: 'bg-red-100 text-red-700',
    document_uploaded: 'bg-cyan-100 text-cyan-700',
    availability_toggled: 'bg-gray-100 text-gray-600',
};

const entityIcons = {
    lead: 'L',
    registration: 'R',
    tag: 'T',
    activity: 'A',
    package: 'P',
    calendar: 'C',
    availability: 'V',
    sales: 'S',
};

function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' ' +
           d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function formatAction(a) {
    return a.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

const fieldLabels = {
    first_name: 'First Name', last_name: 'Last Name', email: 'Email', phone: 'Phone',
    company_name: 'Company', country: 'Country', status: 'Status', source: 'Source',
    assigned_to: 'Assigned To', budget: 'Budget', notes: 'Notes', name: 'Name',
    description: 'Description', price: 'Price', event_id: 'Event', event_ids: 'Events',
    title: 'Title', type: 'Type', scheduled_at: 'Scheduled', color: 'Color',
    retail_category: 'Category', website_url: 'Website', instagram: 'Instagram',
    designs_ready: 'Designs Ready', past_shows: 'Past Shows', preferred_contact_time: 'Preferred Contact',
    default_looks: 'Default Looks', default_assistants: 'Default Assistants',
    is_active: 'Active', agreed_price: 'Price', downpayment: 'Down Payment',
};

const hiddenFields = ['_token', '_method', 'password', 'password_confirmation'];

function formatChanges(changes) {
    if (!changes || typeof changes !== 'object') return [];
    return Object.entries(changes)
        .filter(([key]) => !hiddenFields.includes(key))
        .map(([key, value]) => ({
            label: fieldLabels[key] || key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
            value: Array.isArray(value) ? value.join(', ') : (value === null || value === '' ? '—' : String(value)),
        }));
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Sales Activity Logs</h2>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Filters -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">User</label>
                        <select v-model="userId" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">All Users</option>
                            <option v-for="u in salesUsers" :key="u.id" :value="u.id">{{ u.first_name }} {{ u.last_name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Action</label>
                        <select v-model="action" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">All Actions</option>
                            <option v-for="a in actions" :key="a" :value="a">{{ formatAction(a) }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Entity</label>
                        <select v-model="entityType" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">All Entities</option>
                            <option v-for="e in entityTypes" :key="e" :value="e">{{ e.charAt(0).toUpperCase() + e.slice(1) }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
                        <input v-model="dateFrom" type="date" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
                        <input v-model="dateTo" type="date" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    </div>
                    <button @click="resetFilters" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                        Reset
                    </button>
                </div>
            </div>

            <!-- Logs table -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div v-if="!logs.data.length" class="p-12 text-center text-gray-400 text-sm">
                    No logs found. Actions will appear here as sales users perform operations.
                </div>
                <table v-else class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entity</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ formatDate(log.created_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-gray-900 flex items-center justify-center text-[10px] font-bold text-white flex-shrink-0">
                                        {{ log.user?.first_name?.charAt(0) ?? '?' }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 text-xs">{{ log.user?.first_name }} {{ log.user?.last_name }}</p>
                                        <p class="text-[10px] text-gray-400 capitalize">{{ log.user?.sales_type || 'admin' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase"
                                    :class="actionColors[log.action] || 'bg-gray-100 text-gray-600'">
                                    {{ formatAction(log.action) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-5 h-5 rounded flex items-center justify-center text-[10px] font-bold bg-gray-100 text-gray-600">
                                        {{ entityIcons[log.entity_type] || '?' }}
                                    </span>
                                    <span class="text-xs text-gray-700 capitalize">{{ log.entity_type }}</span>
                                    <span v-if="log.entity_id" class="text-[10px] text-gray-400">#{{ log.entity_id }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-700 max-w-xs truncate">{{ log.description }}</td>
                            <td class="px-4 py-3">
                                <button v-if="log.changes && formatChanges(log.changes).length"
                                    @click.stop="detailLog = log"
                                    class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                    View details
                                </button>
                                <span v-else class="text-xs text-gray-300">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="logs.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <p class="text-xs text-gray-500">Showing {{ logs.from }}-{{ logs.to }} of {{ logs.total }}</p>
                    <div class="flex gap-1">
                        <Link v-for="link in logs.links" :key="link.label"
                            :href="link.url || ''"
                            class="px-3 py-1 text-xs rounded-lg border transition-colors"
                            :class="link.active ? 'bg-black text-white border-black' : link.url ? 'border-gray-300 text-gray-600 hover:bg-gray-50' : 'border-gray-200 text-gray-300 pointer-events-none'"
                            v-html="link.label"
                            preserve-state
                        />
                    </div>
                </div>
            </div>
        </div>
        <!-- Detail Modal -->
        <Teleport to="body">
            <div v-if="detailLog" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="detailLog = null"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[80vh] overflow-y-auto">
                    <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-100 flex items-center justify-between rounded-t-2xl">
                        <div>
                            <h3 class="font-semibold text-gray-900">Activity Details</h3>
                            <p class="text-xs text-gray-500 mt-0.5">{{ formatDate(detailLog.created_at) }}</p>
                        </div>
                        <button @click="detailLog = null" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        <!-- Summary -->
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-gray-900 flex items-center justify-center text-xs font-bold text-white flex-shrink-0">
                                {{ detailLog.user?.first_name?.charAt(0) ?? '?' }}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ detailLog.user?.first_name }} {{ detailLog.user?.last_name }}</p>
                                <p class="text-xs text-gray-500">{{ detailLog.description }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase"
                                :class="actionColors[detailLog.action] || 'bg-gray-100 text-gray-600'">
                                {{ formatAction(detailLog.action) }}
                            </span>
                            <span class="text-xs text-gray-500 capitalize">{{ detailLog.entity_type }}</span>
                            <span v-if="detailLog.entity_id" class="text-xs text-gray-400">#{{ detailLog.entity_id }}</span>
                        </div>
                        <!-- Changes -->
                        <div v-if="detailLog.changes" class="border-t border-gray-100 pt-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Data</p>
                            <div class="space-y-2">
                                <div v-for="field in formatChanges(detailLog.changes)" :key="field.label"
                                    class="flex gap-3 py-1.5 border-b border-gray-50 last:border-0">
                                    <span class="text-xs text-gray-500 font-medium w-32 flex-shrink-0">{{ field.label }}</span>
                                    <span class="text-xs text-gray-900 break-words flex-1">{{ field.value }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
