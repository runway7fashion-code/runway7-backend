<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    designer: Object,
});

const profile    = props.designer.designer_profile;
const events     = props.designer.events     ?? [];
const shows      = props.designer.shows      ?? [];
const assistants = props.designer.assistants  ?? [];
const materials  = props.designer.materials   ?? [];
const displays   = props.designer.displays    ?? [];

function storageUrl(path) {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}

function removeFromEvent(eventId, eventName) {
    if (!confirm(`Quitar a ${props.designer.first_name} del evento "${eventName}"?`)) return;
    router.delete(`/admin/designers/${props.designer.id}/remove-event/${eventId}`, { preserveScroll: true });
}

// Materiales agrupados por evento
const materialsByEvent = computed(() => {
    const map = {};
    materials.forEach(m => {
        if (!map[m.event_id]) map[m.event_id] = [];
        map[m.event_id].push(m);
    });
    return map;
});

function materialsProgress(eventId) {
    const mats = materialsByEvent.value[eventId];
    if (!mats || mats.length === 0) return 0;
    const done = mats.filter(m => m.status === 'confirmed' || m.status === 'submitted').length;
    return Math.round((done / mats.length) * 100);
}

function progressColor(pct) {
    if (pct === 100) return 'bg-green-500';
    if (pct >= 50)   return 'bg-yellow-400';
    return 'bg-red-300';
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

function displayStatusClass(status) {
    return {
        pending:   'text-yellow-700 bg-yellow-50',
        ready:     'text-blue-700 bg-blue-50',
        confirmed: 'text-green-700 bg-green-50',
    }[status] ?? 'text-gray-600 bg-gray-50';
}

function displayStatusLabel(status) {
    return { pending: 'Pendiente', ready: 'Listo', confirmed: 'Confirmado' }[status] ?? status;
}

function showStatusClass(s) {
    return { assigned: 'text-green-700 bg-green-50', pending: 'text-yellow-700 bg-yellow-50',
        confirmed: 'text-green-700 bg-green-50', rejected: 'text-red-600 bg-red-50' }[s] ?? 'text-gray-600 bg-gray-50';
}

function showStatusLabel(s) {
    return { assigned: 'Asignado', pending: 'Pendiente', confirmed: 'Confirmado', rejected: 'Rechazado' }[s] ?? s;
}

// Social media links
const socialLinks = computed(() => {
    const sm = profile?.social_media;
    if (!sm) return [];
    const links = [];
    if (sm.instagram) links.push({ label: sm.instagram, url: `https://instagram.com/${sm.instagram.replace('@', '')}`, color: 'text-pink-600 hover:text-pink-700' });
    if (sm.facebook) links.push({ label: 'Facebook', url: sm.facebook.startsWith('http') ? sm.facebook : `https://facebook.com/${sm.facebook}`, color: 'text-blue-600 hover:text-blue-700' });
    if (sm.tiktok) links.push({ label: sm.tiktok, url: `https://tiktok.com/${sm.tiktok.replace('@', '')}`, color: 'text-gray-800 hover:text-black' });
    if (sm.website) links.push({ label: 'Website', url: sm.website.startsWith('http') ? sm.website : `https://${sm.website}`, color: 'text-indigo-600 hover:text-indigo-700' });
    return links;
});

function eventName(eventId) {
    return events.find(e => e.id === eventId)?.name ?? `Evento #${eventId}`;
}

// Asistentes agrupados por evento
const assistantsByEvent = computed(() => {
    const map = {};
    assistants.forEach(a => {
        if (!map[a.event_id]) map[a.event_id] = [];
        map[a.event_id].push(a);
    });
    return map;
});
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/designers" class="text-gray-400 hover:text-gray-600 text-sm">← Diseñadores</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">{{ designer.first_name }} {{ designer.last_name }}</h2>
            </div>
        </template>

        <div class="max-w-5xl mx-auto space-y-6">

            <!-- Hero card -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex gap-6">
                    <!-- Foto -->
                    <div class="flex-shrink-0">
                        <div class="w-24 h-24 rounded-full overflow-hidden bg-gray-100">
                            <img v-if="storageUrl(designer.profile_picture)"
                                :src="storageUrl(designer.profile_picture)"
                                class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center text-2xl font-bold text-gray-400">
                                {{ designer.first_name?.[0] }}{{ designer.last_name?.[0] }}
                            </div>
                        </div>
                    </div>
                    <!-- Info -->
                    <div class="flex-1">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">{{ designer.first_name }} {{ designer.last_name }}</h3>
                                <p class="text-gray-500 text-sm">
                                    {{ profile?.brand_name }}
                                    <span v-if="profile?.country"> · {{ profile.country }}</span>
                                </p>
                            </div>
                            <Link :href="`/admin/designers/${designer.id}/edit`"
                                class="px-4 py-1.5 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors">
                                Editar
                            </Link>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-3 text-sm">
                            <span class="text-gray-600">
                                <svg class="w-4 h-4 inline mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                                {{ designer.email }}
                            </span>
                            <span v-if="designer.phone" class="text-gray-600">
                                Tel: {{ designer.phone }}
                            </span>
                            <a v-for="link in socialLinks" :key="link.label"
                                :href="link.url" target="_blank" :class="link.color" class="text-sm">
                                {{ link.label }}
                            </a>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <span v-if="profile?.category"
                                class="text-xs bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full font-medium">
                                {{ profile.category.name }}
                            </span>
                            <span v-if="profile?.skype"
                                class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full">
                                Skype: {{ profile.skype }}
                            </span>
                            <span v-if="profile?.tracking_link"
                                class="text-xs bg-purple-50 text-purple-700 px-2 py-0.5 rounded-full">
                                Tracking
                            </span>
                            <span v-if="profile?.sales_rep"
                                class="text-xs bg-green-50 text-green-700 border border-green-200 px-2 py-0.5 rounded-full font-medium">
                                Rep: {{ profile.sales_rep.first_name }} {{ profile.sales_rep.last_name }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-6">
                <!-- Columna izquierda (2 cols) -->
                <div class="col-span-2 space-y-6">

                    <!-- Bio -->
                    <div v-if="profile?.bio" class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="font-bold text-gray-900 mb-3">Bio</h4>
                        <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">{{ profile.bio }}</p>
                    </div>

                    <!-- Shows asignados -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="font-bold text-gray-900 mb-4">Shows Asignados</h4>
                        <div v-if="shows.length === 0" class="text-sm text-gray-400 italic">Sin shows asignados.</div>
                        <div class="space-y-2">
                            <div v-for="s in shows" :key="s.id"
                                class="flex items-center gap-3 bg-gray-50 rounded-lg px-3 py-2.5 text-sm">
                                <span class="text-gray-500 text-xs">{{ s.event_day?.label }} · {{ s.event_day?.date }}</span>
                                <span class="font-medium text-gray-900">{{ s.name }}</span>
                                <span v-if="s.collection_name" class="text-gray-400 text-xs">{{ s.collection_name }}</span>
                                <span :class="showStatusClass(s.status)"
                                    class="ml-auto px-2 py-0.5 rounded text-xs font-medium">
                                    {{ showStatusLabel(s.status) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Materiales por evento -->
                    <div v-for="evt in events" :key="'mat-' + evt.id"
                        class="bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="font-bold text-gray-900">Materiales — {{ evt.name }}</h4>
                            <div class="flex items-center gap-2">
                                <div class="w-28 h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div :class="progressColor(materialsProgress(evt.id))"
                                        class="h-full rounded-full transition-all"
                                        :style="`width: ${materialsProgress(evt.id)}%`"></div>
                                </div>
                                <span class="text-sm font-medium text-gray-600">{{ materialsProgress(evt.id) }}%</span>
                            </div>
                        </div>

                        <div v-if="materialsByEvent[evt.id]?.length" class="space-y-1.5">
                            <div v-for="m in materialsByEvent[evt.id]" :key="m.id"
                                class="flex items-center gap-3 text-sm px-3 py-2 bg-gray-50 rounded-lg">
                                <span class="text-gray-700 font-medium flex-1">{{ m.name }}</span>
                                <a v-if="m.drive_link" :href="m.drive_link" target="_blank"
                                    class="text-xs text-blue-600 hover:text-blue-700 underline">Link</a>
                                <span :class="materialStatusClass(m.status)"
                                    class="px-2 py-0.5 rounded text-xs font-medium">
                                    {{ materialStatusLabel(m.status) }}
                                </span>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-400 italic">Sin materiales.</p>
                    </div>

                    <!-- Displays por evento -->
                    <div v-for="evt in events" :key="'disp-' + evt.id">
                        <div v-if="displays.filter(d => d.event_id === evt.id).length"
                            class="bg-white rounded-2xl border border-gray-200 p-6">
                            <h4 class="font-bold text-gray-900 mb-4">Display — {{ evt.name }}</h4>
                            <div v-for="d in displays.filter(dd => dd.event_id === evt.id)" :key="d.id"
                                class="space-y-3">
                                <div class="flex items-center gap-3 text-sm">
                                    <span class="text-gray-500">Video:</span>
                                    <span v-if="d.background_video_url" class="text-blue-600 text-xs truncate max-w-xs">{{ d.background_video_url }}</span>
                                    <span v-else class="text-gray-400 text-xs italic">Sin video</span>
                                </div>
                                <div class="flex items-center gap-3 text-sm">
                                    <span class="text-gray-500">Audio:</span>
                                    <span v-if="d.music_audio_url" class="text-blue-600 text-xs truncate max-w-xs">{{ d.music_audio_url }}</span>
                                    <span v-else class="text-gray-400 text-xs italic">Sin audio</span>
                                </div>
                                <div class="flex items-center gap-3 text-sm">
                                    <span class="text-gray-500">Estado:</span>
                                    <span :class="displayStatusClass(d.status)"
                                        class="px-2 py-0.5 rounded text-xs font-medium">
                                        {{ displayStatusLabel(d.status) }}
                                    </span>
                                </div>
                                <p v-if="d.notes" class="text-xs text-gray-500">{{ d.notes }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna derecha (1 col) -->
                <div class="space-y-6">
                    <!-- Eventos -->
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
                                    <div class="mt-1 space-y-0.5">
                                        <p v-if="evt.package_id" class="text-xs text-gray-500">
                                            Paquete: <span class="font-medium">${{ Number(evt.package_price).toLocaleString() }}</span>
                                        </p>
                                        <p class="text-xs text-gray-500">Looks: <span class="font-medium">{{ evt.looks }}</span></p>
                                        <p class="text-xs text-gray-500">
                                            Casting: <span :class="evt.model_casting_enabled ? 'text-green-600' : 'text-red-500'" class="font-medium">
                                                {{ evt.model_casting_enabled ? 'Habilitado' : 'Deshabilitado' }}
                                            </span>
                                        </p>
                                        <p v-if="evt.notes" class="text-xs text-gray-400 mt-1">{{ evt.notes }}</p>
                                    </div>
                                </div>
                                <button @click="removeFromEvent(evt.id, evt.name)"
                                    class="text-red-400 hover:text-red-600 text-xs ml-2 flex-shrink-0">✕</button>
                            </div>
                        </div>
                    </div>

                    <!-- Asistentes -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h4 class="font-bold text-gray-900 mb-4">Asistentes</h4>
                        <div v-if="assistants.length === 0" class="text-sm text-gray-400 italic">Sin asistentes.</div>

                        <div v-for="(evtAssistants, eventId) in assistantsByEvent" :key="eventId" class="mb-4 last:mb-0">
                            <p class="text-xs font-medium text-gray-500 mb-2">{{ eventName(Number(eventId)) }}</p>
                            <div v-for="a in evtAssistants" :key="a.id"
                                class="flex items-center justify-between py-1.5 border-b border-gray-50 last:border-0">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ a.full_name }}</p>
                                    <p v-if="a.phone || a.email" class="text-xs text-gray-400">
                                        {{ a.phone }}{{ a.phone && a.email ? ' · ' : '' }}{{ a.email }}
                                    </p>
                                </div>
                                <span class="text-xs px-1.5 py-0.5 rounded"
                                    :class="a.status === 'checked_in' ? 'bg-green-50 text-green-700' : 'bg-gray-50 text-gray-500'">
                                    {{ a.status === 'checked_in' ? 'Check-in' : 'Registrado' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Redes Sociales -->
                    <div v-if="socialLinks.length || profile?.skype || profile?.tracking_link"
                        class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h4 class="font-bold text-gray-900 mb-3">Redes y Enlaces</h4>
                        <div class="space-y-2 text-sm">
                            <a v-for="link in socialLinks" :key="link.label"
                                :href="link.url" target="_blank"
                                :class="link.color" class="block hover:underline">
                                {{ link.label }}
                            </a>
                            <p v-if="profile?.skype" class="text-gray-600">Skype: {{ profile.skype }}</p>
                            <a v-if="profile?.tracking_link" :href="profile.tracking_link" target="_blank"
                                class="block text-purple-600 hover:text-purple-700 hover:underline">
                                Tracking Link
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
