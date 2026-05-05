<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    totals: Object,
    byDepartment: Object,
    byCategory: Object,
    upcoming: Array,
});

function fmtMoney(n) {
    return '$' + Number(n ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function labelize(value) {
    if (!value) return '';
    return value.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}
function renewalBadge(d) {
    if (d === null || d === undefined) return 'bg-gray-100 text-gray-500';
    if (d < 0) return 'bg-red-100 text-red-700';
    if (d <= 7) return 'bg-orange-100 text-orange-700';
    if (d <= 30) return 'bg-yellow-100 text-yellow-700';
    return 'bg-gray-100 text-gray-600';
}
function renewalText(d) {
    if (d === null || d === undefined) return '—';
    if (d < 0) return `${Math.abs(d)}d overdue`;
    if (d === 0) return 'today';
    return `in ${d}d`;
}

const departments = computed(() => {
    return Object.entries(props.byDepartment ?? {}).map(([key, val]) => ({ key, ...val }))
        .sort((a, b) => b.monthly - a.monthly);
});
const categories = computed(() => {
    return Object.entries(props.byCategory ?? {}).map(([key, val]) => ({ key, ...val }))
        .sort((a, b) => b.monthly - a.monthly);
});

const maxDeptMonthly = computed(() => Math.max(...departments.value.map(d => d.monthly), 1));
const maxCatMonthly = computed(() => Math.max(...categories.value.map(c => c.monthly), 1));
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Subscriptions Dashboard</h2>
        </template>

        <div>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Subscriptions Dashboard</h3>
                    <p class="text-gray-500 text-sm mt-1">Overview of all company recurring expenses</p>
                </div>
                <div class="flex gap-2">
                    <Link href="/admin/accounting/subscriptions" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">All Subscriptions</Link>
                    <Link href="/admin/accounting/subscriptions/create" class="px-4 py-2 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800">+ New</Link>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-medium">Monthly Spend</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ fmtMoney(totals.monthly) }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-medium">Annual Spend</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ fmtMoney(totals.annual) }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-medium">Active</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ totals.active_count }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-medium">Total</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ totals.total_count }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">By Department</h4>
                    <div v-if="departments.length === 0" class="text-gray-400 text-sm">No active subscriptions.</div>
                    <div v-else class="space-y-3">
                        <div v-for="d in departments" :key="d.key">
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="font-medium text-gray-700">{{ labelize(d.key) }} <span class="text-xs text-gray-400">({{ d.count }})</span></span>
                                <span class="font-semibold text-gray-900">{{ fmtMoney(d.monthly) }}/mo</span>
                            </div>
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-black" :style="{ width: `${(d.monthly / maxDeptMonthly) * 100}%` }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">By Category</h4>
                    <div v-if="categories.length === 0" class="text-gray-400 text-sm">No active subscriptions.</div>
                    <div v-else class="space-y-3">
                        <div v-for="c in categories" :key="c.key">
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="font-medium text-gray-700">{{ labelize(c.key) }} <span class="text-xs text-gray-400">({{ c.count }})</span></span>
                                <span class="font-semibold text-gray-900">{{ fmtMoney(c.monthly) }}/mo</span>
                            </div>
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-yellow-500" :style="{ width: `${(c.monthly / maxCatMonthly) * 100}%` }"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Renewing in next 30 days</h4>
                    <Link href="/admin/accounting/subscriptions/renewals" class="text-xs text-blue-600 hover:underline">View all renewals →</Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-3">Service</th>
                                <th class="text-left px-4 py-3">Department</th>
                                <th class="text-left px-4 py-3">Renewal</th>
                                <th class="text-left px-4 py-3">In</th>
                                <th class="text-right px-4 py-3">Amount</th>
                                <th class="text-right px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="upcoming.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400">No upcoming renewals.</td>
                            </tr>
                            <tr v-for="sub in upcoming" :key="sub.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ sub.name }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ labelize(sub.department) }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ sub.next_renewal_date }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full" :class="renewalBadge(sub.next_renewal_in_days)">
                                        {{ renewalText(sub.next_renewal_in_days) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-900">{{ fmtMoney(sub.amount) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="`/admin/accounting/subscriptions/${sub.id}`"
                                        class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50">View</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
