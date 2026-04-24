<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MaterialCard from '@/Components/MaterialCard.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { ArrowLeftIcon, CalendarDaysIcon, ArrowTopRightOnSquareIcon, InformationCircleIcon, XMarkIcon, BellAlertIcon, EnvelopeIcon, DevicePhoneMobileIcon, LockClosedIcon } from '@heroicons/vue/24/outline';

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

// Deadline info modal
const showDeadlineInfo = ref(false);

const reminderStages = [
    { days: 30, label: '30 days before', title: 'Friendly reminder',   desc: 'The deadline is approaching. Plenty of time to gather everything.', tone: 'neutral' },
    { days: 7,  label: '7 days before',  title: 'One week to go',      desc: 'A reminder that only 1 week remains to upload all materials.',       tone: 'warn'    },
    { days: 3,  label: '3 days before',  title: 'Only 3 days left',    desc: 'Urgent reminder to finalize pending uploads.',                        tone: 'warn'    },
    { days: 1,  label: '1 day before',   title: 'Deadline tomorrow',   desc: 'Final notice — the deadline is tomorrow.',                            tone: 'urgent'  },
    { days: 0,  label: 'Same day',       title: 'Today is the day',    desc: 'Last day to upload. After today, uploads are blocked.',               tone: 'urgent'  },
    { days: -1, label: 'Day after',      title: 'Deadline passed',     desc: 'Uploads are blocked. The designer must contact their advisor.',       tone: 'overdue' },
];

function toneDot(tone) {
    return {
        neutral: 'bg-gray-300',
        warn:    'bg-amber-400',
        urgent:  'bg-orange-500',
        overdue: 'bg-red-600',
    }[tone] || 'bg-gray-300';
}
function toneBadge(tone) {
    return {
        neutral: 'bg-gray-100 text-gray-700',
        warn:    'bg-amber-50 text-amber-700',
        urgent:  'bg-orange-50 text-orange-700',
        overdue: 'bg-red-50 text-red-700',
    }[tone] || 'bg-gray-100 text-gray-700';
}
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
                    <button @click="showDeadlineInfo = true"
                        class="group w-6 h-6 rounded-full border border-gray-200 text-gray-400 flex items-center justify-center hover:border-[#D4AF37] hover:text-[#D4AF37] hover:bg-[#D4AF37]/5 transition-colors"
                        title="How do deadline reminders work?">
                        <InformationCircleIcon class="w-3.5 h-3.5" />
                    </button>
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

        <!-- Modal: Deadline reminders info -->
        <Teleport to="body">
            <div v-if="showDeadlineInfo" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showDeadlineInfo = false"></div>

                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col">
                    <!-- Gold accent bar -->
                    <div class="h-1 bg-gradient-to-r from-[#D4AF37] via-[#f0d060] to-[#D4AF37]"></div>

                    <!-- Header -->
                    <div class="relative px-6 pt-6 pb-4 border-b border-gray-100">
                        <button @click="showDeadlineInfo = false"
                            class="absolute top-4 right-4 w-8 h-8 rounded-full text-gray-400 hover:text-gray-700 hover:bg-gray-100 flex items-center justify-center transition-colors">
                            <XMarkIcon class="w-4 h-4" />
                        </button>
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-full bg-[#D4AF37]/10 flex items-center justify-center flex-shrink-0">
                                <BellAlertIcon class="w-5 h-5 text-[#D4AF37]" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 leading-tight">Deadline reminders</h3>
                                <p class="text-xs text-gray-500 mt-0.5">How the system reminds designers automatically.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Body (scrollable) -->
                    <div class="px-6 py-5 overflow-y-auto">
                        <!-- Intro -->
                        <p class="text-sm text-gray-600 leading-relaxed mb-5">
                            Once a deadline is set, the designer receives <strong class="text-gray-900">6 automatic reminders</strong>
                            via <span class="inline-flex items-center gap-1"><EnvelopeIcon class="w-3.5 h-3.5 text-gray-500" /> <strong class="text-gray-900">email</strong></span>
                            and <span class="inline-flex items-center gap-1"><DevicePhoneMobileIcon class="w-3.5 h-3.5 text-gray-500" /> <strong class="text-gray-900">push notification</strong></span>
                            in the Runway 7 app. Only fires if the designer still has pending materials.
                        </p>

                        <!-- Timeline of stages -->
                        <div class="relative pl-6 space-y-4 mb-6">
                            <!-- vertical line -->
                            <div class="absolute left-2 top-2 bottom-2 w-px bg-gradient-to-b from-gray-200 via-gray-300 to-red-300"></div>

                            <div v-for="stage in reminderStages" :key="stage.days" class="relative">
                                <!-- dot -->
                                <div :class="toneDot(stage.tone)"
                                    class="absolute -left-[1.45rem] top-1.5 w-3 h-3 rounded-full ring-4 ring-white"></div>

                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-0.5">
                                            <span class="text-sm font-semibold text-gray-900">{{ stage.title }}</span>
                                            <span :class="toneBadge(stage.tone)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium">
                                                {{ stage.label }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 leading-relaxed">{{ stage.desc }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Blocked upload note -->
                        <div class="bg-red-50 border border-red-100 rounded-xl p-4 flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                                <LockClosedIcon class="w-4 h-4 text-red-600" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-red-900 mb-0.5">After the deadline</p>
                                <p class="text-xs text-red-700 leading-relaxed">
                                    Uploads from the app are <strong>blocked automatically</strong>. The designer needs to contact their advisor
                                    to request an extension by updating this same deadline.
                                </p>
                            </div>
                        </div>

                        <!-- Operations note -->
                        <div class="mt-4 bg-gray-50 rounded-xl p-4 text-xs text-gray-600 leading-relaxed">
                            <p class="font-semibold text-gray-900 mb-1">For operations</p>
                            <p>
                                You can monitor all designers with overdue materials in the dedicated panel, and send a
                                manual reminder at any time from there. Reminders run every day at 9:00 AM automatically.
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                        <Link href="/admin/operations/designers/overdue-materials"
                            @click="showDeadlineInfo = false"
                            class="text-xs font-medium text-gray-600 hover:text-gray-900 underline underline-offset-2">
                            Open overdue designers panel →
                        </Link>
                        <button @click="showDeadlineInfo = false"
                            class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors">
                            Got it
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </AdminLayout>
</template>
