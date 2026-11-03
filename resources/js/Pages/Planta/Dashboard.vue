<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    initialCountPending: {
        type: Boolean,
        default: false,
    },
    initialCountInfo: {
        type: Object,
        default: null,
    },
    balances: {
        type: Object,
        default: () => ({
            exchange_empty_available: 0,
            exchange_washing_filling: 0,
            exchange_full_available: 0,
            exchange_full_reserved: 0,
            exchange_damaged_total: 0,
            fixed_damaged_total: 0,
            exchange_total_physical: 0,
        }),
    },
    fixedInPlantCount: {
        type: Number,
        default: 0,
    },
    pendingReceptionsCount: {
        type: Number,
        default: 0,
    },
    recentIncidentsCount: {
        type: Number,
        default: 0,
    },
    recentMovements: {
        type: Array,
        default: () => [],
    },
});

const totalDamaged = computed(() => {
    return (props.balances?.exchange_damaged_total || 0) + (props.balances?.fixed_damaged_total || 0);
});

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head title="Panel de Planta - Puritronic" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-slate-800">
                        Panel de Planta y Purificación
                    </h2>
                    <p class="text-sm text-slate-500">Control operativo de llenado, custodia física y almacén</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <i class="fa-solid fa-industry mr-1.5 text-emerald-600"></i>
                        Estación Planta
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- ALERTA DE CONTEO INICIAL PENDIENTE -->
                <div
                    v-if="initialCountPending"
                    class="rounded-2xl bg-amber-50 border-2 border-amber-300 p-5 shadow-sm transition-all"
                >
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="h-12 w-12 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 text-xl font-bold">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-amber-900">
                                    Conteo inicial físico pendiente
                                </h3>
                                <p class="text-sm text-amber-800 mt-0.5">
                                    No se ha registrado el inventario base de garrafones de cambio presentes en planta. Realiza el conteo físico presencial para establecer la línea base operativa.
                                </p>
                            </div>
                        </div>
                        <Link
                            :href="route('planta.inventory.index')"
                            class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm shadow-sm transition-colors whitespace-nowrap self-stretch md:self-auto text-center"
                        >
                            <i class="fa-solid fa-clipboard-check mr-2"></i>
                            Capturar Conteo Inicial
                        </Link>
                    </div>
                </div>

                <!-- RESUMEN DE LÍNEA BASE YA REGISTRADA -->
                <div
                    v-else-if="initialCountInfo"
                    class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-xs sm:text-sm text-slate-600 flex flex-wrap items-center justify-between gap-3"
                >
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                        <span>
                            <strong>Línea base registrada:</strong> {{ initialCountInfo.empty_count }} vacíos y {{ initialCountInfo.full_count }} llenos por {{ initialCountInfo.operator_name }} el {{ initialCountInfo.completed_at }}.
                        </span>
                    </div>
                    <Link
                        :href="route('planta.inventory.index')"
                        class="text-blue-600 hover:text-blue-800 font-semibold inline-flex items-center"
                    >
                        Ver existencias completas <i class="fa-solid fa-arrow-right ml-1"></i>
                    </Link>
                </div>

                <!-- TARJETAS DE SALDOS REALES DE PLANTA -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Vacíos disponibles -->
                    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs hover:border-slate-300 transition-colors">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Vacíos Disponibles</span>
                            <div class="h-9 w-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-sm">
                                <i class="fa-solid fa-box-open"></i>
                            </div>
                        </div>
                        <div class="text-2xl sm:text-3xl font-extrabold text-slate-800">
                            {{ balances.exchange_empty_available }}
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Listos para entrar a lavado</p>
                    </div>

                    <!-- En proceso (lavando / llenando) -->
                    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs hover:border-slate-300 transition-colors">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">En Lavado / Llenado</span>
                            <div class="h-9 w-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm">
                                <i class="fa-solid fa-soap"></i>
                            </div>
                        </div>
                        <div class="text-2xl sm:text-3xl font-extrabold text-amber-600">
                            {{ balances.exchange_washing_filling }}
                        </div>
                        <p class="text-xs text-slate-400 mt-1">En línea de producción</p>
                    </div>

                    <!-- Llenos disponibles -->
                    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs hover:border-slate-300 transition-colors">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Llenos Disponibles</span>
                            <div class="h-9 w-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                                <i class="fa-solid fa-bottle-water"></i>
                            </div>
                        </div>
                        <div class="text-2xl sm:text-3xl font-extrabold text-emerald-700">
                            {{ balances.exchange_full_available }}
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Sellados y aptos para reparto</p>
                    </div>

                    <!-- Reservados para carga -->
                    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs hover:border-slate-300 transition-colors">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Reservados Camión</span>
                            <div class="h-9 w-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-sm">
                                <i class="fa-solid fa-truck-ramp-box"></i>
                            </div>
                        </div>
                        <div class="text-2xl sm:text-3xl font-extrabold text-purple-700">
                            {{ balances.exchange_full_reserved }}
                        </div>
                        <p class="text-xs text-slate-400 mt-1">En propuestas de carga</p>
                    </div>
                </div>

                <!-- ACCESOS OPERATIVOS DIRECTOS -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                    <!-- Tarjeta: Por Recibir de Ruta -->
                    <Link
                        :href="route('planta.reception.index')"
                        class="group bg-white rounded-2xl p-5 border border-slate-200/90 shadow-xs hover:shadow-md hover:border-amber-400 transition-all flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <div class="h-11 w-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-dolly"></i>
                                </div>
                                <span
                                    v-if="pendingReceptionsCount > 0"
                                    class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 animate-pulse"
                                >
                                    {{ pendingReceptionsCount }} pendientes
                                </span>
                                <span
                                    v-else
                                    class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-500"
                                >
                                    Al día
                                </span>
                            </div>
                            <h3 class="text-base font-bold text-slate-800 group-hover:text-amber-700 transition-colors">
                                Por Recibir de Ruta
                            </h3>
                            <p class="text-xs text-slate-500 mt-1">
                                Coteja fijos de clientes y vacíos descargados del camión antes de pasarlos a lavado.
                            </p>
                        </div>
                        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-amber-700">
                            <span>Revisar Recepción</span>
                            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </div>
                    </Link>

                    <!-- Tarjeta: Lavado y Llenado (Mesa de Trabajo) -->
                    <Link
                        :href="route('planta.production.index')"
                        class="group bg-white rounded-2xl p-5 border border-slate-200/90 shadow-xs hover:shadow-md hover:border-cyan-500 transition-all flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <div class="h-11 w-11 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center text-lg group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-soap"></i>
                                </div>
                                <span
                                    v-if="balances.exchange_washing_filling > 0"
                                    class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-cyan-100 text-cyan-800"
                                >
                                    {{ balances.exchange_washing_filling }} en línea
                                </span>
                                <span
                                    v-else
                                    class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-500"
                                >
                                    Listo
                                </span>
                            </div>
                            <h3 class="text-base font-bold text-slate-800 group-hover:text-cyan-700 transition-colors">
                                Lavado y Llenado
                            </h3>
                            <p class="text-xs text-slate-500 mt-1">
                                Mesa de trabajo: pendientes, túnel de purificación y fijos/lotes preparados para reparto.
                            </p>
                        </div>
                        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-cyan-700">
                            <span>Mesa de Trabajo</span>
                            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </div>
                    </Link>

                    <!-- Tarjeta: Cargas al Repartidor -->
                    <Link
                        :href="route('planta.loads.index')"
                        class="group bg-white rounded-2xl p-5 border border-slate-200/90 shadow-xs hover:shadow-md hover:border-indigo-500 transition-all flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <div class="h-11 w-11 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-truck-ramp-box"></i>
                                </div>
                                <span
                                    v-if="balances.exchange_full_reserved > 0"
                                    class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800"
                                >
                                    {{ balances.exchange_full_reserved }} en propuesta
                                </span>
                                <span
                                    v-else
                                    class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-500"
                                >
                                    Al día
                                </span>
                            </div>
                            <h3 class="text-base font-bold text-slate-800 group-hover:text-indigo-700 transition-colors">
                                Cargas a Ruta
                            </h3>
                            <p class="text-xs text-slate-500 mt-1">
                                Propón cargas de llenos y fijos preparados con reserva segura y custodia en planta.
                            </p>
                        </div>
                        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-indigo-700">
                            <span>Gestionar Cargas</span>
                            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </div>
                    </Link>

                    <!-- Tarjeta: Gestión de Existencias e Incidencias -->
                    <Link
                        :href="route('planta.inventory.index')"
                        class="group bg-white rounded-2xl p-6 border border-slate-200/90 shadow-xs hover:shadow-md hover:border-blue-400 transition-all flex flex-col justify-between"
                    >
                        <div>
                            <div class="h-12 w-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl mb-4 group-hover:scale-105 transition-transform">
                                <i class="fa-solid fa-boxes-stacked"></i>
                            </div>
                            <h3 class="text-lg font-bold text-slate-800 group-hover:text-blue-600 transition-colors">
                                Existencias de Planta
                            </h3>
                            <p class="text-sm text-slate-500 mt-1">
                                Consulta saldos por condición, historial de movimientos y registra el conteo físico inicial.
                            </p>
                        </div>
                        <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between text-sm font-semibold text-blue-600">
                            <span>Gestionar Almacén</span>
                            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </div>
                    </Link>

                    <!-- Tarjeta: Fijos de Clientes en Planta -->
                    <Link
                        :href="route('planta.inventory.index')"
                        class="group bg-white rounded-2xl p-6 border border-slate-200/90 shadow-xs hover:shadow-md hover:border-emerald-400 transition-all flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <div class="h-12 w-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-user-tag"></i>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                    {{ fixedInPlantCount }} en custodia
                                </span>
                            </div>
                            <h3 class="text-lg font-bold text-slate-800 group-hover:text-emerald-600 transition-colors">
                                Fijos por Cliente
                            </h3>
                            <p class="text-sm text-slate-500 mt-1">
                                Control individual por propietario: botellones recibidos, en lavado y preparados para retorno.
                            </p>
                        </div>
                        <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between text-sm font-semibold text-emerald-600">
                            <span>Ver fijos activos</span>
                            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </div>
                    </Link>

                    <!-- Tarjeta: Incidencias de Envases -->
                    <Link
                        :href="route('planta.inventory.index')"
                        class="group bg-white rounded-2xl p-6 border border-slate-200/90 shadow-xs hover:shadow-md hover:border-rose-400 transition-all flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <div class="h-12 w-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                    {{ totalDamaged }} dañados
                                </span>
                            </div>
                            <h3 class="text-lg font-bold text-slate-800 group-hover:text-rose-600 transition-colors">
                                Registro de Incidencias
                            </h3>
                            <p class="text-sm text-slate-500 mt-1">
                                Separa botellones rotos o fisurados de los aptos para preparar, con trazabilidad de propietario y pedido.
                            </p>
                        </div>
                        <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between text-sm font-semibold text-rose-600">
                            <span>Revisar bitácora</span>
                            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </div>
                    </Link>
                </div>

                <!-- ACTIVIDAD RECIENTE EN PLANTA -->
                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left text-slate-400"></i>
                            Últimos Movimientos de Custodia en Planta
                        </h3>
                        <Link
                            :href="route('planta.inventory.index')"
                            class="text-xs font-semibold text-blue-600 hover:text-blue-800"
                        >
                            Ver todos
                        </Link>
                    </div>

                    <div v-if="recentMovements.length === 0" class="text-center py-8 text-sm text-slate-400">
                        <i class="fa-regular fa-folder-open text-3xl mb-2 text-slate-300 block"></i>
                        No hay movimientos registrados recientemente en planta.
                    </div>

                    <div v-else class="divide-y divide-slate-100">
                        <div
                            v-for="mov in recentMovements"
                            :key="mov.id"
                            class="py-3 flex items-center justify-between gap-4 text-sm"
                        >
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 text-xs">
                                    <i v-if="mov.movement_type === 'plant_initial_count'" class="fa-solid fa-clipboard-check text-blue-600"></i>
                                    <i v-else-if="mov.movement_type === 'bottle_incident'" class="fa-solid fa-triangle-exclamation text-rose-600"></i>
                                    <i v-else-if="mov.movement_type.includes('plant_washing')" class="fa-solid fa-soap text-amber-600"></i>
                                    <i v-else class="fa-solid fa-arrow-right-arrow-left text-slate-600"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-800">
                                        {{ mov.notes || mov.movement_type }}
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        {{ mov.origin }} &rarr; {{ mov.destination }} | {{ formatDate(mov.created_at) }}
                                    </p>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="font-bold text-slate-900">{{ mov.quantity }} u.</span>
                                <span class="text-xs text-slate-400 block capitalize">{{ mov.bottle_type }}</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
