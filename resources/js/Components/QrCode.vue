<script setup>
import { ref, watch, onMounted } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    value:  { type: String, required: true },
    size:   { type: Number, default: 200 },
    color:  { type: String, default: '#000000' },
    bg:     { type: String, default: '#ffffff' },
});

const dataUrl = ref('');

async function generate() {
    if (!props.value) return;
    dataUrl.value = await QRCode.toDataURL(props.value, {
        width:           props.size,
        margin:          2,
        color: {
            dark:  props.color,
            light: props.bg,
        },
    });
}

onMounted(generate);
watch(() => props.value, generate);
</script>

<template>
    <img v-if="dataUrl" :src="dataUrl" :width="size" :height="size" :alt="value" />
    <div v-else :style="{ width: size + 'px', height: size + 'px' }" class="bg-gray-100 animate-pulse rounded" />
</template>
