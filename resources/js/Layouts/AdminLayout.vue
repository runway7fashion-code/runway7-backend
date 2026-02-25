<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const sidebarOpen = ref(false);

const navItems = [
    { name: 'Dashboard', href: '/admin', exact: true, icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { name: 'Eventos', href: '/admin/events', exact: false, icon: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z' },
    { name: 'Modelos', href: '/admin/models', exact: false, icon: 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z' },
    { name: 'Chats', href: '/admin/chats', exact: false, icon: 'M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z' },
    { name: 'Banners', href: '/admin/banners', exact: false, icon: 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z' },
    { name: 'Usuarios', href: '/admin/users', exact: false, icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
];

function logout() {
    router.post('/admin/logout');
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-black flex-shrink-0 flex flex-col min-h-screen">
            <!-- Logo -->
            <div class="px-6 py-8 border-b border-gray-800">
                <img src="/images/logo.webp" alt="Runway7" class="h-8" />
                <p class="text-gray-500 text-xs mt-2 tracking-wider">ADMIN PANEL</p>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-4 py-6 space-y-1">
                <Link
                    v-for="item in navItems"
                    :key="item.name"
                    :href="item.href"
                    class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 group"
                    :class="(item.exact ? $page.url === item.href : $page.url.startsWith(item.href))
                        ? 'bg-yellow-900/30 text-yellow-400'
                        : 'text-gray-400 hover:text-white hover:bg-gray-800'"
                >
                    <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    {{ item.name }}
                </Link>
            </nav>

            <!-- User info + logout -->
            <div class="px-4 py-6 border-t border-gray-800">
                <div class="flex items-center space-x-3 mb-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-black" style="background-color: #D4AF37;">
                        {{ user?.first_name?.[0] }}{{ user?.last_name?.[0] }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white text-sm font-medium truncate">{{ user?.first_name }} {{ user?.last_name }}</p>
                        <p class="text-gray-500 text-xs truncate">{{ user?.email }}</p>
                    </div>
                </div>
                <button
                    @click="logout"
                    class="w-full text-left text-gray-400 hover:text-white text-sm px-3 py-2 rounded-lg hover:bg-gray-800 transition-colors flex items-center"
                >
                    <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                    </svg>
                    Cerrar sesión
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

            <!-- Page content -->
            <main class="flex-1 px-8 py-6">
                <slot />
            </main>
        </div>
    </div>
</template>
