<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    model: Object,
});

const profile = props.model.model_profile;
const events  = props.model.events ?? [];
const shows   = props.model.shows  ?? [];

function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}

function genderLabel(g) {
    return { female: 'Femenino', male: 'Masculino', non_binary: 'No binario' }[g] ?? '—';
}

function ethnicityLabel(e) {
    return { asian: 'Asiática', black: 'Negra', caucasian: 'Caucásica', hispanic: 'Hispana',
        middle_eastern: 'Medio Oriente', mixed: 'Mixta', other: 'Otra' }[e] ?? '—';
}

function hairLabel(h) {
    return { black: 'Negro', brown: 'Castaño', blonde: 'Rubio', red: 'Rojo', gray: 'Gris', other: 'Otro' }[h] ?? '—';
}

function bodyTypeLabel(b) {
    return { slim: 'Delgada', athletic: 'Atlética', average: 'Promedio', curvy: 'Curvy', plus_size: 'Plus Size' }[b] ?? '—';
}

function castingStatusLabel(s) {
    return { scheduled: 'Programado', checked_in: 'Hizo check-in', no_show: 'No se presentó' }[s] ?? s ?? '—';
}

function showStatusLabel(s) {
    return { pending: 'Pendiente', confirmed: 'Confirmado', rejected: 'Rechazado', requested: 'Solicitado' }[s] ?? s ?? '—';
}

function showStatusClass(s) {
    return { confirmed: 'text-green-700 bg-green-50', rejected: 'text-red-600 bg-red-50',
        requested: 'text-blue-700 bg-blue-50', pending: 'text-gray-600 bg-gray-50' }[s] ?? 'text-gray-600 bg-gray-50';
}

function progressColor(pct) {
    if (pct === 100) return 'bg-green-500';
    if (pct >= 50)   return 'bg-yellow-400';
    return 'bg-red-300';
}

function sendWelcomeEmail() {
    if (!confirm('¿Enviar email de bienvenida a ' + props.model.first_name + '?')) return;
    router.post(`/admin/models/${props.model.id}/send-welcome-email`, {}, { preserveScroll: true });
}

function removeFromEvent(eventId, eventName) {
    if (!confirm(`¿Quitar a ${props.model.first_name} del evento "${eventName}"?`)) return;
    router.delete(`/admin/models/${props.model.id}/remove-event/${eventId}`, { preserveScroll: true });
}

const compCardLabels = ['Headshot', 'Full Body Front', 'Full Body Side', 'Creative/Editorial'];
const compCardPhotos = [profile?.photo_1, profile?.photo_2, profile?.photo_3, profile?.photo_4];
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/models" class="text-gray-400 hover:text-gray-600 text-sm">← Modelos</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">{{ model.first_name }} {{ model.last_name }}</h2>
            </div>
        </template>

        <div class="max-w-5xl mx-auto space-y-6">

            <!-- Header: foto + datos principales -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex gap-6">
                    <!-- Foto de perfil -->
                    <div class="flex-shrink-0">
                        <div class="w-24 h-24 rounded-full overflow-hidden bg-gray-100">
                            <img v-if="storageUrl(model.profile_picture)"
                                :src="storageUrl(model.profile_picture)"
                                class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center text-2xl font-bold text-gray-400">
                                {{ model.first_name?.[0] }}{{ model.last_name?.[0] }}
                            </div>
                        </div>
                    </div>
                    <!-- Info principal -->
                    <div class="flex-1">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">{{ model.first_name }} {{ model.last_name }}</h3>
                                <p class="text-gray-500 text-sm">{{ genderLabel(profile?.gender) }}
                                    <span v-if="profile?.age"> · {{ profile.age }} años</span>
                                    <span v-if="profile?.location"> · {{ profile.location }}</span>
                                </p>
                            </div>
                            <!-- Acciones -->
                            <div class="flex gap-2">
                                <button @click="sendWelcomeEmail"
                                    class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs hover:bg-gray-50 transition-colors">
                                    Enviar Email
                                </button>
                                <Link :href="`/admin/models/${model.id}/edit`"
                                    class="px-4 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors">
                                    Editar
                                </Link>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-3 text-sm">
                            <span class="text-gray-600">
                                <svg class="w-4 h-4 inline mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                                {{ model.email }}
                            </span>
                            <span v-if="model.phone" class="text-gray-600">
                                📞 {{ model.phone }}
                            </span>
                            <a v-if="profile?.instagram" :href="`https://instagram.com/${profile.instagram.replace('@','')}`"
                                target="_blank"
                                class="text-pink-600 hover:text-pink-700">
                                {{ profile.instagram }}
                            </a>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <span v-if="profile?.ethnicity" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ ethnicityLabel(profile.ethnicity) }}</span>
                            <span v-if="profile?.hair" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">Cabello {{ hairLabel(profile.hair) }}</span>
                            <span v-if="profile?.body_type" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ bodyTypeLabel(profile.body_type) }}</span>
                            <span v-if="profile?.is_agency" class="text-xs bg-purple-50 text-purple-700 px-2 py-0.5 rounded-full">Agencia: {{ profile.agency }}</span>
                            <span v-if="profile?.is_test_model" class="text-xs bg-orange-50 text-orange-700 px-2 py-0.5 rounded-full">Modelo de prueba</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-6">
                <!-- Columna izquierda: Comp Card + Medidas -->
                <div class="col-span-2 space-y-6">

                    <!-- Comp Card -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="font-bold text-gray-900">Comp Card</h4>
                            <div class="flex items-center gap-2">
                                <div class="w-28 h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div :class="progressColor(profile?.comp_card_progress ?? 0)"
                                        class="h-full rounded-full transition-all"
                                        :style="`width: ${profile?.comp_card_progress ?? 0}%`"></div>
                                </div>
                                <span class="text-sm font-medium text-gray-600">{{ profile?.comp_card_progress ?? 0 }}%</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-4 gap-3">
                            <div v-for="(label, i) in compCardLabels" :key="i"
                                class="aspect-[3/4] rounded-xl overflow-hidden border border-gray-200 bg-gray-50 relative group">
                                <img v-if="storageUrl(compCardPhotos[i])"
                                    :src="storageUrl(compCardPhotos[i])"
                                    class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex flex-col items-center justify-center gap-2 text-gray-300">
                                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                                    </svg>
                                    <span class="text-[10px]">Sin foto</span>
                                </div>
                                <div class="absolute bottom-0 left-0 right-0 bg-black/60 text-white text-[10px] text-center py-1 px-1">
                                    {{ label }}
                                </div>
                            </div>
                        </div>

                        <p class="mt-3 text-xs text-gray-400 italic text-center">Las modelos pueden completar su comp card desde la app.</p>
                    </div>

                    <!-- Medidas -->
                    <div v-if="profile" class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="font-bold text-gray-900 mb-4">Medidas</h4>
                        <div class="grid grid-cols-3 gap-4">
                            <div v-for="(item, i) in [
                                { label: 'Altura', value: profile.height ? profile.height + ' cm' : '—' },
                                { label: 'Busto/Pecho', value: profile.bust ? profile.bust + ' cm' : '—' },
                                { label: 'Cintura', value: profile.waist ? profile.waist + ' cm' : '—' },
                                { label: 'Cadera', value: profile.hips ? profile.hips + ' cm' : '—' },
                                { label: 'Talla zapato', value: profile.shoe_size || '—' },
                                { label: 'Talla ropa', value: profile.dress_size || '—' },
                            ]" :key="i"
                                class="bg-gray-50 rounded-xl p-3 text-center">
                                <p class="text-xs text-gray-400 mb-1">{{ item.label }}</p>
                                <p class="font-bold text-gray-800 text-lg">{{ item.value }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna derecha: Eventos y Shows -->
                <div class="space-y-6">
                    <!-- Eventos asignados -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h4 class="font-bold text-gray-900 mb-4">Eventos</h4>

                        <div v-if="events.length === 0" class="text-sm text-gray-400 italic">Sin eventos asignados.</div>

                        <div v-for="evt in events" :key="evt.id" class="mb-4 last:mb-0">
                            <div class="flex items-start justify-between">
                                <div>
                                    <Link :href="`/admin/events/${evt.id}`"
                                        class="text-sm font-semibold text-gray-900 hover:text-black hover:underline">
                                        {{ evt.name }}
                                    </Link>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span v-if="evt.participation_number"
                                            class="text-xs font-bold bg-black text-white px-2 py-0.5 rounded-full tracking-wide">
                                            #{{ evt.participation_number }}
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            Casting: {{ evt.casting_time ?? 'No asignado' }}
                                            <span v-if="evt.casting_status"> · {{ castingStatusLabel(evt.casting_status) }}</span>
                                        </span>
                                    </div>
                                </div>
                                <button @click="removeFromEvent(evt.id, evt.name)"
                                    class="text-red-400 hover:text-red-600 text-xs ml-2 flex-shrink-0">✕</button>
                            </div>

                            <!-- Shows de esta modelo en este evento -->
                            <div class="mt-2 space-y-1">
                                <div v-for="s in shows.filter(sh => sh.event?.id === evt.id)" :key="s.id"
                                    class="flex items-center gap-2 text-xs bg-gray-50 rounded-lg px-2 py-1.5">
                                    <span class="text-gray-500">{{ s.event_day?.label }}</span>
                                    <span class="text-gray-400">·</span>
                                    <span class="font-medium">{{ s.formatted_time }}</span>
                                    <span :class="showStatusClass(s.status)" class="ml-auto px-1.5 py-0.5 rounded text-[10px] font-medium">
                                        {{ showStatusLabel(s.status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notas -->
                    <div v-if="profile?.notes" class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h4 class="font-bold text-gray-900 mb-2">Notas</h4>
                        <p class="text-sm text-gray-600 leading-relaxed">{{ profile.notes }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
