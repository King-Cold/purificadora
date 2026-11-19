<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import AppSidebar from '@/Components/AppSidebar.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { usePage } from '@inertiajs/vue3';

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
        if (route().current('admin.exchange-inventory.*')) return 'Cambios';
        if (route().current('admin.settings.*')) return 'Configuración';

        // Rutas de Repartidor
        if (route().current('repartidor.dashboard')) return 'Inicio';
        if (route().current('repartidor.truck-load.*')) return 'Mi carga';
        if (route().current('repartidor.debt.*')) return 'Cobros';
        if (route().current('repartidor.customers.*')) return 'Clientes';
        if (route().current('repartidor.direct-service.*')) return 'Pedido al paso';
        if (route().current('repartidor.stops.*')) return 'Atender pedido';
        if (route().current('repartidor.reparto') || route().current('repartidor.*')) return 'Reparto';

        // Rutas de Planta
        if (route().current('planta.dashboard')) return 'Inicio';
        if (route().current('planta.quick-refill.*')) return 'Llenado rápido';
        if (route().current('planta.fijos.*')) return 'Fijos';
        if (route().current('planta.cambios.*')) return 'Cambios';
        if (route().current('planta.inventory.*')) return 'Existencias';
        if (route().current('planta.incidents.*')) return 'Incidencias';
        if (route().current('planta.history.*')) return 'Historial';
        if (route().current('planta.reception.*')) return 'Recepción en planta';
        if (route().current('planta.production.*')) return 'Lavado y llenado';
        if (route().current('planta.loads.*')) return 'Cargas al repartidor';
        if (route().current('planta.*')) return 'Planta';
    } catch (e) {
        // fallback
    }
    return 'Puritronic';
});

const currentModuleSubtitle = computed(() => {
    try {
        if (route().current('planta.*')) return 'Planta';
        if (route().current('repartidor.*')) return 'Reparto';
        if (route().current('admin.*')) return 'Administración';
        if (route().current('cliente.*')) return 'Portal Cliente';
    } catch (e) {
        // fallback
    }
    return 'Puritronic';
});

// Fecha en formato corto
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
    <div class="min-h-screen bg-[#F4F3F8] flex flex-col antialiased text-[#292638]">

        <!-- ========================================================================= -->
        <!-- 1. COMPONENTE DEL SIDEBAR                                                 -->
        <!-- ========================================================================= -->
        <AppSidebar
            :collapsed="sidebarCollapsed"
            :mobile-open="mobileSidebarOpen"
            @close="mobileSidebarOpen = false"
        />

        <!-- ========================================================================= -->
        <!-- 2. CONTENIDO PRINCIPAL Y BARRA SUPERIOR                                   -->
        <!-- ========================================================================= -->
        <div
            class="flex-1 flex flex-col min-w-0 transition-all duration-300"
            :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64'"
            :inert="mobileSidebarOpen ? true : undefined"
            :aria-hidden="mobileSidebarOpen ? 'true' : undefined"
        >

            <!-- Barra Superior Limpia y Minimalista (h-16 / 64px) -->
            <header class="sticky top-0 z-30 h-16 bg-white/95 backdrop-blur-md border-b border-[#E9E7F0] flex items-center justify-between px-4 sm:px-6">
                <!-- Lado Izquierdo: Botón menú + Ruta de navegación -->
                <div class="flex items-center gap-3 min-w-0">
                    <button
                        @click="handleMenuToggle"
                        type="button"
                        class="h-9 w-9 inline-flex items-center justify-center rounded-xl bg-white hover:bg-[#E9F3F8] text-[#747181] hover:text-[#0071BA] border border-[#E9E7F0] transition-colors focus:outline-none focus:ring-2 focus:ring-[#0071BA]/25 cursor-pointer shrink-0"
                        :title="sidebarCollapsed ? 'Expandir menú' : 'Contraer menú'"
                        aria-label="Alternar menú de navegación"
                    >
                        <i class="fa-solid fa-bars text-sm"></i>
                    </button>

                    <nav aria-label="Ubicación actual" class="flex items-center gap-2 min-w-0">
                        <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-[#E9F3F8] text-[#0071BA] shrink-0">
                            {{ currentModuleSubtitle }}
                        </span>
                        <i class="hidden sm:inline-block fa-solid fa-chevron-right text-[9px] text-[#A6A3B2]"></i>
                        <h2 class="text-sm sm:text-[15px] font-semibold text-[#292638] tracking-[-0.01em] truncate">
                            {{ currentSectionTitle }}
                        </h2>
                    </nav>
                </div>

                <!-- Lado Derecho: Fecha discreta + Perfil del operador -->
                <div class="flex items-center gap-3 sm:gap-4 ml-auto shrink-0">
                    <!-- Fecha en formato discreto -->
                    <div class="hidden sm:inline-flex items-center gap-1.5 text-xs font-medium text-[#747181] px-2.5 py-1.5 rounded-lg bg-[#F4F3F8] border border-[#E9E7F0]">
                        <i class="fa-regular fa-calendar text-[#0071BA] text-xs"></i>
                        <span>{{ shortFormattedDate }}</span>
                    </div>

                    <span class="hidden sm:block h-5 w-px bg-[#E9E7F0]"></span>

                    <!-- Menú de Usuario -->
                    <Dropdown align="right" width="56">
                        <template #trigger>
                            <button
                                type="button"
                                class="group inline-flex items-center gap-2.5 py-1 px-1.5 sm:pr-2.5 rounded-xl hover:bg-[#F4F3F8] border border-transparent hover:border-[#E9E7F0] transition-colors focus:outline-none focus:ring-2 focus:ring-[#0071BA]/25 cursor-pointer min-h-[40px]"
                            >
                                <div class="h-8 w-8 rounded-lg bg-[#DCECF7] text-[#0071BA] font-semibold text-xs flex items-center justify-center shrink-0">
                                    {{ getInitials($page.props.auth.user.name) }}
                                </div>
                                <div class="hidden sm:flex flex-col items-start text-left leading-tight">
                                    <span class="max-w-[130px] truncate font-semibold text-[#292638] text-xs">
                                        {{ shortName }}
                                    </span>
                                    <span class="text-[11px] font-normal text-[#747181] truncate max-w-[130px]">
                                        {{ $page.props.auth.user.roles?.[0] || 'Usuario' }}
                                    </span>
                                </div>
                                <i class="fa-solid fa-chevron-down text-[10px] text-[#A6A3B2] group-hover:text-[#0071BA] transition-colors"></i>
                            </button>
                        </template>

                        <template #content>
                            <!-- Información del operador -->
                            <div class="px-4 py-3 border-b border-[#E9E7F0] bg-[#F4F3F8]/70">
                                <p class="text-xs font-semibold text-[#292638] truncate">
                                    {{ $page.props.auth.user.name }}
                                </p>
                                <p class="text-[11px] text-[#747181] truncate mt-0.5">
                                    {{ $page.props.auth.user.email }}
                                </p>
                                <div class="mt-2">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-medium bg-[#DAD6E8]/70 text-[#1E1545]">
                                        <span class="h-1.5 w-1.5 rounded-full bg-[#1E1545]"></span>
                                        {{ $page.props.auth.user.roles?.[0] || 'Cliente' }}
                                    </span>
                                </div>
                            </div>

                            <div class="py-1 bg-white">
                                <DropdownLink
                                    :href="route('profile.edit')"
                                    class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-[#292638] hover:bg-[#E9F3F8] hover:text-[#0071BA]"
                                >
                                    <i class="fa-regular fa-user text-[#747181] text-xs w-4 text-center"></i>
                                    <span>Mi perfil</span>
                                </DropdownLink>

                                <div class="my-1 border-t border-[#E9E7F0]"></div>

                                <DropdownLink
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                    class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-[#E82147] hover:bg-[#FCECEF]"
                                >
                                    <i class="fa-solid fa-arrow-right-from-bracket text-xs w-4 text-center"></i>
                                    <span>Cerrar sesión</span>
                                </DropdownLink>
                            </div>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <!-- Encabezado de Página ($slots.header) -->
            <div
                v-if="$slots.header"
                class="bg-white border-b border-[#E9E7F0]"
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
