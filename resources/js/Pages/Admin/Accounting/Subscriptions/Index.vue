<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    subscriptions: Object,
    totals: Object,
    filters: Object,
    options: Object,
});

const filters = ref({
    search: props.filters?.search ?? '',
    department: props.filters?.department ?? '',
    category: props.filters?.category ?? '',
    billing_cycle: props.filters?.billing_cycle ?? '',
    status: props.filters?.status ?? '',
});

let debounceTimer = null;
function applyFilters() {
    const params = {};
    Object.entries(filters.value).forEach(([k, v]) => { if (v) params[k] = v; });
    router.get('/admin/accounting/subscriptions', params, { preserveState: true, replace: true });
}

watch(() => filters.value.search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 400);
});

function clearFilters() {
    filters.value = { search: '', department: '', category: '', billing_cycle: '', status: '' };
    applyFilters();
}

const hasActiveFilters = computed(() => Object.values(filters.value).some(v => v));

function fmtMoney(n) {
    return '$' + Number(n ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function labelize(value) {
    if (!value) return '';
    return value.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function statusBadge(status) {
    return {
        active: 'bg-green-100 text-green-700',
        paused: 'bg-yellow-100 text-yellow-700',
        cancelled: 'bg-red-100 text-red-700',
        trial: 'bg-blue-100 text-blue-700',
    }[status] ?? 'bg-gray-100 text-gray-700';
}

function renewalBadge(days) {
    if (days === null || days === undefined) return 'bg-gray-100 text-gray-500';
    if (days < 0) return 'bg-red-100 text-red-700';
    if (days <= 7) return 'bg-orange-100 text-orange-700';
    if (days <= 30) return 'bg-yellow-100 text-yellow-700';
    return 'bg-gray-100 text-gray-600';
}

function renewalText(days) {
    if (days === null || days === undefined) return '—';
    if (days < 0) return `${Math.abs(days)}d overdue`;
    if (days === 0) return 'today';
    return `in ${days}d`;
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Company Subscriptions</h2>
        </template>

        <div>
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Subscriptions</h3>
                    <p class="text-gray-500 text-sm mt-1">Track all recurring company subscriptions across departments</p>
                </div>
                <Link href="/admin/accounting/subscriptions/create"
                    class="px-4 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors">
                    + New Subscription
                </Link>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-medium">Monthly</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ fmtMoney(totals.monthly) }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-medium">Annual</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ fmtMoney(totals.annual) }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-medium">Active</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ totals.active_count }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-medium">Total</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ totals.count }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white border border-gray-200 rounded-2xl p-4 mb-4">
                <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
                    <div class="md:col-span-2">
                        <input v-model="filters.search" type="text" placeholder="Search by name, vendor or email..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    </div>
                    <select v-model="filters.department" @change="applyFilters"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">All departments</option>
                        <option v-for="d in options.departments" :key="d" :value="d">{{ labelize(d) }}</option>
                    </select>
                    <select v-model="filters.category" @change="applyFilters"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">All categories</option>
                        <option v-for="c in options.categories" :key="c" :value="c">{{ labelize(c) }}</option>
                    </select>
                    <select v-model="filters.billing_cycle" @change="applyFilters"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">All cycles</option>
                        <option v-for="b in options.billing_cycles" :key="b" :value="b">{{ labelize(b) }}</option>
                    </select>
                    <select v-model="filters.status" @change="applyFilters"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">All statuses</option>
                        <option v-for="s in options.statuses" :key="s" :value="s">{{ labelize(s) }}</option>
                    </select>
                </div>
                <div v-if="hasActiveFilters" class="mt-3 flex justify-end">
                    <button @click="clearFilters" class="text-xs text-gray-500 hover:text-gray-700 underline">Clear filters</button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-3">Service</th>
                                <th class="text-left px-4 py-3">Department</th>
                                <th class="text-left px-4 py-3">Category</th>
                                <th class="text-left px-4 py-3">Cycle</th>
                                <th class="text-right px-4 py-3">Amount</th>
                                <th class="text-left px-4 py-3">Card</th>
                                <th class="text-left px-4 py-3">Renewal</th>
                                <th class="text-left px-4 py-3">Status</th>
                                <th class="text-right px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="subscriptions.data.length === 0">
                                <td colspan="9" class="px-4 py-12 text-center text-gray-400">No subscriptions found.</td>
                            </tr>
                            <tr v-for="sub in subscriptions.data" :key="sub.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-900">{{ sub.name }}</div>
                                    <div v-if="sub.vendor && sub.vendor !== sub.name" class="text-xs text-gray-500">{{ sub.vendor }}</div>
                                    <div v-if="sub.account_email" class="text-xs text-gray-400">{{ sub.account_email }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ labelize(sub.department) }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ labelize(sub.category) }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ labelize(sub.billing_cycle) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-900">{{ fmtMoney(sub.amount) }}</td>
                                <td class="px-4 py-3 text-gray-700">
                                    <span v-if="sub.payment_method">{{ sub.payment_method.masked }}</span>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-700">{{ sub.next_renewal_date ?? '—' }}</div>
                                    <span v-if="sub.next_renewal_in_days !== null" class="inline-block mt-0.5 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full" :class="renewalBadge(sub.next_renewal_in_days)">
                                        {{ renewalText(sub.next_renewal_in_days) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full" :class="statusBadge(sub.status)">
                                        {{ labelize(sub.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="`/admin/accounting/subscriptions/${sub.id}`"
                                        class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50">View</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="subscriptions.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
                    <p class="text-xs text-gray-500">Showing {{ subscriptions.from }} to {{ subscriptions.to }} of {{ subscriptions.total }}</p>
                    <div class="flex gap-1">
                        <Link v-for="link in subscriptions.links" :key="link.label"
                            :href="link.url ?? '#'"
                            v-html="link.label"
                            preserve-state
                            preserve-scroll
                            class="px-3 py-1 text-xs rounded-lg border"
                            :class="link.active ? 'bg-black text-white border-black' : 'border-gray-200 text-gray-600 hover:bg-gray-50'" />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
