<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
// Por defecto 'all' (Todas las zonas)
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
            <!-- 1. SECCIÓN PRINCIPAL: ESTADO DE RUTA Y SELECCIÓN DE ZONA              -->
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
                                <option
                                    v-for="r in availableRoutes"
                                    :key="r.id"
                                    :value="r.id"
                                >
                                    {{ r.folio }} ({{ r.status_label }}) - {{ r.date }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Banner de Incidencia si la ruta está en pausa -->
                    <div
                        v-if="activeRoute.is_incident"
                        class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs space-y-1"
                    >
                        <p class="font-bold flex items-center gap-1.5">
                            <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> Ruta en pausa por avería o incidencia
                        </p>
                        <p>{{ activeRoute.notes }}</p>
                        <p class="text-rose-700">Las paradas no atendidas permanecen guardadas. Accede a "Mi Ruta" para reanudar el recorrido.</p>
                    </div>

                    <!-- Selector de Zona (obtenido de los registros reales del sistema) -->
                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-extrabold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                                <i class="fa-solid fa-compass text-blue-900"></i> Seleccionar zona de reparto:
                            </label>
                            <span v-if="currentZoneSummary" class="text-xs text-slate-500 font-medium">
                                Filtrando: <strong class="text-slate-800">{{ currentZoneSummary.label }}</strong>
                            </span>
                        </div>

                        <!-- Opciones de Zona existentes -->
                        <div class="flex items-center gap-2 overflow-x-auto pb-1">
                            <button
                                v-for="z in activeRoute.zones"
                                :key="z.key"
                                type="button"
                                @click="selectedZoneKey = z.key"
                                class="min-h-[44px] px-4 py-2 rounded-xl text-xs sm:text-sm font-bold border transition shrink-0 inline-flex items-center gap-2"
                                :class="selectedZoneKey === z.key
                                    ? 'bg-blue-900 text-white border-blue-900 shadow-xs'
                                    : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100 active:scale-98'"
                            >
                                <span>{{ z.label }}</span>
                                <span
                                    class="text-xs px-2 py-0.5 rounded-full font-black"
                                    :class="selectedZoneKey === z.key
                                        ? 'bg-white/20 text-white'
                                        : 'bg-slate-200 text-slate-700'"
                                >
                                    {{ z.total_stops }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Desglose de Paradas de la Zona Seleccionada y Botón Principal -->
                    <div class="p-4 sm:p-5 rounded-xl bg-slate-50 border border-slate-200/80 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="space-y-1">
                            <span class="text-xs uppercase font-extrabold text-slate-500 block">
                                Avance operativo en {{ currentZoneSummary?.label || 'esta ruta' }}
                            </span>
                            <div class="flex items-center gap-4 text-xs sm:text-sm font-semibold text-slate-700">
                                <span>
                                    <strong class="text-slate-900 text-base sm:text-lg">{{ currentZoneSummary?.pending_stops ?? activeRoute.pending_stops_count }}</strong> pendientes
                                </span>
                                <span class="text-slate-300">•</span>
                                <span class="text-emerald-700">
                                    <strong class="text-emerald-900 text-base sm:text-lg">{{ currentZoneSummary?.completed_stops ?? activeRoute.completed_stops_count }}</strong> atendidas
                                </span>
                                <template v-if="(currentZoneSummary?.failed_stops ?? activeRoute.failed_stops_count) > 0">
                                    <span class="text-slate-300">•</span>
                                    <span class="text-rose-700">
                                        <strong class="text-rose-900 text-base sm:text-lg">{{ currentZoneSummary?.failed_stops ?? activeRoute.failed_stops_count }}</strong> fallidas
                                    </span>
                                </template>
                            </div>
                        </div>

                        <!-- Botón Principal Condicional -->
                        <div>
                            <!-- Ruta en curso: Continuar mi ruta -->
                            <Link
                                v-if="activeRoute.is_in_progress"
                                :href="route('repartidor.route.index')"
                                class="min-h-[44px] w-full md:w-auto px-6 py-3 rounded-xl text-sm font-black bg-blue-900 text-white hover:bg-blue-800 active:scale-98 transition shadow-xs inline-flex items-center justify-center gap-2"
                            >
                                <i class="fa-solid fa-play"></i> Continuar mi ruta
                            </Link>

                            <!-- Ruta asignada: Ver mi ruta -->
                            <Link
                                v-else-if="activeRoute.is_assigned"
                                :href="route('repartidor.route.index')"
                                class="min-h-[44px] w-full md:w-auto px-6 py-3 rounded-xl text-sm font-black bg-blue-900 text-white hover:bg-blue-800 active:scale-98 transition shadow-xs inline-flex items-center justify-center gap-2"
                            >
                                <i class="fa-solid fa-route"></i> Ver mi ruta
                            </Link>

                            <!-- Ruta en avería: Ir a Mi Ruta para resolver -->
                            <Link
                                v-else-if="activeRoute.is_incident"
                                :href="route('repartidor.route.index')"
                                class="min-h-[44px] w-full md:w-auto px-6 py-3 rounded-xl text-sm font-black bg-amber-600 text-white hover:bg-amber-700 active:scale-98 transition shadow-xs inline-flex items-center justify-center gap-2"
                            >
                                <i class="fa-solid fa-arrows-rotate"></i> Reanudar recorrido en Mi Ruta
                            </Link>

                            <!-- Ruta completada: Ver resumen -->
                            <Link
                                v-else
                                :href="route('repartidor.route.index')"
                                class="min-h-[44px] w-full md:w-auto px-6 py-3 rounded-xl text-sm font-black bg-slate-800 text-white hover:bg-slate-900 active:scale-98 transition shadow-xs inline-flex items-center justify-center gap-2"
                            >
                                <i class="fa-solid fa-circle-check"></i> Ver resumen de ruta
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Caso B: Sin ruta asignada actualmente -->
                <div v-else class="text-center py-6 sm:py-8 space-y-4">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-2xl">
                        <i class="fa-solid fa-route"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-base sm:text-lg font-bold text-slate-900">
                            Sin ruta de reparto asignada para hoy
                        </h3>
                        <p class="text-xs sm:text-sm text-slate-500 max-w-md mx-auto">
                            El Administrador generará tu hoja de ruta del día en cuanto los pedidos confirmados estén listos. Mientras tanto, puedes revisar la carga de tu camión o registrar clientes al paso.
                        </p>
                    </div>
                    <div class="pt-2 flex flex-wrap items-center justify-center gap-3">
                        <Link
                            :href="route('repartidor.truck-load.index')"
                            class="min-h-[44px] px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 active:scale-98 transition shadow-2xs inline-flex items-center gap-2"
                        >
                            <i class="fa-solid fa-boxes-stacked text-blue-900"></i> Revisar Mi Carga en Camión
                        </Link>
                        <Link
                            :href="route('repartidor.customers.create')"
                            class="min-h-[44px] px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-blue-900 text-white hover:bg-blue-800 active:scale-98 transition shadow-2xs inline-flex items-center gap-2"
                        >
                            <i class="fa-solid fa-user-plus"></i> Agregar cliente
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ===================================================================== -->
            <!-- 2. RESUMEN OPERATIVO (INDICADORES COMPACTOS DE DATOS REALES)           -->
            <!-- ===================================================================== -->
            <div class="space-y-3">
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-xs uppercase tracking-wider font-extrabold text-slate-500 flex items-center gap-2">
                        <i class="fa-solid fa-chart-simple text-blue-900"></i> Resumen Operativo
                    </h3>
                    <span class="text-[11px] text-slate-400">Datos en tiempo de operación</span>
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

            <!-- ===================================================================== -->
            <!-- 3. ACCIONES RÁPIDAS (ACCESOS VISIBLES CON PRIORIDAD A MI RUTA)         -->
            <!-- ===================================================================== -->
            <div class="space-y-3">
                <h3 class="text-xs uppercase tracking-wider font-extrabold text-slate-500 px-1 flex items-center gap-2">
                    <i class="fa-solid fa-bolt text-blue-900"></i> Acciones Rápidas
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 sm:gap-4">
                    <!-- Acción 1: Mi Ruta (Prioridad Principal) -->
                    <Link
                        :href="route('repartidor.route.index')"
                        class="min-h-[44px] p-5 rounded-2xl bg-blue-900 text-white hover:bg-blue-800 active:scale-98 transition shadow-xs flex flex-col justify-between gap-3 group"
                    >
                        <div class="flex items-start justify-between">
                            <div class="space-y-1">
                                <h4 class="text-base font-black flex items-center gap-2">
                                    <i class="fa-solid fa-route"></i> Mi ruta
                                </h4>
                                <p class="text-xs text-blue-100 leading-relaxed">
                                    Consulta tus paradas y registra entregas
                                </p>
                            </div>
                            <span class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center text-sm group-hover:translate-x-1 transition-transform">
                                <i class="fa-solid fa-arrow-right"></i>
                            </span>
                        </div>
                        <div class="text-[11px] font-bold text-blue-200">
                            {{ activeRoute ? `${activeRoute.total_stops} paradas programadas` : 'Sin ruta en curso' }}
                        </div>
                    </Link>

                    <!-- Acción 2: Mi Carga (Secundario) -->
                    <Link
                        :href="route('repartidor.truck-load.index')"
                        class="min-h-[44px] p-5 rounded-2xl bg-white text-slate-800 border border-slate-200/80 hover:bg-slate-50/80 active:scale-98 transition shadow-2xs flex flex-col justify-between gap-3 group"
                    >
                        <div class="flex items-start justify-between">
                            <div class="space-y-1">
                                <h4 class="text-base font-black text-slate-900 flex items-center gap-2">
                                    <i class="fa-solid fa-boxes-stacked text-blue-900"></i> Mi carga
                                </h4>
                                <p class="text-xs text-slate-500 leading-relaxed">
                                    Revisa llenos, vacíos y botellones fijos
                                </p>
                            </div>
                            <span class="w-8 h-8 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center text-sm group-hover:translate-x-1 transition-transform">
                                <i class="fa-solid fa-arrow-right"></i>
                            </span>
                        </div>
                        <div class="text-[11px] font-semibold text-slate-400">
                            {{ truckInventory.exchange_full_available }} llenos • {{ truckInventory.exchange_empty_collected }} vacíos
                        </div>
                    </Link>

                    <!-- Acción 3: Agregar Cliente (Fácil de encontrar) -->
                    <Link
                        :href="route('repartidor.customers.create')"
                        class="min-h-[44px] p-5 rounded-2xl bg-white text-slate-800 border border-slate-200/80 hover:bg-slate-50/80 active:scale-98 transition shadow-2xs flex flex-col justify-between gap-3 group"
                    >
                        <div class="flex items-start justify-between">
                            <div class="space-y-1">
                                <h4 class="text-base font-black text-slate-900 flex items-center gap-2">
                                    <i class="fa-solid fa-user-plus text-emerald-700"></i> Agregar cliente
                                </h4>
                                <p class="text-xs text-slate-500 leading-relaxed">
                                    Registra un nuevo cliente durante el recorrido
                                </p>
                            </div>
                            <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-sm group-hover:translate-x-1 transition-transform">
                                <i class="fa-solid fa-arrow-right"></i>
                            </span>
                        </div>
                        <div class="text-[11px] font-semibold text-emerald-700">
                            Alta rápida con solo apodo
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Pie de página sutil -->
            <div class="pt-4 border-t border-slate-200/60 text-center text-xs text-slate-400">
                Puritronic &copy; Logística y Distribución de Agua Purificada
            </div>

        </div>
    </AuthenticatedLayout>
</template>
