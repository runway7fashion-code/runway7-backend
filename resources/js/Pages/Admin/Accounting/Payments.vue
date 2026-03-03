<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, watch, onMounted } from 'vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    events: Array,
});

const selectedEventId = ref('all');
const search = ref('');
const designers = ref([]);
const loading = ref(false);
const searched = ref(false);

function fetchDesigners() {
    loading.value = true;
    searched.value = true;

    const url = selectedEventId.value === 'all'
        ? `/admin/accounting/api/designers-all-events?search=${encodeURIComponent(search.value)}`
        : `/admin/accounting/api/designers-by-event/${selectedEventId.value}?search=${encodeURIComponent(search.value)}`;

    fetch(url)
        .then(r => r.json())
        .then(data => {
            designers.value = data;
            loading.value = false;
        })
        .catch(() => {
            loading.value = false;
        });
}

watch(selectedEventId, () => {
    search.value = '';
    designers.value = [];
    searched.value = false;
    fetchDesigners();
});

let searchTimeout = null;
function onSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(fetchDesigners, 300);
}

function goToDesigner(designer) {
    const eventId = designer.event_id || selectedEventId.value;
    router.get(`/admin/accounting/payments/designer/${designer.id}/event/${eventId}`);
}

function fmt(n) {
    return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function planStatusLabel(status) {
    return { active: 'Activo', completed: 'Completado', cancelled: 'Cancelado' }[status] ?? status;
}
function planStatusClass(status) {
    return { active: 'bg-blue-50 text-blue-700', completed: 'bg-green-50 text-green-700', cancelled: 'bg-red-50 text-red-600' }[status] ?? 'bg-gray-50 text-gray-600';
}

onMounted(() => {
    fetchDesigners();
});
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Pagos de Disenadores</h2>
        </template>

        <div class="space-y-6">
            <!-- Selector de evento + búsqueda -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="md:w-80">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Evento</label>
                        <select v-model="selectedEventId"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="all">Todos los eventos</option>
                            <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Buscar Disenador</label>
                        <div class="relative">
                            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <input v-model="search" @input="onSearch" type="text" placeholder="Nombre, email o brand..."
                                class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
                <div class="inline-block w-6 h-6 border-2 border-gray-300 border-t-black rounded-full animate-spin"></div>
                <p class="text-sm text-gray-400 mt-2">Buscando...</p>
            </div>

            <!-- Resultados -->
            <div v-else-if="designers.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="(d, idx) in designers" :key="`${d.id}-${d.event_id || idx}`"
                    @click="goToDesigner(d)"
                    class="bg-white border border-gray-200 rounded-xl p-4 hover:border-gray-400 hover:shadow-sm cursor-pointer transition-all">
                    <div class="flex items-center gap-3 mb-3">
                        <div v-if="d.profile_picture"
                            class="w-10 h-10 rounded-full bg-cover bg-center border border-gray-200 flex-shrink-0"
                            :style="`background-image: url('/storage/${d.profile_picture}')`"></div>
                        <div v-else class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-sm font-bold text-gray-500 flex-shrink-0">
                            {{ (d.first_name?.[0] ?? '') + (d.last_name?.[0] ?? '') }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-gray-900 text-sm truncate">{{ d.first_name }} {{ d.last_name }}</p>
                            <p v-if="d.brand_name" class="text-xs text-gray-500 truncate">{{ d.brand_name }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span v-if="d.event_name" class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded truncate max-w-[160px]">{{ d.event_name }}</span>
                        <span v-if="d.package_price" class="text-gray-500">{{ fmt(d.package_price) }}</span>
                        <span v-if="d.has_plan" :class="planStatusClass(d.plan_status)" class="px-2 py-0.5 rounded font-medium">
                            {{ planStatusLabel(d.plan_status) }}
                        </span>
                        <span v-if="d.has_plan && d.plan_progress !== null" class="text-gray-400">{{ d.plan_progress }}%</span>
                        <span v-if="!d.has_plan" class="text-yellow-600 bg-yellow-50 px-2 py-0.5 rounded font-medium">Sin plan</span>
                    </div>
                </div>
            </div>

            <!-- Sin resultados -->
            <div v-else-if="searched && !loading" class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
                <p class="text-sm text-gray-400 italic">No se encontraron disenadores.</p>
            </div>
        </div>
    </AdminLayout>
</template>
