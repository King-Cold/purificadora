<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RepartidorBottomNav from '@/Components/RepartidorBottomNav.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    driver: {
        type: Object,
        required: true,
    },
    formattedDate: {
        type: String,
        required: true,
    },
    activeRoute: {
        type: Object,
        default: null,
    },
    availableRoutes: {
        type: Array,
        default: () => [],
    },
    counters: {
        type: Object,
        default: () => ({
            por_recoger: 0,
            por_entregar: 0,
            cobrar_pendientes: 0,
            transferencias_pendientes: 0,
        }),
    },
    truckInventory: {
        type: Object,
        required: true,
    },
});

// Selector de ruta (si hay múltiples rutas disponibles)
const selectedRouteId = ref(props.activeRoute?.id || (props.availableRoutes[0]?.id ?? null));

const onRouteSelectChange = () => {
    if (!selectedRouteId.value) return;
    router.get(
        route('repartidor.dashboard'),
        { route_id: selectedRouteId.value },
        { preserveState: true, preserveScroll: true }
    );
};

// Selector de zona (para la ruta seleccionada)
const selectedZoneKey = ref('all');

const currentZoneSummary = computed(() => {
    if (!props.activeRoute?.zones || props.activeRoute.zones.length === 0) {
        return null;
    }
    return props.activeRoute.zones.find(z => z.key === selectedZoneKey.value) || props.activeRoute.zones[0];
});

// Clases para el badge del estado de la ruta
const getRouteStatusBadge = (status) => {
    switch (status) {
        case 'in_progress':
            return {
                class: 'bg-blue-50 text-blue-900 border-blue-200 font-bold',
                icon: 'fa-solid fa-truck-moving text-blue-700',
            };
        case 'assigned':
            return {
                class: 'bg-amber-50 text-amber-900 border-amber-200 font-semibold',
                icon: 'fa-solid fa-clock text-amber-600',
            };
        case 'incident':
            return {
                class: 'bg-rose-50 text-rose-900 border-rose-200 font-bold',
                icon: 'fa-solid fa-triangle-exclamation text-rose-600',
            };
        case 'completed':
            return {
                class: 'bg-emerald-50 text-emerald-900 border-emerald-200 font-bold',
                icon: 'fa-solid fa-circle-check text-emerald-600',
            };
        default:
            return {
                class: 'bg-slate-100 text-slate-700 border-slate-200 font-medium',
                icon: 'fa-regular fa-circle text-slate-500',
            };
    }
};
</script>

<template>
    <Head title="Mi Jornada de Reparto - Puritronic" />

    <AuthenticatedLayout>
        <!-- ========================================================================= -->
        <!-- ENCABEZADO DE PÁGINA                                                      -->
        <!-- ========================================================================= -->
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-xl sm:text-2xl font-black leading-tight text-slate-900 flex items-center gap-2.5">
                        <i class="fa-solid fa-truck-fast text-blue-900"></i> Mi jornada de reparto
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                        Consulta tu ruta y administra tus entregas
                    </p>
                </div>

                <div class="flex items-center gap-2.5 text-xs sm:text-sm text-slate-600 bg-slate-100/90 px-3.5 py-2 rounded-xl border border-slate-200/70 self-start sm:self-auto shadow-2xs">
                    <span class="font-bold text-slate-800 flex items-center gap-1.5">
                        <i class="fa-solid fa-user text-blue-900"></i> {{ driver.name }}
                    </span>
                    <span class="text-slate-300">•</span>
                    <span class="text-slate-500 font-medium capitalize">
                        {{ formattedDate }}
                    </span>
                </div>
            </div>
        </template>

        <!-- ========================================================================= -->
        <!-- CONTENIDO PRINCIPAL                                                       -->
        <!-- ========================================================================= -->
        <div class="py-6 sm:py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- ===================================================================== -->
            <!-- 1. ACCESOS FUNCIONALES VISIBLES CON CONTADORES REALES                 -->
            <!-- ===================================================================== -->
            <div class="space-y-3">
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-xs uppercase tracking-wider font-extrabold text-slate-500 flex items-center gap-2">
                        <i class="fa-solid fa-layer-group text-blue-900"></i> Funciones Operativas de Reparto
                    </h3>
                    <span class="text-[11px] text-slate-400">Accesos directos de campo</span>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    <!-- 1. POR RECOGER -->
                    <Link
                        :href="route('repartidor.reparto', { tab: 'por_recoger' })"
                        class="p-4 sm:p-5 rounded-2xl bg-white border border-amber-200/80 hover:border-amber-300 hover:shadow-xs active:scale-98 transition flex flex-col justify-between gap-3 group min-h-[44px]"
                    >
                        <div class="flex items-start justify-between">
                            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center text-lg shrink-0">
                                <i class="fa-solid fa-hand-holding-hand"></i>
                            </div>
                            <span class="text-xl sm:text-2xl font-black text-amber-900">
                                {{ counters.por_recoger }}
                            </span>
                        </div>
                        <div>
                            <span class="text-sm font-black text-slate-900 block group-hover:text-amber-800 transition">
                                Por recoger
                            </span>
                            <span class="text-[11px] text-slate-500 block leading-tight mt-0.5">
                                Fijos vacíos en domicilios
                            </span>
                        </div>
                    </Link>

                    <!-- 2. POR ENTREGAR -->
                    <Link
                        :href="route('repartidor.reparto', { tab: 'por_entregar' })"
                        class="p-4 sm:p-5 rounded-2xl bg-white border border-blue-200/80 hover:border-blue-300 hover:shadow-xs active:scale-98 transition flex flex-col justify-between gap-3 group min-h-[44px]"
                    >
                        <div class="flex items-start justify-between">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center text-lg shrink-0">
                                <i class="fa-solid fa-truck-ramp-box"></i>
                            </div>
                            <span class="text-xl sm:text-2xl font-black text-blue-900">
                                {{ counters.por_entregar }}
                            </span>
                        </div>
                        <div>
                            <span class="text-sm font-black text-slate-900 block group-hover:text-blue-900 transition">
                                Por entregar
                            </span>
                            <span class="text-[11px] text-slate-500 block leading-tight mt-0.5">
                                Cambios y devoluciones autorizadas
                            </span>
                        </div>
                    </Link>

                    <!-- 3. PEDIDO AL INSTANTE (DESTACADO HERO) -->
                    <Link
                        :href="route('repartidor.direct-service.create')"
                        class="p-4 sm:p-5 rounded-2xl bg-blue-900 text-white hover:bg-blue-800 active:scale-98 transition shadow-xs flex flex-col justify-between gap-3 group col-span-2 lg:col-span-1 min-h-[44px]"
                    >
                        <div class="flex items-start justify-between">
                            <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-lg shrink-0">
                                <i class="fa-solid fa-bolt text-amber-300"></i>
                            </div>
                            <span class="px-2 py-0.5 rounded-lg bg-amber-400 text-blue-950 font-black text-[10px] uppercase tracking-wider">
                                Inmediato
                            </span>
                        </div>
                        <div>
                            <span class="text-sm font-black text-white block">
                                Pedido al instante
                            </span>
                            <span class="text-[11px] text-blue-100 block leading-tight mt-0.5">
                                Atender cliente al paso (cambio o fijos)
                            </span>
                        </div>
                    </Link>

                    <!-- 4. AGREGAR CLIENTE -->
                    <Link
                        :href="route('repartidor.customers.create')"
                        class="p-4 sm:p-5 rounded-2xl bg-white border border-slate-200/80 hover:border-emerald-300 hover:shadow-xs active:scale-98 transition flex flex-col justify-between gap-3 group min-h-[44px]"
                    >
                        <div class="flex items-start justify-between">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-lg shrink-0">
                                <i class="fa-solid fa-user-plus"></i>
                            </div>
                            <span class="text-xs font-extrabold text-emerald-700 font-mono">
                                Rápido
                            </span>
                        </div>
                        <div>
                            <span class="text-sm font-black text-slate-900 block group-hover:text-emerald-700 transition">
                                Agregar cliente
                            </span>
                            <span class="text-[11px] text-slate-500 block leading-tight mt-0.5">
                                Solo apodo, sin cuenta User
                            </span>
                        </div>
                    </Link>

                    <!-- 5. MI CARGA -->
                    <Link
                        :href="route('repartidor.truck-load.index')"
                        class="p-4 sm:p-5 rounded-2xl bg-white border border-slate-200/80 hover:border-blue-300 hover:shadow-xs active:scale-98 transition flex flex-col justify-between gap-3 group min-h-[44px]"
                    >
                        <div class="flex items-start justify-between">
                            <div class="w-10 h-10 rounded-xl bg-slate-100 text-blue-900 flex items-center justify-center text-lg shrink-0">
                                <i class="fa-solid fa-boxes-stacked"></i>
                            </div>
                            <div class="text-right">
                                <span class="text-xs font-black text-blue-900 block">
                                    {{ truckInventory.exchange_full_available }} llenos
                                </span>
                                <span class="text-[10px] text-slate-400 font-bold block">
                                    {{ truckInventory.exchange_empty_collected }} vacíos
                                </span>
                            </div>
                        </div>
                        <div>
                            <span class="text-sm font-black text-slate-900 block group-hover:text-blue-900 transition">
                                Mi carga
                            </span>
                            <span class="text-[11px] text-slate-500 block leading-tight mt-0.5">
                                <template v-if="counters.transferencias_pendientes > 0">
                                    <span class="text-amber-700 font-bold">{{ counters.transferencias_pendientes }} transferencia(s) por aceptar</span>
                                </template>
                                <template v-else>
                                    Control de stock en camión
                                </template>
                            </span>
                        </div>
                    </Link>

                    <!-- 6. COBRAR PENDIENTES -->
                    <Link
                        :href="route('repartidor.debt.index')"
                        class="p-4 sm:p-5 rounded-2xl bg-white border border-emerald-200/80 hover:border-emerald-300 hover:shadow-xs active:scale-98 transition flex flex-col justify-between gap-3 group min-h-[44px]"
                    >
                        <div class="flex items-start justify-between">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-800 flex items-center justify-center text-lg shrink-0">
                                <i class="fa-solid fa-hand-holding-dollar"></i>
                            </div>
                            <span class="text-xl sm:text-2xl font-black text-emerald-800">
                                {{ counters.cobrar_pendientes }}
                            </span>
                        </div>
                        <div>
                            <span class="text-sm font-black text-slate-900 block group-hover:text-emerald-800 transition">
                                Cobrar pendientes
                            </span>
                            <span class="text-[11px] text-slate-500 block leading-tight mt-0.5">
                                Liquidar botellones históricos
                            </span>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- ===================================================================== -->
            <!-- 2. SECCIÓN PRINCIPAL: ESTADO DE RUTA Y SELECCIÓN DE ZONA              -->
            <!-- ===================================================================== -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-5 sm:p-6 transition">
                <!-- Caso A: Con ruta asignada, en curso o en incidencia -->
                <div v-if="activeRoute" class="space-y-5">
                    <!-- Fila superior: Título de sección, Folio, Selector de Rutas y Estado de la Ruta -->
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <span class="text-xs uppercase tracking-wider font-extrabold text-slate-400">
                                    Hoja de ruta
                                </span>
                                <span class="text-lg font-black text-slate-900">
                                    {{ activeRoute.folio }}
                                </span>
                                <span
                                    class="px-2.5 py-0.5 rounded-lg text-xs border inline-flex items-center gap-1.5"
                                    :class="getRouteStatusBadge(activeRoute.status).class"
                                >
                                    <i :class="getRouteStatusBadge(activeRoute.status).icon"></i>
                                    <span>{{ activeRoute.status_label }}</span>
                                </span>
                            </div>
                            <p class="text-xs text-slate-500">
                                Fecha programada: <span class="font-semibold text-slate-700">{{ activeRoute.date }}</span>
                                <template v-if="activeRoute.started_at">
                                    • Inicio: <span class="font-semibold text-slate-700">{{ activeRoute.started_at }}</span>
                                </template>
                            </p>
                        </div>

                        <!-- Selector de Rutas Habilitadas (si tiene más de 1 ruta) -->
                        <div v-if="availableRoutes.length > 1" class="flex items-center gap-2">
                            <label for="route_selector" class="text-xs font-bold text-slate-600 shrink-0">
                                Cambiar ruta:
                            </label>
                            <select
                                id="route_selector"
                                v-model="selectedRouteId"
                                @change="onRouteSelectChange"
                                class="text-xs rounded-xl border-slate-300 py-1.5 px-3 font-semibold text-slate-800 focus:border-blue-900 focus:ring-blue-900 shadow-2xs"
                            >
                                <option v-for="r in availableRoutes" :key="r.id" :value="r.id">
                                    {{ r.folio }} ({{ r.status_label }}) - {{ r.total_stops }} paradas
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila intermedia: Selector de Zona de Reparto -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-600 uppercase tracking-wider flex items-center gap-1.5">
                                <i class="fa-solid fa-compass text-blue-900"></i> Filtrar Paradas por Zona:
                            </span>
                            <span class="text-[11px] text-slate-400">
                                Paradas en zona: <strong class="text-slate-700">{{ currentZoneSummary?.total_stops ?? activeRoute.total_stops }}</strong>
                            </span>
                        </div>

                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1">
                            <button
                                v-for="z in activeRoute.zones"
                                :key="z.key"
                                type="button"
                                @click="selectedZoneKey = z.key"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition shrink-0 inline-flex items-center gap-1.5 border min-h-[44px]"
                                :class="selectedZoneKey === z.key 
                                    ? 'bg-blue-900 text-white border-blue-900 shadow-2xs' 
                                    : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                            >
                                <span>{{ z.label }}</span>
                                <span
                                    class="text-[10px] px-1.5 py-0.2 rounded-full font-black"
                                    :class="selectedZoneKey === z.key ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'"
                                >
                                    {{ z.total_stops }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Fila inferior: Acción Primaria Directa hacia Mi Ruta -->
                    <div class="pt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-slate-100">
                        <div class="text-xs text-slate-500 flex items-center gap-2">
                            <span>
                                Paradas pendientes en ruta: <strong class="text-slate-800">{{ activeRoute.pending_stops_count }}</strong>
                            </span>
                            <span class="text-slate-300">•</span>
                            <span>
                                Atendidas: <strong class="text-emerald-700">{{ activeRoute.completed_stops_count }}</strong>
                            </span>
                        </div>

                        <Link
                            :href="route('repartidor.reparto')"
                            class="min-h-[44px] px-6 py-2.5 rounded-xl text-xs sm:text-sm font-black transition active:scale-98 shadow-2xs inline-flex items-center justify-center gap-2"
                            :class="activeRoute.is_in_progress
                                ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                                : (activeRoute.is_incident ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-blue-900 hover:bg-blue-800 text-white')"
                        >
                            <i :class="activeRoute.is_in_progress ? 'fa-solid fa-play' : 'fa-solid fa-truck'"></i>
                            <span>{{ activeRoute.is_in_progress ? 'Continuar en Reparto' : (activeRoute.is_incident ? 'Reanudar en Reparto' : 'Abrir Reparto') }}</span>
                        </Link>
                    </div>
                </div>

                <!-- Caso B: Sin ruta asignada actualmente -->
                <div v-else class="text-center py-8 px-4 space-y-4">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-blue-50 text-blue-900 flex items-center justify-center text-2xl">
                        <i class="fa-solid fa-map-location-dot"></i>
                    </div>
                    <div class="max-w-md mx-auto space-y-1">
                        <h3 class="text-base font-black text-slate-900">
                            No tienes ninguna ruta asignada en este momento
                        </h3>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Puedes iniciar tu jornada de reparto directamente para cargar pedidos o atender pedidos al paso.
                        </p>
                    </div>
                    <div class="pt-2 flex flex-wrap items-center justify-center gap-3">
                        <Link
                            :href="route('repartidor.route.start-today')"
                            method="post"
                            as="button"
                            class="min-h-[44px] px-6 py-2.5 rounded-xl text-xs sm:text-sm font-black bg-blue-900 hover:bg-blue-800 text-white active:scale-98 transition shadow-2xs inline-flex items-center gap-2"
                        >
                            <i class="fa-solid fa-play"></i> Iniciar Jornada de Hoy
                        </Link>
                        <Link
                            :href="route('repartidor.truck-load.index')"
                            class="min-h-[44px] px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 active:scale-98 transition shadow-2xs inline-flex items-center gap-2"
                        >
                            <i class="fa-solid fa-boxes-stacked text-blue-900"></i> Revisar Mi Carga en Camión
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ===================================================================== -->
            <!-- 3. RESUMEN OPERATIVO (INDICADORES COMPACTOS DE DATOS REALES)           -->
            <!-- ===================================================================== -->
            <div class="space-y-3">
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-xs uppercase tracking-wider font-extrabold text-slate-500 flex items-center gap-2">
                        <i class="fa-solid fa-chart-simple text-blue-900"></i> Resumen de Existencias
                    </h3>
                    <span class="text-[11px] text-slate-400">Datos reales de camión y ruta</span>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <!-- Tarjeta 1: Entregas pendientes (Ruta) -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-2xs flex flex-col justify-between gap-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500">Entregas pendientes</span>
                            <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm">
                                <i class="fa-solid fa-clock"></i>
                            </div>
                        </div>
                        <div>
                            <span class="text-2xl sm:text-3xl font-black text-slate-900 block leading-tight">
                                <template v-if="activeRoute">
                                    {{ currentZoneSummary?.pending_stops ?? activeRoute.pending_stops_count }}
                                </template>
                                <template v-else>
                                    --
                                </template>
                            </span>
                            <span class="text-[11px] text-slate-400 block mt-0.5">
                                {{ activeRoute ? (selectedZoneKey !== 'all' ? currentZoneSummary?.label : 'En ruta seleccionada') : 'Sin ruta activa' }}
                            </span>
                        </div>
                    </div>

                    <!-- Tarjeta 2: Entregas realizadas (Ruta) -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-2xs flex flex-col justify-between gap-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500">Entregas realizadas</span>
                            <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                        </div>
                        <div>
                            <span class="text-2xl sm:text-3xl font-black text-slate-900 block leading-tight">
                                <template v-if="activeRoute">
                                    {{ currentZoneSummary?.completed_stops ?? activeRoute.completed_stops_count }}
                                </template>
                                <template v-else>
                                    --
                                </template>
                            </span>
                            <span class="text-[11px] text-slate-400 block mt-0.5">
                                {{ activeRoute ? (selectedZoneKey !== 'all' ? currentZoneSummary?.label : 'En ruta seleccionada') : 'Sin ruta activa' }}
                            </span>
                        </div>
                    </div>

                    <!-- Tarjeta 3: Cambios llenos disponibles (Camión) -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-2xs flex flex-col justify-between gap-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500">Cambios llenos</span>
                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center text-sm">
                                <i class="fa-solid fa-bottle-water"></i>
                            </div>
                        </div>
                        <div>
                            <span class="text-2xl sm:text-3xl font-black text-slate-900 block leading-tight">
                                {{ truckInventory.exchange_full_available }}
                            </span>
                            <span class="text-[11px] text-slate-400 block mt-0.5">
                                Disponibles en camión
                            </span>
                        </div>
                    </div>

                    <!-- Tarjeta 4: Vacíos recogidos (Camión) -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-2xs flex flex-col justify-between gap-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500">Vacíos recogidos</span>
                            <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center text-sm">
                                <i class="fa-solid fa-arrow-rotate-left"></i>
                            </div>
                        </div>
                        <div>
                            <span class="text-2xl sm:text-3xl font-black text-slate-900 block leading-tight">
                                {{ truckInventory.exchange_empty_collected }}
                            </span>
                            <span class="text-[11px] text-slate-400 block mt-0.5">
                                De cambio en camión
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Detalle de Botellones Fijos bajo Custodia en Camión (si existen) -->
                <div
                    v-if="truckInventory.total_fixed_in_truck > 0"
                    class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-2xs space-y-3"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                            <i class="fa-solid fa-boxes-stacked text-purple-700"></i> Botellones fijos en custodia ({{ truckInventory.total_fixed_in_truck }} en camión)
                        </span>
                        <Link
                            :href="route('repartidor.truck-load.index')"
                            class="text-xs text-blue-900 font-bold hover:underline"
                        >
                            Ver en Mi Carga
                        </Link>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                        <div
                            v-for="f in truckInventory.fixed_custody"
                            :key="f.customer_id"
                            class="p-3 rounded-xl bg-slate-50 border border-slate-200/70 text-xs flex items-center justify-between gap-2"
                        >
                            <div class="min-w-0">
                                <span class="font-extrabold text-slate-900 block truncate">
                                    {{ f.customer_nickname || f.customer_name }}
                                </span>
                                <span class="text-[10px] font-mono text-slate-400">{{ f.customer_code }}</span>
                            </div>
                            <div class="text-right shrink-0">
                                <span v-if="f.empty_collected > 0" class="text-amber-800 font-bold block text-[11px]">
                                    {{ f.empty_collected }} vacíos
                                </span>
                                <span v-if="f.full_to_deliver > 0" class="text-emerald-800 font-bold block text-[11px]">
                                    {{ f.full_to_deliver }} llenos
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pie de página sutil -->
            <div class="pt-4 border-t border-slate-200/60 text-center text-xs text-slate-400 pb-16 md:pb-4">
                Puritronic &copy; Logística y Distribución de Agua Purificada
            </div>

        </div>

        <!-- Barra de navegación móvil -->
        <RepartidorBottomNav />
    </AuthenticatedLayout>
</template>
