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

const emit = defineEmits(['close']);

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

const getRouteUrl = (name, params = undefined) => {
    try {
        if (name && route().has(name)) {
            return route(name, params);
        }
    } catch (e) {
        // fallback
    }
    return '#';
};

const currentUrlTab = computed(() => {
    const url = page.url || '';
    const queryIndex = url.indexOf('?');
    if (queryIndex === -1) return null;
    const params = new URLSearchParams(url.slice(queryIndex + 1));
    return params.get('tab');
});

const userInitials = computed(() => {
    const name = user.value.name || '';
    if (!name.trim()) return 'U';
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return parts[0].slice(0, 2).toUpperCase();
});

const roleBadgeInfo = computed(() => {
    if (isAdmin.value) {
        return {
            label: userRoles.value.includes('Super Admin') ? 'Super Admin' : 'Administrador',
            icon: 'fa-solid fa-shield-halved',
            dotClass: 'bg-[#0071BA]',
            avatarClass: 'bg-[#2E2363] text-white',
        };
    }
    if (isRepartidor.value) {
        return {
            label: 'Repartidor',
            icon: 'fa-solid fa-truck-fast',
            dotClass: 'bg-[#0071BA]',
            avatarClass: 'bg-[#2E2363] text-white',
        };
    }
    if (isPlanta.value) {
        return {
            label: 'Operador Planta',
            icon: 'fa-solid fa-industry',
            dotClass: 'bg-[#34785A]',
            avatarClass: 'bg-[#2E2363] text-white',
        };
    }
    return {
        label: 'Portal Cliente',
        icon: 'fa-solid fa-droplet',
        dotClass: 'bg-[#0071BA]',
        avatarClass: 'bg-[#2E2363] text-white',
    };
});

const navGroups = computed(() => {
    if (isCliente.value) {
        return [
            {
                id: 'cliente-main',
                label: 'Principal',
                items: [
                    {
                        label: 'Inicio',
                        href: getRouteUrl('dashboard'),
                        icon: 'fa-solid fa-house',
                        active: isRouteActive('dashboard') || isRouteActive('cliente.dashboard'),
                    },
                ],
            },
            {
                id: 'cliente-orders',
                label: 'Pedidos',
                items: [
                    {
                        label: 'Mis pedidos',
                        href: getRouteUrl('cliente.orders.index'),
                        icon: 'fa-regular fa-file-lines',
                        active: isRouteActive('cliente.orders.index') || isRouteActive('cliente.orders.show'),
                    },
                    {
                        label: 'Nuevo pedido',
                        href: getRouteUrl('cliente.orders.create'),
                        icon: 'fa-solid fa-cart-plus',
                        active: isRouteActive('cliente.orders.create'),
                    },
                ],
            },
            {
                id: 'cliente-account',
                label: 'Mi cuenta',
                items: [
                    {
                        label: 'Mi domicilio',
                        href: getRouteUrl('cliente.addresses.index'),
                        icon: 'fa-solid fa-house-chimney',
                        active: isRouteActive('cliente.addresses.*'),
                    },
                    {
                        label: 'Mi perfil',
                        href: getRouteUrl('profile.edit'),
                        icon: 'fa-regular fa-user',
                        active: isRouteActive('profile.edit') || isRouteActive('cliente.profile.*'),
                    },
                ],
            },
        ];
    }

    if (isRepartidor.value && !isAdmin.value) {
        return [
            {
                id: 'repartidor-main',
                label: 'Operación en ruta',
                items: [
                    {
                        label: 'Reparto',
                        href: getRouteUrl('repartidor.reparto'),
                        icon: 'fa-solid fa-truck',
                        active:
                            isRouteActive('repartidor.reparto') ||
                            isRouteActive('repartidor.dashboard') ||
                            isRouteActive('repartidor.stops.*') ||
                            isRouteActive('repartidor.direct-service.*') ||
                            isRouteActive('repartidor.route.*') ||
                            isRouteActive('repartidor.collections.*') ||
                            isRouteActive('repartidor.deliveries.*') ||
                            isRouteActive('repartidor.ready-to-load.*'),
                    },
                    {
                        label: 'Mi carga',
                        href: getRouteUrl('repartidor.truck-load.index'),
                        icon: 'fa-solid fa-boxes-stacked',
                        active: isRouteActive('repartidor.truck-load.*'),
                    },
                    {
                        label: 'Cobros',
                        href: getRouteUrl('repartidor.debt.index'),
                        icon: 'fa-solid fa-hand-holding-dollar',
                        active: isRouteActive('repartidor.debt.*'),
                    },
                    {
                        label: 'Clientes',
                        href: getRouteUrl('repartidor.customers.index'),
                        icon: 'fa-solid fa-users',
                        active: isRouteActive('repartidor.customers.*'),
                    },
                ],
            },
        ];
    }

    if (isPlanta.value && !isAdmin.value) {
        const inInventory = isRouteActive('planta.inventory.*');
        return [
            {
                id: 'planta-main',
                label: 'Operación Planta',
                items: [
                    {
                        label: 'Llenado rápido',
                        href: getRouteUrl('planta.quick-refill.index'),
                        icon: 'fa-solid fa-bolt',
                        active: isRouteActive('planta.quick-refill.*') || isRouteActive('planta.dashboard'),
                    },
                    {
                        label: 'Fijos',
                        href: getRouteUrl('planta.fijos.index'),
                        icon: 'fa-solid fa-boxes-stacked',
                        badge: pendingFixedCount.value > 0 ? pendingFixedCount.value : null,
                        badgeTitle: 'Recepciones pendientes',
                        active:
                            isRouteActive('planta.fijos.*') ||
                            isRouteActive('planta.reception.*') ||
                            isRouteActive('planta.production.*') ||
                            isRouteActive('planta.loads.*'),
                    },
                    {
                        label: 'Cambios',
                        href: getRouteUrl('planta.cambios.index'),
                        icon: 'fa-solid fa-arrows-rotate',
                        active: isRouteActive('planta.cambios.*'),
                    },
                ],
            },
            {
                id: 'planta-more',
                label: 'Control y Almacén',
                items: [
                    {
                        label: 'Existencias',
                        href: getRouteUrl('planta.inventory.index'),
                        icon: 'fa-solid fa-warehouse',
                        active: inInventory && !['incidencias', 'historial'].includes(currentUrlTab.value),
                    },
                    {
                        label: 'Incidencias',
                        href: getRouteUrl('planta.inventory.index', { tab: 'incidencias' }),
                        icon: 'fa-solid fa-triangle-exclamation',
                        iconTint: 'text-[#F8F0DF]',
                        active: inInventory && currentUrlTab.value === 'incidencias',
                    },
                    {
                        label: 'Historial',
                        href: getRouteUrl('planta.inventory.index', { tab: 'historial' }),
                        icon: 'fa-solid fa-clock-rotate-left',
                        active: inInventory && currentUrlTab.value === 'historial',
                    },
                ],
            },
        ];
    }

    // Administrador / Super Admin / Otros roles
    const groups = [
        {
            id: 'admin-main',
            label: 'Principal',
            items: [
                {
                    label: 'Dashboard',
                    href: getRouteUrl('dashboard'),
                    icon: 'fa-solid fa-house',
                    active: isRouteActive('admin.dashboard') || isRouteActive('dashboard'),
                },
            ],
        },
    ];

    if (isAdmin.value) {
        groups.push(
            {
                id: 'admin-manage',
                label: 'Administración',
                items: [
                    {
                        label: 'Pedidos',
                        href: getRouteUrl('admin.orders.index'),
                        icon: 'fa-solid fa-clipboard-list',
                        active: isRouteActive('admin.orders.*'),
                    },
                    {
                        label: 'Clientes',
                        href: getRouteUrl('admin.customers.index'),
                        icon: 'fa-regular fa-user',
                        active: isRouteActive('admin.customers.*'),
                    },
                    {
                        label: 'Usuarios',
                        href: getRouteUrl('admin.users.index'),
                        icon: 'fa-solid fa-users',
                        active: isRouteActive('admin.users.*'),
                    },
                    {
                        label: 'Configuración General',
                        href: getRouteUrl('admin.settings.index'),
                        icon: 'fa-solid fa-gear',
                        active: isRouteActive('admin.settings.*'),
                    },
                ],
            },
            {
                id: 'admin-supervision',
                label: 'Supervisión',
                items: [
                    {
                        label: 'Inventario de cambios',
                        href: getRouteUrl('admin.exchange-inventory.index'),
                        icon: 'fa-solid fa-bottle-water',
                        active: isRouteActive('admin.exchange-inventory.*'),
                    },
                ],
            }
        );
    }

    return groups;
});

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
    <!-- Overlay móvil suave -->
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
            class="fixed inset-0 bg-[#1E1545]/40 backdrop-blur-[2px] z-40 lg:hidden"
            @click="$emit('close')"
            aria-hidden="true"
        />
    </transition>

    <!-- Sidebar con identidad corporativa morado profundo (#1E1545) -->
    <aside
        class="fixed top-0 bottom-0 left-0 z-40 min-h-screen h-[100dvh] flex flex-col bg-[#1E1545] border-r border-[#2E2363] select-none transition-[width,transform] duration-300 ease-in-out"
        :class="[
            mobileOpen
                ? 'translate-x-0 shadow-[8px_0_32px_-8px_rgba(30,21,69,0.28)] w-64'
                : '-translate-x-full lg:translate-x-0',
            collapsed ? 'lg:w-20' : 'lg:w-64'
        ]"
        aria-label="Menú principal de navegación"
    >
        <!-- Cabecera de Marca -->
        <div
            class="h-16 flex items-center border-b border-white/10 shrink-0"
            :class="collapsed ? 'justify-center px-3' : 'justify-between px-5'"
        >
            <!-- Modo contraído: Isotipo limpio -->
            <Link
                v-if="collapsed"
                :href="route('dashboard')"
                @click="handleLinkClick"
                class="hidden lg:flex w-10 h-10 rounded-xl bg-white/10 text-white hover:bg-[#0071BA] items-center justify-center transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-white/30"
                title="Puritronic - Ir al inicio"
                aria-label="Puritronic"
            >
                <i class="fa-solid fa-droplet text-base"></i>
            </Link>

            <!-- Modo expandido: Logotipo oficial sobre fondo limpio para contraste -->
            <template v-else>
                <Link
                    :href="route('dashboard')"
                    @click="handleLinkClick"
                    class="flex items-center min-w-0 rounded-xl bg-white px-2.5 py-1 focus:outline-none focus:ring-2 focus:ring-white/35"
                >
                    <img
                        src="/images/logo.png"
                        alt="Puritronic"
                        class="h-7 w-auto max-w-[140px] object-contain shrink-0"
                    />
                </Link>

                <button
                    v-if="mobileOpen"
                    @click="$emit('close')"
                    type="button"
                    class="lg:hidden h-8 w-8 rounded-lg flex items-center justify-center text-[#DAD6E8] hover:text-white hover:bg-white/10 transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-white/25"
                    title="Cerrar menú"
                    aria-label="Cerrar menú"
                >
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </template>
        </div>

        <!-- Lista de Navegación -->
        <div
            class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden py-5"
            :class="collapsed ? 'px-2.5 space-y-4' : 'px-3.5 space-y-6'"
        >
            <div v-for="(group, gIdx) in navGroups" :key="group.id">
                <!-- Categoría de navegación -->
                <template v-if="group.label">
                    <div
                        v-if="!collapsed"
                        class="px-3 mb-2 text-[11px] font-semibold tracking-[0.04em] text-[#A6A3B2]"
                    >
                        {{ group.label }}
                    </div>
                    <div
                        v-else-if="gIdx > 0"
                        class="w-7 mx-auto my-2 border-t border-white/10"
                        :title="group.label"
                    ></div>
                </template>

                <!-- Enlaces -->
                <nav class="space-y-1">
                    <Link
                        v-for="item in group.items"
                        :key="item.label"
                        :href="item.href"
                        @click="handleLinkClick"
                        :title="collapsed ? item.label : ''"
                        :aria-label="item.label"
                        :aria-current="item.active ? 'page' : undefined"
                        :class="[
                            'group relative flex items-center rounded-xl text-[13.5px] transition-colors duration-150 min-h-[42px] focus:outline-none focus:ring-2 focus:ring-white/25',
                            collapsed ? 'justify-center w-11 h-11 mx-auto' : 'gap-3 px-3 py-2',
                            item.active
                                ? 'bg-white/[0.13] text-white font-semibold'
                                : 'text-[#DAD6E8] hover:text-white hover:bg-white/[0.06] font-medium'
                        ]"
                    >
                        <!-- Indicador sutil activo -->
                        <span
                            v-if="item.active && !collapsed"
                            class="absolute left-0 top-2.5 bottom-2.5 w-[3px] bg-[#E82147] rounded-r-full"
                        ></span>

                        <!-- Icono consistente -->
                        <div
                            :class="[
                                'relative flex items-center justify-center shrink-0 rounded-lg transition-colors duration-150',
                                collapsed ? 'w-9 h-9 text-sm' : 'w-7 h-7 text-[13px]',
                                item.active
                                    ? 'text-white'
                                    : 'group-hover:text-white',
                                !item.active && (item.iconTint || 'text-[#A6A3B2]')
                            ]"
                        >
                            <i :class="item.icon"></i>

                            <!-- Badge en modo contraído -->
                            <span
                                v-if="collapsed && item.badge"
                                class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-[16px] h-4 px-1 rounded-full text-[9px] font-bold bg-[#F8F0DF] text-[#A97827] ring-2 ring-[#1E1545]"
                            >
                                {{ item.badge }}
                            </span>
                        </div>

                        <!-- Etiqueta -->
                        <span v-if="!collapsed" class="truncate flex-1">
                            {{ item.label }}
                        </span>

                        <!-- Badge en modo expandido -->
                        <span
                            v-if="!collapsed && item.badge"
                            class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[11px] font-semibold bg-[#F8F0DF] text-[#A97827] border border-[#A97827]/25 shrink-0"
                            :title="item.badgeTitle"
                        >
                            {{ item.badge }}
                        </span>
                    </Link>
                </nav>
            </div>
        </div>

        <!-- Perfil del Operador / Usuario al pie del Sidebar -->
        <div class="shrink-0 border-t border-white/10 bg-[#19113B] p-3">
            <div
                v-if="!collapsed"
                class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl bg-white/[0.07] border border-white/10"
            >
                <div class="relative shrink-0">
                    <div
                        class="h-9 w-9 rounded-lg font-semibold text-xs flex items-center justify-center"
                        :class="roleBadgeInfo.avatarClass"
                    >
                        {{ userInitials }}
                    </div>
                    <span
                        class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-[#19113B]"
                        :class="roleBadgeInfo.dotClass"
                        title="Sesión activa"
                    ></span>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold text-white truncate leading-tight">
                        {{ user.name || 'Usuario' }}
                    </p>
                    <p class="text-[11px] font-medium text-[#DAD6E8] truncate mt-0.5">
                        {{ roleBadgeInfo.label }}
                    </p>
                </div>
            </div>

            <div v-else class="flex justify-center" :title="`${user.name || 'Usuario'} · ${roleBadgeInfo.label}`">
                <div class="relative">
                    <div
                        class="h-9 w-9 rounded-lg font-semibold text-xs flex items-center justify-center"
                        :class="roleBadgeInfo.avatarClass"
                    >
                        {{ userInitials }}
                    </div>
                    <span
                        class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-[#19113B]"
                        :class="roleBadgeInfo.dotClass"
                        title="Sesión activa"
                    ></span>
                </div>
            </div>
        </div>
    </aside>
</template>
