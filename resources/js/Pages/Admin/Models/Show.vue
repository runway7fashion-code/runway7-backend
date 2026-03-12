<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import QrCode from '@/Components/QrCode.vue';
import { ArrowLeftIcon, EnvelopeIcon, PhoneIcon, CameraIcon, XMarkIcon, ArrowRightIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    model: Object,
});

const profile  = props.model.model_profile;
const events   = props.model.events ?? [];
const shows    = props.model.shows  ?? [];
const fittings = props.model.fittings ?? [];

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
    return { scheduled: 'Agendada', checked_in: 'Hizo check-in', selected: 'Seleccionada', no_show: 'No se presentó' }[s] ?? s ?? '—';
}

function showStatusLabel(s) {
    return { pending: 'Pendiente', confirmed: 'Confirmado', rejected: 'Rechazado', requested: 'Solicitado' }[s] ?? s ?? '—';
}

function showStatusClass(s) {
    return { confirmed: 'text-green-700 bg-green-50', rejected: 'text-red-600 bg-red-50',
        requested: 'text-blue-700 bg-blue-50', pending: 'text-gray-600 bg-gray-50' }[s] ?? 'text-gray-600 bg-gray-50';
}

function passStatusClass(s) {
    return {
        active:    'bg-green-50 text-green-700',
        cancelled: 'bg-red-50 text-red-600',
        used:      'bg-gray-100 text-gray-500',
    }[s] ?? 'bg-gray-100 text-gray-500';
}

function passStatusLabel(s) {
    return { active: 'Activo', cancelled: 'Cancelado', used: 'Usado' }[s] ?? s;
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

function statusBadgeClass(s) {
    return { active: 'bg-green-50 text-green-700', inactive: 'bg-red-50 text-red-600', pending: 'bg-yellow-50 text-yellow-700', applicant: 'bg-purple-50 text-purple-700' }[s] ?? 'bg-gray-50 text-gray-600';
}
function statusBadgeLabel(s) {
    return { active: 'Activo', inactive: 'Inactivo', pending: 'Pendiente', applicant: 'Aplicante' }[s] ?? s;
}

const compCardLabels = ['Headshot', 'Full Body Front', 'Full Body Side', 'Creative/Editorial'];
const compCardPhotos = [profile?.photo_1, profile?.photo_2, profile?.photo_3, profile?.photo_4];
const failedImgs = ref([false, false, false, false]);

// Lightbox gallery
const allGalleryLabels = ['Foto de Perfil', ...compCardLabels];
const allGalleryPhotos = [props.model.profile_picture, ...compCardPhotos];
const lightboxIndex = ref(-1);
function openLightbox(index) { lightboxIndex.value = index; }
function closeLightbox() { lightboxIndex.value = -1; }
function lightboxPrev() { if (lightboxIndex.value > 0) lightboxIndex.value--; }
function lightboxNext() { if (lightboxIndex.value < allGalleryPhotos.length - 1) lightboxIndex.value++; }

// Modal pase
const passModal = ref(null);
function openPassModal(evt) { passModal.value = { ...evt.pass, event_name: evt.name }; }
function closePassModal()   { passModal.value = null; }

// Modal eliminar
const showDeleteModal = ref(false);
function deleteModel() {
    router.delete(`/admin/models/${props.model.id}`, {
        onSuccess: () => { showDeleteModal.value = false; },
    });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/models" class="flex items-center gap-1 text-gray-400 hover:text-gray-600 text-sm">
                    <ArrowLeftIcon class="w-4 h-4" /> Modelos
                </Link>
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
                        <div class="w-24 h-24 rounded-full"
                            :class="[
                                profile?.is_top ? 'ring-[3px] ring-[#D4AF37] ring-offset-2' : '',
                                storageUrl(model.profile_picture) ? 'cursor-pointer hover:opacity-90 transition' : ''
                            ]"
                            @click="storageUrl(model.profile_picture) && openLightbox(0)">
                            <div class="w-full h-full rounded-full overflow-hidden bg-gray-100">
                                <img v-if="storageUrl(model.profile_picture)"
                                    :src="storageUrl(model.profile_picture)"
                                    class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-2xl font-bold text-gray-400">
                                    {{ model.first_name?.[0] }}{{ model.last_name?.[0] }}
                                </div>
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
                            <div class="flex items-center gap-2">
                                <span :class="statusBadgeClass(model.status)"
                                    class="text-xs font-medium rounded-lg px-3 py-1.5">
                                    {{ statusBadgeLabel(model.status) }}
                                </span>
                                <button @click="sendWelcomeEmail"
                                    class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs hover:bg-gray-50 transition-colors">
                                    Enviar Email
                                </button>
                                <Link :href="`/admin/models/${model.id}/edit`"
                                    class="px-4 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors">
                                    Editar
                                </Link>
                                <button @click="showDeleteModal = true"
                                    class="px-3 py-1.5 border border-red-200 text-red-600 rounded-lg text-xs font-medium hover:bg-red-50 transition-colors flex items-center gap-1">
                                    <TrashIcon class="w-3.5 h-3.5" />
                                    Eliminar
                                </button>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-3 text-sm">
                            <span class="flex items-center gap-1 text-gray-600">
                                <EnvelopeIcon class="w-4 h-4 text-gray-900" />
                                {{ model.email }}
                            </span>
                            <span v-if="model.phone" class="flex items-center gap-1 text-gray-600">
                                <PhoneIcon class="w-4 h-4 text-gray-900" />
                                {{ model.phone }}
                            </span>
                            <a v-if="profile?.instagram" :href="`https://instagram.com/${profile.instagram.replace('@','')}`"
                                target="_blank"
                                class="flex items-center gap-1 text-pink-600 hover:text-pink-700">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                </svg>
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
                                :class="[
                                    'aspect-[3/4] rounded-xl overflow-hidden border border-gray-200 bg-gray-50 relative group',
                                    storageUrl(compCardPhotos[i]) && !failedImgs[i] ? 'cursor-pointer' : ''
                                ]"
                                @click="storageUrl(compCardPhotos[i]) && !failedImgs[i] && openLightbox(i + 1)">
                                <img v-if="storageUrl(compCardPhotos[i]) && !failedImgs[i]"
                                    :src="storageUrl(compCardPhotos[i])"
                                    @error="failedImgs[i] = true"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200" />
                                <div v-else class="w-full h-full flex flex-col items-center justify-center gap-2 text-gray-300">
                                    <CameraIcon class="w-8 h-8" />
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
                                { label: 'Height', value: profile.height ? profile.height + ' in' : '—' },
                                { label: 'Bust/Chest', value: profile.bust ? profile.bust + ' in' : '—' },
                                { label: 'Waist', value: profile.waist ? profile.waist + ' in' : '—' },
                                { label: 'Hips', value: profile.hips ? profile.hips + ' in' : '—' },
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

                        <div v-for="evt in events" :key="evt.id" class="mb-5 last:mb-0 border border-gray-100 rounded-xl overflow-hidden">
                            <!-- Header del evento -->
                            <div class="bg-gray-50 px-4 py-3 flex items-center justify-between">
                                <div class="min-w-0">
                                    <Link :href="`/admin/events/${evt.id}`"
                                        class="text-sm font-semibold text-gray-900 hover:text-black hover:underline leading-tight">
                                        {{ evt.name }}
                                    </Link>
                                </div>
                                <button @click="removeFromEvent(evt.id, evt.name)"
                                    class="text-red-400 hover:text-red-600 ml-2 flex-shrink-0">
                                    <XMarkIcon class="w-4 h-4" />
                                </button>
                            </div>

                            <div class="px-4 py-3 space-y-3">
                                <!-- Info general: participación + casting -->
                                <div class="flex items-center gap-3 flex-wrap">
                                    <span v-if="evt.participation_number"
                                        class="inline-flex items-center gap-1 text-xs font-bold bg-black text-white px-2 py-0.5 rounded-full">
                                        #{{ evt.participation_number }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Casting: {{ evt.casting_time ?? 'No asignado' }}
                                        <span v-if="evt.casting_time && evt.casting_status"
                                            :class="{
                                                'text-yellow-600': evt.casting_status === 'scheduled',
                                                'text-blue-600': evt.casting_status === 'checked_in',
                                                'text-green-600': evt.casting_status === 'selected',
                                                'text-red-500': evt.casting_status === 'no_show',
                                            }"
                                            class="font-medium">
                                            · {{ castingStatusLabel(evt.casting_status) }}
                                        </span>
                                    </span>
                                </div>

                                <!-- Pase -->
                                <div v-if="evt.pass" class="flex items-center gap-2 bg-gray-50 rounded-lg px-3 py-2">
                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5z" />
                                    </svg>
                                    <span class="font-mono text-[11px] text-gray-500 tracking-wide">{{ evt.pass.qr_code }}</span>
                                    <span :class="passStatusClass(evt.pass.status)"
                                        class="text-[10px] font-medium px-1.5 py-0.5 rounded">
                                        {{ passStatusLabel(evt.pass.status) }}
                                    </span>
                                    <button @click="openPassModal(evt)"
                                        class="ml-auto flex items-center gap-0.5 text-[11px] text-indigo-500 hover:text-indigo-700 font-medium">
                                        Ver QR <ArrowRightIcon class="w-3 h-3" />
                                    </button>
                                </div>

                                <!-- Shows -->
                                <div v-if="shows.filter(sh => sh.event?.id === evt.id).length">
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Shows</p>
                                    <div class="space-y-1.5">
                                        <div v-for="s in shows.filter(sh => sh.event?.id === evt.id)" :key="s.id"
                                            class="flex items-start gap-2 bg-purple-50 border border-purple-100 rounded-lg px-3 py-2">
                                            <svg class="w-3.5 h-3.5 text-purple-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" />
                                            </svg>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-semibold text-purple-800">
                                                    {{ s.event_day?.label }} · {{ s.formatted_time }}
                                                </p>
                                                <p v-if="s.designers?.length" class="text-[11px] text-purple-500 truncate"
                                                    :title="s.designers.map(d => d.brand_name || d.name).join(', ')">
                                                    {{ s.designers.map(d => d.brand_name || d.name).join(', ') }}
                                                </p>
                                            </div>
                                            <span :class="showStatusClass(s.status)" class="flex-shrink-0 px-1.5 py-0.5 rounded text-[10px] font-medium mt-0.5">
                                                {{ showStatusLabel(s.status) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fittings -->
                                <div v-if="fittings.filter(f => f.event_id === evt.id).length">
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Fittings</p>
                                    <div class="space-y-1.5">
                                        <div v-for="f in fittings.filter(f => f.event_id === evt.id)" :key="f.designer_name + f.time"
                                            class="flex items-start gap-2 bg-orange-50 border border-orange-100 rounded-lg px-3 py-2">
                                            <svg class="w-3.5 h-3.5 text-orange-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-semibold text-orange-800">
                                                    {{ f.day_label }} · {{ f.time }}
                                                </p>
                                                <p class="text-[11px] text-orange-500">{{ f.brand_name || f.designer_name }}</p>
                                            </div>
                                        </div>
                                    </div>
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

    <!-- Modal: Confirmar eliminación -->
    <Teleport to="body">
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/60" @click="showDeleteModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
                <!-- Ícono -->
                <div class="flex items-center justify-center w-12 h-12 rounded-full bg-red-50 mx-auto mb-4">
                    <TrashIcon class="w-6 h-6 text-red-600" />
                </div>

                <!-- Título -->
                <h3 class="text-lg font-bold text-gray-900 text-center mb-1">
                    ¿Eliminar a {{ model.first_name }} {{ model.last_name }}?
                </h3>
                <p class="text-sm text-gray-500 text-center mb-5">Esta acción es permanente y no se puede deshacer.</p>

                <!-- Detalles de lo que se elimina -->
                <div class="bg-red-50 border border-red-100 rounded-xl p-4 mb-6 space-y-1.5">
                    <p class="text-xs font-semibold text-red-700 mb-2">Se eliminará de forma definitiva:</p>
                    <div class="flex items-start gap-2 text-xs text-red-600">
                        <span class="mt-0.5">•</span>
                        <span>Cuenta de usuario y datos personales</span>
                    </div>
                    <div class="flex items-start gap-2 text-xs text-red-600">
                        <span class="mt-0.5">•</span>
                        <span>Foto de perfil y todas las fotos del comp card</span>
                    </div>
                    <div class="flex items-start gap-2 text-xs text-red-600">
                        <span class="mt-0.5">•</span>
                        <span>Asignaciones a eventos y slots de casting</span>
                    </div>
                    <div class="flex items-start gap-2 text-xs text-red-600">
                        <span class="mt-0.5">•</span>
                        <span>Participación en shows</span>
                    </div>
                    <div class="flex items-start gap-2 text-xs text-red-600">
                        <span class="mt-0.5">•</span>
                        <span>Pases de acceso generados</span>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex gap-3">
                    <button @click="showDeleteModal = false"
                        class="flex-1 px-4 py-2 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancelar
                    </button>
                    <button @click="deleteModel"
                        class="flex-1 px-4 py-2 bg-red-600 text-white rounded-xl text-sm font-medium hover:bg-red-700 transition-colors">
                        Eliminar definitivamente
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal: Ver Pase QR -->
    <Teleport to="body">
        <div v-if="passModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/60" @click="closePassModal"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6 flex flex-col items-center gap-4">
                <!-- Cerrar -->
                <button @click="closePassModal" class="absolute top-4 right-4 p-1 text-gray-400 hover:text-gray-600">
                    <XMarkIcon class="h-5 w-5" />
                </button>

                <!-- Evento -->
                <p class="text-xs font-medium text-gray-400 text-center">{{ passModal.event_name }}</p>

                <!-- QR -->
                <div class="p-3 bg-white border-2 border-gray-100 rounded-xl">
                    <QrCode :value="passModal.qr_code" :size="220" />
                </div>

                <!-- Código + estado -->
                <div class="text-center">
                    <p class="font-mono text-sm text-gray-400">{{ passModal.qr_code }}</p>
                </div>

                <span :class="passStatusClass(passModal.status)"
                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium">
                    {{ passStatusLabel(passModal.status) }}
                </span>

                <!-- Días válidos -->
                <div class="text-center">
                    <p v-if="passModal.valid_days_labels" class="text-xs text-gray-500 font-medium">Días válidos</p>
                    <p v-if="passModal.valid_days_labels" class="text-xs text-gray-400 mt-0.5">{{ passModal.valid_days_labels }}</p>
                    <p v-else class="text-xs text-gray-400">Válido todos los días</p>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Lightbox Gallery -->
    <Teleport to="body">
        <div v-if="lightboxIndex >= 0"
            class="fixed inset-0 z-[9999] bg-black/90 flex items-center justify-center"
            @click.self="closeLightbox"
            @keydown.escape.window="closeLightbox"
            @keydown.left.window="lightboxPrev"
            @keydown.right.window="lightboxNext">

            <!-- Close -->
            <button @click="closeLightbox"
                class="absolute top-4 right-4 w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition text-white cursor-pointer z-10">
                <XMarkIcon class="w-6 h-6" />
            </button>

            <!-- Counter -->
            <div class="absolute top-4 left-1/2 -translate-x-1/2 text-white/70 text-sm font-medium">
                {{ lightboxIndex + 1 }} / {{ allGalleryPhotos.length }}
            </div>

            <!-- Label -->
            <div class="absolute bottom-6 left-1/2 -translate-x-1/2 text-white text-sm font-semibold tracking-wide uppercase">
                {{ allGalleryLabels[lightboxIndex] }}
            </div>

            <!-- Prev -->
            <button v-if="lightboxIndex > 0" @click="lightboxPrev"
                class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition text-white cursor-pointer">
                <ArrowLeftIcon class="w-5 h-5" />
            </button>

            <!-- Image -->
            <img :src="storageUrl(allGalleryPhotos[lightboxIndex])"
                :key="lightboxIndex"
                class="max-h-[85vh] max-w-[90vw] object-contain rounded-lg shadow-2xl" />

            <!-- Next -->
            <button v-if="lightboxIndex < allGalleryPhotos.length - 1" @click="lightboxNext"
                class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition text-white cursor-pointer">
                <ArrowRightIcon class="w-5 h-5" />
            </button>
        </div>
    </Teleport>
</template>
