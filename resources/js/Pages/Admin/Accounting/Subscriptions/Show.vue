<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { TrashIcon, PencilSquareIcon, PaperClipIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    subscription: Object,
    paymentMethods: Array,
});

const showPaymentModal = ref(false);
const paymentForm = useForm({
    amount: props.subscription.amount,
    paid_at: new Date().toISOString().slice(0, 10),
    period_start: '',
    period_end: '',
    payment_method_id: props.subscription.payment_method?.id ?? '',
    invoice_url: '',
    notes: '',
    receipt: null,
    advance_renewal: true,
});

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

function openPaymentModal() {
    paymentForm.reset();
    paymentForm.amount = props.subscription.amount;
    paymentForm.paid_at = new Date().toISOString().slice(0, 10);
    paymentForm.payment_method_id = props.subscription.payment_method?.id ?? '';
    paymentForm.advance_renewal = true;
    showPaymentModal.value = true;
}

function submitPayment() {
    paymentForm.post(`/admin/accounting/subscriptions/${props.subscription.id}/payments`, {
        forceFormData: true,
        onSuccess: () => { showPaymentModal.value = false; },
    });
}

function deletePayment(payment) {
    if (!confirm(`Delete this payment of ${fmtMoney(payment.amount)}?`)) return;
    router.delete(`/admin/accounting/subscriptions/${props.subscription.id}/payments/${payment.id}`, {
        preserveScroll: true,
    });
}

function deleteSubscription() {
    if (!confirm(`Delete subscription "${props.subscription.name}"? This cannot be undone.`)) return;
    router.delete(`/admin/accounting/subscriptions/${props.subscription.id}`);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">{{ subscription.name }}</h2>
        </template>

        <div class="max-w-5xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <div class="flex items-center gap-3">
                        <h3 class="text-2xl font-bold text-gray-900">{{ subscription.name }}</h3>
                        <span class="text-[11px] font-bold uppercase px-2 py-0.5 rounded-full" :class="statusBadge(subscription.status)">
                            {{ labelize(subscription.status) }}
                        </span>
                    </div>
                    <p v-if="subscription.vendor" class="text-gray-500 text-sm mt-1">{{ subscription.vendor }}</p>
                </div>
                <div class="flex gap-2">
                    <Link href="/admin/accounting/subscriptions" class="text-sm text-gray-500 hover:text-gray-700 self-center mr-2">← Back</Link>
                    <Link :href="`/admin/accounting/subscriptions/${subscription.id}/edit`"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 inline-flex items-center gap-1.5">
                        <PencilSquareIcon class="w-4 h-4" /> Edit
                    </Link>
                    <button @click="deleteSubscription"
                        class="px-3 py-2 border border-red-200 text-red-600 rounded-lg text-sm hover:bg-red-50 inline-flex items-center gap-1.5">
                        <TrashIcon class="w-4 h-4" /> Delete
                    </button>
                </div>
            </div>

            <!-- Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-medium">{{ labelize(subscription.billing_cycle) }} Cost</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ fmtMoney(subscription.amount) }}</p>
                    <p class="text-xs text-gray-500 mt-1">≈ {{ fmtMoney(subscription.monthly_equivalent) }} / month</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-medium">Annual Cost</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ fmtMoney(subscription.annual_equivalent) }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-medium">Total Paid</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ fmtMoney(subscription.totals.paid_total) }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ subscription.totals.payments_count }} payment(s)</p>
                </div>
            </div>

            <!-- Details -->
            <div class="bg-white border border-gray-200 rounded-2xl p-5 mb-6">
                <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">Details</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-gray-400 uppercase">Department</p>
                        <p class="text-gray-900 font-medium">{{ labelize(subscription.department) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase">Category</p>
                        <p class="text-gray-900 font-medium">{{ labelize(subscription.category) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase">Account</p>
                        <p class="text-gray-900 font-medium">{{ subscription.account_email ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase">Plan</p>
                        <p class="text-gray-900 font-medium">{{ subscription.plan_tier ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase">Seats</p>
                        <p class="text-gray-900 font-medium">{{ subscription.seats ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase">Auto-renew</p>
                        <p class="text-gray-900 font-medium">{{ subscription.auto_renew ? 'Yes' : 'No' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase">Purchase Date</p>
                        <p class="text-gray-900 font-medium">{{ subscription.purchase_date ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase">Next Renewal</p>
                        <p class="text-gray-900 font-medium">{{ subscription.next_renewal_date ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase">Card</p>
                        <p class="text-gray-900 font-medium">
                            <span v-if="subscription.payment_method">{{ subscription.payment_method.masked }} <span class="text-gray-400 text-xs">({{ subscription.payment_method.nickname }})</span></span>
                            <span v-else>—</span>
                        </p>
                    </div>
                    <div v-if="subscription.website_url" class="md:col-span-3">
                        <p class="text-xs text-gray-400 uppercase">Website</p>
                        <a :href="subscription.website_url" target="_blank" class="text-blue-600 hover:underline text-sm">{{ subscription.website_url }}</a>
                    </div>
                    <div v-if="subscription.description" class="md:col-span-3">
                        <p class="text-xs text-gray-400 uppercase">Description</p>
                        <p class="text-gray-900">{{ subscription.description }}</p>
                    </div>
                    <div v-if="subscription.notes" class="md:col-span-3">
                        <p class="text-xs text-gray-400 uppercase">Notes</p>
                        <p class="text-gray-900 whitespace-pre-line">{{ subscription.notes }}</p>
                    </div>
                    <div v-if="subscription.cancelled_at" class="md:col-span-3 bg-red-50 rounded-lg p-3">
                        <p class="text-xs text-red-600 uppercase font-bold">Cancelled at {{ subscription.cancelled_at }}</p>
                        <p v-if="subscription.cancellation_reason" class="text-red-900 text-sm mt-1">{{ subscription.cancellation_reason }}</p>
                    </div>
                </div>
            </div>

            <!-- Payments -->
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Payment History</h4>
                    <button @click="openPaymentModal"
                        class="px-3 py-1.5 bg-black text-white text-xs font-semibold rounded-lg hover:bg-gray-800">
                        + Register Payment
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-3">Paid At</th>
                                <th class="text-right px-4 py-3">Amount</th>
                                <th class="text-left px-4 py-3">Period</th>
                                <th class="text-left px-4 py-3">Card</th>
                                <th class="text-left px-4 py-3">Receipt</th>
                                <th class="text-left px-4 py-3">Notes</th>
                                <th class="text-right px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="subscription.payments.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center text-gray-400">No payments registered yet.</td>
                            </tr>
                            <tr v-for="p in subscription.payments" :key="p.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-700">{{ p.paid_at }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-900">{{ fmtMoney(p.amount) }}</td>
                                <td class="px-4 py-3 text-gray-600 text-xs">
                                    <span v-if="p.period_start && p.period_end">{{ p.period_start }} → {{ p.period_end }}</span>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    <span v-if="p.payment_method">{{ p.payment_method.masked }}</span>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <a v-if="p.receipt_url" :href="p.receipt_url" target="_blank"
                                        class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                                        <PaperClipIcon class="w-3 h-3" /> Receipt
                                    </a>
                                    <a v-else-if="p.invoice_url" :href="p.invoice_url" target="_blank"
                                        class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                                        <PaperClipIcon class="w-3 h-3" /> Invoice
                                    </a>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 text-xs max-w-xs truncate">{{ p.notes ?? '' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button @click="deletePayment(p)"
                                        class="text-xs text-red-500 hover:text-red-700">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Register Payment Modal -->
        <Teleport to="body">
            <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showPaymentModal = false">
                <div class="bg-white rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6 mx-4">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-bold text-gray-900">Register Payment</h3>
                        <button @click="showPaymentModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                    </div>

                    <form @submit.prevent="submitPayment" class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Amount (USD) *</label>
                                <input v-model="paymentForm.amount" type="number" step="0.01" min="0.01"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Paid At *</label>
                                <input v-model="paymentForm.paid_at" type="date"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Period Start</label>
                                <input v-model="paymentForm.period_start" type="date"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Period End</label>
                                <input v-model="paymentForm.period_end" type="date"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Card</label>
                            <select v-model="paymentForm.payment_method_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="">— Use subscription default —</option>
                                <option v-for="m in paymentMethods" :key="m.id" :value="m.id">
                                    {{ m.nickname }} ({{ m.masked }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Invoice URL</label>
                            <input v-model="paymentForm.invoice_url" type="url" placeholder="https://..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Receipt (PDF or image)</label>
                            <input type="file" accept=".pdf,image/*" @change="paymentForm.receipt = $event.target.files[0]"
                                class="text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-black file:text-white file:text-xs file:font-medium hover:file:bg-gray-800 file:cursor-pointer" />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Notes</label>
                            <textarea v-model="paymentForm.notes" rows="2"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
                        </div>

                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="paymentForm.advance_renewal" type="checkbox"
                                class="rounded border-gray-300 text-black focus:ring-black/20" />
                            <span class="text-sm text-gray-700">Advance next renewal date by one cycle</span>
                        </label>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showPaymentModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Cancel</button>
                            <button type="submit" :disabled="paymentForm.processing"
                                class="px-6 py-2 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-50">
                                {{ paymentForm.processing ? 'Saving...' : 'Save Payment' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
