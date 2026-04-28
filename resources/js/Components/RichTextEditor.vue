<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    minHeight: { type: String, default: '80px' },
});
const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Underline,
        Link.configure({ openOnClick: false, autolink: true }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none focus:outline-none px-3 py-2',
            style: `min-height: ${props.minHeight}`,
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

// Keep editor in sync if parent resets v-model externally (e.g. cancel/save).
watch(() => props.modelValue, (val) => {
    if (!editor.value) return;
    if (editor.value.getHTML() !== val) {
        editor.value.commands.setContent(val || '', false);
    }
});

onBeforeUnmount(() => editor.value?.destroy());

function setLink() {
    if (!editor.value) return;
    const previousUrl = editor.value.getAttributes('link').href;
    const url = prompt('URL:', previousUrl || 'https://');
    if (url === null) return;
    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }
    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}
</script>

<template>
    <div class="rich-text-editor">
        <div v-if="editor" class="flex flex-wrap gap-0.5 border border-gray-200 border-b-0 rounded-t-lg px-2 py-1.5 bg-gray-50">
            <button type="button" @click="editor.chain().focus().toggleBold().run()" :class="{ 'bg-gray-200': editor.isActive('bold') }" class="p-1.5 rounded hover:bg-gray-200 text-xs font-bold w-7" title="Bold">B</button>
            <button type="button" @click="editor.chain().focus().toggleItalic().run()" :class="{ 'bg-gray-200': editor.isActive('italic') }" class="p-1.5 rounded hover:bg-gray-200 text-xs italic w-7" title="Italic">I</button>
            <button type="button" @click="editor.chain().focus().toggleUnderline().run()" :class="{ 'bg-gray-200': editor.isActive('underline') }" class="p-1.5 rounded hover:bg-gray-200 text-xs underline w-7" title="Underline">U</button>
            <button type="button" @click="editor.chain().focus().toggleStrike().run()" :class="{ 'bg-gray-200': editor.isActive('strike') }" class="p-1.5 rounded hover:bg-gray-200 text-xs line-through w-7" title="Strikethrough">S</button>
            <span class="w-px bg-gray-300 mx-1"></span>
            <button type="button" @click="editor.chain().focus().toggleBulletList().run()" :class="{ 'bg-gray-200': editor.isActive('bulletList') }" class="px-1.5 py-1 rounded hover:bg-gray-200 text-xs" title="Bullet list">&#8226; List</button>
            <button type="button" @click="editor.chain().focus().toggleOrderedList().run()" :class="{ 'bg-gray-200': editor.isActive('orderedList') }" class="px-1.5 py-1 rounded hover:bg-gray-200 text-xs" title="Numbered list">1. List</button>
            <span class="w-px bg-gray-300 mx-1"></span>
            <button type="button" @click="setLink" :class="{ 'bg-gray-200': editor.isActive('link') }" class="px-1.5 py-1 rounded hover:bg-gray-200 text-xs" title="Add link">Link</button>
        </div>
        <div class="border border-gray-200 rounded-b-lg bg-white">
            <EditorContent :editor="editor" :placeholder="placeholder" />
        </div>
    </div>
</template>

<style>
.rich-text-editor .tiptap p { margin: 0 0 0.5em 0; }
.rich-text-editor .tiptap p:last-child { margin-bottom: 0; }
.rich-text-editor .tiptap a { color: #D4AF37; text-decoration: underline; }
.rich-text-editor .tiptap ul, .rich-text-editor .tiptap ol { padding-left: 1.5em; margin: 0 0 0.5em 0; }
.rich-text-editor .tiptap ul { list-style-type: disc; }
.rich-text-editor .tiptap ol { list-style-type: decimal; }
.rich-text-editor .tiptap li > p { margin: 0; }
.rich-text-editor .tiptap strong { font-weight: 600; color: #111827; }
.rich-text-editor .tiptap p.is-editor-empty:first-child::before {
    color: #9ca3af;
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}
</style>
