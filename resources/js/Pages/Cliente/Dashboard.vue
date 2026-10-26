<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    customer: {
        type: Object,
        default: null,
    },
    activeOrder: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <Head title="Panel de Cliente - Puritronic" />

    <AuthenticatedLayout>
        <!-- 1. ENCABEZADO ÚNICO: Saludo breve y acciones de pedidos -->
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900">
                        Hola, {{ customer?.nickname || $page.props.auth.user.name }}
                    </h1>
                    <p class="text-xs text-slate-500">
                        Resumen de tu cuenta y pedidos de agua purificada
                    </p>
                </div>

                <div class="flex items-center gap-2.5">
                    <!-- Acción secundaria: Ver mis pedidos -->
                    <Link
                        :href="route('cliente.orders.index')"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 border border-slate-200/90 transition shadow-2xs cursor-pointer"
                    >
                        <i class="fa-regular fa-file-lines text-slate-500 text-xs"></i>
                        <span>Ver mis pedidos</span>
                    </Link>

                    <!-- Acción principal: Nuevo pedido (respeta bloqueo por pedido activo) -->
                    <Link
                        v-if="!activeOrder"
                        :href="route('cliente.orders.create')"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold text-white bg-blue-900 hover:bg-blue-800 transition shadow-xs cursor-pointer"
                    >
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Nuevo pedido</span>
                    </Link>
                    <span
                        v-else
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-medium text-slate-400 bg-slate-100 border border-slate-200 cursor-not-allowed select-none"
                        title="Ya cuentas con un pedido activo en proceso. No puedes solicitar otro hasta que se complete o cancele."
                    >
                        <i class="fa-solid fa-lock text-xs"></i>
                        <span>Nuevo pedido</span>
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8 bg-slate-50/60 min-h-[calc(100vh-4rem)]">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 space-y-5">

                <!-- ALERTA DE ÉXITO FLASH -->
                <div
                    v-if="$page.props.flash?.success"
                    class="p-4 rounded-xl bg-emerald-50 border border-emerald-200/90 text-emerald-900 shadow-xs flex items-start gap-3"
                >
                    <div class="h-6 w-6 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5">
                        <i class="fa-solid fa-check text-xs"></i>
                    </div>
                    <div class="flex-1 text-xs sm:text-sm leading-relaxed font-medium">
                        {{ $page.props.flash.success }}
                    </div>
                </div>

                <!-- ALERTA DE ERROR FLASH -->
                <div
                    v-if="$page.props.flash?.error"
                    class="p-4 rounded-xl bg-rose-50 border border-rose-200/90 text-rose-900 shadow-xs flex items-start gap-3"
                >
                    <div class="h-6 w-6 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center shrink-0 mt-0.5">
                        <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                    </div>
                    <div class="flex-1 text-xs sm:text-sm leading-relaxed font-medium">
                        {{ $page.props.flash.error }}
                    </div>
                </div>

                <!-- 2. TARJETA COMPACTA DE PEDIDO ACTIVO (SI EXISTE) -->
                <div
                    v-if="activeOrder"
                    class="bg-white rounded-2xl border border-blue-100 p-4 sm:p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3.5"
                >
                    <div class="flex items-start sm:items-center gap-3">
                        <div class="h-9 w-9 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-clock-rotate-left text-sm"></i>
                        </div>
                        <div class="space-y-0.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs text-slate-500 font-medium">Pedido activo:</span>
                                <span class="font-mono font-semibold text-xs sm:text-sm text-slate-900">{{ activeOrder.folio }}</span>
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded-md text-[11px] font-medium border',
                                        activeOrder.status === 'confirmed'
                                            ? 'bg-blue-50 text-blue-800 border-blue-200'
                                            : 'bg-amber-50 text-amber-800 border-amber-200'
                                    ]"
                                >
                                    {{ activeOrder.status_label }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500">
                                {{ activeOrder.total_bottles }} botellón(es) de 20L en proceso.
                            </p>
                        </div>
                    </div>

                    <Link
                        :href="route('cliente.orders.show', activeOrder.id)"
                        class="inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-blue-900 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-xl transition shrink-0 cursor-pointer"
                    >
                        <span>Ver detalle</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </Link>
                </div>

                <!-- 3 y 4. CUADRÍCULA DE CONTROL: BOTELLONES Y DOMICILIO EN TARJETAS DE ALTURA NATURAL -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6 items-start">

                    <!-- TARJETA UNIFICADA: BOTELLONES (20 LITROS) -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between pb-3.5 border-b border-slate-100">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-8 w-8 rounded-lg bg-blue-50 text-blue-900 flex items-center justify-center shrink-0">
                                        <i class="fa-solid fa-bottle-water text-sm"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-sm font-semibold text-slate-900">
                                            Botellones (20 litros)
                                        </h2>
                                        <p class="text-[11px] text-slate-500">
                                            Inventario registrado en tu cuenta
                                        </p>
                                    </div>
                                </div>

                                <Link
                                    :href="route('profile.edit')"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg transition cursor-pointer"
                                    title="Modificar tus cantidades de botellones"
                                >
                                    <i class="fa-solid fa-pen-to-square text-[11px] text-slate-500"></i>
                                    <span>Ajustar</span>
                                </Link>
                            </div>

                            <!-- Métricas de botellones -->
                            <div class="grid grid-cols-3 gap-2.5 py-4">
                                <div class="p-3 rounded-xl bg-slate-50/70 border border-slate-100 text-center">
                                    <span class="text-[11px] font-medium text-slate-500 block">Fijos</span>
                                    <span class="text-2xl font-semibold text-slate-900 font-mono mt-0.5 block">
                                        {{ customer?.fixed_bottles_count ?? 0 }}
                                    </span>
                                </div>

                                <div class="p-3 rounded-xl bg-slate-50/70 border border-slate-100 text-center">
                                    <span class="text-[11px] font-medium text-slate-500 block">De cambio</span>
                                    <span class="text-2xl font-semibold text-slate-900 font-mono mt-0.5 block">
                                        {{ customer?.exchange_bottles_count ?? 0 }}
                                    </span>
                                </div>

                                <div class="p-3 rounded-xl bg-blue-50/50 border border-blue-100 text-center">
                                    <span class="text-[11px] font-medium text-blue-900 block">Total</span>
                                    <span class="text-2xl font-semibold text-blue-950 font-mono mt-0.5 block">
                                        {{ customer?.total_bottles ?? 0 }}
                                    </span>
                                </div>
                            </div>

                            <!-- Aviso cuando tiene 0 botellones -->
                            <div
                                v-if="!customer || (customer?.total_bottles ?? 0) === 0"
                                class="mb-3 p-2.5 rounded-xl bg-amber-50/80 border border-amber-200/80 text-amber-900 text-xs flex items-center justify-between gap-2"
                            >
                                <span class="text-[11px]">No tienes botellones registrados para solicitar pedidos.</span>
                                <Link :href="route('profile.edit')" class="font-semibold underline shrink-0 text-[11px]">
                                    Indicar cantidades
                                </Link>
                            </div>

                            <!-- Ayuda desplegable accesible sobre modalidades -->
                            <details class="group pt-2 border-t border-slate-100 text-xs">
                                <summary class="cursor-pointer font-medium text-slate-500 hover:text-slate-800 flex items-center justify-between list-none select-none py-1">
                                    <span class="flex items-center gap-1.5">
                                        <i class="fa-regular fa-circle-question text-slate-400"></i>
                                        <span>¿Cómo funcionan las modalidades?</span>
                                    </span>
                                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 group-open:rotate-180 transition-transform"></i>
                                </summary>
                                <div class="mt-2 space-y-1.5 text-[11px] text-slate-600 bg-slate-50/80 p-2.5 rounded-xl border border-slate-100 leading-relaxed">
                                    <p>
                                        <strong class="text-slate-800 font-semibold">Fijos:</strong> Envases marcados con tu nombre; se recogen vacíos, se lavan y rellenan en planta y se te regresan los mismos.
                                    </p>
                                    <p>
                                        <strong class="text-slate-800 font-semibold">De cambio:</strong> Envases comerciales estándar que intercambias vacíos por llenos en el momento de la entrega.
                                    </p>
                                </div>
                            </details>
                        </div>
                    </div>

                    <!-- TARJETA REDUCIDA: DOMICILIO DE ENTREGA -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between pb-3.5 border-b border-slate-100">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-8 w-8 rounded-lg bg-emerald-50 text-emerald-800 flex items-center justify-center shrink-0">
                                        <i class="fa-solid fa-location-dot text-sm"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-sm font-semibold text-slate-900">
                                            Domicilio de entrega
                                        </h2>
                                        <p class="text-[11px] text-slate-500">
                                            Dirección para tus pedidos en Hocabá
                                        </p>
                                    </div>
                                </div>

                                <span
                                    v-if="customer?.zone_label"
                                    class="text-[11px] font-medium px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200"
                                >
                                    {{ customer.zone_label }}
                                </span>
                            </div>

                            <!-- Información de la dirección -->
                            <div class="py-4 space-y-2">
                                <template v-if="customer?.has_address">
                                    <div class="space-y-0.5">
                                        <span class="text-[11px] font-medium text-slate-500 block">Dirección</span>
                                        <p class="text-sm font-semibold text-slate-900 leading-snug">
                                            {{ customer.address_summary }}
                                        </p>
                                    </div>

                                    <div v-if="customer?.between_streets" class="text-xs text-slate-600 pt-1">
                                        <span class="text-slate-400 text-[11px]">Cruzamientos:</span>
                                        <span class="ml-1 text-slate-700 font-medium">{{ customer.between_streets }}</span>
                                    </div>

                                    <div v-if="customer?.reference" class="text-xs text-slate-600">
                                        <span class="text-slate-400 text-[11px]">Referencia:</span>
                                        <span class="ml-1 text-slate-700 font-medium">{{ customer.reference }}</span>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="py-3 text-center space-y-1">
                                        <p class="text-xs font-medium text-slate-700">
                                            Aún no has registrado tu domicilio de entrega.
                                        </p>
                                        <p class="text-[11px] text-slate-400">
                                            Agrega tu dirección en Hocabá para recibir tus garrafones.
                                        </p>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Enlace para administrarlo -->
                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-[11px] text-slate-400">
                                Hocabá, Yucatán
                            </span>
                            <Link
                                :href="route('cliente.addresses.index')"
                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-900 hover:text-blue-800 transition cursor-pointer"
                            >
                                <span>{{ customer?.has_address ? 'Administrar domicilio' : 'Registrar domicilio' }}</span>
                                <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </Link>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
