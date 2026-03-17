<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    packages: Array,
});

const showPackageForm = ref(false);
const editingPackage = ref(null);
const packageForm = ref(getEmptyPackage());

function getEmptyPackage() {
    return { name: '', description: '', price: 0, default_looks: 10, default_assistants: 2, features: [] };
}

function resetPackageForm() {
    packageForm.value = getEmptyPackage();
    showPackageForm.value = false;
    editingPackage.value = null;
}

function savePackage() {
    if (editingPackage.value) {
        router.put(`/admin/settings/designer-packages/${editingPackage.value}`, packageForm.value, {
            preserveScroll: true,
            onSuccess: () => resetPackageForm(),
        });
    } else {
        router.post('/admin/settings/designer-packages', packageForm.value, {
            preserveScroll: true,
            onSuccess: () => resetPackageForm(),
        });
    }
}

function startEditPackage(pkg) {
    editingPackage.value = pkg.id;
    packageForm.value = {
        name: pkg.name,
        description: pkg.description || '',
        price: pkg.price,
        default_looks: pkg.default_looks,
        default_assistants: pkg.default_assistants,
        features: pkg.features || [],
    };
    showPackageForm.value = true;
}

function togglePackage(pkg) {
    router.put(`/admin/settings/designer-packages/${pkg.id}`, { is_active: !pkg.is_active }, { preserveScroll: true });
}

function deletePackage(pkg) {
    if (!confirm(`¿Eliminar el paquete "${pkg.name}"?`)) return;
    router.delete(`/admin/settings/designer-packages/${pkg.id}`, { preserveScroll: true });
}

function formatPrice(val) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0 }).format(val);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Paquetes de Diseñadores</h2>
        </template>

        <div class="max-w-4xl">
            <!-- Add/Edit form -->
            <div v-if="showPackageForm" class="bg-white rounded-xl border border-gray-200 p-6 mb-5">
                <h4 class="font-semibold text-gray-900 mb-4">{{ editingPackage ? 'Editar Paquete' : 'Nuevo Paquete' }}</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre *</label>
                        <input v-model="packageForm.name" type="text" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Precio (USD) *</label>
                        <input v-model.number="packageForm.price" type="number" min="0" step="100" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Looks incluidos *</label>
                        <input v-model.number="packageForm.default_looks" type="number" min="1" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Asistentes incluidos *</label>
                        <input v-model.number="packageForm.default_assistants" type="number" min="0" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400" />
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Descripción</label>
                        <input v-model="packageForm.description" type="text" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400" />
                    </div>
                </div>
                <div class="flex gap-2">
                    <button @click="savePackage" class="px-4 py-2.5 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors">
                        {{ editingPackage ? 'Guardar Cambios' : 'Crear Paquete' }}
                    </button>
                    <button @click="resetPackageForm" class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm font-medium hover:bg-gray-50 transition-colors">
                        Cancelar
                    </button>
                </div>
            </div>

            <div v-if="!showPackageForm" class="mb-4">
                <button @click="showPackageForm = true" class="px-4 py-2.5 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors">
                    + Agregar Paquete
                </button>
            </div>

            <!-- Packages table -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Paquete</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Precio</th>
                            <th class="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Looks</th>
                            <th class="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Asistentes</th>
                            <th class="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="pkg in packages" :key="pkg.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-3">
                                <p class="font-medium text-gray-900 text-sm">{{ pkg.name }}</p>
                                <p v-if="pkg.description" class="text-xs text-gray-400 mt-0.5">{{ pkg.description }}</p>
                            </td>
                            <td class="px-6 py-3">
                                <span class="text-sm font-semibold" style="color: #D4AF37;">{{ formatPrice(pkg.price) }}</span>
                            </td>
                            <td class="px-6 py-3 text-center text-sm text-gray-700">{{ pkg.default_looks }}</td>
                            <td class="px-6 py-3 text-center text-sm text-gray-700">{{ pkg.default_assistants }}</td>
                            <td class="px-6 py-3 text-center">
                                <button @click="togglePackage(pkg)"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium transition-colors"
                                    :class="pkg.is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'">
                                    {{ pkg.is_active ? 'Activo' : 'Inactivo' }}
                                </button>
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button @click="startEditPackage(pkg)" class="p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition-colors" title="Editar">
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </button>
                                    <button @click="deletePackage(pkg)" class="p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-red-50 hover:text-red-500 transition-colors" title="Eliminar">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="packages.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400 text-sm">No hay paquetes creados.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
