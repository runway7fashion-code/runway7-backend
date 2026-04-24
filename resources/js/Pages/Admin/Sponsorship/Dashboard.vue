<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { StarIcon, DocumentCheckIcon, UserPlusIcon, TagIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    stats: Object,
    topCategories: Array,
    filters: Object,
    events: Array,
    statuses: Object,
    isLider: Boolean,
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const eventId = ref(props.filters.event_id || '');

watch([from, to, eventId], () => {
    router.get('/admin/sponsorship/dashboard', {
        from: from.value,
        to: to.value,
        event_id: eventId.value || undefined,
    }, { preserveState: true, replace: true });
});

const maxCategoryContracts = computed(() =>
    (props.topCategories || []).reduce((m, c) => Math.max(m, c.contracts_count), 0) || 1
);

function formatPrice(v) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(v) || 0);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Sponsorship Dashboard</h2>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Filters -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
                    <input v-model="from" type="date" class="px-3 py-2 border border-gray-300 rounded-lg text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
                    <input v-model="to" type="date" class="px-3 py-2 border border-gray-300 rounded-lg text-sm" />
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Event</label>
                    <select v-model="eventId" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                        <option value="">All events</option>
                        <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                </div>
            </div>

            <!-- KPI cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs uppercase tracking-wider text-gray-400">Total leads</p>
                        <UserPlusIcon class="w-5 h-5 text-gray-300" />
                    </div>
                    <p class="text-3xl font-bold text-gray-900">{{ stats.leadsTotal }}</p>
                    <p class="text-xs text-gray-500 mt-1">+{{ stats.leadsInRange }} in range</p>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs uppercase tracking-wider text-gray-400">Sponsors</p>
                        <StarIcon class="w-5 h-5 text-[#D4AF37]" />
                    </div>
                    <p class="text-3xl font-bold text-gray-900">{{ stats.sponsorsTotal }}</p>
                    <p class="text-xs text-gray-500 mt-1">total users with role sponsor</p>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs uppercase tracking-wider text-gray-400">Contracts in range</p>
                        <DocumentCheckIcon class="w-5 h-5 text-green-500" />
                    </div>
                    <p class="text-3xl font-bold text-gray-900">{{ stats.contractsInRange }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ stats.contractsTotal }} total</p>
                </div>
            </div>

            <!-- Leads by status -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Leads by status</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
                    <div v-for="(meta, key) in statuses" :key="key" class="bg-gray-50 rounded-xl p-3">
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: meta.color }"></span>
                            <span class="text-xs font-medium text-gray-600">{{ meta.label }}</span>
                        </div>
                        <p class="text-2xl font-bold text-gray-900">{{ stats.leadsByStatus[key] || 0 }}</p>
                    </div>
                </div>
            </div>

            <!-- Top 3 categories -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex items-center gap-2 mb-4">
                    <TagIcon class="w-5 h-5 text-[#D4AF37]" />
                    <h3 class="font-semibold text-gray-900">Top 3 categories (contracts closed)</h3>
                </div>
                <div v-if="topCategories?.length" class="space-y-3">
                    <div v-for="(c, i) in topCategories" :key="c.category_id" class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0"
                            :class="[
                                i === 0 ? 'bg-[#D4AF37] text-black' :
                                i === 1 ? 'bg-gray-300 text-gray-800' :
                                'bg-orange-300 text-orange-900'
                            ]">
                            {{ i + 1 }}
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-sm font-medium text-gray-900">{{ c.name }}</p>
                                <p class="text-sm text-gray-500">
                                    <span class="font-semibold text-gray-900">{{ c.contracts_count }}</span> contracts ·
                                    <span class="font-semibold text-[#D4AF37]">{{ formatPrice(c.total_revenue) }}</span>
                                </p>
                            </div>
                            <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-[#D4AF37] rounded-full" :style="{ width: `${(c.contracts_count / maxCategoryContracts) * 100}%` }"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400">No contracts in this range yet.</p>
            </div>
        </div>
    </AdminLayout>
</template>
