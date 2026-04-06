<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeftIcon, PencilSquareIcon, TrashIcon, PaperClipIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    article: Object,
    categories: Object,
    isAdmin: Boolean,
});

const showDeleteModal = ref(false);
const previewDoc = ref(null);

function deletArticle() {
    router.delete(`/admin/help/${props.article.id}`, {
        onSuccess: () => { showDeleteModal.value = false; },
    });
}

function deleteAttachment(id) {
    if (!confirm('Remove this attachment?')) return;
    router.delete(`/admin/help-attachments/${id}`, { preserveScroll: true });
}

function openPreview(file) {
    const url = `/storage/${file.file_path}`;
    const ext = file.file_name?.split('.').pop()?.toLowerCase();
    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext);
    const isOffice = ['docx', 'doc', 'xlsx', 'xls', 'pptx', 'ppt'].includes(ext);
    let viewerUrl = url;
    if (isOffice) {
        const fullUrl = window.location.origin + url;
        viewerUrl = `https://docs.google.com/gview?url=${encodeURIComponent(fullUrl)}&embedded=true`;
    }
    previewDoc.value = { url, viewerUrl, name: file.file_name, isImage };
}

const categoryColors = {
    general: 'bg-gray-100 text-gray-700',
    sales: 'bg-blue-50 text-blue-700',
    operations: 'bg-orange-50 text-orange-700',
    models: 'bg-pink-50 text-pink-700',
    designers: 'bg-purple-50 text-purple-700',
    media: 'bg-cyan-50 text-cyan-700',
    volunteers: 'bg-green-50 text-green-700',
    accounting: 'bg-yellow-50 text-yellow-700',
    events: 'bg-indigo-50 text-indigo-700',
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-3">
                    <Link href="/admin/help" class="text-gray-400 hover:text-gray-600 text-sm flex items-center gap-1">
                        <ArrowLeftIcon class="w-4 h-4" /> Help Center
                    </Link>
                    <span class="text-gray-300">/</span>
                    <h2 class="text-lg font-semibold text-gray-900">{{ article.title }}</h2>
                </div>
                <div v-if="isAdmin" class="flex items-center gap-2">
                    <Link :href="`/admin/help/${article.id}/edit`"
                        class="px-4 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 flex items-center gap-1">
                        <PencilSquareIcon class="w-3.5 h-3.5" /> Edit
                    </Link>
                    <button @click="showDeleteModal = true"
                        class="px-3 py-1.5 border border-red-200 text-red-600 rounded-lg text-xs font-medium hover:bg-red-50 flex items-center gap-1">
                        <TrashIcon class="w-3.5 h-3.5" /> Delete
                    </button>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto">
            <!-- Article header -->
            <div class="bg-white rounded-2xl border border-gray-200 p-8 mb-6">
                <div class="flex items-center gap-2 mb-4">
                    <span :class="categoryColors[article.category] || 'bg-gray-100 text-gray-700'" class="text-xs font-medium px-3 py-1 rounded-full">
                        {{ categories[article.category] || article.category }}
                    </span>
                    <span v-if="article.author" class="text-xs text-gray-400">by {{ article.author.first_name }} {{ article.author.last_name }}</span>
                    <span class="text-xs text-gray-400">· {{ new Date(article.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ article.title }}</h1>
                <p v-if="article.description" class="text-gray-500">{{ article.description }}</p>
            </div>

            <!-- Article content -->
            <div class="bg-white rounded-2xl border border-gray-200 p-8 mb-6">
                <div class="prose prose-sm max-w-none text-gray-700 whitespace-pre-line leading-relaxed">{{ article.content }}</div>
            </div>

            <!-- Attachments -->
            <div v-if="article.attachments?.length" class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Attachments ({{ article.attachments.length }})</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div v-for="file in article.attachments" :key="file.id"
                        class="flex items-center justify-between p-3 border border-gray-100 rounded-lg hover:bg-gray-50 transition-colors">
                        <button @click="openPreview(file)" class="flex items-center gap-3 flex-1 min-w-0 text-left">
                            <PaperClipIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
                            <span class="text-sm text-gray-700 truncate">{{ file.file_name }}</span>
                        </button>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <a :href="`/storage/${file.file_path}`" download class="text-xs text-blue-600 hover:text-blue-800">Download</a>
                            <button v-if="isAdmin" @click="deleteAttachment(file.id)" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Preview Modal -->
        <Teleport to="body">
            <div v-if="previewDoc" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/70" @click="previewDoc = null"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl h-[85vh] mx-4 flex flex-col overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 flex-shrink-0">
                        <span class="text-sm font-medium text-gray-900 truncate">{{ previewDoc.name }}</span>
                        <div class="flex items-center gap-2">
                            <a :href="previewDoc.url" download class="px-3 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800">Download</a>
                            <button @click="previewDoc = null" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
                        </div>
                    </div>
                    <div class="flex-1 bg-gray-100 overflow-auto flex items-center justify-center">
                        <img v-if="previewDoc.isImage" :src="previewDoc.url" :alt="previewDoc.name" class="max-w-full max-h-full object-contain" />
                        <iframe v-else :src="previewDoc.viewerUrl" class="w-full h-full border-0"></iframe>
                    </div>
                </div>
            </div>

            <!-- Delete Modal -->
            <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showDeleteModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
                    <TrashIcon class="w-10 h-10 text-red-400 mx-auto mb-3" />
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Delete "{{ article.title }}"?</h3>
                    <p class="text-sm text-gray-500 mb-5">This will permanently delete the article and all its attachments.</p>
                    <div class="flex gap-3">
                        <button @click="showDeleteModal = false" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">Cancel</button>
                        <button @click="deletArticle" class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Delete</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
