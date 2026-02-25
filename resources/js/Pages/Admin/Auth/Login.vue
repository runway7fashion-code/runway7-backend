<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/admin/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <div
        class="min-h-screen flex items-center justify-center p-4 relative"
        style="background-image: url('/images/fondo-login.jpg'); background-size: cover; background-position: center;"
    >
        <!-- Overlay oscuro -->
        <div class="absolute inset-0 bg-black/40"></div>

        <!-- Contenido -->
        <div class="w-full max-w-md relative z-10">
            <!-- Logo -->
            <div class="text-center mb-10">
                <img src="/images/logo.webp" alt="Runway7" class="h-30 mx-auto" />
            </div>

            <!-- Card -->
            <div class="bg-black/50 backdrop-blur-sm rounded-2xl p-8 border border-white/20 shadow-2xl">
                <h2 class="text-white text-xl font-semibold mb-6 text-center">Iniciar Sesión</h2>

                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="block text-white/60 text-sm mb-2">Correo electrónico</label>
                        <input
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            class="w-full bg-white/10 border rounded-lg px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:ring-2 transition-all"
                            :class="form.errors.email ? 'border-red-500 focus:ring-red-500/30' : 'border-white/20 focus:ring-white/20 focus:border-white/40'"
                            placeholder="admin@runway7.com"
                        />
                        <p v-if="form.errors.email" class="mt-1.5 text-red-400 text-xs">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="block text-white/60 text-sm mb-2">Contraseña</label>
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            class="w-full bg-white/10 border rounded-lg px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:ring-2 transition-all"
                            :class="form.errors.password ? 'border-red-500 focus:ring-red-500/30' : 'border-white/20 focus:ring-white/20 focus:border-white/40'"
                            placeholder="••••••••"
                        />
                        <p v-if="form.errors.password" class="mt-1.5 text-red-400 text-xs">{{ form.errors.password }}</p>
                    </div>

                    <div class="flex items-center">
                        <input v-model="form.remember" id="remember" type="checkbox" class="w-4 h-4 rounded border-white/30 bg-white/10" />
                        <label for="remember" class="ml-2 text-white/50 text-sm">Recordarme</label>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 px-4 rounded-lg font-semibold text-black bg-white hover:bg-white/90 transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <span v-if="form.processing">Iniciando sesión...</span>
                        <span v-else>Iniciar Sesión</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
