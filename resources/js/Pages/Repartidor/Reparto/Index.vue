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
const flashError = computed(() => page.props.errors?.route || page.props.errors?.stop || page.props.errors?.quantity || null);

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

// Menú Dropdown Jornada
const isJornadaMenuOpen = ref(false);
const toggleJornadaMenu = () => {
    isJornadaMenuOpen.value = !isJornadaMenuOpen.value;
};
const closeJornadaMenu = () => {
    isJornadaMenuOpen.value = false;
};

// Acciones de jornada
const startForm = useForm({});
const handleStartJourney = () => {
    closeJornadaMenu();
    startForm.post(route('repartidor.route.start-today'), {
        preserveScroll: true,
    });
};

const resumeForm = useForm({ notes: '' });
const handleResumeJourney = () => {
    if (!props.activeRoute) return;
    closeJornadaMenu();
    resumeForm.post(route('repartidor.route.resume', props.activeRoute.id), {
        preserveScroll: true,
    });
};

const completeForm = useForm({});
const handleCompleteJourney = () => {
    if (!props.activeRoute) return;
    closeJornadaMenu();
    if (confirm('¿Confirmas que deseas concluir la jornada y cerrar la hoja de ruta?')) {
        completeForm.post(route('repartidor.route.complete', props.activeRoute.id), {
            preserveScroll: true,
        });
    }
};

// Modal Avería / Incidencia
const showBreakdownModal = ref(false);
const breakdownForm = useForm({
    reason: '',
    notes: '',
});

const openBreakdownModal = () => {
    closeJornadaMenu();
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

// Modal Visita Fallida
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

// Modal Ver Detalle del Pedido / Cliente
const showDetailModal = ref(false);
const selectedDetailItem = ref(null);

const openDetailModal = (item) => {
    selectedDetailItem.value = item;
    showDetailModal.value = true;
};

const closeDetailModal = () => {
    showDetailModal.value = false;
    selectedDetailItem.value = null;
};

// Modal Recolección Rápida de Fijos
const showCollectModal = ref(false);
const selectedCollectItem = ref(null);
const collectForm = useForm({
    quantity: 1,
    notes: '',
});

const openCollectModal = (item) => {
    selectedCollectItem.value = item;
    collectForm.quantity = item.fixed_to_collect || item.fixed_quantity || 1;
    collectForm.notes = '';
    collectForm.clearErrors();
    showCollectModal.value = true;
};

const closeCollectModal = () => {
    showCollectModal.value = false;
    selectedCollectItem.value = null;
};

const submitCollectFixed = () => {
    if (!selectedCollectItem.value) return;
    collectForm.post(route('repartidor.orders.collect-fixed', selectedCollectItem.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeCollectModal();
        },
    });
};

// Texto descriptivo de operación y cantidad (ej. "Devolver 2 fijos", "Entregar 3 de cambio")
const getOperationText = (item) => {
    if (item.operation_type === 'devolucion_fijos') {
        return `Devolver ${item.fixed_to_deliver || item.fixed_quantity} fijos`;
    }
    if (item.operation_type === 'recoleccion_fijos') {
        return `Recoger ${item.fixed_to_collect || item.fixed_quantity} fijos`;
    }
    if (item.operation_type === 'intercambio') {
        return `Entregar ${item.exchange_pending || item.exchange_quantity} de cambio`;
    }
    const parts = [];
    if (item.fixed_to_deliver > 0) parts.push(`Devolver ${item.fixed_to_deliver} fijos`);
    if (item.exchange_pending > 0) parts.push(`Entregar ${item.exchange_pending} de cambio`);
    if (item.fixed_to_collect > 0) parts.push(`Recoger ${item.fixed_to_collect} fijos`);
    if (parts.length > 0) return parts.join(' y ');
    return item.operation_label || 'Atender pedido';
};

// Atender pedido directamente (sin paso manual ni orden de visitas obligatorio)
const serveOrderForm = useForm({});
const handleServeAction = (item) => {
    if (item.action_type === 'collect_fixed') {
        openCollectModal(item);
        return;
    }

    if (item.stop_id && item.stop_status === 'pending') {
        router.visit(route('repartidor.stops.serve', {
            stop: item.stop_id,
            return_tab: props.activeTab,
            return_zone: props.activeZone,
        }));
        return;
    }

    serveOrderForm.post(route('repartidor.orders.serve', {
        order: item.id,
        tab: props.activeTab,
        zone: props.activeZone,
    }), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Reparto - Puritronic" />

    <AuthenticatedLayout wide>
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6 space-y-4 pb-28 md:pb-16">

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
            <!-- 1. BARRA SUPERIOR COMPACTA (Jornada, Cambios disponibles, + Pedido al paso)-->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs p-3.5 sm:p-4">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <!-- Izquierda: Estado de Jornada y Garrafones de Cambio Disponibles -->
                    <div class="flex items-center gap-3 flex-wrap">
                        <!-- Indicador de Jornada -->
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider hidden sm:inline">Jornada:</span>
                            <span
                                v-if="journeyStatus === 'en_ruta'"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-extrabold bg-emerald-50 text-emerald-800 border border-emerald-200 shadow-2xs"
                            >
                                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span>En ruta</span>
                                <span v-if="activeRoute" class="font-mono text-emerald-950 font-bold ml-1">({{ activeRoute.folio }})</span>
                            </span>
                            <span
                                v-else-if="journeyStatus === 'detenida'"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-extrabold bg-amber-50 text-amber-800 border border-amber-200 shadow-2xs"
                            >
                                <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                <span>Detenida por avería</span>
                            </span>
                            <span
                                v-else-if="journeyStatus === 'cerrada'"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700 border border-slate-200 shadow-2xs"
                            >
                                <i class="fa-solid fa-flag-checkered text-xs"></i>
                                <span>Jornada concluida</span>
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-extrabold bg-blue-50 text-blue-900 border border-blue-200 shadow-2xs"
                            >
                                <i class="fa-regular fa-clock text-xs"></i>
                                <span>Sin iniciar</span>
                            </span>
                        </div>

                        <!-- Divisor vertical -->
                        <span class="h-4 w-px bg-slate-200 hidden sm:inline-block"></span>

                        <!-- Garrafones de cambio disponibles en camión -->
                        <Link
                            :href="route('repartidor.truck-load.index')"
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-blue-50/80 hover:bg-blue-100/80 text-blue-950 border border-blue-200/80 transition min-h-[44px] group"
                            title="Consultar existencias completas en Mi Carga"
                        >
                            <div class="h-6 w-6 rounded-lg bg-blue-900 text-white flex items-center justify-center text-xs group-hover:scale-105 transition">
                                <i class="fa-solid fa-bottle-water"></i>
                            </div>
                            <div class="text-left text-xs leading-tight">
                                <span class="font-black text-slate-900 text-sm">{{ truckState.exchange_full_available }}</span>
                                <span class="text-slate-600 font-semibold ml-1">cambios disp.</span>
                            </div>
                        </Link>

                        <!-- Contexto exclusivo 20L general -->
                        <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                            <i class="fa-solid fa-droplet text-blue-900 text-xs"></i>
                            <span>Botellones 20L</span>
                        </span>
                    </div>

                    <!-- Derecha: "+ Pedido al paso", Menú "Jornada", Actualizar -->
                    <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                        <!-- Botón Visible: + Pedido al paso -->
                        <Link
                            :href="route('repartidor.direct-service.create')"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-black bg-blue-900 text-white hover:bg-blue-800 active:scale-95 transition shadow-2xs min-h-[44px]"
                        >
                            <i class="fa-solid fa-plus text-sky-400"></i>
                            <span>Pedido al paso</span>
                        </Link>

                        <!-- Menú Dropdown Jornada -->
                        <div class="relative">
                            <button
                                @click="toggleJornadaMenu"
                                type="button"
                                class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 active:scale-95 transition min-h-[44px]"
                            >
                                <i class="fa-solid fa-route text-slate-500"></i>
                                <span>Jornada</span>
                                <i class="fa-solid fa-chevron-down text-[10px] ml-0.5 text-slate-400 transition" :class="{ 'rotate-180': isJornadaMenuOpen }"></i>
                            </button>

                            <!-- Fondo de cierre al hacer clic afuera -->
                            <div
                                v-if="isJornadaMenuOpen"
                                @click="closeJornadaMenu"
                                class="fixed inset-0 z-30"
                            ></div>

                            <!-- Menú flotante -->
                            <div
                                v-if="isJornadaMenuOpen"
                                class="absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-slate-200 py-1.5 z-40 space-y-0.5"
                            >
                                <!-- Iniciar jornada -->
                                <button
                                    v-if="journeyStatus === 'sin_iniciar'"
                                    @click="handleStartJourney"
                                    :disabled="startForm.processing"
                                    type="button"
                                    class="w-full text-left px-3.5 py-2.5 text-xs font-bold text-emerald-800 hover:bg-emerald-50 flex items-center gap-2.5 min-h-[44px]"
                                >
                                    <i class="fa-solid fa-play text-emerald-600 w-4 text-center"></i>
                                    <span>{{ startForm.processing ? 'Iniciando...' : 'Iniciar jornada' }}</span>
                                </button>

                                <!-- Pausar por avería -->
                                <button
                                    v-if="journeyStatus === 'en_ruta'"
                                    @click="openBreakdownModal"
                                    type="button"
                                    class="w-full text-left px-3.5 py-2.5 text-xs font-bold text-amber-800 hover:bg-amber-50 flex items-center gap-2.5 min-h-[44px]"
                                >
                                    <i class="fa-solid fa-triangle-exclamation text-amber-600 w-4 text-center"></i>
                                    <span>Reportar avería</span>
                                </button>

                                <!-- Reanudar jornada -->
                                <button
                                    v-if="journeyStatus === 'detenida'"
                                    @click="handleResumeJourney"
                                    :disabled="resumeForm.processing"
                                    type="button"
                                    class="w-full text-left px-3.5 py-2.5 text-xs font-bold text-emerald-800 hover:bg-emerald-50 flex items-center gap-2.5 min-h-[44px]"
                                >
                                    <i class="fa-solid fa-rotate-right text-emerald-600 w-4 text-center"></i>
                                    <span>{{ resumeForm.processing ? 'Reanudando...' : 'Reanudar jornada' }}</span>
                                </button>

                                <!-- Finalizar jornada -->
                                <button
                                    v-if="journeyStatus === 'en_ruta'"
                                    @click="handleCompleteJourney"
                                    :disabled="completeForm.processing"
                                    type="button"
                                    class="w-full text-left px-3.5 py-2.5 text-xs font-bold text-slate-800 hover:bg-slate-100 flex items-center gap-2.5 min-h-[44px]"
                                >
                                    <i class="fa-solid fa-flag-checkered text-slate-600 w-4 text-center"></i>
                                    <span>{{ completeForm.processing ? 'Cerrando...' : 'Finalizar jornada' }}</span>
                                </button>

                                <div class="h-px bg-slate-100 my-1"></div>

                                <!-- Detalle de inventario en Mi Carga -->
                                <Link
                                    :href="route('repartidor.truck-load.index')"
                                    class="w-full text-left px-3.5 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2.5 min-h-[44px]"
                                >
                                    <i class="fa-solid fa-boxes-stacked text-blue-900 w-4 text-center"></i>
                                    <span>Ver Mi Carga</span>
                                </Link>
                            </div>
                        </div>

                        <!-- Botón Actualizar sin perder filtros -->
                        <button
                            @click="refreshData"
                            :disabled="isRefreshing"
                            type="button"
                            class="inline-flex items-center justify-center p-2.5 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 active:scale-95 transition min-h-[44px] min-w-[44px]"
                            title="Actualizar datos"
                        >
                            <i class="fa-solid fa-arrows-rotate text-sm" :class="{ 'fa-spin text-blue-900': isRefreshing }"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. PESTAÑAS PRINCIPALES Y ACCESO HISTÓRICO SECUNDARIO                      -->
            <!-- ========================================================================= -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 border-b border-slate-200 pb-2">
                <!-- 4 Pestañas Principales en Segmented Control -->
                <div class="flex items-center gap-1.5 overflow-x-auto p-1 bg-slate-100/90 rounded-2xl border border-slate-200/60 max-w-full">
                    <!-- Tab 1: Pendientes (Default) -->
                    <button
                        type="button"
                        @click="changeTab('pendientes')"
                        class="px-3.5 py-2 rounded-xl text-xs font-extrabold transition min-h-[44px] flex items-center gap-2 shrink-0 select-none"
                        :class="activeTab === 'pendientes' ? 'bg-white text-blue-950 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    >
                        <i class="fa-solid fa-list-check text-xs" :class="activeTab === 'pendientes' ? 'text-blue-900' : 'text-slate-400'"></i>
                        <span>Pendientes</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black" :class="activeTab === 'pendientes' ? 'bg-blue-100 text-blue-950' : 'bg-slate-200 text-slate-600'">
                            {{ tabCounts.pendientes }}
                        </span>
                    </button>

                    <!-- Tab 2: Por recoger -->
                    <button
                        type="button"
                        @click="changeTab('por_recoger')"
                        class="px-3.5 py-2 rounded-xl text-xs font-extrabold transition min-h-[44px] flex items-center gap-2 shrink-0 select-none"
                        :class="activeTab === 'por_recoger' ? 'bg-white text-blue-950 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    >
                        <i class="fa-solid fa-hand-holding-hand text-xs" :class="activeTab === 'por_recoger' ? 'text-blue-900' : 'text-slate-400'"></i>
                        <span>Por recoger</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black" :class="activeTab === 'por_recoger' ? 'bg-blue-100 text-blue-950' : 'bg-slate-200 text-slate-600'">
                            {{ tabCounts.por_recoger }}
                        </span>
                    </button>

                    <!-- Tab 3: Por entregar -->
                    <button
                        type="button"
                        @click="changeTab('por_entregar')"
                        class="px-3.5 py-2 rounded-xl text-xs font-extrabold transition min-h-[44px] flex items-center gap-2 shrink-0 select-none"
                        :class="activeTab === 'por_entregar' ? 'bg-white text-blue-950 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    >
                        <i class="fa-solid fa-dolly text-xs" :class="activeTab === 'por_entregar' ? 'text-blue-900' : 'text-slate-400'"></i>
                        <span>Por entregar</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black" :class="activeTab === 'por_entregar' ? 'bg-blue-100 text-blue-950' : 'bg-slate-200 text-slate-600'">
                            {{ tabCounts.por_entregar }}
                        </span>
                    </button>

                    <!-- Tab 4: En proceso -->
                    <button
                        type="button"
                        @click="changeTab('en_proceso')"
                        class="px-3.5 py-2 rounded-xl text-xs font-extrabold transition min-h-[44px] flex items-center gap-2 shrink-0 select-none"
                        :class="activeTab === 'en_proceso' ? 'bg-white text-blue-950 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    >
                        <i class="fa-solid fa-spinner text-xs" :class="activeTab === 'en_proceso' ? 'text-blue-900' : 'text-slate-400'"></i>
                        <span>En proceso</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black" :class="activeTab === 'en_proceso' ? 'bg-blue-100 text-blue-950' : 'bg-slate-200 text-slate-600'">
                            {{ tabCounts.en_proceso }}
                        </span>
                    </button>
                </div>

                <!-- Acceso Secundario: Atendidos (Historial) -->
                <div class="shrink-0 flex items-center justify-end">
                    <button
                        type="button"
                        @click="changeTab('atendidos')"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-1.5 border"
                        :class="activeTab === 'atendidos' ? 'bg-slate-900 text-white border-slate-900 shadow-xs' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                    >
                        <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                        <span>Atendidos</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold" :class="activeTab === 'atendidos' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600'">
                            {{ tabCounts.atendidos }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 3. FILTRO ÚNICO DE ZONAS (Todas, Norte, Sur, Este, Oeste, Sin zona)        -->
            <!-- ========================================================================= -->
            <div class="flex items-center gap-1.5 overflow-x-auto py-1">
                <span class="text-xs font-bold text-slate-400 px-1 uppercase tracking-wider flex items-center gap-1 shrink-0">
                    <i class="fa-solid fa-location-dot"></i> Zona:
                </span>

                <button
                    type="button"
                    @click="changeZone('all')"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-1.5 shrink-0 select-none"
                    :class="activeZone === 'all' ? 'bg-blue-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                >
                    <span>Todas</span>
                    <span class="px-1.5 py-0.5 rounded-md text-[10px]" :class="activeZone === 'all' ? 'bg-blue-800 text-white' : 'bg-white text-slate-600'">
                        {{ zoneCounts.all }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="changeZone('north')"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-1.5 shrink-0 select-none"
                    :class="activeZone === 'north' ? 'bg-blue-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                >
                    <span>Norte</span>
                    <span class="px-1.5 py-0.5 rounded-md text-[10px]" :class="activeZone === 'north' ? 'bg-blue-800 text-white' : 'bg-white text-slate-600'">
                        {{ zoneCounts.north }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="changeZone('south')"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-1.5 shrink-0 select-none"
                    :class="activeZone === 'south' ? 'bg-blue-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                >
                    <span>Sur</span>
                    <span class="px-1.5 py-0.5 rounded-md text-[10px]" :class="activeZone === 'south' ? 'bg-blue-800 text-white' : 'bg-white text-slate-600'">
                        {{ zoneCounts.south }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="changeZone('east')"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-1.5 shrink-0 select-none"
                    :class="activeZone === 'east' ? 'bg-blue-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                >
                    <span>Este</span>
                    <span class="px-1.5 py-0.5 rounded-md text-[10px]" :class="activeZone === 'east' ? 'bg-blue-800 text-white' : 'bg-white text-slate-600'">
                        {{ zoneCounts.east }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="changeZone('west')"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-1.5 shrink-0 select-none"
                    :class="activeZone === 'west' ? 'bg-blue-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                >
                    <span>Oeste</span>
                    <span class="px-1.5 py-0.5 rounded-md text-[10px]" :class="activeZone === 'west' ? 'bg-blue-800 text-white' : 'bg-white text-slate-600'">
                        {{ zoneCounts.west }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="changeZone('none')"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-1.5 shrink-0 select-none"
                    :class="activeZone === 'none' ? 'bg-blue-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                >
                    <span>Sin zona</span>
                    <span class="px-1.5 py-0.5 rounded-md text-[10px]" :class="activeZone === 'none' ? 'bg-blue-800 text-white' : 'bg-white text-slate-600'">
                        {{ zoneCounts.none }}
                    </span>
                </button>
            </div>

            <!-- ========================================================================= -->
            <!-- 4. CUADRÍCULA DE TARJETAS (1 col vertical, 2 cols horizontal en Tablet)    -->
            <!-- ========================================================================= -->
            <div v-if="items.length === 0" class="bg-white rounded-3xl border border-slate-200/80 p-10 text-center shadow-2xs space-y-3">
                <div class="h-14 w-14 mx-auto rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-inbox"></i>
                </div>
                <h3 class="text-base font-bold text-slate-800">No hay pedidos disponibles</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">
                    No se encontraron pedidos en la pestaña <span class="font-bold text-slate-700">{{ activeTab }}</span> para la zona seleccionada.
                </p>
                <div class="pt-2 flex items-center justify-center gap-2">
                    <button
                        v-if="activeZone !== 'all'"
                        @click="changeZone('all')"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 transition min-h-[44px]"
                    >
                        Ver todas las zonas
                    </button>
                    <Link
                        :href="route('repartidor.direct-service.create')"
                        class="px-4 py-2 rounded-xl text-xs font-bold bg-blue-900 hover:bg-blue-800 text-white transition min-h-[44px] inline-flex items-center gap-1.5"
                    >
                        <i class="fa-solid fa-plus text-sky-400"></i>
                        <span>Pedido al paso</span>
                    </Link>
                </div>
            </div>

            <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div
                    v-for="item in items"
                    :key="item.id || item.stop_id"
                    class="bg-white rounded-3xl border border-slate-200/90 p-4 sm:p-5 shadow-2xs hover:border-slate-300 transition flex flex-col justify-between space-y-3"
                >
                    <!-- Sección Superior: Cliente, Zona, Operación y Dirección Corta -->
                    <div class="space-y-2">
                        <!-- Línea 1: Cliente · Zona (ej. Juana · Norte) -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <h3 class="text-base sm:text-lg font-black text-slate-900 leading-snug">
                                    {{ item.customer_nickname }}
                                    <span class="text-slate-400 font-normal mx-1">·</span>
                                    <span class="text-sm font-bold text-slate-600">{{ item.zone_label }}</span>
                                </h3>
                                <p v-if="item.customer_name && item.customer_name !== item.customer_nickname" class="text-xs text-slate-500 truncate">
                                    {{ item.customer_name }}
                                </p>
                            </div>
                            <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 shrink-0">
                                {{ item.customer_code }}
                            </span>
                        </div>

                        <!-- Línea 2: Operación y Cantidad (ej. Devolver 2 fijos, Entregar 3 de cambio, Recoger 2 fijos) -->
                        <p class="text-base font-bold text-slate-800 leading-snug">
                            {{ getOperationText(item) }}
                        </p>

                        <!-- Línea 3: Dirección Corta (ej. Calle 21 entre 30 y 32) -->
                        <div class="text-xs sm:text-sm text-slate-600 flex items-start gap-1.5 bg-slate-50/70 p-2.5 rounded-xl border border-slate-100">
                            <i class="fa-solid fa-location-dot text-slate-400 mt-0.5 shrink-0 text-xs"></i>
                            <span class="font-medium text-slate-700 leading-normal">{{ item.address_short }}</span>
                        </div>

                        <!-- Distintivo de Estado de Cobro (Sólo si tiene deuda o límite alcanzado) -->
                        <div v-if="item.traffic_light?.color === 'red'" class="p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2">
                            <i class="fa-solid fa-triangle-exclamation text-rose-600 text-sm shrink-0"></i>
                            <span>Límite de fiado superado ({{ item.pending_debt_bottles }}/{{ item.traffic_light?.limit }} bot.) · Cobro requerido</span>
                        </div>
                        <div v-else-if="item.traffic_light?.color === 'yellow'" class="p-2 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-amber-500 shrink-0"></span>
                            <span>Deuda: ${{ item.pending_debt_amount }} ({{ item.pending_debt_bottles }} bot.)</span>
                        </div>

                        <!-- Desglose de Operaciones adicionales si aplica -->
                        <div v-if="item.breakdown && item.breakdown.length > 1" class="space-y-1 p-2 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                            <div v-for="(b, bIdx) in item.breakdown" :key="bIdx" class="flex items-center gap-1.5 text-slate-700">
                                <i class="fa-solid fa-circle-dot text-[8px] text-blue-900"></i>
                                <span class="font-medium">{{ b }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Sección Inferior: Acción y Estado -->
                    <div class="pt-3 border-t border-slate-100">
                        <!-- Caso 1: Bloqueado (ej. En preparación en Planta · Ver detalle) -->
                        <template v-if="item.action_type === 'blocked' || !item.can_execute">
                            <div class="flex items-center justify-between gap-2 text-sm">
                                <span class="text-xs sm:text-sm font-bold text-amber-800 flex items-center gap-1.5">
                                    <i class="fa-solid fa-clock text-amber-600 text-xs"></i>
                                    <span>{{ item.blocked_reason || 'En preparación en Planta' }}</span>
                                </span>
                                <button
                                    @click="openDetailModal(item)"
                                    type="button"
                                    class="text-xs sm:text-sm font-bold text-blue-900 hover:text-blue-700 underline underline-offset-2 min-h-[44px] inline-flex items-center px-1"
                                >
                                    Ver detalle
                                </button>
                            </div>
                        </template>

                        <!-- Caso 2: Carga propuesta desde planta (Revisar carga + Ver detalle) -->
                        <template v-else-if="item.action_type === 'review_load'">
                            <div class="flex items-center justify-between gap-2">
                                <Link
                                    :href="route('repartidor.truck-load.index')"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-amber-600 hover:bg-amber-700 text-white active:scale-95 transition min-h-[44px] shadow-2xs"
                                >
                                    <i class="fa-solid fa-boxes-stacked"></i>
                                    <span>Revisar carga</span>
                                </Link>
                                <button
                                    @click="openDetailModal(item)"
                                    type="button"
                                    class="text-xs sm:text-sm font-bold text-slate-600 hover:text-slate-900 px-3 min-h-[44px] inline-flex items-center"
                                >
                                    Ver detalle
                                </button>
                            </div>
                        </template>

                        <!-- Caso 3: En pestaña "En proceso" (Parada activa en ruta) -->
                        <template v-else-if="activeTab === 'en_proceso' || (item.stop_id && item.stop_status === 'pending')">
                            <div class="flex items-center gap-2">
                                <Link
                                    :href="route('repartidor.stops.serve', { stop: item.stop_id, return_tab: activeTab, return_zone: activeZone })"
                                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-black bg-blue-900 hover:bg-blue-800 text-white active:scale-95 transition min-h-[44px] shadow-2xs"
                                >
                                    <i class="fa-solid fa-play text-sky-400 text-xs"></i>
                                    <span>Atender parada</span>
                                </Link>
                                <button
                                    @click="openFailModal(item)"
                                    type="button"
                                    class="px-3 py-2.5 rounded-xl text-xs font-bold bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 active:scale-95 transition min-h-[44px] flex items-center"
                                    title="Marcar visita fallida"
                                >
                                    <i class="fa-solid fa-ban"></i>
                                    <span class="hidden sm:inline ml-1">Fallida</span>
                                </button>
                                <button
                                    @click="openDetailModal(item)"
                                    type="button"
                                    class="text-xs sm:text-sm font-bold text-slate-600 hover:text-slate-900 px-2 min-h-[44px] inline-flex items-center"
                                >
                                    Ver detalle
                                </button>
                            </div>
                        </template>

                        <!-- Caso 4: En pestaña "Atendidos" (Consulta histórica) -->
                        <template v-else-if="activeTab === 'atendidos'">
                            <div class="flex items-center justify-between text-xs sm:text-sm">
                                <span class="font-bold text-emerald-800 flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-check text-emerald-600"></i>
                                    <span>Atendido {{ item.completed_at ? 'a las ' + item.completed_at : '' }}</span>
                                </span>
                                <button
                                    @click="openDetailModal(item)"
                                    type="button"
                                    class="font-bold text-blue-900 hover:text-blue-700 underline underline-offset-2 min-h-[44px] inline-flex items-center px-1"
                                >
                                    Ver detalle
                                </button>
                            </div>
                        </template>

                        <!-- Caso 5: Acciones ejecutables directas (Entregar X fijos, Entregar X de cambio, Recoger X fijos) -->
                        <template v-else>
                            <div class="flex items-center justify-between gap-2">
                                <button
                                    @click="handleServeAction(item)"
                                    :disabled="serveOrderForm.processing"
                                    type="button"
                                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-black bg-blue-900 hover:bg-blue-800 text-white active:scale-95 disabled:opacity-50 transition min-h-[44px] shadow-2xs"
                                >
                                    <i class="fa-solid fa-play text-sky-400 text-xs"></i>
                                    <span>{{ item.action_label }}</span>
                                </button>
                                <button
                                    @click="openDetailModal(item)"
                                    type="button"
                                    class="text-xs sm:text-sm font-bold text-slate-600 hover:text-slate-900 px-3 min-h-[44px] inline-flex items-center"
                                >
                                    Ver detalle
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

        </div>

        <!-- ========================================================================= -->
        <!-- MODAL VER DETALLE DEL PEDIDO Y CLIENTE                                    -->
        <!-- ========================================================================= -->
        <div
            v-if="showDetailModal && selectedDetailItem"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-3xl max-w-lg w-full p-5 sm:p-6 shadow-2xl border border-slate-100 space-y-4 max-h-[90vh] overflow-y-auto">
                <!-- Encabezado Modal -->
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="h-8 w-8 rounded-xl bg-blue-900 text-white flex items-center justify-center text-xs">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">
                                {{ selectedDetailItem.customer_nickname }}
                            </h3>
                            <span class="text-xs font-mono text-slate-500">{{ selectedDetailItem.customer_code }}</span>
                        </div>
                    </div>
                    <button @click="closeDetailModal" type="button" class="text-slate-400 hover:text-slate-600 p-2 min-h-[44px] min-w-[44px] flex items-center justify-center">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <!-- Contenido Detallado -->
                <div class="space-y-3.5 text-xs">
                    <!-- Ficha del Pedido -->
                    <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-mono font-bold text-slate-700 text-sm">{{ selectedDetailItem.folio }}</span>
                            <span class="px-2 py-0.5 rounded-md font-bold text-blue-900 bg-blue-50 border border-blue-100">
                                Zona {{ selectedDetailItem.zone_label }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-slate-600">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Fecha pedido</span>
                                <span class="font-semibold text-slate-800">{{ selectedDetailItem.created_at || 'Hoy' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Total 20 Litros</span>
                                <span class="font-black text-slate-900 text-sm">{{ selectedDetailItem.total_bottles }} bot.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Teléfono con Marcación Directa -->
                    <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100 space-y-1">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Teléfono de contacto</span>
                        <div v-if="selectedDetailItem.customer_phone">
                            <a
                                :href="`tel:${selectedDetailItem.customer_phone}`"
                                class="inline-flex items-center gap-2 text-blue-900 hover:text-blue-700 font-black text-sm min-h-[44px] py-1"
                            >
                                <i class="fa-solid fa-phone text-emerald-600"></i>
                                <span>{{ selectedDetailItem.customer_phone }}</span>
                                <span class="text-[11px] font-normal text-slate-500">(Tocar para llamar)</span>
                            </a>
                        </div>
                        <div v-else class="text-slate-500 italic">
                            Sin teléfono registrado
                        </div>
                    </div>

                    <!-- Dirección Completa y Referencias -->
                    <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Dirección completa</span>
                            <p class="font-bold text-slate-800 text-xs sm:text-sm mt-0.5">
                                {{ selectedDetailItem.address_summary }}
                            </p>
                        </div>
                        <div v-if="selectedDetailItem.address_reference" class="pt-1 border-t border-slate-200/60">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Referencias de entrega</span>
                            <p class="text-slate-700 italic mt-0.5">{{ selectedDetailItem.address_reference }}</p>
                        </div>
                        <div v-if="selectedDetailItem.notes" class="pt-1 border-t border-slate-200/60">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Notas del pedido</span>
                            <p class="text-slate-700 mt-0.5">{{ selectedDetailItem.notes }}</p>
                        </div>
                    </div>

                    <!-- Desglose de Botellones -->
                    <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Desglose de botellones (20L)</span>
                        <div class="grid grid-cols-2 gap-2 text-slate-700">
                            <div v-if="selectedDetailItem.exchange_quantity > 0">
                                <span class="font-semibold text-slate-500">Cambio solicitado:</span>
                                <span class="font-bold text-slate-900 ml-1">{{ selectedDetailItem.exchange_quantity }}</span>
                            </div>
                            <div v-if="selectedDetailItem.fixed_quantity > 0">
                                <span class="font-semibold text-slate-500">Fijos cliente:</span>
                                <span class="font-bold text-slate-900 ml-1">{{ selectedDetailItem.fixed_quantity }}</span>
                                <span v-if="selectedDetailItem.fixed_to_collect > 0" class="block text-[11px] text-amber-800 font-semibold">
                                    ({{ selectedDetailItem.fixed_to_collect }} por recoger)
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Razón de Bloqueo si aplica -->
                    <div v-if="selectedDetailItem.blocked_reason" class="p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 space-y-1">
                        <span class="text-[10px] uppercase font-bold text-rose-700 block">Situación operativa</span>
                        <p class="font-bold flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                            <span>{{ selectedDetailItem.blocked_reason }}</span>
                        </p>
                    </div>
                </div>

                <!-- Botones de Cierre / Acción en Modal -->
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button
                        @click="closeDetailModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 min-h-[44px]"
                    >
                        Cerrar
                    </button>
                    <button
                        v-if="selectedDetailItem.can_execute && activeTab !== 'atendidos'"
                        @click="() => { const item = selectedDetailItem; closeDetailModal(); handleServeAction(item); }"
                        type="button"
                        class="px-5 py-2.5 rounded-xl text-xs sm:text-sm font-black bg-blue-900 text-white hover:bg-blue-800 active:scale-95 transition min-h-[44px] shadow-2xs"
                    >
                        <i class="fa-solid fa-play text-sky-400 text-xs mr-1"></i>
                        <span>{{ selectedDetailItem.action_label }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL RECOLECCIÓN RÁPIDA DE FIJOS VACÍOS                                   -->
        <!-- ========================================================================= -->
        <div
            v-if="showCollectModal && selectedCollectItem"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-3xl max-w-sm w-full p-5 shadow-2xl border border-slate-100 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-hand-holding-hand text-blue-900"></i>
                            <span>Recoger fijos</span>
                        </h3>
                        <p class="text-xs text-slate-500">{{ selectedCollectItem.customer_nickname }} ({{ selectedCollectItem.folio }})</p>
                    </div>
                    <button @click="closeCollectModal" type="button" class="text-slate-400 hover:text-slate-600 p-2 min-h-[44px] min-w-[44px] flex items-center justify-center">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <form @submit.prevent="submitCollectFixed" class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Cantidad de fijos a recoger *</label>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="collectForm.quantity = Math.max(1, collectForm.quantity - 1)"
                                class="h-11 w-11 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-lg flex items-center justify-center min-h-[44px] min-w-[44px]"
                            >
                                -
                            </button>
                            <input
                                v-model.number="collectForm.quantity"
                                type="number"
                                min="1"
                                :max="selectedCollectItem.fixed_to_collect || selectedCollectItem.fixed_quantity"
                                class="flex-1 text-center text-lg font-black rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900 h-11"
                                required
                            />
                            <button
                                type="button"
                                @click="collectForm.quantity = Math.min(selectedCollectItem.fixed_to_collect || selectedCollectItem.fixed_quantity, collectForm.quantity + 1)"
                                class="h-11 w-11 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-lg flex items-center justify-center min-h-[44px] min-w-[44px]"
                            >
                                +
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">
                            Máximo a recoger: {{ selectedCollectItem.fixed_to_collect || selectedCollectItem.fixed_quantity }} botellón(es).
                        </p>
                        <p v-if="collectForm.errors.quantity" class="text-xs text-rose-600 mt-1">
                            {{ collectForm.errors.quantity }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Notas de recolección (opcional)</label>
                        <textarea
                            v-model="collectForm.notes"
                            rows="2"
                            placeholder="Estado físico de los envases o notas..."
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button
                            @click="closeCollectModal"
                            type="button"
                            class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 min-h-[44px]"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="collectForm.processing"
                            class="px-4 py-2.5 rounded-xl text-xs font-bold bg-blue-900 hover:bg-blue-800 text-white transition min-h-[44px]"
                        >
                            {{ collectForm.processing ? 'Registrando...' : 'Confirmar recolección' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL PAUSAR POR AVERÍA / INCIDENCIA                                      -->
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
                    <button @click="closeBreakdownModal" type="button" class="text-slate-400 hover:text-slate-600 p-2 min-h-[44px] min-w-[44px] flex items-center justify-center">
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
                            class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 min-h-[44px]"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="breakdownForm.processing"
                            class="px-4 py-2.5 rounded-xl text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white transition min-h-[44px]"
                        >
                            {{ breakdownForm.processing ? 'Registrando...' : 'Confirmar avería' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL VISITA FALLIDA                                                      -->
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
                    <button @click="closeFailModal" type="button" class="text-slate-400 hover:text-slate-600 p-2 min-h-[44px] min-w-[44px] flex items-center justify-center">
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
                        Nota: Esta acción registra la visita como fallida y conserva el pedido para reprogramación futura sin cancelarlo.
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button
                            @click="closeFailModal"
                            type="button"
                            class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 min-h-[44px]"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="failForm.processing"
                            class="px-4 py-2.5 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white transition min-h-[44px]"
                        >
                            {{ failForm.processing ? 'Guardando...' : 'Registrar falla' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- 5. BARRA DE NAVEGACIÓN INFERIOR MÓVIL/TABLET (4 accesos unificados)       -->
        <!-- ========================================================================= -->
        <RepartidorBottomNav />
    </AuthenticatedLayout>
</template>
