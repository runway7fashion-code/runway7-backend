<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    subscriptions: Array,
    days: Number,
});

const days = ref(props.days);

watch(days, (val) => {
    router.get('/admin/accounting/subscriptions/renewals', { days: val }, { preserveState: true, replace: true });
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
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Upcoming Renewals</h2>
        </template>

        <div>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Upcoming Renewals</h3>
                    <p class="text-gray-500 text-sm mt-1">Active subscriptions renewing in the next {{ days }} days</p>
                </div>
                <select v-model.number="days" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option :value="7">Next 7 days</option>
                    <option :value="15">Next 15 days</option>
                    <option :value="30">Next 30 days</option>
                    <option :value="60">Next 60 days</option>
                    <option :value="90">Next 90 days</option>
                </select>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-3">Service</th>
                                <th class="text-left px-4 py-3">Department</th>
                                <th class="text-left px-4 py-3">Renewal Date</th>
                                <th class="text-left px-4 py-3">In</th>
                                <th class="text-right px-4 py-3">Amount</th>
                                <th class="text-left px-4 py-3">Card</th>
                                <th class="text-right px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="subscriptions.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center text-gray-400">No upcoming renewals.</td>
                            </tr>
                            <tr v-for="sub in subscriptions" :key="sub.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-900">{{ sub.name }}</div>
                                    <div v-if="sub.plan_tier" class="text-xs text-gray-500">{{ sub.plan_tier }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ labelize(sub.department) }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ sub.next_renewal_date }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full" :class="renewalBadge(sub.next_renewal_in_days)">
                                        {{ renewalText(sub.next_renewal_in_days) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-900">{{ fmtMoney(sub.amount) }}</td>
                                <td class="px-4 py-3 text-gray-700">
                                    <span v-if="sub.payment_method">{{ sub.payment_method.masked }}</span>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
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
