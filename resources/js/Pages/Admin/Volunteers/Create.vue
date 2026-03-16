<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    events: Array,
});

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    age: '',
    gender: '',
    location: '',
    instagram: '',
    tshirt_size: '',
    experience: '',
    comfortable_fast_paced: '',
    full_availability: '',
    contribution: '',
    resume_link: '',
    event_id: '',
});

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

function submit() {
    form.post('/admin/volunteers');
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Crear Voluntario</h2>
        </template>

        <div class="max-w-3xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-bold text-gray-900">Nuevo Voluntario</h3>
                <Link href="/admin/volunteers" class="text-sm text-gray-500 hover:text-gray-700">&larr; Volver</Link>
            </div>

            <form @submit.prevent="submit" class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
                <!-- Información Personal -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4 pb-2 border-b border-gray-200">Información Personal</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                            <input v-model="form.first_name" type="text" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.first_name" class="text-red-500 text-xs mt-1">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Apellido *</label>
                            <input v-model="form.last_name" type="text" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.last_name" class="text-red-500 text-xs mt-1">{{ form.errors.last_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input v-model="form.email" type="email" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono *</label>
                            <input v-model="form.phone" type="tel" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.phone" class="text-red-500 text-xs mt-1">{{ form.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Edad *</label>
                            <input v-model="form.age" type="number" min="18" max="80" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.age" class="text-red-500 text-xs mt-1">{{ form.errors.age }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Género *</label>
                            <select v-model="form.gender" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">Seleccionar...</option>
                                <option value="female">Femenino</option>
                                <option value="male">Masculino</option>
                                <option value="non_binary">No Binario</option>
                            </select>
                            <p v-if="form.errors.gender" class="text-red-500 text-xs mt-1">{{ form.errors.gender }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ubicación *</label>
                            <select v-model="form.location" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">Seleccionar...</option>
                                <option v-for="st in usStates" :key="st" :value="st">{{ st }}</option>
                            </select>
                            <p v-if="form.errors.location" class="text-red-500 text-xs mt-1">{{ form.errors.location }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Instagram</label>
                            <input v-model="form.instagram" type="text" placeholder="username" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.instagram" class="text-red-500 text-xs mt-1">{{ form.errors.instagram }}</p>
                        </div>
                    </div>
                </div>

                <!-- Detalles de Voluntario -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4 pb-2 border-b border-gray-200">Detalles de Voluntario</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Talla Camiseta *</label>
                            <select v-model="form.tshirt_size" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">Seleccionar...</option>
                                <option value="XS">XS</option>
                                <option value="S">S</option>
                                <option value="M">M</option>
                                <option value="L">L</option>
                                <option value="XL">XL</option>
                                <option value="XXL">XXL</option>
                            </select>
                            <p v-if="form.errors.tshirt_size" class="text-red-500 text-xs mt-1">{{ form.errors.tshirt_size }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Experiencia *</label>
                            <select v-model="form.experience" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">Seleccionar...</option>
                                <option value="none">Sin experiencia</option>
                                <option value="some">Algo de experiencia</option>
                                <option value="experienced">Con experiencia</option>
                            </select>
                            <p v-if="form.errors.experience" class="text-red-500 text-xs mt-1">{{ form.errors.experience }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estilo de trabajo *</label>
                            <select v-model="form.comfortable_fast_paced" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">Seleccionar...</option>
                                <option value="multitask">Multitarea / Dinámico</option>
                                <option value="structured">Estructurado</option>
                            </select>
                            <p v-if="form.errors.comfortable_fast_paced" class="text-red-500 text-xs mt-1">{{ form.errors.comfortable_fast_paced }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Disponibilidad *</label>
                            <select v-model="form.full_availability" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">Seleccionar...</option>
                                <option value="yes">Completa</option>
                                <option value="no">No disponible</option>
                                <option value="partially">Parcial</option>
                            </select>
                            <p v-if="form.errors.full_availability" class="text-red-500 text-xs mt-1">{{ form.errors.full_availability }}</p>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contribución</label>
                            <textarea v-model="form.contribution" rows="3" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10"></textarea>
                            <p v-if="form.errors.contribution" class="text-red-500 text-xs mt-1">{{ form.errors.contribution }}</p>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Resume Link</label>
                            <input v-model="form.resume_link" type="url" placeholder="https://..." class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.resume_link" class="text-red-500 text-xs mt-1">{{ form.errors.resume_link }}</p>
                        </div>
                    </div>
                </div>

                <!-- Evento -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4 pb-2 border-b border-gray-200">Evento</h4>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Asignar a evento</label>
                        <select v-model="form.event_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                            <option value="">Sin asignar</option>
                            <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                        </select>
                        <p v-if="form.errors.event_id" class="text-red-500 text-xs mt-1">{{ form.errors.event_id }}</p>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <Link href="/admin/volunteers" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-6 py-2 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition-colors disabled:opacity-40">
                        {{ form.processing ? 'Guardando...' : 'Crear Voluntario' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
