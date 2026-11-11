<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },
    breakdown: {
        type: Object,
        required: true,
    },
    pendingIncidents: {
        type: Array,
        default: () => [],
    },
    resolvedIncidents: {
        type: Array,
        default: () => [],
    },
    movements: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            movement_type: 'all',
            date_from: '',
            date_to: '',
        }),
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

// =========================================================================
// 1. EDITAR META DE CAMBIOS
// =========================================================================
const showTargetModal = ref(false);
const targetForm = useForm({
    target_quantity: props.summary.target_quantity || 40,
    notes: '',
});

const openTargetModal = () => {
    targetForm.target_quantity = props.summary.target_quantity || 40;
    targetForm.notes = '';
    targetForm.clearErrors();
    showTargetModal.value = true;
};

const submitTarget = () => {
    if (targetForm.processing) return;
    targetForm.put(route('admin.exchange-inventory.target.update'), {
        preserveScroll: true,
        onSuccess: () => {
            showTargetModal.value = false;
        },
    });
};

// =========================================================================
// 2. REGISTRAR BOTELLONES NUEVOS (ALTA FÍSICA)
// =========================================================================
const showNewBottlesModal = ref(false);
const newBottlesForm = useForm({
    quantity: 1,
    bottle_condition: 'empty',
    reason: 'Reposición de envase',
    notes: '',
});

const openNewBottlesModal = () => {
    newBottlesForm.quantity = 1;
    newBottlesForm.bottle_condition = 'empty';
    newBottlesForm.reason = 'Reposición de envase';
    newBottlesForm.notes = '';
    newBottlesForm.clearErrors();
    showNewBottlesModal.value = true;
};

const submitNewBottles = () => {
    if (newBottlesForm.processing) return;
    newBottlesForm.post(route('admin.exchange-inventory.new-bottles.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showNewBottlesModal.value = false;
        },
    });
};

// =========================================================================
// 3. REPORTAR INCONVENIENTE (BOTÓN ÁMBAR)
// =========================================================================
const showInconvenienceModal = ref(false);
const inconvenienceForm = useForm({
    quantity: 1,
    origin_stage: 'plant_empty',
    reason: 'rotura',
    location_status: 'separado',
    notes: '',
});

const openInconvenienceModal = () => {
    inconvenienceForm.quantity = 1;
    inconvenienceForm.origin_stage = 'plant_empty';
    inconvenienceForm.reason = 'rotura';
    inconvenienceForm.location_status = 'separado';
    inconvenienceForm.notes = '';
    inconvenienceForm.clearErrors();
    showInconvenienceModal.value = true;
};

const submitInconvenience = () => {
    if (inconvenienceForm.processing) return;
    inconvenienceForm.post(route('admin.exchange-inventory.inconveniences.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showInconvenienceModal.value = false;
        },
    });
};

// =========================================================================
// 4. REVISAR Y RESOLVER INCIDENCIAS (BAJA O RECUPERACIÓN)
// =========================================================================
const selectedIncident = ref(null);
const showResolveIncidentModal = ref(false);
const resolveIncidentForm = useForm({
    resolution_type: 'baja',
    recovery_location: 'plant_empty',
    notes: '',
});

const openResolveIncidentModal = (incident, defaultType = 'baja') => {
    selectedIncident.value = incident;
    resolveIncidentForm.resolution_type = defaultType;
    resolveIncidentForm.recovery_location = incident.bottle_condition === 'full' ? 'plant_full' : 'plant_empty';
    resolveIncidentForm.notes = '';
    resolveIncidentForm.clearErrors();
    showResolveIncidentModal.value = true;
};

const submitResolveIncident = () => {
    if (!selectedIncident.value || resolveIncidentForm.processing) return;
    resolveIncidentForm.post(route('admin.exchange-inventory.incidents.resolve', selectedIncident.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showResolveIncidentModal.value = false;
            selectedIncident.value = null;
        },
    });
};

// =========================================================================
// 5. RESOLVER UNIDADES SIN LOCALIZAR
// =========================================================================
const showResolveUnlocatedModal = ref(false);
const resolveUnlocatedForm = useForm({
    quantity: 1,
    resolution_type: 'recuperacion',
    recovery_location: 'plant_empty',
    damage_type: 'rotura',
    notes: '',
});

const openResolveUnlocatedModal = (defaultType = 'recuperacion') => {
    resolveUnlocatedForm.quantity = Math.max(1, props.summary.unlocated_in_review || 1);
    resolveUnlocatedForm.resolution_type = defaultType;
    resolveUnlocatedForm.recovery_location = 'plant_empty';
    resolveUnlocatedForm.damage_type = 'rotura';
    resolveUnlocatedForm.notes = '';
    resolveUnlocatedForm.clearErrors();
    showResolveUnlocatedModal.value = true;
};

const submitResolveUnlocated = () => {
    if (resolveUnlocatedForm.processing) return;
    resolveUnlocatedForm.post(route('admin.exchange-inventory.unlocated.resolve'), {
        preserveScroll: true,
        onSuccess: () => {
            showResolveUnlocatedModal.value = false;
        },
    });
};

// =========================================================================
// 6. FILTROS DE MOVIMIENTOS
// =========================================================================
const filterType = ref(props.filters.movement_type || 'all');
const filterDateFrom = ref(props.filters.date_from || '');
const filterDateTo = ref(props.filters.date_to || '');

const applyFilters = () => {
    router.get(route('admin.exchange-inventory.index'), {
        movement_type: filterType.value,
        date_from: filterDateFrom.value || undefined,
        date_to: filterDateTo.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    filterType.value = 'all';
    filterDateFrom.value = '';
    filterDateTo.value = '';
    applyFilters();
};

// Etiquetas legibles
const getStageLabel = (stage) => {
    const map = {
        recepcion: 'Recepción en Planta',
        almacen_vacios: 'Almacén de vacíos (Planta)',
        lavado: 'Lavado en Planta',
        llenado: 'Llenado en Planta',
        almacen: 'Almacén de llenos (Planta)',
        almacen_planta: 'Almacén de Planta',
        preparado: 'Preparados en Planta',
        carga: 'Carga a camión',
        carga_camion: 'Carga a camión',
        en_camion: 'En el camión',
        camion: 'En el camión',
        ruta: 'En ruta de reparto',
    };
    return map[stage] || stage;
};

const getDamageLabel = (damage) => {
    const map = {
        rotura: 'Roto / quebrado',
        fisura: 'Fisurado / fuga en cuerpo',
        fuga_cuerpo: 'Fisurado / fuga en cuerpo',
        cuello_roto: 'Tapa o cuello dañado',
        golpe_fondo: 'Golpe severo en fondo',
        contaminacion: 'Contaminado / olor',
        contaminacion_quimica: 'Contaminado / olor',
        deformacion: 'Deformado',
        desgaste_extremo: 'Desgaste extremo',
        otro: 'Otro inconveniente',
    };
    return map[damage] || damage;
};

const getMovementTypeInfo = (type) => {
    const map = {
        exchange_new_entry: {
            label: 'Alta de botellones nuevos',
            badge: 'bg-emerald-50 text-emerald-800 border-emerald-200',
            icon: 'fa-solid fa-circle-plus text-emerald-600',
        },
        exchange_write_off: {
            label: 'Baja confirmada',
            badge: 'bg-rose-50 text-rose-800 border-rose-200',
            icon: 'fa-solid fa-circle-minus text-rose-600',
        },
        exchange_recovery: {
            label: 'Recuperación en circuito',
            badge: 'bg-emerald-50 text-emerald-800 border-emerald-200',
            icon: 'fa-solid fa-rotate-left text-emerald-600',
        },
        exchange_target_updated: {
            label: 'Meta actualizada',
            badge: 'bg-blue-50 text-blue-900 border-blue-200',
            icon: 'fa-solid fa-bullseye text-blue-900',
        },
        bottle_incident: {
            label: 'Separado en revisión (Daño)',
            badge: 'bg-amber-50 text-amber-900 border-amber-200',
            icon: 'fa-solid fa-triangle-exclamation text-amber-600',
        },
        exchange_inconvenience_reported: {
            label: 'En revisión (Sin localizar)',
            badge: 'bg-amber-50 text-amber-900 border-amber-200',
            icon: 'fa-solid fa-magnifying-glass text-amber-600',
        },
        plant_initial_count: {
            label: 'Conteo físico inicial',
            badge: 'bg-slate-100 text-slate-800 border-slate-200',
            icon: 'fa-solid fa-clipboard-check text-slate-700',
        },
        plant_washing_started: {
            label: 'Ingreso a preparación (Lavado)',
            badge: 'bg-sky-50 text-sky-900 border-sky-200',
            icon: 'fa-solid fa-soap text-sky-600',
        },
        plant_washing_finished: {
            label: 'Preparación terminada (Llenos)',
            badge: 'bg-emerald-50 text-emerald-800 border-emerald-200',
            icon: 'fa-solid fa-check text-emerald-600',
        },
        truck_load_exchange: {
            label: 'Planta → Camión (Llenos)',
            badge: 'bg-blue-50 text-blue-900 border-blue-200',
            icon: 'fa-solid fa-truck-arrow-right text-blue-900',
        },
        truck_unload_exchange: {
            label: 'Camión → Planta (Vacíos)',
            badge: 'bg-indigo-50 text-indigo-900 border-indigo-200',
            icon: 'fa-solid fa-truck-ramp-box text-indigo-700',
        },
        delivery_refill_to_customer: {
            label: 'Cambio entregado en domicilio',
            badge: 'bg-emerald-50 text-emerald-800 border-emerald-200',
            icon: 'fa-solid fa-house-circle-check text-emerald-600',
        },
        collection_exchange_empty: {
            label: 'Vacío recogido en domicilio',
            badge: 'bg-slate-100 text-slate-800 border-slate-200',
            icon: 'fa-solid fa-bottle-water text-slate-600',
        },
    };
    return map[type] || {
        label: type,
        badge: 'bg-slate-100 text-slate-700 border-slate-200',
        icon: 'fa-solid fa-arrows-rotate text-slate-500',
    };
};

const showResolvedIncidents = ref(false);
</script>

<template>
    <Head title="Inventario de cambios · Supervisión" />

    <AuthenticatedLayout wide>
        <div class="py-5 sm:py-7 max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 space-y-6">

            <!-- Mensajes Flash -->
            <div
                v-if="flashSuccess"
                class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-center gap-3 shadow-2xs"
                role="alert"
            >
                <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-circle-check text-lg"></i>
                </div>
                <p class="text-sm font-bold flex-1">{{ flashSuccess }}</p>
            </div>

            <div
                v-if="flashError || Object.keys(page.props.errors || {}).length > 0"
                class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 flex items-center gap-3 shadow-2xs"
                role="alert"
            >
                <div class="h-10 w-10 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-triangle-exclamation text-lg"></i>
                </div>
                <div class="text-sm font-bold flex-1">
                    <p v-if="flashError">{{ flashError }}</p>
                    <p v-for="(err, key) in page.props.errors" :key="key">{{ err }}</p>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- ENCABEZADO Y ACCIONES DEL ADMINISTRADOR                                   -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-5 sm:p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-blue-900">
                        <span>Administración</span>
                        <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                        <span>Supervisión</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                        Inventario de cambios
                    </h1>
                    <p class="text-sm sm:text-base text-slate-600">
                        Control general de botellones de cambio de 20 L en Planta y camión · Excluye fijos de clientes
                    </p>
                </div>

                <!-- Botones de acción separados y táctiles (mínimo 44x44px) -->
                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <button
                        type="button"
                        @click="openTargetModal"
                        class="min-h-[44px] px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 font-bold text-xs sm:text-sm flex items-center gap-2 transition cursor-pointer shadow-2xs"
                    >
                        <i class="fa-solid fa-bullseye text-blue-900"></i>
                        <span>Editar meta</span>
                    </button>

                    <button
                        type="button"
                        @click="openInconvenienceModal"
                        class="min-h-[44px] px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs sm:text-sm flex items-center gap-2 transition cursor-pointer shadow-2xs"
                    >
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>Reportar inconveniente</span>
                    </button>

                    <button
                        type="button"
                        @click="openNewBottlesModal"
                        class="min-h-[44px] px-4 py-2.5 rounded-xl bg-[#0E1E42] hover:bg-blue-900 text-white font-bold text-xs sm:text-sm flex items-center gap-2 transition cursor-pointer shadow-2xs"
                    >
                        <i class="fa-solid fa-plus text-sky-400"></i>
                        <span>Registrar botellones nuevos</span>
                    </button>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 1. TARJETAS PRINCIPALES DE RESUMEN (ACTIVOS/META, LLENOS, EN REVISIÓN)    -->
            <!-- ========================================================================= -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                <!-- TARJETA 1: ACTIVOS / META + DIFERENCIA FRENTE A LA META -->
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-5 flex flex-col justify-between space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block">
                                Activos / Meta
                            </span>
                            <div class="flex items-baseline gap-1.5 mt-1">
                                <span class="text-3xl font-black text-slate-900">{{ summary.active_total }}</span>
                                <span class="text-lg font-bold text-slate-400">/ {{ summary.target_quantity }}</span>
                                <span class="text-xs font-semibold text-slate-500">botellones</span>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="openTargetModal"
                            class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center transition cursor-pointer shrink-0"
                            title="Editar meta de cambios"
                        >
                            <i class="fa-solid fa-pen text-xs"></i>
                        </button>
                    </div>

                    <div class="space-y-1.5">
                        <!-- Etiqueta de diferencia frente a la meta -->
                        <div
                            :class="[
                                'inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-bold border',
                                summary.diff_status === 'below'
                                    ? 'bg-amber-50 text-amber-900 border-amber-200'
                                    : (summary.diff_status === 'above'
                                        ? 'bg-sky-50 text-sky-900 border-sky-200'
                                        : 'bg-emerald-50 text-emerald-800 border-emerald-200')
                            ]"
                        >
                            <i
                                :class="[
                                    'fa-solid text-xs',
                                    summary.diff_status === 'below'
                                        ? 'fa-arrow-trend-down text-amber-600'
                                        : (summary.diff_status === 'above' ? 'fa-arrow-trend-up text-sky-600' : 'fa-circle-check text-emerald-600')
                                ]"
                            ></i>
                            <span>{{ summary.diff_label }}</span>
                        </div>

                        <p class="text-[11px] text-slate-500 leading-snug">
                            Meta de cambios: <strong class="text-slate-700">{{ summary.target_quantity }} u.</strong>
                            <span v-if="summary.target_updated_by"> · Editada por {{ summary.target_updated_by }}</span>
                        </p>
                    </div>
                </div>

                <!-- TARJETA 2: LLENOS EN PLANTA -->
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-5 flex flex-col justify-between space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block">
                                Llenos en Planta
                            </span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <span class="text-3xl font-black text-emerald-700">{{ summary.plant_full_available }}</span>
                                <span class="text-xs font-semibold text-slate-500">llenos libres</span>
                            </div>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-industry"></i>
                        </div>
                    </div>

                    <div class="space-y-1 text-xs">
                        <div v-if="breakdown.plant.full_reserved > 0" class="text-amber-800 font-bold flex items-center gap-1.5">
                            <i class="fa-solid fa-clock text-amber-600"></i>
                            <span>{{ breakdown.plant.full_reserved }} llenos pendientes de confirmar</span>
                        </div>
                        <p class="text-slate-500">
                            Vacíos aptos: <strong class="text-slate-800">{{ breakdown.plant.empty_available }}</strong> ·
                            En preparación: <strong class="text-slate-800">{{ breakdown.plant.in_preparation }}</strong>
                        </p>
                    </div>
                </div>

                <!-- TARJETA 3: LLENOS EN CAMIÓN -->
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-5 flex flex-col justify-between space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block">
                                Llenos en camión
                            </span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <span class="text-3xl font-black text-[#0E1E42]">{{ summary.truck_full_available }}</span>
                                <span class="text-xs font-semibold text-slate-500">disponibles en ruta</span>
                            </div>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-blue-50 text-[#0E1E42] flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-truck"></i>
                        </div>
                    </div>

                    <div class="space-y-1 text-xs">
                        <p class="text-slate-600 font-semibold">
                            Llenos disponibles totales: <strong class="text-slate-900">{{ summary.available_full_total }} u.</strong>
                            ({{ summary.plant_full_available }} Planta + {{ summary.truck_full_available }} camión)
                        </p>
                        <p class="text-slate-500">
                            Vacíos recogidos en camión: <strong class="text-slate-800">{{ breakdown.truck.empty_collected }}</strong>
                        </p>
                    </div>
                </div>

                <!-- TARJETA 4: EN REVISIÓN (DAÑADOS SEPARADOS Y SIN LOCALIZAR) -->
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-5 flex flex-col justify-between space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block">
                                En revisión
                            </span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <span
                                    class="text-3xl font-black"
                                    :class="summary.in_review_total > 0 ? 'text-amber-600' : 'text-slate-900'"
                                >
                                    {{ summary.in_review_total }}
                                </span>
                                <span class="text-xs font-semibold text-slate-500">fuera de activos</span>
                            </div>
                        </div>
                        <div
                            class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0"
                            :class="summary.in_review_total > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-500'"
                        >
                            <i class="fa-solid fa-magnifying-glass-chart"></i>
                        </div>
                    </div>

                    <!-- Muestra ambas situaciones por separado -->
                    <div class="grid grid-cols-2 gap-2 pt-1 text-xs">
                        <div class="p-2 rounded-xl bg-slate-50 border border-slate-200/80">
                            <span class="text-[11px] text-slate-500 block">Dañados separados</span>
                            <span class="font-black text-slate-900 text-sm">
                                {{ summary.damaged_in_review }} {{ summary.damaged_in_review === 1 ? 'dañado en revisión' : 'dañados' }}
                            </span>
                        </div>
                        <div class="p-2 rounded-xl bg-slate-50 border border-slate-200/80">
                            <span class="text-[11px] text-slate-500 block">Sin localizar</span>
                            <span class="font-black text-slate-900 text-sm">
                                {{ summary.unlocated_in_review }} {{ summary.unlocated_in_review === 1 ? 'unidad' : 'unidades' }}
                            </span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ========================================================================= -->
            <!-- 2. DESGLOSE DE UBICACIÓN Y CONSERVACIÓN DEL INVENTARIO                    -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-5 sm:p-6 space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100">
                    <div>
                        <h2 class="text-lg font-black text-slate-900">
                            Ubicación y disponibilidad en el circuito (20 L)
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-500">
                            Cada botellón ocupa una sola posición verificada · Las propuestas pendientes se conservan en su origen hasta confirmar recepción
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-50 text-[#0E1E42] text-xs font-bold self-start sm:self-auto">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Cambios activos: {{ summary.active_total }} u.</span>
                    </span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- COLUMNA 1: EN PLANTA -->
                    <div class="rounded-2xl border border-slate-200 p-4 space-y-3 bg-slate-50/50">
                        <div class="flex items-center justify-between border-b border-slate-200/80 pb-2.5">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-warehouse text-[#0E1E42]"></i>
                                <h3 class="font-black text-slate-900 text-base">En Planta</h3>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-lg bg-white border border-slate-200 text-xs font-black text-slate-800">
                                {{ breakdown.plant.subtotal }} activos
                            </span>
                        </div>

                        <div class="space-y-2 text-sm">
                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-white border border-slate-200/80">
                                <span class="text-slate-700 font-medium">Vacíos aptos</span>
                                <span class="font-black text-slate-900 text-base">{{ breakdown.plant.empty_available }}</span>
                            </div>
                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-white border border-slate-200/80">
                                <span class="text-slate-700 font-medium">En preparación (lavado y llenado)</span>
                                <span class="font-black text-blue-900 text-base">{{ breakdown.plant.in_preparation }}</span>
                            </div>
                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-emerald-50/70 border border-emerald-200/80">
                                <span class="text-emerald-950 font-bold">Llenos libres</span>
                                <span class="font-black text-emerald-700 text-base">{{ breakdown.plant.full_available }}</span>
                            </div>
                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-amber-50/70 border border-amber-200/80">
                                <div>
                                    <span class="text-amber-950 font-semibold block">Llenos reservados para carga</span>
                                    <span class="text-[11px] text-amber-800">
                                        {{ breakdown.plant.full_reserved }} llenos pendientes de confirmar
                                    </span>
                                </div>
                                <span class="font-black text-amber-800 text-base">{{ breakdown.plant.full_reserved }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- COLUMNA 2: EN EL CAMIÓN -->
                    <div class="rounded-2xl border border-slate-200 p-4 space-y-3 bg-slate-50/50">
                        <div class="flex items-center justify-between border-b border-slate-200/80 pb-2.5">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-truck text-[#0E1E42]"></i>
                                <h3 class="font-black text-slate-900 text-base">En el camión</h3>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-lg bg-white border border-slate-200 text-xs font-black text-slate-800">
                                {{ breakdown.truck.subtotal }} activos
                            </span>
                        </div>

                        <div class="space-y-2 text-sm">
                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-blue-50/70 border border-blue-200/80">
                                <span class="text-blue-950 font-bold">Cambios llenos disponibles</span>
                                <span class="font-black text-[#0E1E42] text-base">{{ breakdown.truck.full_available }}</span>
                            </div>
                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-white border border-slate-200/80">
                                <span class="text-slate-700 font-medium">Vacíos recogidos (Total en camión)</span>
                                <span class="font-black text-slate-900 text-base">{{ breakdown.truck.empty_collected }}</span>
                            </div>
                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-white border border-slate-200/80 text-xs">
                                <span class="text-slate-600">Vacíos libres en camión (sin entrega propuesta)</span>
                                <span class="font-bold text-slate-800">{{ breakdown.truck.empty_free }}</span>
                            </div>
                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-amber-50/70 border border-amber-200/80 text-xs">
                                <span class="text-amber-900 font-semibold">
                                    Comprometidos en entrega a Planta pendiente de confirmar
                                </span>
                                <span class="font-black text-amber-800 text-sm">{{ breakdown.truck.empty_committed_in_unload }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- COLUMNA 3: EN REVISIÓN Y CONCILIACIÓN -->
                    <div class="rounded-2xl border border-slate-200 p-4 space-y-3 bg-slate-50/50 flex flex-col justify-between">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between border-b border-slate-200/80 pb-2.5">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
                                    <h3 class="font-black text-slate-900 text-base">En revisión</h3>
                                </div>
                                <span class="px-2.5 py-0.5 rounded-lg bg-amber-100 text-amber-900 text-xs font-black">
                                    {{ breakdown.in_review.subtotal }} en revisión
                                </span>
                            </div>

                            <div class="space-y-2 text-sm">
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-white border border-slate-200/80">
                                    <div>
                                        <span class="text-slate-800 font-semibold block">Dañados con ubicación confirmada</span>
                                        <span class="text-[11px] text-slate-500">Separados de los disponibles</span>
                                    </div>
                                    <span class="font-black text-amber-700 text-base">{{ breakdown.in_review.damaged_separated }}</span>
                                </div>

                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-white border border-slate-200/80">
                                    <div>
                                        <span class="text-slate-800 font-semibold block">Faltantes sin localizar</span>
                                        <span class="text-[11px] text-slate-500">Pendientes de aclaración física</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-black text-amber-700 text-base">{{ breakdown.in_review.unlocated }}</span>
                                        <button
                                            v-if="breakdown.in_review.unlocated > 0"
                                            type="button"
                                            @click="openResolveUnlocatedModal('recuperacion')"
                                            class="px-2.5 py-1 rounded-lg bg-[#0E1E42] text-white text-xs font-bold hover:bg-blue-900 cursor-pointer"
                                        >
                                            Resolver
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Acumulados de Historial -->
                        <div class="pt-3 border-t border-slate-200/80 text-xs text-slate-600 space-y-1">
                            <div class="flex justify-between">
                                <span>Altas registradas en historial:</span>
                                <strong class="text-emerald-700">+{{ breakdown.history_totals.new_entries }} u.</strong>
                            </div>
                            <div class="flex justify-between">
                                <span>Bajas confirmadas en historial:</span>
                                <strong class="text-rose-700">{{ breakdown.history_totals.write_offs }} u.</strong>
                            </div>
                            <div class="flex justify-between">
                                <span>Recuperaciones registradas:</span>
                                <strong class="text-blue-900">+{{ breakdown.history_totals.recoveries }} u.</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 3. REVISAR INCIDENCIAS Y UNIDADES EN REVISIÓN                             -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-5 sm:p-6 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                    <div>
                        <h2 class="text-lg font-black text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-clipboard-list text-amber-600"></i>
                            <span>Revisar incidencias de botellones de cambio</span>
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-500">
                            Confirma la baja definitiva o registra la recuperación cuando un botellón en revisión vuelve a estar apto
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="summary.unlocated_in_review > 0"
                            type="button"
                            @click="openResolveUnlocatedModal('recuperacion')"
                            class="min-h-[44px] px-3.5 py-2 rounded-xl bg-amber-100 hover:bg-amber-200 text-amber-950 font-bold text-xs flex items-center gap-1.5 cursor-pointer"
                        >
                            <i class="fa-solid fa-location-crosshairs"></i>
                            <span>Resolver {{ summary.unlocated_in_review }} sin localizar</span>
                        </button>

                        <button
                            v-if="resolvedIncidents.length > 0"
                            type="button"
                            @click="showResolvedIncidents = !showResolvedIncidents"
                            class="min-h-[44px] px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs cursor-pointer"
                        >
                            {{ showResolvedIncidents ? 'Ocultar resueltas' : `Ver resueltas (${resolvedIncidents.length})` }}
                        </button>
                    </div>
                </div>

                <!-- Lista de incidencias pendientes -->
                <div v-if="pendingIncidents.length === 0 && summary.unlocated_in_review === 0" class="py-8 text-center text-slate-500">
                    <i class="fa-solid fa-circle-check text-2xl text-emerald-500 mb-2"></i>
                    <p class="text-sm font-bold text-slate-700">No hay incidencias ni unidades pendientes en revisión.</p>
                    <p class="text-xs text-slate-400 mt-0.5">Todos los botellones de cambio activos están conciliados.</p>
                </div>

                <div v-else class="space-y-3">
                    <!-- Tarjeta especial si hay unidades sin localizar -->
                    <div
                        v-if="summary.unlocated_in_review > 0"
                        class="p-4 rounded-2xl bg-amber-50/80 border border-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-lg bg-amber-200 text-amber-950 text-xs font-black">
                                    Sin localizar
                                </span>
                                <span class="text-sm font-black text-slate-900">
                                    {{ summary.unlocated_in_review }} {{ summary.unlocated_in_review === 1 ? 'botellón pendiente de localizar' : 'botellones pendientes de localizar' }}
                                </span>
                            </div>
                            <p class="text-xs text-amber-900">
                                Unidades descontadas de los saldos activos por discrepancia en carga, descarga o reporte operativo.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <button
                                type="button"
                                @click="openResolveUnlocatedModal('recuperacion')"
                                class="min-h-[44px] px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold flex items-center gap-1.5 cursor-pointer"
                            >
                                <i class="fa-solid fa-rotate-left"></i>
                                <span>Registrar recuperación</span>
                            </button>
                            <button
                                type="button"
                                @click="openResolveUnlocatedModal('baja')"
                                class="min-h-[44px] px-4 py-2 rounded-xl bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold flex items-center gap-1.5 cursor-pointer"
                            >
                                <i class="fa-solid fa-ban"></i>
                                <span>Confirmar baja</span>
                            </button>
                        </div>
                    </div>

                    <!-- Tarjetas de incidencias de daño físico pendientes -->
                    <div
                        v-for="inc in pendingIncidents"
                        :key="inc.id"
                        class="p-4 rounded-2xl bg-slate-50 border border-slate-200/90 flex flex-col lg:flex-row lg:items-center justify-between gap-4"
                    >
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2.5 py-0.5 rounded-lg bg-amber-100 text-amber-900 border border-amber-200 text-xs font-black">
                                    #{{ inc.id }} · {{ inc.quantity }} {{ inc.quantity === 1 ? 'dañado en revisión' : 'dañados en revisión' }}
                                </span>
                                <span class="px-2.5 py-0.5 rounded-lg bg-white border border-slate-200 text-xs font-bold text-slate-700">
                                    {{ getDamageLabel(inc.damage_type) }}
                                </span>
                                <span class="text-xs text-slate-500 font-medium">
                                    Etapa: <strong class="text-slate-800">{{ getStageLabel(inc.stage) }}</strong>
                                </span>
                            </div>

                            <p class="text-sm font-semibold text-slate-800">
                                {{ inc.notes }}
                            </p>

                            <p class="text-xs text-slate-500">
                                Reportado por <strong class="text-slate-700">{{ inc.reported_by_name }}</strong> · {{ inc.created_at }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <button
                                type="button"
                                @click="openResolveIncidentModal(inc, 'recuperacion')"
                                class="min-h-[44px] px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs flex items-center gap-1.5 transition cursor-pointer shadow-2xs"
                            >
                                <i class="fa-solid fa-rotate-left"></i>
                                <span>Registrar recuperación</span>
                            </button>

                            <button
                                type="button"
                                @click="openResolveIncidentModal(inc, 'baja')"
                                class="min-h-[44px] px-4 py-2 rounded-xl bg-rose-700 hover:bg-rose-800 text-white font-bold text-xs flex items-center gap-1.5 transition cursor-pointer shadow-2xs"
                            >
                                <i class="fa-solid fa-trash-can"></i>
                                <span>Confirmar baja</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Incidencias resueltas recientes (desplegable) -->
                <div v-if="showResolvedIncidents && resolvedIncidents.length > 0" class="pt-4 border-t border-slate-100 space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        Incidencias resueltas recientemente
                    </h3>
                    <div class="divide-y divide-slate-100">
                        <div
                            v-for="rInc in resolvedIncidents"
                            :key="rInc.id"
                            class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs"
                        >
                            <div>
                                <span class="font-bold text-slate-800">
                                    Incidencia #{{ rInc.id }} ({{ rInc.quantity }} u. · {{ getDamageLabel(rInc.damage_type) }})
                                </span>
                                <p class="text-slate-600">{{ rInc.resolution_notes }}</p>
                            </div>
                            <div class="text-slate-400 sm:text-right shrink-0">
                                <div>Resuelta por {{ rInc.resolved_by_name }}</div>
                                <div>{{ rInc.resolved_at }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 4. CONSULTAR MOVIMIENTOS (HISTORIAL AUDITADO CON FILTROS)                 -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-5 sm:p-6 space-y-4">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-3 border-b border-slate-100">
                    <div>
                        <h2 class="text-lg font-black text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left text-[#0E1E42]"></i>
                            <span>Historial de movimientos de cambios</span>
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-500">
                            Registro cronológico de altas, bajas, recuperaciones, traslados, preparación y entregas
                        </p>
                    </div>

                    <!-- Filtros básicos por fecha y tipo de movimiento -->
                    <form @submit.prevent="applyFilters" class="flex flex-wrap items-center gap-2">
                        <select
                            v-model="filterType"
                            @change="applyFilters"
                            class="min-h-[44px] text-xs font-semibold rounded-xl border-slate-300 text-slate-800 focus:border-[#0E1E42] focus:ring-[#0E1E42]"
                        >
                            <option value="all">Todos los movimientos</option>
                            <option value="alta">Altas de botellones nuevos</option>
                            <option value="baja">Bajas confirmadas</option>
                            <option value="recuperacion">Recuperaciones</option>
                            <option value="incidencia">Incidencias / En revisión</option>
                            <option value="preparacion">Preparación en Planta</option>
                            <option value="carga">Cargas Planta → Camión</option>
                            <option value="descarga">Descargas Camión → Planta</option>
                            <option value="domicilio">Cambios en domicilio</option>
                            <option value="meta">Actualizaciones de meta</option>
                        </select>

                        <input
                            v-model="filterDateFrom"
                            type="date"
                            @change="applyFilters"
                            class="min-h-[44px] text-xs rounded-xl border-slate-300 text-slate-700"
                            title="Fecha desde"
                        />

                        <input
                            v-model="filterDateTo"
                            type="date"
                            @change="applyFilters"
                            class="min-h-[44px] text-xs rounded-xl border-slate-300 text-slate-700"
                            title="Fecha hasta"
                        />

                        <button
                            v-if="filterType !== 'all' || filterDateFrom || filterDateTo"
                            type="button"
                            @click="clearFilters"
                            class="min-h-[44px] px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold cursor-pointer"
                        >
                            Limpiar
                        </button>
                    </form>
                </div>

                <div v-if="movements.length === 0" class="py-10 text-center text-slate-400">
                    <i class="fa-solid fa-box-open text-2xl mb-2"></i>
                    <p class="text-sm font-semibold">No se encontraron movimientos con los filtros seleccionados.</p>
                </div>

                <!-- Lista de tarjetas/filas compactas (sin tablas anchas en celular) -->
                <div v-else class="divide-y divide-slate-100">
                    <div
                        v-for="mov in movements"
                        :key="mov.id"
                        class="py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 text-xs sm:text-sm"
                    >
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 rounded-xl bg-slate-100 flex items-center justify-center shrink-0 mt-0.5">
                                <i :class="getMovementTypeInfo(mov.movement_type).icon"></i>
                            </div>
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span
                                        class="px-2.5 py-0.5 rounded-lg text-xs font-bold border"
                                        :class="getMovementTypeInfo(mov.movement_type).badge"
                                    >
                                        {{ getMovementTypeInfo(mov.movement_type).label }}
                                    </span>
                                    <span class="font-black text-slate-900">
                                        {{ mov.quantity }} u. (20 L)
                                    </span>
                                    <span
                                        v-if="mov.status === 'proposed'"
                                        class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-900 text-[11px] font-bold"
                                    >
                                        Pendiente de confirmar
                                    </span>
                                </div>

                                <p v-if="mov.notes" class="text-slate-700 text-xs leading-relaxed">
                                    {{ mov.notes }}
                                </p>

                                <p v-if="mov.difference_reason && !mov.notes?.includes(mov.difference_reason)" class="text-amber-800 text-xs font-semibold">
                                    Detalle: {{ mov.difference_reason }}
                                </p>
                            </div>
                        </div>

                        <div class="text-xs text-slate-500 sm:text-right shrink-0 pl-13 sm:pl-0">
                            <div class="font-bold text-slate-700">{{ mov.created_at }}</div>
                            <div>Registró: {{ mov.user_name }}</div>
                            <div v-if="mov.confirmed_by_name && mov.confirmed_by_name !== mov.user_name">
                                Confirmó: {{ mov.confirmed_by_name }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 1: EDITAR META DE CAMBIOS                                           -->
        <!-- ========================================================================= -->
        <div
            v-if="showTargetModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="h-10 w-10 rounded-xl bg-blue-50 text-[#0E1E42] flex items-center justify-center">
                            <i class="fa-solid fa-bullseye"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">Editar meta de cambios</h3>
                            <p class="text-xs text-slate-500">No altera los saldos físicos actuales ({{ summary.active_total }} activos)</p>
                        </div>
                    </div>
                    <button @click="showTargetModal = false" type="button" class="h-9 w-9 rounded-lg text-slate-400 hover:text-slate-700 cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form @submit.prevent="submitTarget" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1.5">
                            Meta operativa de botellones de cambio (20 L) *
                        </label>
                        <input
                            v-model.number="targetForm.target_quantity"
                            type="number"
                            min="1"
                            max="10000"
                            required
                            class="w-full h-12 text-xl font-black text-slate-900 rounded-xl border-slate-300 focus:border-[#0E1E42] focus:ring-[#0E1E42]"
                        />
                        <p v-if="targetForm.errors.target_quantity" class="text-xs text-rose-600 mt-1">
                            {{ targetForm.errors.target_quantity }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1.5">
                            Motivo o nota (opcional)
                        </label>
                        <input
                            v-model="targetForm.notes"
                            type="text"
                            placeholder="Ej: Ajuste de meta para temporada alta"
                            class="w-full h-11 text-sm rounded-xl border-slate-300 focus:border-[#0E1E42] focus:ring-[#0E1E42]"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showTargetModal = false"
                            class="min-h-[44px] px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="targetForm.processing"
                            class="min-h-[44px] px-5 py-2.5 rounded-xl bg-[#0E1E42] hover:bg-blue-900 text-white font-bold text-xs cursor-pointer shadow-2xs"
                        >
                            Guardar meta
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 2: REGISTRAR BOTELLONES NUEVOS (ALTA)                               -->
        <!-- ========================================================================= -->
        <div
            v-if="showNewBottlesModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="h-10 w-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                            <i class="fa-solid fa-circle-plus"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">Registrar botellones nuevos</h3>
                            <p class="text-xs text-slate-500">Incorporación física al inventario de cambios de 20 L</p>
                        </div>
                    </div>
                    <button @click="showNewBottlesModal = false" type="button" class="h-9 w-9 rounded-lg text-slate-400 hover:text-slate-700 cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form @submit.prevent="submitNewBottles" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1.5">
                            Cantidad de botellones nuevos (20 L) *
                        </label>
                        <input
                            v-model.number="newBottlesForm.quantity"
                            type="number"
                            min="1"
                            max="5000"
                            required
                            class="w-full h-12 text-xl font-black text-slate-900 rounded-xl border-slate-300 focus:border-[#0E1E42] focus:ring-[#0E1E42]"
                        />
                        <p v-if="newBottlesForm.errors.quantity" class="text-xs text-rose-600 mt-1">
                            {{ newBottlesForm.errors.quantity }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1.5">
                            Condición real de ingreso a Planta *
                        </label>
                        <div class="grid grid-cols-2 gap-2.5">
                            <button
                                type="button"
                                @click="newBottlesForm.bottle_condition = 'empty'"
                                :class="[
                                    'min-h-[48px] p-3 rounded-xl border text-left transition cursor-pointer',
                                    newBottlesForm.bottle_condition === 'empty'
                                        ? 'bg-blue-50 border-[#0E1E42] text-[#0E1E42] font-bold'
                                        : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'
                                ]"
                            >
                                <span class="text-xs font-black block">Vacío apto</span>
                                <span class="text-[11px] text-slate-500 block">Para lavar y llenar</span>
                            </button>

                            <button
                                type="button"
                                @click="newBottlesForm.bottle_condition = 'full'"
                                :class="[
                                    'min-h-[48px] p-3 rounded-xl border text-left transition cursor-pointer',
                                    newBottlesForm.bottle_condition === 'full'
                                        ? 'bg-emerald-50 border-emerald-700 text-emerald-900 font-bold'
                                        : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'
                                ]"
                            >
                                <span class="text-xs font-black block">Lleno y sellado</span>
                                <span class="text-[11px] text-slate-500 block">Listo para usar</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1.5">
                            Motivo del alta *
                        </label>
                        <select
                            v-model="newBottlesForm.reason"
                            class="w-full h-11 text-sm font-semibold rounded-xl border-slate-300 text-slate-800 focus:border-[#0E1E42] focus:ring-[#0E1E42]"
                        >
                            <option value="Reposición de envase">Reposición de envase</option>
                            <option value="Compra de lote nuevo">Compra de lote nuevo</option>
                            <option value="Incorporación al inventario">Incorporación al inventario</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1.5">
                            Notas adicionales (opcional)
                        </label>
                        <input
                            v-model="newBottlesForm.notes"
                            type="text"
                            placeholder="Ej: Reposición por baja de envase roto"
                            class="w-full h-11 text-sm rounded-xl border-slate-300"
                        />
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 flex items-center justify-between">
                        <span>Cambios activos tras el alta:</span>
                        <strong class="text-slate-900 text-sm">
                            {{ summary.active_total }} → {{ summary.active_total + (Number(newBottlesForm.quantity) || 0) }} u.
                        </strong>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showNewBottlesModal = false"
                            class="min-h-[44px] px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="newBottlesForm.processing"
                            class="min-h-[44px] px-5 py-2.5 rounded-xl bg-[#0E1E42] hover:bg-blue-900 text-white font-bold text-xs cursor-pointer shadow-2xs"
                        >
                            Confirmar alta
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 3: REPORTAR INCONVENIENTE                                           -->
        <!-- ========================================================================= -->
        <div
            v-if="showInconvenienceModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="h-10 w-10 rounded-xl bg-amber-500 text-white flex items-center justify-center">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">Reportar inconveniente</h3>
                            <p class="text-xs text-slate-500">Separa unidades dañadas o sin localizar en «En revisión»</p>
                        </div>
                    </div>
                    <button @click="showInconvenienceModal = false" type="button" class="h-9 w-9 rounded-lg text-slate-400 hover:text-slate-700 cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form @submit.prevent="submitInconvenience" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Cantidad *</label>
                            <input
                                v-model.number="inconvenienceForm.quantity"
                                type="number"
                                min="1"
                                required
                                class="w-full h-11 text-lg font-black text-slate-900 rounded-xl border-slate-300"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Etapa donde ocurrió *</label>
                            <select
                                v-model="inconvenienceForm.origin_stage"
                                class="w-full h-11 text-xs font-semibold rounded-xl border-slate-300 text-slate-800"
                            >
                                <option value="plant_empty">Vacíos en Planta ({{ breakdown.plant.empty_available }})</option>
                                <option value="plant_preparation">En preparación ({{ breakdown.plant.in_preparation }})</option>
                                <option value="plant_full">Llenos en Planta ({{ breakdown.plant.full_available }})</option>
                                <option value="truck_full">Llenos en camión ({{ breakdown.truck.full_available }})</option>
                                <option value="truck_empty">Vacíos en camión ({{ breakdown.truck.empty_collected }})</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Motivo *</label>
                        <select
                            v-model="inconvenienceForm.reason"
                            class="w-full h-11 text-xs font-semibold rounded-xl border-slate-300 text-slate-800"
                        >
                            <option value="rotura">Roto</option>
                            <option value="fisura">Fisurado</option>
                            <option value="tapa_cuello">Tapa o cuello dañado</option>
                            <option value="contaminacion">Contaminado</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Ubicación física del botellón *</label>
                        <select
                            v-model="inconvenienceForm.location_status"
                            class="w-full h-11 text-xs font-semibold rounded-xl border-slate-300 text-slate-800"
                        >
                            <option value="separado">Separado físicamente (Ubicación confirmada)</option>
                            <option value="sin_localizar">Pendiente de aclarar / Sin localizar</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Descripción breve *</label>
                        <input
                            v-model="inconvenienceForm.notes"
                            type="text"
                            required
                            placeholder="Ej: Botellón roto durante lavado en Planta"
                            class="w-full h-11 text-xs rounded-xl border-slate-300"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showInconvenienceModal = false"
                            class="min-h-[44px] px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="inconvenienceForm.processing"
                            class="min-h-[44px] px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs cursor-pointer shadow-2xs"
                        >
                            Guardar reporte
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 4: RESOLVER INCIDENCIA (BAJA O RECUPERACIÓN)                        -->
        <!-- ========================================================================= -->
        <div
            v-if="showResolveIncidentModal && selectedIncident"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-black text-slate-900">
                        {{ resolveIncidentForm.resolution_type === 'baja' ? 'Confirmar baja de botellón' : 'Registrar recuperación' }}
                    </h3>
                    <button @click="showResolveIncidentModal = false" type="button" class="h-9 w-9 rounded-lg text-slate-400 hover:text-slate-700 cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs space-y-1">
                    <div class="font-bold text-slate-900">
                        Incidencia #{{ selectedIncident.id }} · {{ selectedIncident.quantity }} u. ({{ getDamageLabel(selectedIncident.damage_type) }})
                    </div>
                    <div class="text-slate-600">{{ selectedIncident.notes }}</div>
                </div>

                <form @submit.prevent="submitResolveIncident" class="space-y-4">
                    <div v-if="resolveIncidentForm.resolution_type === 'baja'" class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-xs text-rose-900">
                        Al confirmar la <strong>baja</strong>, la unidad sale de «En revisión» y queda registrada como baja en el historial, <strong>sin volver a descontarla</strong> de los cambios activos ({{ summary.active_total }} activos).
                    </div>

                    <div v-else class="space-y-3">
                        <label class="block text-xs font-bold uppercase text-slate-600">
                            Ubicación y condición verificada donde reingresa *
                        </label>
                        <select
                            v-model="resolveIncidentForm.recovery_location"
                            class="w-full h-11 text-xs font-semibold rounded-xl border-slate-300 text-slate-800"
                        >
                            <option value="plant_empty">Vacíos aptos en Planta</option>
                            <option value="plant_full">Llenos libres en Planta</option>
                            <option value="truck_full">Llenos disponibles en el camión</option>
                            <option value="truck_empty">Vacíos recogidos en el camión</option>
                        </select>
                        <p class="text-xs text-emerald-800 bg-emerald-50 p-2.5 rounded-xl border border-emerald-200">
                            Cambios activos pasarán de <strong>{{ summary.active_total }}</strong> a <strong>{{ summary.active_total + selectedIncident.quantity }}</strong> unidades.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">
                            Observaciones de resolución
                        </label>
                        <input
                            v-model="resolveIncidentForm.notes"
                            type="text"
                            :placeholder="resolveIncidentForm.resolution_type === 'baja' ? 'Ej: Envase roto sin posibilidad de uso' : 'Ej: Revisado y lavado, apto para uso'"
                            class="w-full h-11 text-xs rounded-xl border-slate-300"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showResolveIncidentModal = false"
                            class="min-h-[44px] px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="resolveIncidentForm.processing"
                            :class="[
                                'min-h-[44px] px-5 py-2.5 rounded-xl text-white font-bold text-xs cursor-pointer shadow-2xs',
                                resolveIncidentForm.resolution_type === 'baja'
                                    ? 'bg-rose-700 hover:bg-rose-800'
                                    : 'bg-emerald-700 hover:bg-emerald-800'
                            ]"
                        >
                            {{ resolveIncidentForm.resolution_type === 'baja' ? 'Confirmar baja definitiva' : 'Confirmar recuperación' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 5: RESOLVER UNIDADES SIN LOCALIZAR                                  -->
        <!-- ========================================================================= -->
        <div
            v-if="showResolveUnlocatedModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-black text-slate-900">
                        Resolver unidades sin localizar ({{ summary.unlocated_in_review }} disponibles)
                    </h3>
                    <button @click="showResolveUnlocatedModal = false" type="button" class="h-9 w-9 rounded-lg text-slate-400 hover:text-slate-700 cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form @submit.prevent="submitResolveUnlocated" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Cantidad a resolver *</label>
                        <input
                            v-model.number="resolveUnlocatedForm.quantity"
                            type="number"
                            min="1"
                            :max="summary.unlocated_in_review"
                            required
                            class="w-full h-11 text-lg font-black text-slate-900 rounded-xl border-slate-300"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Resultado de la verificación *</label>
                        <select
                            v-model="resolveUnlocatedForm.resolution_type"
                            class="w-full h-11 text-xs font-semibold rounded-xl border-slate-300 text-slate-800"
                        >
                            <option value="recuperacion">Localizado apto (Reincorporar al inventario activo)</option>
                            <option value="danado">Localizado con daño físico (Mantener separado como dañado)</option>
                            <option value="baja">Confirmar baja definitiva (No localizado)</option>
                        </select>
                    </div>

                    <div v-if="resolveUnlocatedForm.resolution_type === 'recuperacion'">
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Ubicación verificada *</label>
                        <select
                            v-model="resolveUnlocatedForm.recovery_location"
                            class="w-full h-11 text-xs font-semibold rounded-xl border-slate-300 text-slate-800"
                        >
                            <option value="plant_empty">Vacíos aptos en Planta</option>
                            <option value="plant_full">Llenos libres en Planta</option>
                            <option value="truck_full">Llenos disponibles en el camión</option>
                            <option value="truck_empty">Vacíos recogidos en el camión</option>
                        </select>
                    </div>

                    <div v-if="resolveUnlocatedForm.resolution_type === 'danado'">
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Tipo de daño detectado *</label>
                        <select
                            v-model="resolveUnlocatedForm.damage_type"
                            class="w-full h-11 text-xs font-semibold rounded-xl border-slate-300 text-slate-800"
                        >
                            <option value="rotura">Roto</option>
                            <option value="fisura">Fisurado</option>
                            <option value="contaminacion">Contaminado</option>
                            <option value="deformacion">Deformado</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Notas de aclaración</label>
                        <input
                            v-model="resolveUnlocatedForm.notes"
                            type="text"
                            placeholder="Ej: Estaba en almacén secundario de Planta"
                            class="w-full h-11 text-xs rounded-xl border-slate-300"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showResolveUnlocatedModal = false"
                            class="min-h-[44px] px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="resolveUnlocatedForm.processing"
                            class="min-h-[44px] px-5 py-2.5 rounded-xl bg-[#0E1E42] hover:bg-blue-900 text-white font-bold text-xs cursor-pointer shadow-2xs"
                        >
                            Guardar resolución
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

