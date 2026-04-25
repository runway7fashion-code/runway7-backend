<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, nextTick, onMounted, onBeforeUnmount, watch, computed } from 'vue';
import { ChatBubbleLeftRightIcon, PaperAirplaneIcon, ArrowsRightLeftIcon } from '@heroicons/vue/24/outline';
import { initEcho } from '@/echo.js';

const props = defineProps({
    conversation: Object,
    messages:     Array,
});

const page = usePage();
const currentUser = page.props.auth?.user;

// Reactive to prop updates — a reassignment swaps user_a/user_b on the server,
// Inertia reloads the `conversation` prop, and these computeds re-evaluate so
// the UI (input enabled/disabled, header names, ticks) updates live.
const userA = computed(() => props.conversation.user_a);
const userB = computed(() => props.conversation.user_b);
const show  = computed(() => props.conversation.show);
const isGroup = computed(() => !!props.conversation.is_group);
const groupParticipants = computed(() => props.conversation.participants ?? []);

const messages = ref([...props.messages]);
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

function appendMessage(msg) {
    if (messages.value.some(m => m.id === msg.id)) return;
    messages.value.push(msg);
    nextTick(() => scrollToBottom());
}

// Keep local `messages` ref in sync when Inertia delivers new props (e.g. after own send)
watch(() => props.messages, (newList) => {
    if (!Array.isArray(newList)) return;
    for (const m of newList) appendMessage(m);
}, { deep: false });

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
    const u = typingUserId.value === userA.value?.id ? userA.value : userB.value;
    return u?.first_name || 'Someone';
};

const isParticipant = computed(() => {
    if (!currentUser) return false;
    if (isGroup.value) {
        return groupParticipants.value.some(p => p.user_id === currentUser.id);
    }
    return currentUser.id === userA.value?.id || currentUser.id === userB.value?.id;
});

function markAsRead() {
    if (!isParticipant.value) return;
    window.axios.post(`/api/v1/chat/conversations/${props.conversation.id}/read`).catch(() => {});
}

function focusChat() {
    if (!isParticipant.value) return;
    window.axios.post(`/api/v1/chat/conversations/${props.conversation.id}/focus`).catch(() => {});
}

function blurChat() {
    if (!isParticipant.value) return;
    window.axios.post(`/api/v1/chat/presence/blur`).catch(() => {});
}

let presenceHeartbeat = null;

onMounted(() => {
    scrollToBottom();

    // Mark existing messages as read on open (only if current user is a participant)
    markAsRead();
    focusChat();
    // Heartbeat — refresh active presence every 30s while the chat is open
    presenceHeartbeat = setInterval(focusChat, 30_000);

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

    channel.listen('.NewMessage', (e) => {
        if (!e || e.sender_id === currentUser?.id) return;
        appendMessage(e);
        // Clear typing indicator from this sender
        if (typingUserId.value === e.sender_id) {
            typingUserId.value = null;
            clearTimeout(typingTimeout);
        }
        // Chat is open — mark as read immediately (also backfills delivered_at server-side)
        markAsRead();
    });

    channel.listen('.MessagesRead', (e) => {
        if (!e) return;
        messages.value = messages.value.map(m =>
            m.sender_id !== e.reader_id && !m.is_read
                ? { ...m, is_read: true, read_at: e.read_at, delivered_at: m.delivered_at || e.read_at }
                : m
        );
    });

    channel.listen('.MessagesDelivered', (e) => {
        if (!e) return;
        messages.value = messages.value.map(m =>
            m.sender_id !== e.recipient_id && !m.delivered_at
                ? { ...m, delivered_at: e.delivered_at }
                : m
        );
    });
});

onBeforeUnmount(() => {
    clearTimeout(typingTimeout);
    clearInterval(presenceHeartbeat);
    blurChat();
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
    const prev = new Date(messages.value[index - 1].created_at).toDateString();
    const curr = new Date(messages.value[index].created_at).toDateString();
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

// Reassignment — only makes sense when there's an operation/admin participant
const INTERNAL_ROLES = ['admin', 'operation', 'sales', 'creative', 'tickets_manager', 'accounting', 'marketing', 'public_relations', 'sponsorship'];
const canReassign = computed(() =>
    ['admin', 'operation'].includes(currentUser?.role) &&
    (INTERNAL_ROLES.includes(userA.value?.role) || INTERNAL_ROLES.includes(userB.value?.role))
);
const showReassignPanel = ref(false);
const reassignAgents = ref([]);
const reassignLoading = ref(false);
const reassignError = ref('');

async function openReassign() {
    showReassignPanel.value = !showReassignPanel.value;
    if (!showReassignPanel.value) return;
    reassignLoading.value = true;
    reassignError.value = '';
    try {
        const { data } = await window.axios.get('/admin/operations/chats/support-assignments');
        const currentOpId = INTERNAL_ROLES.includes(userA.value?.role) ? userA.value.id : userB.value?.id;
        reassignAgents.value = (data.agents || []).filter(a => a.id !== currentOpId);
    } catch (e) {
        reassignError.value = e?.response?.data?.message || 'Error loading agents.';
    } finally {
        reassignLoading.value = false;
    }
}

async function reassignTo(agentId) {
    try {
        await window.axios.post(`/admin/operations/chats/${props.conversation.id}/reassign`, { user_id: agentId });
        showReassignPanel.value = false;
        router.reload({ only: ['conversation', 'messages'] });
    } catch (e) {
        reassignError.value = e?.response?.data?.message || 'Error reassigning.';
    }
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
            <!-- Group info (read-only for admin/operation) -->
            <div v-if="isGroup" class="bg-white rounded-2xl border border-gray-200 p-5 mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-black text-white flex items-center justify-center text-sm font-bold flex-shrink-0">
                        {{ (conversation.name || 'G').split(' ').map(s => s[0]).slice(0,2).join('').toUpperCase() }}
                    </div>
                    <div class="flex-1">
                        <p class="text-base font-semibold text-gray-900">{{ conversation.name }}</p>
                        <p class="text-xs text-gray-500">Group · {{ groupParticipants.length }} members · created by {{ conversation.creator?.first_name }} {{ conversation.creator?.last_name }}</p>
                    </div>
                    <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-medium">Group</span>
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    <span v-for="p in groupParticipants" :key="p.id"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[11px] bg-gray-50 border border-gray-200 text-gray-700">
                        <span class="w-4 h-4 rounded-full bg-gray-200 flex items-center justify-center text-[8px] font-bold">{{ p.user?.first_name?.[0] }}</span>
                        {{ p.user?.first_name }} {{ p.user?.last_name }}
                        <span v-if="p.role === 'admin'" class="text-[9px] uppercase text-amber-600 font-semibold">admin</span>
                    </span>
                </div>
            </div>

            <!-- 1:1 Conversation info -->
            <div v-else class="bg-white rounded-2xl border border-gray-200 p-5 mb-5">
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

                        <!-- Reassign -->
                        <div v-if="canReassign" class="relative mt-2 inline-block text-left">
                            <button @click="openReassign"
                                class="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-700">
                                <ArrowsRightLeftIcon class="w-3.5 h-3.5" /> Reassign
                            </button>
                            <div v-if="showReassignPanel"
                                class="absolute right-0 mt-1 w-64 bg-white border border-gray-200 rounded-xl shadow-lg z-20">
                                <div class="px-3 py-2 border-b border-gray-100 text-[11px] text-gray-500 uppercase tracking-wide">Assign to</div>
                                <div v-if="reassignLoading" class="px-4 py-4 text-center text-gray-400 text-xs">Loading…</div>
                                <div v-else-if="reassignError" class="px-4 py-4 text-center text-red-500 text-xs">{{ reassignError }}</div>
                                <div v-else-if="reassignAgents.length === 0" class="px-4 py-4 text-center text-gray-400 text-xs">No other agents available.</div>
                                <div v-else class="max-h-56 overflow-y-auto">
                                    <button v-for="a in reassignAgents" :key="a.id"
                                        @click="reassignTo(a.id)"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center text-[10px] font-bold text-gray-500">
                                            {{ a.first_name?.[0] }}{{ a.last_name?.[0] }}
                                        </div>
                                        {{ a.first_name }} {{ a.last_name }}
                                    </button>
                                </div>
                            </div>
                        </div>
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

                                    <!-- Ticks (sent / delivered / read) — only for own messages -->
                                    <span v-if="msg.sender_id === currentUser?.id" class="inline-flex items-center ml-0.5" :title="deliveryState(msg)">
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

                <!-- Message input (only for participants) -->
                <div v-if="isParticipant" class="border-t border-gray-200 px-5 py-3 bg-gray-50">
                    <form @submit.prevent="sendMessage" class="flex gap-2">
                        <input v-model="messageForm.body" type="text" placeholder="Type a message..."
                            class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10"
                            @input="onInput" />
                        <button type="submit" :disabled="!messageForm.body.trim() || messageForm.processing"
                            class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-50 flex items-center gap-1">
                            <PaperAirplaneIcon class="w-4 h-4" />
                        </button>
                    </form>
                </div>
                <div v-else class="border-t border-gray-200 px-5 py-3 bg-gray-50 text-center">
                    <p class="text-xs text-gray-400 italic">Read-only — you are not a participant of this conversation.</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
