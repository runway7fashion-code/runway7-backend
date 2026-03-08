<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    events: Array,
    packages: Array,
    countries: Array,
});

const phoneCodes = [
    { code: '+1', country: 'US/CA', flag: '🇺🇸' },
    { code: '+52', country: 'MX', flag: '🇲🇽' },
    { code: '+44', country: 'UK', flag: '🇬🇧' },
    { code: '+33', country: 'FR', flag: '🇫🇷' },
    { code: '+39', country: 'IT', flag: '🇮🇹' },
    { code: '+34', country: 'ES', flag: '🇪🇸' },
    { code: '+49', country: 'DE', flag: '🇩🇪' },
    { code: '+55', country: 'BR', flag: '🇧🇷' },
    { code: '+57', country: 'CO', flag: '🇨🇴' },
    { code: '+51', country: 'PE', flag: '🇵🇪' },
    { code: '+54', country: 'AR', flag: '🇦🇷' },
    { code: '+56', country: 'CL', flag: '🇨🇱' },
    { code: '+58', country: 'VE', flag: '🇻🇪' },
    { code: '+593', country: 'EC', flag: '🇪🇨' },
    { code: '+91', country: 'IN', flag: '🇮🇳' },
    { code: '+86', country: 'CN', flag: '🇨🇳' },
    { code: '+81', country: 'JP', flag: '🇯🇵' },
    { code: '+82', country: 'KR', flag: '🇰🇷' },
    { code: '+234', country: 'NG', flag: '🇳🇬' },
    { code: '+27', country: 'ZA', flag: '🇿🇦' },
    { code: '+971', country: 'AE', flag: '🇦🇪' },
];

const phoneCode = ref('+1');
const phoneNumber = ref('');

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    brand_name: '',
    country: '',
    event_id: '',
    package_id: '',
    agreed_price: '',
    downpayment: '',
    notes: '',
});

function submit() {
    form.phone = phoneNumber.value ? `${phoneCode.value}${phoneNumber.value}` : '';
    form.post('/admin/sales/designers', {
        preserveScroll: true,
    });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link href="/admin/sales/designers" class="text-gray-400 hover:text-gray-600 text-sm">&larr; Registros</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-lg font-semibold text-gray-900">Registrar Diseñador</h2>
            </div>
        </template>

        <div class="max-w-3xl">
            <form @submit.prevent="submit" class="space-y-8">
                <!-- Info del Diseñador -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4">Información del Diseñador</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                            <input v-model="form.first_name" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400" />
                            <p v-if="form.errors.first_name" class="text-red-500 text-xs mt-1">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Apellido *</label>
                            <input v-model="form.last_name" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400" />
                            <p v-if="form.errors.last_name" class="text-red-500 text-xs mt-1">{{ form.errors.last_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input v-model="form.email" type="email" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400" />
                            <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                            <div class="flex gap-2">
                                <select v-model="phoneCode" class="w-28 border border-gray-300 rounded-lg px-2 py-2 text-sm focus:ring-2 focus:ring-yellow-400">
                                    <option v-for="pc in phoneCodes" :key="pc.code" :value="pc.code">{{ pc.flag }} {{ pc.code }}</option>
                                </select>
                                <input v-model="phoneNumber" type="text" placeholder="Número..." class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400" />
                            </div>
                            <p v-if="form.errors.phone" class="text-red-500 text-xs mt-1">{{ form.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Marca / Brand *</label>
                            <input v-model="form.brand_name" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400" />
                            <p v-if="form.errors.brand_name" class="text-red-500 text-xs mt-1">{{ form.errors.brand_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">País *</label>
                            <select v-model="form.country" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-400">
                                <option value="">Seleccionar...</option>
                                <option v-for="c in countries" :key="c" :value="c">{{ c }}</option>
                            </select>
                            <p v-if="form.errors.country" class="text-red-500 text-xs mt-1">{{ form.errors.country }}</p>
                        </div>
                    </div>
                </div>

                <!-- Evento y Paquete -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4">Evento y Paquete</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Evento *</label>
                            <select v-model="form.event_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-400">
                                <option value="">Seleccionar evento...</option>
                                <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                            </select>
                            <p v-if="form.errors.event_id" class="text-red-500 text-xs mt-1">{{ form.errors.event_id }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Paquete *</label>
                            <select v-model="form.package_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-400">
                                <option value="">Seleccionar paquete...</option>
                                <option v-for="p in packages" :key="p.id" :value="p.id">{{ p.name }} — ${{ Number(p.price).toLocaleString() }}</option>
                            </select>
                            <p v-if="form.errors.package_id" class="text-red-500 text-xs mt-1">{{ form.errors.package_id }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Precio Acordado ($) *</label>
                            <input v-model="form.agreed_price" type="number" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400" />
                            <p v-if="form.errors.agreed_price" class="text-red-500 text-xs mt-1">{{ form.errors.agreed_price }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Inicial / Downpayment ($) *</label>
                            <input v-model="form.downpayment" type="number" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400" />
                            <p v-if="form.errors.downpayment" class="text-red-500 text-xs mt-1">{{ form.errors.downpayment }}</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                        <textarea v-model="form.notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400"></textarea>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-black text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors disabled:opacity-50">
                        {{ form.processing ? 'Registrando...' : 'Registrar Diseñador' }}
                    </button>
                    <Link href="/admin/sales/designers" class="px-6 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        Cancelar
                    </Link>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
