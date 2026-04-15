<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, nextTick, onMounted } from 'vue';
import { ChatBubbleLeftRightIcon, PaperAirplaneIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    conversation: Object,
    messages:     Array,
});

const page = usePage();
const currentUser = page.props.auth?.user;

const userA = props.conversation.user_a;
const userB = props.conversation.user_b;
const show  = props.conversation.show;

const messageForm = useForm({ body: '' });
const chatContainer = ref(null);

function sendMessage() {
    if (!messageForm.body.trim()) return;
    messageForm.post(`/admin/operations/chats/${props.conversation.id}/messages`, {
        preserveScroll: true,
        onSuccess: () => {
            messageForm.reset();
            nextTick(() => scrollToBottom());
        },
    });
}

function scrollToBottom() {
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
}

onMounted(() => scrollToBottom());

function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}

function formatTime(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleTimeString('en', { hour: '2-digit', minute: '2-digit' });
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en', { weekday: 'long', day: 'numeric', month: 'long' });
}

function shouldShowDate(index) {
    if (index === 0) return true;
    const prev = new Date(props.messages[index - 1].created_at).toDateString();
    const curr = new Date(props.messages[index].created_at).toDateString();
    return prev !== curr;
}

const roleLabels = {
    model: 'Model', designer: 'Designer', media: 'Media', volunteer: 'Volunteer',
    operation: 'Operation', sales: 'Sales', creative: 'Creative', tickets_manager: 'Tickets', admin: 'Admin',
};
const contextLabels = { casting: 'Casting Chat', material: 'Material Chat' };
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/operations/chats" class="text-gray-400 hover:text-gray-600 text-sm">Chats</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Conversation</h2>
            </div>
        </template>

        <div class="max-w-3xl mx-auto">
            <!-- Conversation info -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-6">
                        <!-- User A -->
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-100 flex-shrink-0">
                                <img v-if="storageUrl(userA?.profile_picture)" :src="storageUrl(userA.profile_picture)" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-xs font-bold text-gray-500">
                                    {{ userA?.first_name?.[0] }}{{ userA?.last_name?.[0] }}
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ userA?.first_name }} {{ userA?.last_name }}</p>
                                <p class="text-xs text-gray-400">{{ roleLabels[userA?.role] || userA?.role }}</p>
                            </div>
                        </div>

                        <div class="text-gray-300 text-lg">&harr;</div>

                        <!-- User B -->
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-100 flex-shrink-0">
                                <img v-if="storageUrl(userB?.profile_picture)" :src="storageUrl(userB.profile_picture)" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-xs font-bold text-gray-500">
                                    {{ userB?.first_name?.[0] }}{{ userB?.last_name?.[0] }}
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ userB?.first_name }} {{ userB?.last_name }}</p>
                                <p class="text-xs text-gray-400">{{ roleLabels[userB?.role] || userB?.role }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="text-right">
                        <span v-if="conversation.context_type" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-medium">
                            {{ contextLabels[conversation.context_type] || conversation.context_type }}
                        </span>
                        <p v-if="show" class="text-sm text-gray-700 font-medium mt-1">{{ show?.name }}</p>
                        <p v-if="show" class="text-xs text-gray-400">{{ show?.event_day?.event?.name }}</p>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div ref="chatContainer" class="p-5 space-y-1 max-h-[500px] overflow-y-auto">
                    <div v-if="messages.length === 0" class="text-center text-gray-400 py-16">
                        <ChatBubbleLeftRightIcon class="w-12 h-12 mx-auto mb-3 text-gray-200" />
                        <p class="text-sm">No messages yet. Send the first one below.</p>
                    </div>

                    <template v-for="(msg, i) in messages" :key="msg.id">
                        <!-- Date separator -->
                        <div v-if="shouldShowDate(i)" class="flex items-center gap-3 py-3">
                            <div class="flex-1 h-px bg-gray-200"></div>
                            <span class="text-xs text-gray-400 font-medium">{{ formatDate(msg.created_at) }}</span>
                            <div class="flex-1 h-px bg-gray-200"></div>
                        </div>

                        <!-- System message -->
                        <div v-if="msg.type === 'system'" class="text-center py-2">
                            <p class="text-xs text-gray-400 italic">{{ msg.body }}</p>
                        </div>

                        <!-- Normal message -->
                        <div v-else class="flex gap-2 py-1"
                            :class="msg.sender_id === userA?.id ? 'justify-start' : 'justify-end'">
                            <!-- Avatar left -->
                            <div v-if="msg.sender_id === userA?.id" class="w-7 h-7 rounded-full overflow-hidden bg-gray-100 flex-shrink-0 mt-1">
                                <img v-if="storageUrl(userA?.profile_picture)" :src="storageUrl(userA.profile_picture)" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-[10px] font-bold text-gray-500">{{ userA?.first_name?.[0] }}</div>
                            </div>

                            <div class="max-w-[70%]">
                                <div class="px-3 py-2 rounded-2xl text-sm"
                                    :class="msg.sender_id === userA?.id ? 'bg-gray-100 text-gray-800 rounded-tl-sm' : 'bg-black text-white rounded-tr-sm'">
                                    <img v-if="msg.type === 'image' && msg.image_url" :src="msg.image_url" class="rounded-lg max-w-full mb-1" />
                                    <p>{{ msg.body }}</p>
                                </div>
                                <div class="flex items-center gap-1 mt-0.5 px-1" :class="msg.sender_id === userA?.id ? '' : 'justify-end'">
                                    <span class="text-[10px] text-gray-400">{{ formatTime(msg.created_at) }}</span>
                                    <span v-if="msg.sender?.first_name" class="text-[10px] text-gray-400">· {{ msg.sender.first_name }}</span>
                                    <span v-if="msg.is_read" class="text-[10px] text-blue-400">read</span>
                                </div>
                            </div>

                            <!-- Avatar right -->
                            <div v-if="msg.sender_id === userB?.id" class="w-7 h-7 rounded-full overflow-hidden bg-gray-100 flex-shrink-0 mt-1">
                                <img v-if="storageUrl(userB?.profile_picture)" :src="storageUrl(userB.profile_picture)" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-[10px] font-bold text-gray-500">{{ userB?.first_name?.[0] }}</div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Message input -->
                <div class="border-t border-gray-200 px-5 py-3 bg-gray-50">
                    <form @submit.prevent="sendMessage" class="flex gap-2">
                        <input v-model="messageForm.body" type="text" placeholder="Type a message..."
                            class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10"
                            @keyup.enter="sendMessage" />
                        <button type="submit" :disabled="!messageForm.body.trim() || messageForm.processing"
                            class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-50 flex items-center gap-1">
                            <PaperAirplaneIcon class="w-4 h-4" />
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
