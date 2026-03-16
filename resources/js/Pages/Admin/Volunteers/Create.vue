<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    events: Array,
});

const activeTab = ref(1);

const form = useForm({
    // Pestaña 1 - Datos personales
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    age: '',
    gender: 'female',
    location: '',
    instagram: '',
    // Pestaña 2 - Detalles voluntario
    tshirt_size: '',
    experience: '',
    comfortable_fast_paced: '',
    full_availability: '',
    contribution: '',
    resume_link: '',
    notes: '',
    // Pestaña 3 - Evento
    event_id: '',
});

const ageOptions = Array.from({ length: 63 }, (_, i) => i + 18);

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
            <div class="flex items-center gap-3">
                <Link href="/admin/volunteers" class="text-gray-400 hover:text-gray-600 text-sm">&larr; Voluntarios</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Crear Voluntario</h2>
            </div>
        </template>

        <div class="max-w-3xl mx-auto">
            <!-- Tabs -->
            <div class="flex gap-1 bg-gray-100 rounded-xl p-1 mb-6">
                <button v-for="tab in [
                    { n: 1, label: 'Datos Personales' },
                    { n: 2, label: 'Detalles Voluntario' },
                    { n: 3, label: 'Evento' },
                ]" :key="tab.n"
                    type="button"
                    @click="activeTab = tab.n"
                    class="flex-1 py-2 text-sm font-medium rounded-lg transition-all"
                    :class="activeTab === tab.n ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                    {{ tab.label }}
                </button>
            </div>

            <form @submit.prevent="submit" novalidate class="space-y-5">

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
                            <p v-if="form.errors.last_name" class="mt-1 text-red-500 text-xs">{{ form.errors.last_name }}</p>
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono *</label>
                            <input v-model="form.phone" type="tel"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.phone" class="mt-1 text-red-500 text-xs">{{ form.errors.phone }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Edad *</label>
                            <select v-model="form.age"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Seleccionar —</option>
                                <option v-for="a in ageOptions" :key="a" :value="a">{{ a }}</option>
                            </select>
                            <p v-if="form.errors.age" class="mt-1 text-red-500 text-xs">{{ form.errors.age }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Género *</label>
                            <select v-model="form.gender"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="female">Femenino</option>
                                <option value="male">Masculino</option>
                                <option value="non_binary">No binario</option>
                            </select>
                            <p v-if="form.errors.gender" class="mt-1 text-red-500 text-xs">{{ form.errors.gender }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Instagram</label>
                            <input v-model="form.instagram" type="text" placeholder="@usuario"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ubicación *</label>
                        <select v-model="form.location"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">— Seleccionar —</option>
                            <option v-for="st in usStates" :key="st" :value="st">{{ st }}</option>
                        </select>
                        <p v-if="form.errors.location" class="mt-1 text-red-500 text-xs">{{ form.errors.location }}</p>
                    </div>
                </div>

                <!-- Pestaña 2: Detalles Voluntario -->
                <div v-show="activeTab === 2" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="font-semibold text-gray-800">Detalles</h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Talla Camiseta *</label>
                            <select v-model="form.tshirt_size"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Seleccionar —</option>
                                <option value="XS">XS</option>
                                <option value="S">S</option>
                                <option value="M">M</option>
                                <option value="L">L</option>
                                <option value="XL">XL</option>
                                <option value="XXL">XXL</option>
                            </select>
                            <p v-if="form.errors.tshirt_size" class="mt-1 text-red-500 text-xs">{{ form.errors.tshirt_size }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Experiencia *</label>
                            <select v-model="form.experience"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Seleccionar —</option>
                                <option value="none">Sin experiencia</option>
                                <option value="some">Algo de experiencia</option>
                                <option value="experienced">Con experiencia</option>
                            </select>
                            <p v-if="form.errors.experience" class="mt-1 text-red-500 text-xs">{{ form.errors.experience }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estilo de trabajo *</label>
                            <select v-model="form.comfortable_fast_paced"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Seleccionar —</option>
                                <option value="multitask">Multitarea / Dinámico</option>
                                <option value="structured">Estructurado</option>
                            </select>
                            <p v-if="form.errors.comfortable_fast_paced" class="mt-1 text-red-500 text-xs">{{ form.errors.comfortable_fast_paced }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Disponibilidad *</label>
                            <select v-model="form.full_availability"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Seleccionar —</option>
                                <option value="yes">Completa</option>
                                <option value="no">No disponible</option>
                                <option value="partially">Parcial</option>
                            </select>
                            <p v-if="form.errors.full_availability" class="mt-1 text-red-500 text-xs">{{ form.errors.full_availability }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contribución</label>
                        <textarea v-model="form.contribution" rows="3"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                        <p v-if="form.errors.contribution" class="mt-1 text-red-500 text-xs">{{ form.errors.contribution }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Resume Link</label>
                        <input v-model="form.resume_link" type="url" placeholder="https://docs.google.com/..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        <p v-if="form.errors.resume_link" class="mt-1 text-red-500 text-xs">{{ form.errors.resume_link }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notas Internas</label>
                        <textarea v-model="form.notes" rows="3" placeholder="Notas visibles solo para admin/operation..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                    </div>
                </div>

                <!-- Pestaña 3: Evento -->
                <div v-show="activeTab === 3" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Asignar a evento</label>
                        <select v-model="form.event_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">— Sin asignar —</option>
                            <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                        <p v-if="form.errors.event_id" class="mt-1 text-red-500 text-xs">{{ form.errors.event_id }}</p>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex justify-between">
                    <Link href="/admin/volunteers"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        Cancelar
                    </Link>
                    <div class="flex gap-3">
                        <button v-if="activeTab > 1" type="button" @click="activeTab--"
                            class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                            &larr; Anterior
                        </button>
                        <button v-if="activeTab < 3" type="button" @click="activeTab++"
                            class="px-6 py-2.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-black transition-colors">
                            Siguiente &rarr;
                        </button>
                        <button v-else type="submit" :disabled="form.processing"
                            class="px-8 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-60 transition-colors">
                            <span v-if="form.processing">Creando...</span>
                            <span v-else>Crear Voluntario</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
