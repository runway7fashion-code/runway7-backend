<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { PlusIcon, XMarkIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    conversations: Object,
    events:        Array,
    users:         Array,
    filters:       Object,
});

const search = ref(props.filters?.search ?? '');
const event  = ref(props.filters?.event  ?? '');
const contextType = ref(props.filters?.context_type ?? '');

let timer = null;
function applyFilters() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/admin/operations/chats', {
            search: search.value || undefined,
            event:  event.value  || undefined,
            context_type: contextType.value || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}

watch([search, event, contextType], applyFilters);

// New chat modal
const showNewChatModal = ref(false);
const userSearch = ref('');
const userRoleFilter = ref('');
const newChatForm = useForm({ user_id: '', message: '' });

const filteredUsers = computed(() => {
    let list = props.users || [];
    if (userRoleFilter.value) {
        list = list.filter(u => u.role === userRoleFilter.value);
    }
    if (userSearch.value) {
        const s = userSearch.value.toLowerCase();
        list = list.filter(u =>
            `${u.first_name} ${u.last_name}`.toLowerCase().includes(s) ||
            u.email.toLowerCase().includes(s)
        );
    }
    return list.slice(0, 20);
});

function selectUser(user) {
    newChatForm.user_id = user.id;
}

function startChat() {
    if (!newChatForm.user_id) return;
    newChatForm.post('/admin/operations/chats', {
        onSuccess: () => { showNewChatModal.value = false; newChatForm.reset(); userSearch.value = ''; },
    });
}

function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}

function timeAgo(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    const now  = new Date();
    const diff = Math.floor((now - date) / 1000);
    if (diff < 60)    return 'just now';
    if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return date.toLocaleDateString('en', { day: 'numeric', month: 'short' });
}

const roleLabels = {
    model: 'Model', designer: 'Designer', media: 'Media', volunteer: 'Volunteer',
    staff: 'Staff', assistant: 'Assistant', admin: 'Admin', operation: 'Operation',
    sales: 'Sales', creative: 'Creative', tickets_manager: 'Tickets',
};
const roleColors = {
    model: 'bg-blue-100 text-blue-700', designer: 'bg-purple-100 text-purple-700',
    media: 'bg-pink-100 text-pink-700', volunteer: 'bg-green-100 text-green-700',
    operation: 'bg-amber-100 text-amber-700', sales: 'bg-indigo-100 text-indigo-700',
    creative: 'bg-fuchsia-100 text-fuchsia-700', tickets_manager: 'bg-orange-100 text-orange-700',
};
const contextLabels = { casting: 'Casting', material: 'Material' };
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Chats</h2>
        </template>

        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-2xl font-bold text-gray-900">Chats</h3>
                <p class="text-gray-500 text-sm mt-1">{{ conversations.total }} conversations</p>
            </div>
            <button @click="showNewChatModal = true"
                class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors flex items-center gap-1.5">
                <PlusIcon class="w-4 h-4" /> New Chat
            </button>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-5 flex flex-wrap gap-3">
            <input v-model="search" type="text" placeholder="Search by name..."
                class="flex-1 min-w-48 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
            <select v-model="contextType" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                <option value="">All types</option>
                <option value="casting">Casting</option>
                <option value="material">Material</option>
                <option value="general">General</option>
            </select>
            <select v-model="event" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                <option value="">All events</option>
                <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-5 py-3 font-medium text-gray-500">Participant A</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Participant B</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Type</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Last message</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Messages</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="conversations.data.length === 0">
                        <td colspan="6" class="text-center text-gray-400 py-12">No conversations yet.</td>
                    </tr>
                    <tr v-for="c in conversations.data" :key="c.id" class="hover:bg-gray-50 transition-colors cursor-pointer" @click="router.visit(`/admin/operations/chats/${c.id}`)">
                        <!-- User A -->
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-100 flex-shrink-0">
                                    <img v-if="storageUrl(c.user_a?.profile_picture)" :src="storageUrl(c.user_a.profile_picture)" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-xs font-bold text-gray-500">
                                        {{ c.user_a?.first_name?.[0] }}{{ c.user_a?.last_name?.[0] }}
                                    </div>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-800">{{ c.user_a?.first_name }} {{ c.user_a?.last_name }}</span>
                                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium" :class="roleColors[c.user_a?.role] || 'bg-gray-100 text-gray-500'">{{ roleLabels[c.user_a?.role] || c.user_a?.role }}</span>
                                </div>
                            </div>
                        </td>
                        <!-- User B -->
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-100 flex-shrink-0">
                                    <img v-if="storageUrl(c.user_b?.profile_picture)" :src="storageUrl(c.user_b.profile_picture)" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-xs font-bold text-gray-500">
                                        {{ c.user_b?.first_name?.[0] }}{{ c.user_b?.last_name?.[0] }}
                                    </div>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-800">{{ c.user_b?.first_name }} {{ c.user_b?.last_name }}</span>
                                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium" :class="roleColors[c.user_b?.role] || 'bg-gray-100 text-gray-500'">{{ roleLabels[c.user_b?.role] || c.user_b?.role }}</span>
                                </div>
                            </div>
                        </td>
                        <!-- Type -->
                        <td class="px-4 py-3">
                            <span v-if="c.context_type" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-medium">{{ contextLabels[c.context_type] || c.context_type }}</span>
                            <span v-else class="text-xs text-gray-400">General</span>
                            <p v-if="c.show" class="text-xs text-gray-400 mt-0.5">{{ c.show?.name }}</p>
                        </td>
                        <!-- Last message -->
                        <td class="px-4 py-3">
                            <p v-if="c.last_message" class="text-sm text-gray-600 truncate max-w-48">{{ c.last_message.body }}</p>
                            <p v-else class="text-xs text-gray-400 italic">No messages</p>
                            <p v-if="c.last_message_at" class="text-xs text-gray-400 mt-0.5">{{ timeAgo(c.last_message_at) }}</p>
                        </td>
                        <!-- Messages count -->
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-medium">{{ c.messages_count }}</span>
                        </td>
                        <!-- Actions -->
                        <td class="px-4 py-3" @click.stop>
                            <Link :href="`/admin/operations/chats/${c.id}`"
                                class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                View
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="conversations.last_page > 1" class="border-t border-gray-100 px-5 py-3 flex items-center justify-between text-sm text-gray-500">
                <span>{{ conversations.from }}-{{ conversations.to }} of {{ conversations.total }}</span>
                <div class="flex gap-1">
                    <Link v-if="conversations.prev_page_url" :href="conversations.prev_page_url" class="px-3 py-1 border border-gray-200 rounded-lg hover:bg-gray-50">Prev</Link>
                    <Link v-if="conversations.next_page_url" :href="conversations.next_page_url" class="px-3 py-1 border border-gray-200 rounded-lg hover:bg-gray-50">Next</Link>
                </div>
            </div>
        </div>

        <!-- New Chat Modal -->
        <Teleport to="body">
            <div v-if="showNewChatModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showNewChatModal = false">
                <div class="bg-white rounded-2xl w-full max-w-lg shadow-xl max-h-[80vh] flex flex-col">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
                        <h3 class="text-lg font-bold text-gray-900">New Chat</h3>
                        <button @click="showNewChatModal = false" class="p-1 rounded-lg hover:bg-gray-100"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
                    </div>
                    <div class="px-6 py-4 space-y-3 flex-shrink-0">
                        <div class="flex gap-2">
                            <input v-model="userSearch" type="text" placeholder="Search users..."
                                class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <select v-model="userRoleFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">All roles</option>
                                <option value="designer">Designer</option>
                                <option value="model">Model</option>
                                <option value="media">Media</option>
                                <option value="volunteer">Volunteer</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto px-6">
                        <div v-for="user in filteredUsers" :key="user.id"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg cursor-pointer transition-colors"
                            :class="newChatForm.user_id === user.id ? 'bg-black text-white' : 'hover:bg-gray-50'"
                            @click="selectUser(user)">
                            <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold"
                                :class="newChatForm.user_id === user.id ? 'bg-gray-700 text-white' : 'text-gray-500'">
                                {{ user.first_name?.[0] }}{{ user.last_name?.[0] }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium truncate">{{ user.first_name }} {{ user.last_name }}</p>
                                <p class="text-xs truncate" :class="newChatForm.user_id === user.id ? 'text-gray-300' : 'text-gray-500'">{{ user.email }}</p>
                            </div>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-medium"
                                :class="newChatForm.user_id === user.id ? 'bg-gray-700 text-gray-200' : (roleColors[user.role] || 'bg-gray-100 text-gray-500')">
                                {{ roleLabels[user.role] || user.role }}
                            </span>
                        </div>
                        <p v-if="filteredUsers.length === 0" class="text-center text-gray-400 text-sm py-6">No users found.</p>
                    </div>
                    <div class="px-6 py-3 border-t border-gray-100 space-y-3 flex-shrink-0">
                        <textarea v-model="newChatForm.message" rows="2" placeholder="First message (optional)..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                        <div class="flex justify-end gap-3">
                            <button @click="showNewChatModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                            <button @click="startChat" :disabled="!newChatForm.user_id || newChatForm.processing"
                                class="px-5 py-2 text-sm font-semibold text-white bg-black hover:bg-gray-800 rounded-lg disabled:opacity-50 flex items-center gap-1">
                                <ChatBubbleLeftRightIcon class="w-4 h-4" />
                                {{ newChatForm.processing ? 'Starting...' : 'Start Chat' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
