<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    categories: Array,
});

const categoryForm = useForm({ name: '' });
const editingCategory = ref(null);
const editCategoryName = ref('');

function addCategory() {
    if (!categoryForm.name.trim()) return;
    categoryForm.post('/admin/settings/designer-categories', {
        preserveScroll: true,
        onSuccess: () => { categoryForm.reset(); },
    });
}

function startEditCategory(cat) {
    editingCategory.value = cat.id;
    editCategoryName.value = cat.name;
}

function saveCategory(cat) {
    router.put(`/admin/settings/designer-categories/${cat.id}`, { name: editCategoryName.value }, {
        preserveScroll: true,
        onSuccess: () => { editingCategory.value = null; },
    });
}

function toggleCategory(cat) {
    router.put(`/admin/settings/designer-categories/${cat.id}`, { is_active: !cat.is_active }, { preserveScroll: true });
}

function deleteCategory(cat) {
    if (!confirm(`¿Eliminar la categoría "${cat.name}"?`)) return;
    router.delete(`/admin/settings/designer-categories/${cat.id}`, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Categorías de Diseñadores</h2>
        </template>

        <div class="max-w-3xl">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <!-- Add row -->
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <input v-model="categoryForm.name" type="text" placeholder="Nueva categoría..."
                                class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400"
                                :class="categoryForm.errors.name ? 'border-red-400 bg-red-50' : 'border-gray-200'"
                                @keyup.enter="addCategory" />
                            <p v-if="categoryForm.errors.name" class="text-red-500 text-xs mt-1">{{ categoryForm.errors.name }}</p>
                        </div>
                        <button @click="addCategory" :disabled="categoryForm.processing"
                            class="px-4 py-2.5 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors disabled:opacity-50">
                            {{ categoryForm.processing ? '...' : '+ Agregar' }}
                        </button>
                    </div>
                </div>

                <!-- List -->
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Slug</th>
                            <th class="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="cat in categories" :key="cat.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-3">
                                <template v-if="editingCategory === cat.id">
                                    <input v-model="editCategoryName" type="text"
                                        class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 w-full"
                                        @keyup.enter="saveCategory(cat)" @keyup.escape="editingCategory = null" />
                                </template>
                                <span v-else class="text-sm font-medium text-gray-900">{{ cat.name }}</span>
                            </td>
                            <td class="px-6 py-3">
                                <span class="text-xs text-gray-400 font-mono">{{ cat.slug }}</span>
                            </td>
                            <td class="px-6 py-3 text-center">
                                <button @click="toggleCategory(cat)"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium transition-colors"
                                    :class="cat.is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'">
                                    {{ cat.is_active ? 'Activa' : 'Inactiva' }}
                                </button>
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <template v-if="editingCategory === cat.id">
                                        <button @click="saveCategory(cat)" class="text-xs px-3 py-1.5 bg-black text-white rounded-lg hover:bg-gray-800">Guardar</button>
                                        <button @click="editingCategory = null" class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50">Cancelar</button>
                                    </template>
                                    <template v-else>
                                        <button @click="startEditCategory(cat)" class="text-gray-400 hover:text-gray-700 p-1.5 rounded hover:bg-gray-100 transition-colors" title="Editar">
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </button>
                                        <button @click="deleteCategory(cat)" class="text-gray-400 hover:text-red-500 p-1.5 rounded hover:bg-red-50 transition-colors" title="Eliminar">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="categories.length === 0">
                            <td colspan="4" class="px-6 py-12 text-center text-gray-400 text-sm">No hay categorías creadas.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
