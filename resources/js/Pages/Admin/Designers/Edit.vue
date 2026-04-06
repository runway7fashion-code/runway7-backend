<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    designer:   Object,
    events:     Array,
    categories: Array,
    packages:   Array,
    salesReps:  Array,
    countries:  Object,
});

const activeTab = ref(1);
const profile   = props.designer.designer_profile;

const countryCodes = [
    { code: '+1',   label: 'US/CA +1' },
    { code: '+44',  label: 'UK +44' },
    { code: '+33',  label: 'FR +33' },
    { code: '+39',  label: 'IT +39' },
    { code: '+34',  label: 'ES +34' },
    { code: '+49',  label: 'DE +49' },
    { code: '+55',  label: 'BR +55' },
    { code: '+52',  label: 'MX +52' },
    { code: '+57',  label: 'CO +57' },
    { code: '+51',  label: 'PE +51' },
    { code: '+54',  label: 'AR +54' },
    { code: '+56',  label: 'CL +56' },
    { code: '+91',  label: 'IN +91' },
    { code: '+86',  label: 'CN +86' },
    { code: '+81',  label: 'JP +81' },
    { code: '+82',  label: 'KR +82' },
    { code: '+61',  label: 'AU +61' },
    { code: '+971', label: 'AE +971' },
    { code: '+234', label: 'NG +234' },
    { code: '+27',  label: 'ZA +27' },
];

function parsePhone(full) {
    if (!full || !full.startsWith('+')) return { code: '+1', number: full ?? '' };
    const match = countryCodes.find(c => full.startsWith(c.code));
    if (match) return { code: match.code, number: full.slice(match.code.length) };
    return { code: '+1', number: full.replace(/^\+/, '') };
}

const parsed = parsePhone(props.designer.phone);
const phoneCode = ref(parsed.code);
const phoneNumber = ref(parsed.number);

const form = useForm({
    first_name:      props.designer.first_name  ?? '',
    last_name:       props.designer.last_name   ?? '',
    email:           props.designer.email       ?? '',
    phone:           props.designer.phone       ?? '',
    status:          props.designer.status      ?? 'pending',
    brand_name:      profile?.brand_name        ?? '',
    collection_name: profile?.collection_name   ?? '',
    category_id:     profile?.category_id       ?? '',
    sales_rep_id:    profile?.sales_rep_id      ?? '',
    country:         profile?.country           ?? '',
    bio:             profile?.bio               ?? '',
    tracking_link:   profile?.tracking_link     ?? '',
    skype:           profile?.skype             ?? '',
    social_media: {
        instagram: profile?.social_media?.instagram ?? '',
        facebook:  profile?.social_media?.facebook  ?? '',
        tiktok:    profile?.social_media?.tiktok    ?? '',
        website:   profile?.social_media?.website   ?? '',
        other:     profile?.social_media?.other     ?? '',
    },
});

// Eventos del diseñador
const designerEvents = computed(() => props.designer.events ?? []);
const designerShows  = computed(() => props.designer.shows ?? []);
const designerFittings = computed(() => props.designer.fittings ?? []);

// Asignar evento
const selectedEventId = ref('');
const selectedPackageId = ref('');
const assignLooks = ref('');
const assignPrice = ref('');
const assignCasting = ref(true);
const assignMedia = ref(false);
const assignBackground = ref(false);
const assignTickets = ref(false);
const assignNotes = ref('');

const selectedEventData = computed(() => props.events?.find(e => e.id == selectedEventId.value) ?? null);
const eventDays = computed(() => selectedEventData.value?.days ?? []);

watch(selectedPackageId, (newId) => {
    const pkg = props.packages?.find(p => p.id == newId);
    if (pkg) {
        assignLooks.value = pkg.default_looks;
        assignPrice.value = pkg.price;
    }
});

// Shows del evento seleccionado
const assignShows = ref([]);
const assignFittingSlotId = ref('');

// Fitting slots del evento seleccionado (de cualquier día)
const eventFittingSlots = computed(() => {
    const slots = [];
    for (const day of (selectedEventData.value?.days ?? [])) {
        for (const slot of day.fitting_slots ?? []) {
            slots.push({ ...slot, day_label: day.label, day_date: day.date });
        }
    }
    return slots;
});

function isShowSelected(showId) {
    return assignShows.value.some(s => s.show_id === showId);
}

function toggleShow(showId) {
    const idx = assignShows.value.findIndex(s => s.show_id === showId);
    if (idx >= 0) {
        assignShows.value.splice(idx, 1);
    } else {
        assignShows.value.push({ show_id: showId, collection_name: '' });
    }
}

function assignEvent() {
    if (!selectedEventId.value) return;
    router.post(`/admin/operations/designers/${props.designer.id}/assign-event`, {
        event_id:              selectedEventId.value,
        package_id:            selectedPackageId.value || null,
        looks:                 assignLooks.value || null,
        model_casting_enabled: assignCasting.value,
        media_package:         assignMedia.value,
        custom_background:     assignBackground.value,
        courtesy_tickets:      assignTickets.value,
        package_price:         assignPrice.value || null,
        notes:                 assignNotes.value || null,
        shows:                 assignShows.value.length ? assignShows.value : null,
        fitting_slot_id:       assignFittingSlotId.value || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedEventId.value = '';
            selectedPackageId.value = '';
            assignLooks.value = '';
            assignPrice.value = '';
            assignCasting.value = true;
            assignMedia.value = false;
            assignBackground.value = false;
            assignTickets.value = false;
            assignNotes.value = '';
            assignShows.value = [];
            assignFittingSlotId.value = '';
        },
    });
}

function cancelFromEvent(eventId, eventName) {
    if (!confirm(`¿Cancelar participación en "${eventName}"? Se cancelarán todos sus shows y pases, pero se conservarán materiales y asistentes.`)) return;
    router.patch(`/admin/operations/designers/${props.designer.id}/cancel-event/${eventId}`, {}, { preserveScroll: true });
}

function removeFromEvent(eventId, eventName) {
    if (!confirm(`¿Quitar completamente del evento "${eventName}"? Se eliminarán todos los datos asociados.`)) return;
    router.delete(`/admin/operations/designers/${props.designer.id}/remove-event/${eventId}`, { preserveScroll: true });
}

// Asistentes
const assistants = computed(() => props.designer.assistants ?? []);
const newAssistant = ref({ first_name: '', last_name: '', document_id: '', phone: '', email: '' });
const assistantEventId = ref(designerEvents.value[0]?.id ?? '');

const assistantEventLimit = computed(() => {
    const evt = designerEvents.value.find(e => e.id == assistantEventId.value);
    return evt?.assistants ?? null;
});
const assistantEventCount = computed(() =>
    assistants.value.filter(a => a.event_id == assistantEventId.value).length
);
const assistantLimitReached = computed(() =>
    assistantEventLimit.value !== null && assistantEventCount.value >= assistantEventLimit.value
);

function addAssistant() {
    if (!newAssistant.value.first_name || !assistantEventId.value) return;
    router.post(`/admin/operations/designers/${props.designer.id}/assistants`, {
        event_id:    assistantEventId.value,
        ...newAssistant.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            newAssistant.value = { first_name: '', last_name: '', document_id: '', phone: '', email: '' };
        },
    });
}

function removeAssistant(assistantId) {
    if (!confirm('Eliminar asistente?')) return;
    router.delete(`/admin/operations/designers/assistants/${assistantId}`, { preserveScroll: true });
}

// Materiales
const materials = computed(() => props.designer.materials ?? []);

function updateMaterial(material, field, value) {
    router.put(`/admin/operations/designer-materials/${material.id}`, {
        [field]: value,
    }, { preserveScroll: true });
}

// Displays
const displays = computed(() => props.designer.displays ?? []);

function updateDisplay(display, data) {
    router.put(`/admin/operations/designer-displays/${display.id}`, data, { preserveScroll: true });
}

function uploadDisplayFile(displayId, type) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = type === 'video' ? 'video/*' : 'audio/*';
    input.onchange = (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('file', file);
        const endpoint = type === 'video' ? 'upload-video' : 'upload-audio';
        router.post(`/admin/operations/designer-displays/${displayId}/${endpoint}`, formData, { preserveScroll: true });
    };
    input.click();
}

function materialStatusClass(status) {
    return {
        pending:   'text-yellow-700 bg-yellow-50',
        submitted: 'text-blue-700 bg-blue-50',
        confirmed: 'text-green-700 bg-green-50',
        rejected:  'text-red-600 bg-red-50',
    }[status] ?? 'text-gray-600 bg-gray-50';
}

function materialStatusLabel(status) {
    return { pending: 'Pendiente', submitted: 'Enviado', confirmed: 'Confirmado', rejected: 'Rechazado' }[status] ?? status;
}

function displayStatusLabel(status) {
    return { pending: 'Pendiente', ready: 'Listo', confirmed: 'Confirmado' }[status] ?? status;
}

function materialsProgress(eventId) {
    const mats = materials.value.filter(m => m.event_id === eventId);
    if (mats.length === 0) return 0;
    const done = mats.filter(m => m.status === 'confirmed' || m.status === 'submitted').length;
    return Math.round((done / mats.length) * 100);
}

function progressColor(pct) {
    if (pct === 100) return 'bg-green-500';
    if (pct >= 50)   return 'bg-yellow-400';
    return 'bg-red-300';
}

function eventName(eventId) {
    return designerEvents.value.find(e => e.id === eventId)?.name ?? `Evento #${eventId}`;
}

function showStatusLabel(s) {
    return { confirmed: 'Confirmado', cancelled: 'Cancelado' }[s] ?? s;
}

// Cancelar participación en un show (mantiene historial, status=cancelled)
function cancelShow(showId, showName) {
    if (!confirm(`¿Cancelar participación en "${showName}"? El show quedará marcado como cancelado.`)) return;
    router.patch(`/admin/operations/designers/${props.designer.id}/shows/${showId}/cancel`, {}, { preserveScroll: true });
}

// Quitar show completamente (elimina el registro)
function removeShow(showId, showName) {
    if (!confirm(`¿Quitar completamente el show "${showName}"? Esta acción no se puede deshacer.`)) return;
    router.delete(`/admin/operations/designers/${props.designer.id}/shows/${showId}`, { preserveScroll: true });
}

// Shows agrupados por evento
function showsForEvent(eventId) {
    return designerShows.value.filter(s => s.event_day?.event_id === eventId);
}

function availableShowsForEvent(eventId) {
    const assignedShowIds = designerShows.value.map(s => s.id);
    const eventData = props.events?.find(e => e.id === eventId);
    if (!eventData) return [];
    const all = [];
    for (const day of eventData.days ?? []) {
        for (const show of day.shows) {
            if (!assignedShowIds.includes(show.id)) {
                all.push({ ...show, dayLabel: day.label });
            }
        }
    }
    return all;
}

// Estado para agregar show (por evento)
const addShowState = ref({});

function getAddShowState(eventId) {
    if (!addShowState.value[eventId]) {
        addShowState.value[eventId] = { showId: '', collection: '' };
    }
    return addShowState.value[eventId];
}

// Fitting helpers para eventos ya asignados
function fittingForEvent(eventId) {
    return designerFittings.value.find(f => f.event_id === eventId) ?? null;
}

function fittingSlotsForEvent(eventId) {
    const eventData = props.events?.find(e => e.id === eventId);
    if (!eventData) return [];
    const slots = [];
    for (const day of eventData.days ?? []) {
        for (const slot of day.fitting_slots ?? []) {
            slots.push({ ...slot, day_label: day.label, day_date: day.date });
        }
    }
    return slots;
}

function updateFitting(eventId, fittingSlotId) {
    router.put(`/admin/operations/designers/${props.designer.id}/fitting`, {
        event_id: eventId,
        fitting_slot_id: fittingSlotId || null,
    }, { preserveScroll: true });
}

function addShowToEvent(eventId) {
    const state = addShowState.value[eventId];
    if (!state?.showId) return;
    router.post(`/admin/operations/designers/${props.designer.id}/shows`, {
        show_id: state.showId,
        collection_name: state.collection || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            addShowState.value[eventId] = { showId: '', collection: '' };
        },
    });
}

function submit() {
    form.phone = phoneNumber.value ? `${phoneCode.value}${phoneNumber.value.replace(/\D/g, '')}` : '';
    form.put(`/admin/operations/designers/${props.designer.id}`);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="`/admin/operations/designers/${designer.id}`" class="text-gray-400 hover:text-gray-600 text-sm flex items-center gap-1">
                    <ArrowLeftIcon class="w-4 h-4" /> Ver diseñador
                </Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Editar: {{ designer.first_name }} {{ designer.last_name }}</h2>
            </div>
        </template>

        <div class="max-w-3xl mx-auto">
            <!-- Tabs -->
            <div class="flex gap-1 bg-gray-100 rounded-xl p-1 mb-6">
                <button v-for="tab in [
                    { n: 1, label: 'Datos Personales' },
                    { n: 2, label: 'Evento y Show' },
                    { n: 3, label: 'Asistentes' },
                    { n: 4, label: 'Materiales' },
                    { n: 5, label: 'Displays' },
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telefono</label>
                            <div class="flex gap-2">
                                <select v-model="phoneCode"
                                    class="w-28 border border-gray-300 rounded-lg px-2 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                    <option v-for="c in countryCodes" :key="c.code" :value="c.code">{{ c.label }}</option>
                                </select>
                                <input v-model="phoneNumber" type="tel" placeholder="3055550404"
                                    :class="form.errors.phone ? 'border-red-400 ring-2 ring-red-100' : 'border-gray-300'"
                                    class="flex-1 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <p v-if="form.errors.phone" class="mt-1 text-red-500 text-xs">{{ form.errors.phone }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                        <select v-model="form.status"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option v-if="form.status === 'active'" value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                            <option value="pending">Pendiente</option>
                        </select>
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
                            <select v-model="form.country"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                <option value="">— Seleccionar pais —</option>
                                <option v-for="(name, code) in countries" :key="code" :value="name">{{ name }}</option>
                            </select>
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
                <div v-show="activeTab === 2" class="space-y-5">
                    <!-- Asignar a nuevo evento (primero) -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
                        <h4 class="font-semibold text-gray-800">Asignar a evento</h4>

                        <select v-model="selectedEventId"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                            <option value="">— Seleccionar evento —</option>
                            <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>

                        <div v-if="selectedEventId" class="space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Paquete</label>
                                    <select v-model="selectedPackageId"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                        <option value="">— Sin paquete —</option>
                                        <option v-for="p in packages" :key="p.id" :value="p.id">
                                            {{ p.name }} — ${{ Number(p.price).toLocaleString() }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Looks</label>
                                    <input v-model="assignLooks" type="number" min="0"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Precio ($)</label>
                                    <input v-model="assignPrice" type="number" step="0.01" min="0"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-x-6 gap-y-2 bg-gray-50 rounded-xl p-3 col-span-full">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="assignCasting" type="checkbox"
                                        class="rounded border-gray-300 text-black focus:ring-black/20 w-4 h-4" />
                                    <span class="text-sm text-gray-700">Model Casting</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="assignMedia" type="checkbox"
                                        class="rounded border-gray-300 text-black focus:ring-black/20 w-4 h-4" />
                                    <span class="text-sm text-gray-700">Media Package</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="assignBackground" type="checkbox"
                                        class="rounded border-gray-300 text-black focus:ring-black/20 w-4 h-4" />
                                    <span class="text-sm text-gray-700">Custom Background</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="assignTickets" type="checkbox"
                                        class="rounded border-gray-300 text-black focus:ring-black/20 w-4 h-4" />
                                    <span class="text-sm text-gray-700">Courtesy Tickets</span>
                                </label>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Notas</label>
                                <textarea v-model="assignNotes" rows="2"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                            </div>

                            <!-- Shows del evento seleccionado -->
                            <div v-if="eventDays.length" class="border-t border-gray-100 pt-3">
                                <p class="text-xs font-semibold text-gray-700 mb-2">Asignar a Shows</p>
                                <div v-for="day in eventDays" :key="day.id" class="mb-3 last:mb-0">
                                    <p class="text-xs text-gray-500 mb-1.5">{{ day.label }} — {{ day.date }}</p>
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

                            <!-- Fitting slot selector -->
                            <div v-if="eventFittingSlots.length" class="border-t border-gray-100 pt-3">
                                <label class="block text-xs font-semibold text-orange-600 mb-2">Horario de Fitting (opcional)</label>
                                <select v-model="assignFittingSlotId"
                                    class="w-full border border-orange-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400/30">
                                    <option value="">— Sin fitting —</option>
                                    <option v-for="slot in eventFittingSlots" :key="slot.id" :value="slot.id">
                                        {{ slot.day_label }} · {{ slot.time }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <button type="button" @click="assignEvent"
                            :disabled="!selectedEventId"
                            class="w-full py-2.5 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40 transition-colors">
                            Asignar al evento
                        </button>
                    </div>

                    <!-- Eventos asignados (un card por evento con sus shows) -->
                    <div v-if="designerEvents.length === 0" class="bg-white rounded-2xl border border-gray-200 p-5">
                        <p class="text-sm text-gray-400 italic text-center">No hay eventos asignados.</p>
                    </div>

                    <div v-for="evt in designerEvents" :key="evt.id"
                        class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
                        <!-- Header del evento -->
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="font-semibold text-gray-800">{{ evt.name }}</h4>
                                <div class="flex items-center gap-3 mt-1 text-xs text-gray-400">
                                    <span v-if="evt.package_price">${{ Number(evt.package_price).toLocaleString() }}</span>
                                    <span>{{ evt.looks }} looks</span>
                                    <span>{{ evt.models_count ?? 0 }} modelos</span>
                                    <span :class="evt.model_casting_enabled ? 'text-green-500' : ''">Casting: {{ evt.model_casting_enabled ? 'Si' : 'No' }}</span>
                                    <span :class="evt.media_package ? 'text-green-500' : ''">Media: {{ evt.media_package ? 'Si' : 'No' }}</span>
                                    <span :class="evt.custom_background ? 'text-green-500' : ''">BG: {{ evt.custom_background ? 'Si' : 'No' }}</span>
                                    <span :class="evt.courtesy_tickets ? 'text-green-500' : ''">Tickets: {{ evt.courtesy_tickets ? 'Si' : 'No' }}</span>
                                    <span v-if="evt.designer_status === 'cancelled'"
                                        class="px-1.5 py-0.5 rounded bg-red-50 text-red-600 font-medium">
                                        Cancelado
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <button v-if="evt.designer_status === 'confirmed'" type="button"
                                    @click="cancelFromEvent(evt.id, evt.name)"
                                    class="text-yellow-500 hover:text-yellow-700 text-xs">Cancelar</button>
                                <button type="button" @click="removeFromEvent(evt.id, evt.name)"
                                    class="text-red-400 hover:text-red-600 text-xs">Quitar</button>
                            </div>
                        </div>

                        <!-- Shows de este evento -->
                        <div class="border-t border-gray-100 pt-3">
                            <p class="text-xs font-semibold text-gray-600 mb-2">Shows asignados</p>

                            <div v-if="showsForEvent(evt.id).length === 0" class="text-xs text-gray-400 italic mb-2">Sin shows en este evento.</div>
                            <div v-for="s in showsForEvent(evt.id)" :key="s.id"
                                class="flex items-center gap-3 py-1.5 border-b border-gray-50 last:border-0 text-sm">
                                <span class="text-gray-400 text-xs w-20 flex-shrink-0">{{ s.event_day?.label }}</span>
                                <span class="font-medium text-gray-800">{{ s.name }}</span>
                                <span v-if="s.collection_name" class="text-gray-400 text-xs">({{ s.collection_name }})</span>
                                <span class="ml-auto text-xs px-1.5 py-0.5 rounded"
                                    :class="s.status === 'confirmed' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'">
                                    {{ showStatusLabel(s.status) }}
                                </span>
                                <button v-if="s.status === 'confirmed'" type="button"
                                    @click="cancelShow(s.id, s.name)"
                                    class="text-yellow-500 hover:text-yellow-700 text-xs">Cancelar</button>
                                <button type="button" @click="removeShow(s.id, s.name)"
                                    class="text-red-400 hover:text-red-600 text-xs">Quitar</button>
                            </div>

                            <!-- Agregar show a este evento -->
                            <div v-if="availableShowsForEvent(evt.id).length" class="mt-3 pt-3 border-t border-gray-100 space-y-2">
                                <p class="text-xs font-medium text-gray-600">Agregar show</p>
                                <div class="flex items-end gap-2">
                                    <div class="flex-1">
                                        <select :value="getAddShowState(evt.id).showId"
                                            @change="getAddShowState(evt.id).showId = $event.target.value"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                            <option value="">— Seleccionar show —</option>
                                            <option v-for="show in availableShowsForEvent(evt.id)" :key="show.id" :value="show.id">
                                                {{ show.dayLabel }} — {{ show.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="flex-1">
                                        <input :value="getAddShowState(evt.id).collection"
                                            @input="getAddShowState(evt.id).collection = $event.target.value"
                                            type="text" placeholder="Coleccion (opcional) Ej: Dark Elegance SS26"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                    </div>
                                    <button type="button" @click="addShowToEvent(evt.id)"
                                        :disabled="!getAddShowState(evt.id).showId"
                                        class="px-3 py-2 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 disabled:opacity-40 transition-colors flex-shrink-0">
                                        + Show
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Fitting del evento -->
                        <div v-if="fittingSlotsForEvent(evt.id).length" class="border-t border-gray-100 pt-3">
                            <p class="text-xs font-semibold text-orange-600 mb-2">Fitting</p>
                            <div v-if="fittingForEvent(evt.id)" class="flex items-center gap-2 mb-2">
                                <span class="text-xs bg-orange-50 text-orange-700 border border-orange-200 px-2 py-1 rounded-lg">
                                    {{ fittingForEvent(evt.id).day_label }} · {{ fittingForEvent(evt.id).time }}
                                </span>
                            </div>
                            <select @change="updateFitting(evt.id, $event.target.value)"
                                :value="fittingForEvent(evt.id)?.fitting_slot_id ?? ''"
                                class="w-full border border-orange-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400/30">
                                <option value="">— Sin fitting —</option>
                                <option v-for="slot in fittingSlotsForEvent(evt.id)" :key="slot.id" :value="slot.id">
                                    {{ slot.day_label }} · {{ slot.time }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Asistentes -->
                <div v-show="activeTab === 3" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
                    <div class="flex items-center justify-between">
                        <h4 class="font-semibold text-gray-800">Asistentes</h4>
                        <span v-if="assistantEventLimit !== null"
                            :class="assistantLimitReached ? 'bg-red-50 text-red-600 border border-red-200' : 'bg-gray-50 text-gray-500 border border-gray-200'"
                            class="text-xs font-medium px-2.5 py-1 rounded-full">
                            {{ assistantEventCount }} / {{ assistantEventLimit }} para este evento
                        </span>
                    </div>

                    <!-- Lista actual -->
                    <div v-if="assistants.length === 0" class="text-sm text-gray-400 italic">Sin asistentes registrados.</div>
                    <div v-for="a in assistants" :key="a.id"
                        class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ a.first_name }} {{ a.last_name }}</p>
                            <p class="text-xs text-gray-400">
                                {{ eventName(a.event_id) }}
                                <span v-if="a.document_id"> · ID: {{ a.document_id }}</span>
                                <span v-if="a.phone"> · {{ a.phone }}</span>
                                <span v-if="a.email"> · {{ a.email }}</span>
                            </p>
                        </div>
                        <button type="button" @click="removeAssistant(a.id)"
                            class="text-red-400 hover:text-red-600 text-xs">Eliminar</button>
                    </div>

                    <!-- Agregar nuevo -->
                    <div class="border-t border-gray-100 pt-4 space-y-3">
                        <h5 class="text-sm font-medium text-gray-700">Agregar asistente</h5>

                        <!-- Aviso límite alcanzado -->
                        <div v-if="assistantLimitReached"
                            class="flex items-center gap-2 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                            Límite alcanzado — este diseñador tiene <strong class="mx-1">{{ assistantEventLimit }}</strong> asistente(s) negociado(s) para este evento.
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="col-span-2">
                                <label class="block text-xs text-gray-500 mb-1">Evento *</label>
                                <select v-model="assistantEventId"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                    <option v-for="evt in designerEvents" :key="evt.id" :value="evt.id">{{ evt.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">First Name *</label>
                                <input v-model="newAssistant.first_name" type="text"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Last Name</label>
                                <input v-model="newAssistant.last_name" type="text"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Email *</label>
                                <input v-model="newAssistant.email" type="email"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Documento ID</label>
                                <input v-model="newAssistant.document_id" type="text"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Telefono</label>
                                <input v-model="newAssistant.phone" type="tel"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                        </div>
                        <button type="button" @click="addAssistant"
                            :disabled="!newAssistant.first_name || !assistantEventId || !newAssistant.email || assistantLimitReached"
                            class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-40 transition-colors">
                            + Agregar Asistente
                        </button>
                    </div>
                </div>

                <!-- Tab 4: Materiales -->
                <div v-show="activeTab === 4" class="space-y-5">
                    <div v-for="evt in designerEvents" :key="'mat-' + evt.id"
                        class="bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="font-semibold text-gray-800">{{ evt.name }}</h4>
                            <div class="flex items-center gap-2">
                                <div class="w-28 h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div :class="progressColor(materialsProgress(evt.id))"
                                        class="h-full rounded-full transition-all"
                                        :style="`width: ${materialsProgress(evt.id)}%`"></div>
                                </div>
                                <span class="text-xs font-medium text-gray-600">{{ materialsProgress(evt.id) }}%</span>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div v-for="m in materials.filter(mat => mat.event_id === evt.id)" :key="m.id"
                                class="flex items-center gap-3 bg-gray-50 rounded-lg px-3 py-2.5">
                                <span class="text-sm text-gray-700 font-medium flex-1">{{ m.name }}</span>
                                <input :value="m.drive_link" type="text" placeholder="Drive link..."
                                    @change="updateMaterial(m, 'drive_link', $event.target.value)"
                                    class="w-56 border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-black/10" />
                                <select :value="m.status"
                                    @change="updateMaterial(m, 'status', $event.target.value)"
                                    class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                    <option value="pending">Pendiente</option>
                                    <option value="submitted">Enviado</option>
                                    <option value="confirmed">Confirmado</option>
                                    <option value="rejected">Rechazado</option>
                                </select>
                            </div>
                        </div>

                        <p v-if="materials.filter(mat => mat.event_id === evt.id).length === 0"
                            class="text-sm text-gray-400 italic">Sin materiales para este evento.</p>
                    </div>

                    <div v-if="designerEvents.length === 0"
                        class="bg-white rounded-2xl border border-gray-200 p-6 text-sm text-gray-400 italic text-center">
                        Asigna el diseñador a un evento para ver sus materiales.
                    </div>
                </div>

                <!-- Tab 5: Displays -->
                <div v-show="activeTab === 5" class="space-y-5">
                    <div v-for="evt in designerEvents" :key="'disp-' + evt.id"
                        class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="font-semibold text-gray-800 mb-4">{{ evt.name }}</h4>

                        <div v-for="d in displays.filter(dd => dd.event_id === evt.id)" :key="d.id"
                            class="space-y-4">
                            <!-- Video -->
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-500 w-16">Video:</span>
                                <input :value="d.background_video_url" type="text" placeholder="URL del video..."
                                    @change="updateDisplay(d, { background_video_url: $event.target.value })"
                                    class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-black/10" />
                                <button type="button" @click="uploadDisplayFile(d.id, 'video')"
                                    class="px-3 py-1.5 text-xs border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                    Subir
                                </button>
                            </div>
                            <!-- Audio -->
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-500 w-16">Audio:</span>
                                <input :value="d.music_audio_url" type="text" placeholder="URL del audio..."
                                    @change="updateDisplay(d, { music_audio_url: $event.target.value })"
                                    class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-black/10" />
                                <button type="button" @click="uploadDisplayFile(d.id, 'audio')"
                                    class="px-3 py-1.5 text-xs border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                    Subir
                                </button>
                            </div>
                            <!-- Estado -->
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-500 w-16">Estado:</span>
                                <select :value="d.status"
                                    @change="updateDisplay(d, { status: $event.target.value })"
                                    class="border border-gray-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                                    <option value="pending">Pendiente</option>
                                    <option value="ready">Listo</option>
                                    <option value="confirmed">Confirmado</option>
                                </select>
                            </div>
                            <!-- Notas -->
                            <div class="flex items-start gap-3">
                                <span class="text-sm text-gray-500 w-16 pt-2">Notas:</span>
                                <textarea :value="d.notes" rows="2" placeholder="Notas del display..."
                                    @change="updateDisplay(d, { notes: $event.target.value })"
                                    class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                            </div>
                        </div>

                        <p v-if="displays.filter(dd => dd.event_id === evt.id).length === 0"
                            class="text-sm text-gray-400 italic">Sin display para este evento.</p>
                    </div>

                    <div v-if="designerEvents.length === 0"
                        class="bg-white rounded-2xl border border-gray-200 p-6 text-sm text-gray-400 italic text-center">
                        Asigna el diseñador a un evento para ver sus displays.
                    </div>
                </div>

                <!-- Botones (solo en tab 1 se muestran guardar/cancelar) -->
                <div v-if="activeTab === 1" class="flex justify-between">
                    <Link :href="`/admin/operations/designers/${designer.id}`"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        Cancelar
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-8 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-60 transition-colors">
                        <span v-if="form.processing">Guardando...</span>
                        <span v-else>Guardar Cambios</span>
                    </button>
                </div>
                <div v-else class="flex justify-end">
                    <Link :href="`/admin/operations/designers/${designer.id}`"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        Volver al perfil
                    </Link>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
