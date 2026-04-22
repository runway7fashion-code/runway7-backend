<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { PencilSquareIcon, TrashIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ categories: Array });

const createForm = useForm({ name: '', is_active: true });
function createCategory() {
    createForm.post('/admin/sponsorship/categories', { preserveScroll: true, onSuccess: () => createForm.reset('name') });
}

const editingId = ref(null);
const editForm = useForm({ name: '', is_active: true });
function startEdit(cat) {
    editingId.value = cat.id;
    editForm.name = cat.name;
    editForm.is_active = cat.is_active;
}
function saveEdit(cat) {
    editForm.put(`/admin/sponsorship/categories/${cat.id}`, { preserveScroll: true, onSuccess: () => { editingId.value = null; } });
}
function cancelEdit() { editingId.value = null; }

const deleteModal = ref(null);
function confirmDelete(cat) { deleteModal.value = cat; }
function deleteCategory() {
    useForm({}).delete(`/admin/sponsorship/categories/${deleteModal.value.id}`, { preserveScroll: true, onSuccess: () => { deleteModal.value = null; } });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Sponsorship Categories</h2>
        </template>

        <div class="max-w-3xl mx-auto space-y-6">
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37] mb-4">New category</h3>
                <form @submit.prevent="createCategory" class="flex gap-3 items-end">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input v-model="createForm.name" type="text" placeholder="Ej: Fintech"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        <p v-if="createForm.errors.name" class="text-xs text-red-500 mt-1">{{ createForm.errors.name }}</p>
                    </div>
                    <button type="submit" :disabled="createForm.processing || !createForm.name"
                        class="px-5 py-2.5 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40 flex items-center gap-1.5">
                        <PlusIcon class="w-4 h-4" /> Create
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">{{ categories.length }} categories</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    <div v-for="cat in categories" :key="cat.id" class="px-6 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors">
                        <div v-if="editingId !== cat.id" class="flex items-center gap-3 flex-1">
                            <span class="text-sm font-medium text-gray-900">{{ cat.name }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                :class="cat.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                                {{ cat.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div v-if="editingId !== cat.id" class="flex items-center gap-2">
                            <button @click="startEdit(cat)" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600">
                                <PencilSquareIcon class="w-4 h-4" />
                            </button>
                            <button @click="confirmDelete(cat)" class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500">
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </div>

                        <div v-if="editingId === cat.id" class="flex-1 flex items-center gap-3">
                            <input v-model="editForm.name" type="text" class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-black" />
                            <label class="flex items-center gap-1.5 text-sm">
                                <input v-model="editForm.is_active" type="checkbox" class="rounded"> Active
                            </label>
                            <button @click="cancelEdit" class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs font-medium hover:bg-gray-50">Cancel</button>
                            <button @click="saveEdit(cat)" class="px-3 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800">Save</button>
                        </div>
                    </div>
                    <div v-if="!categories.length" class="px-6 py-8 text-center text-gray-400 text-sm">
                        No categories yet.
                    </div>
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
                        <button @click="deleteCategory" class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Delete</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
