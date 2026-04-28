<script setup>
import { ref, computed, onBeforeUnmount, nextTick } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyle } from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import Image from '@tiptap/extension-image';
import { XMarkIcon, PaperClipIcon, ClockIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    recipientLabel: { type: String, default: '' },
    processing: { type: Boolean, default: false },
    hideSchedule: { type: Boolean, default: false },
    hideBccNote: { type: Boolean, default: false },
    sendLabel: { type: String, default: 'Send' },
    /**
     * Optional merge-tag variables to expose in the toolbar.
     * Each item: { label: string, value: string } — value is the placeholder inserted
     * (e.g. "{{first_name}}"), label is the human-readable name in the dropdown.
     * The backend is responsible for substituting placeholders per recipient before sending.
     */
    variables: { type: Array, default: () => [] },
    /**
     * Optional Email Type selector. Each item: { value: string, label: string }.
     * When populated, a required select shows up between subject and body and the
     * chosen value is included in the emitted send payload as `email_type`.
     */
    emailTypes: { type: Array, default: () => [] },
});

const emit = defineEmits(['send', 'close']);

const subject = ref('');
const emailType = ref('');
const attachments = ref([]);
const fileInput = ref(null);
const showSchedule = ref(false);
const scheduleOption = ref('now');
const scheduleDate = ref('');
const scheduleTime = ref('10:00');

const editor = useEditor({
    extensions: [
        StarterKit,
        Underline,
        Link.configure({ openOnClick: false }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        TextStyle,
        Color,
        Image,
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none focus:outline-none min-h-[200px] px-4 py-3',
        },
    },
});

onBeforeUnmount(() => { editor.value?.destroy(); });

function addAttachment(e) {
    for (const file of e.target.files) {
        if (file.size > 10 * 1024 * 1024) {
            alert(`${file.name} exceeds 10MB limit`);
            continue;
        }
        attachments.value.push(file);
    }
    e.target.value = '';
}

function removeAttachment(index) {
    attachments.value.splice(index, 1);
}

function setLink() {
    const url = prompt('URL:');
    if (url) {
        editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }
}

function addImage() {
    const url = prompt('Image URL:');
    if (url) {
        editor.value.chain().focus().setImage({ src: url }).run();
    }
}

const showVariables = ref(false);
function insertVariable(value) {
    if (!editor.value) return;
    editor.value.chain().focus().insertContent(value).run();
    showVariables.value = false;
}

// Variables en el campo Subject: dropdown propio + inserción en la posición del cursor.
const subjectInput = ref(null);
const showSubjectVariables = ref(false);
function insertSubjectVariable(value) {
    const el = subjectInput.value;
    if (!el) {
        subject.value = (subject.value || '') + value;
    } else {
        const start = el.selectionStart ?? subject.value.length;
        const end   = el.selectionEnd   ?? subject.value.length;
        subject.value = subject.value.slice(0, start) + value + subject.value.slice(end);
        nextTick(() => {
            el.focus();
            const pos = start + value.length;
            el.setSelectionRange(pos, pos);
        });
    }
    showSubjectVariables.value = false;
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

function handleSend() {
    const body = editor.value?.getHTML() || '';
    if (!subject.value.trim() || !body.trim() || body === '<p></p>') return;
    // Si hay emailTypes definidos, exigir que el usuario haya elegido uno.
    if (props.emailTypes.length && !emailType.value) return;

    emit('send', {
        subject: subject.value,
        body: body,
        attachments: attachments.value,
        scheduled_at: getScheduledAt(),
        email_type: emailType.value || null,
    });
}

const formatSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};
</script>

<template>
    <div class="bg-white rounded-2xl w-full max-w-2xl shadow-xl max-h-[90vh] flex flex-col">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
            <h3 class="text-lg font-bold text-gray-900">Send Email</h3>
            <button @click="emit('close')" class="p-1 rounded-lg hover:bg-gray-100"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div class="px-6 py-4 space-y-3">
                <!-- Recipient -->
                <p v-if="recipientLabel" class="text-xs text-gray-400">To: {{ recipientLabel }}</p>

                <!-- Subject -->
                <div class="relative">
                    <input v-model="subject" ref="subjectInput" type="text" placeholder="Subject..."
                        :class="variables.length ? 'pr-12' : 'pr-3'"
                        class="w-full border border-gray-300 rounded-lg pl-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    <button v-if="variables.length" type="button"
                        @click.stop="showSubjectVariables = !showSubjectVariables"
                        class="absolute right-1.5 top-1/2 -translate-y-1/2 px-2 py-1 rounded text-xs font-medium text-[#D4AF37] hover:bg-gray-100"
                        title="Insert variable">
                        {{ '{{' }}
                    </button>
                    <div v-if="showSubjectVariables"
                        class="absolute z-30 right-0 mt-1 top-full bg-white rounded-lg shadow-xl border border-gray-200 py-1 min-w-44 max-h-64 overflow-y-auto">
                        <button v-for="v in variables" :key="v.value"
                            @click="insertSubjectVariable(v.value)"
                            class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50 flex items-center justify-between gap-3">
                            <span class="font-medium text-gray-700">{{ v.label }}</span>
                            <span class="font-mono text-[10px] text-gray-400">{{ v.value }}</span>
                        </button>
                    </div>
                </div>

                <!-- Email Type select -->
                <div v-if="emailTypes.length">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Email Type *</label>
                    <select v-model="emailType"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-black/10">
                        <option value="" disabled>— Select email type —</option>
                        <option v-for="t in emailTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                </div>

                <!-- Toolbar -->
                <div v-if="editor" class="flex flex-wrap gap-0.5 border border-gray-200 rounded-lg px-2 py-1.5 bg-gray-50">
                    <button @click="editor.chain().focus().toggleBold().run()" :class="{ 'bg-gray-200': editor.isActive('bold') }" class="p-1.5 rounded hover:bg-gray-200 text-xs font-bold" title="Bold">B</button>
                    <button @click="editor.chain().focus().toggleItalic().run()" :class="{ 'bg-gray-200': editor.isActive('italic') }" class="p-1.5 rounded hover:bg-gray-200 text-xs italic" title="Italic">I</button>
                    <button @click="editor.chain().focus().toggleUnderline().run()" :class="{ 'bg-gray-200': editor.isActive('underline') }" class="p-1.5 rounded hover:bg-gray-200 text-xs underline" title="Underline">U</button>
                    <button @click="editor.chain().focus().toggleStrike().run()" :class="{ 'bg-gray-200': editor.isActive('strike') }" class="p-1.5 rounded hover:bg-gray-200 text-xs line-through" title="Strikethrough">S</button>
                    <span class="w-px bg-gray-300 mx-1"></span>
                    <button @click="editor.chain().focus().toggleBulletList().run()" :class="{ 'bg-gray-200': editor.isActive('bulletList') }" class="p-1.5 rounded hover:bg-gray-200 text-xs" title="Bullet List">&#8226; List</button>
                    <button @click="editor.chain().focus().toggleOrderedList().run()" :class="{ 'bg-gray-200': editor.isActive('orderedList') }" class="p-1.5 rounded hover:bg-gray-200 text-xs" title="Numbered List">1. List</button>
                    <span class="w-px bg-gray-300 mx-1"></span>
                    <button @click="editor.chain().focus().setTextAlign('left').run()" class="p-1.5 rounded hover:bg-gray-200 text-xs" title="Align Left">Left</button>
                    <button @click="editor.chain().focus().setTextAlign('center').run()" class="p-1.5 rounded hover:bg-gray-200 text-xs" title="Align Center">Center</button>
                    <span class="w-px bg-gray-300 mx-1"></span>
                    <button @click="setLink" :class="{ 'bg-gray-200': editor.isActive('link') }" class="p-1.5 rounded hover:bg-gray-200 text-xs" title="Add Link">Link</button>
                    <button @click="addImage" class="p-1.5 rounded hover:bg-gray-200 text-xs" title="Add Image">Img</button>
                    <span class="w-px bg-gray-300 mx-1"></span>
                    <button @click="editor.chain().focus().setHorizontalRule().run()" class="p-1.5 rounded hover:bg-gray-200 text-xs" title="Horizontal Rule">&#8212;</button>
                    <template v-if="variables.length">
                        <span class="w-px bg-gray-300 mx-1"></span>
                        <div class="relative">
                            <button @click.stop="showVariables = !showVariables"
                                class="px-2 py-1 rounded hover:bg-gray-200 text-xs font-medium text-[#D4AF37] flex items-center gap-1"
                                title="Insert variable">
                                {{ '{{' }} Variable
                            </button>
                            <div v-if="showVariables"
                                class="absolute z-30 mt-1 left-0 bg-white rounded-lg shadow-xl border border-gray-200 py-1 min-w-44 max-h-64 overflow-y-auto">
                                <button v-for="v in variables" :key="v.value"
                                    @click="insertVariable(v.value)"
                                    class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50 flex items-center justify-between gap-3">
                                    <span class="font-medium text-gray-700">{{ v.label }}</span>
                                    <span class="font-mono text-[10px] text-gray-400">{{ v.value }}</span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Editor -->
                <div class="border border-gray-300 rounded-lg overflow-hidden min-h-[200px]">
                    <EditorContent :editor="editor" />
                </div>

                <!-- Extra options slot (e.g. contract toggle) -->
                <slot name="extra-options" />

                <!-- Attachments -->
                <div v-if="attachments.length" class="space-y-1">
                    <div v-for="(file, i) in attachments" :key="i" class="flex items-center gap-2 text-xs bg-gray-50 rounded-lg px-3 py-2">
                        <PaperClipIcon class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" />
                        <span class="text-gray-700 truncate flex-1">{{ file.name }}</span>
                        <span class="text-gray-400">{{ formatSize(file.size) }}</span>
                        <button @click="removeAttachment(i)" class="text-red-400 hover:text-red-600"><XMarkIcon class="w-3.5 h-3.5" /></button>
                    </div>
                </div>

                <!-- Schedule -->
                <div v-if="showSchedule && !hideSchedule" class="bg-gray-50 rounded-lg p-4 space-y-3">
                    <p class="text-sm font-medium text-gray-700">Schedule Email</p>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" v-model="scheduleOption" value="now" class="accent-black" /> Send now
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
                <input ref="fileInput" type="file" multiple class="hidden" @change="addAttachment" />
                <button @click="fileInput.click()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-medium transition-colors"
                    title="Attach File">
                    <PaperClipIcon class="w-3.5 h-3.5" /> Attach
                    <span v-if="attachments.length" class="ml-0.5 px-1.5 py-0 bg-black text-white rounded-full text-[10px]">{{ attachments.length }}</span>
                </button>
                <button v-if="!hideSchedule" @click="showSchedule = !showSchedule"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                    :class="showSchedule ? 'bg-gray-300 text-gray-800' : 'bg-gray-100 hover:bg-gray-200 text-gray-700'"
                    title="Schedule">
                    <ClockIcon class="w-3.5 h-3.5" /> Schedule
                </button>
                <p v-if="!hideBccNote" class="text-xs text-gray-400 ml-2">You'll receive a BCC copy.</p>
            </div>
            <div class="flex gap-3">
                <button @click="emit('close')" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button @click="handleSend"
                    :disabled="processing || (emailTypes.length && !emailType)"
                    class="px-5 py-2 text-sm font-semibold text-white bg-black hover:bg-gray-800 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ processing ? 'Sending...' : (scheduleOption !== 'now' && showSchedule && !hideSchedule ? 'Schedule' : sendLabel) }}
                </button>
            </div>
        </div>
    </div>
</template>

<style>
.tiptap p { margin-bottom: 0.5em; }
.tiptap ul, .tiptap ol { padding-left: 1.5em; margin-bottom: 0.5em; }
.tiptap a { color: #D4AF37; text-decoration: underline; }
.tiptap img { max-width: 100%; border-radius: 8px; margin: 8px 0; }
.tiptap hr { border-top: 1px solid #e5e7eb; margin: 12px 0; }
</style>
