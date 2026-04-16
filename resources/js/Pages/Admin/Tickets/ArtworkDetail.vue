<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeftIcon, CloudArrowUpIcon, TrashIcon, PaperClipIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';
import axios from 'axios';

const props = defineProps({
    designer: Object,
    event: Object,
    material: Object,
});

const brandName = props.designer.designer_profile?.brand_name || `${props.designer.first_name} ${props.designer.last_name}`;
const uploading = ref(false);
const uploadProgress = ref(0);

async function uploadFile() {
    const input = document.createElement('input');
    input.type = 'file';
    input.multiple = true;
    input.accept = 'image/*,video/*';
    input.onchange = async (e) => {
        for (const file of e.target.files) {
            await uploadSingle(file);
        }
    };
    input.click();
}

async function uploadSingle(file) {
    if (!props.material.drive_folder_id) {
        alert('No Drive folder configured.');
        return;
    }
    uploading.value = true;
    uploadProgress.value = 0;

    try {
        const { data } = await axios.post(`/admin/tickets/artworks/${props.material.id}/upload-url`, {
            file_name: file.name,
            mime_type: file.type || 'application/octet-stream',
        });

        const uploadResponse = await axios.put(data.upload_url, file, {
            headers: { 'Content-Type': file.type || 'application/octet-stream' },
            onUploadProgress: (e) => { uploadProgress.value = Math.round((e.loaded / e.total) * 100); },
        });

        router.post(`/admin/tickets/artworks/${props.material.id}/confirm-upload`, {
            drive_file_id: uploadResponse.data?.id,
            file_name: file.name,
            file_type: file.type?.startsWith('image') ? 'image' : file.type?.startsWith('video') ? 'video' : 'document',
            mime_type: file.type,
            file_size: file.size,
        }, { preserveScroll: true });
    } catch (err) {
        alert('Upload failed: ' + (err.response?.data?.error || err.message));
    } finally {
        uploading.value = false;
        uploadProgress.value = 0;
    }
}

function deleteFile(fileId) {
    if (!confirm('Delete this file?')) return;
    router.delete(`/admin/tickets/artwork-files/${fileId}`, { preserveScroll: true });
}

function formatSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="`/admin/tickets/artworks?event_id=${event.id}`" class="text-gray-400 hover:text-gray-600 text-sm flex items-center gap-1">
                    <ArrowLeftIcon class="w-4 h-4" /> Artworks
                </Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">{{ brandName }}</h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full overflow-hidden bg-gray-100 flex-shrink-0">
                        <img v-if="storageUrl(designer.profile_picture)" :src="storageUrl(designer.profile_picture)" class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex items-center justify-center text-sm font-bold text-gray-500">
                            {{ designer.first_name?.[0] }}{{ designer.last_name?.[0] }}
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">{{ brandName }}</h3>
                        <p class="text-sm text-gray-500">{{ event.name }} · Artworks</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a v-if="material.drive_folder_url" :href="material.drive_folder_url" target="_blank"
                        class="px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg text-xs font-medium hover:bg-blue-100 flex items-center gap-1">
                        <ArrowTopRightOnSquareIcon class="w-3.5 h-3.5" /> Drive Folder
                    </a>
                    <button @click="uploadFile" :disabled="uploading"
                        class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-50 flex items-center gap-1.5">
                        <CloudArrowUpIcon class="w-4 h-4" />
                        {{ uploading ? `Uploading ${uploadProgress}%...` : 'Upload Files' }}
                    </button>
                </div>
            </div>

            <!-- Progress bar -->
            <div v-if="uploading" class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-black h-2 rounded-full transition-all" :style="{ width: uploadProgress + '%' }"></div>
            </div>

            <!-- Files grid -->
            <div v-if="material.files?.length" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div v-for="file in material.files" :key="file.id"
                    class="bg-white rounded-xl border border-gray-200 overflow-hidden group">
                    <!-- Preview -->
                    <div class="aspect-square bg-gray-100 flex items-center justify-center relative">
                        <img v-if="file.file_type === 'image' && file.drive_url"
                            :src="file.drive_url" class="w-full h-full object-cover" />
                        <div v-else-if="file.file_type === 'video'" class="text-center text-gray-400">
                            <p class="text-3xl">&#9654;</p>
                            <p class="text-xs mt-1">Video</p>
                        </div>
                        <PaperClipIcon v-else class="w-8 h-8 text-gray-300" />
                        <!-- Overlay on hover -->
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                            <a v-if="file.drive_url" :href="file.drive_url" target="_blank"
                                class="p-2 bg-white rounded-lg text-gray-700 hover:bg-gray-100">
                                <ArrowTopRightOnSquareIcon class="w-4 h-4" />
                            </a>
                            <button @click.stop="deleteFile(file.id)" class="p-2 bg-white rounded-lg text-red-600 hover:bg-red-50">
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                    <!-- Info -->
                    <div class="px-3 py-2">
                        <p class="text-xs font-medium text-gray-700 truncate">{{ file.file_name }}</p>
                        <p class="text-xs text-gray-400">{{ formatSize(file.file_size) }} · {{ file.uploader?.first_name }}</p>
                    </div>
                </div>
            </div>

            <div v-else class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                <CloudArrowUpIcon class="w-12 h-12 mx-auto text-gray-300 mb-3" />
                <p class="text-gray-500 text-sm">No artwork files uploaded yet.</p>
                <p class="text-gray-400 text-xs mt-1">Upload photos and videos for this designer's social media.</p>
            </div>
        </div>
    </AdminLayout>
</template>
