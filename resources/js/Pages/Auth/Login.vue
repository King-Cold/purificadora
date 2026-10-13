<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
        default: true,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Iniciar Sesión - Puritronic" />

    <div class="min-h-screen w-full bg-white font-['Poppins',sans-serif] text-slate-800 flex flex-col justify-between selection:bg-[#0084ff] selection:text-white">
        
        <!-- Header superior / Barra de navegación -->
        <header class="w-full px-4 py-3 sm:px-8 sm:py-4 flex items-center justify-between">
            <Link
                :href="route('auth.portal')"
                class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center bg-slate-50 border border-slate-200/90 text-slate-600 hover:text-[#0084ff] hover:border-[#0084ff]/40 hover:bg-blue-50/50 shadow-xs transition-all"
                title="Volver"
                aria-label="Volver"
            >
                <i class="fa-solid fa-chevron-left text-xs sm:text-sm"></i>
            </Link>

            <Link href="/" class="flex items-center">
                <img
                    src="/images/logo.png"
                    alt="Puritronic"
                    class="h-7 sm:h-9 w-auto object-contain drop-shadow-xs"
                />
            </Link>

            <Link
                href="/"
                class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center bg-slate-50 border border-slate-200/90 text-slate-600 hover:text-[#0084ff] hover:border-[#0084ff]/40 hover:bg-blue-50/50 shadow-xs transition-all"
                title="Ir al inicio"
                aria-label="Ir al inicio"
            >
                <i class="fa-solid fa-house text-xs sm:text-sm"></i>
            </Link>
        </header>

        <!-- Contenido principal a Pantalla Completa: ilustración reducida en móvil para visibilidad total del formulario -->
        <main class="flex-1 w-full max-w-6xl mx-auto px-4 sm:px-8 lg:px-12 py-2 sm:py-4 flex flex-col lg:flex-row items-center justify-center gap-3 sm:gap-6 lg:gap-14">
            
            <!-- Columna Ilustración (Tamaño balanceado y nítido) -->
            <div class="w-full lg:w-1/2 flex justify-center items-center">
                <div class="w-60 sm:w-72 md:w-80 lg:w-full lg:max-w-lg">
                    <img
                        src="/images/login.svg"
                        alt="Iniciar Sesión"
                        class="w-full h-auto max-h-[240px] sm:max-h-[300px] lg:max-h-[480px] object-contain drop-shadow-sm transform hover:scale-[1.02] transition-transform duration-300 mx-auto"
                    />
                </div>
            </div>

            <!-- Columna Formulario Login -->
            <div class="w-full lg:w-1/2 flex justify-center">
                <div class="w-full max-w-sm sm:max-w-md space-y-3.5 sm:space-y-5">
                    
                    <!-- Encabezado del Formulario -->
                    <div class="space-y-1 text-center lg:text-left">
                        <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-[#0e1c2e] tracking-tight">
                            Iniciar Sesión
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500 font-normal">
                            Ingresa tu correo y contraseña para continuar.
                        </p>
                    </div>

                    <!-- Estado de sesión (si existe) -->
                    <div v-if="status" class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-700">
                        {{ status }}
                    </div>

                    <!-- Formulario de acceso -->
                    <form @submit.prevent="submit" class="space-y-3 sm:space-y-3.5">
                        
                        <!-- Campo: Correo Electrónico -->
                        <div class="space-y-1">
                            <label for="email" class="block text-xs font-bold text-slate-700">
                                Correo Electrónico
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-regular fa-envelope text-sm"></i>
                                </div>
                                <input
                                    id="email"
                                    type="email"
                                    v-model="form.email"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="ejemplo@correo.com"
                                    class="w-full pl-10 pr-4 py-2.5 sm:py-3 rounded-2xl border border-slate-200 focus:border-[#0084ff] focus:ring-4 focus:ring-blue-500/10 text-xs sm:text-sm text-slate-800 placeholder-slate-400 transition-all outline-none bg-slate-50/50 hover:bg-white"
                                />
                            </div>
                            <InputError class="mt-1" :message="form.errors.email" />
                        </div>

                        <!-- Campo: Contraseña -->
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <label for="password" class="block text-xs font-bold text-slate-700">
                                    Contraseña
                                </label>
                                <Link
                                    v-if="canResetPassword"
                                    :href="route('password.request')"
                                    class="text-[11px] font-semibold text-[#0084ff] hover:text-[#006bd6] transition-colors"
                                >
                                    ¿Olvidaste tu contraseña?
                                </Link>
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-solid fa-lock text-sm"></i>
                                </div>
                                <input
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    v-model="form.password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="••••••••"
                                    class="w-full pl-10 pr-10 py-2.5 sm:py-3 rounded-2xl border border-slate-200 focus:border-[#0084ff] focus:ring-4 focus:ring-blue-500/10 text-xs sm:text-sm text-slate-800 placeholder-slate-400 transition-all outline-none bg-slate-50/50 hover:bg-white"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"
                                >
                                    <i :class="showPassword ? 'fa-regular fa-eye-slash text-sm' : 'fa-regular fa-eye text-sm'"></i>
                                </button>
                            </div>
                            <InputError class="mt-1" :message="form.errors.password" />
                        </div>

                        <!-- Recordarme -->
                        <div class="flex items-center justify-between pt-0.5">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <Checkbox
                                    name="remember"
                                    v-model:checked="form.remember"
                                    class="rounded-lg text-[#0084ff] focus:ring-[#0084ff] border-slate-300"
                                />
                                <span class="text-xs text-slate-600 font-medium">Recordarme en este equipo</span>
                            </label>
                        </div>

                        <!-- Botón de Envío -->
                        <div class="pt-1">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full py-3 sm:py-3.5 px-6 rounded-2xl bg-gradient-to-r from-[#0084ff] to-[#009bf5] hover:from-[#0070dc] hover:to-[#008fe0] text-white font-bold text-xs sm:text-sm shadow-md shadow-blue-500/20 hover:shadow-lg hover:shadow-blue-500/30 hover:-translate-y-0.5 transition-all duration-200 disabled:opacity-50 disabled:pointer-events-none flex items-center justify-center gap-2"
                            >
                                <span v-if="form.processing">Iniciando sesión...</span>
                                <span v-else>INICIAR SESIÓN</span>
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </button>
                        </div>
                    </form>

                    <!-- Enlace a Registro -->
                    <div class="pt-2 sm:pt-2.5 border-t border-slate-100 text-center">
                        <p class="text-xs text-slate-500">
                            ¿Aún no tienes cuenta?
                            <Link
                                :href="route('register')"
                                class="font-bold text-[#0084ff] hover:text-[#006bd6] ml-1 transition-colors underline"
                            >
                                Regístrate aquí
                            </Link>
                        </p>
                    </div>

                </div>
            </div>

        </main>

        <!-- Footer inferior simple -->
        <footer class="w-full py-2.5 sm:py-3 text-center text-[11px] sm:text-xs text-slate-400">
            Puritronic &copy; {{ new Date().getFullYear() }}
        </footer>

    </div>
</template>
