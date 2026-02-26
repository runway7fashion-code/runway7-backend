<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    events:     Array,
    categories: Array,
    packages:   Array,
    salesReps:  Array,
});

const activeTab = ref(1);

const form = useForm({
    // Tab 1 - Datos personales
    first_name:      '',
    last_name:       '',
    email:           '',
    phone:           '',
    brand_name:      '',
    collection_name: '',
    category_id:     '',
    sales_rep_id:    '',
    country:         '',
    bio:             '',
    tracking_link:   '',
    skype:           '',
    social_media: {
        instagram: '',
        facebook:  '',
        tiktok:    '',
        website:   '',
        other:     '',
    },
    // Tab 2 - Evento y Show
    event_id:              '',
    package_id:            '',
    looks:                 '',
    model_casting_enabled: true,
    package_price:         '',
    notes:                 '',
    shows:                 [],
    // Tab 3 - Asistentes
    assistants: [],
});

// Evento seleccionado
const selectedEvent = computed(() => props.events?.find(e => e.id == form.event_id) ?? null);

// Paquete seleccionado - auto-fill looks y price
const selectedPackage = computed(() => props.packages?.find(p => p.id == form.package_id) ?? null);

watch(() => form.package_id, (newId) => {
    const pkg = props.packages?.find(p => p.id == newId);
    if (pkg) {
        form.looks = pkg.default_looks;
        form.package_price = pkg.price;
    }
});

// Shows del evento seleccionado (agrupados por día)
const eventDays = computed(() => selectedEvent.value?.days ?? []);

function isShowSelected(showId) {
    return form.shows.some(s => s.show_id === showId);
}

function toggleShow(showId) {
    const idx = form.shows.findIndex(s => s.show_id === showId);
    if (idx >= 0) {
        form.shows.splice(idx, 1);
    } else {
        form.shows.push({ show_id: showId, collection_name: '' });
    }
}

// Asistentes
function addAssistant() {
    form.assistants.push({ full_name: '', document_id: '', phone: '', email: '' });
}

function removeAssistant(index) {
    form.assistants.splice(index, 1);
}

function submit() {
    form.post('/admin/designers');
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/designers" class="text-gray-400 hover:text-gray-600 text-sm">← Diseñadores</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Crear Diseñador</h2>
            </div>
        </template>

        <div class="max-w-3xl mx-auto">
            <!-- Tabs -->
            <div class="flex gap-1 bg-gray-100 rounded-xl p-1 mb-6">
                <button v-for="tab in [
                    { n: 1, label: 'Datos Personales' },
                    { n: 2, label: 'Evento y Show' },
                    { n: 3, label: 'Asistentes' },
                ]" :key="tab.n"
                    type="button"
                    @click="activeTab = tab.n"
                    class="flex-1 py-2 text-sm font-medium rounded-lg transition-all"
                    :class="activeTab === tab.n ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                    {{ tab.label }}
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-5">

                <!-- Tab 1: Datos Personales -->
                <div v-show="activeTab === 1" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        La contraseña de acceso a la app sera <strong>runway7</strong> para todos los diseñadores.
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telefono</label>
                            <input v-model="form.phone" type="tel"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Marca / Brand *</label>
                            <input v-model="form.brand_name" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.brand_name" class="mt-1 text-red-500 text-xs">{{ form.errors.brand_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de Coleccion</label>
                            <input v-model="form.collection_name" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                            <select v-model="form.category_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Sin categoria —</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Pais</label>
                            <input v-model="form.country" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div v-if="salesReps?.length" class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Representante de Ventas</label>
                            <select v-model="form.sales_rep_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">— Sin asignar —</option>
                                <option v-for="rep in salesReps" :key="rep.id" :value="rep.id">{{ rep.first_name }} {{ rep.last_name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tracking Link</label>
                            <input v-model="form.tracking_link" type="text" placeholder="https://..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Skype</label>
                            <input v-model="form.skype" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bio</label>
                        <textarea v-model="form.bio" rows="3"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                    </div>

                    <!-- Redes sociales -->
                    <div class="border-t border-gray-100 pt-4">
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">Redes Sociales</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Instagram</label>
                                <input v-model="form.social_media.instagram" type="text" placeholder="@usuario"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Facebook</label>
                                <input v-model="form.social_media.facebook" type="text"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">TikTok</label>
                                <input v-model="form.social_media.tiktok" type="text" placeholder="@usuario"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Website</label>
                                <input v-model="form.social_media.website" type="text" placeholder="https://..."
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs text-gray-500 mb-1">Otro</label>
                                <input v-model="form.social_media.other" type="text"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Evento y Show -->
                <div v-show="activeTab === 2" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Asignar a evento</label>
                        <select v-model="form.event_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">— Sin asignar —</option>
                            <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>

                    <div v-if="form.event_id" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Paquete</label>
                                <select v-model="form.package_id"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                    <option value="">— Sin paquete —</option>
                                    <option v-for="p in packages" :key="p.id" :value="p.id">
                                        {{ p.name }} — ${{ Number(p.price).toLocaleString() }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Looks</label>
                                <input v-model="form.looks" type="number" min="0"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Precio del paquete ($)</label>
                                <input v-model="form.package_price" type="number" step="0.01" min="0"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div class="flex items-end pb-1">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.model_casting_enabled" type="checkbox"
                                        class="rounded border-gray-300 text-black focus:ring-black/20" />
                                    <span class="text-sm text-gray-700">Casting de modelos habilitado</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                            <textarea v-model="form.notes" rows="2"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                        </div>

                        <!-- Shows del evento -->
                        <div v-if="eventDays.length" class="border-t border-gray-100 pt-4">
                            <h4 class="text-sm font-semibold text-gray-800 mb-3">Asignar a Shows</h4>
                            <div v-for="day in eventDays" :key="day.id" class="mb-3 last:mb-0">
                                <p class="text-xs text-gray-500 mb-2">{{ day.label }} — {{ day.date }}</p>
                                <div class="flex flex-wrap gap-2">
                                    <button v-for="show in day.shows" :key="show.id"
                                        type="button"
                                        @click="toggleShow(show.id)"
                                        :class="isShowSelected(show.id)
                                            ? 'bg-black text-white border-black'
                                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                        class="px-3 py-1.5 border rounded-lg text-xs font-medium transition-all">
                                        {{ show.name }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Asistentes -->
                <div v-show="activeTab === 3" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800">Asistentes</h3>
                        <button type="button" @click="addAssistant"
                            class="px-3 py-1.5 text-xs bg-black text-white rounded-lg hover:bg-gray-800 transition-colors">
                            + Agregar Asistente
                        </button>
                    </div>

                    <p v-if="form.assistants.length === 0" class="text-sm text-gray-400 italic">
                        No hay asistentes. Haz clic en "+ Agregar Asistente" para añadir.
                    </p>

                    <div v-for="(assistant, i) in form.assistants" :key="i"
                        class="border border-gray-200 rounded-xl p-4 space-y-3 relative">
                        <button type="button" @click="removeAssistant(i)"
                            class="absolute top-3 right-3 text-red-400 hover:text-red-600 text-sm">✕</button>

                        <p class="text-xs font-semibold text-gray-500">Asistente {{ i + 1 }}</p>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Nombre completo *</label>
                                <input v-model="assistant.full_name" type="text"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Documento ID</label>
                                <input v-model="assistant.document_id" type="text"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Telefono</label>
                                <input v-model="assistant.phone" type="tel"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Email</label>
                                <input v-model="assistant.email" type="email"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                        </div>
                    </div>

                    <p v-if="selectedPackage && form.assistants.length > 0"
                        class="text-xs text-gray-500">
                        Paquete {{ selectedPackage.name }} incluye {{ selectedPackage.default_assistants }} asistentes.
                        <span v-if="form.assistants.length > selectedPackage.default_assistants" class="text-amber-600 font-medium">
                            Excedido por {{ form.assistants.length - selectedPackage.default_assistants }}.
                        </span>
                    </p>
                </div>

                <!-- Botones -->
                <div class="flex justify-between">
                    <Link href="/admin/designers"
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
                            <span v-else>Crear Diseñador</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
