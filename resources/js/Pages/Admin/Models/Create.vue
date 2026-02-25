<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    events: Array,
});

const activeTab = ref(1);

const form = useForm({
    // Pestaña 1 - Datos personales
    first_name:   '',
    last_name:    '',
    email:        '',
    phone:        '',
    instagram:    '',
    age:          '',
    gender:       'female',
    location:     '',
    ethnicity:    '',
    hair:         '',
    body_type:    '',
    // Pestaña 2 - Medidas
    height:       '',
    bust:         '',
    chest:        '',
    waist:        '',
    hips:         '',
    shoe_size:    '',
    dress_size:   '',
    // Agencia
    agency:       '',
    is_agency:    false,
    is_test_model: false,
    notes:        '',
    // Pestaña 3 - Evento
    event_id:     '',
    casting_time: '',
    send_welcome_email: false,
});

const selectedEvent = computed(() => props.events.find(e => e.id == form.event_id) ?? null);
const castingSlots  = computed(() => selectedEvent.value?.casting_day?.slots ?? []);

function slotColor(slot) {
    if (slot.available === 0) return 'bg-red-50 border-red-300 text-red-600 opacity-60 cursor-not-allowed';
    if (slot.available <= 10) return 'bg-yellow-50 border-yellow-300 text-yellow-700 hover:bg-yellow-100 cursor-pointer';
    return 'bg-green-50 border-green-300 text-green-700 hover:bg-green-100 cursor-pointer';
}

function selectSlot(slot) {
    if (slot.available === 0) return;
    form.casting_time = form.casting_time === slot.time ? '' : slot.time;
}

function formatSlotTime(t) {
    const [h, m] = t.split(':');
    const hour = parseInt(h);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const h12  = hour % 12 || 12;
    return `${h12}:${m} ${ampm}`;
}

function submit() {
    form.post('/admin/models');
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/models" class="text-gray-400 hover:text-gray-600 text-sm">← Modelos</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Crear Modelo</h2>
            </div>
        </template>

        <div class="max-w-3xl mx-auto">
            <!-- Tabs -->
            <div class="flex gap-1 bg-gray-100 rounded-xl p-1 mb-6">
                <button v-for="tab in [
                    { n: 1, label: 'Datos Personales' },
                    { n: 2, label: 'Medidas' },
                    { n: 3, label: 'Evento y Casting' },
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
                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        La contraseña de acceso a la app será <strong>runway7</strong> para todas las modelos.
                    </p>

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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Color de cabello</label>
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

                    <div class="flex gap-6 pt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.is_test_model" type="checkbox"
                                class="rounded border-gray-300 text-black focus:ring-black/20" />
                            <span class="text-sm text-gray-700">Modelo de prueba</span>
                        </label>
                    </div>
                </div>

                <!-- Pestaña 2: Medidas -->
                <div v-show="activeTab === 2" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="font-semibold text-gray-800">Medidas</h3>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Altura (cm)</label>
                            <input v-model="form.height" type="number" step="0.1" min="140" max="220"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Busto / Pecho (cm)</label>
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Talla de zapato</label>
                            <input v-model="form.shoe_size" type="text" placeholder="ej. 8.5"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Talla de ropa</label>
                            <input v-model="form.dress_size" type="text" placeholder="ej. S, M, 4"
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
                <div v-show="activeTab === 3" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Asignar a evento</label>
                        <select v-model="form.event_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">— Sin asignar —</option>
                            <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>

                    <!-- Casting slots del evento seleccionado -->
                    <div v-if="selectedEvent && castingSlots.length">
                        <p class="text-sm font-medium text-gray-700 mb-1">
                            Casting:
                            <span class="text-gray-500 font-normal">{{ selectedEvent.casting_day?.date }}</span>
                        </p>
                        <p class="text-xs text-gray-400 mb-3">Selecciona el horario de casting de la modelo:</p>

                        <div class="grid grid-cols-4 gap-2">
                            <button
                                v-for="slot in castingSlots"
                                :key="slot.id"
                                type="button"
                                :disabled="slot.available === 0"
                                @click="selectSlot(slot)"
                                :class="[
                                    slotColor(slot),
                                    form.casting_time === slot.time ? 'ring-2 ring-black ring-offset-1' : '',
                                    'border rounded-lg p-2 text-center text-xs transition-all'
                                ]">
                                <p class="font-semibold">{{ formatSlotTime(slot.time) }}</p>
                                <p class="text-[10px] mt-0.5 opacity-80">{{ slot.booked }}/{{ slot.capacity }}</p>
                            </button>
                        </div>

                        <p v-if="form.casting_time" class="mt-2 text-sm text-green-700">
                            ✓ Horario seleccionado: <strong>{{ formatSlotTime(form.casting_time) }}</strong>
                        </p>
                    </div>

                    <div v-else-if="selectedEvent && !castingSlots.length" class="text-sm text-gray-400 italic">
                        Este evento no tiene día de casting configurado.
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.send_welcome_email" type="checkbox"
                                class="rounded border-gray-300 text-black focus:ring-black/20" />
                            <span class="text-sm text-gray-700">Enviar email de bienvenida al crear</span>
                        </label>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex justify-between">
                    <Link href="/admin/models"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        Cancelar
                    </Link>
                    <div class="flex gap-3">
                        <button v-if="activeTab > 1" type="button" @click="activeTab--"
                            class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                            ← Anterior
                        </button>
                        <button v-if="activeTab < 3" type="button" @click="activeTab++"
                            class="px-6 py-2.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-black transition-colors">
                            Siguiente →
                        </button>
                        <button v-else type="submit" :disabled="form.processing"
                            class="px-8 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-60 transition-colors">
                            <span v-if="form.processing">Creando...</span>
                            <span v-else>Crear Modelo</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
