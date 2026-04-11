<script setup>
import { ref, computed } from 'vue';
import { XMarkIcon, ClockIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    recipientLabel: { type: String, default: '' },
    variables: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['preview', 'close']);

const message = ref('');
const messageRef = ref(null);
const showSchedule = ref(false);
const scheduleOption = ref('now');
const scheduleDate = ref('');
const scheduleTime = ref('10:00');

// GSM-7 vs UCS-2 detection
const gsm7Regex = /^[A-Za-z0-9 \r\n@£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!"#$%&'()*+,\-./:;<=>?¡¿\^{}\[\]~|€]*$/;

const messagePreview = computed(() => {
    const trimmed = message.value.trimEnd();
    if (!trimmed) return '';
    if (trimmed.endsWith('- Runway 7')) return trimmed;
    return trimmed + '\n- Runway 7';
});

const charCount = computed(() => messagePreview.value.length);
const isGsm7 = computed(() => gsm7Regex.test(messagePreview.value));
const encoding = computed(() => isGsm7.value ? 'GSM-7' : 'UCS-2');

const segments = computed(() => {
    const text = messagePreview.value;
    if (!text) return 0;
    const length = text.length;
    if (isGsm7.value) {
        if (length <= 160) return 1;
        return Math.ceil(length / 153);
    }
    if (length <= 70) return 1;
    return Math.ceil(length / 67);
});

const maxChars = computed(() => {
    if (!messagePreview.value) return isGsm7.value ? 160 : 70;
    if (isGsm7.value) return segments.value === 1 ? 160 : segments.value * 153;
    return segments.value === 1 ? 70 : segments.value * 67;
});

const costPerSms = computed(() => (segments.value * 0.0083).toFixed(4));

function insertVariable(varKey) {
    const el = messageRef.value;
    const start = el?.selectionStart ?? message.value.length;
    const end = el?.selectionEnd ?? message.value.length;
    message.value = message.value.substring(0, start) + varKey + message.value.substring(end);
    setTimeout(() => {
        el?.focus();
        const pos = start + varKey.length;
        el?.setSelectionRange(pos, pos);
    }, 0);
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
    if (!message.value.trim()) return;
    emit('preview', {
        message: message.value,
        scheduled_at: getScheduledAt(),
    });
}
</script>

<template>
    <div class="bg-white rounded-2xl w-full max-w-2xl shadow-xl max-h-[90vh] flex flex-col">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
            <h3 class="text-lg font-bold text-gray-900">Send SMS</h3>
            <button @click="emit('close')" class="p-1 rounded-lg hover:bg-gray-100"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div class="px-6 py-4 space-y-3">
                <p v-if="recipientLabel" class="text-xs text-gray-400">To: {{ recipientLabel }}</p>

                <!-- Variables -->
                <div v-if="variables.length" class="flex flex-wrap gap-1.5 items-center">
                    <span class="text-xs text-gray-500">Insert variable:</span>
                    <button v-for="v in variables" :key="v.key" @click="insertVariable(v.key)"
                        class="px-2 py-0.5 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors">
                        {{ v.label }}
                    </button>
                </div>

                <!-- Message -->
                <textarea ref="messageRef" v-model="message" rows="6"
                    placeholder="Type your message... Variables like {{first_name}} will be replaced per recipient. &quot;- Runway 7&quot; signature is appended automatically."
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none font-mono"></textarea>

                <!-- Character counter and cost -->
                <div class="flex items-center justify-between text-xs text-gray-500 px-1">
                    <span>{{ charCount }} / {{ maxChars }} chars · {{ encoding }}</span>
                    <span>{{ segments }} segment{{ segments !== 1 ? 's' : '' }} · ${{ costPerSms }}/SMS</span>
                </div>

                <!-- Preview -->
                <div v-if="messagePreview" class="bg-gray-50 rounded-lg p-3 text-xs text-gray-600 whitespace-pre-wrap">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase mb-1">Preview (with signature)</p>{{ messagePreview }}
                </div>

                <!-- Schedule -->
                <div v-if="showSchedule" class="bg-gray-50 rounded-lg p-4 space-y-3">
                    <p class="text-sm font-medium text-gray-700">Schedule SMS</p>
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
                <p class="text-xs text-gray-400 ml-2">SMS will be sent via Twilio.</p>
            </div>
            <div class="flex gap-3">
                <button @click="emit('close')" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button @click="handlePreview" :disabled="!message.trim() || processing"
                    class="px-5 py-2 text-sm font-semibold text-white bg-black hover:bg-gray-800 rounded-lg disabled:opacity-50">
                    {{ processing ? 'Loading...' : 'Preview & Send' }}
                </button>
            </div>
        </div>
    </div>
</template>
