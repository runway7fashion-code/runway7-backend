<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { EyeIcon, PencilSquareIcon, MagnifyingGlassIcon, PlusIcon, StarIcon } from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';

const props = defineProps({
    leads: Object,
    counts: Object,
    statuses: Object,
    sources: Array,
    filters: Object,
    advisors: Array,
    categories: Array,
    events: Array,
    tags: Array,
    isLider: Boolean,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const assignedTo = ref(props.filters?.assigned_to || '');
const categoryId = ref(props.filters?.category_id || '');
const eventId = ref(props.filters?.event_id || '');
const tagId = ref(props.filters?.tag_id || '');
const source = ref(props.filters?.source || '');
const emailSend = ref(props.filters?.email_send || '');

let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});

watch([status, assignedTo, categoryId, eventId, tagId, source, emailSend], () => applyFilters());

function applyFilters() {
    router.get('/admin/sponsorship/leads', {
        search: search.value || undefined,
        status: status.value || undefined,
        assigned_to: assignedTo.value || undefined,
        category_id: categoryId.value || undefined,
        event_id: eventId.value || undefined,
        tag_id: tagId.value || undefined,
        source: source.value || undefined,
        email_send: emailSend.value || undefined,
    }, { preserveState: true, replace: true });
}

function setStatus(s) {
    status.value = status.value === s ? '' : s;
}

const totalCount = computed(() => Object.values(props.counts || {}).reduce((a, b) => a + b, 0));

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Sponsorship Leads</h2>
        </template>

        <div class="max-w-7xl mx-auto space-y-4">
            <!-- Top bar -->
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">{{ totalCount }} leads total</p>
                <Link href="/admin/sponsorship/leads/create"
                    class="px-4 py-2.5 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 flex items-center gap-1.5">
                    <PlusIcon class="w-4 h-4" /> New Lead
                </Link>
            </div>

            <!-- Cards por estado -->
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2">
                <button v-for="(meta, key) in statuses" :key="key" @click="setStatus(key)"
                    class="text-left px-3 py-2.5 rounded-xl border text-xs transition-all"
                    :class="status === key ? 'ring-2 ring-offset-1 ring-black border-transparent' : 'bg-white border-gray-200 hover:border-gray-300'">
                    <div class="flex items-center gap-1.5 mb-1">
                        <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: meta.color }"></span>
                        <span class="font-medium text-gray-700">{{ meta.label }}</span>
                    </div>
                    <p class="text-xl font-bold text-gray-900">{{ counts[key] || 0 }}</p>
                </button>
            </div>

            <!-- Filtros -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
                <div class="relative">
                    <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" />
                    <input v-model="search" type="text" placeholder="Buscar por nombre, email, teléfono o empresa..."
                        class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2">
                    <select v-if="isLider" v-model="assignedTo" class="input-sm">
                        <option value="">All advisors</option>
                        <option v-for="a in advisors" :key="a.id" :value="a.id">
                            {{ a.first_name }} {{ a.last_name }} {{ a.sponsorship_type === 'lider' ? '(L)' : '' }}
                        </option>
                    </select>
                    <select v-model="eventId" class="input-sm">
                        <option value="">All events</option>
                        <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                    <select v-model="categoryId" class="input-sm">
                        <option value="">All categories</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <select v-model="tagId" class="input-sm">
                        <option value="">All tags</option>
                        <option v-for="t in tags" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                    <select v-model="source" class="input-sm">
                        <option value="">All sources</option>
                        <option v-for="s in sources" :key="s" :value="s">{{ s }}</option>
                    </select>
                    <select v-model="emailSend" class="input-sm">
                        <option value="">Email status</option>
                        <option value="none">No email sent</option>
                        <option value="sent">Email sent</option>
                        <option value="failed">Email failed</option>
                    </select>
                </div>
            </div>

            <!-- Tabla -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Company</th>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Assigned to</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Tags</th>
                            <th class="px-4 py-3 font-medium">Last email</th>
                            <th class="px-4 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="l in leads.data" :key="l.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <StarSolid v-if="l.is_contract_winner" class="w-4 h-4 text-[#D4AF37]" title="Contract winner" />
                                    <div>
                                        <p class="font-medium text-gray-900">{{ l.first_name }} {{ l.last_name }}</p>
                                        <p v-if="l.charge" class="text-xs text-gray-500">{{ l.charge }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ l.company?.name || '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 text-xs">{{ l.primary_email?.email || '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ l.assigned_to ? `${l.assigned_to.first_name} ${l.assigned_to.last_name}` : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium text-white"
                                    :style="{ backgroundColor: statuses[l.status]?.color }">
                                    {{ statuses[l.status]?.label || l.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="t in l.tags" :key="t.id"
                                        class="text-xs px-1.5 py-0.5 rounded text-white"
                                        :style="{ backgroundColor: t.color }">{{ t.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">
                                <div v-if="l.last_email_sent_at">
                                    <p>{{ formatDate(l.last_email_sent_at) }}</p>
                                    <span class="inline-block px-1.5 py-0.5 rounded text-xs"
                                        :class="l.last_email_status === 'sent' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                                        {{ l.last_email_status }}
                                    </span>
                                </div>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex gap-1">
                                    <Link :href="`/admin/sponsorship/leads/${l.id}`" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600">
                                        <EyeIcon class="w-4 h-4" />
                                    </Link>
                                    <Link :href="`/admin/sponsorship/leads/${l.id}/edit`" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600">
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!leads.data.length">
                            <td colspan="8" class="px-6 py-12 text-center text-gray-400 text-sm">
                                No leads found with current filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="leads.last_page > 1" class="flex justify-center gap-1">
                <Link v-for="link in leads.links" :key="link.label" :href="link.url ?? '#'"
                    class="px-3 py-1.5 text-sm rounded-lg border"
                    :class="[
                        link.active ? 'bg-black text-white border-black' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50',
                        !link.url ? 'opacity-40 pointer-events-none' : ''
                    ]"
                    v-html="link.label"></Link>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
@reference "tailwindcss";
.input-sm { @apply w-full border border-gray-300 rounded-lg px-2.5 py-2 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-black/10; }
</style>
