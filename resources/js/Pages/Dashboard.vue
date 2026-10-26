<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const modules = [
    {
        name: 'Gestión de Pedidos',
        description: 'Consulta, confirmación, registro de pedidos a domicilio y control de entregas.',
        route: 'admin.orders.index',
        icon: 'fa-solid fa-boxes-stacked',
        iconBg: 'bg-blue-50 text-blue-900 border-blue-200/80',
        badge: 'Operativo',
        badgeClass: 'bg-blue-100 text-blue-900',
    },
    {
        name: 'Control de Clientes',
        description: 'Directorio de clientes particulares y comerciales, botellones registrados y domicilios.',
        route: 'admin.customers.index',
        icon: 'fa-solid fa-users',
        iconBg: 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
        badge: 'Directorio',
        badgeClass: 'bg-emerald-100 text-emerald-800',
    },
    {
        name: 'Configuración General',
        description: 'Horario comercial, política de anticipación, catálogo de botellones y precios de recarga.',
        route: 'admin.settings.index',
        icon: 'fa-solid fa-sliders',
        iconBg: 'bg-indigo-50 text-indigo-700 border-indigo-200/80',
        badge: 'Sistema',
        badgeClass: 'bg-indigo-100 text-indigo-800',
    },
    {
        name: 'Usuarios y Accesos',
        description: 'Administración de personal, roles (repartidores, operadores) y estado de cuentas.',
        route: 'admin.users.index',
        icon: 'fa-solid fa-id-badge',
        iconBg: 'bg-purple-50 text-purple-700 border-purple-200/80',
        badge: 'Seguridad',
        badgeClass: 'bg-purple-100 text-purple-800',
    },
];
</script>

<template>
    <Head title="Panel de Administración - Puritronic" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <span class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-blue-900 bg-blue-50 px-2.5 py-1 rounded-full border border-blue-200/70 mb-1">
                        <i class="fa-solid fa-shield-halved text-[10px]"></i>
                        Administración
                    </span>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">
                        Panel de Control
                    </h1>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="route('admin.orders.create')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-900 hover:bg-blue-800 text-white text-xs sm:text-sm font-bold rounded-xl shadow-xs transition-all active:scale-[0.98]"
                    >
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Nuevo Pedido</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6 sm:space-y-8">
                
                <!-- Tarjeta de Bienvenida y Estado -->
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#0E1E42] via-[#142858] to-[#1a3575] text-white p-6 sm:p-8 shadow-md">
                    <div class="relative z-10 max-w-2xl space-y-2">
                        <span class="text-xs font-semibold uppercase tracking-widest text-blue-300">
                            Bienvenido de nuevo
                        </span>
                        <h2 class="text-xl sm:text-2xl font-bold tracking-tight">
                            Hola, {{ $page.props.auth.user.name }}
                        </h2>
                        <p class="text-xs sm:text-sm text-blue-100/90 leading-relaxed font-normal">
                            Gestiona las entregas a domicilio, clientes registrados, configuración de horarios y personal operativo desde una plataforma centralizada.
                        </p>
                    </div>

                    <!-- Botón rápido al catálogo -->
                    <div class="mt-5 flex flex-wrap gap-2.5 relative z-10">
                        <Link
                            :href="route('admin.orders.index')"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 backdrop-blur-xs text-white text-xs font-bold rounded-xl border border-white/15 transition-all"
                        >
                            <i class="fa-solid fa-clipboard-list text-xs"></i>
                            <span>Ver pedidos de hoy</span>
                        </Link>
                        <Link
                            :href="route('admin.customers.create')"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 backdrop-blur-xs text-white text-xs font-bold rounded-xl border border-white/15 transition-all"
                        >
                            <i class="fa-solid fa-user-plus text-xs"></i>
                            <span>Registrar cliente</span>
                        </Link>
                    </div>

                    <!-- Decoración sutil de fondo -->
                    <div class="absolute right-0 bottom-0 top-0 w-80 opacity-10 pointer-events-none flex items-center justify-end pr-6">
                        <i class="fa-solid fa-droplet text-9xl text-white"></i>
                    </div>
                </div>

                <!-- Módulos Principales de Gestión -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-bold text-slate-900 tracking-tight">
                            Módulos del Sistema
                        </h3>
                        <span class="text-xs text-slate-500">
                            Accesos rápidos directos
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
                        <Link
                            v-for="mod in modules"
                            :key="mod.name"
                            :href="route(mod.route)"
                            class="group relative bg-white rounded-2xl p-5 border border-slate-200/90 shadow-xs hover:shadow-md hover:border-blue-900/40 transition-all duration-200 flex flex-col justify-between"
                        >
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <div :class="['w-11 h-11 rounded-xl flex items-center justify-center text-lg border shadow-2xs group-hover:scale-105 transition-transform', mod.iconBg]">
                                        <i :class="mod.icon"></i>
                                    </div>
                                    <span :class="['text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full', mod.badgeClass]">
                                        {{ mod.badge }}
                                    </span>
                                </div>
                                <h4 class="text-base font-bold text-slate-900 group-hover:text-blue-900 transition-colors">
                                    {{ mod.name }}
                                </h4>
                                <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                                    {{ mod.description }}
                                </p>
                            </div>

                            <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-blue-900 group-hover:text-blue-800">
                                <span>Acceder</span>
                                <i class="fa-solid fa-arrow-right text-[11px] transform group-hover:translate-x-1 transition-transform"></i>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Accesos directos complementarios -->
                <div class="bg-white rounded-2xl p-5 sm:p-6 border border-slate-200/90 shadow-xs">
                    <h3 class="text-sm font-bold text-slate-900 tracking-tight mb-3">
                        Acciones Frecuentes
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <Link
                            :href="route('admin.orders.create')"
                            class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 hover:bg-blue-50/70 border border-slate-200/80 hover:border-blue-300 text-slate-700 hover:text-blue-900 transition-all"
                        >
                            <i class="fa-solid fa-plus-circle text-blue-900 text-base"></i>
                            <span class="text-xs font-semibold">Registrar pedido manual</span>
                        </Link>

                        <Link
                            :href="route('admin.settings.index')"
                            class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 hover:bg-blue-50/70 border border-slate-200/80 hover:border-blue-300 text-slate-700 hover:text-blue-900 transition-all"
                        >
                            <i class="fa-solid fa-clock text-blue-900 text-base"></i>
                            <span class="text-xs font-semibold">Ajustar horarios de atención</span>
                        </Link>

                        <Link
                            :href="route('admin.users.create')"
                            class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 hover:bg-blue-50/70 border border-slate-200/80 hover:border-blue-300 text-slate-700 hover:text-blue-900 transition-all"
                        >
                            <i class="fa-solid fa-user-plus text-blue-900 text-base"></i>
                            <span class="text-xs font-semibold">Crear nuevo usuario operador</span>
                        </Link>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

