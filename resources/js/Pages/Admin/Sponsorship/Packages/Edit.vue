<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    package: Object,
    benefits: Array,
    selectedBenefitIds: Array,
});

const form = useForm({
    name: props.package.name,
    price: Number(props.package.price),
    assistants_count: Number(props.package.assistants_count),
    description: props.package.description || '',
    is_active: !!props.package.is_active,
    benefit_ids: (props.selectedBenefitIds || []).map(Number),
});

function toggleBenefit(id) {
    const i = form.benefit_ids.indexOf(id);
    if (i >= 0) form.benefit_ids.splice(i, 1);
    else form.benefit_ids.push(id);
}

function submit() {
    form.put(`/admin/sponsorship/packages/${props.package.id}`);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center space-x-2 text-sm">
                <Link href="/admin/sponsorship/packages" class="text-gray-400 hover:text-gray-600">Packages</Link>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-medium">{{ package.name }}</span>
            </div>
        </template>

        <div class="max-w-2xl">
            <h3 class="text-2xl font-bold text-gray-900 mb-6">Edit Package</h3>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Name *</label>
                        <input v-model="form.name" type="text" class="input" :class="form.errors.name && 'border-red-300'" />
                        <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Price (USD) *</label>
                            <input v-model.number="form.price" type="number" min="0" step="0.01" class="input" />
                            <p v-if="form.errors.price" class="err">{{ form.errors.price }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Assistants (guests) *</label>
                            <input v-model.number="form.assistants_count" type="number" min="0" class="input" />
                            <p v-if="form.errors.assistants_count" class="err">{{ form.errors.assistants_count }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                        <textarea v-model="form.description" rows="3" class="input resize-none"></textarea>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.is_active" type="checkbox" class="rounded"> Active
                    </label>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h4 class="font-semibold text-gray-900 mb-4">Benefits included</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label v-for="b in benefits" :key="b.id"
                            class="flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50"
                            :class="form.benefit_ids.includes(b.id) ? 'bg-yellow-50 border-[#D4AF37]' : ''">
                            <input type="checkbox" :checked="form.benefit_ids.includes(b.id)" @change="toggleBenefit(b.id)" class="rounded">
                            <span class="text-sm">{{ b.name }}</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <Link href="/admin/sponsorship/packages" class="px-4 py-2.5 text-sm text-gray-600 hover:text-gray-800 font-medium">Cancel</Link>
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
