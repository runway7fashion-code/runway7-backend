<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const allowedSections = computed(() => user.value?.allowed_sections ?? []);
const isAdmin = computed(() => user.value?.role === 'admin');
const sidebarCollapsed = ref(false);

function hasSection(section) {
    return isAdmin.value || allowedSections.value.includes(section);
}

const allNavItems = [
    { name: 'Dashboard', href: '/admin', exact: true, section: 'dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { name: 'Eventos', href: '/admin/events', exact: false, section: 'events', icon: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z' },
    { name: 'Modelos', href: '/admin/models', exact: false, section: 'models', icon: 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z' },
    { name: 'Diseñadores', href: '/admin/designers', exact: false, section: 'designers', icon: 'M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42' },
    { name: 'Chats', href: '/admin/chats', exact: false, section: 'chats', icon: 'M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z' },
    { name: 'Banners', href: '/admin/banners', exact: false, section: 'banners', icon: 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z' },
    { name: 'Usuarios', href: '/admin/users', exact: false, section: 'users', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
    { name: 'Pases', href: '/admin/passes', exact: false, section: 'tickets_management', icon: 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z' },
];

const navItems = computed(() => allNavItems.filter(item => hasSection(item.section)));

const showAccounting = computed(() => hasSection('accounting_dashboard') || hasSection('accounting_payments'));
const accountingItems = computed(() => {
    const items = [];
    if (hasSection('accounting_dashboard')) items.push({ name: 'Dashboard', href: '/admin/accounting/dashboard', icon: 'dashboard' });
    if (hasSection('accounting_payments')) {
        items.push({ name: 'Diseñadores', href: '/admin/accounting/designers-list', icon: 'designers-list' });
        items.push({ name: 'Deudas', href: '/admin/accounting/overdue', icon: 'overdue' });
        items.push({ name: 'Historial', href: '/admin/accounting/cases', icon: 'history' });
        items.push({ name: 'Liquidez', href: '/admin/accounting/liquidity', icon: 'liquidity' });
        items.push({ name: 'Pagos Diseñadores', href: '/admin/accounting/payments', icon: 'payments' });
        items.push({ name: 'Registro de Pagos', href: '/admin/accounting/payment-records', icon: 'records' });
    }
    return items;
});

const showSettings = computed(() => hasSection('settings'));
const settingsOpen = ref(page.url.startsWith('/admin/settings'));
const settingsItems = [
    { name: 'Diseñadores', href: '/admin/settings/designers' },
];

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
                    <img src="/images/logo.webp" alt="Runway7" class="h-8" />
                    <p class="text-gray-500 text-xs mt-2 tracking-wider">ADMIN PANEL</p>
                </div>
                <button
                    @click="sidebarCollapsed = !sidebarCollapsed"
                    :title="sidebarCollapsed ? 'Expandir sidebar' : 'Colapsar sidebar'"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition-colors flex-shrink-0"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path v-if="!sidebarCollapsed" stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
                        <path v-else stroke-linecap="round" stroke-linejoin="round" d="M5.25 4.5l7.5 7.5-7.5 7.5m6-15l7.5 7.5-7.5 7.5" />
                    </svg>
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
                    <svg :class="['h-5 w-5 flex-shrink-0', sidebarCollapsed ? '' : 'mr-3']" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
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
                            <svg :class="['h-5 w-5 flex-shrink-0', sidebarCollapsed ? '' : 'mr-3']" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path v-if="sub.icon === 'dashboard'" stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" />
                                <path v-else-if="sub.icon === 'payments'" stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                <path v-else-if="sub.icon === 'designers-list'" stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                <path v-else-if="sub.icon === 'overdue'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                <path v-else-if="sub.icon === 'history'" stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V19.5a2.25 2.25 0 002.25 2.25h6.75a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192" />
                                <path v-else-if="sub.icon === 'liquidity'" stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                                <path v-else-if="sub.icon === 'records'" stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                            </svg>
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
                        <svg :class="['h-5 w-5 flex-shrink-0', sidebarCollapsed ? '' : 'mr-3']" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.431.992a7.723 7.723 0 010 .255c-.007.378.138.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <template v-if="!sidebarCollapsed">
                            Ajustes
                            <svg class="ml-auto h-4 w-4 transition-transform" :class="settingsOpen ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
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
                    <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                    </svg>
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
                <div class="flex items-center space-x-2">
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
