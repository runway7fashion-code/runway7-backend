<script setup>
import { ref, computed } from 'vue';
import { XMarkIcon, ClockIcon, BellAlertIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    recipientLabel: { type: String, default: '' },
    variables: { type: Array, default: () => [] },
    deepLinks: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['preview', 'close']);

const title = ref('');
const body = ref('');
const deepLink = ref('');
const titleRef = ref(null);
const bodyRef = ref(null);
const activeField = ref('body');

const showSchedule = ref(false);
const scheduleOption = ref('now');
const scheduleDate = ref('');
const scheduleTime = ref('10:00');

const TITLE_MAX = 50;
const BODY_MAX = 200;

const titleCount = computed(() => title.value.length);
const bodyCount = computed(() => body.value.length);

function insertVariable(varKey) {
    if (activeField.value === 'title') {
        const el = titleRef.value;
        const start = el?.selectionStart ?? title.value.length;
        const end = el?.selectionEnd ?? title.value.length;
        title.value = title.value.substring(0, start) + varKey + title.value.substring(end);
        setTimeout(() => {
            el?.focus();
            const pos = start + varKey.length;
            el?.setSelectionRange(pos, pos);
        }, 0);
    } else {
        const el = bodyRef.value;
        const start = el?.selectionStart ?? body.value.length;
        const end = el?.selectionEnd ?? body.value.length;
        body.value = body.value.substring(0, start) + varKey + body.value.substring(end);
        setTimeout(() => {
            el?.focus();
            const pos = start + varKey.length;
            el?.setSelectionRange(pos, pos);
        }, 0);
    }
}

function getScheduledAt() {
    if (scheduleOption.value === 'now') return null;
    if (scheduleOption.value === '1hour') {
        return new Date(Date.now() + 60 * 60 * 1000).toISOString();
    }
    if (scheduleOption.value === 'tomorrow') {
        const d = new Date();
        d.setDate(d.getDate() + 1);
        d.setHours(10, 0, 0, 0);
        return d.toISOString();
    }
    if (scheduleOption.value === 'custom' && scheduleDate.value) {
        return `${scheduleDate.value}T${scheduleTime.value}:00`;
    }
    return null;
}

function handlePreview() {
    if (!title.value.trim() || !body.value.trim()) return;
    emit('preview', {
        title: title.value,
        body: body.value,
        deep_link: deepLink.value || null,
        scheduled_at: getScheduledAt(),
    });
}
</script>

<template>
    <div class="bg-white rounded-2xl w-full max-w-2xl shadow-xl max-h-[90vh] flex flex-col">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
            <h3 class="text-lg font-bold text-gray-900">Send Push Notification</h3>
            <button @click="emit('close')" class="p-1 rounded-lg hover:bg-gray-100"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div class="px-6 py-4 space-y-4">
                <p v-if="recipientLabel" class="text-xs text-gray-400">To: {{ recipientLabel }}</p>

                <!-- Variables -->
                <div v-if="variables.length" class="flex flex-wrap gap-1.5 items-center">
                    <span class="text-xs text-gray-500">Insert in {{ activeField }}:</span>
                    <button v-for="v in variables" :key="v.key" @click="insertVariable(v.key)"
                        class="px-2 py-0.5 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors">
                        {{ v.label }}
                    </button>
                </div>

                <!-- Title -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-sm font-medium text-gray-700">Title *</label>
                        <span class="text-xs" :class="titleCount > TITLE_MAX ? 'text-red-500' : 'text-gray-400'">{{ titleCount }} / {{ TITLE_MAX }}</span>
                    </div>
                    <input ref="titleRef" v-model="title" type="text" :maxlength="TITLE_MAX + 10"
                        placeholder="Short, attention-grabbing title..."
                        @focus="activeField = 'title'"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 font-semibold" />
                </div>

                <!-- Body -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-sm font-medium text-gray-700">Message *</label>
                        <span class="text-xs" :class="bodyCount > BODY_MAX ? 'text-red-500' : 'text-gray-400'">{{ bodyCount }} / {{ BODY_MAX }}</span>
                    </div>
                    <textarea ref="bodyRef" v-model="body" rows="4" :maxlength="BODY_MAX + 20"
                        placeholder="Notification message body..."
                        @focus="activeField = 'body'"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                </div>

                <!-- Deep Link -->
                <div>
                    <label class="text-sm font-medium text-gray-700 block mb-1">Open when tapped (optional)</label>
                    <select v-model="deepLink" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                        <option v-for="link in deepLinks" :key="link.key" :value="link.key">{{ link.label }}</option>
                    </select>
                </div>

                <!-- Preview -->
                <div v-if="title || body">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase mb-2">Preview</p>
                    <div class="bg-gray-100 rounded-2xl p-3 flex items-start gap-3 max-w-md">
                        <div class="w-10 h-10 rounded-lg bg-black flex items-center justify-center flex-shrink-0">
                            <BellAlertIcon class="w-5 h-5 text-[#D4AF37]" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-0.5">
                                <span class="text-xs font-semibold text-gray-900">RUNWAY 7</span>
                                <span class="text-[10px] text-gray-500">now</span>
                            </div>
                            <p class="text-sm font-semibold text-gray-900 leading-tight">{{ title || 'Title preview' }}</p>
                            <p class="text-xs text-gray-600 leading-snug mt-0.5">{{ body || 'Message preview' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Schedule -->
                <div v-if="showSchedule" class="bg-gray-50 rounded-lg p-4 space-y-3">
                    <p class="text-sm font-medium text-gray-700">Schedule Notification</p>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" v-model="scheduleOption" value="now" class="accent-black" /> Send now (after preview)
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" v-model="scheduleOption" value="1hour" class="accent-black" /> 1 hour from now
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" v-model="scheduleOption" value="tomorrow" class="accent-black" /> Tomorrow @ 10:00 AM
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" v-model="scheduleOption" value="custom" class="accent-black" /> Custom Date and Time
                        </label>
                        <div v-if="scheduleOption === 'custom'" class="flex gap-2 ml-6">
                            <input v-model="scheduleDate" type="date" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-black/10" />
                            <input v-model="scheduleTime" type="time" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-black/10" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-2">
                <button @click="showSchedule = !showSchedule" :class="showSchedule ? 'bg-gray-200' : ''" class="p-2 rounded-lg hover:bg-gray-100 text-gray-500" title="Schedule">
                    <ClockIcon class="w-4 h-4" />
                </button>
                <p class="text-xs text-gray-400 ml-2">Sent via Firebase to users with the app installed.</p>
            </div>
            <div class="flex gap-3">
                <button @click="emit('close')" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button @click="handlePreview" :disabled="!title.trim() || !body.trim() || processing"
                    class="px-5 py-2 text-sm font-semibold text-white bg-black hover:bg-gray-800 rounded-lg disabled:opacity-50">
                    {{ processing ? 'Loading...' : 'Preview & Send' }}
                </button>
            </div>
        </div>
    </div>
</template>
