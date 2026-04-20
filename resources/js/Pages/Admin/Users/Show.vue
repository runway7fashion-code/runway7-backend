<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    user: Object,
});

const categoryColors = {
    internal: 'bg-blue-100 text-blue-800',
    participant: 'bg-purple-100 text-purple-800',
    attendee: 'bg-green-100 text-green-800',
};

const statusColors = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-red-100 text-red-800',
    pending: 'bg-yellow-100 text-yellow-800',
};

function formatRole(r) {
    return r.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function deleteUser() {
    if (confirm(`¿Eliminar a ${props.user.first_name} ${props.user.last_name}? Esta acción no se puede deshacer.`)) {
        router.delete(`/admin/users/${props.user.id}`);
    }
}

const profile = props.user.model_profile
    || props.user.designer_profile
    || props.user.press_profile
    || props.user.sponsor_profile
    || null;
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center space-x-2 text-sm">
                <Link href="/admin/users" class="text-gray-400 hover:text-gray-600">Usuarios</Link>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-medium">{{ user.first_name }} {{ user.last_name }}</span>
            </div>
        </template>

        <div class="max-w-3xl">
            <!-- Header card -->
            <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <div class="flex items-start justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 rounded-full bg-black flex items-center justify-center text-xl font-bold text-white">
                            {{ user.first_name[0] }}{{ user.last_name[0] }}
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900">{{ user.first_name }} {{ user.last_name }}</h3>
                            <p class="text-gray-500 text-sm">{{ user.email }}</p>
                            <p v-if="user.phone" class="text-gray-500 text-sm">{{ user.phone }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium capitalize"
                            :class="categoryColors[user.role_category]">
                            {{ user.role_category === 'internal' ? 'Interno' : user.role_category === 'participant' ? 'Participante' : 'Asistente' }}
                        </span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium"
                            :class="statusColors[user.status]">
                            {{ user.status === 'active' ? 'Activo' : user.status === 'inactive' ? 'Inactivo' : 'Pendiente' }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-100 flex items-center gap-4 text-sm text-gray-500">
                    <span><strong class="text-gray-700">Rol:</strong> {{ formatRole(user.role) }}</span>
                    <span v-if="user.role === 'sales' && user.sales_type"><strong class="text-gray-700">Tipo:</strong> {{ user.sales_type === 'lider' ? 'Líder' : 'Asesor' }}</span>
                    <span v-if="user.role === 'sponsorship' && user.sponsorship_type"><strong class="text-gray-700">Tipo:</strong> {{ user.sponsorship_type === 'lider' ? 'Líder' : 'Asesor' }}</span>
                    <span><strong class="text-gray-700">Registrado:</strong> {{ new Date(user.created_at).toLocaleDateString('es-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
                </div>

                <div class="mt-4 flex gap-2">
                    <Link :href="`/admin/users/${user.id}/edit`"
                        class="px-4 py-2 text-sm font-semibold text-white bg-black rounded-lg hover:bg-gray-800 transition-colors">
                        Editar
                    </Link>
                    <button @click="deleteUser"
                        class="px-4 py-2 text-sm font-semibold text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                        Eliminar
                    </button>
                </div>
            </div>

            <!-- Profile data -->
            <div v-if="profile" class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <h4 class="font-semibold text-gray-900 mb-5">Perfil {{ formatRole(user.role) }}</h4>

                <!-- Model profile -->
                <template v-if="user.role === 'model' && user.model_profile">
                    <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                        <div v-if="user.model_profile.agency"><span class="text-gray-500">Agencia:</span> <span class="font-medium">{{ user.model_profile.agency }}</span></div>
                        <div v-if="user.model_profile.instagram"><span class="text-gray-500">Instagram:</span> <span class="font-medium">{{ user.model_profile.instagram }}</span></div>
                        <div v-if="user.model_profile.birth_date"><span class="text-gray-500">Nacimiento:</span> <span class="font-medium">{{ user.model_profile.birth_date }}</span></div>
                        <div v-if="user.model_profile.participation_number"><span class="text-gray-500">N° participación:</span> <span class="font-medium">{{ user.model_profile.participation_number }}</span></div>
                    </div>
                    <div v-if="user.model_profile.height || user.model_profile.bust" class="border-t border-gray-100 pt-4">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Medidas</p>
                        <div class="grid grid-cols-3 gap-3 text-sm">
                            <div v-if="user.model_profile.height" class="bg-gray-50 rounded-lg p-3 text-center"><p class="text-gray-500 text-xs">Altura</p><p class="font-bold text-lg">{{ user.model_profile.height }}</p><p class="text-gray-400 text-xs">cm</p></div>
                            <div v-if="user.model_profile.bust" class="bg-gray-50 rounded-lg p-3 text-center"><p class="text-gray-500 text-xs">Busto</p><p class="font-bold text-lg">{{ user.model_profile.bust }}</p><p class="text-gray-400 text-xs">cm</p></div>
                            <div v-if="user.model_profile.waist" class="bg-gray-50 rounded-lg p-3 text-center"><p class="text-gray-500 text-xs">Cintura</p><p class="font-bold text-lg">{{ user.model_profile.waist }}</p><p class="text-gray-400 text-xs">cm</p></div>
                            <div v-if="user.model_profile.hips" class="bg-gray-50 rounded-lg p-3 text-center"><p class="text-gray-500 text-xs">Caderas</p><p class="font-bold text-lg">{{ user.model_profile.hips }}</p><p class="text-gray-400 text-xs">cm</p></div>
                            <div v-if="user.model_profile.shoe_size" class="bg-gray-50 rounded-lg p-3 text-center"><p class="text-gray-500 text-xs">Zapato</p><p class="font-bold text-lg">{{ user.model_profile.shoe_size }}</p></div>
                            <div v-if="user.model_profile.dress_size" class="bg-gray-50 rounded-lg p-3 text-center"><p class="text-gray-500 text-xs">Ropa</p><p class="font-bold text-lg">{{ user.model_profile.dress_size }}</p></div>
                        </div>
                    </div>
                </template>

                <!-- Designer profile -->
                <template v-if="user.role === 'designer' && user.designer_profile">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div v-if="user.designer_profile.brand_name"><span class="text-gray-500">Marca:</span> <span class="font-medium">{{ user.designer_profile.brand_name }}</span></div>
                        <div v-if="user.designer_profile.collection_name"><span class="text-gray-500">Colección:</span> <span class="font-medium">{{ user.designer_profile.collection_name }}</span></div>
                        <div v-if="user.designer_profile.country"><span class="text-gray-500">País:</span> <span class="font-medium">{{ user.designer_profile.country }}</span></div>
                        <div v-if="user.designer_profile.instagram"><span class="text-gray-500">Instagram:</span> <span class="font-medium">{{ user.designer_profile.instagram }}</span></div>
                        <div v-if="user.designer_profile.website" class="col-span-2"><span class="text-gray-500">Web:</span> <a :href="user.designer_profile.website" target="_blank" class="text-blue-600 hover:underline font-medium">{{ user.designer_profile.website }}</a></div>
                        <div v-if="user.designer_profile.bio" class="col-span-2"><span class="text-gray-500">Bio:</span> <p class="font-medium mt-1">{{ user.designer_profile.bio }}</p></div>
                    </div>
                </template>

                <!-- Press profile -->
                <template v-if="user.role === 'press' && user.press_profile">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div v-if="user.press_profile.media_outlet"><span class="text-gray-500">Medio:</span> <span class="font-medium">{{ user.press_profile.media_outlet }}</span></div>
                        <div v-if="user.press_profile.position"><span class="text-gray-500">Cargo:</span> <span class="font-medium">{{ user.press_profile.position }}</span></div>
                        <div v-if="user.press_profile.instagram"><span class="text-gray-500">Instagram:</span> <span class="font-medium">{{ user.press_profile.instagram }}</span></div>
                        <div v-if="user.press_profile.website"><span class="text-gray-500">Web:</span> <a :href="user.press_profile.website" target="_blank" class="text-blue-600 hover:underline font-medium">{{ user.press_profile.website }}</a></div>
                    </div>
                </template>

                <!-- Sponsor profile -->
                <template v-if="user.role === 'sponsor' && user.sponsor_profile">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div v-if="user.sponsor_profile.company_name"><span class="text-gray-500">Empresa:</span> <span class="font-medium">{{ user.sponsor_profile.company_name }}</span></div>
                        <div v-if="user.sponsor_profile.sponsorship_level"><span class="text-gray-500">Nivel:</span> <span class="font-medium capitalize">{{ user.sponsor_profile.sponsorship_level }}</span></div>
                        <div v-if="user.sponsor_profile.website"><span class="text-gray-500">Web:</span> <a :href="user.sponsor_profile.website" target="_blank" class="text-blue-600 hover:underline font-medium">{{ user.sponsor_profile.website }}</a></div>
                        <div v-if="user.sponsor_profile.notes" class="col-span-2"><span class="text-gray-500">Notas:</span> <p class="font-medium mt-1">{{ user.sponsor_profile.notes }}</p></div>
                    </div>
                </template>
            </div>

            <!-- Shows (model) -->
            <div v-if="user.role === 'model' && user.shows?.length" class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <h4 class="font-semibold text-gray-900 mb-4">Shows asignados ({{ user.shows.length }})</h4>
                <div class="space-y-2">
                    <div v-for="show in user.shows" :key="show.id" class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                        <div>
                            <p class="font-medium text-sm text-gray-900">{{ show.name }}</p>
                            <p class="text-xs text-gray-500">{{ show.event_day?.event?.name }}</p>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-full capitalize"
                            :class="show.pivot?.status === 'confirmed' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'">
                            {{ show.pivot?.status }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Orders (attendee types) -->
            <div v-if="user.orders?.length" class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <h4 class="font-semibold text-gray-900 mb-4">Órdenes ({{ user.orders.length }})</h4>
                <div class="space-y-2">
                    <div v-for="order in user.orders" :key="order.id" class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0 text-sm">
                        <span class="font-mono font-medium">{{ order.order_number }}</span>
                        <span class="font-medium">${{ order.total }}</span>
                        <span class="capitalize text-gray-500">{{ order.status }}</span>
                    </div>
                </div>
            </div>

            <div class="flex">
                <Link href="/admin/users" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                    ← Volver a usuarios
                </Link>
            </div>
        </div>
    </AdminLayout>
</template>
