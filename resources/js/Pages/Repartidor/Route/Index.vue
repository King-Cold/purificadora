<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RepartidorBottomNav from '@/Components/RepartidorBottomNav.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    activeRoute: {
        type: Object,
        nullable: true,
    },
    driver: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.errors?.route || page.props.errors?.stop || null);

// Iniciar ruta
const startForm = useForm({});
const submitStart = () => {
    if (!props.activeRoute) return;
    startForm.post(route('repartidor.route.start', props.activeRoute.id), {
        preserveScroll: true,
    });
};

// Reanudar ruta
const resumeForm = useForm({ notes: '' });
const submitResume = () => {
    if (!props.activeRoute) return;
    resumeForm.post(route('repartidor.route.resume', props.activeRoute.id), {
        preserveScroll: true,
    });
};

// Completar ruta
const completeForm = useForm({});
const submitComplete = () => {
    if (!props.activeRoute) return;
    if (confirm('¿Confirmas que has concluido todas las paradas y deseas cerrar la hoja de ruta?')) {
        completeForm.post(route('repartidor.route.complete', props.activeRoute.id), {
            preserveScroll: true,
        });
    }
};

// Modal de Avería
const showBreakdownModal = ref(false);
const breakdownForm = useForm({ reason: '' });

const openBreakdownModal = () => {
    breakdownForm.reason = '';
    breakdownForm.clearErrors();
    showBreakdownModal.value = true;
};

const closeBreakdownModal = () => {
    showBreakdownModal.value = false;
};

const submitBreakdown = () => {
    if (!props.activeRoute) return;
    breakdownForm.post(route('repartidor.route.breakdown', props.activeRoute.id), {
        preserveScroll: true,
        onSuccess: () => closeBreakdownModal(),
    });
};

// Modal de Visita Fallida
const selectedStop = ref(null);
const showFailedModal = ref(false);
const failedForm = useForm({
    failure_reason: 'Cliente ausente / Domicilio cerrado',
    notes: '',
});

const openFailedModal = (stop) => {
    selectedStop.value = stop;
    failedForm.failure_reason = 'Cliente ausente / Domicilio cerrado';
    failedForm.notes = '';
    failedForm.clearErrors();
    showFailedModal.value = true;
};

const closeFailedModal = () => {
    showFailedModal.value = false;
    selectedStop.value = null;
};

const submitFailed = () => {
    if (!selectedStop.value) return;
    failedForm.post(route('repartidor.stops.fail', selectedStop.value.id), {
        preserveScroll: true,
        onSuccess: () => closeFailedModal(),
    });
};

// -------------------------------------------------------------------------
// SELECCIÓN DE ZONA (Norte, Sur, Este, Oeste, Sin zona)
// -------------------------------------------------------------------------
const selectedZone = ref('all');

const zoneOptions = [
    { key: 'all', label: 'Todas' },
    { key: 'north', label: 'Norte' },
    { key: 'south', label: 'Sur' },
    { key: 'east', label: 'Este' },
    { key: 'west', label: 'Oeste' },
    { key: 'none', label: 'Sin zona' },
];

const getZoneCount = (key) => {
    if (!props.activeRoute?.stops) return 0;
    if (key === 'all') return props.activeRoute.stops.length;
    return props.activeRoute.stops.filter(stop => (stop.delivery_zone_key || 'none') === key).length;
};

const filteredStops = computed(() => {
    if (!props.activeRoute?.stops) return [];
    if (selectedZone.value === 'all') {
        return props.activeRoute.stops;
    }
    return props.activeRoute.stops.filter(stop => (stop.delivery_zone_key || 'none') === selectedZone.value);
});

// -------------------------------------------------------------------------
// SEMÁFORO DE PENDIENTES DE PAGO
// -------------------------------------------------------------------------
const getTrafficLightBadge = (tl) => {
    if (!tl) return { class: 'bg-slate-100 text-slate-700 border-slate-200', icon: 'fa-regular fa-circle text-slate-400', text: 'Sin datos' };
    const limit = tl.limit ?? 0;
    const pending = tl.pending_bottles ?? 0;

    // Límite cero: Fiado deshabilitado, sin inventar deuda
    if (limit === 0) {
        if (pending === 0) {
            return {
                class: 'bg-emerald-50 text-emerald-800 border-emerald-200 font-medium',
                icon: 'fa-solid fa-circle text-emerald-600',
                text: 'Fiado deshabilitado (0 pend.)',
            };
        }
        return {
            class: 'bg-rose-100 text-rose-800 border-rose-300 font-bold',
            icon: 'fa-solid fa-circle text-rose-600',
            text: `Fiado deshabilitado (${pending} pend.)`,
        };
    }

    // Verde: 0 pendientes
    if (pending === 0 || tl.color === 'green') {
        return {
            class: 'bg-emerald-100 text-emerald-800 border-emerald-300 font-medium',
            icon: 'fa-solid fa-circle text-emerald-600',
            text: `Sin pendientes (0/${limit})`,
        };
    }

    // Amarillo: mayor que cero y menor que el límite
    if (pending < limit && tl.color === 'yellow') {
        return {
            class: 'bg-amber-100 text-amber-800 border-amber-300 font-semibold',
            icon: 'fa-solid fa-circle text-amber-500',
            text: `Con deuda (${pending}/${limit} pend.)`,
        };
    }

    // Rojo: igual o superior al límite
    return {
        class: 'bg-rose-100 text-rose-800 border-rose-300 font-bold',
        icon: 'fa-solid fa-circle text-rose-600',
        text: `Límite alcanzado (${pending}/${limit} pend.)`,
    };
};

const getOpBadgeClass = (type) => {
    switch (type) {
        case 'intercambio':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'mixto':
            return 'bg-purple-100 text-purple-800 border-purple-200';
        case 'recoleccion_fijos':
            return 'bg-amber-100 text-amber-800 border-amber-200';
        case 'devolucion_fijos':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        default:
            return 'bg-slate-100 text-slate-800 border-slate-200';
    }
};

const getStopStatusBadge = (status) => {
    switch (status) {
        case 'pending':
            return 'bg-slate-100 text-slate-700 border-slate-200';
        case 'completed':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'failed':
            return 'bg-rose-100 text-rose-800 border-rose-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};
</script>

<template>
    <Head title="Mi Ruta - Panel de Reparto" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-route text-blue-900"></i> Mi Ruta de Entrega
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500">
                        Chofer: <span class="font-semibold text-slate-700">{{ driver.name }}</span>
                        <template v-if="activeRoute">
                            • Folio: <span class="font-bold text-blue-900">{{ activeRoute.folio }}</span>
                        </template>
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <Link
                        :href="route('repartidor.customers.create')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-bold bg-emerald-700 text-white hover:bg-emerald-800 shadow-2xs transition"
                    >
                        <i class="fa-solid fa-user-plus"></i> Agregar cliente
                    </Link>
                    <Link
                        :href="route('repartidor.truck-load.index')"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 shadow-2xs transition"
                    >
                        <i class="fa-solid fa-boxes-stacked"></i> Mi Carga
                    </Link>
                    <Link
                        :href="route('repartidor.dashboard')"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs sm:text-sm font-medium bg-slate-100 text-slate-600 hover:bg-slate-200 transition"
                    >
                        Panel
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            <!-- Mensajes Flash -->
            <div
                v-if="flashSuccess"
                class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-start gap-3 shadow-2xs"
            >
                <i class="fa-solid fa-circle-check text-lg text-emerald-600 mt-0.5"></i>
                <div class="flex-1 font-medium">{{ flashSuccess }}</div>
            </div>

            <div
                v-if="flashError"
                class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-start gap-3 shadow-2xs"
            >
                <i class="fa-solid fa-triangle-exclamation text-lg text-rose-600 mt-0.5"></i>
                <div class="flex-1 font-medium">{{ flashError }}</div>
            </div>

            <!-- ========================================================================= -->
            <!-- 1. CASO SIN RUTA ASIGNADA                                                 -->
            <!-- ========================================================================= -->
            <div v-if="!activeRoute" class="bg-white rounded-2xl border border-slate-200 p-8 text-center shadow-2xs space-y-3">
                <div class="w-16 h-16 mx-auto rounded-3xl bg-slate-100 text-slate-400 flex items-center justify-center text-3xl">
                    <i class="fa-solid fa-truck text-3xl text-slate-400"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800">No tienes una ruta activa asignada</h3>
                <p class="text-xs sm:text-sm text-slate-500 max-w-md mx-auto">
                    El Administrador armará y despachará tu hoja de ruta del día cuando los pedidos confirmados estén listos.
                </p>
                <div class="pt-2 flex flex-wrap items-center justify-center gap-2">
                    <Link
                        :href="route('repartidor.customers.create')"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-emerald-700 text-white hover:bg-emerald-800 transition"
                    >
                        <i class="fa-solid fa-user-plus"></i> Agregar cliente
                    </Link>
                    <Link
                        :href="route('repartidor.truck-load.index')"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-blue-900 text-white hover:bg-blue-800 transition"
                    >
                        <i class="fa-solid fa-boxes-stacked"></i> Revisar Mi Carga en Camión
                    </Link>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. CASO CON RUTA ASIGNADA O EN CURSO                                      -->
            <!-- ========================================================================= -->
            <template v-else>
                <!-- Barra de Estado y Controles Principales -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-2xs space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="space-y-0.5">
                            <span class="text-xs uppercase tracking-wider font-bold text-slate-400">Estado de la Hoja de Ruta</span>
                            <div class="flex items-center gap-2">
                                <span class="text-base font-extrabold text-slate-900">{{ activeRoute.folio }}</span>
                                <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold bg-blue-100 text-blue-800">
                                    {{ activeRoute.status_label }}
                                </span>
                            </div>
                        </div>

                        <!-- Botones de Acción de Ruta -->
                        <div class="flex items-center gap-2 flex-wrap">
                            <!-- Iniciar Ruta -->
                            <button
                                v-if="activeRoute.status === 'assigned'"
                                @click="submitStart"
                                :disabled="startForm.processing"
                                type="button"
                                class="w-full sm:w-auto px-5 py-3 rounded-xl text-sm font-extrabold bg-blue-900 text-white hover:bg-blue-800 active:scale-95 transition shadow-2xs inline-flex items-center gap-2"
                            >
                                <i class="fa-solid fa-play"></i> Iniciar Ruta
                            </button>

                            <!-- Atender cliente al paso (Servicio directo) -->
                            <Link
                                v-if="activeRoute.status === 'in_progress'"
                                :href="route('repartidor.direct-service.create')"
                                class="w-full sm:w-auto px-4 py-2.5 rounded-xl text-xs sm:text-sm font-extrabold bg-blue-600 text-white hover:bg-blue-700 active:scale-95 transition shadow-2xs inline-flex items-center justify-center gap-1.5"
                            >
                                <i class="fa-solid fa-person-walking"></i> Atender cliente al paso
                            </Link>

                            <!-- Reportar Avería -->
                            <button
                                v-if="activeRoute.status === 'in_progress'"
                                @click="openBreakdownModal"
                                type="button"
                                class="w-full sm:w-auto px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 active:scale-95 transition inline-flex items-center gap-1.5"
                            >
                                <i class="fa-solid fa-triangle-exclamation"></i> Reportar Avería Mecánica
                            </button>

                            <!-- Reanudar Ruta tras Avería -->
                            <button
                                v-if="activeRoute.status === 'incident'"
                                @click="submitResume"
                                :disabled="resumeForm.processing"
                                type="button"
                                class="w-full sm:w-auto px-5 py-3 rounded-xl text-sm font-extrabold bg-amber-600 text-white hover:bg-amber-700 active:scale-95 transition shadow-2xs inline-flex items-center gap-2"
                            >
                                <i class="fa-solid fa-arrows-rotate"></i> Reanudar Recorrido
                            </button>

                            <!-- Completar Ruta -->
                            <button
                                v-if="activeRoute.status === 'in_progress' && activeRoute.pending_stops_count === 0"
                                @click="submitComplete"
                                :disabled="completeForm.processing"
                                type="button"
                                class="w-full sm:w-auto px-5 py-3 rounded-xl text-sm font-extrabold bg-emerald-600 text-white hover:bg-emerald-700 active:scale-95 transition shadow-2xs inline-flex items-center gap-2"
                            >
                                <i class="fa-solid fa-flag-checkered"></i> Concluir y Cerrar Ruta
                            </button>
                        </div>
                    </div>

                    <!-- Banner de Incidencia activa -->
                    <div
                        v-if="activeRoute.status === 'incident'"
                        class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs space-y-1"
                    >
                        <p class="font-bold flex items-center gap-1.5"><i class="fa-solid fa-triangle-exclamation text-rose-700"></i> Ruta en pausa por incidencia o avería</p>
                        <p>{{ activeRoute.notes }}</p>
                        <p class="text-rose-700">Las paradas no atendidas permanecen intactas. Pulsa "Reanudar Recorrido" cuando la unidad esté disponible.</p>
                    </div>

                    <!-- Indicadores Rápidos -->
                    <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100 text-center text-xs">
                        <div class="p-2 rounded-xl bg-slate-50">
                            <span class="text-slate-400 block text-[10px] font-bold uppercase">Pendientes</span>
                            <span class="text-lg font-extrabold text-slate-800">{{ activeRoute.pending_stops_count }}</span>
                        </div>
                        <div class="p-2 rounded-xl bg-emerald-50">
                            <span class="text-emerald-600 block text-[10px] font-bold uppercase">Atendidas</span>
                            <span class="text-lg font-extrabold text-emerald-900">{{ activeRoute.completed_stops_count }}</span>
                        </div>
                        <div class="p-2 rounded-xl bg-rose-50">
                            <span class="text-rose-600 block text-[10px] font-bold uppercase">Fallidas</span>
                            <span class="text-lg font-extrabold text-rose-900">{{ activeRoute.failed_stops_count }}</span>
                        </div>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- 3. LISTADO DE PARADAS SECUENCIALES Y SELECCIÓN DE ZONA                     -->
                <!-- ========================================================================= -->
                <div class="space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 px-1">
                        <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                            <i class="fa-solid fa-route text-slate-700"></i> Secuencia de Visitas 
                            <span class="text-xs font-normal text-slate-500">({{ filteredStops.length }} de {{ activeRoute.stops.length }})</span>
                        </h3>
                        <span class="text-xs text-slate-400">Orden original conservado</span>
                    </div>

                    <!-- Selector de Zona (Norte, Sur, Este, Oeste, Sin zona) -->
                    <div class="bg-white p-2.5 rounded-2xl border border-slate-200 shadow-2xs space-y-2">
                        <div class="flex items-center justify-between px-1">
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                                <i class="fa-solid fa-compass text-blue-900"></i> Filtrar por Zona de Reparto:
                            </span>
                            <span
                                v-if="selectedZone !== 'all'"
                                @click="selectedZone = 'all'"
                                class="text-[11px] text-blue-900 font-bold hover:underline cursor-pointer"
                            >
                                Ver todas las zonas
                            </span>
                        </div>
                        <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5">
                            <button
                                v-for="opt in zoneOptions"
                                :key="opt.key"
                                type="button"
                                @click="selectedZone = opt.key"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition shrink-0 inline-flex items-center gap-1.5 border"
                                :class="selectedZone === opt.key 
                                    ? 'bg-blue-900 text-white border-blue-900 shadow-xs' 
                                    : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                            >
                                <span>{{ opt.label }}</span>
                                <span 
                                    class="text-[10px] px-1.5 py-0.2 rounded-full font-extrabold"
                                    :class="selectedZone === opt.key ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-600'"
                                >
                                    {{ getZoneCount(opt.key) }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Mensaje si no hay paradas en la zona seleccionada -->
                    <div
                        v-if="filteredStops.length === 0"
                        class="bg-white rounded-2xl border border-dashed border-slate-300 p-8 text-center space-y-2 shadow-2xs"
                    >
                        <i class="fa-solid fa-map-location-dot text-3xl text-slate-300"></i>
                        <p class="text-sm font-bold text-slate-700">
                            No hay paradas en la zona "{{ zoneOptions.find(z => z.key === selectedZone)?.label }}"
                        </p>
                        <button
                            type="button"
                            @click="selectedZone = 'all'"
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 transition"
                        >
                            Mostrar todas las paradas
                        </button>
                    </div>

                    <div
                        v-for="stop in filteredStops"
                        :key="stop.id"
                        class="bg-white rounded-2xl border p-4 sm:p-5 shadow-2xs space-y-3 transition"
                        :class="stop.status === 'completed' ? 'border-emerald-200 bg-emerald-50/20' : (stop.status === 'failed' ? 'border-rose-200 bg-rose-50/20' : 'border-slate-200')"
                    >
                        <!-- Cabecera de la parada: Número, Apodo, Estado -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3 min-w-0">
                                <span
                                    class="w-9 h-9 rounded-2xl font-extrabold text-sm flex items-center justify-center shrink-0 shadow-2xs"
                                    :class="stop.status === 'completed' ? 'bg-emerald-600 text-white' : (stop.status === 'failed' ? 'bg-rose-600 text-white' : 'bg-blue-900 text-white')"
                                >
                                    {{ stop.stop_number }}
                                </span>

                                <div class="min-w-0 space-y-0.5">
                                    <!-- Apodo destacado -->
                                    <div class="text-lg font-extrabold text-slate-900 leading-snug truncate">
                                        {{ stop.customer_nickname ? `"${stop.customer_nickname}"` : (stop.customer_name || 'Cliente sin nombre') }}
                                    </div>

                                    <!-- Nombre si existe -->
                                    <div v-if="stop.customer_nickname && stop.customer_name" class="text-xs text-slate-500 truncate">
                                        Nombre: {{ stop.customer_name }}
                                    </div>

                                    <!-- Código del cliente -->
                                    <div class="text-xs text-slate-400 font-mono">
                                        Código: {{ stop.customer_code }} • Folio: {{ stop.order_folio }}
                                    </div>
                                </div>
                            </div>

                            <!-- Badge de Estado de la Parada -->
                            <span
                                class="px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wider border shrink-0"
                                :class="getStopStatusBadge(stop.status)"
                            >
                                {{ stop.status_label }}
                            </span>
                        </div>

                        <!-- Semáforo de Pendientes de Pago (Texto claro y cantidad) -->
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2 flex-wrap">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Semáforo de Pago:</span>
                            <div
                                class="px-3 py-1 rounded-xl text-xs border flex items-center gap-1.5"
                                :class="getTrafficLightBadge(stop.traffic_light).class"
                            >
                                <i :class="getTrafficLightBadge(stop.traffic_light).icon"></i>
                                <span>{{ getTrafficLightBadge(stop.traffic_light).text }}</span>
                            </div>
                        </div>

                        <!-- Operación y Cantidades -->
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 space-y-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-700">Operación:</span>
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border"
                                    :class="getOpBadgeClass(stop.operation_type)"
                                >
                                    {{ stop.operation_label }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-500">Cantidad total:</span>
                                <span class="font-extrabold text-blue-950 text-sm">
                                    {{ stop.total_bottles }} garrafones
                                </span>
                            </div>
                            <div v-if="stop.exchange_quantity > 0 && stop.fixed_quantity > 0" class="text-[11px] text-slate-500 flex justify-between">
                                <span>Desglose:</span>
                                <span>{{ stop.exchange_quantity }} cambio / {{ stop.fixed_quantity }} fijos</span>
                            </div>
                        </div>

                        <!-- Domicilio y Teléfono -->
                        <div class="space-y-1.5 text-xs text-slate-600">
                            <!-- Domicilio o sin ubicación registrada -->
                            <div class="flex items-start gap-2">
                                <i class="fa-solid fa-location-dot text-slate-400 text-sm mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="font-medium text-slate-800">{{ stop.address_summary }}</p>
                                    <p class="text-[11px] text-slate-400">Zona: {{ stop.delivery_zone }}</p>
                                </div>
                            </div>

                            <!-- Teléfono ÚNICAMENTE si está registrado -->
                            <div v-if="stop.has_phone" class="pt-1 flex items-center gap-2">
                                <a
                                    :href="`tel:${stop.customer_phone}`"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold hover:bg-emerald-100 active:scale-95 transition"
                                >
                                    <i class="fa-solid fa-phone"></i> Llamar: {{ stop.customer_phone }}
                                </a>
                            </div>
                        </div>

                        <!-- Si la parada fue fallida, mostrar motivo -->
                        <div v-if="stop.status === 'failed' && stop.failure_reason" class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-0.5">
                            <span class="font-bold">Motivo de no entrega:</span> {{ stop.failure_reason }}
                            <p v-if="stop.notes" class="text-slate-500 text-[11px]">{{ stop.notes }}</p>
                        </div>

                        <!-- Botones de Acción de la Parada (Solo si está pendiente) -->
                        <div v-if="stop.status === 'pending'" class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2 flex-wrap">
                            <Link
                                v-if="activeRoute.status === 'in_progress'"
                                :href="route('repartidor.stops.serve', stop.id)"
                                class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-extrabold bg-emerald-600 text-white hover:bg-emerald-700 active:scale-95 transition shadow-2xs inline-flex items-center gap-1.5"
                            >
                                <i class="fa-solid fa-bottle-water"></i> Atender Parada
                            </Link>
                            <span v-else-if="activeRoute.status === 'incident'" class="text-xs text-rose-600 font-medium">
                                En pausa por avería
                            </span>
                            <span v-else class="text-xs text-slate-400">
                                Inicia la ruta para atender
                            </span>

                            <button
                                v-if="activeRoute.status === 'in_progress'"
                                @click="openFailedModal(stop)"
                                type="button"
                                class="px-3 py-2 rounded-xl text-xs font-bold bg-slate-100 text-rose-700 hover:bg-rose-50 hover:border-rose-200 border border-transparent active:scale-95 transition inline-flex items-center gap-1"
                            >
                                <i class="fa-solid fa-xmark"></i> Visita Fallida
                            </button>
                        </div>
                    </div>
                </div>
            </template>

        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: REPORTAR AVERÍA MECÁNICA                                           -->
        <!-- ========================================================================= -->
        <div
            v-if="showBreakdownModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-lg font-bold text-rose-900 flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> Reportar Avería de Unidad
                    </h3>
                    <button @click="closeBreakdownModal" type="button" class="text-slate-400 hover:text-slate-700">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="text-xs text-rose-700 bg-rose-50 p-3 rounded-xl border border-rose-100 space-y-1">
                    <p class="font-bold">La ruta pasará a estado de pausa por avería.</p>
                    <p>Las paradas no atendidas se conservarán pendientes para su posterior reanudación o reprogramación.</p>
                </div>

                <form @submit.prevent="submitBreakdown" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Motivo o desperfecto del vehículo * (Obligatorio)
                        </label>
                        <textarea
                            v-model="breakdownForm.reason"
                            rows="3"
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-rose-500 focus:ring-rose-500"
                            placeholder="Ej. Falla mecánica en transmisión / Neumático pinchado en ruta"
                            required
                        ></textarea>
                        <p v-if="breakdownForm.errors.reason" class="text-xs text-rose-600 mt-1">
                            {{ breakdownForm.errors.reason }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            @click="closeBreakdownModal"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-medium text-slate-600 hover:bg-slate-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="breakdownForm.processing"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold bg-rose-600 text-white hover:bg-rose-700 active:scale-95 disabled:opacity-50 transition"
                        >
                            {{ breakdownForm.processing ? 'Registrando...' : 'Confirmar Avería' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: REGISTRAR VISITA FALLIDA                                           -->
        <!-- ========================================================================= -->
        <div
            v-if="showFailedModal && selectedStop"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-xmark text-rose-600"></i> Registrar Visita Fallida
                    </h3>
                    <button @click="closeFailedModal" type="button" class="text-slate-400 hover:text-slate-700">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-100 space-y-1">
                    <p><span class="font-bold">Cliente:</span> {{ selectedStop.customer_nickname || selectedStop.customer_name }} ({{ selectedStop.customer_code }})</p>
                    <p><span class="font-bold">Pedido:</span> {{ selectedStop.order_folio }} • {{ selectedStop.total_bottles }} garrafones</p>
                    <p class="text-[11px] text-slate-500 mt-1 italic">El pedido no será cancelado; quedará registrado el intento fallido de entrega.</p>
                </div>

                <form @submit.prevent="submitFailed" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Motivo de no entrega *</label>
                        <select
                            v-model="failedForm.failure_reason"
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                            required
                        >
                            <option value="Cliente ausente / Domicilio cerrado">Cliente ausente / Domicilio cerrado</option>
                            <option value="No cuenta con dinero en efectivo">No cuenta con dinero en efectivo</option>
                            <option value="No cuenta con envases vacíos de cambio">No cuenta con envases vacíos de cambio</option>
                            <option value="Rechazó recibir el pedido">Rechazó recibir el pedido</option>
                            <option value="Dirección inaccesible o calle cerrada">Dirección inaccesible o calle cerrada</option>
                            <option value="Otro motivo">Otro motivo</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Observaciones adicionales</label>
                        <input
                            v-model="failedForm.notes"
                            type="text"
                            placeholder="Ej. Se tocó el timbre dos veces sin respuesta"
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            @click="closeFailedModal"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-medium text-slate-600 hover:bg-slate-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="failedForm.processing"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold bg-rose-600 text-white hover:bg-rose-700 active:scale-95 disabled:opacity-50 transition"
                        >
                            {{ failedForm.processing ? 'Registrando...' : 'Marcar Visita Fallida' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Barra inferior móvil -->
        <RepartidorBottomNav />
    </AuthenticatedLayout>
</template>

