<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Bienvenido a Puritronic" />

    <div class="min-h-screen w-full bg-white font-['Poppins',sans-serif] text-slate-800 flex flex-col justify-between selection:bg-[#0084ff] selection:text-white">
        
        <!-- Header superior / Barra de navegación -->
        <header class="w-full px-4 py-3 sm:px-8 sm:py-4 flex items-center justify-between">
            <Link
                href="/"
                class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center bg-slate-50 border border-slate-200/90 text-slate-600 hover:text-[#0084ff] hover:border-[#0084ff]/40 hover:bg-blue-50/50 shadow-xs transition-all"
                title="Ir al inicio"
                aria-label="Ir al inicio"
            >
                <i class="fa-solid fa-house text-xs sm:text-sm"></i>
            </Link>

            <Link href="/" class="flex items-center">
                <img
                    src="/images/logo.png"
                    alt="Puritronic"
                    class="h-7 sm:h-9 w-auto object-contain drop-shadow-xs"
                />
            </Link>

            <div class="w-9 sm:w-10"></div>
        </header>

        <!-- Contenido principal: en móvil ilustración compacta para priorizar las opciones -->
        <main class="flex-1 w-full max-w-6xl mx-auto px-4 sm:px-8 lg:px-12 py-2 sm:py-4 flex flex-col lg:flex-row items-center justify-center gap-4 lg:gap-14">
            
            <!-- Columna Ilustración (Tamaño balanceado y nítido) -->
            <div class="w-full lg:w-1/2 flex justify-center items-center">
                <div class="w-60 sm:w-72 md:w-80 lg:w-full lg:max-w-lg">
                    <img
                        src="/images/opciondeir.svg"
                        alt="Seleccionar acceso"
                        class="w-full h-auto max-h-[260px] sm:max-h-[320px] lg:max-h-[480px] object-contain drop-shadow-sm transform hover:scale-[1.02] transition-transform duration-300 mx-auto"
                    />
                </div>
            </div>

            <!-- Columna Opciones / Acciones -->
            <div class="w-full lg:w-1/2 flex justify-center">
                <div class="w-full max-w-sm sm:max-w-md space-y-4 sm:space-y-6">
                    
                    <!-- Encabezado de la selección -->
                    <div class="text-center lg:text-left space-y-1">
                        <span class="inline-block text-[10px] sm:text-[11px] font-extrabold uppercase tracking-widest text-[#0084ff] bg-blue-50 border border-blue-100 px-3 py-0.5 rounded-full">
                            Elige una opción
                        </span>
                        <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-[#0e1c2e] tracking-tight">
                            ¿Cómo deseas ingresar?
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500 font-normal">
                            Selecciona para continuar a tu cuenta o registrarte.
                        </p>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="space-y-3 sm:space-y-3.5">
                        
                        <!-- Opción 1: Iniciar Sesión -->
                        <Link
                            :href="route('login')"
                            class="group w-full flex items-center justify-between p-3.5 sm:p-4 rounded-2xl bg-slate-50 hover:bg-blue-50/60 border border-slate-200/80 hover:border-[#0084ff]/40 shadow-xs hover:shadow-md transition-all duration-200"
                        >
                            <div class="flex items-center gap-3.5">
                                <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-blue-100 text-[#0084ff] group-hover:bg-[#0084ff] group-hover:text-white flex items-center justify-center text-base sm:text-lg transition-all duration-200 shadow-xs flex-shrink-0">
                                    <i class="fa-solid fa-right-to-bracket"></i>
                                </div>
                                <div class="text-left">
                                    <h3 class="text-xs sm:text-sm font-bold text-slate-900 group-hover:text-[#0084ff] transition-colors">
                                        Iniciar Sesión
                                    </h3>
                                    <p class="text-[11px] sm:text-xs text-slate-500">
                                        Ya tengo una cuenta registrada
                                    </p>
                                </div>
                            </div>
                            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white group-hover:bg-[#0084ff] text-slate-400 group-hover:text-white flex items-center justify-center text-xs transition-all duration-200 shadow-xs flex-shrink-0 ml-2">
                                <i class="fa-solid fa-chevron-right group-hover:translate-x-0.5 transition-transform"></i>
                            </div>
                        </Link>

                        <!-- Opción 2: Crear Cuenta -->
                        <Link
                            :href="route('register')"
                            class="group w-full flex items-center justify-between p-3.5 sm:p-4 rounded-2xl bg-gradient-to-r from-[#0084ff] to-[#009bf5] hover:from-[#0070dc] hover:to-[#008fe0] text-white shadow-md shadow-blue-500/20 hover:shadow-lg hover:shadow-blue-500/30 hover:-translate-y-0.5 transition-all duration-200"
                        >
                            <div class="flex items-center gap-3.5">
                                <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-white/20 text-white flex items-center justify-center text-base sm:text-lg shadow-xs flex-shrink-0">
                                    <i class="fa-solid fa-user-plus"></i>
                                </div>
                                <div class="text-left">
                                    <h3 class="text-xs sm:text-sm font-bold text-white">
                                        Crear Cuenta
                                    </h3>
                                    <p class="text-[11px] sm:text-xs text-blue-100 font-light">
                                        Soy nuevo, registrarme ahora
                                    </p>
                                </div>
                            </div>
                            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white/20 text-white flex items-center justify-center text-xs flex-shrink-0 ml-2 group-hover:translate-x-1 transition-transform">
                                <i class="fa-solid fa-arrow-right"></i>
                            </div>
                        </Link>

                    </div>

                    <!-- Enlace directo para pedir por WhatsApp sin cuenta -->
                    <div class="pt-2 sm:pt-3 border-t border-slate-100 text-center">
                        <p class="text-[11px] sm:text-xs text-slate-500 mb-1.5">
                            ¿Prefieres pedir sin registro?
                        </p>
                        <a
                            href="https://wa.me/529991234567?text=Hola,%20quisiera%20pedir%20un%20garrafón%20de%20agua%20a%20domicilio"
                            target="_blank"
                            class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-600 hover:text-emerald-700 bg-emerald-50 hover:bg-emerald-100/70 border border-emerald-200/80 px-3.5 py-1.5 sm:px-4 sm:py-2 rounded-xl transition-all"
                        >
                            <i class="fa-brands fa-whatsapp text-sm"></i>
                            <span>Pedir por WhatsApp</span>
                        </a>
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
