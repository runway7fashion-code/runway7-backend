<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { MagnifyingGlassIcon, PlusIcon, ArrowDownTrayIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    registrations: Object,
    totalCount: Number,
    events: Array,
    packages: Array,
    salesReps: Array,
    isLeader: Boolean,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const event = ref(props.filters?.event ?? '');
const pkg = ref(props.filters?.package ?? '');
const salesRep = ref(props.filters?.sales_rep ?? '');
const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo = ref(props.filters?.date_to ?? '');

let debounceTimer;
function applyFilters() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/admin/sales/designers', {
            search: search.value || undefined,
            status: status.value || undefined,
            event: event.value || undefined,
            package: pkg.value || undefined,
            sales_rep: salesRep.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}

watch([search, status, event, pkg, salesRep, dateFrom, dateTo], applyFilters);

function exportCsv() {
    const params = new URLSearchParams();
    if (search.value) params.set('search', search.value);
    if (status.value) params.set('status', status.value);
    if (event.value) params.set('event', event.value);
    if (pkg.value) params.set('package', pkg.value);
    if (salesRep.value) params.set('sales_rep', salesRep.value);
    if (dateFrom.value) params.set('date_from', dateFrom.value);
    if (dateTo.value) params.set('date_to', dateTo.value);
    window.location.href = `/admin/sales/designers/export?${params.toString()}`;
}

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
        registered: 'Registered',
        onboarded:  'Onboarded',
        confirmed:  'Confirmed',
        cancelled:  'Cancelled',
    }[s] ?? s;
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Designer Registrations</h2>
        </template>

        <div>
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Designer Registrations</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ totalCount }} registrations</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="exportCsv" class="inline-flex items-center gap-1.5 px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        <ArrowDownTrayIcon class="h-4 w-4" /> Export
                    </button>
                    <Link href="/admin/sales/designers/create" class="inline-flex items-center gap-1.5 px-4 py-2 bg-black text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors">
                        <PlusIcon class="h-4 w-4" /> Register Designer
                    </Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-6">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                        <div class="relative">
                            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input v-model="search" type="text" placeholder="Name, email, brand..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-black/10 focus:outline-none" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <select v-model="status" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-black/10 focus:outline-none">
                            <option value="">All statuses</option>
                            <option value="registered">Registered</option>
                            <option value="onboarded">Onboarded</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Event</label>
                        <select v-model="event" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-black/10 focus:outline-none">
                            <option value="">All events</option>
                            <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Package</label>
                        <select v-model="pkg" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-black/10 focus:outline-none">
                            <option value="">All packages</option>
                            <option v-for="p in packages" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div v-if="isLeader">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Sales Rep</label>
                        <select v-model="salesRep" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-black/10 focus:outline-none">
                            <option value="">All reps</option>
                            <option v-for="r in salesReps" :key="r.id" :value="r.id">{{ r.first_name }} {{ r.last_name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
                        <input v-model="dateFrom" type="date" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-black/10 focus:outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
                        <input v-model="dateTo" type="date" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-black/10 focus:outline-none" />
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div v-if="!registrations.data.length" class="p-12 text-center text-gray-400">
                    No registrations found.
                </div>
                <table v-else class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-widest">
                        <tr>
                            <th class="px-4 py-3 text-left">Designer / Brand</th>
                            <th class="px-4 py-3 text-left">Event</th>
                            <th class="px-4 py-3 text-left">Package</th>
                            <th class="px-4 py-3 text-right">Price</th>
                            <th class="px-4 py-3 text-right">Down Payment</th>
                            <th class="px-4 py-3 text-left">Sales Rep</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Docs</th>
                            <th class="px-4 py-3 text-left">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="r in registrations.data" :key="r.id" class="hover:bg-gray-50 cursor-pointer" @click="router.visit(`/admin/sales/designers/${r.id}`)">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ r.designer?.first_name }} {{ r.designer?.last_name }}</p>
                                <p class="text-xs text-gray-500">{{ r.designer?.designer_profile?.brand_name ?? '-' }}</p>
                            </td>
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
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ new Date(r.created_at).toLocaleDateString('en-US') }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="registrations.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <p class="text-xs text-gray-500">Showing {{ registrations.from }}-{{ registrations.to }} of {{ registrations.total }}</p>
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
