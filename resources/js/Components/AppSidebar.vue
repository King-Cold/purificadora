<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    mobileOpen: {
        type: Boolean,
        default: false,
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'toggle']);

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const userRoles = computed(() => user.value.roles || []);
const isAdmin = computed(() => userRoles.value.includes('Administrador') || userRoles.value.includes('Super Admin'));
const isCliente = computed(() => userRoles.value.includes('Cliente'));
const isRepartidor = computed(() => userRoles.value.includes('Repartidor'));
const isPlanta = computed(() => userRoles.value.includes('Operador Planta'));
const pendingFixedCount = computed(() => page.props.plant?.pending_fixed_count || 0);

const isRouteActive = (pattern) => {
    try {
        return route().current(pattern);
    } catch (e) {
        return false;
    }
};

const isTopDashboardActive = computed(() => {
    if (isAdmin.value) {
        return isRouteActive('admin.dashboard');
    }
    if (isRepartidor.value) {
        return isRouteActive('repartidor.dashboard');
    }
    if (isPlanta.value) {
        return isRouteActive('planta.dashboard');
    }
    return isRouteActive('dashboard');
});

const getRouteUrl = (name) => {
    try {
        if (name && route().has(name)) {
            return route(name);
        }
    } catch (e) {
        // fallback
    }
    return '#';
};

const handleLinkClick = () => {
    if (props.mobileOpen) {
        emit('close');
    }
};

const handleKeydown = (e) => {
    if (e.key === 'Escape' && props.mobileOpen) {
        emit('close');
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <!-- ========================================================================= -->
    <!-- 1. OVERLAY MÓVIL (BACKDROP OSCURECIDO) -->
    <!-- ========================================================================= -->
    <transition
        enter-active-class="transition-opacity duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="mobileOpen"
            class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-40 lg:hidden"
            @click="$emit('close')"
            aria-hidden="true"
        />
    </transition>

    <!-- ========================================================================= -->
    <!-- 2. SIDEBAR CONTENEDOR (256 PX EXPANDIDO / 80 PX CONTRAÍDO) -->
    <!-- ========================================================================= -->
    <aside
        class="fixed inset-y-0 left-0 z-40 flex flex-col bg-[#f8fafc] border-r border-slate-200/80 select-none transition-all duration-300 ease-in-out"
        :class="[
            mobileOpen ? 'translate-x-0 shadow-xl w-64' : '-translate-x-full lg:translate-x-0',
            collapsed ? 'lg:w-20' : 'lg:w-64'
        ]"
        aria-label="Menú principal de navegación"
    >
        <!-- CABECERA: Logotipo original proporcionado y botón de cierre en móvil -->
        <div
            class="h-16 flex items-center border-b border-slate-200/80 shrink-0 bg-[#f8fafc]"
            :class="collapsed ? 'justify-center px-3' : 'justify-between px-5'"
        >
            <!-- MODO CONTRAÍDO: Isotipo / Marca compacta de Puritronic (elimina el doble botón de menú) -->
            <Link
                v-if="collapsed"
                :href="route('dashboard')"
                @click="handleLinkClick"
                class="hidden lg:flex w-10 h-10 rounded-2xl bg-blue-900 text-white items-center justify-center transition shadow-2xs hover:bg-blue-800 hover:scale-105 active:scale-95 focus:outline-none focus:ring-2 focus:ring-blue-900/20"
                title="Puritronic - Ir al inicio"
                aria-label="Puritronic"
            >
                <i class="fa-solid fa-droplet text-lg text-sky-400"></i>
            </Link>

            <!-- MODO EXPANDIDO: Logotipo original sin deformar y botón móvil de cierre -->
            <template v-else>
                <Link
                    :href="route('dashboard')"
                    @click="handleLinkClick"
                    class="flex items-center min-w-0 focus:outline-none focus:ring-2 focus:ring-blue-900/20 rounded-lg p-0.5"
                >
                    <img
                        src="/images/logo.png"
                        alt="Puritronic"
                        class="h-8 w-auto max-w-[155px] object-contain shrink-0"
                    />
                </Link>

                <!-- Botón de cerrar visible únicamente en móviles -->
                <button
                    v-if="mobileOpen"
                    @click="$emit('close')"
                    type="button"
                    class="lg:hidden h-9 w-9 rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-800 hover:bg-slate-200/60 transition focus:outline-none focus:ring-2 focus:ring-blue-900/20 cursor-pointer"
                    title="Cerrar menú"
                    aria-label="Cerrar menú"
                >
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </template>
        </div>

        <!-- LISTADO DE NAVEGACIÓN -->
        <div
            class="flex-1 overflow-y-auto py-5"
            :class="collapsed ? 'px-2.5 space-y-4' : 'px-3.5 space-y-5'"
        >

            <!-- ============================================================= -->
            <!-- NAVEGACIÓN DEL CLIENTE                                       -->
            <!-- ============================================================= -->
            <template v-if="isCliente">
                <!-- 1. INICIO (Sin encabezado "Principal") -->
                <div>
                    <nav class="space-y-1">
                        <Link
                            :href="route('dashboard')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('dashboard') || isRouteActive('cliente.dashboard')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Inicio' : ''"
                            aria-label="Inicio"
                        >
                            <span
                                v-if="isRouteActive('dashboard') || isRouteActive('cliente.dashboard')"
                                class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"
                            ></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i
                                    class="fa-solid fa-house transition-colors"
                                    :class="isRouteActive('dashboard') || isRouteActive('cliente.dashboard') ? 'text-blue-900' : 'text-slate-400 group-hover:text-slate-700'"
                                ></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Inicio</span>
                        </Link>
                    </nav>
                </div>

                <!-- 2. GRUPO: PEDIDOS (Etiqueta discreta) -->
                <div>
                    <div v-if="!collapsed" class="px-3 mb-1.5 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
                        Pedidos
                    </div>
                    <div v-else class="w-6 mx-auto my-2 border-t border-slate-200" title="Pedidos"></div>

                    <nav class="space-y-1">
                        <!-- Mis pedidos -->
                        <Link
                            :href="route('cliente.orders.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('cliente.orders.index') || isRouteActive('cliente.orders.show')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Mis pedidos' : ''"
                            aria-label="Mis pedidos"
                        >
                            <span
                                v-if="isRouteActive('cliente.orders.index') || isRouteActive('cliente.orders.show')"
                                class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"
                            ></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i
                                    class="fa-regular fa-file-lines transition-colors"
                                    :class="isRouteActive('cliente.orders.index') || isRouteActive('cliente.orders.show') ? 'text-blue-900' : 'text-slate-400 group-hover:text-slate-700'"
                                ></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Mis pedidos</span>
                        </Link>

                        <!-- Nuevo pedido -->
                        <Link
                            :href="route('cliente.orders.create')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('cliente.orders.create')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Nuevo pedido' : ''"
                            aria-label="Nuevo pedido"
                        >
                            <span
                                v-if="isRouteActive('cliente.orders.create')"
                                class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"
                            ></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i
                                    class="fa-solid fa-cart-plus transition-colors"
                                    :class="isRouteActive('cliente.orders.create') ? 'text-blue-900' : 'text-slate-400 group-hover:text-slate-700'"
                                ></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Nuevo pedido</span>
                        </Link>
                    </nav>
                </div>

                <!-- 3. GRUPO: MI CUENTA (Etiqueta discreta) -->
                <div>
                    <div v-if="!collapsed" class="px-3 mb-1.5 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
                        Mi cuenta
                    </div>
                    <div v-else class="w-6 mx-auto my-2 border-t border-slate-200" title="Mi cuenta"></div>

                    <nav class="space-y-1">
                        <!-- Mi domicilio -->
                        <Link
                            :href="route('cliente.addresses.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('cliente.addresses.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Mi domicilio' : ''"
                            aria-label="Mi domicilio"
                        >
                            <span
                                v-if="isRouteActive('cliente.addresses.*')"
                                class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"
                            ></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i
                                    class="fa-solid fa-house-chimney transition-colors"
                                    :class="isRouteActive('cliente.addresses.*') ? 'text-blue-900' : 'text-slate-400 group-hover:text-slate-700'"
                                ></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Mi domicilio</span>
                        </Link>

                        <!-- Mi perfil -->
                        <Link
                            :href="route('profile.edit')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('profile.edit') || isRouteActive('cliente.profile.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Mi perfil' : ''"
                            aria-label="Mi perfil"
                        >
                            <span
                                v-if="isRouteActive('profile.edit') || isRouteActive('cliente.profile.*')"
                                class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"
                            ></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i
                                    class="fa-regular fa-user transition-colors"
                                    :class="isRouteActive('profile.edit') || isRouteActive('cliente.profile.*') ? 'text-blue-900' : 'text-slate-400 group-hover:text-slate-700'"
                                ></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Mi perfil</span>
                        </Link>
                    </nav>
                </div>
            </template>

            <!-- ============================================================= -->
            <!-- NAVEGACIÓN EXCLUSIVA PARA REPARTIDOR (4 DESTINOS EXACTOS)      -->
            <!-- ============================================================= -->
            <template v-else-if="isRepartidor && !isAdmin">
                <nav class="space-y-2">
                    <!-- 1. Reparto -->
                    <Link
                        :href="route('repartidor.reparto')"
                        @click="handleLinkClick"
                        :class="[
                            'group flex rounded-xl transition-colors duration-150 relative min-h-[48px]',
                            collapsed
                                ? 'flex-col items-center justify-center py-2 px-1 text-center w-full'
                                : 'items-center gap-3 px-3.5 py-2.5 text-sm',
                            isRouteActive('repartidor.reparto') || isRouteActive('repartidor.dashboard') || isRouteActive('repartidor.stops.*') || isRouteActive('repartidor.direct-service.*')
                                ? 'bg-blue-50/80 text-blue-900 font-bold shadow-2xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                        ]"
                        title="Reparto"
                        aria-label="Reparto"
                    >
                        <span
                            v-if="!collapsed && (isRouteActive('repartidor.reparto') || isRouteActive('repartidor.dashboard') || isRouteActive('repartidor.stops.*') || isRouteActive('repartidor.direct-service.*'))"
                            class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"
                        ></span>
                        <div class="text-center shrink-0 flex items-center justify-center" :class="collapsed ? 'h-6 w-6 mb-0.5' : 'w-5 text-sm'">
                            <i class="fa-solid fa-truck text-base"></i>
                        </div>
                        <span :class="collapsed ? 'text-[11px] leading-tight font-bold truncate max-w-[70px]' : 'truncate font-bold'">Reparto</span>
                    </Link>

                    <!-- 2. Mi Carga -->
                    <Link
                        :href="route('repartidor.truck-load.index')"
                        @click="handleLinkClick"
                        :class="[
                            'group flex rounded-xl transition-colors duration-150 relative min-h-[48px]',
                            collapsed
                                ? 'flex-col items-center justify-center py-2 px-1 text-center w-full'
                                : 'items-center gap-3 px-3.5 py-2.5 text-sm',
                            isRouteActive('repartidor.truck-load.*')
                                ? 'bg-blue-50/80 text-blue-900 font-bold shadow-2xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                        ]"
                        title="Mi carga"
                        aria-label="Mi carga"
                    >
                        <span
                            v-if="!collapsed && isRouteActive('repartidor.truck-load.*')"
                            class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"
                        ></span>
                        <div class="text-center shrink-0 flex items-center justify-center" :class="collapsed ? 'h-6 w-6 mb-0.5' : 'w-5 text-sm'">
                            <i class="fa-solid fa-boxes-stacked text-base"></i>
                        </div>
                        <span :class="collapsed ? 'text-[11px] leading-tight font-bold truncate max-w-[70px]' : 'truncate font-bold'">Mi carga</span>
                    </Link>

                    <!-- 3. Cobros -->
                    <Link
                        :href="route('repartidor.debt.index')"
                        @click="handleLinkClick"
                        :class="[
                            'group flex rounded-xl transition-colors duration-150 relative min-h-[48px]',
                            collapsed
                                ? 'flex-col items-center justify-center py-2 px-1 text-center w-full'
                                : 'items-center gap-3 px-3.5 py-2.5 text-sm',
                            isRouteActive('repartidor.debt.*')
                                ? 'bg-blue-50/80 text-blue-900 font-bold shadow-2xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                        ]"
                        title="Cobros"
                        aria-label="Cobros"
                    >
                        <span
                            v-if="!collapsed && isRouteActive('repartidor.debt.*')"
                            class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"
                        ></span>
                        <div class="text-center shrink-0 flex items-center justify-center" :class="collapsed ? 'h-6 w-6 mb-0.5' : 'w-5 text-sm'">
                            <i class="fa-solid fa-hand-holding-dollar text-base"></i>
                        </div>
                        <span :class="collapsed ? 'text-[11px] leading-tight font-bold truncate max-w-[70px]' : 'truncate font-bold'">Cobros</span>
                    </Link>

                    <!-- 4. Clientes -->
                    <Link
                        :href="route('repartidor.customers.index')"
                        @click="handleLinkClick"
                        :class="[
                            'group flex rounded-xl transition-colors duration-150 relative min-h-[48px]',
                            collapsed
                                ? 'flex-col items-center justify-center py-2 px-1 text-center w-full'
                                : 'items-center gap-3 px-3.5 py-2.5 text-sm',
                            isRouteActive('repartidor.customers.*')
                                ? 'bg-blue-50/80 text-blue-900 font-bold shadow-2xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                        ]"
                        title="Clientes"
                        aria-label="Clientes"
                    >
                        <span
                            v-if="!collapsed && isRouteActive('repartidor.customers.*')"
                            class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"
                        ></span>
                        <div class="text-center shrink-0 flex items-center justify-center" :class="collapsed ? 'h-6 w-6 mb-0.5' : 'w-5 text-sm'">
                            <i class="fa-solid fa-users text-base"></i>
                        </div>
                        <span :class="collapsed ? 'text-[11px] leading-tight font-bold truncate max-w-[70px]' : 'truncate font-bold'">Clientes</span>
                    </Link>
                </nav>
            </template>

            <!-- ============================================================= -->
            <!-- NAVEGACIÓN EXCLUSIVA PARA OPERADOR DE PLANTA (TABLET-FIRST)   -->
            <!-- ============================================================= -->
            <template v-else-if="isPlanta && !isAdmin">
                <nav class="space-y-2">
                    <!-- 1. Llenado Rápido -->
                    <Link
                        :href="route('planta.quick-refill.index')"
                        @click="handleLinkClick"
                        :class="[
                            'group flex rounded-xl transition-colors duration-150 relative min-h-[48px]',
                            collapsed
                                ? 'flex-col items-center justify-center py-2 px-1 text-center w-full'
                                : 'items-center gap-3 px-3.5 py-2.5 text-sm',
                            isRouteActive('planta.quick-refill.*') || isRouteActive('planta.dashboard')
                                ? 'bg-blue-50/80 text-blue-900 font-bold shadow-2xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                        ]"
                        title="Llenado rápido"
                        aria-label="Llenado rápido"
                    >
                        <span
                            v-if="!collapsed && (isRouteActive('planta.quick-refill.*') || isRouteActive('planta.dashboard'))"
                            class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"
                        ></span>
                        <div class="text-center shrink-0 flex items-center justify-center" :class="collapsed ? 'h-6 w-6 mb-0.5' : 'w-5 text-sm'">
                            <i class="fa-solid fa-bolt text-base"></i>
                        </div>
                        <span :class="collapsed ? 'text-[11px] leading-tight font-bold truncate max-w-[70px]' : 'truncate font-bold'">Llenado rápido</span>
                    </Link>

                    <!-- 2. Fijos (con badge de recepciones pendientes) -->
                    <Link
                        :href="route('planta.fijos.index')"
                        @click="handleLinkClick"
                        :class="[
                            'group flex rounded-xl transition-colors duration-150 relative min-h-[48px]',
                            collapsed
                                ? 'flex-col items-center justify-center py-2 px-1 text-center w-full'
                                : 'items-center gap-3 px-3.5 py-2.5 text-sm',
                            isRouteActive('planta.fijos.*') || isRouteActive('planta.reception.*') || isRouteActive('planta.production.*') || isRouteActive('planta.loads.*')
                                ? 'bg-blue-50/80 text-blue-900 font-bold shadow-2xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                        ]"
                        title="Fijos"
                        aria-label="Fijos"
                    >
                        <span
                            v-if="!collapsed && (isRouteActive('planta.fijos.*') || isRouteActive('planta.reception.*') || isRouteActive('planta.production.*') || isRouteActive('planta.loads.*'))"
                            class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"
                        ></span>
                        <div class="text-center shrink-0 flex items-center justify-center relative" :class="collapsed ? 'h-6 w-6 mb-0.5' : 'w-5 text-sm'">
                            <i class="fa-solid fa-boxes-stacked text-base"></i>
                            <span
                                v-if="collapsed && pendingFixedCount > 0"
                                class="absolute -top-1 -right-2 inline-flex items-center justify-center min-w-[16px] h-4 px-1 rounded-full text-[9px] font-black bg-amber-500 text-white"
                            >
                                {{ pendingFixedCount }}
                            </span>
                        </div>
                        <span :class="collapsed ? 'text-[11px] leading-tight font-bold truncate max-w-[70px]' : 'truncate font-bold flex-1'">Fijos</span>
                        <span
                            v-if="!collapsed && pendingFixedCount > 0"
                            class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-xs font-black bg-amber-500 text-white shrink-0 ml-auto"
                            title="Recepciones pendientes"
                        >
                            {{ pendingFixedCount }}
                        </span>
                    </Link>

                    <!-- 3. Cambios -->
                    <Link
                        :href="route('planta.cambios.index')"
                        @click="handleLinkClick"
                        :class="[
                            'group flex rounded-xl transition-colors duration-150 relative min-h-[48px]',
                            collapsed
                                ? 'flex-col items-center justify-center py-2 px-1 text-center w-full'
                                : 'items-center gap-3 px-3.5 py-2.5 text-sm',
                            isRouteActive('planta.cambios.*')
                                ? 'bg-blue-50/80 text-blue-900 font-bold shadow-2xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                        ]"
                        title="Cambios"
                        aria-label="Cambios"
                    >
                        <span
                            v-if="!collapsed && isRouteActive('planta.cambios.*')"
                            class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"
                        ></span>
                        <div class="text-center shrink-0 flex items-center justify-center" :class="collapsed ? 'h-6 w-6 mb-0.5' : 'w-5 text-sm'">
                            <i class="fa-solid fa-arrows-rotate text-base"></i>
                        </div>
                        <span :class="collapsed ? 'text-[11px] leading-tight font-bold truncate max-w-[70px]' : 'truncate font-bold'">Cambios</span>
                    </Link>

                    <!-- Grupo: Más (Existencias, Incidencias, Historial) -->
                    <div class="pt-2 border-t border-slate-200/80">
                        <div v-if="!collapsed" class="px-3 mb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
                            Más
                        </div>
                        <div v-else class="w-6 mx-auto my-1.5 border-t border-slate-200" title="Más opciones"></div>

                        <nav class="space-y-1">
                            <!-- Existencias -->
                            <Link
                                :href="route('planta.inventory.index')"
                                @click="handleLinkClick"
                                :class="[
                                    'group flex items-center rounded-xl text-xs font-semibold transition-colors duration-150 relative min-h-[40px]',
                                    collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5 py-2',
                                    isRouteActive('planta.inventory.*')
                                        ? 'bg-blue-50/80 text-blue-900 font-bold shadow-2xs'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'
                                ]"
                                title="Existencias"
                            >
                                <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                    <i class="fa-solid fa-warehouse text-xs"></i>
                                </div>
                                <span v-if="!collapsed" class="truncate">Existencias</span>
                            </Link>

                            <!-- Incidencias -->
                            <Link
                                :href="route('planta.inventory.index', { tab: 'incidencias' })"
                                @click="handleLinkClick"
                                :class="[
                                    'group flex items-center rounded-xl text-xs font-semibold transition-colors duration-150 relative min-h-[40px]',
                                    collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5 py-2',
                                    'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'
                                ]"
                                title="Incidencias"
                            >
                                <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                    <i class="fa-solid fa-triangle-exclamation text-xs text-amber-500"></i>
                                </div>
                                <span v-if="!collapsed" class="truncate">Incidencias</span>
                            </Link>

                            <!-- Historial -->
                            <Link
                                :href="route('planta.inventory.index', { tab: 'historial' })"
                                @click="handleLinkClick"
                                :class="[
                                    'group flex items-center rounded-xl text-xs font-semibold transition-colors duration-150 relative min-h-[40px]',
                                    collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5 py-2',
                                    'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'
                                ]"
                                title="Historial"
                            >
                                <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                    <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                                </div>
                                <span v-if="!collapsed" class="truncate">Historial</span>
                            </Link>
                        </nav>
                    </div>
                </nav>
            </template>

            <!-- ============================================================= -->
            <!-- NAVEGACIÓN PARA OTROS ROLES (ADMIN, PLANTA)                    -->
            <!-- ============================================================= -->
            <template v-else>
                <!-- Principal (Dashboard) -->
                <div>
                    <nav class="space-y-1">
                        <Link
                            :href="route('dashboard')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isTopDashboardActive
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Dashboard' : ''"
                            aria-label="Dashboard"
                        >
                            <span
                                v-if="isTopDashboardActive"
                                class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"
                            ></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-house"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Dashboard</span>
                        </Link>
                    </nav>
                </div>

                <!-- Administración (Usuarios, Clientes, Configuración) -->
                <div v-if="isAdmin">
                    <div v-if="!collapsed" class="px-3 mb-1.5 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
                        Administración
                    </div>
                    <div v-else class="w-6 mx-auto my-2 border-t border-slate-200" title="Administración"></div>

                    <nav class="space-y-1">
                        <Link
                            :href="route('admin.users.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('admin.users.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Usuarios' : ''"
                        >
                            <span v-if="isRouteActive('admin.users.*')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Usuarios</span>
                        </Link>

                        <Link
                            :href="route('admin.customers.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('admin.customers.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Clientes' : ''"
                        >
                            <span v-if="isRouteActive('admin.customers.*')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-regular fa-user"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Clientes</span>
                        </Link>

                        <Link
                            v-if="isAdmin"
                            :href="route('admin.settings.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('admin.settings.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Configuración General' : ''"
                        >
                            <span v-if="isRouteActive('admin.settings.*')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-gear"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Configuración General</span>
                        </Link>
                    </nav>
                </div>

                <!-- Supervisión (Inventario de cambios) -->
                <div v-if="isAdmin">
                    <div v-if="!collapsed" class="px-3 mb-1.5 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
                        Supervisión
                    </div>
                    <div v-else class="w-6 mx-auto my-2 border-t border-slate-200" title="Supervisión"></div>

                    <nav class="space-y-1">
                        <Link
                            :href="getRouteUrl('admin.exchange-inventory.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('admin.exchange-inventory.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Inventario de cambios' : ''"
                        >
                            <span v-if="isRouteActive('admin.exchange-inventory.*')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-bottle-water"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Inventario de cambios</span>
                        </Link>
                    </nav>
                </div>
            </template>

        </div>
    </aside>
</template>
