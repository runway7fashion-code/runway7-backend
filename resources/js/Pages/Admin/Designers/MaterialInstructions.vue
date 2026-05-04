<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    ArrowLeftIcon, PencilSquareIcon, CheckIcon, XMarkIcon,
    InformationCircleIcon, DocumentTextIcon, CalendarDaysIcon, BoltIcon,
    CloudArrowUpIcon, TrashIcon, ExclamationCircleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    instructions: Array,
    events: Array,
    sharedMaterials: Array,
});

// ─── Shared materials (Operations: one-for-all) ───
const uploading = ref({}); // { "eventId|materialName": { progress, name } }
const uploadError = ref({}); // { "eventId|materialName": "msg" }
const fileInputs = ref({});

function uploadKey(eventId, materialName) {
    return `${eventId}|${materialName}`;
}

function triggerUpload(eventId, materialName) {
    const key = uploadKey(eventId, materialName);
    fileInputs.value[key]?.click();
}

async function handleFileSelected(event, eventId, materialName) {
    const file = event.target.files?.[0];
    event.target.value = ''; // reset so same file can re-trigger
    if (!file) return;

    const key = uploadKey(eventId, materialName);
    uploadError.value[key] = null;
    uploading.value[key] = { progress: 0, name: file.name };

    try {
        // Step 1: get resumable upload URL from backend
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const urlRes = await fetch(`/admin/operations/designers/material-instructions/events/${eventId}/shared/upload-url`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                material_name: materialName,
                file_name: file.name,
                mime_type: file.type || 'application/octet-stream',
            }),
        });
        if (!urlRes.ok) {
            const err = await urlRes.json().catch(() => ({}));
            throw new Error(err.message || `Failed to get upload URL (HTTP ${urlRes.status})`);
        }
        const { upload_url } = await urlRes.json();

        // Step 2: PUT bytes directly to Google
        const driveId = await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('PUT', upload_url, true);
            xhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    uploading.value[key] = { progress: Math.round((e.loaded / e.total) * 100), name: file.name };
                }
            };
            xhr.onload = () => {
                if (xhr.status === 200 || xhr.status === 201) {
                    try { resolve(JSON.parse(xhr.responseText).id); } catch (e) { reject(new Error('Invalid response from Drive')); }
                } else {
                    reject(new Error(`Drive upload failed (HTTP ${xhr.status})`));
                }
            };
            xhr.onerror = () => reject(new Error('Network error during upload'));
            xhr.send(file);
        });

        // Step 3: confirm to backend (replicates to all designers)
        const completeRes = await fetch(`/admin/operations/designers/material-instructions/events/${eventId}/shared/upload-complete`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                material_name: materialName,
                drive_file_id: driveId,
                file_name: file.name,
                mime_type: file.type || null,
                file_size: file.size,
            }),
        });
        if (!completeRes.ok) {
            const err = await completeRes.json().catch(() => ({}));
            throw new Error(err.message || `Failed to register upload (HTTP ${completeRes.status})`);
        }

        // Reload event data so the new file shows up in the list.
        router.reload({ only: ['events'], preserveScroll: true });
    } catch (e) {
        uploadError.value[key] = e.message;
    } finally {
        delete uploading.value[key];
    }
}

async function deleteSharedFile(file, eventId, materialName) {
    if (!confirm(`Delete ${file.file_name} from all designers in this event?`)) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const res = await fetch(`/admin/operations/designers/material-instructions/shared/${file.id}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
    });

    if (res.ok) {
        router.reload({ only: ['events'], preserveScroll: true });
        return;
    }

    const err = await res.json().catch(() => ({}));
    if (Array.isArray(err.designers) && err.designers.length) {
        alert(`Cannot delete: these designers already responded:\n\n${err.designers.join('\n')}`);
    } else {
        alert(err.message || `Delete failed (HTTP ${res.status})`);
    }
}

function formatSize(bytes) {
    if (!bytes) return '';
    const kb = bytes / 1024;
    if (kb < 1024) return `${kb.toFixed(1)} KB`;
    return `${(kb / 1024).toFixed(1)} MB`;
}

function materialAccent(name) {
    return {
        'Runway Logo':       'bg-[#D4AF37]/10 text-[#8a7220]',
        'Hair Mood Board':   'bg-pink-50 text-pink-700',
        'Makeup Mood Board': 'bg-purple-50 text-purple-700',
    }[name] || 'bg-gray-50 text-gray-700';
}

const editing = ref(null); // id of row being edited
const draft = ref('');
const saving = ref(false);

// ─── Event deadlines ───
const eventDrafts = ref(Object.fromEntries(props.events.map(ev => [ev.id, ev.materials_deadline_default || ''])));
const savingEventId = ref(null);
const overwriteConfirm = ref(null); // event id pending overwrite confirmation

function startEdit(row) {
    editing.value = row.id;
    draft.value = row.instructions || '';
}

function cancelEdit() {
    editing.value = null;
    draft.value = '';
}

function save(row) {
    saving.value = true;
    router.patch(`/admin/operations/designers/material-instructions/${row.id}`, {
        instructions: draft.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; draft.value = ''; },
        onFinish: () => { saving.value = false; },
    });
}

function saveEventDeadline(ev, overwrite = false) {
    savingEventId.value = ev.id;
    router.patch(`/admin/operations/designers/material-instructions/events/${ev.id}/deadline`, {
        materials_deadline_default: eventDrafts.value[ev.id] || null,
        overwrite,
    }, {
        preserveScroll: true,
        onSuccess: () => { overwriteConfirm.value = null; },
        onFinish: () => { savingEventId.value = null; },
    });
}

function uploaderLabel(by) {
    return {
        designer:  'Designer uploads',
        operation: 'Operations uploads',
        tickets:   'Tickets team uploads',
    }[by] || by;
}

function uploaderBadge(by) {
    return {
        designer:  'bg-blue-50 text-blue-700',
        operation: 'bg-amber-50 text-amber-700',
        tickets:   'bg-purple-50 text-purple-700',
    }[by] || 'bg-gray-100 text-gray-600';
}

const filledCount = computed(() => props.instructions.filter(i => i.instructions && i.instructions.trim()).length);
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/operations/designers" class="flex items-center gap-1 text-gray-400 hover:text-gray-600 text-sm">
                    <ArrowLeftIcon class="w-4 h-4" /> Designers
                </Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Material Instructions</h2>
            </div>
        </template>

        <div class="max-w-screen-2xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            <!-- LEFT column: deadlines + shared materials -->
            <div class="space-y-5 min-w-0">

            <!-- Default deadlines per event -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center flex-shrink-0">
                        <CalendarDaysIcon class="w-5 h-5 text-blue-600" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-gray-900 mb-1">Default deadlines by event</h3>
                        <p class="text-xs text-gray-600 leading-relaxed">
                            Set a default materials deadline for an event. New designers and designers without a custom deadline will inherit this date.
                            Use <strong class="text-gray-900">Apply to all</strong> if you also want to overwrite designers that already have a custom deadline.
                        </p>
                    </div>
                </div>

                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Event</th>
                            <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Designers</th>
                            <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Default deadline</th>
                            <th class="px-5 py-2.5 text-right text-[10px] font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="ev in events" :key="ev.id">
                            <td class="px-5 py-3 align-top">
                                <div class="font-medium text-gray-900">{{ ev.name }}</div>
                                <div v-if="ev.start_date" class="text-[11px] text-gray-400">Starts {{ ev.start_date }}</div>
                            </td>
                            <td class="px-5 py-3 align-top">
                                <span class="text-xs text-gray-700">{{ ev.designers_count }} total</span>
                                <span v-if="ev.designers_with_custom > 0" class="block text-[11px] text-amber-700">
                                    {{ ev.designers_with_custom }} with custom deadline
                                </span>
                            </td>
                            <td class="px-5 py-3 align-top">
                                <input v-model="eventDrafts[ev.id]" type="date"
                                    class="border border-gray-300 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-black/10" />
                            </td>
                            <td class="px-5 py-3 align-top text-right">
                                <div class="inline-flex flex-wrap justify-end gap-1.5">
                                    <button @click="saveEventDeadline(ev, false)" :disabled="savingEventId === ev.id"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 disabled:opacity-50 transition-colors"
                                        title="Save and apply only to designers without a custom deadline">
                                        <CheckIcon class="w-3.5 h-3.5" />
                                        {{ savingEventId === ev.id && overwriteConfirm !== ev.id ? 'Saving...' : 'Save' }}
                                    </button>
                                    <button v-if="overwriteConfirm !== ev.id"
                                        @click="overwriteConfirm = ev.id"
                                        :disabled="savingEventId === ev.id || ev.designers_with_custom === 0"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 border border-amber-300 text-amber-700 rounded-lg text-xs font-medium hover:bg-amber-50 disabled:opacity-40 transition-colors"
                                        title="Force this deadline on every designer of this event">
                                        <BoltIcon class="w-3.5 h-3.5" />
                                        Apply to all
                                    </button>
                                    <template v-else>
                                        <button @click="saveEventDeadline(ev, true)" :disabled="savingEventId === ev.id"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-amber-500 text-white rounded-lg text-xs font-medium hover:bg-amber-600 disabled:opacity-50 transition-colors">
                                            {{ savingEventId === ev.id ? 'Applying...' : `Confirm overwrite (${ev.designers_with_custom})` }}
                                        </button>
                                        <button @click="overwriteConfirm = null"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 border border-gray-200 text-gray-600 rounded-lg text-xs font-medium hover:bg-gray-50 transition-colors">
                                            <XMarkIcon class="w-3.5 h-3.5" />
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!events.length">
                            <td colspan="4" class="px-5 py-6 text-center text-xs text-gray-400 italic">No active events.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Shared materials by event (Operations: one-for-all) -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center flex-shrink-0">
                        <CloudArrowUpIcon class="w-5 h-5 text-emerald-600" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-gray-900 mb-1">Shared materials by event</h3>
                        <p class="text-xs text-gray-600 leading-relaxed">
                            Upload <strong class="text-gray-900">once</strong> and the file is automatically distributed to every designer of the event.
                            Designers added later will receive the same files when assigned. Removing a Mood Board image is blocked if any designer has already responded to it.
                        </p>
                    </div>
                </div>

                <div v-if="!events.length" class="px-5 py-6 text-center text-xs text-gray-400 italic">
                    No active events.
                </div>

                <div v-for="ev in events" :key="`shared-${ev.id}`" class="border-t border-gray-100 first:border-t-0">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-100">
                        <div class="font-medium text-gray-900 text-sm">{{ ev.name }}</div>
                        <div v-if="ev.start_date" class="text-[11px] text-gray-400">Starts {{ ev.start_date }} · {{ ev.designers_count }} designer(s)</div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-gray-100">
                        <div v-for="materialName in sharedMaterials" :key="`${ev.id}-${materialName}`" class="p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <span :class="materialAccent(materialName)" class="px-2 py-0.5 rounded-full text-[10px] font-semibold">{{ materialName }}</span>
                                <span v-if="!ev.shared[materialName].configured" class="text-[10px] text-amber-600 inline-flex items-center gap-1">
                                    <ExclamationCircleIcon class="w-3 h-3" />
                                    Not configured
                                </span>
                            </div>

                            <input
                                type="file"
                                class="hidden"
                                :ref="(el) => { if (el) fileInputs[uploadKey(ev.id, materialName)] = el; }"
                                @change="handleFileSelected($event, ev.id, materialName)"
                            />

                            <button
                                @click="triggerUpload(ev.id, materialName)"
                                :disabled="!ev.shared[materialName].configured || !!uploading[uploadKey(ev.id, materialName)]"
                                class="w-full border-2 border-dashed border-gray-200 hover:border-gray-300 hover:bg-gray-50 rounded-lg px-3 py-2 text-center transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <CloudArrowUpIcon class="w-6 h-4 mx-auto text-gray-400 mb-0" />
                                <span class="block text-[10px] text-gray-600 font-medium">
                                    {{ uploading[uploadKey(ev.id, materialName)]
                                        ? `Uploading ${uploading[uploadKey(ev.id, materialName)].name} (${uploading[uploadKey(ev.id, materialName)].progress}%)`
                                        : 'Click to upload' }}
                                </span>
                                <div v-if="uploading[uploadKey(ev.id, materialName)]" class="mt-2 h-1 bg-gray-200 rounded overflow-hidden">
                                    <div class="h-full bg-emerald-500 transition-all" :style="{ width: `${uploading[uploadKey(ev.id, materialName)].progress}%` }"></div>
                                </div>
                            </button>

                            <p v-if="uploadError[uploadKey(ev.id, materialName)]" class="mt-2 text-[11px] text-red-600">
                                {{ uploadError[uploadKey(ev.id, materialName)] }}
                            </p>

                            <ul v-if="ev.shared[materialName].files.length" class="mt-3 space-y-1.5">
                                <li v-for="f in ev.shared[materialName].files" :key="f.id"
                                    class="flex items-center justify-between gap-2 text-[11px] bg-gray-50 rounded px-2 py-1.5">
                                    <a :href="f.drive_url" target="_blank" rel="noopener" class="flex-1 text-gray-700 hover:text-gray-900 hover:underline truncate" :title="f.file_name">
                                        {{ f.file_name }}
                                        <span v-if="f.file_size" class="text-gray-400">· {{ formatSize(f.file_size) }}</span>
                                    </a>
                                    <button @click="deleteSharedFile(f, ev.id, materialName)" class="text-gray-400 hover:text-red-600 flex-shrink-0">
                                        <TrashIcon class="w-3.5 h-3.5" />
                                    </button>
                                </li>
                            </ul>
                            <p v-else class="mt-3 text-[10px] text-gray-400 italic">No files uploaded.</p>
                        </div>
                    </div>
                </div>
            </div>

            </div><!-- /left column -->

            <!-- RIGHT column: global info card + per-material instructions list -->
            <aside class="space-y-5 min-w-0">
                <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full bg-[#D4AF37]/10 flex items-center justify-center flex-shrink-0">
                        <InformationCircleIcon class="w-5 h-5 text-[#D4AF37]" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-gray-900 mb-1">Global instructions for designers</h3>
                        <p class="text-xs text-gray-600 leading-relaxed">
                            These texts are shown to <strong class="text-gray-900">every designer</strong> in the Runway 7 app and in the admin panel.
                            Edit them here whenever the brief or the requirements change. Changes apply immediately to all designers across all events.
                        </p>
                        <div class="mt-2 inline-flex items-center gap-1.5 text-[11px] text-gray-500">
                            <DocumentTextIcon class="w-3.5 h-3.5" />
                            <span>{{ filledCount }} of {{ instructions.length }} materials with instructions</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <div v-for="row in instructions" :key="row.id"
                        class="bg-white rounded-2xl border border-gray-200 overflow-hidden">

                        <!-- Header row -->
                        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-semibold text-gray-900">{{ row.material_name }}</span>
                                <span :class="uploaderBadge(row.upload_by)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium">
                                    {{ uploaderLabel(row.upload_by) }}
                                </span>
                                <span v-if="!row.instructions || !row.instructions.trim()"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500">
                                    No instructions yet
                                </span>
                            </div>
                            <div v-if="editing !== row.id" class="flex items-center gap-2 flex-shrink-0">
                                <button @click="startEdit(row)"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 border border-gray-200 text-gray-600 rounded-lg text-xs font-medium hover:bg-gray-50 transition-colors">
                                    <PencilSquareIcon class="w-3.5 h-3.5" />
                                    Edit
                                </button>
                            </div>
                        </div>

                        <!-- Display mode -->
                        <div v-if="editing !== row.id" class="px-5 py-4">
                            <p v-if="row.instructions" class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ row.instructions }}</p>
                            <p v-else class="text-sm text-gray-400 italic">No instructions configured yet. Click Edit to add the brief.</p>
                        </div>

                        <!-- Edit mode -->
                        <div v-else class="px-5 py-4 bg-gray-50">
                            <textarea v-model="draft" rows="6"
                                placeholder="Enter the instructions the designer will see for this material..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white resize-y"></textarea>
                            <div class="mt-3 flex items-center gap-2 flex-wrap">
                                <button @click="save(row)" :disabled="saving"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 disabled:opacity-50 transition-colors">
                                    <CheckIcon class="w-3.5 h-3.5" />
                                    {{ saving ? 'Saving...' : 'Save' }}
                                </button>
                                <button @click="cancelEdit"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 border border-gray-200 text-gray-600 rounded-lg text-xs font-medium hover:bg-gray-50 transition-colors">
                                    <XMarkIcon class="w-3.5 h-3.5" />
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            </div><!-- /grid -->
        </div>
    </AdminLayout>
</template>
