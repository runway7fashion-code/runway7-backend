<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { PencilSquareIcon, TrashIcon, PlusIcon, UsersIcon, CurrencyDollarIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ packages: Array });

const deleteModal = ref(null);
function confirmDelete(p) { deleteModal.value = p; }
function deletePackage() {
    useForm({}).delete(`/admin/sponsorship/packages/${deleteModal.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { deleteModal.value = null; },
    });
}

function formatPrice(v) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(v) || 0);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Packages</h2>
        </template>

        <div class="max-w-6xl mx-auto space-y-4">
            <div class="flex justify-end">
                <Link href="/admin/sponsorship/packages/create"
                    class="px-4 py-2.5 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 flex items-center gap-1.5">
                    <PlusIcon class="w-4 h-4" /> New Package
                </Link>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="p in packages" :key="p.id"
                    class="bg-white rounded-2xl border border-gray-200 p-5 flex flex-col">
                    <div class="flex items-start justify-between mb-2">
                        <h3 class="font-semibold text-gray-900">{{ p.name }}</h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                            :class="p.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                            {{ p.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <p v-if="p.description" class="text-sm text-gray-500 mb-3 line-clamp-2">{{ p.description }}</p>

                    <div class="flex items-center gap-4 text-sm text-gray-600 mb-3">
                        <span class="flex items-center gap-1">
                            <CurrencyDollarIcon class="w-4 h-4 text-[#D4AF37]" />
                            <span class="font-semibold text-gray-900">{{ formatPrice(p.price) }}</span>
                        </span>
                        <span class="flex items-center gap-1">
                            <UsersIcon class="w-4 h-4" />
                            {{ p.assistants_count }} guests
                        </span>
                    </div>

                    <div v-if="p.benefits?.length" class="border-t border-gray-100 pt-3 mt-auto">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Benefits</p>
                        <div class="flex flex-wrap gap-1">
                            <span v-for="b in p.benefits" :key="b.id"
                                class="text-xs px-2 py-0.5 bg-gray-100 text-gray-700 rounded">{{ b.name }}</span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-4 pt-3 border-t border-gray-100">
                        <Link :href="`/admin/sponsorship/packages/${p.id}/edit`" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600">
                            <PencilSquareIcon class="w-4 h-4" />
                        </Link>
                        <button @click="confirmDelete(p)" class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500">
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <div v-if="!packages.length" class="col-span-full bg-white rounded-2xl border border-gray-200 py-12 text-center text-gray-400 text-sm">
                    No packages yet. Create the first one.
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="deleteModal = null"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <TrashIcon class="w-6 h-6 text-red-500" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Delete "{{ deleteModal.name }}"?</h3>
                    <p class="text-sm text-gray-500 mb-5">This action cannot be undone.</p>
                    <div class="flex gap-3">
                        <button @click="deleteModal = null" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancel</button>
                        <button @click="deletePackage" class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Delete</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
