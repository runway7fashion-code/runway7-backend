<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, reactive, watch } from 'vue';
import { PhotoIcon } from '@heroicons/vue/24/outline';

const failedImgs = reactive({});

const props = defineProps({
    cards: Array,
    events: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const role = ref(props.filters?.role || '');
const action_type = ref(props.filters?.action_type || '');

let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});
watch([status, role, action_type], () => applyFilters());

function applyFilters() {
    router.get('/admin/operations/home-cards', {
        search: search.value || undefined,
        status: status.value || undefined,
        role: role.value || undefined,
        action_type: action_type.value || undefined,
    }, { preserveState: true, replace: true });
}

function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}

function roleLabel(r) {
    return {
        model: 'Model', designer: 'Designer', media: 'Media', volunteer: 'Volunteer',
        staff: 'Staff', attendee: 'Attendee', vip: 'VIP', influencer: 'Influencer',
        press: 'Press', sponsor: 'Sponsor', complementary: 'Complementary',
    }[r] ?? r;
}

function actionTypeLabel(t) {
    return { url: 'URL', video: 'Video', mailto: 'Email' }[t] ?? t;
}

function actionTypeBadgeClass(t) {
    return {
        url: 'bg-blue-100 text-blue-700',
        video: 'bg-purple-100 text-purple-700',
        mailto: 'bg-green-100 text-green-700',
    }[t] ?? 'bg-gray-100 text-gray-700';
}

const availableRoles = [
    'model', 'designer', 'media', 'volunteer', 'staff',
    'attendee', 'vip', 'influencer', 'press', 'sponsor', 'complementary',
];

function deleteCard(card) {
    if (!confirm(`Delete home card "${card.title}"?`)) return;
    router.delete(`/admin/operations/home-cards/${card.id}`);
}

function moveUp(card, index) {
    if (index === 0) return;
    const prev = props.cards[index - 1];
    router.post('/admin/operations/home-cards/reorder', {
        order: [
            { id: card.id, order: prev.order },
            { id: prev.id, order: card.order },
        ],
    }, { preserveScroll: true });
}

function moveDown(card, index) {
    if (index >= props.cards.length - 1) return;
    const next = props.cards[index + 1];
    router.post('/admin/operations/home-cards/reorder', {
        order: [
            { id: card.id, order: next.order },
            { id: next.id, order: card.order },
        ],
    }, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Home Cards</h2>
        </template>

        <div>
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Home Cards</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ cards.length }} cards registered - These cards appear on the app's home screen below the banner carousel</p>
                </div>
                <Link href="/admin/operations/home-cards/create" class="px-4 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors">
                    + Create Card
                </Link>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3 mb-6">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by title..."
                    class="flex-1 min-w-48 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400"
                />
                <select v-model="status" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select v-model="action_type" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">All types</option>
                    <option value="url">URL</option>
                    <option value="video">Video</option>
                    <option value="mailto">Email</option>
                </select>
                <select v-model="role" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">All roles</option>
                    <option v-for="r in availableRoles" :key="r" :value="r">{{ roleLabel(r) }}</option>
                </select>
            </div>

            <!-- Empty state -->
            <div v-if="cards.length === 0" class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
                <p class="text-gray-400">No home cards found with the applied filters.</p>
            </div>

            <!-- Grid -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                <div v-for="(card, i) in cards" :key="card.id"
                    class="bg-white rounded-2xl border border-gray-200 overflow-hidden group transition-shadow hover:shadow-lg"
                    :class="card.status === 'inactive' ? 'opacity-60' : ''">
                    <!-- Image preview -->
                    <div class="aspect-[4/3] bg-gray-50 relative overflow-hidden">
                        <img v-if="storageUrl(card.image_url) && !failedImgs[card.id]"
                            :src="storageUrl(card.image_url)"
                            @error="failedImgs[card.id] = true"
                            class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex flex-col items-center justify-center gap-2 text-gray-300 border-2 border-dashed border-gray-200 rounded-t-2xl">
                            <PhotoIcon class="w-10 h-10" />
                            <span class="text-xs text-gray-400">No image</span>
                        </div>

                        <!-- Title overlay (like the app) -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent flex items-end p-3">
                            <span class="text-white font-bold text-sm uppercase tracking-wide">{{ card.title }}</span>
                        </div>

                        <!-- Status badge -->
                        <span class="absolute top-2 left-2 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full"
                            :class="card.status === 'active' ? 'bg-green-500 text-white' : 'bg-gray-500 text-white'">
                            {{ card.status === 'active' ? 'Active' : 'Inactive' }}
                        </span>

                        <!-- Order badge -->
                        <span class="absolute top-2 right-2 bg-black/70 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                            #{{ card.order }}
                        </span>
                    </div>

                    <!-- Info -->
                    <div class="p-4">
                        <!-- Action type badge -->
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full" :class="actionTypeBadgeClass(card.action_type)">
                                {{ actionTypeLabel(card.action_type) }}
                            </span>
                        </div>

                        <!-- Action value -->
                        <p class="text-xs text-gray-500 truncate mb-2" :title="card.action_value">
                            {{ card.action_value }}
                        </p>

                        <!-- Roles target -->
                        <div class="flex flex-wrap gap-1 mb-2">
                            <template v-if="card.target_roles?.length">
                                <span v-for="r in card.target_roles" :key="r"
                                    class="text-[10px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">
                                    {{ roleLabel(r) }}
                                </span>
                            </template>
                            <span v-else class="text-[10px] bg-[#D4AF37]/10 text-[#D4AF37] px-1.5 py-0.5 rounded font-medium">
                                All roles
                            </span>
                        </div>

                        <!-- Event -->
                        <p class="text-xs text-gray-400 mb-3">
                            {{ card.event ? card.event.name : 'All events' }}
                        </p>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <button v-if="i > 0" type="button" @click="moveUp(card, i)"
                                class="p-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-400 hover:text-gray-600 transition-colors text-xs">
                                &uarr;
                            </button>
                            <button v-if="i < cards.length - 1" type="button" @click="moveDown(card, i)"
                                class="p-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-400 hover:text-gray-600 transition-colors text-xs">
                                &darr;
                            </button>
                            <div class="flex-1"></div>
                            <Link :href="`/admin/operations/home-cards/${card.id}/edit`"
                                class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                Edit
                            </Link>
                            <button type="button" @click="deleteCard(card)"
                                class="text-xs px-3 py-1.5 border border-red-200 text-red-500 rounded-lg hover:bg-red-50 transition-colors">
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
