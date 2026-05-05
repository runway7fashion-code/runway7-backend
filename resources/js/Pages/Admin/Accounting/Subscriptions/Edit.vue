<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import SubscriptionForm from './SubscriptionForm.vue';

const props = defineProps({
    subscription: Object,
    options: Object,
    paymentMethods: Array,
});

const form = useForm({
    name: props.subscription.name,
    vendor: props.subscription.vendor ?? '',
    description: props.subscription.description ?? '',
    account_email: props.subscription.account_email ?? '',
    department: props.subscription.department,
    category: props.subscription.category,
    billing_cycle: props.subscription.billing_cycle,
    amount: props.subscription.amount,
    payment_method_id: props.subscription.payment_method?.id ?? '',
    purchase_date: props.subscription.purchase_date ?? '',
    next_renewal_date: props.subscription.next_renewal_date ?? '',
    auto_renew: props.subscription.auto_renew,
    status: props.subscription.status,
    plan_tier: props.subscription.plan_tier ?? '',
    seats: props.subscription.seats ?? '',
    website_url: props.subscription.website_url ?? '',
    notes: props.subscription.notes ?? '',
    cancellation_reason: props.subscription.cancellation_reason ?? '',
});

function submit() {
    form.put(`/admin/accounting/subscriptions/${props.subscription.id}`);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Edit Subscription</h2>
        </template>

        <div class="max-w-4xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Edit {{ subscription.name }}</h3>
                </div>
                <Link :href="`/admin/accounting/subscriptions/${subscription.id}`"
                    class="text-sm text-gray-500 hover:text-gray-700">← Back to detail</Link>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <SubscriptionForm :form="form" :options="options" :paymentMethods="paymentMethods" :is-edit="true" />

                <div class="flex justify-end gap-3">
                    <Link :href="`/admin/accounting/subscriptions/${subscription.id}`"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Cancel</Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-6 py-2 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 transition-colors disabled:opacity-50">
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
