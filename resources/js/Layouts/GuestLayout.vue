<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="min-h-screen flex flex-col justify-between items-center bg-gradient-to-b from-slate-50 via-white to-blue-50/30 px-4 py-8 sm:py-12 font-['Poppins',sans-serif] text-slate-800 selection:bg-[#0084ff] selection:text-white"
    >
        <!-- Top bar con logo y retorno -->
        <header class="w-full max-w-md flex items-center justify-between mb-4">
            <Link
                href="/"
                class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center bg-white border border-slate-200/90 text-slate-600 hover:text-[#0084ff] hover:border-[#0084ff]/40 hover:bg-blue-50/50 shadow-xs transition-all"
                title="Volver al inicio"
                aria-label="Volver al inicio"
            >
                <i class="fa-solid fa-arrow-left text-xs sm:text-sm"></i>
            </Link>

            <Link href="/" class="flex items-center">
                <ApplicationLogo class="h-8 sm:h-9 w-auto object-contain drop-shadow-xs" />
            </Link>

            <div class="w-9 sm:w-10"></div>
        </header>

        <!-- Tarjeta Central -->
        <main class="w-full max-w-md bg-white rounded-3xl p-6 sm:p-8 shadow-xl shadow-slate-200/60 border border-slate-200/70">
            <slot />
        </main>

        <!-- Footer discreto -->
        <footer class="mt-8 text-center text-xs text-slate-400">
            &copy; {{ new Date().getFullYear() }} Puritronic Agua Purificada. Todos los derechos reservados.
        </footer>
    </div>
</template>

