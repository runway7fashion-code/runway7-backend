<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    conversation: Object,
    messages:     Array,
});

const model    = props.conversation.model;
const designer = props.conversation.designer;
const show     = props.conversation.show;

function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}

function formatTime(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('es', { weekday: 'long', day: 'numeric', month: 'long' });
}

function shouldShowDate(index) {
    if (index === 0) return true;
    const prev = new Date(props.messages[index - 1].created_at).toDateString();
    const curr = new Date(props.messages[index].created_at).toDateString();
    return prev !== curr;
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/operations/chats" class="text-gray-400 hover:text-gray-600 text-sm">Chats</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Conversacion</h2>
            </div>
        </template>

        <div class="max-w-3xl mx-auto">
            <!-- Info de la conversacion -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-6">
                        <!-- Modelo -->
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-100 flex-shrink-0">
                                <img v-if="storageUrl(model?.profile_picture)"
                                    :src="storageUrl(model.profile_picture)" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-xs font-bold text-gray-500">
                                    {{ model?.first_name?.[0] }}{{ model?.last_name?.[0] }}
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ model?.first_name }} {{ model?.last_name }}</p>
                                <p class="text-xs text-gray-400">Modelo</p>
                            </div>
                        </div>

                        <div class="text-gray-300 text-lg">&harr;</div>

                        <!-- Disenador -->
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-100 flex-shrink-0">
                                <img v-if="storageUrl(designer?.profile_picture)"
                                    :src="storageUrl(designer.profile_picture)" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-xs font-bold text-gray-500">
                                    {{ designer?.first_name?.[0] }}{{ designer?.last_name?.[0] }}
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ designer?.first_name }} {{ designer?.last_name }}</p>
                                <p class="text-xs text-gray-400">Disenador</p>
                            </div>
                        </div>
                    </div>

                    <div class="text-right">
                        <p class="text-sm text-gray-700 font-medium">{{ show?.name }}</p>
                        <p class="text-xs text-gray-400">{{ show?.event_day?.event?.name }}</p>
                    </div>
                </div>
            </div>

            <!-- Mensajes -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="p-5 space-y-1 max-h-[600px] overflow-y-auto" id="chatMessages">
                    <div v-if="messages.length === 0" class="text-center text-gray-400 py-16">
                        <ChatBubbleLeftRightIcon class="w-12 h-12 mx-auto mb-3 text-gray-200" />
                        <p class="text-sm">No hay mensajes en esta conversacion.</p>
                    </div>

                    <template v-for="(msg, i) in messages" :key="msg.id">
                        <!-- Separador de fecha -->
                        <div v-if="shouldShowDate(i)" class="flex items-center gap-3 py-3">
                            <div class="flex-1 h-px bg-gray-200"></div>
                            <span class="text-xs text-gray-400 font-medium">{{ formatDate(msg.created_at) }}</span>
                            <div class="flex-1 h-px bg-gray-200"></div>
                        </div>

                        <!-- Mensaje del sistema -->
                        <div v-if="msg.type === 'system'" class="text-center py-2">
                            <p class="text-xs text-gray-400 italic">{{ msg.body }}</p>
                        </div>

                        <!-- Mensaje normal -->
                        <div v-else class="flex gap-2 py-1"
                            :class="msg.sender_id === model?.id ? 'justify-start' : 'justify-end'">
                            <!-- Avatar (solo modelo a la izquierda) -->
                            <div v-if="msg.sender_id === model?.id" class="w-7 h-7 rounded-full overflow-hidden bg-gray-100 flex-shrink-0 mt-1">
                                <img v-if="storageUrl(model?.profile_picture)"
                                    :src="storageUrl(model.profile_picture)" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-[10px] font-bold text-gray-500">
                                    {{ model?.first_name?.[0] }}
                                </div>
                            </div>

                            <div class="max-w-[70%]">
                                <div class="px-3 py-2 rounded-2xl text-sm"
                                    :class="msg.sender_id === model?.id
                                        ? 'bg-gray-100 text-gray-800 rounded-tl-sm'
                                        : 'bg-black text-white rounded-tr-sm'">
                                    <!-- Imagen si es tipo image -->
                                    <img v-if="msg.type === 'image' && msg.image_url"
                                        :src="msg.image_url" class="rounded-lg max-w-full mb-1" />
                                    <p>{{ msg.body }}</p>
                                </div>
                                <div class="flex items-center gap-1 mt-0.5 px-1"
                                    :class="msg.sender_id === model?.id ? '' : 'justify-end'">
                                    <span class="text-[10px] text-gray-400">{{ formatTime(msg.created_at) }}</span>
                                    <span v-if="msg.is_read" class="text-[10px] text-blue-400">leido</span>
                                </div>
                            </div>

                            <!-- Avatar (disenador a la derecha) -->
                            <div v-if="msg.sender_id === designer?.id" class="w-7 h-7 rounded-full overflow-hidden bg-gray-100 flex-shrink-0 mt-1">
                                <img v-if="storageUrl(designer?.profile_picture)"
                                    :src="storageUrl(designer.profile_picture)" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-[10px] font-bold text-gray-500">
                                    {{ designer?.first_name?.[0] }}
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Barra inferior (solo lectura) -->
                <div class="border-t border-gray-200 px-5 py-3 bg-gray-50 flex items-center justify-center">
                    <p class="text-xs text-gray-400">Vista de solo lectura — los mensajes se envian desde la app.</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
