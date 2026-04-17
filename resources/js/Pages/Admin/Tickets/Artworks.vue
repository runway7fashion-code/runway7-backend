<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { MagnifyingGlassIcon, PhotoIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    designers: Array,
    events: Array,
    eventId: Number,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const selectedEvent = ref(props.eventId || '');

let timer;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => applyFilters(), 300);
});
watch(selectedEvent, () => applyFilters());

function applyFilters() {
    router.get('/admin/tickets/artworks', {
        event_id: selectedEvent.value || undefined,
        search: search.value || undefined,
    }, { preserveState: true, replace: true });
}

function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}

const statusColors = {
    pending: 'bg-gray-100 text-gray-600',
    completed: 'bg-green-100 text-green-700',
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Artworks Management</h2>
        </template>

        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Designer Artworks</h3>
                    <p class="text-gray-500 text-sm mt-1">Upload photos and videos for designers' social media use.</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <div class="relative flex-1 min-w-48">
                    <MagnifyingGlassIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input v-model="search" type="text" placeholder="Search designer or brand..."
                        class="w-full border border-gray-200 rounded-lg pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                </div>
                <select v-model="selectedEvent" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                    <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
            </div>

            <!-- Designer cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link v-for="d in designers" :key="d.id"
                    :href="`/admin/tickets/artworks/${d.id}/${eventId}`"
                    class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-100 flex-shrink-0">
                            <img v-if="storageUrl(d.profile_picture)" :src="storageUrl(d.profile_picture)" class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center text-xs font-bold text-gray-500">
                                {{ d.first_name?.[0] }}{{ d.last_name?.[0] }}
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ d.brand_name || `${d.first_name} ${d.last_name}` }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ d.first_name }} {{ d.last_name }}</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <PhotoIcon class="w-4 h-4 text-gray-400" />
                            <span class="text-xs text-gray-600">{{ d.files_count }} files</span>
                        </div>
                        <span v-if="d.material" class="px-2 py-0.5 rounded-full text-xs font-medium"
                            :class="statusColors[d.material.status] || 'bg-gray-100 text-gray-500'">
                            {{ d.material.status === 'completed' ? 'Uploaded' : 'Pending' }}
                        </span>
                    </div>
                </Link>
            </div>

            <p v-if="!designers?.length" class="text-center text-gray-400 text-sm py-12">No designers found for this event.</p>
        </div>
    </AdminLayout>
</template>
