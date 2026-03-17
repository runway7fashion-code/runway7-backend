<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import {
    HomeIcon,
    CalendarDaysIcon,
    UserIcon,
    PaintBrushIcon,
    ChatBubbleLeftRightIcon,
    PhotoIcon,
    UsersIcon,
    TicketIcon,
    PresentationChartBarIcon,
    BanknotesIcon,
    ExclamationTriangleIcon,
    ClipboardDocumentListIcon,
    ArrowTrendingUpIcon,
    DocumentTextIcon,
    Cog6ToothIcon,
    ArrowRightOnRectangleIcon,
    ChevronDoubleLeftIcon,
    ChevronDoubleRightIcon,
    ChevronRightIcon,
    BellIcon,
    CurrencyDollarIcon,
    ChartBarIcon,
    HandRaisedIcon,
    ClipboardDocumentCheckIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const allowedSections = computed(() => user.value?.allowed_sections ?? []);
const isAdmin = computed(() => user.value?.role === 'admin');
const sidebarCollapsed = ref(false);

function hasSection(section) {
    return isAdmin.value || allowedSections.value.includes(section);
}

const allNavItems = [
    { name: 'Dashboard',    href: '/admin',           exact: true,  section: 'dashboard',          icon: HomeIcon },
    { name: 'Eventos',      href: '/admin/events',    exact: false, section: 'events',             icon: CalendarDaysIcon },
    { name: 'Modelos',      href: '/admin/models',    exact: false, section: 'models',             icon: UserIcon },
    { name: 'Diseñadores',  href: '/admin/designers', exact: false, section: 'designers',          icon: PaintBrushIcon },
    { name: 'Voluntarios', href: '/admin/volunteers', exact: false, section: 'volunteers',         icon: HandRaisedIcon },
    { name: 'Asistencia',  href: '/admin/attendance', exact: false, section: 'attendance',         icon: ClipboardDocumentCheckIcon },
    { name: 'Chats',        href: '/admin/chats',     exact: false, section: 'chats',              icon: ChatBubbleLeftRightIcon },
    { name: 'Banners',      href: '/admin/banners',   exact: false, section: 'banners',            icon: PhotoIcon },
    { name: 'Usuarios',     href: '/admin/users',     exact: false, section: 'users',              icon: UsersIcon },
    { name: 'Pases',        href: '/admin/passes',    exact: false, section: 'tickets_management', icon: TicketIcon },
    { name: 'Logs',         href: '/admin/logs',      exact: false, section: 'activity_logs',      icon: DocumentTextIcon },
    { name: 'Categorías',   href: '/admin/settings/designer-categories', exact: false, section: 'designer_categories', icon: Cog6ToothIcon },
];

const navItems = computed(() => allNavItems.filter(item => hasSection(item.section)));

const showAccounting = computed(() => hasSection('accounting_dashboard') || hasSection('accounting_payments'));
const accountingItems = computed(() => {
    const items = [];
    if (hasSection('accounting_dashboard')) items.push({ name: 'Dashboard',            href: '/admin/accounting/dashboard',      icon: PresentationChartBarIcon });
    if (hasSection('accounting_payments')) {
        items.push({ name: 'Diseñadores',          href: '/admin/accounting/designers-list',  icon: UsersIcon });
        items.push({ name: 'Deudas',               href: '/admin/accounting/overdue',         icon: ExclamationTriangleIcon });
        items.push({ name: 'Historial',            href: '/admin/accounting/cases',           icon: ClipboardDocumentListIcon });
        items.push({ name: 'Liquidez',             href: '/admin/accounting/liquidity',       icon: ArrowTrendingUpIcon });
        items.push({ name: 'Pagos Diseñadores',    href: '/admin/accounting/payments',        icon: BanknotesIcon });
        items.push({ name: 'Registro de Pagos',    href: '/admin/accounting/payment-records', icon: DocumentTextIcon });
    }
    return items;
});

const isSalesLider = computed(() => user.value?.role === 'sales' && user.value?.sales_type === 'lider');
const showSales = computed(() => hasSection('sales_dashboard') || hasSection('sales_designers') || hasSection('designer_packages'));
const salesItems = computed(() => {
    const items = [];
    if (hasSection('sales_dashboard')) items.push({ name: 'Dashboard', href: '/admin/sales/dashboard', icon: PresentationChartBarIcon });
    if (hasSection('sales_dashboard') && (isAdmin.value || isSalesLider.value)) items.push({ name: 'Sales History', href: '/admin/sales/history', icon: ChartBarIcon });
    if (hasSection('sales_designers')) items.push({ name: 'Diseñadores', href: '/admin/sales/designers', icon: PaintBrushIcon });
    if (hasSection('designer_packages') && (isAdmin.value || isSalesLider.value)) items.push({ name: 'Paquetes', href: '/admin/settings/designer-packages', icon: CurrencyDollarIcon });
    return items;
});

const showSettings = computed(() => hasSection('settings'));
const settingsOpen = ref(page.url.startsWith('/admin/settings'));
const settingsItems = [
    { name: 'Diseñadores', href: '/admin/settings/designers' },
];

// Notifications
const notifications = ref([]);
const showNotifDropdown = ref(false);
let notifInterval = null;

const unreadCount = computed(() => notifications.value.filter(n => !n.read_at).length);

async function fetchNotifications() {
    try {
        const res = await fetch('/admin/api/notifications', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (res.ok) {
            const data = await res.json();
            const prevUnread = notifications.value.filter(n => !n.read_at).length;
            const newUnread = data.filter(n => !n.read_at).length;
            if (newUnread > prevUnread && prevUnread >= 0 && notifications.value.length > 0) {
                playNotifSound();
                // Disparar eventos DOM para que las páginas refresquen según el tipo
                const prevIds = new Set(notifications.value.map(n => n.id));
                data.filter(n => !n.read_at && !prevIds.has(n.id)).forEach(n => {
                    window.dispatchEvent(new CustomEvent('notification:received', { detail: n }));
                    if (document.hidden) showSystemNotification(n);
                });
            }
            notifications.value = data;
        }
    } catch {}
}

let audioUnlocked = false;
function unlockAudio() {
    if (audioUnlocked) return;
    audioUnlocked = true;
    requestNotifPermission();
    document.removeEventListener('click', unlockAudio);
}

function playNotifSound() {
    if (!audioUnlocked) return;
    try {
        const audio = new Audio('/sounds/notification-sales.mp3');
        audio.play().catch(() => {});
    } catch {}
}

// Solicitar permiso para notificaciones del sistema al primer click
async function requestNotifPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        await Notification.requestPermission();
    }
}

function showSystemNotification(notif) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    try {
        const n = new Notification(notif.data?.title ?? 'Nueva notificación', {
            body: notif.data?.message ?? '',
            icon: '/favicon.ico',
            tag: notif.id, // evita duplicados
        });
        n.onclick = () => { window.focus(); n.close(); };
    } catch {}
}

async function markAllRead() {
    const token = page.props.csrf_token
        || document.querySelector('meta[name="csrf-token"]')?.content
        || document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1];
    try {
        const res = await fetch('/admin/api/notifications/mark-read', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
            },
        });
        if (res.ok) {
            notifications.value = notifications.value.map(n => ({ ...n, read_at: n.read_at || new Date().toISOString() }));
            showNotifDropdown.value = false;
        }
    } catch {}
}

function closeNotifOnOutsideClick(e) {
    if (showNotifDropdown.value && !e.target.closest('.relative')) {
        showNotifDropdown.value = false;
    }
}

onMounted(() => {
    fetchNotifications();
    notifInterval = setInterval(fetchNotifications, 30000);
    document.addEventListener('click', closeNotifOnOutsideClick);
    document.addEventListener('click', unlockAudio);
});
onUnmounted(() => {
    clearInterval(notifInterval);
    document.removeEventListener('click', closeNotifOnOutsideClick);
    document.removeEventListener('click', unlockAudio);
});

function toggleSettings() {
    if (sidebarCollapsed.value) {
        sidebarCollapsed.value = false;
        settingsOpen.value = true;
    } else {
        settingsOpen.value = !settingsOpen.value;
    }
}

function logout() {
    router.post('/admin/logout');
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex">
        <!-- Sidebar -->
        <aside :class="['bg-black flex-shrink-0 flex flex-col h-screen sticky top-0 transition-all duration-300 ease-in-out', sidebarCollapsed ? 'w-16' : 'w-64']">
            <!-- Logo -->
            <div :class="['py-5 border-b border-gray-800 flex items-center', sidebarCollapsed ? 'justify-center px-2' : 'justify-between px-6']">
                <div v-if="!sidebarCollapsed">
                    <img src="/images/logo.webp" alt="Runway7" class="h-20 mx-auto" />
                    <p class="text-gray-500 text-xs mt-2 tracking-wider">PANEL ADMINISTRATIVO</p>
                </div>
                <button
                    @click="sidebarCollapsed = !sidebarCollapsed"
                    :title="sidebarCollapsed ? 'Expandir sidebar' : 'Colapsar sidebar'"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition-colors flex-shrink-0"
                >
                    <ChevronDoubleLeftIcon v-if="!sidebarCollapsed" class="h-5 w-5" />
                    <ChevronDoubleRightIcon v-else class="h-5 w-5" />
                </button>
            </div>

            <!-- Navigation -->
            <nav :class="['flex-1 py-6 space-y-1 overflow-y-auto', sidebarCollapsed ? 'px-2' : 'px-4']">
                <Link
                    v-for="item in navItems"
                    :key="item.name"
                    :href="item.href"
                    :title="sidebarCollapsed ? item.name : ''"
                    class="flex items-center py-2.5 rounded-lg text-sm font-medium transition-all duration-150 group"
                    :class="[
                        (item.exact ? $page.url === item.href : $page.url.startsWith(item.href))
                            ? 'bg-yellow-900/30 text-yellow-400'
                            : 'text-gray-400 hover:text-white hover:bg-gray-800',
                        sidebarCollapsed ? 'justify-center px-0' : 'px-3'
                    ]"
                >
                    <component :is="item.icon" :class="['h-5 w-5 flex-shrink-0', sidebarCollapsed ? '' : 'mr-3']" />
                    <span v-if="!sidebarCollapsed">{{ item.name }}</span>
                </Link>

                <!-- Contabilidad -->
                <template v-if="showAccounting">
                    <div class="pt-3 mt-3 border-t border-gray-800">
                        <p v-if="!sidebarCollapsed" class="px-3 mb-2 text-xs uppercase tracking-widest text-gray-600">Contabilidad</p>
                        <Link v-for="sub in accountingItems" :key="sub.name" :href="sub.href"
                            :title="sidebarCollapsed ? sub.name : ''"
                            class="flex items-center py-2.5 rounded-lg text-sm font-medium transition-all duration-150"
                            :class="[
                                (sub.href === '/admin/accounting/payments' ? $page.url.startsWith('/admin/accounting/payments') && !$page.url.startsWith('/admin/accounting/payment-records') : sub.href === '/admin/accounting/designers-list' ? $page.url.startsWith('/admin/accounting/designers-list') : $page.url.startsWith(sub.href))
                                    ? 'bg-yellow-900/30 text-yellow-400'
                                    : 'text-gray-400 hover:text-white hover:bg-gray-800',
                                sidebarCollapsed ? 'justify-center px-0' : 'px-3'
                            ]">
                            <component :is="sub.icon" :class="['h-5 w-5 flex-shrink-0', sidebarCollapsed ? '' : 'mr-3']" />
                            <span v-if="!sidebarCollapsed">{{ sub.name }}</span>
                        </Link>
                    </div>
                </template>

                <!-- Ventas -->
                <template v-if="showSales">
                    <div class="pt-3 mt-3 border-t border-gray-800">
                        <p v-if="!sidebarCollapsed" class="px-3 mb-2 text-xs uppercase tracking-widest text-gray-600">Ventas</p>
                        <Link v-for="sub in salesItems" :key="sub.name" :href="sub.href"
                            :title="sidebarCollapsed ? sub.name : ''"
                            class="flex items-center py-2.5 rounded-lg text-sm font-medium transition-all duration-150"
                            :class="[
                                $page.url.startsWith(sub.href)
                                    ? 'bg-yellow-900/30 text-yellow-400'
                                    : 'text-gray-400 hover:text-white hover:bg-gray-800',
                                sidebarCollapsed ? 'justify-center px-0' : 'px-3'
                            ]">
                            <component :is="sub.icon" :class="['h-5 w-5 flex-shrink-0', sidebarCollapsed ? '' : 'mr-3']" />
                            <span v-if="!sidebarCollapsed">{{ sub.name }}</span>
                        </Link>
                    </div>
                </template>

                <!-- Ajustes (collapsible) -->
                <div v-if="showSettings" class="pt-3 mt-3 border-t border-gray-800">
                    <button
                        @click="toggleSettings"
                        :title="sidebarCollapsed ? 'Ajustes' : ''"
                        class="flex items-center w-full py-2.5 rounded-lg text-sm font-medium transition-all duration-150"
                        :class="[
                            $page.url.startsWith('/admin/settings')
                                ? 'bg-yellow-900/30 text-yellow-400'
                                : 'text-gray-400 hover:text-white hover:bg-gray-800',
                            sidebarCollapsed ? 'justify-center px-0' : 'px-3'
                        ]">
                        <Cog6ToothIcon :class="['h-5 w-5 flex-shrink-0', sidebarCollapsed ? '' : 'mr-3']" />
                        <template v-if="!sidebarCollapsed">
                            Ajustes
                            <ChevronRightIcon class="ml-auto h-4 w-4 transition-transform" :class="settingsOpen ? 'rotate-90' : ''" />
                        </template>
                    </button>
                    <div v-show="settingsOpen && !sidebarCollapsed" class="ml-8 mt-1 space-y-1">
                        <Link v-for="sub in settingsItems" :key="sub.name" :href="sub.href"
                            class="block px-3 py-2 rounded-lg text-sm transition-all duration-150"
                            :class="$page.url.startsWith(sub.href)
                                ? 'text-yellow-400 font-medium'
                                : 'text-gray-500 hover:text-white hover:bg-gray-800'">
                            {{ sub.name }}
                        </Link>
                    </div>
                </div>
            </nav>

            <!-- User info + logout -->
            <div :class="['py-6 border-t border-gray-800', sidebarCollapsed ? 'px-2' : 'px-4']">
                <div v-if="!sidebarCollapsed" class="flex items-center space-x-3 mb-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-black flex-shrink-0" style="background-color: #D4AF37;">
                        {{ user?.first_name?.[0] }}{{ user?.last_name?.[0] }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white text-sm font-medium truncate">{{ user?.first_name }} {{ user?.last_name }}</p>
                        <p class="text-gray-500 text-xs truncate">{{ user?.email }}</p>
                        <p v-if="user?.role_label" class="text-xs mt-0.5 truncate" style="color: #D4AF37;">{{ user.role_label }}</p>
                    </div>
                </div>
                <div v-else class="flex justify-center mb-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-black"
                        style="background-color: #D4AF37;"
                        :title="`${user?.first_name} ${user?.last_name}`">
                        {{ user?.first_name?.[0] }}{{ user?.last_name?.[0] }}
                    </div>
                </div>
                <button
                    @click="logout"
                    :title="sidebarCollapsed ? 'Cerrar sesión' : ''"
                    class="w-full text-gray-400 hover:text-white text-sm py-2 rounded-lg hover:bg-gray-800 transition-colors flex items-center"
                    :class="sidebarCollapsed ? 'justify-center px-0' : 'text-left px-3'"
                >
                    <ArrowRightOnRectangleIcon class="h-4 w-4 flex-shrink-0" />
                    <span v-if="!sidebarCollapsed" class="ml-2">Cerrar sesión</span>
                </button>
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex-1 flex flex-col min-h-screen overflow-hidden">
            <!-- Top bar -->
            <header class="bg-white border-b border-gray-200 px-8 py-4 flex items-center justify-between">
                <slot name="header">
                    <h2 class="text-lg font-semibold text-gray-900">Dashboard</h2>
                </slot>
                <div class="flex items-center space-x-4">
                    <!-- Notification bell -->
                    <div class="relative">
                        <button @click="showNotifDropdown = !showNotifDropdown" class="relative p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                            <BellIcon class="h-5 w-5" />
                            <span v-if="unreadCount" class="absolute -top-0.5 -right-0.5 h-4 w-4 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center">
                                {{ unreadCount > 9 ? '9+' : unreadCount }}
                            </span>
                        </button>
                        <div v-if="showNotifDropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-200 z-50 overflow-hidden">
                            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                                <h4 class="text-sm font-semibold text-gray-900">Notificaciones</h4>
                                <button v-if="unreadCount" @click="markAllRead" class="text-xs text-blue-600 hover:underline">Marcar leídas</button>
                            </div>
                            <div class="max-h-72 overflow-y-auto">
                                <div v-if="!notifications.length" class="px-4 py-6 text-center text-gray-400 text-sm">Sin notificaciones</div>
                                <div v-for="n in notifications" :key="n.id"
                                    class="px-4 py-3 border-b border-gray-50 hover:bg-gray-50 transition-colors"
                                    :class="n.read_at ? 'opacity-50' : 'bg-blue-50/40'">
                                    <p class="text-sm font-medium" :class="n.read_at ? 'text-gray-500' : 'text-gray-900'">{{ n.data.title }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ n.data.message }}</p>
                                    <p class="text-xs text-gray-400 mt-1">{{ new Date(n.created_at).toLocaleString('es-US') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <span class="text-sm text-gray-500">{{ new Date().toLocaleDateString('es-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
                </div>
            </header>

            <!-- Flash messages -->
            <div v-if="$page.props.flash?.success" class="mx-8 mt-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="mx-8 mt-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
                {{ $page.props.flash.error }}
            </div>

            <!-- Page content -->
            <main class="flex-1 px-8 py-6">
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
nav::-webkit-scrollbar {
    width: 8px;
}
nav::-webkit-scrollbar-track {
    background: transparent;
}
nav::-webkit-scrollbar-thumb {
    background-color: #D4AF37;
    border-radius: 9999px;
}
nav::-webkit-scrollbar-thumb:hover {
    background-color: #b8942e;
}
</style>
