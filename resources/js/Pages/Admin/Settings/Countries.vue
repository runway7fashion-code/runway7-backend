<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    countries: Array,
});

const form = useForm({ name: '', code: '', phone: '', flag: '' });
const editing = ref(null);
const editData = ref({ name: '', code: '', phone: '', flag: '' });

function addCountry() {
    if (!form.name.trim() || !form.code.trim() || !form.phone.trim()) return;
    form.post('/admin/operations/countries', {
        preserveScroll: true,
        onSuccess: () => { form.reset(); },
    });
}

function startEdit(country) {
    editing.value = country.id;
    editData.value = { name: country.name, code: country.code, phone: country.phone, flag: country.flag || '' };
}

function saveEdit(country) {
    router.put(`/admin/operations/countries/${country.id}`, editData.value, {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; },
    });
}

function toggleCountry(country) {
    router.put(`/admin/operations/countries/${country.id}`, { is_active: !country.is_active }, { preserveScroll: true });
}

function deleteCountry(country) {
    if (!confirm(`Delete "${country.name}"?`)) return;
    router.delete(`/admin/operations/countries/${country.id}`, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Countries & Phone Codes</h2>
        </template>

        <div class="max-w-5xl">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <!-- Add row -->
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="flex gap-3 items-end">
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Country Name</label>
                            <input v-model="form.name" type="text" placeholder="United States"
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10"
                                :class="form.errors.name ? 'border-red-400 bg-red-50' : 'border-gray-200'"
                                @keyup.enter="addCountry" />
                            <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                        </div>
                        <div class="w-24">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Code</label>
                            <input v-model="form.code" type="text" placeholder="US" maxlength="5"
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 uppercase"
                                :class="form.errors.code ? 'border-red-400 bg-red-50' : 'border-gray-200'" />
                            <p v-if="form.errors.code" class="text-red-500 text-xs mt-1">{{ form.errors.code }}</p>
                        </div>
                        <div class="w-28">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Phone</label>
                            <input v-model="form.phone" type="text" placeholder="+1"
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10"
                                :class="form.errors.phone ? 'border-red-400 bg-red-50' : 'border-gray-200'" />
                            <p v-if="form.errors.phone" class="text-red-500 text-xs mt-1">{{ form.errors.phone }}</p>
                        </div>
                        <div class="w-20">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Flag</label>
                            <input v-model="form.flag" type="text" placeholder="🇺🇸"
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 border-gray-200" />
                        </div>
                        <button @click="addCountry" :disabled="form.processing"
                            class="px-4 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors disabled:opacity-50 whitespace-nowrap">
                            {{ form.processing ? '...' : '+ Add' }}
                        </button>
                    </div>
                </div>

                <!-- List -->
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Flag</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Country</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Code</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Phone</th>
                            <th class="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="country in countries" :key="country.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-3">
                                <template v-if="editing === country.id">
                                    <input v-model="editData.flag" type="text"
                                        class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-14 text-center focus:outline-none focus:ring-2 focus:ring-black/10" />
                                </template>
                                <span v-else class="text-lg">{{ country.flag }}</span>
                            </td>
                            <td class="px-6 py-3">
                                <template v-if="editing === country.id">
                                    <input v-model="editData.name" type="text"
                                        class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 w-full"
                                        @keyup.enter="saveEdit(country)" @keyup.escape="editing = null" />
                                </template>
                                <span v-else class="text-sm font-medium text-gray-900">{{ country.name }}</span>
                            </td>
                            <td class="px-6 py-3">
                                <template v-if="editing === country.id">
                                    <input v-model="editData.code" type="text" maxlength="5"
                                        class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-16 uppercase focus:outline-none focus:ring-2 focus:ring-black/10" />
                                </template>
                                <span v-else class="text-xs text-gray-400 font-mono uppercase">{{ country.code }}</span>
                            </td>
                            <td class="px-6 py-3">
                                <template v-if="editing === country.id">
                                    <input v-model="editData.phone" type="text"
                                        class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-20 focus:outline-none focus:ring-2 focus:ring-black/10" />
                                </template>
                                <span v-else class="text-sm text-gray-600">{{ country.phone }}</span>
                            </td>
                            <td class="px-6 py-3 text-center">
                                <button @click="toggleCountry(country)"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium transition-colors"
                                    :class="country.is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'">
                                    {{ country.is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <template v-if="editing === country.id">
                                        <button @click="saveEdit(country)" class="text-xs px-3 py-1.5 bg-black text-white rounded-lg hover:bg-gray-800">Save</button>
                                        <button @click="editing = null" class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                                    </template>
                                    <template v-else>
                                        <button @click="startEdit(country)" class="p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition-colors" title="Edit">
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </button>
                                        <button @click="deleteCountry(country)" class="p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-red-50 hover:text-red-500 transition-colors" title="Delete">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="countries.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400 text-sm">No countries added yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
