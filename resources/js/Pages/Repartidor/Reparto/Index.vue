<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RepartidorBottomNav from '@/Components/RepartidorBottomNav.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    driver: {
        type: Object,
        required: true,
    },
    activeRoute: {
        type: Object,
        nullable: true,
    },
    journeyStatus: {
        type: String,
        required: true, // 'sin_iniciar', 'en_ruta', 'detenida', 'cerrada'
    },
    journeyStatusLabel: {
        type: String,
        required: true,
    },
    truckState: {
        type: Object,
        required: true,
    },
    activeTab: {
        type: String,
        default: 'pendientes',
    },
    activeZone: {
        type: String,
        default: 'all',
    },
    tabCounts: {
        type: Object,
        required: true,
    },
    zoneCounts: {
        type: Object,
        required: true,
    },
    items: {
        type: Array,
        default: () => [],
    },
    lastUpdatedAt: {
        type: String,
        required: true,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.errors?.route || page.props.errors?.stop || null);

const isRefreshing = ref(false);

const changeTab = (tabKey) => {
    router.get(
        route('repartidor.reparto'),
        { tab: tabKey, zone: props.activeZone },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

const changeZone = (zoneKey) => {
    router.get(
        route('repartidor.reparto'),
        { tab: props.activeTab, zone: zoneKey },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

const refreshData = () => {
    isRefreshing.value = true;
    router.get(
        route('repartidor.reparto'),
        { tab: props.activeTab, zone: props.activeZone },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isRefreshing.value = false;
            },
        }
    );
};

// Acciones de jornada
const startForm = useForm({});
const handleStartJourney = () => {
    startForm.post(route('repartidor.route.start-today'), {
        preserveScroll: true,
    });
};

const resumeForm = useForm({ notes: '' });
const handleResumeJourney = () => {
    if (!props.activeRoute) return;
    resumeForm.post(route('repartidor.route.resume', props.activeRoute.id), {
        preserveScroll: true,
    });
};

const completeForm = useForm({});
const handleCompleteJourney = () => {
    if (!props.activeRoute) return;
    if (confirm('¿Confirmas que deseas concluir la jornada y cerrar la hoja de ruta?')) {
        completeForm.post(route('repartidor.route.complete', props.activeRoute.id), {
            preserveScroll: true,
        });
    }
};

// Modal Avería / Incidencia (Compacto)
const showBreakdownModal = ref(false);
const breakdownForm = useForm({
    reason: '',
    notes: '',
});

const openBreakdownModal = () => {
    breakdownForm.reset();
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
        onSuccess: () => {
            closeBreakdownModal();
        },
    });
};

// Modal Visita Fallida (Compacto)
const showFailModal = ref(false);
const selectedStopToFail = ref(null);
const failForm = useForm({
    failure_reason: 'Cliente ausente',
    notes: '',
});

const openFailModal = (item) => {
    selectedStopToFail.value = item;
    failForm.failure_reason = 'Cliente ausente';
    failForm.notes = '';
    failForm.clearErrors();
    showFailModal.value = true;
};

const closeFailModal = () => {
    showFailModal.value = false;
    selectedStopToFail.value = null;
};

const submitFailStop = () => {
    if (!selectedStopToFail.value?.stop_id) return;
    failForm.post(route('repartidor.stops.fail', selectedStopToFail.value.stop_id), {
        preserveScroll: true,
        onSuccess: () => {
            closeFailModal();
        },
    });
};

// Acciones sobre pedidos
const serveOrderForm = useForm({});
const handleServeOrder = (orderId) => {
    serveOrderForm.post(route('repartidor.orders.serve', orderId), {
        preserveScroll: true,
    });
};

const addToRouteForm = useForm({});
const handleAddToRoute = (orderId) => {
    addToRouteForm.post(route('repartidor.orders.add-to-route', orderId), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Reparto - Operaciones" />

    <AuthenticatedLayout wide>
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6 space-y-4 pb-28 md:pb-12">

            <!-- Mensajes Flash de Notificación -->
            <div
                v-if="flashSuccess"
                class="p-3.5 sm:p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs sm:text-sm flex items-start gap-3 shadow-2xs"
            >
                <i class="fa-solid fa-circle-check text-emerald-600 text-base mt-0.5 shrink-0"></i>
                <div class="flex-1 font-semibold leading-relaxed">{{ flashSuccess }}</div>
            </div>

            <div
                v-if="flashError"
                class="p-3.5 sm:p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 text-xs sm:text-sm flex items-start gap-3 shadow-2xs"
            >
                <i class="fa-solid fa-triangle-exclamation text-rose-600 text-base mt-0.5 shrink-0"></i>
                <div class="flex-1 font-semibold leading-relaxed">{{ flashError }}</div>
            </div>

            <!-- ========================================================================= -->
            <!-- 1. ENCABEZADO COMPACTO DE JORNADA E INVENTARIO DEL CAMIÓN                  -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-4 sm:p-5 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="h-11 w-11 rounded-2xl bg-blue-900 text-white flex items-center justify-center text-lg shrink-0 shadow-2xs">
                            <i class="fa-solid fa-truck-moving"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h1 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">
                                    Reparto Operativo
                                </h1>
                                <!-- Indicador de Estado de la Jornada -->
                                <span
                                    v-if="journeyStatus === 'en_ruta'"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200"
                                >
                                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span>En ruta</span>
                                </span>
                                <span
                                    v-else-if="journeyStatus === 'detenida'"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200"
                                >
                                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                    <span>Detenida por avería</span>
                                </span>
                                <span
                                    v-else-if="journeyStatus === 'cerrada'"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200"
                                >
                                    <i class="fa-solid fa-flag-checkered text-[10px]"></i>
                                    <span>Jornada concluida</span>
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-900 border border-blue-200"
                                >
                                    <i class="fa-regular fa-clock text-[10px]"></i>
                                    <span>Sin iniciar</span>
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Chofer: <span class="font-bold text-slate-700">{{ driver.name }}</span>
                                <span v-if="activeRoute" class="ml-1.5 font-mono text-slate-600 font-semibold">({{ activeRoute.folio }})</span>
                            </p>
                        </div>
                    </div>

                    <!-- Controles de la Jornada y Botón Actualizar -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <!-- Iniciar Jornada -->
                        <button
                            v-if="journeyStatus === 'sin_iniciar'"
                            @click="handleStartJourney"
                            :disabled="startForm.processing"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm active:scale-95 disabled:opacity-50 transition min-h-[44px]"
                        >
                            <i class="fa-solid fa-play text-xs"></i>
                            <span>{{ startForm.processing ? 'Iniciando...' : 'Iniciar jornada' }}</span>
                        </button>

                        <!-- Pausar por avería -->
                        <button
                            v-if="journeyStatus === 'en_ruta'"
                            @click="openBreakdownModal"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 transition min-h-[44px]"
                        >
                            <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
                            <span>Avería</span>
                        </button>

                        <!-- Reanudar jornada -->
                        <button
                            v-if="journeyStatus === 'detenida'"
                            @click="handleResumeJourney"
                            :disabled="resumeForm.processing"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition min-h-[44px]"
                        >
                            <i class="fa-solid fa-rotate-right text-xs"></i>
                            <span>{{ resumeForm.processing ? 'Reanudando...' : 'Reanudar ruta' }}</span>
                        </button>

                        <!-- Finalizar jornada (cuando no hay paradas pendientes en ruta) -->
                        <button
                            v-if="journeyStatus === 'en_ruta' && activeRoute?.pending_stops === 0"
                            @click="handleCompleteJourney"
                            :disabled="completeForm.processing"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-slate-900 hover:bg-slate-800 text-white transition min-h-[44px]"
                        >
                            <i class="fa-solid fa-flag-checkered text-xs"></i>
                            <span>{{ completeForm.processing ? 'Cerrando...' : 'Finalizar jornada' }}</span>
                        </button>

                        <!-- Botón Actualizar sin perder filtros -->
                        <button
                            @click="refreshData"
                            :disabled="isRefreshing"
                            type="button"
                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 active:scale-95 transition min-h-[44px]"
                            title="Actualizar datos"
                        >
                            <i class="fa-solid fa-arrows-rotate text-xs" :class="{ 'fa-spin text-blue-900': isRefreshing }"></i>
                            <span class="hidden sm:inline">Actualizar</span>
                        </button>
                    </div>
                </div>

                <!-- Resumen compacto del camión y hora de actualización -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                    <div class="p-2.5 rounded-2xl bg-blue-50/70 border border-blue-100 flex items-center gap-2.5">
                        <div class="h-8 w-8 rounded-xl bg-blue-900 text-white flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-bottle-water text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase font-bold text-blue-900 tracking-wider">Cambios llenos</p>
                            <p class="text-base font-black text-slate-900">{{ truckState.exchange_full_available }} <span class="text-[11px] font-medium text-slate-500">disp.</span></p>
                        </div>
                    </div>

                    <div class="p-2.5 rounded-2xl bg-amber-50/70 border border-amber-100 flex items-center gap-2.5">
                        <div class="h-8 w-8 rounded-xl bg-amber-600 text-white flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-recycle text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase font-bold text-amber-900 tracking-wider">Vacíos recogidos</p>
                            <p class="text-base font-black text-slate-900">{{ truckState.exchange_empty_collected }} <span class="text-[11px] font-medium text-slate-500">rec.</span></p>
                        </div>
                    </div>

                    <div class="p-2.5 rounded-2xl bg-purple-50/70 border border-purple-100 flex items-center gap-2.5">
                        <div class="h-8 w-8 rounded-xl bg-purple-700 text-white flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-box text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase font-bold text-purple-900 tracking-wider">Fijos en camión</p>
                            <p class="text-base font-black text-slate-900">{{ truckState.fixed_transporting_to_plant }} <span class="text-[11px] font-medium text-slate-500">u.</span></p>
                        </div>
                    </div>

                    <div class="p-2.5 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center gap-2.5">
                        <div class="h-8 w-8 rounded-xl bg-slate-200 text-slate-600 flex items-center justify-center shrink-0">
                            <i class="fa-regular fa-clock text-xs"></i>
                        </div>
                        <div class="truncate">
                            <p class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Actualizado</p>
                            <p class="text-xs font-semibold text-slate-700 truncate font-mono">{{ lastUpdatedAt }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. SELECTOR DE ZONA PERMANENTE                                            -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl border border-slate-200/90 p-2 sm:p-2.5 shadow-2xs overflow-x-auto">
                <div class="flex items-center gap-1.5 min-w-max">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 px-2 flex items-center gap-1">
                        <i class="fa-solid fa-location-dot text-slate-400"></i> Zona:
                    </span>

                    <button
                        type="button"
                        @click="changeZone('all')"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-1.5"
                        :class="activeZone === 'all' ? 'bg-blue-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    >
                        <span>Todas</span>
                        <span class="px-1.5 py-0.2 rounded-md text-[10px]" :class="activeZone === 'all' ? 'bg-blue-800 text-white' : 'bg-white text-slate-600'">
                            {{ zoneCounts.all }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="changeZone('north')"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-1.5"
                        :class="activeZone === 'north' ? 'bg-blue-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    >
                        <span>Norte</span>
                        <span class="px-1.5 py-0.2 rounded-md text-[10px]" :class="activeZone === 'north' ? 'bg-blue-800 text-white' : 'bg-white text-slate-600'">
                            {{ zoneCounts.north }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="changeZone('south')"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-1.5"
                        :class="activeZone === 'south' ? 'bg-blue-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    >
                        <span>Sur</span>
                        <span class="px-1.5 py-0.2 rounded-md text-[10px]" :class="activeZone === 'south' ? 'bg-blue-800 text-white' : 'bg-white text-slate-600'">
                            {{ zoneCounts.south }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="changeZone('east')"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-1.5"
                        :class="activeZone === 'east' ? 'bg-blue-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    >
                        <span>Este</span>
                        <span class="px-1.5 py-0.2 rounded-md text-[10px]" :class="activeZone === 'east' ? 'bg-blue-800 text-white' : 'bg-white text-slate-600'">
                            {{ zoneCounts.east }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="changeZone('west')"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-1.5"
                        :class="activeZone === 'west' ? 'bg-blue-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    >
                        <span>Oeste</span>
                        <span class="px-1.5 py-0.2 rounded-md text-[10px]" :class="activeZone === 'west' ? 'bg-blue-800 text-white' : 'bg-white text-slate-600'">
                            {{ zoneCounts.west }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="changeZone('none')"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-1.5"
                        :class="activeZone === 'none' ? 'bg-blue-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    >
                        <span>Sin zona</span>
                        <span class="px-1.5 py-0.2 rounded-md text-[10px]" :class="activeZone === 'none' ? 'bg-blue-800 text-white' : 'bg-white text-slate-600'">
                            {{ zoneCounts.none }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 3. BARRA DE LAS 5 PESTAÑAS OPERATIVAS                                      -->
            <!-- ========================================================================= -->
            <div class="flex items-center justify-between gap-2 overflow-x-auto border-b border-slate-200 pb-1">
                <div class="flex items-center gap-1.5 min-w-max">
                    <!-- Tab 1: Pendientes -->
                    <button
                        type="button"
                        @click="changeTab('pendientes')"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-2"
                        :class="activeTab === 'pendientes' ? 'bg-blue-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                    >
                        <i class="fa-solid fa-list-check"></i>
                        <span>Pendientes</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'pendientes' ? 'bg-blue-800 text-white' : 'bg-slate-200 text-slate-700'">
                            {{ tabCounts.pendientes }}
                        </span>
                    </button>

                    <!-- Tab 2: Por recoger -->
                    <button
                        type="button"
                        @click="changeTab('por_recoger')"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-2"
                        :class="activeTab === 'por_recoger' ? 'bg-blue-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                    >
                        <i class="fa-solid fa-box-archive"></i>
                        <span>Por recoger</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'por_recoger' ? 'bg-blue-800 text-white' : 'bg-slate-200 text-slate-700'">
                            {{ tabCounts.por_recoger }}
                        </span>
                    </button>

                    <!-- Tab 3: Por entregar -->
                    <button
                        type="button"
                        @click="changeTab('por_entregar')"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-2"
                        :class="activeTab === 'por_entregar' ? 'bg-blue-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                    >
                        <i class="fa-solid fa-dolly"></i>
                        <span>Por entregar</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'por_entregar' ? 'bg-blue-800 text-white' : 'bg-slate-200 text-slate-700'">
                            {{ tabCounts.por_entregar }}
                        </span>
                    </button>

                    <!-- Tab 4: En proceso (Paradas activas) -->
                    <button
                        type="button"
                        @click="changeTab('en_proceso')"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-2"
                        :class="activeTab === 'en_proceso' ? 'bg-blue-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                    >
                        <i class="fa-solid fa-route"></i>
                        <span>En proceso</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'en_proceso' ? 'bg-blue-800 text-white' : 'bg-slate-200 text-slate-700'">
                            {{ tabCounts.en_proceso }}
                        </span>
                    </button>

                    <!-- Tab 5: Atendidos -->
                    <button
                        type="button"
                        @click="changeTab('atendidos')"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-2"
                        :class="activeTab === 'atendidos' ? 'bg-blue-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                    >
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Atendidos</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'atendidos' ? 'bg-blue-800 text-white' : 'bg-slate-200 text-slate-700'">
                            {{ tabCounts.atendidos }}
                        </span>
                    </button>
                </div>

                <!-- Botón Agregar Cliente en Cabecera -->
                <div class="hidden sm:block shrink-0">
                    <Link
                        :href="route('repartidor.customers.create')"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 transition min-h-[44px]"
                    >
                        <i class="fa-solid fa-user-plus text-blue-900"></i>
                        <span>Agregar cliente</span>
                    </Link>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 4. CONTENIDO / TARJETAS OPERATIVAS                                         -->
            <!-- ========================================================================= -->
            <div v-if="items.length === 0" class="bg-white rounded-3xl border border-slate-200/80 p-10 text-center shadow-2xs space-y-3">
                <div class="h-14 w-14 mx-auto rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-inbox"></i>
                </div>
                <h3 class="text-base font-bold text-slate-800">No hay registros para mostrar</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">
                    No se encontraron pedidos en la pestaña <span class="font-bold text-slate-700">{{ activeTab }}</span> para la zona seleccionada.
                </p>
                <div class="pt-2 flex items-center justify-center gap-2">
                    <button
                        v-if="activeZone !== 'all'"
                        @click="changeZone('all')"
                        type="button"
                        class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 transition"
                    >
                        Ver todas las zonas
                    </button>
                    <Link
                        :href="route('repartidor.direct-service.create')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-blue-900 hover:bg-blue-800 text-white transition"
                    >
                        + Pedido al paso
                    </Link>
                </div>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5 sm:gap-4">
                <div
                    v-for="item in items"
                    :key="item.id || item.stop_id"
                    class="bg-white rounded-3xl border border-slate-200/90 p-4 shadow-2xs hover:border-slate-300 transition flex flex-col justify-between space-y-3.5"
                >
                    <!-- Parte Superior: Cliente y Folio -->
                    <div class="space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span v-if="item.stop_number" class="h-6 w-6 rounded-lg bg-blue-900 text-white text-xs font-black flex items-center justify-center">
                                        #{{ item.stop_number }}
                                    </span>
                                    <span class="text-[10px] font-mono font-black px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 border border-slate-200">
                                        {{ item.customer_code }}
                                    </span>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-blue-50 text-blue-900 border border-blue-100">
                                        {{ item.zone_label }}
                                    </span>
                                </div>
                                <h3 class="text-base font-extrabold text-slate-900 mt-1">
                                    {{ item.customer_nickname }}
                                </h3>
                                <p v-if="item.customer_name" class="text-xs text-slate-500">
                                    {{ item.customer_name }}
                                </p>
                            </div>

                            <!-- Semáforo de Crédito -->
                            <div class="shrink-0 text-right">
                                <span
                                    v-if="item.traffic_light?.color === 'green'"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    <span>Al corriente</span>
                                </span>
                                <span
                                    v-else-if="item.traffic_light?.color === 'yellow'"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                    <span>Con deuda (${{ item.pending_debt_amount }} · {{ item.pending_debt_bottles }}/5)</span>
                                </span>
                                <span
                                    v-else-if="item.traffic_light?.color === 'red'"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-50 text-rose-800 border border-rose-200"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                    <span>Límite alcanzado</span>
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-600"
                                >
                                    {{ item.traffic_light?.label || 'Fiado deshabilitado' }}
                                </span>
                            </div>
                        </div>

                        <!-- Cantidades y Modalidad -->
                        <div class="p-2.5 rounded-2xl bg-slate-50 border border-slate-100 space-y-1.5 text-xs">
                            <div class="flex items-center justify-between font-bold">
                                <span class="text-slate-700 flex items-center gap-1.5">
                                    <i class="fa-solid fa-cube text-slate-400"></i>
                                    <span>{{ item.operation_label }}</span>
                                </span>
                                <span class="text-slate-900 font-black font-mono">
                                    {{ item.total_bottles }} bot.
                                </span>
                            </div>

                            <div class="flex items-center gap-3 text-[11px] text-slate-600 flex-wrap">
                                <span v-if="item.exchange_quantity > 0">
                                    Cambio: <strong class="text-slate-900">{{ item.exchange_quantity }}</strong> u.
                                </span>
                                <span v-if="item.fixed_quantity > 0">
                                    Fijos: <strong class="text-slate-900">{{ item.fixed_quantity }}</strong> u.
                                    <template v-if="item.fixed_to_collect > 0">
                                        ({{ item.fixed_to_collect }} por recoger)
                                    </template>
                                </span>
                            </div>
                        </div>

                        <!-- Dirección -->
                        <div class="text-xs text-slate-600 flex items-start gap-1.5">
                            <i class="fa-solid fa-location-dot text-slate-400 mt-0.5 shrink-0"></i>
                            <span class="line-clamp-2">{{ item.address_summary }}</span>
                        </div>

                        <!-- Datos de estado si está atendido -->
                        <div v-if="item.stop_status === 'completed'" class="p-2 rounded-xl bg-emerald-50 text-emerald-800 text-xs font-semibold flex items-center gap-2">
                            <i class="fa-solid fa-circle-check text-emerald-600"></i>
                            <span>Entregado con éxito {{ item.completed_at ? `a las ${item.completed_at}` : '' }}</span>
                        </div>

                        <div v-if="item.stop_status === 'failed'" class="p-2 rounded-xl bg-rose-50 text-rose-800 text-xs font-semibold flex items-start gap-2">
                            <i class="fa-solid fa-circle-xmark text-rose-600 mt-0.5 shrink-0"></i>
                            <div>
                                <p>Visita fallida: {{ item.failure_reason }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción Primaria (Touch target >= 44-48px) -->
                    <div class="pt-2 border-t border-slate-100 flex items-center gap-2">
                        <!-- Caso 1: En Proceso (Parada en ruta activa) -->
                        <template v-if="activeTab === 'en_proceso' || item.stop_status === 'pending'">
                            <Link
                                :href="route('repartidor.stops.serve', item.stop_id)"
                                class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-blue-900 text-white hover:bg-blue-800 active:scale-95 transition min-h-[44px]"
                            >
                                <i class="fa-solid fa-hand-holding-hand text-sky-400"></i>
                                <span>Atender parada</span>
                            </Link>

                            <button
                                @click="openFailModal(item)"
                                type="button"
                                class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-bold bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 active:scale-95 transition min-h-[44px]"
                                title="Marcar como visita fallida"
                            >
                                <i class="fa-solid fa-ban"></i>
                                <span class="hidden sm:inline">Fallida</span>
                            </button>
                        </template>

                        <!-- Caso 2: Atendidos (Solo lectura/consulta) -->
                        <template v-else-if="activeTab === 'atendidos'">
                            <span class="w-full text-center text-xs font-semibold text-slate-400 py-2">
                                Parada concluida
                            </span>
                        </template>

                        <!-- Caso 3: Pendientes / Por Recoger / Por Entregar (Disponibles para atender o agregar) -->
                        <template v-else>
                            <button
                                @click="handleServeOrder(item.id)"
                                :disabled="serveOrderForm.processing"
                                type="button"
                                class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-blue-900 text-white hover:bg-blue-800 active:scale-95 disabled:opacity-50 transition min-h-[44px]"
                            >
                                <i class="fa-solid fa-play text-sky-400 text-xs"></i>
                                <span>{{ activeTab === 'por_recoger' ? 'Recoger' : (activeTab === 'por_entregar' ? 'Entregar' : 'Atender') }}</span>
                            </button>

                            <button
                                v-if="journeyStatus === 'en_ruta'"
                                @click="handleAddToRoute(item.id)"
                                :disabled="addToRouteForm.processing"
                                type="button"
                                class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 active:scale-95 transition min-h-[44px]"
                                title="Agregar a mi ruta sin atender de inmediato"
                            >
                                <i class="fa-solid fa-plus text-slate-500"></i>
                                <span class="hidden sm:inline">A ruta</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

        </div>

        <!-- ========================================================================= -->
        <!-- BOTÓN FLOTANTE PERMANENTE: "+ PEDIDO AL PASO"                             -->
        <!-- ========================================================================= -->
        <div class="fixed bottom-20 right-4 sm:right-6 md:bottom-8 z-30">
            <Link
                :href="route('repartidor.direct-service.create')"
                class="inline-flex items-center gap-2.5 px-4 py-3 sm:px-5 sm:py-3.5 rounded-2xl bg-blue-900 text-white font-black text-xs sm:text-sm shadow-xl hover:bg-blue-800 active:scale-95 transition border border-white/20 min-h-[48px]"
            >
                <div class="h-6 w-6 rounded-lg bg-sky-400 text-blue-950 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-plus font-black"></i>
                </div>
                <span>Pedido al paso</span>
            </Link>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL COMPACTO: REGISTRAR AVERÍA / INCIDENCIA                             -->
        <!-- ========================================================================= -->
        <div
            v-if="showBreakdownModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-3xl max-w-sm w-full p-5 shadow-2xl border border-slate-100 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
                        <span>Pausar por avería</span>
                    </h3>
                    <button @click="closeBreakdownModal" type="button" class="text-slate-400 hover:text-slate-600 p-1">
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>

                <form @submit.prevent="submitBreakdown" class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Motivo de la avería *</label>
                        <input
                            v-model="breakdownForm.reason"
                            type="text"
                            placeholder="Ej. Falla mecánica, ponchadura..."
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                            required
                        />
                        <p v-if="breakdownForm.errors.reason" class="text-xs text-rose-600 mt-1">
                            {{ breakdownForm.errors.reason }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Detalles adicionales</label>
                        <textarea
                            v-model="breakdownForm.notes"
                            rows="2"
                            placeholder="Ubicación o notas de asistencia..."
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button
                            @click="closeBreakdownModal"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 min-h-[44px]"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="breakdownForm.processing"
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white transition min-h-[44px]"
                        >
                            {{ breakdownForm.processing ? 'Registrando...' : 'Confirmar avería' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL COMPACTO: VISITA FALLIDA                                            -->
        <!-- ========================================================================= -->
        <div
            v-if="showFailModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-3xl max-w-sm w-full p-5 shadow-2xl border border-slate-100 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-circle-xmark text-rose-600"></i>
                        <span>Marcar visita fallida</span>
                    </h3>
                    <button @click="closeFailModal" type="button" class="text-slate-400 hover:text-slate-600 p-1">
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>

                <form @submit.prevent="submitFailStop" class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Motivo obligatorio *</label>
                        <select
                            v-model="failForm.failure_reason"
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                            required
                        >
                            <option value="Cliente ausente">Cliente ausente</option>
                            <option value="Sin envases vacíos">Sin envases vacíos</option>
                            <option value="Sin dinero / Límite de fiado superado">Sin dinero / Límite de fiado superado</option>
                            <option value="Domicilio no localizado">Domicilio no localizado</option>
                        </select>
                        <p v-if="failForm.errors.failure_reason" class="text-xs text-rose-600 mt-1">
                            {{ failForm.errors.failure_reason }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Observación adicional</label>
                        <textarea
                            v-model="failForm.notes"
                            rows="2"
                            placeholder="Comentario sobre la visita..."
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                        ></textarea>
                    </div>

                    <div class="p-2.5 rounded-xl bg-slate-50 text-[11px] text-slate-600 leading-relaxed border border-slate-200">
                        Nota: Esta acción registra la parada como fallida y libera el pedido para reprogramación futura sin cancelarlo.
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button
                            @click="closeFailModal"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 min-h-[44px]"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="failForm.processing"
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white transition min-h-[44px]"
                        >
                            {{ failForm.processing ? 'Guardando...' : 'Registrar falla' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- 5. BARRA DE NAVEGACIÓN INFERIOR MÓVIL                                      -->
        <!-- ========================================================================= -->
        <RepartidorBottomNav />
    </AuthenticatedLayout>
</template>

