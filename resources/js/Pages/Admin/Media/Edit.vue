<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ media: Object, events: Array });
const profile = props.media.media_profile;

const usStates = [
    'Alabama','Alaska','Arizona','Arkansas','California','Colorado','Connecticut','Delaware',
    'Florida','Georgia','Hawaii','Idaho','Illinois','Indiana','Iowa','Kansas','Kentucky',
    'Louisiana','Maine','Maryland','Massachusetts','Michigan','Minnesota','Mississippi',
    'Missouri','Montana','Nebraska','Nevada','New Hampshire','New Jersey','New Mexico',
    'New York','North Carolina','North Dakota','Ohio','Oklahoma','Oregon','Pennsylvania',
    'Rhode Island','South Carolina','South Dakota','Tennessee','Texas','Utah','Vermont',
    'Virginia','Washington','Washington D.C.','West Virginia','Wisconsin','Wyoming',
    'Puerto Rico','Outside the U.S.',
];

const form = useForm({
    first_name:     props.media.first_name     ?? '',
    last_name:      props.media.last_name      ?? '',
    email:          props.media.email           ?? '',
    phone:          props.media.phone           ?? '',
    status:         props.media.status          ?? 'applicant',
    category:       profile?.category           ?? 'photographer',
    portfolio_url:  profile?.portfolio_url      ?? '',
    instagram:      profile?.instagram          ?? '',
    location:       profile?.location           ?? '',
    will_travel:    profile?.will_travel        ?? 'yes',
    importance:     profile?.importance         ?? 2,
    max_assistants: profile?.max_assistants     ?? 0,
    notes:          profile?.notes              ?? '',
    media_link_1:   profile?.media_link_1       ?? '',
    media_link_2:   profile?.media_link_2       ?? '',
    media_link_3:   profile?.media_link_3       ?? '',
});

function submit() {
    form.put(`/admin/operations/media/${props.media.id}`);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="`/admin/operations/media/${media.id}`" class="text-gray-400 hover:text-gray-600"><ArrowLeftIcon class="w-5 h-5" /></Link>
                <h2 class="text-lg font-semibold text-gray-900">Editar Media — {{ media.first_name }} {{ media.last_name }}</h2>
            </div>
        </template>

        <div class="max-w-2xl mx-auto">
            <form @submit.prevent="submit" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                        <input v-model="form.first_name" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        <p v-if="form.errors.first_name" class="text-xs text-red-500 mt-1">{{ form.errors.first_name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Apellido</label>
                        <input v-model="form.last_name" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input v-model="form.email" type="email" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        <p v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                        <input v-model="form.phone" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                        <select v-model="form.status" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="applicant">Aplicante</option>
                            <option value="pending">Pendiente</option>
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Categoría *</label>
                        <select v-model="form.category" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="photographer">Photographer</option>
                            <option value="videographer">Videographer</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Instagram</label>
                        <input v-model="form.instagram" type="text" placeholder="username" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ubicación</label>
                        <select v-model="form.location" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">Seleccionar...</option>
                            <option v-for="s in usStates" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">¿Viaja para el evento?</label>
                        <select v-model="form.will_travel" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="yes">Sí</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Portfolio URL</label>
                    <input v-model="form.portfolio_url" type="url" placeholder="https://..." class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Importancia</label>
                        <select v-model="form.importance" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option :value="1">1 (Alta)</option>
                            <option :value="2">2 (Media)</option>
                            <option :value="3">3 (Baja)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Asistentes</label>
                        <input v-model.number="form.max_assistants" type="number" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    </div>
                </div>

                <!-- Media Links -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Links de Media (subidos por el usuario)</label>
                    <div class="space-y-2">
                        <input v-model="form.media_link_1" type="url" placeholder="Link 1 — https://..." class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        <input v-model="form.media_link_2" type="url" placeholder="Link 2 — https://..." class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        <input v-model="form.media_link_3" type="url" placeholder="Link 3 — https://..." class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                    <textarea v-model="form.notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                </div>

                <div class="flex gap-3 pt-4">
                    <Link :href="`/admin/operations/media/${media.id}`" class="flex-1 py-2.5 border border-gray-200 rounded-xl text-sm font-semibold text-gray-700 text-center hover:bg-gray-50">Cancelar</Link>
                    <button type="submit" :disabled="form.processing" class="flex-1 py-2.5 bg-black text-white rounded-xl text-sm font-semibold hover:bg-gray-800 disabled:opacity-40">
                        {{ form.processing ? 'Guardando...' : 'Guardar Cambios' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
