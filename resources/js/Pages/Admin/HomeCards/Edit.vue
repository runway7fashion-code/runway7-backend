<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { PhotoIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    card: Object,
    events: Array,
});

const currentImageUrl = ref(props.card.image_url);
const uploadingImage = ref(false);

watch(() => props.card.image_url, (val) => {
    currentImageUrl.value = val;
});

const allRoles = [
    { value: 'model',         label: 'Model' },
    { value: 'designer',      label: 'Designer' },
    { value: 'media',         label: 'Media' },
    { value: 'volunteer',     label: 'Volunteer' },
    { value: 'staff',         label: 'Staff' },
    { value: 'attendee',      label: 'Attendee' },
    { value: 'vip',           label: 'VIP' },
    { value: 'influencer',    label: 'Influencer' },
    { value: 'press',         label: 'Press' },
    { value: 'sponsor',       label: 'Sponsor' },
    { value: 'complementary', label: 'Complementary' },
];

function formatDatetime(val) {
    if (!val) return '';
    return val.replace(' ', 'T').substring(0, 16);
}

const form = useForm({
    title:        props.card.title,
    action_type:  props.card.action_type,
    action_value: props.card.action_value,
    target_roles: props.card.target_roles ?? [],
    event_id:     props.card.event_id ?? '',
    order:        props.card.order ?? 0,
    status:       props.card.status,
    starts_at:    formatDatetime(props.card.starts_at),
    ends_at:      formatDatetime(props.card.ends_at),
});

const allRolesSelected = computed(() => form.target_roles.length === 0);
const imagePreview = ref(null);
const hasImage = computed(() => !!imagePreview.value || !!currentImageUrl.value);

const canSubmit = computed(() => {
    return form.title.trim() !== ''
        && hasImage.value
        && form.action_value.trim() !== '';
});

const actionValueLabel = computed(() => {
    return {
        url: 'Destination URL',
        video: 'Video URL (YouTube, Vimeo, etc.)',
        mailto: 'Email address',
    }[form.action_type] ?? 'Value';
});

const actionValuePlaceholder = computed(() => {
    return {
        url: 'https://runway7fashion.com/tickets',
        video: 'https://youtube.com/watch?v=...',
        mailto: 'operations@runway7fashion.com',
    }[form.action_type] ?? '';
});

function toggleAllRoles() {
    form.target_roles = form.target_roles.length === allRoles.length ? [] : allRoles.map(r => r.value);
}

function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}

function onImageChange(e) {
    const file = e.target.files[0];
    if (!file) return;

    imagePreview.value = URL.createObjectURL(file);
    uploadingImage.value = true;

    router.post(`/admin/operations/home-cards/${props.card.id}/upload-image`, { image: file }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadingImage.value = false;
            currentImageUrl.value = props.card.image_url;
        },
        onError: (errors) => {
            uploadingImage.value = false;
            imagePreview.value = null;
            alert('Error uploading image: ' + (Object.values(errors).flat().join(', ') || 'Unknown error'));
        },
    });
}

function submit() {
    form.put(`/admin/operations/home-cards/${props.card.id}`);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/operations/home-cards" class="text-gray-400 hover:text-gray-600 text-sm">Home Cards</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Edit Card</h2>
            </div>
        </template>

        <div class="max-w-2xl mx-auto">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Image -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h3 class="font-bold text-gray-900 mb-4">Card Image *</h3>

                    <div class="aspect-[4/3] rounded-xl overflow-hidden border-2 border-dashed bg-gray-50 mb-3 relative"
                        :class="hasImage ? 'border-gray-300' : 'border-red-300'">
                        <img v-if="imagePreview || storageUrl(currentImageUrl)"
                            :src="imagePreview || storageUrl(currentImageUrl)"
                            class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex flex-col items-center justify-center text-gray-300">
                            <PhotoIcon class="w-12 h-12 mb-2" />
                            <p class="text-sm">Upload an image (required)</p>
                        </div>
                        <div v-if="uploadingImage" class="absolute inset-0 bg-black/40 flex items-center justify-center">
                            <p class="text-white text-sm font-medium">Uploading...</p>
                        </div>
                    </div>

                    <input type="file" accept="image/*" @change="onImageChange" :disabled="uploadingImage"
                        class="text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-black file:text-white file:text-sm file:font-medium hover:file:bg-gray-800 file:cursor-pointer disabled:opacity-50" />
                </div>

                <!-- Info -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="font-bold text-gray-900 mb-2">Information</h3>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input v-model="form.title" type="text"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        <p v-if="form.errors.title" class="mt-1 text-red-500 text-xs">{{ form.errors.title }}</p>
                    </div>

                    <!-- Action Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Action Type *</label>
                        <div class="flex gap-3">
                            <label v-for="opt in [
                                { value: 'url', label: 'Open URL', icon: '🔗' },
                                { value: 'video', label: 'Play Video', icon: '🎬' },
                                { value: 'mailto', label: 'Send Email', icon: '✉️' },
                            ]" :key="opt.value"
                                class="flex-1 flex items-center gap-2 p-3 border rounded-xl cursor-pointer transition-all"
                                :class="form.action_type === opt.value ? 'border-black bg-gray-50 ring-1 ring-black' : 'border-gray-200 hover:border-gray-300'">
                                <input type="radio" v-model="form.action_type" :value="opt.value" class="sr-only" />
                                <span class="text-lg">{{ opt.icon }}</span>
                                <span class="text-sm font-medium">{{ opt.label }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Action Value -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ actionValueLabel }} *</label>
                        <input v-model="form.action_value" type="text" :placeholder="actionValuePlaceholder"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        <p v-if="form.errors.action_value" class="mt-1 text-red-500 text-xs">{{ form.errors.action_value }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Event</label>
                            <select v-model="form.event_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">All events</option>
                                <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Order</label>
                            <input v-model.number="form.order" type="number" min="0"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select v-model="form.status"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div></div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Show from</label>
                            <input v-model="form.starts_at" type="datetime-local"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Show until</label>
                            <input v-model="form.ends_at" type="datetime-local"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>
                </div>

                <!-- Roles target -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h3 class="font-bold text-gray-900 mb-3">Target Roles</h3>
                    <p class="text-xs text-gray-400 mb-4">If none selected = all roles.</p>

                    <label class="flex items-center gap-2 cursor-pointer mb-3 pb-3 border-b border-gray-100">
                        <input type="checkbox" :checked="allRolesSelected" @change="toggleAllRoles"
                            class="rounded border-gray-300 text-[#D4AF37] focus:ring-[#D4AF37]/20" />
                        <span class="text-sm font-semibold text-gray-800">All roles</span>
                    </label>

                    <div class="grid grid-cols-3 gap-2">
                        <label v-for="role in allRoles" :key="role.value"
                            class="flex items-center gap-2 cursor-pointer py-1">
                            <input type="checkbox" v-model="form.target_roles" :value="role.value"
                                class="rounded border-gray-300 text-black focus:ring-black/20" />
                            <span class="text-sm text-gray-700">{{ role.label }}</span>
                        </label>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex justify-between">
                    <Link href="/admin/operations/home-cards"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        Cancel
                    </Link>
                    <button type="submit" :disabled="!canSubmit || form.processing"
                        class="px-8 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-60 disabled:cursor-not-allowed transition-colors">
                        <span v-if="form.processing">Saving...</span>
                        <span v-else>Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
