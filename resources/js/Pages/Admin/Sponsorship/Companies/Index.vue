<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { PencilSquareIcon, PlusIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    companies: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
let searchTimeout = null;
function onSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/admin/sponsorship/companies', { search: search.value }, { preserveState: true, preserveScroll: true, replace: true });
    }, 350);
}

const showCreate = ref(false);
const createForm = useForm({ name: '' });
function submitCreate() {
    createForm.post('/admin/sponsorship/companies', {
        preserveScroll: true,
        onSuccess: () => { showCreate.value = false; createForm.reset(); },
    });
}

</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Companies</h2>
        </template>

        <div class="max-w-6xl mx-auto space-y-4">
            <div class="flex items-center gap-3">
                <div class="relative flex-1">
                    <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" />
                    <input v-model="search" @input="onSearch" type="text" placeholder="Search by name, industry or country..."
                        class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                </div>
                <button @click="showCreate = true"
                    class="px-4 py-2.5 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 flex items-center gap-1.5">
                    <PlusIcon class="w-4 h-4" /> New Company
                </button>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-6 py-3 font-medium">Name</th>
                            <th class="px-6 py-3 font-medium">Industry</th>
                            <th class="px-6 py-3 font-medium">Country</th>
                            <th class="px-6 py-3 font-medium">Website</th>
                            <th class="px-6 py-3 font-medium">Created by</th>
                            <th class="px-6 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="c in companies.data" :key="c.id" class="hover:bg-gray-50">
                            <td class="px-6 py-3 font-medium text-gray-900">{{ c.name }}</td>
                            <td class="px-6 py-3 text-gray-600">{{ c.industry || '—' }}</td>
                            <td class="px-6 py-3 text-gray-600">{{ c.country || '—' }}</td>
                            <td class="px-6 py-3">
                                <a v-if="c.website" :href="c.website" target="_blank" class="text-blue-600 hover:underline truncate block max-w-xs">{{ c.website }}</a>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-6 py-3 text-gray-500 text-xs">
                                {{ c.creator ? `${c.creator.first_name} ${c.creator.last_name}` : '—' }}
                            </td>
                            <td class="px-6 py-3 text-right">
                                <Link :href="`/admin/sponsorship/companies/${c.id}/edit`" class="inline-flex p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600">
                                    <PencilSquareIcon class="w-4 h-4" />
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!companies.data.length">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400 text-sm">
                                No companies yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="companies.links && companies.last_page > 1" class="flex justify-center gap-1">
                <Link v-for="link in companies.links" :key="link.label" :href="link.url ?? '#'"
                    class="px-3 py-1.5 text-sm rounded-lg border"
                    :class="[
                        link.active ? 'bg-black text-white border-black' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50',
                        !link.url ? 'opacity-40 pointer-events-none' : ''
                    ]"
                    v-html="link.label">
                </Link>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showCreate = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">New Company</h3>
                    <form @submit.prevent="submitCreate" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                            <input v-model="createForm.name" type="text" autofocus
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="createForm.errors.name" class="text-xs text-red-500 mt-1">{{ createForm.errors.name }}</p>
                            <p class="text-xs text-gray-500 mt-1">Only the name is required now. The rest of the details can be completed later.</p>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="showCreate = false" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancel</button>
                            <button type="submit" :disabled="createForm.processing || !createForm.name"
                                class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40">
                                Create
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </Teleport>
    </AdminLayout>
</template>
