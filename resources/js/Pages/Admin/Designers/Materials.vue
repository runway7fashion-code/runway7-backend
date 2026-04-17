<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MaterialCard from '@/Components/MaterialCard.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { ArrowLeftIcon, CalendarDaysIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    designer: Object,
    event: Object,
    materials: Array,
    pivot: Object,
});

const brandName = props.designer.designer_profile?.brand_name || `${props.designer.first_name} ${props.designer.last_name}`;

// Progress
const progress = computed(() => {
    if (!props.materials.length) return 0;
    const done = props.materials.filter(m => ['completed', 'confirmed'].includes(m.status)).length;
    return Math.round((done / props.materials.length) * 100);
});

const progressColor = computed(() => {
    if (progress.value === 100) return 'bg-green-500';
    if (progress.value >= 50) return 'bg-amber-400';
    return 'bg-red-300';
});

// Deadline
const deadlineForm = useForm({
    materials_deadline: props.pivot.materials_deadline || '',
});

function saveDeadline() {
    deadlineForm.patch(`/admin/operations/designers/${props.designer.id}/materials-deadline/${props.event.id}`, {
        preserveScroll: true,
    });
}

// Deadline warning
const isOverdue = computed(() => {
    if (!props.pivot.materials_deadline) return false;
    return new Date(props.pivot.materials_deadline) < new Date();
});
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="`/admin/operations/designers/${designer.id}`" class="text-gray-400 hover:text-gray-600 text-sm flex items-center gap-1">
                    <ArrowLeftIcon class="w-4 h-4" /> {{ brandName }}
                </Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Materials</h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header card -->
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">{{ brandName }}</h3>
                        <p class="text-sm text-gray-500 mt-0.5">{{ event.name }} · Materials Onboarding</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a v-if="pivot.drive_root_folder_url" :href="pivot.drive_root_folder_url" target="_blank"
                            class="px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg text-xs font-medium hover:bg-blue-100 flex items-center gap-1">
                            <ArrowTopRightOnSquareIcon class="w-3.5 h-3.5" /> Google Drive
                        </a>
                    </div>
                </div>

                <!-- Progress bar -->
                <div class="mt-4">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-medium text-gray-600">Overall Progress</span>
                        <span class="text-xs font-bold" :class="progress === 100 ? 'text-green-600' : 'text-gray-600'">{{ progress }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="h-2 rounded-full transition-all duration-500" :class="progressColor" :style="{ width: progress + '%' }"></div>
                    </div>
                </div>

                <!-- Deadline -->
                <div class="mt-4 flex items-center gap-3">
                    <CalendarDaysIcon class="w-4 h-4 text-gray-400" />
                    <span class="text-xs text-gray-600">Deadline:</span>
                    <input v-model="deadlineForm.materials_deadline" type="date"
                        class="border border-gray-300 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-black/10" />
                    <button @click="saveDeadline" :disabled="deadlineForm.processing"
                        class="px-3 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 disabled:opacity-50">
                        {{ deadlineForm.processing ? 'Saving...' : 'Save' }}
                    </button>
                    <span v-if="isOverdue" class="text-xs text-red-600 font-medium">Overdue!</span>
                </div>
            </div>

            <!-- Materials list -->
            <div class="space-y-3">
                <MaterialCard v-for="material in materials" :key="material.id"
                    :material="material"
                    :designer-id="designer.id"
                />
            </div>
        </div>
    </AdminLayout>
</template>
