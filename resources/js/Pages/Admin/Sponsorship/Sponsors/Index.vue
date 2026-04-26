<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { EyeIcon, EnvelopeIcon, MagnifyingGlassIcon, CheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    sponsors: Object,
    totalCount: Number,
    filters: Object,
});

const search     = ref(props.filters?.search     ?? '');
const status     = ref(props.filters?.status     ?? '');
const onboarding = ref(props.filters?.onboarding ?? '');
const dateFrom   = ref(props.filters?.date_from  ?? '');
const dateTo     = ref(props.filters?.date_to    ?? '');

let debounceTimer;
function applyFilters() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/admin/sponsorship/sponsors', {
            search:     search.value     || undefined,
            status:     status.value     || undefined,
            onboarding: onboarding.value || undefined,
            date_from:  dateFrom.value   || undefined,
            date_to:    dateTo.value     || undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
}

watch([search, status, onboarding, dateFrom, dateTo], applyFilters);

function sendOnboarding(sponsor) {
    if (!confirm(`Send onboarding email to ${sponsor.first_name} ${sponsor.last_name}?`)) return;
    useForm({}).post(`/admin/sponsorship/sponsors/${sponsor.id}/send-onboarding`, { preserveScroll: true });
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' });
}
function formatTime(d) {
    if (!d) return '';
    return new Date(d).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
}

function statusBadge(s) {
    return {
        active:     'bg-green-100 text-green-700',
        registered: 'bg-yellow-100 text-yellow-700',
        pending:    'bg-yellow-100 text-yellow-700',
        inactive:   'bg-gray-100 text-gray-500',
    }[s] ?? 'bg-gray-100 text-gray-600';
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Sponsors</h2>
        </template>

        <div>
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Sponsors</h3>
                    <p class="text-gray-500 text-sm mt-1">{{ totalCount }} sponsors</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-6">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[220px]">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                        <div class="relative">
                            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input v-model="search" type="text" placeholder="Name, email or company..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-black/10 focus:outline-none" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <select v-model="status" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-black/10 focus:outline-none bg-white">
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="registered">Registered</option>
                            <option value="pending">Pending</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Onboarding</label>
                        <select v-model="onboarding" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-black/10 focus:outline-none bg-white">
                            <option value="">All</option>
                            <option value="sent">Email sent</option>
                            <option value="not_sent">Not sent</option>
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
                <div v-if="!sponsors.data.length" class="p-12 text-center text-gray-400 text-sm">
                    No sponsors yet. Convert a lead to create the first one.
                </div>
                <table v-else class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-widest">
                        <tr>
                            <th class="px-4 py-3 text-left">Sponsor</th>
                            <th class="px-4 py-3 text-left">Company</th>
                            <th class="px-4 py-3 text-left">Email</th>
                            <th class="px-4 py-3 text-center">Contracts</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Onboarded</th>
                            <th class="px-4 py-3 text-left">Created</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="s in sponsors.data" :key="s.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ s.first_name }} {{ s.last_name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ s.sponsor_profile?.company_name || '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 text-xs">{{ s.email }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center justify-center min-w-[28px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-xs font-medium">
                                    {{ s.registrations_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="statusBadge(s.status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize">
                                    {{ s.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <span v-if="s.welcome_email_sent_at" class="inline-flex items-center gap-1 text-green-600">
                                    <CheckIcon class="w-3 h-3" /> {{ formatDate(s.welcome_email_sent_at) }}
                                </span>
                                <span v-else class="text-gray-400">Not sent</span>
                            </td>
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                <p class="text-gray-700">{{ formatDate(s.created_at) }}</p>
                                <p class="text-gray-400">{{ formatTime(s.created_at) }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex gap-1">
                                    <button @click="sendOnboarding(s)"
                                        :title="s.welcome_email_sent_at ? 'Re-send onboarding' : 'Send onboarding'"
                                        class="p-1.5 rounded-lg hover:bg-yellow-50 text-gray-400 hover:text-[#D4AF37]">
                                        <EnvelopeIcon class="w-4 h-4" />
                                    </button>
                                    <Link :href="`/admin/sponsorship/sponsors/${s.id}`" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600">
                                        <EyeIcon class="w-4 h-4" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="sponsors.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <p class="text-xs text-gray-500">Showing {{ sponsors.from }}-{{ sponsors.to }} of {{ sponsors.total }}</p>
                    <div class="flex gap-1">
                        <Link v-for="link in sponsors.links" :key="link.label"
                            :href="link.url || ''"
                            class="px-3 py-1 text-xs rounded-lg border transition-colors"
                            :class="link.active ? 'bg-black text-white border-black' : link.url ? 'border-gray-300 text-gray-600 hover:bg-gray-50' : 'border-gray-200 text-gray-300 pointer-events-none'"
                            v-html="link.label"
                            preserve-state />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
