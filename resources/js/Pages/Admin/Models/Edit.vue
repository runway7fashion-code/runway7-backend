<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { UserIcon, CameraIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    model:  Object,
    events: Array,
});

const activeTab = ref(1);
const profile   = props.model.model_profile;

const form = useForm({
    // Datos personales
    first_name:    props.model.first_name  ?? '',
    last_name:     props.model.last_name   ?? '',
    email:         props.model.email       ?? '',
    phone:         props.model.phone       ?? '',
    instagram:     profile?.instagram      ?? '',
    age:           profile?.age            ?? '',
    gender:        profile?.gender         ?? 'female',
    location:      profile?.location       ?? '',
    ethnicity:     profile?.ethnicity      ?? '',
    hair:          profile?.hair           ?? '',
    body_type:     profile?.body_type      ?? '',
    // Medidas
    height:        profile?.height         ?? '',
    bust:          profile?.bust           ?? '',
    chest:         profile?.chest          ?? '',
    waist:         profile?.waist          ?? '',
    hips:          profile?.hips           ?? '',
    shoe_size:     profile?.shoe_size      ?? '',
    dress_size:    profile?.dress_size     ?? '',
    // Agencia y notas
    agency:        profile?.agency         ?? '',
    is_agency:     profile?.is_agency      ?? false,
    is_test_model: profile?.is_test_model  ?? false,
    notes:         profile?.notes          ?? '',
    // Estado de cuenta
    status:        props.model.status      ?? 'pending',
});

// Comp card
const compCardLabels   = ['Headshot', 'Full Body Front', 'Full Body Side', 'Creative/Editorial'];
const compCardPhotos   = ref([profile?.photo_1, profile?.photo_2, profile?.photo_3, profile?.photo_4]);
const profilePicture   = ref(props.model.profile_picture ?? null);
const compCardProgress = computed(() => {
    const filled = compCardPhotos.value.filter(Boolean).length + (profilePicture.value ? 1 : 0);
    return Math.round((filled / 5) * 100);
});
const progressColor = computed(() => {
    if (compCardProgress.value === 100) return 'bg-green-500';
    if (compCardProgress.value >= 50)   return 'bg-yellow-400';
    return 'bg-red-300';
});

function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}

// Upload foto comp card
const uploading = ref({});

function uploadPhoto(position) {
    const input = document.createElement('input');
    input.type = 'accept';
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        uploading.value[position] = true;

        const formData = new FormData();
        formData.append('photo', file);
        formData.append('_method', 'POST');

        router.post(`/admin/models/${props.model.id}/upload-photo/${position}`, formData, {
            preserveScroll: true,
            onSuccess: () => {
                uploading.value[position] = false;
                // Refrescar para ver la nueva foto
                router.reload({ only: ['model'] });
            },
            onError: () => { uploading.value[position] = false; },
        });
    };
    input.click();
}

function deletePhoto(position) {
    if (!confirm(`¿Eliminar foto ${position}?`)) return;
    router.delete(`/admin/models/${props.model.id}/delete-photo/${position}`, {
        preserveScroll: true,
        onSuccess: () => { compCardPhotos.value[position - 1] = null; },
    });
}

// Foto de perfil
const uploadingProfile = ref(false);

function uploadProfilePicture() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        uploadingProfile.value = true;

        const formData = new FormData();
        formData.append('photo', file);

        router.post(`/admin/models/${props.model.id}/upload-profile-picture`, formData, {
            preserveScroll: true,
            onSuccess: () => {
                uploadingProfile.value = false;
                router.reload({ only: ['model'] });
            },
            onError: () => { uploadingProfile.value = false; },
        });
    };
    input.click();
}

function deleteProfilePicture() {
    if (!confirm('¿Eliminar la foto de perfil?')) return;
    router.delete(`/admin/models/${props.model.id}/delete-profile-picture`, {
        preserveScroll: true,
        onSuccess: () => { profilePicture.value = null; },
    });
}

// Sincronizar profilePicture cuando el prop se recarga
watch(() => props.model.profile_picture, (val) => { profilePicture.value = val ?? null; });
watch(() => props.model.model_profile, (p) => {
    if (p) {
        compCardPhotos.value = [p.photo_1, p.photo_2, p.photo_3, p.photo_4];
    }
});

// Evento / Casting
const selectedEventId   = ref(props.model.events?.[0]?.id ?? '');
const selectedSlotTime  = ref(props.model.events?.[0]?.casting_time ?? '');

const selectedEventData = computed(() => props.events.find(e => e.id == selectedEventId.value) ?? null);
const castingSlots      = computed(() => selectedEventData.value?.casting_day?.slots ?? []);

function slotColor(slot) {
    if (slot.available === 0) return 'bg-red-50 border-red-300 text-red-600 opacity-60 cursor-not-allowed';
    if (slot.available <= 10) return 'bg-yellow-50 border-yellow-300 text-yellow-700 hover:bg-yellow-100 cursor-pointer';
    return 'bg-green-50 border-green-300 text-green-700 hover:bg-green-100 cursor-pointer';
}

function selectSlot(slot) {
    if (slot.available === 0) return;
    selectedSlotTime.value = selectedSlotTime.value === slot.time ? '' : slot.time;
}

function formatSlotTime(t) {
    const [h, m] = t.split(':');
    const hour = parseInt(h);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const h12  = hour % 12 || 12;
    return `${h12}:${m} ${ampm}`;
}

function assignEvent() {
    if (!selectedEventId.value) return;
    router.post(`/admin/models/${props.model.id}/assign-event`, {
        event_id:     selectedEventId.value,
        casting_time: selectedSlotTime.value || null,
    }, { preserveScroll: true });
}

function removeFromEvent(eventId, eventName) {
    if (!confirm(`¿Quitar del evento "${eventName}"?`)) return;
    router.delete(`/admin/models/${props.model.id}/remove-event/${eventId}`, { preserveScroll: true });
}

function submit() {
    form.put(`/admin/models/${props.model.id}`);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="`/admin/models/${model.id}`" class="text-gray-400 hover:text-gray-600 text-sm">← Ver modelo</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Editar: {{ model.first_name }} {{ model.last_name }}</h2>
            </div>
        </template>

        <div class="max-w-3xl mx-auto">
            <!-- Tabs -->
            <div class="flex gap-1 bg-gray-100 rounded-xl p-1 mb-6">
                <button v-for="tab in [
                    { n: 1, label: 'Datos Personales' },
                    { n: 2, label: 'Medidas' },
                    { n: 3, label: 'Evento y Casting' },
                    { n: 4, label: 'Comp Card' },
                ]" :key="tab.n"
                    type="button"
                    @click="activeTab = tab.n"
                    class="flex-1 py-2 text-sm font-medium rounded-lg transition-all"
                    :class="activeTab === tab.n ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                    {{ tab.label }}
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-5">

                <!-- Pestaña 1: Datos Personales -->
                <div v-show="activeTab === 1" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                            <input v-model="form.first_name" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.first_name" class="mt-1 text-red-500 text-xs">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Apellido *</label>
                            <input v-model="form.last_name" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input v-model="form.email" type="email"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.email" class="mt-1 text-red-500 text-xs">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                            <input v-model="form.phone" type="tel"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Edad</label>
                            <input v-model="form.age" type="number" min="16" max="80"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Género</label>
                            <select v-model="form.gender"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="female">Femenino</option>
                                <option value="male">Masculino</option>
                                <option value="non_binary">No binario</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Instagram</label>
                            <input v-model="form.instagram" type="text" placeholder="@usuario"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ciudad / Ubicación</label>
                        <input v-model="form.location" type="text"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Etnia</label>
                            <select v-model="form.ethnicity"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Sin especificar —</option>
                                <option value="asian">Asiática</option>
                                <option value="black">Negra</option>
                                <option value="caucasian">Caucásica</option>
                                <option value="hispanic">Hispana</option>
                                <option value="middle_eastern">Medio Oriente</option>
                                <option value="mixed">Mixta</option>
                                <option value="other">Otra</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cabello</label>
                            <select v-model="form.hair"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Sin especificar —</option>
                                <option value="black">Negro</option>
                                <option value="brown">Castaño</option>
                                <option value="blonde">Rubio</option>
                                <option value="red">Rojo</option>
                                <option value="gray">Gris</option>
                                <option value="other">Otro</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de cuerpo</label>
                            <select v-model="form.body_type"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Sin especificar —</option>
                                <option value="slim">Delgada</option>
                                <option value="athletic">Atlética</option>
                                <option value="average">Promedio</option>
                                <option value="curvy">Curvy</option>
                                <option value="plus_size">Plus Size</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado de cuenta</label>
                        <select v-model="form.status"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="active" disabled>Activo (activado por la modelo)</option>
                            <option value="pending">Pendiente</option>
                            <option value="inactive">Inactivo</option>
                            <option value="applicant">Aplicante</option>
                        </select>
                    </div>

                    <div class="flex gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.is_test_model" type="checkbox"
                                class="rounded border-gray-300 text-black focus:ring-black/20" />
                            <span class="text-sm text-gray-700">Modelo de prueba</span>
                        </label>
                    </div>
                </div>

                <!-- Pestaña 2: Medidas -->
                <div v-show="activeTab === 2" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Altura (cm)</label>
                            <input v-model="form.height" type="number" step="0.1"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Busto/Pecho (cm)</label>
                            <input v-model="form.bust" type="number" step="0.1"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cintura (cm)</label>
                            <input v-model="form.waist" type="number" step="0.1"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cadera (cm)</label>
                            <input v-model="form.hips" type="number" step="0.1"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Talla zapato</label>
                            <input v-model="form.shoe_size" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Talla ropa</label>
                            <input v-model="form.dress_size" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <label class="flex items-center gap-2 cursor-pointer mb-3">
                            <input v-model="form.is_agency" type="checkbox"
                                class="rounded border-gray-300 text-black focus:ring-black/20" />
                            <span class="text-sm font-medium text-gray-700">Viene de agencia</span>
                        </label>
                        <div v-if="form.is_agency">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de la agencia</label>
                            <input v-model="form.agency" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notas internas</label>
                        <textarea v-model="form.notes" rows="3"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                    </div>
                </div>

                <!-- Pestaña 3: Evento y Casting -->
                <div v-show="activeTab === 3" class="space-y-5">
                    <!-- Eventos actuales -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h4 class="font-semibold text-gray-800 mb-3">Eventos asignados</h4>
                        <div v-if="model.events?.length === 0" class="text-sm text-gray-400 italic">Sin eventos.</div>
                        <div v-for="evt in model.events" :key="evt.id"
                            class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ evt.name }}</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span v-if="evt.participation_number"
                                        class="text-xs font-bold bg-black text-white px-2 py-0.5 rounded-full">
                                        #{{ evt.participation_number }}
                                    </span>
                                    <span class="text-xs text-gray-400">
                                        Casting: {{ evt.casting_time ?? '—' }}
                                        <span v-if="evt.casting_status"> · {{ evt.casting_status }}</span>
                                    </span>
                                </div>
                            </div>
                            <button type="button" @click="removeFromEvent(evt.id, evt.name)"
                                class="text-red-400 hover:text-red-600 text-xs">Quitar</button>
                        </div>
                    </div>

                    <!-- Asignar a nuevo evento -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
                        <h4 class="font-semibold text-gray-800">Asignar a evento</h4>

                        <select v-model="selectedEventId"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">— Seleccionar evento —</option>
                            <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>

                        <div v-if="selectedEventData && castingSlots.length">
                            <p class="text-sm font-medium text-gray-700 mb-2">Seleccionar horario de casting:</p>
                            <div class="grid grid-cols-4 gap-2">
                                <button v-for="slot in castingSlots" :key="slot.id"
                                    type="button"
                                    :disabled="slot.available === 0"
                                    @click="selectSlot(slot)"
                                    :class="[
                                        slotColor(slot),
                                        selectedSlotTime === slot.time ? 'ring-2 ring-black ring-offset-1' : '',
                                        'border rounded-lg p-2 text-center text-xs transition-all'
                                    ]">
                                    <p class="font-semibold">{{ formatSlotTime(slot.time) }}</p>
                                    <p class="text-[10px] mt-0.5 opacity-80">{{ slot.booked }}/{{ slot.capacity }}</p>
                                </button>
                            </div>
                        </div>

                        <button type="button" @click="assignEvent"
                            :disabled="!selectedEventId"
                            class="w-full py-2.5 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40 transition-colors">
                            Asignar al evento
                        </button>
                    </div>
                </div>

                <!-- Pestaña 4: Comp Card -->
                <div v-show="activeTab === 4" class="bg-white rounded-2xl border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h4 class="font-bold text-gray-900">Comp Card</h4>
                        <div class="flex items-center gap-2">
                            <div class="w-32 h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div :class="progressColor" class="h-full rounded-full transition-all"
                                    :style="`width: ${compCardProgress}%`"></div>
                            </div>
                            <span class="text-sm font-semibold text-gray-700">{{ compCardProgress }}%</span>
                        </div>
                    </div>

                    <!-- Foto de Perfil -->
                    <div class="flex items-center gap-5 mb-6 pb-6 border-b border-gray-100">
                        <div class="w-24 h-24 rounded-full overflow-hidden border-2 border-dashed border-gray-300 bg-gray-50 flex-shrink-0 flex items-center justify-center">
                            <img v-if="storageUrl(profilePicture)"
                                :src="storageUrl(profilePicture)"
                                class="w-full h-full object-cover" />
                            <UserIcon v-else class="w-8 h-8 text-gray-300" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-800 mb-0.5">Foto de Perfil</p>
                            <p class="text-xs text-gray-400 mb-3">Foto principal que se muestra en el app y en listas del admin.</p>
                            <div class="flex gap-2">
                                <button type="button" @click="uploadProfilePicture"
                                    :disabled="uploadingProfile"
                                    class="px-3 py-1.5 text-xs bg-black text-white rounded-lg hover:bg-gray-800 disabled:opacity-50 transition-colors">
                                    {{ uploadingProfile ? 'Subiendo...' : (profilePicture ? 'Cambiar foto' : 'Subir foto') }}
                                </button>
                                <button v-if="profilePicture" type="button" @click="deleteProfilePicture"
                                    class="px-3 py-1.5 text-xs border border-red-200 text-red-500 rounded-lg hover:bg-red-50 transition-colors">
                                    Eliminar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 4 fotos del comp card -->
                    <div class="grid grid-cols-4 gap-4">
                        <div v-for="(label, i) in compCardLabels" :key="i" class="space-y-2">
                            <!-- Preview foto -->
                            <div class="aspect-[3/4] rounded-xl overflow-hidden border-2 border-dashed border-gray-300 bg-gray-50 relative">
                                <img v-if="storageUrl(compCardPhotos[i])"
                                    :src="storageUrl(compCardPhotos[i])"
                                    class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <CameraIcon class="w-8 h-8 text-gray-300" />
                                </div>
                            </div>
                            <p class="text-xs text-center text-gray-500 font-medium">{{ label }}</p>
                            <div class="flex gap-1">
                                <button type="button" @click="uploadPhoto(i + 1)"
                                    :disabled="uploading[i + 1]"
                                    class="flex-1 py-1.5 text-xs bg-black text-white rounded-lg hover:bg-gray-800 disabled:opacity-50 transition-colors">
                                    {{ uploading[i + 1] ? '...' : 'Subir' }}
                                </button>
                                <button v-if="compCardPhotos[i]" type="button" @click="deletePhoto(i + 1)"
                                    class="p-1.5 border border-red-200 text-red-500 rounded-lg hover:bg-red-50 transition-colors">
                                    <XMarkIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <p class="mt-4 text-xs text-gray-400 italic text-center">Las modelos también pueden subir sus fotos desde la app.</p>
                </div>

                <!-- Botones (no en pestaña 3 ni 4 que tienen sus propias acciones) -->
                <div v-if="activeTab <= 2" class="flex justify-between">
                    <Link :href="`/admin/models/${model.id}`"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        Cancelar
                    </Link>
                    <div class="flex gap-3">
                        <button v-if="activeTab > 1" type="button" @click="activeTab--"
                            class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                            ← Anterior
                        </button>
                        <button v-if="activeTab < 2" type="button" @click="activeTab++"
                            class="px-6 py-2.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-black transition-colors">
                            Siguiente →
                        </button>
                        <button type="submit" :disabled="form.processing"
                            class="px-8 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-60 transition-colors">
                            <span v-if="form.processing">Guardando...</span>
                            <span v-else>Guardar Cambios</span>
                        </button>
                    </div>
                </div>
                <div v-else class="flex justify-end">
                    <Link :href="`/admin/models/${model.id}`"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        Volver al perfil
                    </Link>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
