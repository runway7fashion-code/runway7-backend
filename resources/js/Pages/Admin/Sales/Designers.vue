<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    registrations: Object,
    events: Array,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const event = ref(props.filters?.event ?? '');

let debounceTimer;
function applyFilters() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/admin/sales/designers', {
            search: search.value || undefined,
            status: status.value || undefined,
            event: event.value || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}

watch([search, status, event], applyFilters);

function statusBadge(s) {
    return {
        registered: 'bg-blue-100 text-blue-700',
        onboarded:  'bg-purple-100 text-purple-700',
        confirmed:  'bg-green-100 text-green-700',
        cancelled:  'bg-red-100 text-red-700',
    }[s] ?? 'bg-gray-100 text-gray-600';
}

function statusLabel(s) {
    return {
        registered: 'Registrado',
        onboarded:  'Onboarded',
        confirmed:  'Confirmado',
        cancelled:  'Cancelado',
    }[s] ?? s;
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Registros de Diseñadores</h2>
        </template>

        <div>
            <!-- Toolbar -->
            <div class="flex flex-wrap items-center gap-3 mb-6">
                <div class="relative flex-1 min-w-[200px]">
                    <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <input v-model="search" type="text" placeholder="Buscar por nombre, email, marca..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400" />
                </div>
                <select v-model="status" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-yellow-400">
                    <option value="">Todos los estados</option>
                    <option value="registered">Registrado</option>
                    <option value="onboarded">Onboarded</option>
                    <option value="confirmed">Confirmado</option>
                    <option value="cancelled">Cancelado</option>
                </select>
                <select v-model="event" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-yellow-400">
                    <option value="">Todos los eventos</option>
                    <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
                <Link href="/admin/sales/designers/create" class="inline-flex items-center gap-2 px-4 py-2 bg-black text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors">
                    <PlusIcon class="h-4 w-4" />
                    Registrar Diseñador
                </Link>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div v-if="!registrations.data.length" class="p-12 text-center text-gray-400">
                    No se encontraron registros.
                </div>
                <table v-else class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-widest">
                        <tr>
                            <th class="px-4 py-3 text-left">Diseñador</th>
                            <th class="px-4 py-3 text-left">Marca</th>
                            <th class="px-4 py-3 text-left">Evento</th>
                            <th class="px-4 py-3 text-left">Paquete</th>
                            <th class="px-4 py-3 text-right">Precio</th>
                            <th class="px-4 py-3 text-right">Inicial</th>
                            <th class="px-4 py-3 text-left">Vendedor</th>
                            <th class="px-4 py-3 text-left">Estado</th>
                            <th class="px-4 py-3 text-left">Docs</th>
                            <th class="px-4 py-3 text-left">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="r in registrations.data" :key="r.id" class="hover:bg-gray-50 cursor-pointer" @click="router.visit(`/admin/sales/designers/${r.id}`)">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                {{ r.designer?.first_name }} {{ r.designer?.last_name }}
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ r.designer?.designer_profile?.brand_name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ r.event?.name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ r.package?.name ?? '-' }}</td>
                            <td class="px-4 py-3 text-right text-gray-900 font-medium">${{ Number(r.agreed_price).toLocaleString() }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ r.downpayment ? `$${Number(r.downpayment).toLocaleString()}` : '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ r.sales_rep?.first_name }} {{ r.sales_rep?.last_name }}</td>
                            <td class="px-4 py-3">
                                <span :class="statusBadge(r.status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                                    {{ statusLabel(r.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ r.documents?.length ?? 0 }}</td>
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ new Date(r.created_at).toLocaleDateString('es-US') }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="registrations.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <p class="text-xs text-gray-500">Mostrando {{ registrations.from }}-{{ registrations.to }} de {{ registrations.total }}</p>
                    <div class="flex gap-1">
                        <Link v-for="link in registrations.links" :key="link.label"
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
    </AdminLayout>
</template>
