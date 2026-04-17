<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { ChatBubbleLeftRightIcon, PaperAirplaneIcon } from '@heroicons/vue/24/outline';
import { initEcho } from '@/echo.js';

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

// Typing indicator state
const typingUserId = ref(null);
let typingTimeout = null;
let lastTypingSentAt = 0;
let echo = null;
let channel = null;

function sendMessage() {
    if (!messageForm.body.trim()) return;
    messageForm.post(`/admin/operations/chats/${props.conversation.id}/messages`, {
        preserveScroll: true,
        onSuccess: () => {
            messageForm.reset();
            nextTick(() => scrollToBottom());
            emitTyping(false);
        },
    });
}

function scrollToBottom() {
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
}

function emitTyping(isTyping) {
    const now = Date.now();
    // Throttle "typing=true" to once every 3s; always send "typing=false" immediately.
    if (isTyping && now - lastTypingSentAt < 3000) return;
    lastTypingSentAt = now;
    window.axios.post(`/api/v1/chat/conversations/${props.conversation.id}/typing`, { is_typing: isTyping })
        .catch(() => {});
}

function onInput() {
    if (messageForm.body.trim().length > 0) emitTyping(true);
}

const typingUserName = () => {
    if (!typingUserId.value) return '';
    const u = typingUserId.value === userA?.id ? userA : userB;
    return u?.first_name || 'Someone';
};

onMounted(() => {
    scrollToBottom();

    echo = initEcho(page.props.reverb);
    if (!echo) return;

    channel = echo.private(`conversation.${props.conversation.id}`);
    channel.listen('.UserTyping', (e) => {
        if (!e || e.user_id === currentUser?.id) return;
        if (e.is_typing) {
            typingUserId.value = e.user_id;
            clearTimeout(typingTimeout);
            typingTimeout = setTimeout(() => { typingUserId.value = null; }, 5000);
        } else {
            typingUserId.value = null;
            clearTimeout(typingTimeout);
        }
    });
});

onBeforeUnmount(() => {
    clearTimeout(typingTimeout);
    if (channel) {
        try { echo?.leave(`conversation.${props.conversation.id}`); } catch {}
    }
});

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

function deliveryState(msg) {
    if (msg.is_read) return 'read';
    if (msg.delivered_at) return 'delivered';
    return 'sent';
}

function presenceLabel(user) {
    if (!user) return '';
    if (user.is_online) return 'Online';
    if (!user.last_seen_at) return '';
    const last = new Date(user.last_seen_at);
    const now  = new Date();
    const diffMin = Math.floor((now - last) / 60000);
    if (diffMin < 60) return `Last seen ${diffMin} min ago`;
    if (diffMin < 1440) {
        return `Last seen today at ${last.toLocaleTimeString('en', { hour: '2-digit', minute: '2-digit' })}`;
    }
    if (diffMin < 2880) {
        return `Last seen yesterday at ${last.toLocaleTimeString('en', { hour: '2-digit', minute: '2-digit' })}`;
    }
    return `Last seen on ${last.toLocaleDateString('en', { day: 'numeric', month: 'short' })}`;
}
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
                                <p class="text-sm font-semibold text-gray-900 flex items-center gap-1.5">
                                    {{ userA?.first_name }} {{ userA?.last_name }}
                                    <span v-if="userA?.is_online" class="w-2 h-2 rounded-full bg-green-500" title="Online"></span>
                                </p>
                                <p class="text-xs text-gray-400">
                                    {{ roleLabels[userA?.role] || userA?.role }}
                                    <span v-if="presenceLabel(userA)" class="ml-1">· {{ presenceLabel(userA) }}</span>
                                </p>
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
                                <p class="text-sm font-semibold text-gray-900 flex items-center gap-1.5">
                                    {{ userB?.first_name }} {{ userB?.last_name }}
                                    <span v-if="userB?.is_online" class="w-2 h-2 rounded-full bg-green-500" title="Online"></span>
                                </p>
                                <p class="text-xs text-gray-400">
                                    {{ roleLabels[userB?.role] || userB?.role }}
                                    <span v-if="presenceLabel(userB)" class="ml-1">· {{ presenceLabel(userB) }}</span>
                                </p>
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

                                    <!-- Ticks (sent / delivered / read) -->
                                    <span class="inline-flex items-center ml-0.5" :title="deliveryState(msg)">
                                        <!-- Sent: single check -->
                                        <svg v-if="deliveryState(msg) === 'sent'" class="w-3 h-3 text-gray-400" fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.5l3 3 7-7" />
                                        </svg>
                                        <!-- Delivered: double check gray -->
                                        <svg v-else-if="deliveryState(msg) === 'delivered'" class="w-4 h-3 text-gray-400" fill="none" viewBox="0 0 20 16" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2 8.5l3 3 7-7" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 11.5l1 1 7-7" />
                                        </svg>
                                        <!-- Read: double check gold -->
                                        <svg v-else class="w-4 h-3" fill="none" viewBox="0 0 20 16" stroke="#D4AF37" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2 8.5l3 3 7-7" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 11.5l1 1 7-7" />
                                        </svg>
                                    </span>
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

                <!-- Typing indicator -->
                <div v-if="typingUserId" class="px-5 py-1.5 border-t border-gray-100 bg-gray-50">
                    <p class="text-[11px] text-gray-500 italic">
                        <span class="inline-block w-1 h-1 rounded-full bg-gray-400 animate-pulse mr-0.5"></span>
                        <span class="inline-block w-1 h-1 rounded-full bg-gray-400 animate-pulse mr-0.5" style="animation-delay: 150ms"></span>
                        <span class="inline-block w-1 h-1 rounded-full bg-gray-400 animate-pulse mr-1" style="animation-delay: 300ms"></span>
                        {{ typingUserName() }} is typing…
                    </p>
                </div>

                <!-- Message input -->
                <div class="border-t border-gray-200 px-5 py-3 bg-gray-50">
                    <form @submit.prevent="sendMessage" class="flex gap-2">
                        <input v-model="messageForm.body" type="text" placeholder="Type a message..."
                            class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10"
                            @input="onInput"
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
