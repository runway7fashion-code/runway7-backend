<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ company: Object });

const form = useForm({
    name: props.company.name,
    website: props.company.website || '',
    instagram: props.company.instagram || '',
    logo: props.company.logo || '',
    industry: props.company.industry || '',
    country: props.company.country || '',
    notes: props.company.notes || '',
});

function submit() {
    form.put(`/admin/sponsorship/companies/${props.company.id}`);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center space-x-2 text-sm">
                <Link href="/admin/sponsorship/companies" class="text-gray-400 hover:text-gray-600">Companies</Link>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-medium">{{ company.name }}</span>
            </div>
        </template>

        <div class="max-w-2xl">
            <h3 class="text-2xl font-bold text-gray-900 mb-6">Edit Company</h3>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Name *</label>
                        <input v-model="form.name" type="text" class="input" :class="form.errors.name && 'border-red-300'" />
                        <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Industry</label>
                            <input v-model="form.industry" type="text" class="input" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Country</label>
                            <input v-model="form.country" type="text" class="input" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Website</label>
                            <input v-model="form.website" type="url" placeholder="https://" class="input" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Instagram</label>
                            <input v-model="form.instagram" type="text" placeholder="@brand" class="input" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Logo (URL)</label>
                        <input v-model="form.logo" type="text" class="input" placeholder="https://..." />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
                        <textarea v-model="form.notes" rows="4" class="input resize-none"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <Link href="/admin/sponsorship/companies" class="px-4 py-2.5 text-sm text-gray-600 hover:text-gray-800 font-medium">Cancel</Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-6 py-2.5 text-sm font-semibold text-white bg-black rounded-lg hover:bg-gray-800 transition-colors disabled:opacity-60">
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<style scoped>
@reference "tailwindcss";
.input { @apply w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400; }
.err { @apply mt-1 text-red-500 text-xs; }
</style>
