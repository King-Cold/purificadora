<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Eye, EyeOff, Home, Lock, Mail } from 'lucide-vue-next';
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
    <Head title="Iniciar sesión" />

    <div class="min-h-screen w-full bg-[#F5F4F8] font-sans antialiased text-[#211B35] flex flex-col justify-between selection:bg-[#1E1545] selection:text-white">
        <!-- Barra superior -->
        <header class="w-full max-w-5xl mx-auto px-4 sm:px-6 pt-5 pb-3 flex items-center justify-between">
            <Link
                :href="route('auth.portal')"
                class="w-10 h-10 rounded-xl flex items-center justify-center bg-white border border-[#E7E4ED] text-[#777386] hover:text-[#1E1545] hover:border-[#CFC8E0] shadow-[0_1px_2px_rgba(33,27,53,0.04)] transition duration-150"
                title="Volver"
                aria-label="Volver"
            >
                <ArrowLeft class="w-4 h-4" />
            </Link>

            <Link href="/" class="flex items-center">
                <img
                    src="/images/logo.png"
                    alt="Puritronic"
                    class="h-8 sm:h-9 w-auto object-contain"
                />
            </Link>

            <Link
                href="/"
                class="w-10 h-10 rounded-xl flex items-center justify-center bg-white border border-[#E7E4ED] text-[#777386] hover:text-[#1E1545] hover:border-[#CFC8E0] shadow-[0_1px_2px_rgba(33,27,53,0.04)] transition duration-150"
                title="Ir al inicio"
                aria-label="Ir al inicio"
            >
                <Home class="w-4 h-4" />
            </Link>
        </header>

        <!-- Contenedor principal -->
        <main class="flex-1 w-full max-w-5xl mx-auto px-4 sm:px-6 py-4 sm:py-6 flex items-center justify-center">
            <div class="w-full bg-white border border-[#E7E4ED] rounded-3xl shadow-[0_10px_36px_rgba(30,21,69,0.06)] overflow-hidden grid grid-cols-1 lg:grid-cols-12">
                <!-- Columna ilustración (compacta en móvil/tablet) -->
                <div class="lg:col-span-5 bg-[#F5F4F8]/80 border-b lg:border-b-0 lg:border-r border-[#E7E4ED] p-6 sm:p-8 flex items-center justify-center">
                    <div class="w-36 sm:w-48 lg:w-full max-w-[290px] mx-auto">
                        <img
                            src="/images/login.svg"
                            alt="Iniciar sesión"
                            class="w-full h-auto object-contain mx-auto"
                        />
                    </div>
                </div>

                <!-- Columna formulario -->
                <div class="lg:col-span-7 p-6 sm:p-9 lg:p-11 flex flex-col justify-center">
                    <div class="w-full max-w-md mx-auto space-y-5">
                        <div class="space-y-1 text-center lg:text-left">
                            <h1 class="text-2xl sm:text-[26px] font-bold text-[#211B35] tracking-tight">
                                Iniciar sesión
                            </h1>
                            <p class="text-xs sm:text-sm text-[#777386]">
                                Ingresa tu correo electrónico y contraseña para continuar.
                            </p>
                        </div>

                        <div
                            v-if="status"
                            class="p-3 rounded-xl bg-[#E8F5EE] border border-[#BBE4CE] text-xs font-medium text-[#26845B]"
                        >
                            {{ status }}
                        </div>

                        <form @submit.prevent="submit" class="space-y-4">
                            <!-- Correo Electrónico -->
                            <div class="space-y-1.5">
                                <label for="email" class="block text-xs font-medium text-[#211B35]">
                                    Correo electrónico
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#777386]">
                                        <Mail class="w-4 h-4" />
                                    </div>
                                    <input
                                        id="email"
                                        type="email"
                                        v-model="form.email"
                                        required
                                        autofocus
                                        autocomplete="username"
                                        placeholder="ejemplo@correo.com"
                                        class="w-full pl-10 pr-4 min-h-[44px] rounded-xl border border-[#E7E4ED] bg-white focus:border-[#1E1545] focus:ring-2 focus:ring-[#1E1545]/10 text-sm text-[#211B35] placeholder-[#9B96AA] transition duration-150 outline-none"
                                    />
                                </div>
                                <InputError :message="form.errors.email" />
                            </div>

                            <!-- Contraseña -->
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <label for="password" class="block text-xs font-medium text-[#211B35]">
                                        Contraseña
                                    </label>
                                    <Link
                                        v-if="canResetPassword"
                                        :href="route('password.request')"
                                        class="text-xs font-medium text-[#0071BA] hover:text-[#005994] transition-colors"
                                    >
                                        ¿Olvidaste tu contraseña?
                                    </Link>
                                </div>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#777386]">
                                        <Lock class="w-4 h-4" />
                                    </div>
                                    <input
                                        id="password"
                                        :type="showPassword ? 'text' : 'password'"
                                        v-model="form.password"
                                        required
                                        autocomplete="current-password"
                                        placeholder="••••••••"
                                        class="w-full pl-10 pr-10 min-h-[44px] rounded-xl border border-[#E7E4ED] bg-white focus:border-[#1E1545] focus:ring-2 focus:ring-[#1E1545]/10 text-sm text-[#211B35] placeholder-[#9B96AA] transition duration-150 outline-none"
                                    />
                                    <button
                                        type="button"
                                        @click="showPassword = !showPassword"
                                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-[#777386] hover:text-[#211B35] focus:outline-none cursor-pointer"
                                        aria-label="Mostrar u ocultar contraseña"
                                    >
                                        <EyeOff v-if="showPassword" class="w-4 h-4" />
                                        <Eye v-else class="w-4 h-4" />
                                    </button>
                                </div>
                                <InputError :message="form.errors.password" />
                            </div>

                            <!-- Recordarme -->
                            <div class="pt-0.5">
                                <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                    <Checkbox
                                        name="remember"
                                        v-model:checked="form.remember"
                                        class="rounded-md text-[#1E1545] focus:ring-[#1E1545] border-[#CFC8E0]"
                                    />
                                    <span class="text-xs text-[#777386] font-medium">Recordarme en este equipo</span>
                                </label>
                            </div>

                            <!-- Botón enviar -->
                            <div class="pt-1">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="w-full min-h-[46px] px-6 rounded-xl bg-[#1E1545] hover:bg-[#2B1F60] text-white font-semibold text-sm shadow-[0_2px_8px_rgba(30,21,69,0.16)] transition duration-150 disabled:opacity-55 disabled:pointer-events-none flex items-center justify-center gap-2 cursor-pointer"
                                >
                                    <span>{{ form.processing ? 'Iniciando sesión...' : 'Iniciar sesión' }}</span>
                                    <ArrowRight class="w-4 h-4" />
                                </button>
                            </div>
                        </form>

                        <!-- Enlace a Registro -->
                        <div class="pt-4 border-t border-[#E7E4ED] text-center">
                            <p class="text-xs text-[#777386]">
                                ¿Aún no tienes cuenta?
                                <Link
                                    :href="route('register')"
                                    class="font-semibold text-[#0071BA] hover:text-[#005994] ml-1 transition-colors"
                                >
                                    Regístrate aquí
                                </Link>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="w-full py-3.5 text-center text-xs text-[#777386]">
            Puritronic &copy; {{ new Date().getFullYear() }}
        </footer>
    </div>
</template>
