<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    roleCategories: Object,
});

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    role: 'admin',
    sales_type: '',
    status: 'pending',
    profile: {},
});

const allRoles = computed(() => {
    const list = [];
    const labels = {
        internal: 'Equipo Interno',
        participant: 'Participantes',
        attendee: 'Asistentes / Público',
    };
    for (const [cat, roles] of Object.entries(props.roleCategories)) {
        // Modelos y diseñadores se crean desde sus módulos dedicados
        const filtered = roles.filter(r => !['model', 'designer'].includes(r));
        if (!filtered.length) continue;
        list.push({ type: 'group', label: labels[cat] });
        filtered.forEach(r => list.push({ type: 'option', value: r, label: formatRole(r) }));
    }
    return list;
});

function formatRole(r) {
    return r.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

const isSales = computed(() => form.role === 'sales');
const showPressFields = computed(() => form.role === 'press');
const showSponsorFields = computed(() => form.role === 'sponsor');
const showProfileSection = computed(() => ['press', 'sponsor'].includes(form.role));

function submit() {
    form.post('/admin/users');
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center space-x-2 text-sm">
                <Link href="/admin/users" class="text-gray-400 hover:text-gray-600">Usuarios</Link>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-medium">Nuevo Usuario</span>
            </div>
        </template>

        <div class="max-w-2xl">
            <h3 class="text-2xl font-bold text-gray-900 mb-6">Nuevo Usuario</h3>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Sección 1: Info básica -->
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

                    <div class="grid grid-cols-2 gap-4 mb-4">
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
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Contraseña *</label>
                            <input v-model="form.password" type="password" class="input" :class="form.errors.password && 'border-red-300'" />
                            <p v-if="form.errors.password" class="err">{{ form.errors.password }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirmar *</label>
                            <input v-model="form.password_confirmation" type="password" class="input" />
                        </div>
                    </div>
                </div>

                <!-- Sección 2: Perfil dinámico -->
                <div v-if="showProfileSection" class="bg-white rounded-xl border border-gray-200 p-6">
                    <h4 class="font-semibold text-gray-900 mb-5">
                        Perfil de
                        <span class="capitalize">{{ form.role === 'tickets_manager' ? 'Tickets Manager' : form.role }}</span>
                    </h4>

                    <!-- Press fields -->
                    <template v-if="showPressFields">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Medio / Outlet *</label>
                                <input v-model="form.profile.media_outlet" type="text" class="input" placeholder="Vogue, WWD..." />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Cargo</label>
                                <input v-model="form.profile.position" type="text" class="input" placeholder="Editor, Fotógrafo..." />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Sitio web</label>
                                <input v-model="form.profile.website" type="url" class="input" placeholder="https://" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Instagram</label>
                                <input v-model="form.profile.instagram" type="text" class="input" placeholder="@usuario" />
                            </div>
                        </div>
                    </template>

                    <!-- Sponsor fields -->
                    <template v-if="showSponsorFields">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Empresa *</label>
                                <input v-model="form.profile.company_name" type="text" class="input" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nivel de sponsorship</label>
                                <select v-model="form.profile.sponsorship_level" class="input bg-white">
                                    <option value="">Seleccionar...</option>
                                    <option value="gold">Gold</option>
                                    <option value="silver">Silver</option>
                                    <option value="bronze">Bronze</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Sitio web</label>
                            <input v-model="form.profile.website" type="url" class="input" placeholder="https://" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Notas</label>
                            <textarea v-model="form.profile.notes" rows="3" class="input resize-none"></textarea>
                        </div>
                    </template>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3">
                    <Link href="/admin/users" class="px-4 py-2.5 text-sm text-gray-600 hover:text-gray-800 font-medium">Cancelar</Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-6 py-2.5 text-sm font-semibold text-white bg-black rounded-lg hover:bg-gray-800 transition-colors disabled:opacity-60">
                        {{ form.processing ? 'Creando...' : 'Crear Usuario' }}
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
