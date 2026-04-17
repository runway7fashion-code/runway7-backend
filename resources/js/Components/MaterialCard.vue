<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { ChevronDownIcon, ChevronUpIcon, ArrowTopRightOnSquareIcon, TrashIcon, PaperClipIcon, CloudArrowUpIcon } from '@heroicons/vue/24/outline';
import axios from 'axios';

const props = defineProps({
    material: Object,
    designerId: Number,
});

const expanded = ref(false);
const uploading = ref(false);
const uploadProgress = ref(0);

const statusLabels = {
    pending: 'Pending', in_progress: 'In Progress', completed: 'Completed',
    confirmed: 'Confirmed', observed: 'Observed',
};
const statusColors = {
    pending: 'bg-gray-100 text-gray-600', in_progress: 'bg-blue-100 text-blue-700',
    completed: 'bg-green-100 text-green-700', confirmed: 'bg-emerald-100 text-emerald-700',
    observed: 'bg-amber-100 text-amber-700',
};

// File upload to Drive
async function uploadFile() {
    const input = document.createElement('input');
    input.type = 'file';
    input.multiple = true;
    input.onchange = async (e) => {
        for (const file of e.target.files) {
            await uploadSingleFile(file);
        }
    };
    input.click();
}

async function uploadSingleFile(file) {
    if (!props.material.drive_folder_id) {
        alert('No Drive folder configured for this material.');
        return;
    }

    uploading.value = true;
    uploadProgress.value = 0;

    try {
        // 1. Get resumable upload URL from backend
        const { data } = await axios.post(`/admin/operations/materials/${props.material.id}/upload-url`, {
            file_name: file.name,
            mime_type: file.type || 'application/octet-stream',
        });

        // 2. Upload directly to Google Drive
        const uploadResponse = await axios.put(data.upload_url, file, {
            headers: {
                'Content-Type': file.type || 'application/octet-stream',
            },
            onUploadProgress: (e) => {
                uploadProgress.value = Math.round((e.loaded / e.total) * 100);
            },
        });

        const driveFileId = uploadResponse.data?.id;

        // 3. Confirm upload with backend
        router.post(`/admin/operations/materials/${props.material.id}/confirm-upload`, {
            drive_file_id: driveFileId,
            file_name: file.name,
            file_type: file.type?.startsWith('image') ? 'image' : file.type?.startsWith('video') ? 'video' : file.type?.startsWith('audio') ? 'audio' : 'document',
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
    router.delete(`/admin/operations/material-files/${fileId}`, { preserveScroll: true });
}

function updateStatus(status) {
    router.patch(`/admin/operations/materials/${props.material.id}/status`, { status }, { preserveScroll: true });
}

// Bio
const bioForm = useForm({
    biography: props.material.bio_content?.biography || '',
    collection_description: props.material.bio_content?.collection_description || '',
    additional_notes: props.material.bio_content?.additional_notes || '',
    contact_info: props.material.bio_content?.contact_info || '',
});

function saveBio() {
    bioForm.put(`/admin/operations/materials/${props.material.id}/bio`, { preserveScroll: true });
}

// Observe
const observeNote = ref('');
function observe() {
    if (!observeNote.value.trim()) return;
    router.post(`/admin/operations/materials/${props.material.id}/observe`, {
        note: observeNote.value,
    }, { preserveScroll: true, onSuccess: () => { observeNote.value = ''; } });
}

function formatSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}
</script>

<template>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <!-- Header (always visible) -->
        <div class="flex items-center justify-between px-5 py-3 cursor-pointer hover:bg-gray-50 transition-colors" @click="expanded = !expanded">
            <div class="flex items-center gap-3">
                <span class="text-sm font-semibold text-gray-900">{{ material.name }}</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusColors[material.status] || 'bg-gray-100 text-gray-500'">
                    {{ statusLabels[material.status] || material.status }}
                </span>
                <span v-if="material.is_readonly" class="text-xs text-gray-400">(read-only)</span>
            </div>
            <div class="flex items-center gap-2">
                <a v-if="material.drive_folder_url" :href="material.drive_folder_url" target="_blank" @click.stop
                    class="text-xs text-blue-600 hover:text-blue-800 flex items-center gap-0.5">
                    <ArrowTopRightOnSquareIcon class="w-3 h-3" /> Drive
                </a>
                <span class="text-xs text-gray-400">{{ material.files?.length || 0 }} files</span>
                <ChevronUpIcon v-if="expanded" class="w-4 h-4 text-gray-400" />
                <ChevronDownIcon v-else class="w-4 h-4 text-gray-400" />
            </div>
        </div>

        <!-- Expanded content -->
        <div v-show="expanded" class="border-t border-gray-100 px-5 py-4 space-y-4">
            <p v-if="material.description" class="text-xs text-gray-500">{{ material.description }}</p>

            <!-- Status controls for collaborative materials -->
            <div v-if="material.status_flow === 'collaborative'" class="flex flex-wrap gap-2">
                <button v-for="s in ['pending', 'in_progress', 'completed']" :key="s"
                    @click="updateStatus(s)" :disabled="material.status === s"
                    class="px-3 py-1 text-xs rounded-lg border transition-colors"
                    :class="material.status === s ? 'bg-black text-white border-black' : 'border-gray-200 hover:bg-gray-50'">
                    {{ statusLabels[s] }}
                </button>
            </div>

            <!-- Bio section -->
            <div v-if="material.name === 'Bio'" class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Designer Biography</label>
                    <textarea v-model="bioForm.biography" rows="4" placeholder="Background, inspiration, experience, achievements..."
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">About the Collection</label>
                    <textarea v-model="bioForm.collection_description" rows="4" placeholder="Inspiration, key themes, materials, color palette..."
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Additional Notes</label>
                    <textarea v-model="bioForm.additional_notes" rows="2" placeholder="Any additional notes..."
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Contact Information</label>
                    <textarea v-model="bioForm.contact_info" rows="2" placeholder="Email, phone, preferred contact method..."
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                </div>
                <button @click="saveBio" :disabled="bioForm.processing"
                    class="px-4 py-2 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 disabled:opacity-50">
                    {{ bioForm.processing ? 'Saving...' : 'Save Bio' }}
                </button>
            </div>

            <!-- Moodboard section (Hair/Makeup) -->
            <div v-else-if="material.name === 'Hair Mood Board' || material.name === 'Makeup Mood Board'" class="space-y-3">
                <div v-for="item in material.moodboard_items" :key="item.id" class="flex gap-3 p-3 bg-gray-50 rounded-lg">
                    <div v-if="item.drive_url" class="w-24 h-24 rounded-lg overflow-hidden flex-shrink-0 bg-gray-200">
                        <img :src="item.drive_url" class="w-full h-full object-cover" />
                    </div>
                    <div class="flex-1">
                        <p class="text-xs text-gray-500 mb-1">{{ item.image_name }} · by {{ item.uploader?.first_name }}</p>
                        <div v-if="item.response_text" class="bg-white rounded-lg p-2 text-sm text-gray-700 border border-gray-200">
                            {{ item.response_text }}
                            <p class="text-xs text-gray-400 mt-1">Responded {{ item.responded_at }}</p>
                        </div>
                        <p v-else class="text-xs text-amber-600 italic">Awaiting designer response</p>
                    </div>
                </div>
                <p v-if="!material.moodboard_items?.length" class="text-xs text-gray-400">No moodboard images uploaded yet.</p>
            </div>

            <!-- Files section (all other materials) -->
            <div v-else class="space-y-2">
                <div v-for="file in material.files" :key="file.id"
                    class="flex items-center gap-3 p-2.5 bg-gray-50 rounded-lg text-sm">
                    <PaperClipIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
                    <div class="flex-1 min-w-0">
                        <a v-if="file.drive_url" :href="file.drive_url" target="_blank" class="text-blue-600 hover:text-blue-800 truncate block">{{ file.file_name }}</a>
                        <span v-else class="text-gray-700 truncate block">{{ file.file_name }}</span>
                        <p class="text-xs text-gray-400">{{ formatSize(file.file_size) }} · by {{ file.uploader?.first_name }} {{ file.uploader?.last_name }}</p>
                    </div>
                    <span v-if="file.is_final" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Final</span>
                    <button @click="deleteFile(file.id)" class="p-1 rounded hover:bg-red-50 text-gray-400 hover:text-red-500">
                        <TrashIcon class="w-4 h-4" />
                    </button>
                </div>

                <p v-if="!material.files?.length" class="text-xs text-gray-400">No files uploaded yet.</p>
            </div>

            <!-- Upload button -->
            <div v-if="!material.is_readonly || ['operation', 'admin'].includes($page?.props?.auth?.user?.role)">
                <button v-if="material.name !== 'Bio'" @click="uploadFile" :disabled="uploading"
                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-200 disabled:opacity-50 flex items-center gap-1.5">
                    <CloudArrowUpIcon class="w-4 h-4" />
                    {{ uploading ? `Uploading ${uploadProgress}%...` : 'Upload to Drive' }}
                </button>
                <!-- Upload progress bar -->
                <div v-if="uploading" class="mt-2 w-full bg-gray-200 rounded-full h-1.5">
                    <div class="bg-black h-1.5 rounded-full transition-all" :style="{ width: uploadProgress + '%' }"></div>
                </div>
            </div>

            <!-- Observe button (for collaborative materials, designer can observe) -->
            <div v-if="material.status_flow === 'collaborative' && material.status === 'completed'" class="border-t border-gray-100 pt-3 space-y-2">
                <div class="flex gap-2">
                    <button @click="updateStatus('confirmed')"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg text-xs font-medium hover:bg-green-700">
                        Confirm
                    </button>
                    <div class="flex-1 flex gap-2">
                        <input v-model="observeNote" type="text" placeholder="Reason for observation..."
                            class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-black/10" />
                        <button @click="observe" :disabled="!observeNote.trim()"
                            class="px-4 py-2 bg-amber-500 text-white rounded-lg text-xs font-medium hover:bg-amber-600 disabled:opacity-50">
                            Observe
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
