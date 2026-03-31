<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { PencilSquareIcon, TrashIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ tags: Array });

const colors = [
    '#6B7280', '#EF4444', '#F97316', '#EAB308', '#22C55E', '#10B981',
    '#3B82F6', '#6366F1', '#8B5CF6', '#EC4899', '#14B8A6', '#F59E0B',
    '#84CC16', '#06B6D4', '#A855F7', '#D946EF', '#F43F5E', '#0EA5E9',
];

// Create form
const createForm = useForm({ name: '', color: '#3B82F6' });
function createTag() {
    createForm.post('/admin/sales/tags', { preserveScroll: true, onSuccess: () => createForm.reset() });
}

// Edit
const editingTag = ref(null);
const editForm = useForm({ name: '', color: '' });
function startEdit(tag) {
    editingTag.value = tag.id;
    editForm.name = tag.name;
    editForm.color = tag.color;
}
function saveEdit(tag) {
    editForm.put(`/admin/sales/tags/${tag.id}`, { preserveScroll: true, onSuccess: () => { editingTag.value = null; } });
}
function cancelEdit() { editingTag.value = null; }

// Delete
const deleteModal = ref(null);
function confirmDelete(tag) { deleteModal.value = tag; }
function deleteTag() {
    useForm({}).delete(`/admin/sales/tags/${deleteModal.value.id}`, { preserveScroll: true, onSuccess: () => { deleteModal.value = null; } });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Lead Tags</h2>
        </template>

        <div class="max-w-3xl mx-auto space-y-6">
            <!-- Create tag -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37] mb-4">Create new tag</h3>
                <form @submit.prevent="createTag" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input v-model="createForm.name" type="text" placeholder="Ej: Interested / Follow Up"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        <p v-if="createForm.errors.name" class="text-xs text-red-500 mt-1">{{ createForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="c in colors" :key="c" type="button" @click="createForm.color = c"
                                class="w-7 h-7 rounded-full border-2 transition-all"
                                :class="createForm.color === c ? 'border-black scale-110' : 'border-transparent hover:border-gray-300'"
                                :style="{ backgroundColor: c }"></button>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" :disabled="createForm.processing || !createForm.name"
                            class="px-5 py-2.5 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40 flex items-center gap-1.5">
                            <PlusIcon class="w-4 h-4" /> Create
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tags list -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">{{ tags.length }} tags</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    <div v-for="tag in tags" :key="tag.id" class="px-6 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors">
                        <!-- View mode -->
                        <div v-if="editingTag !== tag.id" class="flex items-center gap-3 flex-1">
                            <span class="w-4 h-4 rounded-full flex-shrink-0" :style="{ backgroundColor: tag.color }"></span>
                            <span class="text-sm font-medium text-gray-900">{{ tag.name }}</span>
                            <span class="text-xs text-gray-400">{{ tag.leads_count }} leads</span>
                        </div>
                        <div v-if="editingTag !== tag.id" class="flex items-center gap-2">
                            <button @click="startEdit(tag)" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors">
                                <PencilSquareIcon class="w-4 h-4" />
                            </button>
                            <button @click="confirmDelete(tag)" class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500 transition-colors">
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </div>

                        <!-- Edit mode -->
                        <div v-if="editingTag === tag.id" class="flex-1 space-y-3">
                            <input v-model="editForm.name" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-black" />
                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="c in colors" :key="c" type="button" @click="editForm.color = c"
                                    class="w-6 h-6 rounded-full border-2 transition-all"
                                    :class="editForm.color === c ? 'border-black scale-110' : 'border-transparent hover:border-gray-300'"
                                    :style="{ backgroundColor: c }"></button>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button @click="cancelEdit" class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs font-medium hover:bg-gray-50">Cancel</button>
                                <button @click="saveEdit(tag)" class="px-3 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800">Save</button>
                            </div>
                        </div>
                    </div>
                    <div v-if="!tags.length" class="px-6 py-8 text-center text-gray-400 text-sm">
                        No tags created yet. Create the first one above.
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete modal -->
        <Teleport to="body">
            <div v-if="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="deleteModal = null"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <TrashIcon class="w-6 h-6 text-red-500" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Delete tag "{{ deleteModal.name }}"?</h3>
                    <p class="text-sm text-gray-500 mb-5">It will be removed from {{ deleteModal.leads_count }} leads. This action cannot be undone.</p>
                    <div class="flex gap-3">
                        <button @click="deleteModal = null" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancel</button>
                        <button @click="deleteTag" class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Delete</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
