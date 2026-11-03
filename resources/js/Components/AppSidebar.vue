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
const isAdministrador = computed(() => userRoles.value.includes('Administrador'));
const isCliente = computed(() => userRoles.value.includes('Cliente'));
const isRepartidor = computed(() => userRoles.value.includes('Repartidor'));
const isPlanta = computed(() => userRoles.value.includes('Operador Planta'));

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
            <!-- MODO CONTRAÍDO: Icono de logo o botón para expandir -->
            <button
                v-if="collapsed"
                @click="$emit('toggle')"
                type="button"
                class="hidden lg:flex w-10 h-10 rounded-xl bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-blue-900 items-center justify-center transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-blue-900/20 cursor-pointer"
                title="Expandir menú"
                aria-label="Expandir menú"
            >
                <i class="fa-solid fa-bars text-base"></i>
            </button>

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
            <!-- NAVEGACIÓN PARA OTROS ROLES (ADMIN, REPARTIDOR, PLANTA)        -->
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
                            v-if="isAdministrador"
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

                <!-- Operaciones (Pedidos, Reparto, Planta) -->
                <div v-if="isAdmin || isRepartidor || isPlanta">
                    <div v-if="!collapsed" class="px-3 mb-1.5 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
                        Operaciones
                    </div>
                    <div v-else class="w-6 mx-auto my-2 border-t border-slate-200" title="Operaciones"></div>

                    <nav class="space-y-1">
                        <Link
                            v-if="isAdmin"
                            :href="getRouteUrl('admin.orders.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('admin.orders.*') || isRouteActive('orders.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Pedidos' : ''"
                        >
                            <span v-if="isRouteActive('admin.orders.*')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-regular fa-file-lines"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Pedidos</span>
                        </Link>

                        <!-- Gestión de Rutas (Administrador) -->
                        <Link
                            v-if="isAdmin"
                            :href="getRouteUrl('admin.routes.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('admin.routes.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Rutas' : ''"
                        >
                            <span v-if="isRouteActive('admin.routes.*')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-route"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Rutas</span>
                        </Link>

                        <Link
                            v-if="isAdmin || isRepartidor"
                            :href="getRouteUrl('repartidor.reparto')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('repartidor.reparto')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Reparto' : ''"
                        >
                            <span v-if="isRouteActive('repartidor.reparto')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-truck"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate font-semibold">Reparto</span>
                        </Link>

                        <!-- Mi Ruta (Repartidor) -->
                        <Link
                            v-if="isAdmin || isRepartidor"
                            :href="getRouteUrl('repartidor.route.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('repartidor.route.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Mi Ruta' : ''"
                        >
                            <span v-if="isRouteActive('repartidor.route.*')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-map-location-dot"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Mi Ruta</span>
                        </Link>

                        <!-- Por Recoger (Repartidor) -->
                        <Link
                            v-if="isAdmin || isRepartidor"
                            :href="getRouteUrl('repartidor.collections.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('repartidor.collections.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Por Recoger' : ''"
                        >
                            <span v-if="isRouteActive('repartidor.collections.*')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-hand-holding-hand"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Por Recoger</span>
                        </Link>

                        <!-- Por Entregar (Repartidor) -->
                        <Link
                            v-if="isAdmin || isRepartidor"
                            :href="getRouteUrl('repartidor.deliveries.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('repartidor.deliveries.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Por Entregar' : ''"
                        >
                            <span v-if="isRouteActive('repartidor.deliveries.*')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-truck-ramp-box"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Por Entregar</span>
                        </Link>

                        <!-- Cobrar Pendientes (Repartidor) -->
                        <Link
                            v-if="isAdmin || isRepartidor"
                            :href="getRouteUrl('repartidor.debt.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('repartidor.debt.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Cobrar Pendientes' : ''"
                        >
                            <span v-if="isRouteActive('repartidor.debt.*')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-hand-holding-dollar"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Cobrar Pendientes</span>
                        </Link>

                        <Link
                            v-if="isAdmin || isRepartidor"
                            :href="getRouteUrl('repartidor.truck-load.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('repartidor.truck-load.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Mi Carga' : ''"
                        >
                            <span v-if="isRouteActive('repartidor.truck-load.*')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-boxes-stacked"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Mi Carga</span>
                        </Link>

                        <Link
                            v-if="isAdmin || isRepartidor"
                            :href="getRouteUrl('repartidor.customers.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('repartidor.customers.index')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Clientes' : ''"
                        >
                            <span v-if="isRouteActive('repartidor.customers.index')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Clientes</span>
                        </Link>

                        <Link
                            v-if="isAdmin || isRepartidor"
                            :href="getRouteUrl('repartidor.customers.create')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('repartidor.customers.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Agregar Cliente' : ''"
                        >
                            <span v-if="isRouteActive('repartidor.customers.*')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-user-plus"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Agregar Cliente</span>
                        </Link>

                        <Link
                            v-if="isAdmin || isPlanta"
                            :href="getRouteUrl('planta.dashboard')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('planta.dashboard')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Planta y Llenado' : ''"
                        >
                            <span v-if="isRouteActive('planta.dashboard')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-industry"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Planta y Llenado</span>
                        </Link>

                        <!-- Por Recibir (Planta) -->
                        <Link
                            v-if="isAdmin || isPlanta"
                            :href="getRouteUrl('planta.reception.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('planta.reception.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Por Recibir' : ''"
                        >
                            <span v-if="isRouteActive('planta.reception.*')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-dolly"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Por Recibir</span>
                        </Link>

                        <!-- Lavado y Llenado (Planta) -->
                        <Link
                            v-if="isAdmin || isPlanta"
                            :href="getRouteUrl('planta.production.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('planta.production.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Lavado y Llenado' : ''"
                        >
                            <span v-if="isRouteActive('planta.production.*')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-soap"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Lavado y Llenado</span>
                        </Link>

                        <!-- Cargas al Repartidor (Planta) -->
                        <Link
                            v-if="isAdmin || isPlanta"
                            :href="getRouteUrl('planta.loads.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('planta.loads.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Cargas al Repartidor' : ''"
                        >
                            <span v-if="isRouteActive('planta.loads.*')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-truck-ramp-box"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Cargas a Ruta</span>
                        </Link>

                        <!-- Existencias Planta -->
                        <Link
                            v-if="isAdmin || isPlanta"
                            :href="getRouteUrl('planta.inventory.index')"
                            @click="handleLinkClick"
                            :class="[
                                'group flex items-center rounded-xl text-sm transition-colors duration-150 relative h-11 min-h-[44px]',
                                collapsed ? 'justify-center w-11 mx-auto' : 'gap-3 px-3.5',
                                isRouteActive('planta.inventory.*')
                                    ? 'bg-blue-50/80 text-blue-900 font-semibold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium'
                            ]"
                            :title="collapsed ? 'Existencias' : ''"
                        >
                            <span v-if="isRouteActive('planta.inventory.*')" class="absolute left-0 top-2 bottom-2 w-0.5 bg-blue-900 rounded-r-full"></span>
                            <div class="w-5 text-center text-sm shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-boxes-stacked"></i>
                            </div>
                            <span v-if="!collapsed" class="truncate">Existencias</span>
                        </Link>
                    </nav>
                </div>
            </template>

        </div>
    </aside>
</template>
