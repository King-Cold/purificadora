<script setup>
import { computed, ref, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AppSidebar from '@/Components/AppSidebar.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    wide: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const mobileSidebarOpen = ref(false);
const sidebarCollapsed = ref(localStorage.getItem('puritronic_sidebar_collapsed') === 'true');

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    try {
        localStorage.setItem('puritronic_sidebar_collapsed', sidebarCollapsed.value);
    } catch (e) {
        // Ignorar si el almacenamiento local no está disponible
    }
};

// Cerrar drawer en móvil cuando cambie de ruta
watch(
    () => page.url,
    () => {
        mobileSidebarOpen.value = false;
    }
);

// Fecha formateada en español idéntica al diseño solicitado
const formattedDate = computed(() => {
    const now = new Date();
    const str = new Intl.DateTimeFormat('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(now);
    return str.charAt(0).toUpperCase() + str.slice(1);
});

const getInitials = (name) => {
    if (!name) return 'U';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return parts[0].slice(0, 2).toUpperCase();
};
</script>

<template>
    <div class="min-h-screen bg-slate-100 flex flex-col antialiased text-slate-800">

        <!-- ========================================================================= -->
        <!-- 1. COMPONENTE DEL SIDEBAR (COLAPSABLE EN ESCRITORIO) -->
        <!-- ========================================================================= -->
        <AppSidebar
            :collapsed="sidebarCollapsed"
            :mobile-open="mobileSidebarOpen"
            @close="mobileSidebarOpen = false"
            @toggle="toggleSidebar"
        />

        <!-- ========================================================================= -->
        <!-- 2. CONTENIDO PRINCIPAL Y BARRA SUPERIOR -->
        <!-- ========================================================================= -->
        <div
            class="flex-1 flex flex-col min-w-0 transition-all duration-300"
            :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64'"
        >

            <!-- Barra Superior (Topbar) -->
            <header class="sticky top-0 z-30 h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-6">
                <!-- Lado Izquierdo: Botón y logotipo para móviles -->
                <div class="flex items-center gap-3">
                    <!-- Botón móvil -->
                    <button
                        @click="mobileSidebarOpen = true"
                        type="button"
                        class="lg:hidden h-9 w-9 inline-flex items-center justify-center rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition focus:outline-none"
                        title="Abrir menú"
                        aria-label="Abrir menú"
                    >
                        <i class="fa-solid fa-bars text-sm"></i>
                    </button>

                    <!-- Logotipo visible ÚNICAMENTE en móvil -->
                    <Link
                        :href="route('dashboard')"
                        class="lg:hidden flex items-center gap-2 focus:outline-none"
                    >
                        <ApplicationLogo class="h-8 w-auto" />
                    </Link>
                </div>

                <!-- Lado Derecho: Widgets de Fecha, Notificaciones y Perfil -->
                <div class="flex items-center gap-3 ml-auto">
                    
                    <!-- WIDGET 1: FECHA ACTUAL (Calendario) -->
                    <div class="hidden sm:flex items-center gap-2.5 px-3.5 py-1.5 bg-white border border-slate-200/90 rounded-xl shadow-xs text-xs font-medium text-slate-700">
                        <i class="fa-regular fa-calendar text-slate-600 text-sm"></i>
                        <span>{{ formattedDate }}</span>
                    </div>

                    <!-- WIDGET 2: CAMPANA DE NOTIFICACIONES CON BADGE ROJO -->
                    <div class="relative">
                        <button
                            type="button"
                            class="h-9 w-9 rounded-xl bg-white border border-slate-200/90 flex items-center justify-center text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition shadow-xs focus:outline-none"
                            title="Notificaciones"
                            aria-label="3 notificaciones pendientes"
                        >
                            <i class="fa-regular fa-bell text-sm"></i>
                            <span class="absolute -top-1.5 -right-1.5 h-4 min-w-4 px-1 rounded-full bg-rose-500 text-white font-bold text-[10px] flex items-center justify-center shadow-xs">
                                3
                            </span>
                        </button>
                    </div>

                    <!-- WIDGET 3: MENÚ DE USUARIO / PERFIL -->
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 py-1.5 px-2.5 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200/90 transition shadow-xs focus:outline-none"
                            >
                                <div class="h-6 w-6 rounded-md bg-blue-900 text-white font-bold text-[10px] flex items-center justify-center shrink-0">
                                    {{ getInitials($page.props.auth.user.name) }}
                                </div>
                                <span class="max-w-[120px] sm:max-w-[180px] truncate">
                                    {{ $page.props.auth.user.name }}
                                </span>
                                <svg
                                    class="h-4 w-4 text-slate-400"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                        </template>

                        <template #content>
                            <div class="px-4 py-2.5 border-b border-slate-100 text-xs">
                                <p class="font-bold text-slate-900 truncate">
                                    {{ $page.props.auth.user.name }}
                                </p>
                                <p class="text-slate-500 font-mono truncate text-[11px]">
                                    {{ $page.props.auth.user.email }}
                                </p>
                                <div class="mt-1.5">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-900">
                                        {{ $page.props.auth.user.roles?.[0] || 'Usuario' }}
                                    </span>
                                </div>
                            </div>

                            <DropdownLink
                                :href="route('profile.edit')"
                                class="flex items-center gap-2 text-xs"
                            >
                                <i class="fa-solid fa-user text-slate-400 text-xs"></i>
                                <span>Mi perfil</span>
                            </DropdownLink>

                            <DropdownLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="flex items-center gap-2 text-rose-600 hover:text-rose-700 text-xs"
                            >
                                <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
                                <span>Cerrar sesión</span>
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <!-- Encabezado de Página ($slots.header) -->
            <div
                v-if="$slots.header"
                class="bg-white border-b border-slate-200"
            >
                <div :class="wide ? 'mx-auto w-full max-w-[98%] 2xl:max-w-[1850px] px-4 py-6 sm:px-6 lg:px-8' : 'mx-auto max-w-7xl 2xl:max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-8'">
                    <slot name="header" />
                </div>
            </div>

            <!-- Contenido Principal -->
            <main class="flex-1">
                <slot />
            </main>
        </div>

    </div>
</template>
