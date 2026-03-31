<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, reactive, watch } from 'vue';
import { PhotoIcon } from '@heroicons/vue/24/outline';

const failedImgs = reactive({});

const props = defineProps({
    banners: Array,
    events: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const role = ref(props.filters?.role || '');
const event_id = ref(props.filters?.event_id || '');

let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});
watch([status, role, event_id], () => applyFilters());

function applyFilters() {
    router.get('/admin/operations/banners', {
        search: search.value || undefined,
        status: status.value || undefined,
        role: role.value || undefined,
        event_id: event_id.value || undefined,
    }, { preserveState: true, replace: true });
}

function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}

function roleLabel(r) {
    return {
        model: 'Modelo', designer: 'Diseñador', media: 'Media', volunteer: 'Voluntario',
        staff: 'Staff', attendee: 'Asistente', vip: 'VIP', influencer: 'Influencer',
        press: 'Prensa', sponsor: 'Sponsor', complementary: 'Complementario',
    }[r] ?? r;
}

const availableRoles = [
    'model', 'designer', 'media', 'volunteer', 'staff',
    'attendee', 'vip', 'influencer', 'press', 'sponsor', 'complementary',
];

function deleteBanner(banner) {
    if (!confirm(`Eliminar banner "${banner.title}"?`)) return;
    router.delete(`/admin/operations/banners/${banner.id}`);
}

function moveUp(banner, index) {
    if (index === 0) return;
    const prev = props.banners[index - 1];
    router.post('/admin/operations/banners/reorder', {
        order: [
            { id: banner.id, order: prev.order },
            { id: prev.id, order: banner.order },
        ],
    }, { preserveScroll: true });
}

function moveDown(banner, index) {
    if (index >= props.banners.length - 1) return;
    const next = props.banners[index + 1];
    router.post('/admin/operations/banners/reorder', {
        order: [
            { id: banner.id, order: next.order },
            { id: next.id, order: banner.order },
        ],
    }, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Banners</h2>
        </template>

        <div>
            <!-- Header: título + botón -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Banners</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ banners.length }} banners registrados</p>
                </div>
                <Link href="/admin/operations/banners/create" class="px-4 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors">
                    + Crear Banner
                </Link>
            </div>

            <!-- Filtros -->
            <div class="flex flex-wrap gap-3 mb-6">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Buscar por título..."
                    class="flex-1 min-w-48 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400"
                />
                <select v-model="status" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todos los estados</option>
                    <option value="active">Activos</option>
                    <option value="inactive">Inactivos</option>
                </select>
                <select v-model="role" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todos los roles</option>
                    <option v-for="r in availableRoles" :key="r" :value="r">{{ roleLabel(r) }}</option>
                </select>
                <select v-model="event_id" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 bg-white">
                    <option value="">Todos los eventos</option>
                    <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                </select>
            </div>

            <!-- Grid de banners -->
            <div v-if="banners.length === 0" class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
                <p class="text-gray-400">No se encontraron banners con los filtros aplicados.</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div v-for="(banner, i) in banners" :key="banner.id"
                    class="bg-white rounded-2xl border border-gray-200 overflow-hidden group transition-shadow hover:shadow-lg"
                    :class="banner.status === 'inactive' ? 'opacity-60' : ''">
                    <!-- Imagen preview -->
                    <div class="aspect-[16/9] bg-gray-50 relative overflow-hidden">
                        <img v-if="storageUrl(banner.image_url) && !failedImgs[banner.id]"
                            :src="storageUrl(banner.image_url)"
                            @error="failedImgs[banner.id] = true"
                            class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex flex-col items-center justify-center gap-2 text-gray-300 border-2 border-dashed border-gray-200 rounded-t-2xl">
                            <PhotoIcon class="w-10 h-10" />
                            <span class="text-xs text-gray-400">Sin imagen</span>
                        </div>

                        <!-- Status badge -->
                        <span class="absolute top-2 left-2 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full"
                            :class="banner.status === 'active' ? 'bg-green-500 text-white' : 'bg-gray-500 text-white'">
                            {{ banner.status === 'active' ? 'Activo' : 'Inactivo' }}
                        </span>

                        <!-- Orden -->
                        <span class="absolute top-2 right-2 bg-black/70 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                            #{{ banner.order }}
                        </span>
                    </div>

                    <!-- Info -->
                    <div class="p-4">
                        <h4 class="font-semibold text-gray-900 text-sm mb-1">{{ banner.title }}</h4>

                        <!-- Roles target -->
                        <div class="flex flex-wrap gap-1 mb-2">
                            <template v-if="banner.target_roles?.length">
                                <span v-for="r in banner.target_roles" :key="r"
                                    class="text-[10px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">
                                    {{ roleLabel(r) }}
                                </span>
                            </template>
                            <span v-else class="text-[10px] bg-[#D4AF37]/10 text-[#D4AF37] px-1.5 py-0.5 rounded font-medium">
                                Todos los roles
                            </span>
                        </div>

                        <!-- Evento -->
                        <p class="text-xs text-gray-400 mb-2">
                            {{ banner.event ? banner.event.name : 'Todos los eventos' }}
                        </p>

                        <!-- Fechas -->
                        <p v-if="banner.starts_at || banner.ends_at" class="text-xs text-gray-400 mb-3">
                            {{ banner.starts_at ? new Date(banner.starts_at).toLocaleDateString('es') : 'Siempre' }}
                            &rarr;
                            {{ banner.ends_at ? new Date(banner.ends_at).toLocaleDateString('es') : 'Indefinido' }}
                        </p>

                        <!-- Acciones -->
                        <div class="flex items-center gap-2">
                            <button v-if="i > 0" type="button" @click="moveUp(banner, i)"
                                class="p-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-400 hover:text-gray-600 transition-colors text-xs">
                                &uarr;
                            </button>
                            <button v-if="i < banners.length - 1" type="button" @click="moveDown(banner, i)"
                                class="p-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-400 hover:text-gray-600 transition-colors text-xs">
                                &darr;
                            </button>
                            <div class="flex-1"></div>
                            <Link :href="`/admin/operations/banners/${banner.id}/edit`"
                                class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                Editar
                            </Link>
                            <button type="button" @click="deleteBanner(banner)"
                                class="text-xs px-3 py-1.5 border border-red-200 text-red-500 rounded-lg hover:bg-red-50 transition-colors">
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
