<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
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

const handleMenuToggle = () => {
    if (typeof window !== 'undefined' && window.innerWidth < 1024) {
        mobileSidebarOpen.value = !mobileSidebarOpen.value;
    } else {
        toggleSidebar();
    }
};

// Cerrar drawer en móvil cuando cambie de ruta
watch(
    () => page.url,
    () => {
        mobileSidebarOpen.value = false;
    }
);

// Bloquear interacción y scroll de fondo cuando el menú móvil esté abierto
watch(mobileSidebarOpen, (open) => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = open ? 'hidden' : '';
    }
});

onUnmounted(() => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});

// Nombre de la sección actual para el encabezado
const currentSectionTitle = computed(() => {
    try {
        if (route().current('cliente.dashboard') || route().current('dashboard')) return 'Inicio';
        if (route().current('cliente.orders.create')) return 'Nuevo pedido';
        if (route().current('cliente.orders.index')) return 'Mis pedidos';
        if (route().current('cliente.orders.show')) return 'Detalle del pedido';
        if (route().current('cliente.addresses.*')) return 'Mi domicilio';
        if (route().current('profile.edit')) return 'Mi perfil';

        // Rutas de administración y otros roles
        if (route().current('admin.dashboard')) return 'Inicio';
        if (route().current('admin.users.*')) return 'Usuarios';
        if (route().current('admin.customers.*')) return 'Clientes';
        if (route().current('admin.orders.*')) return 'Pedidos';
        if (route().current('admin.settings.*')) return 'Configuración';
        if (route().current('repartidor.*')) return 'Reparto';
        if (route().current('planta.*')) return 'Planta';
    } catch (e) {
        // fallback
    }
    return 'Puritronic';
});

// Fecha en formato corto sin recuadro (oculta en móvil)
const shortFormattedDate = computed(() => {
    const now = new Date();
    const day = now.getDate();
    const monthNames = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const month = monthNames[now.getMonth()];
    const year = now.getFullYear();
    return `${day} ${month} ${year}`;
});

// Iniciales para el avatar
const getInitials = (name) => {
    if (!name) return 'U';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return parts[0].slice(0, 2).toUpperCase();
};

// Nombre corto para el botón del header
const shortName = computed(() => {
    const fullName = page.props.auth?.user?.name || '';
    if (!fullName) return 'Usuario';
    const parts = fullName.trim().split(/\s+/);
    return parts[0];
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex flex-col antialiased text-slate-800">

        <!-- ========================================================================= -->
        <!-- 1. COMPONENTE DEL SIDEBAR -->
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
            :inert="mobileSidebarOpen ? true : undefined"
            :aria-hidden="mobileSidebarOpen ? 'true' : undefined"
        >

            <!-- Barra Superior (Header de ~64 px) -->
            <header class="sticky top-0 z-30 h-16 bg-white/95 backdrop-blur-xs border-b border-slate-200/80 flex items-center justify-between px-4 sm:px-6">
                <!-- Lado Izquierdo: Botón para abrir/contraer menú y nombre de la sección actual -->
                <div class="flex items-center gap-3">
                    <button
                        @click="handleMenuToggle"
                        type="button"
                        class="h-10 w-10 inline-flex items-center justify-center rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition focus:outline-none focus:ring-2 focus:ring-blue-900/20 cursor-pointer"
                        :title="sidebarCollapsed ? 'Expandir menú' : 'Contraer menú'"
                        aria-label="Alternar menú de navegación"
                    >
                        <i class="fa-solid fa-bars text-base"></i>
                    </button>

                    <!-- Nombre de la sección actual -->
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight">
                        {{ currentSectionTitle }}
                    </h2>
                </div>

                <!-- Lado Derecho: Fecha discreta en formato corto (sin recuadro) y Menú de Usuario -->
                <div class="flex items-center gap-3 sm:gap-4 ml-auto">
                    <!-- Fecha discreta en formato corto, oculta en móvil -->
                    <span class="hidden sm:inline-block text-xs font-medium text-slate-500">
                        {{ shortFormattedDate }}
                    </span>

                    <!-- Menú de Usuario -->
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 py-1 px-2 sm:px-2.5 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 hover:text-slate-900 hover:bg-slate-50 transition border border-transparent hover:border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-900/20 cursor-pointer min-h-[40px]"
                            >
                                <div class="h-8 w-8 rounded-full bg-blue-900 text-white font-semibold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                                    {{ getInitials($page.props.auth.user.name) }}
                                </div>
                                <span class="max-w-[110px] sm:max-w-[150px] truncate font-semibold text-slate-800 text-xs sm:text-sm">
                                    {{ shortName }}
                                </span>
                                <svg
                                    class="h-3.5 w-3.5 text-slate-400"
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
                            <!-- Nombre completo y correo dentro del menú -->
                            <div class="px-4 py-2.5 border-b border-slate-100 text-xs">
                                <p class="font-bold text-slate-900 truncate">
                                    {{ $page.props.auth.user.name }}
                                </p>
                                <p class="text-slate-500 font-mono truncate text-[11px] mt-0.5">
                                    {{ $page.props.auth.user.email }}
                                </p>
                                <div class="mt-1.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-900 border border-blue-100">
                                        {{ $page.props.auth.user.roles?.[0] || 'Cliente' }}
                                    </span>
                                </div>
                            </div>

                            <DropdownLink
                                :href="route('profile.edit')"
                                class="flex items-center gap-2 text-xs"
                            >
                                <i class="fa-solid fa-user text-slate-400 text-xs w-4 text-center"></i>
                                <span>Mi perfil</span>
                            </DropdownLink>

                            <DropdownLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="flex items-center gap-2 text-rose-600 hover:text-rose-700 text-xs"
                            >
                                <i class="fa-solid fa-arrow-right-from-bracket text-xs w-4 text-center"></i>
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
