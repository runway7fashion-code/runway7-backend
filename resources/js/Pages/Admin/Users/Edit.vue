<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { InformationCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    user: Object,
    roleCategories: Object,
});

const showPasswordFields = ref(false);

const profileData = computed(() => {
    return props.user.model_profile
        || props.user.designer_profile
        || props.user.press_profile
        || props.user.sponsor_profile
        || {};
});

const form = useForm({
    first_name: props.user.first_name,
    last_name: props.user.last_name,
    email: props.user.email,
    phone: props.user.phone || '',
    role: props.user.role,
    sales_type: props.user.sales_type || '',
    sponsorship_type: props.user.sponsorship_type || '',
    status: props.user.status,
    password: '',
    password_confirmation: '',
    profile: { ...profileData.value },
    _method: 'PUT',
});

const allRoles = computed(() => {
    const list = [];
    const labels = { internal: 'Equipo Interno', participant: 'Participantes', attendee: 'Asistentes / Público' };
    for (const [cat, roles] of Object.entries(props.roleCategories)) {
        list.push({ type: 'group', label: labels[cat] });
        roles.forEach(r => list.push({ type: 'option', value: r, label: formatRole(r) }));
    }
    return list;
});

function formatRole(r) {
    return r.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

const isModel = computed(() => form.role === 'model');
const isDesigner = computed(() => form.role === 'designer');
const isSales = computed(() => form.role === 'sales');
const isSponsorship = computed(() => form.role === 'sponsorship');
const showPressFields = computed(() => form.role === 'press');
const showSponsorFields = computed(() => form.role === 'sponsor');
const showProfileSection = computed(() => ['press', 'sponsor'].includes(form.role));

function submit() {
    form.post(`/admin/users/${props.user.id}`);
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center space-x-2 text-sm">
                <Link href="/admin/users" class="text-gray-400 hover:text-gray-600">Usuarios</Link>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-medium">{{ user.first_name }} {{ user.last_name }}</span>
            </div>
        </template>

        <div class="max-w-2xl">
            <h3 class="text-2xl font-bold text-gray-900 mb-6">Editar Usuario</h3>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Info básica -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h4 class="font-semibold text-gray-900 mb-5">Información básica</h4>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre *</label>
                            <input v-model="form.first_name" type="text" class="input" :class="form.errors.first_name && 'border-red-300'" />
                            <p v-if="form.errors.first_name" class="err">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Apellido *</label>
                            <input v-model="form.last_name" type="text" class="input" :class="form.errors.last_name && 'border-red-300'" />
                            <p v-if="form.errors.last_name" class="err">{{ form.errors.last_name }}</p>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email *</label>
                        <input v-model="form.email" type="email" class="input" :class="form.errors.email && 'border-red-300'" />
                        <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Teléfono</label>
                        <input v-model="form.phone" type="tel" class="input" :class="form.errors.phone && 'border-red-300'" />
                        <p v-if="form.errors.phone" class="err">{{ form.errors.phone }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Rol *</label>
                            <select v-model="form.role" class="input bg-white">
                                <template v-for="item in allRoles" :key="item.label">
                                    <optgroup v-if="item.type === 'group'" :label="item.label"></optgroup>
                                    <option v-else :value="item.value">{{ item.label }}</option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Estado *</label>
                            <select v-model="form.status" class="input bg-white">
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                                <option value="pending">Pendiente</option>
                            </select>
                        </div>
                        <div v-if="isSales">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de Vendedor *</label>
                            <select v-model="form.sales_type" class="input bg-white">
                                <option value="">Seleccionar...</option>
                                <option value="lider">Líder</option>
                                <option value="asesor">Asesor</option>
                            </select>
                        </div>
                        <div v-if="isSponsorship">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de Sponsorship *</label>
                            <select v-model="form.sponsorship_type" class="input bg-white">
                                <option value="">Seleccionar...</option>
                                <option value="lider">Líder</option>
                                <option value="asesor">Asesor</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Banner: modelo tiene módulo dedicado -->
                <div v-if="isModel" class="bg-blue-50 border border-blue-200 rounded-xl p-5 flex items-start gap-3">
                    <InformationCircleIcon class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" />
                    <div>
                        <p class="text-sm font-semibold text-blue-800">Este usuario es una modelo</p>
                        <p class="text-sm text-blue-700 mt-1">El perfil completo (medidas, comp card, casting, eventos) se gestiona desde el módulo de Modelos.</p>
                        <Link :href="`/admin/models/${user.id}/edit`"
                            class="inline-block mt-2 text-sm font-medium text-blue-700 underline hover:text-blue-900">
                            Ir al perfil de modelo →
                        </Link>
                    </div>
                </div>

                <!-- Banner: diseñador tiene módulo dedicado -->
                <div v-if="isDesigner" class="bg-amber-50 border border-amber-200 rounded-xl p-5 flex items-start gap-3">
                    <InformationCircleIcon class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" />
                    <div>
                        <p class="text-sm font-semibold text-amber-800">Este usuario es un diseñador</p>
                        <p class="text-sm text-amber-700 mt-1">El perfil completo (marca, paquete, materiales, displays, asistentes) se gestiona desde el modulo de Diseñadores.</p>
                        <Link :href="`/admin/operations/designers/${user.id}/edit`"
                            class="inline-block mt-2 text-sm font-medium text-amber-700 underline hover:text-amber-900">
                            Ir al perfil de diseñador →
                        </Link>
                    </div>
                </div>

                <!-- Perfil dinámico (prensa, sponsors) -->
                <div v-if="showProfileSection" class="bg-white rounded-xl border border-gray-200 p-6">
                    <h4 class="font-semibold text-gray-900 mb-5">Perfil <span class="capitalize">{{ form.role }}</span></h4>

                    <template v-if="showPressFields">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Medio *</label><input v-model="form.profile.media_outlet" type="text" class="input" /></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Cargo</label><input v-model="form.profile.position" type="text" class="input" /></div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Sitio web</label><input v-model="form.profile.website" type="url" class="input" /></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Instagram</label><input v-model="form.profile.instagram" type="text" class="input" /></div>
                        </div>
                    </template>

                    <template v-if="showSponsorFields">
                        <div class="mb-4"><label class="block text-sm font-medium text-gray-700 mb-1.5">Empresa *</label><input v-model="form.profile.company_name" type="text" class="input" /></div>
                        <div class="mb-4"><label class="block text-sm font-medium text-gray-700 mb-1.5">Sitio web</label><input v-model="form.profile.website" type="url" class="input" /></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Notas</label><textarea v-model="form.profile.notes" rows="3" class="input resize-none"></textarea></div>
                    </template>
                </div>

                <!-- Cambio de contraseña -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <h4 class="font-semibold text-gray-900">Contraseña</h4>
                        <button type="button" @click="showPasswordFields = !showPasswordFields"
                            class="text-sm text-gray-500 hover:text-gray-700 underline">
                            {{ showPasswordFields ? 'Cancelar' : 'Cambiar contraseña' }}
                        </button>
                    </div>
                    <div v-if="showPasswordFields" class="grid grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nueva contraseña</label>
                            <input v-model="form.password" type="password" class="input" :class="form.errors.password && 'border-red-300'" />
                            <p v-if="form.errors.password" class="err">{{ form.errors.password }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirmar</label>
                            <input v-model="form.password_confirmation" type="password" class="input" />
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400 mt-2">La contraseña actual se mantiene si no cambias este campo.</p>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <Link href="/admin/users" class="px-4 py-2.5 text-sm text-gray-600 hover:text-gray-800 font-medium">Cancelar</Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-6 py-2.5 text-sm font-semibold text-white bg-black rounded-lg hover:bg-gray-800 transition-colors disabled:opacity-60">
                        {{ form.processing ? 'Guardando...' : 'Guardar Cambios' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<style scoped>
@reference "tailwindcss";
.input { @apply w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400; }
.err { @apply mt-1 text-red-500 text-xs; }
</style>
