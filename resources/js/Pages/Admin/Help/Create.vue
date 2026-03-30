<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeftIcon, PaperClipIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ categories: Object });

const form = useForm({
    title: '',
    category: 'general',
    description: '',
    content: '',
    status: 'published',
    files: [],
});

const fileList = ref([]);
const fileInput = ref(null);

function handleFiles(e) {
    for (const f of e.target.files) {
        fileList.value.push({ file: f, name: f.name });
    }
    e.target.value = '';
}

function removeFile(idx) {
    fileList.value.splice(idx, 1);
}

function submit() {
    fileList.value.forEach((f, i) => { form[`files[${i}]`] = f.file; });
    form.post('/admin/help', { forceFormData: true });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/help" class="text-gray-400 hover:text-gray-600 text-sm flex items-center gap-1">
                    <ArrowLeftIcon class="w-4 h-4" /> Help Center
                </Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">New Article</h2>
            </div>
        </template>

        <div class="max-w-3xl mx-auto">
            <form @submit.prevent="submit" class="space-y-6">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37]">Article Details</h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                            <input v-model="form.title" type="text" placeholder="How to..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.title" class="mt-1 text-red-500 text-xs">{{ form.errors.title }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                            <select v-model="form.category" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select v-model="form.status" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="published">Published</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description <span class="text-gray-400 font-normal">(short summary)</span></label>
                            <input v-model="form.description" type="text" placeholder="Brief description of this article..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37]">Content</h3>
                    <textarea v-model="form.content" rows="15" placeholder="Write your article content here..."
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-y leading-relaxed"></textarea>
                    <p v-if="form.errors.content" class="text-red-500 text-xs">{{ form.errors.content }}</p>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37]">Attachments</h3>
                    <div v-if="fileList.length" class="space-y-2 mb-3">
                        <div v-for="(f, idx) in fileList" :key="idx" class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                            <div class="flex items-center gap-2 text-sm text-gray-700">
                                <PaperClipIcon class="w-4 h-4 text-gray-400" />
                                <span class="truncate">{{ f.name }}</span>
                            </div>
                            <button type="button" @click="removeFile(idx)" class="text-xs text-red-500 hover:text-red-700">&times;</button>
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 cursor-pointer transition-colors">
                        <PaperClipIcon class="w-4 h-4" /> Attach Files
                        <input type="file" ref="fileInput" @change="handleFiles" multiple class="hidden" />
                    </label>
                    <p class="text-xs text-gray-400">Images, PDF, Word, Excel, PowerPoint. Max 20MB each.</p>
                </div>

                <div class="flex justify-between">
                    <Link href="/admin/help" class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Cancel</Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-8 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-60">
                        {{ form.processing ? 'Publishing...' : 'Publish Article' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
