<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { PhotoIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    events: Array,
    nextOrder: Number,
});

const allRoles = [
    { value: 'model',         label: 'Modelo' },
    { value: 'designer',      label: 'Disenador' },
    { value: 'media',         label: 'Media' },
    { value: 'volunteer',     label: 'Voluntario' },
    { value: 'staff',         label: 'Staff' },
    { value: 'attendee',      label: 'Asistente' },
    { value: 'vip',           label: 'VIP' },
    { value: 'influencer',    label: 'Influencer' },
    { value: 'press',         label: 'Prensa' },
    { value: 'sponsor',       label: 'Sponsor' },
    { value: 'complementary', label: 'Complementario' },
];

const form = useForm({
    title:        '',
    image:        null,
    link_url:     '',
    target_roles: [],
    event_id:     '',
    order:        props.nextOrder ?? 1,
    status:       'active',
    starts_at:    '',
    ends_at:      '',
});

const allRolesSelected = computed(() => form.target_roles.length === 0);
const imagePreview     = ref(null);

const canSubmit = computed(() => {
    return form.title.trim() !== ''
        && form.image !== null
        && form.status !== ''
        && form.starts_at !== ''
        && form.ends_at !== '';
});

function toggleAllRoles() {
    form.target_roles = form.target_roles.length === allRoles.length ? [] : allRoles.map(r => r.value);
}

function onImageChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    form.image = file;
    imagePreview.value = URL.createObjectURL(file);
}

function submit() {
    form.post('/admin/operations/banners', {
        forceFormData: true,
    });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/admin/operations/banners" class="text-gray-400 hover:text-gray-600 text-sm">Banners</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Crear Banner</h2>
            </div>
        </template>

        <div class="max-w-2xl mx-auto">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Imagen -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h3 class="font-bold text-gray-900 mb-4">Imagen del Banner *</h3>

                    <div class="aspect-[16/9] rounded-xl overflow-hidden border-2 border-dashed border-gray-300 bg-gray-50 mb-3 relative">
                        <img v-if="imagePreview" :src="imagePreview" class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex flex-col items-center justify-center text-gray-300">
                            <PhotoIcon class="w-12 h-12 mb-2" />
                            <p class="text-sm">Proporcion recomendada: 16:9</p>
                        </div>
                    </div>

                    <input type="file" accept="image/*" @change="onImageChange"
                        class="text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-black file:text-white file:text-sm file:font-medium hover:file:bg-gray-800 file:cursor-pointer" />
                    <p v-if="form.errors.image" class="mt-1 text-red-500 text-xs">{{ form.errors.image }}</p>
                </div>

                <!-- Info -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                    <h3 class="font-bold text-gray-900 mb-2">Informacion</h3>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Titulo (nombre interno) *</label>
                        <input v-model="form.title" type="text" placeholder="ej. NYFW September 2026"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        <p v-if="form.errors.title" class="mt-1 text-red-500 text-xs">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">URL de destino (opcional)</label>
                        <input v-model="form.link_url" type="url" placeholder="https://runway7.com/..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        <p v-if="form.errors.link_url" class="mt-1 text-red-500 text-xs">{{ form.errors.link_url }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Evento *</label>
                            <select v-model="form.event_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="">Todos los eventos</option>
                                <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Orden</label>
                            <input v-model.number="form.order" type="number" min="0"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                            <select v-model="form.status"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10">
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                        </div>
                        <div></div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mostrar desde *</label>
                            <input v-model="form.starts_at" type="datetime-local"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mostrar hasta *</label>
                            <input v-model="form.ends_at" type="datetime-local"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
                        </div>
                    </div>
                </div>

                <!-- Roles target -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h3 class="font-bold text-gray-900 mb-3">Roles objetivo</h3>
                    <p class="text-xs text-gray-400 mb-4">Si no seleccionas ninguno, el banner se mostrara a todos los roles.</p>

                    <label class="flex items-center gap-2 cursor-pointer mb-3 pb-3 border-b border-gray-100">
                        <input type="checkbox" :checked="allRolesSelected" @change="toggleAllRoles"
                            class="rounded border-gray-300 text-[#D4AF37] focus:ring-[#D4AF37]/20" />
                        <span class="text-sm font-semibold text-gray-800">Todos los roles</span>
                    </label>

                    <div class="grid grid-cols-3 gap-2">
                        <label v-for="role in allRoles" :key="role.value"
                            class="flex items-center gap-2 cursor-pointer py-1">
                            <input type="checkbox" v-model="form.target_roles" :value="role.value"
                                class="rounded border-gray-300 text-black focus:ring-black/20" />
                            <span class="text-sm text-gray-700">{{ role.label }}</span>
                        </label>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex justify-between">
                    <Link href="/admin/operations/banners"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        Cancelar
                    </Link>
                    <button type="submit" :disabled="!canSubmit || form.processing"
                        class="px-8 py-2.5 bg-black text-white rounded-lg text-sm font-semibold hover:bg-gray-800 disabled:opacity-60 disabled:cursor-not-allowed transition-colors">
                        <span v-if="form.processing">Guardando...</span>
                        <span v-else>Crear Banner</span>
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
