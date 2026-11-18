<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, ChevronRight, Home, LogIn, MessageCircle, UserPlus } from 'lucide-vue-next';
</script>

<template>
    <Head title="Acceso · Puritronic" />

    <div class="min-h-screen w-full bg-[#F5F4F8] font-sans antialiased text-[#211B35] flex flex-col justify-between selection:bg-[#1E1545] selection:text-white">
        <!-- Barra superior -->
        <header class="w-full max-w-5xl mx-auto px-4 sm:px-6 pt-5 pb-3 flex items-center justify-between">
            <Link
                href="/"
                class="w-10 h-10 rounded-xl flex items-center justify-center bg-white border border-[#E7E4ED] text-[#777386] hover:text-[#1E1545] hover:border-[#CFC8E0] shadow-[0_1px_2px_rgba(33,27,53,0.04)] transition duration-150"
                title="Ir al inicio"
                aria-label="Ir al inicio"
            >
                <Home class="w-4 h-4" />
            </Link>

            <Link href="/" class="flex items-center">
                <img
                    src="/images/logo.png"
                    alt="Puritronic"
                    class="h-8 sm:h-9 w-auto object-contain"
                />
            </Link>

            <div class="w-10" aria-hidden="true" />
        </header>

        <!-- Contenedor principal -->
        <main class="flex-1 w-full max-w-5xl mx-auto px-4 sm:px-6 py-4 sm:py-6 flex items-center justify-center">
            <div class="w-full bg-white border border-[#E7E4ED] rounded-3xl shadow-[0_10px_36px_rgba(30,21,69,0.06)] overflow-hidden grid grid-cols-1 lg:grid-cols-12">
                <!-- Panel visual izquierdo -->
                <div class="lg:col-span-5 bg-[#F5F4F8]/80 border-b lg:border-b-0 lg:border-r border-[#E7E4ED] p-6 sm:p-8 flex items-center justify-center">
                    <div class="w-40 sm:w-56 lg:w-full max-w-[290px] mx-auto">
                        <img
                            src="/images/opciondeir.svg"
                            alt="Seleccionar acceso"
                            class="w-full h-auto object-contain mx-auto"
                        />
                    </div>
                </div>

                <!-- Panel de opciones derecho -->
                <div class="lg:col-span-7 p-6 sm:p-9 lg:p-11 flex flex-col justify-center">
                    <div class="w-full max-w-md mx-auto space-y-6">
                        <div class="space-y-1.5 text-center lg:text-left">
                            <h1 class="text-2xl sm:text-[26px] font-bold text-[#211B35] tracking-tight">
                                ¿Cómo deseas ingresar?
                            </h1>
                            <p class="text-xs sm:text-sm text-[#777386]">
                                Inicia sesión en tu cuenta o regístrate en segundos para gestionar tus pedidos.
                            </p>
                        </div>

                        <!-- Opciones principales -->
                        <div class="space-y-3">
                            <!-- Opción 1: Iniciar Sesión -->
                            <Link
                                :href="route('login')"
                                class="group w-full flex items-center justify-between p-4 rounded-2xl bg-[#1E1545] hover:bg-[#2B1F60] text-white shadow-[0_4px_14px_rgba(30,21,69,0.16)] transition duration-150"
                            >
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <div class="w-11 h-11 rounded-xl bg-white/15 text-white flex items-center justify-center shrink-0">
                                        <LogIn class="w-5 h-5" />
                                    </div>
                                    <span class="text-sm font-semibold text-white">
                                        Iniciar sesión
                                    </span>
                                </div>
                                <div class="w-8 h-8 rounded-xl bg-white/15 text-white flex items-center justify-center shrink-0 ml-3 group-hover:translate-x-0.5 transition-transform duration-150">
                                    <ArrowRight class="w-4 h-4" />
                                </div>
                            </Link>

                            <!-- Opción 2: Crear Cuenta -->
                            <Link
                                :href="route('register')"
                                class="group w-full flex items-center justify-between p-4 rounded-2xl bg-white hover:bg-[#EAF4FB]/45 border border-[#E7E4ED] hover:border-[#0071BA]/60 transition duration-150"
                            >
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <div class="w-11 h-11 rounded-xl bg-[#EAF4FB] text-[#0071BA] group-hover:bg-[#0071BA] group-hover:text-white flex items-center justify-center shrink-0 transition-colors duration-150">
                                        <UserPlus class="w-5 h-5" />
                                    </div>
                                    <span class="text-sm font-semibold text-[#211B35]">
                                        Crear cuenta
                                    </span>
                                </div>
                                <div class="w-8 h-8 rounded-xl bg-[#F5F4F8] text-[#777386] group-hover:bg-[#EAF4FB] group-hover:text-[#0071BA] flex items-center justify-center shrink-0 ml-3 transition-colors duration-150">
                                    <ChevronRight class="w-4 h-4" />
                                </div>
                            </Link>
                        </div>

                        <!-- Pedido rápido sin registro -->
                        <div class="pt-4 border-t border-[#E7E4ED] flex flex-col sm:flex-row items-center justify-between gap-3">
                            <span class="text-xs text-[#777386]">
                                ¿Prefieres pedir sin cuenta?
                            </span>
                            <a
                                href="https://wa.me/529991234567?text=Hola,%20quisiera%20pedir%20un%20garrafón%20de%20agua%20a%20domicilio"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-2 text-xs font-semibold text-[#26845B] hover:text-[#1D6B48] bg-[#E8F5EE] hover:bg-[#D8EFE3] border border-[#BBE4CE] px-3.5 py-2 rounded-xl transition duration-150"
                            >
                                <MessageCircle class="w-3.5 h-3.5" />
                                <span>Pedir por WhatsApp</span>
                            </a>
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
