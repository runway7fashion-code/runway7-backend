<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { formatDateRange } from '@/utils/dates.js';
import { DocumentDuplicateIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    events: Object,
    filters: Object,
});

const statusFilter = ref(props.filters?.status ?? '');

const statusOptions = [
    { value: '', label: 'All statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'active', label: 'Active' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];

const statusConfig = {
    draft:     { label: 'Draft',     class: 'bg-gray-700 text-gray-300' },
    published: { label: 'Published', class: 'bg-blue-900/50 text-blue-300' },
    active:    { label: 'Active',    class: 'bg-green-900/50 text-green-300' },
    completed: { label: 'Completed', class: 'bg-purple-900/50 text-purple-300' },
    cancelled: { label: 'Cancelled', class: 'bg-red-900/50 text-red-300' },
};

function applyFilter() {
    router.get('/admin/operations/events', { status: statusFilter.value || undefined }, { preserveState: true });
}


const duplicateModal = ref(false);
const duplicatingEvent = ref(null);
const duplicateForm = ref({ name: '', start_date: '', end_date: '' });

function openDuplicate(event) {
    duplicatingEvent.value = event;
    duplicateForm.value = {
        name: event.name + ' (Copy)',
        start_date: '',
        end_date: '',
    };
    duplicateModal.value = true;
}

function submitDuplicate() {
    router.post(`/admin/operations/events/${duplicatingEvent.value.id}/duplicate`, duplicateForm.value, {
        onSuccess: () => { duplicateModal.value = false; },
    });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Events</h2>
        </template>

        <div>
            <!-- Top bar -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <select
                        v-model="statusFilter"
                        @change="applyFilter"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10"
                    >
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>
                <Link
                    href="/admin/operations/events/create"
                    class="px-4 py-2 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 transition-colors"
                >
                    + Create Event
                </Link>
            </div>

            <!-- Events grid -->
            <div v-if="events.data.length" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <div
                    v-for="event in events.data"
                    :key="event.id"
                    class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-md transition-shadow"
                >
                    <!-- Card header -->
                    <div class="bg-black p-5">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-white font-bold text-base leading-snug">{{ event.name }}</h3>
                            <span
                                class="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0"
                                :class="statusConfig[event.status]?.class ?? 'bg-gray-700 text-gray-300'"
                            >
                                {{ statusConfig[event.status]?.label ?? event.status }}
                            </span>
                        </div>
                        <p class="text-gray-400 text-sm mt-1">{{ event.city }}<span v-if="event.venue"> · {{ event.venue }}</span></p>
                        <p class="text-yellow-400 text-xs mt-2 font-medium">{{ formatDateRange(event.start_date, event.end_date) }}</p>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 divide-x divide-gray-100 border-b border-gray-100">
                        <div class="px-4 py-3 text-center">
                            <p class="text-xl font-bold text-gray-900">{{ event.days_count }}</p>
                            <p class="text-xs text-gray-500">Days</p>
                        </div>
                        <div class="px-4 py-3 text-center">
                            <p class="text-xl font-bold text-gray-900">{{ event.total_shows }}</p>
                            <p class="text-xs text-gray-500">Shows</p>
                        </div>
                        <div class="px-4 py-3 text-center">
                            <p class="text-xl font-bold" :class="event.assigned_designers === event.total_shows && event.total_shows > 0 ? 'text-green-600' : 'text-yellow-600'">
                                {{ event.assigned_designers }}/{{ event.total_shows }}
                            </p>
                            <p class="text-xs text-gray-500">Designers</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 p-4">
                        <Link
                            :href="`/admin/operations/events/${event.id}`"
                            class="flex-1 text-center py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors"
                        >
                            View
                        </Link>
                        <Link
                            :href="`/admin/operations/events/${event.id}/edit`"
                            class="flex-1 text-center py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors"
                        >
                            Edit
                        </Link>
                        <button
                            @click="openDuplicate(event)"
                            class="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                            title="Duplicate event"
                        >
                            <DocumentDuplicateIcon class="w-4 h-4 text-gray-500" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="text-center py-20">
                <CalendarDaysIcon class="mx-auto h-16 w-16 text-gray-300 mb-4" />
                <h3 class="text-lg font-semibold text-gray-900 mb-1">No events</h3>
                <p class="text-gray-500 text-sm mb-6">Create the first event to get started</p>
                <Link href="/admin/operations/events/create" class="px-5 py-2 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800">
                    Create Event
                </Link>
            </div>

            <!-- Pagination -->
            <div v-if="events.last_page > 1" class="flex justify-center gap-2 mt-8">
                <Link
                    v-for="link in events.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    v-html="link.label"
                    class="px-3 py-1.5 rounded-lg text-sm border transition-colors"
                    :class="link.active ? 'bg-black text-white border-black' : 'border-gray-300 hover:bg-gray-50'"
                />
            </div>
        </div>

        <!-- Duplicate Modal -->
        <div v-if="duplicateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl">
                <h3 class="text-lg font-bold mb-4">Duplicate Event</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">New event name</label>
                        <input v-model="duplicateForm.name" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/20" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Start date</label>
                            <input v-model="duplicateForm.start_date" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/20" />
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">End date</label>
                            <input v-model="duplicateForm.end_date" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/20" />
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button @click="duplicateModal = false" class="flex-1 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Cancel</button>
                    <button @click="submitDuplicate" class="flex-1 py-2 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800">Duplicate</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
