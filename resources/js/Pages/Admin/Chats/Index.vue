<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    conversations: Object,
    events:        Array,
    filters:       Object,
});

const search = ref(props.filters.search ?? '');
const event  = ref(props.filters.event  ?? '');

let timer = null;
function applyFilters() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/admin/operations/chats', {
            search: search.value || undefined,
            event:  event.value  || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}

watch([search, event], applyFilters);

function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}

function timeAgo(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    const now  = new Date();
    const diff = Math.floor((now - date) / 1000);
    if (diff < 60)    return 'Hace un momento';
    if (diff < 3600)  return `Hace ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `Hace ${Math.floor(diff / 3600)} h`;
    return date.toLocaleDateString('es', { day: 'numeric', month: 'short' });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Chats</h2>
        </template>

        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-2xl font-bold text-gray-900">Chats</h3>
                <p class="text-gray-500 text-sm mt-1">{{ conversations.total }} conversaciones <span class="text-gray-400">· Solo lectura — moderación</span></p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-5 flex flex-wrap gap-3">
            <input v-model="search" type="text" placeholder="Buscar por nombre de modelo o disenador..."
                class="flex-1 min-w-48 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
            <select v-model="event"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                <option value="">Todos los eventos</option>
                <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
        </div>

        <!-- Tabla -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-5 py-3 font-medium text-gray-500">Modelo</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Disenador</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Show</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Ultimo mensaje</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Mensajes</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="conversations.data.length === 0">
                        <td colspan="6" class="text-center text-gray-400 py-12">No hay conversaciones aun.</td>
                    </tr>
                    <tr v-for="c in conversations.data" :key="c.id" class="hover:bg-gray-50 transition-colors">
                        <!-- Modelo -->
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-100 flex-shrink-0">
                                    <img v-if="storageUrl(c.model?.profile_picture)"
                                        :src="storageUrl(c.model.profile_picture)" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-xs font-bold text-gray-500">
                                        {{ c.model?.first_name?.[0] }}{{ c.model?.last_name?.[0] }}
                                    </div>
                                </div>
                                <span class="text-sm font-medium text-gray-800">{{ c.model?.first_name }} {{ c.model?.last_name }}</span>
                            </div>
                        </td>
                        <!-- Disenador -->
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-100 flex-shrink-0">
                                    <img v-if="storageUrl(c.designer?.profile_picture)"
                                        :src="storageUrl(c.designer.profile_picture)" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-xs font-bold text-gray-500">
                                        {{ c.designer?.first_name?.[0] }}{{ c.designer?.last_name?.[0] }}
                                    </div>
                                </div>
                                <span class="text-sm font-medium text-gray-800">{{ c.designer?.first_name }} {{ c.designer?.last_name }}</span>
                            </div>
                        </td>
                        <!-- Show -->
                        <td class="px-4 py-3">
                            <p class="text-sm text-gray-700">{{ c.show?.name }}</p>
                            <p class="text-xs text-gray-400">{{ c.show?.event_day?.event?.name }}</p>
                        </td>
                        <!-- Ultimo mensaje -->
                        <td class="px-4 py-3">
                            <p v-if="c.last_message" class="text-sm text-gray-600 truncate max-w-48">{{ c.last_message.body }}</p>
                            <p v-else class="text-xs text-gray-400 italic">Sin mensajes</p>
                            <p v-if="c.last_message_at" class="text-xs text-gray-400 mt-0.5">{{ timeAgo(c.last_message_at) }}</p>
                        </td>
                        <!-- Mensajes count -->
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-medium">
                                {{ c.messages_count }}
                            </span>
                        </td>
                        <!-- Acciones -->
                        <td class="px-4 py-3">
                            <Link :href="`/admin/operations/chats/${c.id}`"
                                class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                Ver
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Paginacion -->
            <div v-if="conversations.last_page > 1" class="border-t border-gray-100 px-5 py-3 flex items-center justify-between text-sm text-gray-500">
                <span>{{ conversations.from }}-{{ conversations.to }} de {{ conversations.total }}</span>
                <div class="flex gap-1">
                    <Link v-if="conversations.prev_page_url" :href="conversations.prev_page_url"
                        class="px-3 py-1 border border-gray-200 rounded-lg hover:bg-gray-50">Anterior</Link>
                    <Link v-if="conversations.next_page_url" :href="conversations.next_page_url"
                        class="px-3 py-1 border border-gray-200 rounded-lg hover:bg-gray-50">Siguiente</Link>
                </div>
            </div>
        </div>

        <p class="mt-3 text-xs text-gray-400 text-right">{{ conversations.total }} conversaciones en total</p>
    </AdminLayout>
</template>
