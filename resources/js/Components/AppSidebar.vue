<script setup>
import { computed } from 'vue';
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

defineEmits(['close', 'toggle']);

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const userRoles = computed(() => user.value.roles || []);
const isAdmin = computed(() => userRoles.value.includes('Administrador') || userRoles.value.includes('Super Admin'));
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
</script>

<template>
    <!-- ========================================================================= -->
    <!-- OVERLAY MÓVIL (BACKDROP) -->
    <!-- ========================================================================= -->
    <transition
        enter-active-class="transition-opacity duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="mobileOpen"
            class="fixed inset-0 bg-slate-900/60 z-40 lg:hidden"
            @click="$emit('close')"
            aria-hidden="true"
        />
    </transition>

    <!-- ========================================================================= -->
    <!-- SIDEBAR CONTENEDOR (EXPANDIBLE / COLAPSABLE EN ESCRITORIO) -->
    <!-- ========================================================================= -->
    <aside
        class="fixed inset-y-0 left-0 z-40 flex flex-col bg-white border-r border-slate-100 select-none lg:translate-x-0 transition-all duration-300 ease-in-out"
        :class="[
            mobileOpen ? 'translate-x-0 shadow-2xl w-64' : '-translate-x-full lg:translate-x-0',
            collapsed ? 'lg:w-20' : 'lg:w-64'
        ]"
        aria-label="Menú principal de navegación"
    >
        <!-- 1. CABECERA: Logotipo y botón de colapso/expansión dentro del sidebar -->
        <div class="h-16 flex items-center border-b border-slate-100 shrink-0 bg-white" :class="collapsed ? 'justify-center px-3' : 'justify-between px-5'">
            <!-- MODO COLAPSADO (ESCRITORIO): Icono de menú para volver a abrir el sidebar -->
            <button
                v-if="collapsed"
                @click="$emit('toggle')"
                type="button"
                class="hidden lg:flex w-10 h-10 rounded-xl bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-[#0084ff] items-center justify-center transition shadow-xs focus:outline-none cursor-pointer"
                title="Abrir menú"
                aria-label="Abrir menú"
            >
                <i class="fa-solid fa-bars text-base"></i>
            </button>

            <!-- MODO EXPANDIDO: Logotipo completo y botón de colapsar -->
            <template v-else>
                <Link :href="route('dashboard')" class="flex items-center min-w-0 focus:outline-none">
                    <img
                        src="/images/logo.png"
                        alt="Puritronic"
                        class="h-8.5 w-auto max-w-[165px] object-contain shrink-0"
                    />
                </Link>

                <div class="flex items-center gap-1">
                    <!-- Botón para colapsar en escritorio dentro del sidebar -->
                    <button
                        @click="$emit('toggle')"
                        type="button"
                        class="hidden lg:flex h-9 w-9 rounded-xl items-center justify-center text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition focus:outline-none cursor-pointer"
                        title="Ocultar menú"
                        aria-label="Ocultar menú"
                    >
                        <i class="fa-solid fa-bars text-sm"></i>
                    </button>

                    <!-- Botón de cerrar visible únicamente en móviles -->
                    <button
                        v-if="mobileOpen"
                        @click="$emit('close')"
                        type="button"
                        class="lg:hidden h-8 w-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition focus:outline-none cursor-pointer"
                        title="Cerrar menú"
                        aria-label="Cerrar menú"
                    >
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>
            </template>
        </div>

        <!-- 2. NAVEGACIÓN PRINCIPAL -->
        <div class="flex-1 overflow-y-auto py-6" :class="collapsed ? 'px-2 space-y-4' : 'px-4 space-y-6'">

            <!-- ================= GRUPO: PRINCIPAL ================= -->
            <div>
                <div v-if="!collapsed" class="px-3 mb-2 text-[11px] font-bold tracking-wider text-slate-400 uppercase">
                    Principal
                </div>
                <div v-else class="w-6 mx-auto my-2 border-t border-slate-200/80" title="Principal"></div>

                <nav class="space-y-1">
                    <Link
                        :href="route('dashboard')"
                        :class="[
                            'group flex items-center rounded-xl text-sm transition-all duration-150 relative',
                            collapsed ? 'justify-center w-12 h-12 mx-auto' : 'gap-3.5 px-3.5 py-2.5',
                            isRouteActive('dashboard') || isRouteActive('admin.dashboard') || isRouteActive('cliente.dashboard') || isRouteActive('repartidor.dashboard') || isRouteActive('planta.dashboard')
                                ? 'bg-[#eaf4ff] text-[#0084ff] font-semibold'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium'
                        ]"
                        :title="collapsed ? 'Dashboard' : ''"
                    >
                        <span
                            v-if="isRouteActive('dashboard') || isRouteActive('admin.dashboard') || isRouteActive('cliente.dashboard') || isRouteActive('repartidor.dashboard') || isRouteActive('planta.dashboard')"
                            class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-[#0084ff] rounded-r-full"
                        ></span>
                        <i
                            class="fa-solid fa-house text-[15px] shrink-0 transition-colors"
                            :class="[
                                collapsed ? 'text-[17px]' : 'w-5 text-center',
                                isRouteActive('dashboard') || isRouteActive('admin.dashboard') || isRouteActive('cliente.dashboard') || isRouteActive('repartidor.dashboard') || isRouteActive('planta.dashboard')
                                    ? 'text-[#0084ff]'
                                    : 'text-slate-500 group-hover:text-slate-800'
                            ]"
                        ></i>
                        <span v-if="!collapsed" class="truncate">Dashboard</span>
                    </Link>
                </nav>
            </div>

            <!-- ================= GRUPO: ADMINISTRACIÓN ================= -->
            <div v-if="isAdmin">
                <div v-if="!collapsed" class="px-3 mb-2 text-[11px] font-bold tracking-wider text-slate-400 uppercase">
                    Administración
                </div>
                <div v-else class="w-6 mx-auto my-2 border-t border-slate-200/80" title="Administración"></div>

                <nav class="space-y-1">
                    <!-- Usuarios -->
                    <Link
                        :href="route('admin.users.index')"
                        :class="[
                            'group flex items-center rounded-xl text-sm transition-all duration-150 relative',
                            collapsed ? 'justify-center w-12 h-12 mx-auto' : 'gap-3.5 px-3.5 py-2.5',
                            isRouteActive('admin.users.*')
                                ? 'bg-[#eaf4ff] text-[#0084ff] font-semibold'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium'
                        ]"
                        :title="collapsed ? 'Usuarios' : ''"
                    >
                        <span
                            v-if="isRouteActive('admin.users.*')"
                            class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-[#0084ff] rounded-r-full"
                        ></span>
                        <i
                            class="fa-solid fa-users text-[15px] shrink-0 transition-colors"
                            :class="[
                                collapsed ? 'text-[17px]' : 'w-5 text-center',
                                isRouteActive('admin.users.*') ? 'text-[#0084ff]' : 'text-slate-500 group-hover:text-slate-800'
                            ]"
                        ></i>
                        <span v-if="!collapsed" class="truncate">Usuarios</span>
                    </Link>

                    <!-- Clientes -->
                    <Link
                        :href="route('admin.customers.index')"
                        :class="[
                            'group flex items-center rounded-xl text-sm transition-all duration-150 relative',
                            collapsed ? 'justify-center w-12 h-12 mx-auto' : 'gap-3.5 px-3.5 py-2.5',
                            isRouteActive('admin.customers.*')
                                ? 'bg-[#eaf4ff] text-[#0084ff] font-semibold'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium'
                        ]"
                        :title="collapsed ? 'Clientes' : ''"
                    >
                        <span
                            v-if="isRouteActive('admin.customers.*')"
                            class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-[#0084ff] rounded-r-full"
                        ></span>
                        <i
                            class="fa-regular fa-user text-[15px] shrink-0 transition-colors"
                            :class="[
                                collapsed ? 'text-[17px]' : 'w-5 text-center',
                                isRouteActive('admin.customers.*') ? 'text-[#0084ff]' : 'text-slate-500 group-hover:text-slate-800'
                            ]"
                        ></i>
                        <span v-if="!collapsed" class="truncate">Clientes</span>
                    </Link>
                </nav>
            </div>

            <!-- ================= GRUPO: OPERACIONES ================= -->
            <div v-if="isAdmin || isRepartidor || isPlanta">
                <div v-if="!collapsed" class="px-3 mb-2 text-[11px] font-bold tracking-wider text-slate-400 uppercase">
                    Operaciones
                </div>
                <div v-else class="w-6 mx-auto my-2 border-t border-slate-200/80" title="Operaciones"></div>

                <nav class="space-y-1">
                    <!-- Pedidos -->
                    <Link
                        v-if="isAdmin"
                        :href="getRouteUrl('admin.orders.index')"
                        :class="[
                            'group flex items-center rounded-xl text-sm transition-all duration-150 relative',
                            collapsed ? 'justify-center w-12 h-12 mx-auto' : 'gap-3.5 px-3.5 py-2.5',
                            isRouteActive('admin.orders.*') || isRouteActive('orders.*')
                                ? 'bg-[#eaf4ff] text-[#0084ff] font-semibold'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium'
                        ]"
                        :title="collapsed ? 'Pedidos' : ''"
                    >
                        <span
                            v-if="isRouteActive('admin.orders.*')"
                            class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-[#0084ff] rounded-r-full"
                        ></span>
                        <i
                            class="fa-regular fa-file-lines text-[15px] shrink-0 transition-colors"
                            :class="[
                                collapsed ? 'text-[17px]' : 'w-5 text-center',
                                isRouteActive('admin.orders.*') ? 'text-[#0084ff]' : 'text-slate-500 group-hover:text-slate-800'
                            ]"
                        ></i>
                        <span v-if="!collapsed" class="truncate">Pedidos</span>
                    </Link>

                    <!-- Reparto -->
                    <Link
                        v-if="isAdmin || isRepartidor"
                        :href="getRouteUrl('repartidor.dashboard')"
                        :class="[
                            'group flex items-center rounded-xl text-sm transition-all duration-150 relative',
                            collapsed ? 'justify-center w-12 h-12 mx-auto' : 'gap-3.5 px-3.5 py-2.5',
                            isRouteActive('repartidor.*')
                                ? 'bg-[#eaf4ff] text-[#0084ff] font-semibold'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium'
                        ]"
                        :title="collapsed ? 'Reparto' : ''"
                    >
                        <span
                            v-if="isRouteActive('repartidor.*')"
                            class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-[#0084ff] rounded-r-full"
                        ></span>
                        <i
                            class="fa-solid fa-truck text-[15px] shrink-0 transition-colors"
                            :class="[
                                collapsed ? 'text-[17px]' : 'w-5 text-center',
                                isRouteActive('repartidor.*') ? 'text-[#0084ff]' : 'text-slate-500 group-hover:text-slate-800'
                            ]"
                        ></i>
                        <span v-if="!collapsed" class="truncate">Reparto</span>
                    </Link>

                    <!-- Planta -->
                    <Link
                        v-if="isAdmin || isPlanta"
                        :href="getRouteUrl('planta.dashboard')"
                        :class="[
                            'group flex items-center rounded-xl text-sm transition-all duration-150 relative',
                            collapsed ? 'justify-center w-12 h-12 mx-auto' : 'gap-3.5 px-3.5 py-2.5',
                            isRouteActive('planta.*')
                                ? 'bg-[#eaf4ff] text-[#0084ff] font-semibold'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium'
                        ]"
                        :title="collapsed ? 'Planta' : ''"
                    >
                        <span
                            v-if="isRouteActive('planta.*')"
                            class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-[#0084ff] rounded-r-full"
                        ></span>
                        <i
                            class="fa-solid fa-gear text-[15px] shrink-0 transition-colors"
                            :class="[
                                collapsed ? 'text-[17px]' : 'w-5 text-center',
                                isRouteActive('planta.*') ? 'text-[#0084ff]' : 'text-slate-500 group-hover:text-slate-800'
                            ]"
                        ></i>
                        <span v-if="!collapsed" class="truncate">Planta</span>
                    </Link>
                </nav>
            </div>

            <!-- ================= GRUPO: INVENTARIO ================= -->
            <div v-if="isAdmin">
                <div v-if="!collapsed" class="px-3 mb-2 text-[11px] font-bold tracking-wider text-slate-400 uppercase">
                    Inventario
                </div>
                <div v-else class="w-6 mx-auto my-2 border-t border-slate-200/80" title="Inventario"></div>

                <nav class="space-y-1">
                    <!-- Insumos -->
                    <Link
                        :href="getRouteUrl('admin.supplies.index')"
                        :class="[
                            'group flex items-center rounded-xl text-sm transition-all duration-150 relative',
                            collapsed ? 'justify-center w-12 h-12 mx-auto' : 'gap-3.5 px-3.5 py-2.5',
                            isRouteActive('admin.supplies.*') || isRouteActive('supplies.*')
                                ? 'bg-[#eaf4ff] text-[#0084ff] font-semibold'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium'
                        ]"
                        :title="collapsed ? 'Insumos' : ''"
                    >
                        <span
                            v-if="isRouteActive('admin.supplies.*')"
                            class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-[#0084ff] rounded-r-full"
                        ></span>
                        <i
                            class="fa-solid fa-box-open text-[15px] shrink-0 transition-colors"
                            :class="[
                                collapsed ? 'text-[17px]' : 'w-5 text-center',
                                isRouteActive('admin.supplies.*') ? 'text-[#0084ff]' : 'text-slate-500 group-hover:text-slate-800'
                            ]"
                        ></i>
                        <span v-if="!collapsed" class="truncate">Insumos</span>
                    </Link>
                </nav>
            </div>

            <!-- ================= GRUPO: REPORTES ================= -->
            <div v-if="isAdmin">
                <div v-if="!collapsed" class="px-3 mb-2 text-[11px] font-bold tracking-wider text-slate-400 uppercase">
                    Reportes
                </div>
                <div v-else class="w-6 mx-auto my-2 border-t border-slate-200/80" title="Reportes"></div>

                <nav class="space-y-1">
                    <!-- Reportes -->
                    <Link
                        :href="getRouteUrl('admin.reports.index')"
                        :class="[
                            'group flex items-center rounded-xl text-sm transition-all duration-150 relative',
                            collapsed ? 'justify-center w-12 h-12 mx-auto' : 'gap-3.5 px-3.5 py-2.5',
                            isRouteActive('admin.reports.*') || isRouteActive('reports.*')
                                ? 'bg-[#eaf4ff] text-[#0084ff] font-semibold'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium'
                        ]"
                        :title="collapsed ? 'Reportes' : ''"
                    >
                        <span
                            v-if="isRouteActive('admin.reports.*')"
                            class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-[#0084ff] rounded-r-full"
                        ></span>
                        <i
                            class="fa-solid fa-chart-simple text-[15px] shrink-0 transition-colors"
                            :class="[
                                collapsed ? 'text-[17px]' : 'w-5 text-center',
                                isRouteActive('admin.reports.*') ? 'text-[#0084ff]' : 'text-slate-500 group-hover:text-slate-800'
                            ]"
                        ></i>
                        <span v-if="!collapsed" class="truncate">Reportes</span>
                    </Link>
                </nav>
            </div>

            <!-- ================= GRUPO: MI CUENTA (Para Clientes) ================= -->
            <div v-if="isCliente">
                <div v-if="!collapsed" class="px-3 mb-2 text-[11px] font-bold tracking-wider text-slate-400 uppercase">
                    Mi Cuenta
                </div>
                <div v-else class="w-6 mx-auto my-2 border-t border-slate-200/80" title="Mi Cuenta"></div>

                <nav class="space-y-1">
                    <!-- Domicilio -->
                    <Link
                        :href="route('cliente.addresses.index')"
                        :class="[
                            'group flex items-center rounded-xl text-sm transition-all duration-150 relative',
                            collapsed ? 'justify-center w-12 h-12 mx-auto' : 'gap-3.5 px-3.5 py-2.5',
                            isRouteActive('cliente.addresses.*')
                                ? 'bg-[#eaf4ff] text-[#0084ff] font-semibold'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium'
                        ]"
                        :title="collapsed ? 'Mi domicilio' : ''"
                    >
                        <span
                            v-if="isRouteActive('cliente.addresses.*')"
                            class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-[#0084ff] rounded-r-full"
                        ></span>
                        <i
                            class="fa-solid fa-house-chimney text-[15px] shrink-0 transition-colors"
                            :class="[
                                collapsed ? 'text-[17px]' : 'w-5 text-center',
                                isRouteActive('cliente.addresses.*') ? 'text-[#0084ff]' : 'text-slate-500 group-hover:text-slate-800'
                            ]"
                        ></i>
                        <span v-if="!collapsed" class="truncate">Mi domicilio</span>
                    </Link>

                    <!-- Perfil -->
                    <Link
                        :href="route('profile.edit')"
                        :class="[
                            'group flex items-center rounded-xl text-sm transition-all duration-150 relative',
                            collapsed ? 'justify-center w-12 h-12 mx-auto' : 'gap-3.5 px-3.5 py-2.5',
                            isRouteActive('profile.edit') || isRouteActive('cliente.profile.*')
                                ? 'bg-[#eaf4ff] text-[#0084ff] font-semibold'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium'
                        ]"
                        :title="collapsed ? 'Mi perfil' : ''"
                    >
                        <span
                            v-if="isRouteActive('profile.edit') || isRouteActive('cliente.profile.*')"
                            class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-[#0084ff] rounded-r-full"
                        ></span>
                        <i
                            class="fa-regular fa-user text-[15px] shrink-0 transition-colors"
                            :class="[
                                collapsed ? 'text-[17px]' : 'w-5 text-center',
                                isRouteActive('profile.edit') || isRouteActive('cliente.profile.*') ? 'text-[#0084ff]' : 'text-slate-500 group-hover:text-slate-800'
                            ]"
                        ></i>
                        <span v-if="!collapsed" class="truncate">Mi perfil</span>
                    </Link>
                </nav>
            </div>

        </div>
    </aside>
</template>
