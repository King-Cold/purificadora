<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    customer: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <Head title="Panel de Cliente - Puritronic" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3.5">
                    <div class="h-11 w-11 rounded-2xl bg-blue-900 text-white flex items-center justify-center text-sm shadow-xs shrink-0">
                        <i class="fa-solid fa-droplet text-sky-400 text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900">
                            Portal de Cliente
                        </h2>
                        <p class="text-xs text-slate-500">
                            Puritronic Agua Purificada &bull; Panel personal de botellones y entrega a domicilio
                        </p>
                    </div>
                </div>

                <!-- Código del cliente y acceso a Nuevo Pedido -->
                <div v-if="customer" class="flex flex-wrap items-center gap-2.5 self-start sm:self-auto">
                    <span
                        v-if="customer.code"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-mono font-semibold bg-slate-100 text-slate-700 border border-slate-200 shadow-2xs"
                    >
                        <i class="fa-solid fa-barcode text-slate-500 text-xs"></i>
                        <span>{{ customer.code }}</span>
                    </span>

                    <Link
                        :href="route('cliente.orders.index')"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-700 bg-white hover:bg-slate-100 border border-slate-200 transition shadow-2xs cursor-pointer"
                    >
                        <i class="fa-regular fa-file-lines text-slate-500 text-xs"></i>
                        <span>Mis Pedidos</span>
                    </Link>

                    <Link
                        :href="route('cliente.orders.create')"
                        class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold text-white bg-blue-900 hover:bg-blue-800 transition shadow-xs cursor-pointer"
                    >
                        <i class="fa-solid fa-plus-circle text-sky-400"></i>
                        <span>Nuevo Pedido</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8 lg:py-10 bg-slate-50/60 min-h-[calc(100vh-8rem)]">
            <div class="mx-auto max-w-7xl 2xl:max-w-screen-2xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- ALERTA DE CONFIRMACIÓN CON FOLIO FLASH -->
                <div
                    v-if="$page.props.flash?.success"
                    class="p-4 sm:p-5 rounded-2xl bg-emerald-50 border border-emerald-200/80 text-emerald-900 shadow-xs flex items-start gap-3.5 animate-fadeIn"
                >
                    <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <i class="fa-solid fa-circle-check text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-emerald-950">¡Pedido Registrado con Éxito!</h4>
                        <p class="text-xs sm:text-sm text-emerald-800 mt-0.5 leading-relaxed font-medium">
                            {{ $page.props.flash.success }}
                        </p>
                    </div>
                </div>

                <!-- ALERTA DE ERROR FLASH -->
                <div
                    v-if="$page.props.flash?.error"
                    class="p-4 sm:p-5 rounded-2xl bg-rose-50 border border-rose-200/80 text-rose-900 shadow-xs flex items-start gap-3.5"
                >
                    <div class="h-10 w-10 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <i class="fa-solid fa-triangle-exclamation text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-rose-950">Atención</h4>
                        <p class="text-xs sm:text-sm text-rose-800 mt-0.5 leading-relaxed">
                            {{ $page.props.flash.error }}
                        </p>
                    </div>
                </div>

                <!-- 1. BIENVENIDA SUPERIOR DESKTOP AMIGABLE -->
                <div class="bg-white rounded-2xl p-6 sm:p-7 shadow-xs border border-slate-200/80">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="h-14 w-14 rounded-2xl bg-blue-900 text-white flex items-center justify-center text-xl shadow-sm shrink-0">
                                <i class="fa-solid fa-water"></i>
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <h3 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
                                        Hola, {{ $page.props.auth.user.name }}
                                    </h3>
                                    <span
                                        v-if="customer?.nickname"
                                        class="px-2.5 py-0.5 rounded-lg bg-blue-50 text-blue-900 font-semibold text-xs border border-blue-200/60"
                                    >
                                        "{{ customer.nickname }}"
                                    </span>
                                </div>
                                <p class="text-xs sm:text-sm text-slate-500 mt-1 flex items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 text-emerald-700 font-medium">
                                        <i class="fa-solid fa-circle-check text-emerald-500 text-xs"></i>
                                        Servicio de entregas en Hocabá
                                    </span>
                                </p>
                            </div>
                        </div>

                        <!-- Email y Código Píldoras -->
                        <div class="flex flex-wrap items-center gap-2.5 self-start md:self-center">
                            <div class="px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200/70 text-xs text-slate-600 flex items-center gap-2">
                                <i class="fa-solid fa-envelope text-slate-400"></i>
                                <span class="font-medium font-mono text-xs">{{ $page.props.auth.user.email }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. CUADRÍCULA DE CONTROL: INVENTARIO DE BOTELLONES (3 TARJETAS DESKTOP) -->
                <div>
                    <div class="flex items-center justify-between mb-3 px-1">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-bottle-water text-blue-900 text-sm"></i>
                            <h4 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-slate-500">
                                Inventario de Botellones Registrados
                            </h4>
                        </div>
                        <span class="text-xs text-slate-400 font-mono">
                            Actualizado
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-6">
                        <!-- Tarjeta: Botellones Fijos -->
                        <div class="bg-white rounded-2xl p-5 lg:p-6 shadow-xs border border-slate-200/80 hover:border-blue-200 transition-all duration-150">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    Botellones Fijos (20L)
                                </span>
                                <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-water text-base"></i>
                                </div>
                            </div>
                            <div class="mt-3 flex items-baseline gap-2">
                                <span class="text-3xl lg:text-4xl font-extrabold tracking-tight text-blue-950 font-mono">
                                    {{ customer?.fixed_bottles_count ?? 0 }}
                                </span>
                                <span class="text-xs text-slate-500 font-medium">envase(s) de 20L</span>
                            </div>
                            <p class="text-xs text-slate-400 mt-2 leading-relaxed">
                                Envases de 20L rotulados con tu nombre para pedidos a domicilio; se lavan, rellenan y devuelven los mismos.
                            </p>
                        </div>

                        <!-- Tarjeta: Botellones de Cambio -->
                        <div class="bg-white rounded-2xl p-5 lg:p-6 shadow-xs border border-slate-200/80 hover:border-sky-200 transition-all duration-150">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    Botellones de Cambio (20L)
                                </span>
                                <div class="h-10 w-10 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-bottle-water text-base"></i>
                                </div>
                            </div>
                            <div class="mt-3 flex items-baseline gap-2">
                                <span class="text-3xl lg:text-4xl font-extrabold tracking-tight text-slate-900 font-mono">
                                    {{ customer?.exchange_bottles_count ?? 0 }}
                                </span>
                                <span class="text-xs text-slate-500 font-medium">envase(s) de 20L</span>
                            </div>
                            <p class="text-xs text-slate-400 mt-2 leading-relaxed">
                                Envases estándar de 20L que intercambias llenos por vacíos al momento de la entrega a domicilio.
                            </p>
                        </div>

                        <!-- Tarjeta: Total en Posesión -->
                        <div class="bg-gradient-to-br from-blue-900 to-slate-900 text-white rounded-2xl p-5 lg:p-6 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-sky-200 uppercase tracking-wider">
                                    Total en Posesión
                                </span>
                                <div class="h-10 w-10 rounded-xl bg-white/10 text-sky-400 flex items-center justify-center shrink-0 border border-white/10">
                                    <i class="fa-solid fa-droplet text-base"></i>
                                </div>
                            </div>
                            <div class="mt-3 flex items-baseline gap-2">
                                <span class="text-3xl lg:text-4xl font-extrabold tracking-tight text-white font-mono">
                                    {{ customer?.total_bottles ?? 0 }}
                                </span>
                                <span class="text-xs text-sky-200 font-medium">garrafones totales</span>
                            </div>
                            <p class="text-xs text-slate-300 mt-2 leading-relaxed">
                                Suma combinada de envases fijos y de cambio asignados a tu cuenta de cliente.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 4. SECCIÓN PRINCIPAL EN 2 COLUMNAS AMPLIAS (DOMICILIO + ACCESOS RÁPIDOS) -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                    <!-- Columna Izquierda: Tarjeta de Domicilio de Casa (7 cols) -->
                    <div class="lg:col-span-7 bg-white rounded-2xl p-6 sm:p-7 shadow-xs border border-slate-200/80 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-xl bg-emerald-50 text-emerald-800 flex items-center justify-center shrink-0 border border-emerald-100">
                                        <i class="fa-solid fa-house-chimney text-base"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-slate-900">
                                            Mi Domicilio de Casa
                                        </h3>
                                        <p class="text-xs text-slate-500">
                                            Dirección para la entrega a domicilio de tus botellones
                                        </p>
                                    </div>
                                </div>

                                <span
                                    v-if="customer?.zone_label"
                                    class="text-xs font-semibold px-3 py-1 rounded-full bg-blue-50 text-blue-900 border border-blue-200/70"
                                >
                                    {{ customer.zone_label }}
                                </span>
                            </div>

                            <!-- Contenido del domicilio -->
                            <div class="py-5">
                                <template v-if="customer?.has_address">
                                    <div class="flex items-start gap-3.5">
                                        <div class="h-8 w-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 mt-0.5">
                                            <i class="fa-solid fa-location-dot text-sm text-blue-900"></i>
                                        </div>
                                        <div class="space-y-1">
                                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 block">
                                                Dirección registrada
                                            </span>
                                            <p class="text-base font-bold text-slate-900 leading-snug">
                                                {{ customer.address_summary }}
                                            </p>
                                            <p class="text-xs text-slate-500 pt-0.5">
                                                <i class="fa-solid fa-circle-info text-blue-700 mr-1 text-[11px]"></i>
                                                Domicilio único asociado a tu cuenta para las rutas de reparto.
                                            </p>
                                        </div>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="text-center py-6 px-4 rounded-xl bg-slate-50 border border-dashed border-slate-200">
                                        <i class="fa-solid fa-map-location-dot text-2xl text-slate-400 mb-2 block"></i>
                                        <p class="text-sm font-semibold text-slate-800">
                                            Aún no has registrado tu domicilio
                                        </p>
                                        <p class="text-xs text-slate-500 mt-0.5 max-w-sm mx-auto">
                                            Indica la calle, número y referencias de tu casa en Hocabá para recibir tus garrafones.
                                        </p>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Botón de acción hacia domicilio -->
                        <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-xs text-slate-400">
                                <i class="fa-solid fa-truck text-slate-400 mr-1"></i>
                                Reparto en Hocabá, Yucatán
                            </span>
                            <Link
                                :href="route('cliente.addresses.index')"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-white bg-blue-900 hover:bg-blue-800 transition shadow-xs"
                            >
                                <i class="fa-solid fa-location-dot text-sky-400"></i>
                                <span>{{ customer?.has_address ? 'Administrar domicilio' : 'Registrar mi domicilio' }}</span>
                                <i class="fa-solid fa-arrow-right text-[10px] ml-0.5"></i>
                            </Link>
                        </div>
                    </div>

                    <!-- Columna Derecha: Tarjetas de Acción Rápida (5 cols) -->
                    <div class="lg:col-span-5 space-y-4 flex flex-col justify-between">

                        <!-- Tarjeta Nuevo Pedido Directo -->
                        <Link
                            :href="route('cliente.orders.create')"
                            class="group block bg-gradient-to-br from-blue-900 to-slate-900 text-white rounded-2xl p-5 shadow-xs hover:shadow-md hover:from-blue-800 hover:to-slate-800 transition-all duration-200 cursor-pointer"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3.5">
                                    <div class="h-11 w-11 rounded-xl bg-white/10 text-sky-400 flex items-center justify-center border border-white/10 group-hover:scale-105 transition-transform shrink-0">
                                        <i class="fa-solid fa-truck-ramp-box text-base"></i>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4 class="text-base font-bold text-white">
                                                Nuevo Pedido
                                            </h4>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-400/20 text-sky-200 border border-sky-400/30">
                                                20 Litros
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-300 mt-0.5">
                                            Solicita botellones fijos o de cambio a tu domicilio
                                        </p>
                                    </div>
                                </div>
                                <span class="h-7 w-7 rounded-full bg-white/10 text-white flex items-center justify-center group-hover:bg-white group-hover:text-blue-900 transition-colors shrink-0">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </div>
                        </Link>

                        <!-- Tarjeta Mis Pedidos -->
                        <Link
                            :href="route('cliente.orders.index')"
                            class="group block bg-white rounded-2xl p-5 shadow-xs border border-slate-200/80 hover:border-blue-900 hover:shadow-md transition-all duration-200 cursor-pointer"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3.5">
                                    <div class="h-11 w-11 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center group-hover:bg-blue-900 group-hover:text-white transition-colors duration-200 shrink-0">
                                        <i class="fa-regular fa-file-lines text-base"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-base font-bold text-slate-900 group-hover:text-blue-900 transition-colors">
                                            Mis Pedidos
                                        </h4>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            Consulta tu historial de entregas y pedidos activos
                                        </p>
                                    </div>
                                </div>
                                <span class="h-7 w-7 rounded-full bg-slate-50 text-slate-400 group-hover:text-blue-900 group-hover:bg-blue-50 flex items-center justify-center transition-colors shrink-0">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </div>
                        </Link>

                        <!-- Tarjeta Mi Perfil -->
                        <Link
                            :href="route('profile.edit')"
                            class="group block bg-white rounded-2xl p-5 shadow-xs border border-slate-200/80 hover:border-blue-900 hover:shadow-md transition-all duration-200 cursor-pointer"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3.5">
                                    <div class="h-11 w-11 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center group-hover:bg-blue-900 group-hover:text-white transition-colors duration-200">
                                        <i class="fa-solid fa-user-pen text-base"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-base font-bold text-slate-900 group-hover:text-blue-900 transition-colors">
                                            Mi Perfil
                                        </h4>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            Datos personales, apodo y teléfono
                                        </p>
                                    </div>
                                </div>
                                <span class="h-7 w-7 rounded-full bg-slate-50 text-slate-400 group-hover:text-blue-900 group-hover:bg-blue-50 flex items-center justify-center transition-colors">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </div>
                        </Link>

                        <!-- Tarjeta Mi Domicilio enlace directo -->
                        <Link
                            :href="route('cliente.addresses.index')"
                            class="group block bg-white rounded-2xl p-5 shadow-xs border border-slate-200/80 hover:border-blue-900 hover:shadow-md transition-all duration-200 cursor-pointer"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3.5">
                                    <div class="h-11 w-11 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center group-hover:bg-blue-900 group-hover:text-white transition-colors duration-200">
                                        <i class="fa-solid fa-map-location-dot text-base"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-base font-bold text-slate-900 group-hover:text-blue-900 transition-colors">
                                            Zona y Dirección
                                        </h4>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            Zona de reparto asignada y referencias
                                        </p>
                                    </div>
                                </div>
                                <span class="h-7 w-7 rounded-full bg-slate-50 text-slate-400 group-hover:text-blue-900 group-hover:bg-blue-50 flex items-center justify-center transition-colors">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </div>
                        </Link>

                        <!-- Banner Minimalista Puritronic -->
                        <div class="rounded-2xl bg-gradient-to-r from-blue-950 to-slate-900 text-white p-5 shadow-xs">
                            <div class="flex items-center gap-3.5">
                                <div class="h-11 w-11 rounded-xl bg-white p-1.5 flex items-center justify-center shrink-0 shadow-sm">
                                    <img src="/images/logo.png" alt="Puritronic" class="h-full w-full object-contain" />
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">
                                        Servicio Puritronic
                                    </h4>
                                    <p class="text-xs text-slate-300 mt-0.5 leading-relaxed">
                                        Agua purificada certificada con entregas domiciliarias en Hocabá.
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- 5. PIE DE PÁGINA Y CIERRE DE SESIÓN -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 pb-6 text-xs text-slate-400 border-t border-slate-200/70">
                    <span class="flex items-center gap-2">
                        <img src="/images/logo.png" alt="Puritronic" class="h-4 w-auto object-contain opacity-70" />
                        <span>Puritronic &copy; Agua Purificada de Calidad</span>
                    </span>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 bg-white hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 border border-slate-200/80 transition duration-150 cursor-pointer shadow-2xs"
                    >
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <span>Cerrar sesión</span>
                    </Link>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
