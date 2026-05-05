<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import SubscriptionForm from './SubscriptionForm.vue';

const props = defineProps({
    options: Object,
    paymentMethods: Array,
});

const form = useForm({
    name: '',
    vendor: '',
    description: '',
    account_email: '',
    department: 'web',
    category: 'other',
    billing_cycle: 'monthly',
    amount: '',
    payment_method_id: '',
    purchase_date: '',
    next_renewal_date: '',
    auto_renew: true,
    status: 'active',
    plan_tier: '',
    seats: '',
    website_url: '',
    notes: '',
    cancellation_reason: '',
});

function submit() {
    form.post('/admin/accounting/subscriptions');
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">New Subscription</h2>
        </template>

        <div class="max-w-4xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">New Subscription</h3>
                    <p class="text-gray-500 text-sm mt-1">Register a new recurring subscription</p>
                </div>
                <Link href="/admin/accounting/subscriptions" class="text-sm text-gray-500 hover:text-gray-700">← Back</Link>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <SubscriptionForm :form="form" :options="options" :paymentMethods="paymentMethods" />

                <div class="flex justify-end gap-3">
                    <Link href="/admin/accounting/subscriptions"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Cancel</Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-6 py-2 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 transition-colors disabled:opacity-50">
                        {{ form.processing ? 'Saving...' : 'Create Subscription' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
