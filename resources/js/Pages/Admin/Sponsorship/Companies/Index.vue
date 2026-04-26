<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { PencilSquareIcon, PlusIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    companies: Object,
    totalCount: Number,
    industries: Array,
    countries: Array,
    filters: Object,
});

const search   = ref(props.filters?.search   ?? '');
const industry = ref(props.filters?.industry ?? '');
const country  = ref(props.filters?.country  ?? '');
const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo   = ref(props.filters?.date_to   ?? '');

let debounceTimer;
function applyFilters() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/admin/sponsorship/companies', {
            search:    search.value   || undefined,
            industry:  industry.value || undefined,
            country:   country.value  || undefined,
            date_from: dateFrom.value || undefined,
            date_to:   dateTo.value   || undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
}

watch([search, industry, country, dateFrom, dateTo], applyFilters);

const showCreate = ref(false);
const createForm = useForm({ name: '' });
function submitCreate() {
    createForm.post('/admin/sponsorship/companies', {
        preserveScroll: true,
        onSuccess: () => { showCreate.value = false; createForm.reset(); },
    });
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' });
}
function formatTime(d) {
    if (!d) return '';
    return new Date(d).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Companies</h2>
        </template>

        <div>
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Companies</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ totalCount }} companies</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="showCreate = true"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-black text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors">
                        <PlusIcon class="h-4 w-4" /> New Company
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-6">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                        <div class="relative">
                            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input v-model="search" type="text" placeholder="Name, industry or country..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-black/10 focus:outline-none" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Industry</label>
                        <select v-model="industry" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-black/10 focus:outline-none bg-white">
                            <option value="">All industries</option>
                            <option v-for="i in industries" :key="i" :value="i">{{ i }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Country</label>
                        <select v-model="country" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-black/10 focus:outline-none bg-white">
                            <option value="">All countries</option>
                            <option v-for="c in countries" :key="c" :value="c">{{ c }}</option>
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
                <div v-if="!companies.data.length" class="p-12 text-center text-gray-400">
                    No companies found.
                </div>
                <table v-else class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-widest">
                        <tr>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Industry</th>
                            <th class="px-4 py-3 text-left">Country</th>
                            <th class="px-4 py-3 text-left">Website</th>
                            <th class="px-4 py-3 text-center">Leads</th>
                            <th class="px-4 py-3 text-left">Created by</th>
                            <th class="px-4 py-3 text-left">Created</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="c in companies.data" :key="c.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ c.name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ c.industry || '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ c.country || '—' }}</td>
                            <td class="px-4 py-3">
                                <a v-if="c.website" :href="c.website" target="_blank" class="text-blue-600 hover:underline truncate block max-w-xs">{{ c.website }}</a>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center justify-center min-w-[28px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-xs font-medium">
                                    {{ c.leads_count ?? 0 }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs">
                                {{ c.creator ? `${c.creator.first_name} ${c.creator.last_name}` : '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                <p class="text-gray-700">{{ formatDate(c.created_at) }}</p>
                                <p class="text-gray-400">{{ formatTime(c.created_at) }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`/admin/sponsorship/companies/${c.id}/edit`" class="inline-flex p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600">
                                    <PencilSquareIcon class="w-4 h-4" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="companies.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <p class="text-xs text-gray-500">Showing {{ companies.from }}-{{ companies.to }} of {{ companies.total }}</p>
                    <div class="flex gap-1">
                        <Link v-for="link in companies.links" :key="link.label"
                            :href="link.url || ''"
                            class="px-3 py-1 text-xs rounded-lg border transition-colors"
                            :class="link.active ? 'bg-black text-white border-black' : link.url ? 'border-gray-300 text-gray-600 hover:bg-gray-50' : 'border-gray-200 text-gray-300 pointer-events-none'"
                            v-html="link.label"
                            preserve-state />
                    </div>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showCreate = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">New Company</h3>
                    <form @submit.prevent="submitCreate" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                            <input v-model="createForm.name" type="text" autofocus
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="createForm.errors.name" class="text-xs text-red-500 mt-1">{{ createForm.errors.name }}</p>
                            <p class="text-xs text-gray-500 mt-1">Only the name is required now. The rest of the details can be completed later.</p>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="showCreate = false" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancel</button>
                            <button type="submit" :disabled="createForm.processing || !createForm.name"
                                class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40">
                                Create
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
