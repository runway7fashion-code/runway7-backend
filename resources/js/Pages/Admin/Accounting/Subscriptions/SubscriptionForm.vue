<script setup>
const props = defineProps({
    form: Object,
    options: Object,
    paymentMethods: Array,
    isEdit: { type: Boolean, default: false },
});

function labelize(value) {
    if (!value) return '';
    return value.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}
</script>

<template>
    <div class="space-y-6">
        <!-- Service info -->
        <div class="bg-white border border-gray-200 rounded-2xl p-5 space-y-4">
            <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Service</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                    <input v-model="form.name" type="text" placeholder="e.g. Hostinger Business"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vendor</label>
                    <input v-model="form.vendor" type="text" placeholder="e.g. Hostinger International, Inc."
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea v-model="form.description" rows="2" placeholder="What this subscription is used for"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Email</label>
                    <input v-model="form.account_email" type="email" placeholder="account@runway7.com"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Website URL</label>
                    <input v-model="form.website_url" type="url" placeholder="https://..."
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department *</label>
                    <select v-model="form.department"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                        <option v-for="d in options.departments" :key="d" :value="d">{{ labelize(d) }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                    <select v-model="form.category"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                        <option v-for="c in options.categories" :key="c" :value="c">{{ labelize(c) }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Billing -->
        <div class="bg-white border border-gray-200 rounded-2xl p-5 space-y-4">
            <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Billing</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Billing Cycle *</label>
                    <select v-model="form.billing_cycle"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                        <option v-for="b in options.billing_cycles" :key="b" :value="b">{{ labelize(b) }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount (USD) *</label>
                    <input v-model="form.amount" type="number" step="0.01" min="0" placeholder="0.00"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    <p v-if="form.errors.amount" class="text-xs text-red-500 mt-1">{{ form.errors.amount }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Card</label>
                    <select v-model="form.payment_method_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                        <option value="">— None —</option>
                        <option v-for="m in paymentMethods" :key="m.id" :value="m.id">
                            {{ m.nickname }} ({{ m.masked }})
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Purchase Date</label>
                    <input v-model="form.purchase_date" type="date"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Next Renewal</label>
                    <input v-model="form.next_renewal_date" type="date"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm" />
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="form.auto_renew" type="checkbox"
                            class="rounded border-gray-300 text-black focus:ring-black/20" />
                        <span class="text-sm text-gray-700">Auto-renew enabled</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Plan -->
        <div class="bg-white border border-gray-200 rounded-2xl p-5 space-y-4">
            <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Plan & Status</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Plan Tier</label>
                    <input v-model="form.plan_tier" type="text" placeholder="e.g. Pro, Business 5 seats"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Seats / Users</label>
                    <input v-model="form.seats" type="number" min="0" placeholder="1"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                    <select v-model="form.status"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                        <option v-for="s in options.statuses" :key="s" :value="s">{{ labelize(s) }}</option>
                    </select>
                </div>
                <div v-if="form.status === 'cancelled'" class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cancellation Reason</label>
                    <textarea v-model="form.cancellation_reason" rows="2"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm"></textarea>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea v-model="form.notes" rows="3" placeholder="Internal notes about this subscription"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm"></textarea>
                </div>
            </div>
        </div>
    </div>
</template>
