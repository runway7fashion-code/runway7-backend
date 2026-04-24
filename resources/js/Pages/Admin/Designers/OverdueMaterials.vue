<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import {
    ExclamationTriangleIcon, UserGroupIcon, ClipboardDocumentListIcon,
    MagnifyingGlassIcon, EyeIcon, BellAlertIcon, ArrowLeftIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    rows: Array,
    stats: Object,
    events: Array,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');
const eventId = ref(props.filters?.event_id ?? '');
const sendingKey = ref(null);

function getFilterParams() {
    return {
        search: search.value || undefined,
        event_id: eventId.value || undefined,
    };
}

let searchTimeout = null;
function applyFilters() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/admin/operations/designers/overdue-materials', getFilterParams(), { preserveState: true, replace: true });
    }, 300);
}

watch([eventId], () => {
    clearTimeout(searchTimeout);
    router.get('/admin/operations/designers/overdue-materials', getFilterParams(), { preserveState: true, replace: true });
});

function urgencyClass(days) {
    if (days >= 14) return 'text-red-700 font-bold';
    if (days >= 7) return 'text-red-600 font-semibold';
    return 'text-orange-600 font-medium';
}

function urgencyBadge(days) {
    if (days >= 14) return 'bg-red-100 text-red-700';
    if (days >= 7)  return 'bg-red-50 text-red-600';
    return 'bg-orange-50 text-orange-600';
}

function sendReminder(row) {
    const key = `${row.designer_id}-${row.event_id}`;
    sendingKey.value = key;
    router.post(`/admin/operations/designers/${row.designer_id}/events/${row.event_id}/send-deadline-reminder`, {}, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { sendingKey.value = null; },
    });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/operations/designers" class="flex items-center gap-1 text-gray-400 hover:text-gray-600 text-sm">
                    <ArrowLeftIcon class="w-4 h-4" /> Designers
                </Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Overdue Materials</h2>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-5">

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center">
                            <UserGroupIcon class="w-5 h-5 text-red-500" />
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Designers overdue</p>
                            <p class="text-2xl font-bold text-gray-900">{{ stats.designers_overdue }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-orange-50 flex items-center justify-center">
                            <ClipboardDocumentListIcon class="w-5 h-5 text-orange-500" />
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Total pending materials</p>
                            <p class="text-2xl font-bold text-gray-900">{{ stats.total_pending }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center">
                            <ExclamationTriangleIcon class="w-5 h-5 text-red-500" />
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Max days overdue</p>
                            <p class="text-2xl font-bold text-gray-900">{{ stats.max_days_overdue }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <div class="flex flex-wrap gap-3 items-stretch">
                    <div class="relative flex-1 min-w-[240px]">
                        <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" />
                        <input v-model="search" @input="applyFilters" type="text"
                            placeholder="Search by designer name, email or brand..."
                            class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    </div>
                    <select v-model="eventId"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-black/10 min-w-[180px]">
                        <option value="">All events</option>
                        <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Designer</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Event</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Deadline</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Days overdue</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Pending</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Sales rep</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in rows" :key="`${row.designer_id}-${row.event_id}`" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                        <img v-if="row.profile_picture" :src="`/storage/${row.profile_picture}`" class="w-full h-full object-cover" />
                                        <span v-else class="text-xs font-semibold text-gray-500">
                                            {{ row.designer_name?.[0] || '?' }}
                                        </span>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900">{{ row.designer_name }}</div>
                                        <div v-if="row.brand_name" class="text-xs text-gray-500">{{ row.brand_name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ row.event_name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ row.deadline }}</td>
                            <td class="px-4 py-3 text-center">
                                <span :class="urgencyBadge(row.days_overdue)" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium">
                                    {{ row.days_overdue }} {{ row.days_overdue === 1 ? 'day' : 'days' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                    {{ row.pending_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">{{ row.sales_rep || '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex gap-1.5">
                                    <button @click="sendReminder(row)" :disabled="sendingKey === `${row.designer_id}-${row.event_id}`"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 disabled:opacity-50 transition-colors"
                                        title="Send manual reminder (email + push)">
                                        <BellAlertIcon class="w-3.5 h-3.5" />
                                        {{ sendingKey === `${row.designer_id}-${row.event_id}` ? 'Sending...' : 'Remind' }}
                                    </button>
                                    <Link :href="`/admin/operations/designers/${row.designer_id}/materials/${row.event_id}`"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 border border-gray-200 text-gray-600 rounded-lg text-xs font-medium hover:bg-gray-50 transition-colors"
                                        title="Open materials page">
                                        <EyeIcon class="w-3.5 h-3.5" />
                                        Open
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400 italic">
                                No designers with overdue materials. 🎉
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </AdminLayout>
</template>
