<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { formatDayLabel } from '@/utils/dates.js';

const props = defineProps({
    event: Object,
});

const form = useForm({
    name: props.event.name,
    city: props.event.city,
    venue: props.event.venue ?? '',
    timezone: props.event.timezone,
    start_date: props.event.start_date?.split('T')[0] ?? '',
    end_date: props.event.end_date?.split('T')[0] ?? '',
    description: props.event.description ?? '',
    status: props.event.status,
    model_number_start: props.event.model_number_start ?? 1,
    days: [...props.event.event_days].sort((a, b) => (a.date ?? '').localeCompare(b.date ?? '')).map(d => ({
        id: d.id,
        date: d.date?.split('T')[0] ?? '',
        label: d.label,
        type: d.type,
        start_time: d.start_time ?? '',
        end_time: d.end_time ?? '',
        description: d.description ?? '',
        shows: d.shows ?? [],
        has_assigned_shows: (d.shows ?? []).some(s => s.designers_count > 0),
    })),
});

const statusOptions = [
    { value: 'draft', label: 'Borrador' },
    { value: 'published', label: 'Publicado' },
    { value: 'active', label: 'Activo' },
    { value: 'completed', label: 'Completado' },
    { value: 'cancelled', label: 'Cancelado' },
];

function addDay() {
    form.days.push({
        date: '',
        label: 'Nuevo Día',
        type: 'show_day',
        start_time: '',
        end_time: '',
        description: '',
        shows: [],
        has_assigned_shows: false,
    });
}

function removeDay(index) {
    const day = form.days[index];
    if (day.has_assigned_shows) return;
    if (day.id) {
        router.delete(`/admin/events/${props.event.id}/days/${day.id}`, { preserveScroll: true });
    }
    form.days.splice(index, 1);
}

function deleteShow(day, show) {
    if (!confirm('¿Eliminar este show?')) return;
    router.delete(`/admin/shows/${show.id}`, { preserveScroll: true });
    day.shows = day.shows.filter(s => s.id !== show.id);
}

// Track new show time inputs per day (keyed by day.id)
const newShowTimes = reactive({});

function addShow(day) {
    const time = newShowTimes[day.id];
    if (!time) return;
    router.post(
        `/admin/events/${props.event.id}/days/${day.id}/shows`,
        { scheduled_time: time },
        {
            preserveScroll: true,
            onSuccess: () => { newShowTimes[day.id] = ''; },
        }
    );
}

function submit() {
    form.put(`/admin/events/${props.event.id}`);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="`/admin/events/${event.id}`" class="text-gray-400 hover:text-gray-600 text-sm">← Volver</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Editar Evento</h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto">
            <form @submit.prevent="submit" class="space-y-6">

                <!-- General info -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h3 class="text-lg font-bold mb-5">Información General</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                            <input v-model="form.name" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            <p v-if="form.errors.name" class="mt-1 text-red-500 text-xs">{{ form.errors.name }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ciudad</label>
                                <input v-model="form.city" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Venue</label>
                                <input v-model="form.venue" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Inicio</label>
                                <input v-model="form.start_date" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Fin</label>
                                <input v-model="form.end_date" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                                <select v-model="form.status" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                    <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                                <textarea v-model="form.description" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Numeración de modelos desde</label>
                                <input v-model.number="form.model_number_start" type="number" min="1"
                                    placeholder="ej. 4058"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                <p class="mt-1 text-xs text-gray-400">Primera modelo asignada recibirá este número.</p>
                                <p v-if="form.errors.model_number_start" class="mt-1 text-red-500 text-xs">{{ form.errors.model_number_start }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Days & Shows -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h3 class="text-lg font-bold mb-5">Días del Evento</h3>

                    <div class="space-y-3">
                        <div
                            v-for="(day, i) in form.days"
                            :key="day.id ?? `new-${i}`"
                            class="border border-gray-200 rounded-xl p-4"
                            :class="day.type === 'casting' ? 'border-yellow-300' : day.type === 'show_day' ? 'border-green-200' : ''"
                        >
                            <!-- Day fields -->
                            <div class="flex items-end gap-3 flex-wrap">
                                <div class="flex-shrink-0">
                                    <p class="text-xs text-gray-500 mb-0.5">Fecha</p>
                                    <p v-if="day.id" class="text-sm font-medium text-gray-700 py-2">{{ formatDayLabel(day.date) }}</p>
                                    <input v-else v-model="day.date" type="date"
                                        class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                </div>
                                <div class="flex-1 min-w-28">
                                    <label class="text-xs text-gray-500 mb-0.5 block">Label</label>
                                    <input v-model="day.label" type="text"
                                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                                </div>
                                <div class="w-32">
                                    <label class="text-xs text-gray-500 mb-0.5 block">Tipo</label>
                                    <select v-model="day.type"
                                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                        <option value="setup">Setup</option>
                                        <option value="casting">Casting</option>
                                        <option value="show_day">Show Day</option>
                                        <option value="ceremony">Ceremonia</option>
                                        <option value="other">Otro</option>
                                    </select>
                                </div>
                                <div class="flex gap-2">
                                    <div>
                                        <label class="text-xs text-gray-500 mb-0.5 block">Inicio</label>
                                        <input v-model="day.start_time" type="time" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-24 focus:outline-none" />
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 mb-0.5 block">Fin</label>
                                        <input v-model="day.end_time" type="time" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-24 focus:outline-none" />
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <button
                                        type="button"
                                        @click="removeDay(i)"
                                        :disabled="day.has_assigned_shows"
                                        :title="day.has_assigned_shows ? 'No se puede eliminar: tiene shows con diseñadores asignados' : 'Eliminar día'"
                                        class="text-red-400 hover:text-red-600 text-xl disabled:opacity-30 disabled:cursor-not-allowed"
                                    >×</button>
                                </div>
                            </div>

                            <!-- Shows for this day (only for existing saved days) -->
                            <div v-if="day.id && day.type === 'show_day'" class="mt-3 pt-3 border-t border-gray-100">
                                <p class="text-xs text-gray-500 mb-2 font-medium">Shows ({{ day.shows?.length ?? 0 }})</p>
                                <div class="flex flex-wrap gap-2 mb-2">
                                    <div
                                        v-for="show in day.shows"
                                        :key="show.id"
                                        class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs"
                                    >
                                        <span class="font-medium">{{ show.formatted_time ?? show.scheduled_time }}</span>
                                        <span v-if="show.designers_count > 0" class="text-green-600">✓</span>
                                        <button
                                            type="button"
                                            @click="deleteShow(day, show)"
                                            :disabled="show.designers_count > 0"
                                            :title="show.designers_count > 0 ? 'Tiene diseñadores asignados' : 'Eliminar show'"
                                            class="text-red-400 hover:text-red-600 disabled:opacity-30 disabled:cursor-not-allowed"
                                        >×</button>
                                    </div>
                                </div>
                                <!-- Add show -->
                                <div class="flex items-center gap-2 mt-1">
                                    <input
                                        v-model="newShowTimes[day.id]"
                                        type="time"
                                        class="border border-gray-300 rounded-lg px-2 py-1 text-sm w-28 focus:outline-none focus:ring-2 focus:ring-black/10"
                                    />
                                    <button
                                        type="button"
                                        @click="addShow(day)"
                                        :disabled="!newShowTimes[day.id]"
                                        class="px-3 py-1 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 disabled:opacity-40 transition-colors"
                                    >+ Show</button>
                                </div>
                            </div>
                        </div>

                        <button type="button" @click="addDay"
                            class="w-full py-3 border-2 border-dashed border-gray-300 rounded-xl text-gray-500 text-sm hover:border-gray-400 hover:text-gray-700 transition-colors">
                            + Agregar día
                        </button>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-between">
                    <Link :href="`/admin/events/${event.id}`" class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        Cancelar
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-8 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-60 transition-colors"
                    >
                        <span v-if="form.processing">Guardando...</span>
                        <span v-else>Guardar Cambios</span>
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
