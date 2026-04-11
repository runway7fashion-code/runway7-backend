<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';
import { BellAlertIcon } from '@heroicons/vue/24/outline';
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Communications · Notifications</h2>
        </template>

        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-gray-900">Send Push Notifications</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Send push notifications to users with the mobile app installed.</p>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <Link href="/admin/communications/email" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 font-medium hover:bg-gray-200">Email</Link>
                    <Link href="/admin/communications/sms" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 font-medium hover:bg-gray-200">SMS</Link>
                    <Link href="/admin/communications/notifications" class="px-3 py-1.5 rounded-lg bg-black text-white font-medium">Notifications</Link>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                <BellAlertIcon class="w-12 h-12 mx-auto text-gray-300 mb-3" />
                <p class="text-gray-500 text-sm">Push notifications are coming soon.</p>
            </div>
        </div>
    </AdminLayout>
</template>
