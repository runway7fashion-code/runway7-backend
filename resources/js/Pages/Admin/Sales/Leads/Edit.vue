<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    lead: Object,
    events: Array,
    advisors: Array,
});

const form = useForm({
    first_name: props.lead.first_name || '',
    last_name: props.lead.last_name || '',
    email: props.lead.email || '',
    phone: props.lead.phone || '',
    country: props.lead.country || '',
    company_name: props.lead.company_name || '',
    retail_category: props.lead.retail_category || '',
    website_url: props.lead.website_url || '',
    instagram: props.lead.instagram || '',
    designs_ready: props.lead.designs_ready || '',
    budget: props.lead.budget || '',
    past_shows: props.lead.past_shows || '',
    event_id: props.lead.event_id || '',
    preferred_contact_time: props.lead.preferred_contact_time || '',
    notes: props.lead.notes || '',
});

const designsReadyOptions = ['Under 10', 'Under 25', 'Over 25'];
const budgetOptions = ['$5,000 to $10,000', '$10,000 to $25,000', '$25,000 to $75,000', '$75,000+'];
const pastShowsOptions = ['0', '1', '2', '3', '4', '5+'];
const contactTimeOptions = [
    '9:00 AM', '10:00 AM', '11:00 AM', '12:00 PM',
    '1:00 PM', '2:00 PM', '3:00 PM', '4:00 PM', '5:00 PM',
];

function submit() {
    form.put(`/admin/sales/leads/${props.lead.id}`);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/sales/leads" class="text-gray-400 hover:text-gray-600 text-sm flex items-center gap-1">
                    <ArrowLeftIcon class="w-4 h-4" /> Leads
                </Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Editar Lead</h2>
            </div>
        </template>

        <div class="max-w-3xl mx-auto">
            <form @submit.prevent="submit" class="space-y-6">

                <!-- Section 1: Informacion Personal -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37]">Informacion Personal</h3>

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
                            <input v-model="form.phone" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.phone" class="mt-1 text-red-500 text-xs">{{ form.errors.phone }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Pais</label>
                            <input v-model="form.country" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.country" class="mt-1 text-red-500 text-xs">{{ form.errors.country }}</p>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Informacion del Negocio -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37]">Informacion del Negocio</h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de Empresa</label>
                            <input v-model="form.company_name" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.company_name" class="mt-1 text-red-500 text-xs">{{ form.errors.company_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Retail Category</label>
                            <input v-model="form.retail_category" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.retail_category" class="mt-1 text-red-500 text-xs">{{ form.errors.retail_category }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Website URL</label>
                            <input v-model="form.website_url" type="url" placeholder="https://..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.website_url" class="mt-1 text-red-500 text-xs">{{ form.errors.website_url }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Instagram</label>
                            <input v-model="form.instagram" type="text" placeholder="@usuario"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.instagram" class="mt-1 text-red-500 text-xs">{{ form.errors.instagram }}</p>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Detalles -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37]">Detalles</h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Designs Ready</label>
                            <select v-model="form.designs_ready"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">-- Seleccionar --</option>
                                <option v-for="opt in designsReadyOptions" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                            <p v-if="form.errors.designs_ready" class="mt-1 text-red-500 text-xs">{{ form.errors.designs_ready }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Budget</label>
                            <select v-model="form.budget"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">-- Seleccionar --</option>
                                <option v-for="opt in budgetOptions" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                            <p v-if="form.errors.budget" class="mt-1 text-red-500 text-xs">{{ form.errors.budget }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Past Shows</label>
                            <select v-model="form.past_shows"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">-- Seleccionar --</option>
                                <option v-for="opt in pastShowsOptions" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                            <p v-if="form.errors.past_shows" class="mt-1 text-red-500 text-xs">{{ form.errors.past_shows }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Evento</label>
                            <select v-model="form.event_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">-- Sin asignar --</option>
                                <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                            </select>
                            <p v-if="form.errors.event_id" class="mt-1 text-red-500 text-xs">{{ form.errors.event_id }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Contact Time</label>
                            <select v-model="form.preferred_contact_time"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">-- Seleccionar --</option>
                                <option v-for="t in contactTimeOptions" :key="t" :value="t">{{ t }}</option>
                            </select>
                            <p v-if="form.errors.preferred_contact_time" class="mt-1 text-red-500 text-xs">{{ form.errors.preferred_contact_time }}</p>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Notas (sin Asignacion en Edit) -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800 pb-2 border-b-2 border-[#D4AF37]">Notas</h3>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                        <textarea v-model="form.notes" rows="3"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                        <p v-if="form.errors.notes" class="mt-1 text-red-500 text-xs">{{ form.errors.notes }}</p>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex justify-between">
                    <Link href="/admin/sales/leads"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        Cancelar
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-8 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-60 transition-colors">
                        <span v-if="form.processing">Guardando...</span>
                        <span v-else>Guardar Cambios</span>
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
