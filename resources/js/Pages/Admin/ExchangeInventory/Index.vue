<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    AlertTriangle,
    ArrowLeftRight,
    CheckCircle2,
    Clock,
    Droplets,
    Factory,
    PackageCheck,
    Pencil,
    Plus,
    RefreshCw,
    RotateCcw,
    Search,
    Target,
    Truck,
    X,
} from 'lucide-vue-next';

const props = defineProps({
    summary: { type: Object, required: true },
    breakdown: { type: Object, required: true },
    pendingIncidents: { type: Array, default: () => [] },
    resolvedIncidents: { type: Array, default: () => [] },
    pendingFixedIncidents: { type: Array, default: () => [] },
    resolvedFixedIncidents: { type: Array, default: () => [] },
    movements: { type: Array, default: () => [] },
    filters: {
        type: Object,
        default: () => ({ movement_type: 'all', date_from: '', date_to: '' }),
    },
});

const page = usePage();

// Pestaña activa (por defecto Resumen o Movimientos si hay filtro activo en URL)
const hasActiveFilters = Boolean(
    (props.filters?.movement_type && props.filters.movement_type !== 'all') ||
    props.filters?.date_from ||
    props.filters?.date_to
);
const activeTab = ref(hasActiveFilters ? 'movimientos' : 'resumen');

const pendingIssuesCount = computed(() => {
    return (
        (props.pendingIncidents?.length || 0) +
        (props.pendingFixedIncidents?.length || 0) +
        (props.summary?.unlocated_in_review > 0 ? 1 : 0)
    );
});

const allResolvedIncidents = computed(() => {
    return [
        ...(props.resolvedIncidents || []).map((i) => ({ ...i, bottle_type: 'cambio' })),
        ...(props.resolvedFixedIncidents || []),
    ];
});

const targetPct = computed(() => {
    const target = Number(props.summary?.target_quantity || 0);
    const active = Number(props.summary?.active_total || 0);
    if (target <= 0) return 0;
    return Math.min(100, Math.round((active / target) * 100));
});

// =========================================================================
// 1. EDITAR META
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
// 2. REGISTRAR BOTELLONES NUEVOS
// =========================================================================
const showNewBottlesModal = ref(false);
const newBottlesForm = useForm({
    quantity: 1,
    bottle_condition: 'empty',
    reason: 'Compra de lote nuevo',
    notes: '',
    sync_target: true,
});

const openNewBottlesModal = () => {
    newBottlesForm.quantity = 1;
    newBottlesForm.bottle_condition = 'empty';
    newBottlesForm.reason = 'Compra de lote nuevo';
    newBottlesForm.notes = '';
    newBottlesForm.sync_target = true;
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
// 3. AJUSTAR / REINICIAR CONTEO COMPLETO (PLANTA + CAMIÓN)
// =========================================================================
const showRecountModal = ref(false);
const recountForm = useForm({
    empty_count: props.breakdown?.plant?.empty_available ?? 0,
    washing_count: 0,
    full_count: props.breakdown?.plant?.full_available ?? 0,
    truck_full_count: props.breakdown?.truck?.full_available ?? 0,
    truck_empty_count: props.breakdown?.truck?.empty_collected ?? 0,
    reason: 'Ajuste de inventario físico',
    notes: '',
    sync_target: false,
});

const openRecountModal = () => {
    recountForm.empty_count = Number(props.breakdown?.plant?.empty_available ?? 0);
    recountForm.washing_count = 0;
    recountForm.full_count = Number(props.breakdown?.plant?.full_available ?? 0);
    recountForm.truck_full_count = Number(props.breakdown?.truck?.full_available ?? 0);
    recountForm.truck_empty_count = Number(props.breakdown?.truck?.empty_collected ?? 0);
    recountForm.reason = 'Ajuste de inventario físico';
    recountForm.notes = '';
    recountForm.sync_target = false;
    recountForm.clearErrors();
    showRecountModal.value = true;
};

const resetRecountToZero = () => {
    recountForm.empty_count = 0;
    recountForm.washing_count = 0;
    recountForm.full_count = 0;
    recountForm.truck_full_count = 0;
    recountForm.truck_empty_count = 0;
    recountForm.reason = 'Reinicio total de conteo';
};

const recountPlantTotal = computed(() => {
    return (
        Math.max(0, Number(recountForm.empty_count || 0)) +
        Math.max(0, Number(recountForm.washing_count || 0)) +
        Math.max(0, Number(recountForm.full_count || 0)) +
        Number(props.breakdown?.plant?.full_reserved ?? 0)
    );
});

const recountTruckTotal = computed(() => {
    return (
        Math.max(0, Number(recountForm.truck_full_count || 0)) +
        Math.max(0, Number(recountForm.truck_empty_count || 0))
    );
});

const recountActiveTotal = computed(() => {
    return recountPlantTotal.value + recountTruckTotal.value;
});

const submitRecount = () => {
    if (recountForm.processing) return;
    recountForm.post(route('admin.exchange-inventory.reconcile'), {
        preserveScroll: true,
        onSuccess: () => {
            showRecountModal.value = false;
        },
    });
};

// =========================================================================
// 4. REPORTAR INCONVENIENTE
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
// 5. RESOLVER INCIDENCIA O UNIDADES SIN LOCALIZAR
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
// 6. FILTROS Y ETIQUETAS
// =========================================================================
const filterType = ref(props.filters.movement_type || 'all');
const filterDateFrom = ref(props.filters.date_from || '');
const filterDateTo = ref(props.filters.date_to || '');

const applyFilters = () => {
    activeTab.value = 'movimientos';
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

const getStageLabel = (stage) => {
    const map = {
        recepcion: 'Recepción',
        almacen_vacios: 'Almacén vacíos',
        lavado: 'Lavado',
        llenado: 'Llenado',
        almacen: 'Almacén llenos',
        almacen_planta: 'Planta',
        preparado: 'Preparados',
        carga: 'Carga a camión',
        carga_camion: 'Carga a camión',
        en_camion: 'En camión',
        camion: 'En camión',
        ruta: 'En ruta',
    };
    return map[stage] || stage;
};

const getDamageLabel = (damage) => {
    const map = {
        rotura: 'Roto / quebrado',
        fisura: 'Fisurado / fuga',
        fuga_cuerpo: 'Fisurado / fuga',
        cuello_roto: 'Tapa o cuello dañado',
        golpe_fondo: 'Golpe en fondo',
        contaminacion: 'Contaminado',
        contaminacion_quimica: 'Contaminado',
        deformacion: 'Deformado',
        desgaste_extremo: 'Desgaste extremo',
        otro: 'Otro',
    };
    return map[damage] || damage;
};

const getMovementMeta = (type) => {
    const map = {
        exchange_new_entry: {
            label: 'Alta de nuevos',
            badge: 'bg-[#E6F7EE] text-[#26845B] border-[#26845B]/25',
            iconWrap: 'bg-[#E6F7EE] text-[#26845B]',
        },
        exchange_write_off: {
            label: 'Baja confirmada',
            badge: 'bg-[#FCECEF] text-[#E82147] border-[#E82147]/25',
            iconWrap: 'bg-[#FCECEF] text-[#E82147]',
        },
        exchange_recovery: {
            label: 'Recuperación',
            badge: 'bg-[#E6F7EE] text-[#26845B] border-[#26845B]/25',
            iconWrap: 'bg-[#E6F7EE] text-[#26845B]',
        },
        exchange_target_updated: {
            label: 'Meta editada',
            badge: 'bg-[#EEEAF7] text-[#1E1545] border-[#1E1545]/20',
            iconWrap: 'bg-[#EEEAF7] text-[#1E1545]',
        },
        bottle_incident: {
            label: 'Daño en revisión',
            badge: 'bg-[#FEF3C7] text-[#B45309] border-[#D99A27]/30',
            iconWrap: 'bg-[#FEF3C7] text-[#B45309]',
        },
        exchange_inconvenience_reported: {
            label: 'Sin localizar',
            badge: 'bg-[#FEF3C7] text-[#B45309] border-[#D99A27]/30',
            iconWrap: 'bg-[#FEF3C7] text-[#B45309]',
        },
        plant_initial_count: {
            label: 'Conteo inicial',
            badge: 'bg-[#F4F3F8] text-[#1E1545] border-[#E9E7F0]',
            iconWrap: 'bg-[#F4F3F8] text-[#1E1545]',
        },
        plant_physical_recount: {
            label: 'Reconteo físico',
            badge: 'bg-[#E5F4FD] text-[#0071BA] border-[#0071BA]/25',
            iconWrap: 'bg-[#E5F4FD] text-[#0071BA]',
        },
        plant_washing_started: {
            label: 'Inicio de lavado',
            badge: 'bg-[#E0F2FE] text-[#0284C7] border-[#0284C7]/25',
            iconWrap: 'bg-[#E0F2FE] text-[#0284C7]',
        },
        plant_washing_finished: {
            label: 'Llenos terminados',
            badge: 'bg-[#E6F7EE] text-[#26845B] border-[#26845B]/25',
            iconWrap: 'bg-[#E6F7EE] text-[#26845B]',
        },
        truck_load_exchange: {
            label: 'Planta → Camión',
            badge: 'bg-[#E5F4FD] text-[#0071BA] border-[#0071BA]/25',
            iconWrap: 'bg-[#E5F4FD] text-[#0071BA]',
        },
        truck_unload_exchange: {
            label: 'Camión → Planta',
            badge: 'bg-[#EEEAF7] text-[#1E1545] border-[#1E1545]/20',
            iconWrap: 'bg-[#EEEAF7] text-[#1E1545]',
        },
        delivery_refill_to_customer: {
            label: 'Entrega en ruta',
            badge: 'bg-[#E6F7EE] text-[#26845B] border-[#26845B]/25',
            iconWrap: 'bg-[#E6F7EE] text-[#26845B]',
        },
        collection_exchange_empty: {
            label: 'Vacío recogido',
            badge: 'bg-[#F4F3F8] text-[#1E1545] border-[#E9E7F0]',
            iconWrap: 'bg-[#F4F3F8] text-[#1E1545]',
        },
    };
    return map[type] || {
        label: type,
        badge: 'bg-[#F4F3F8] text-[#1E1545] border-[#E9E7F0]',
        iconWrap: 'bg-[#F4F3F8] text-[#747181]',
    };
};
</script>

<template>
    <Head title="Cambios - Puritronic" />

    <AuthenticatedLayout :wide="true">
        <div class="bg-[#F5F4F8] min-h-full py-5 sm:py-7">
            <div class="mx-auto w-full max-w-[98%] 2xl:max-w-[1600px] px-4 sm:px-6 lg:px-8 space-y-5">

                <!-- 1. ENCABEZADO MINIMALISTA -->
                <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <div class="h-10 w-10 rounded-xl bg-[#1E1545] text-white flex items-center justify-center shadow-sm">
                                <RefreshCw class="w-5 h-5" :stroke-width="2" />
                            </div>
                            <div>
                                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-[#1E1545]">
                                    Cambios
                                </h1>
                                <p class="text-xs sm:text-sm text-[#747181]">
                                    Control de botellones de cambio de 20 L en planta y camión
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            @click="openInconvenienceModal"
                            class="h-10 px-3.5 rounded-xl bg-white hover:bg-[#FEF6E7] text-[#9A630C] border border-[#E9E7F0] hover:border-[#D99A27]/40 font-semibold text-xs sm:text-sm inline-flex items-center gap-2 transition-colors cursor-pointer"
                        >
                            <AlertTriangle class="w-4 h-4 text-[#D99A27]" />
                            <span>Reportar</span>
                        </button>

                        <button
                            type="button"
                            @click="openRecountModal"
                            class="h-10 px-3.5 rounded-xl bg-white hover:bg-[#F4F3F8] text-[#1E1545] border border-[#E9E7F0] font-semibold text-xs sm:text-sm inline-flex items-center gap-2 transition-colors cursor-pointer"
                        >
                            <Pencil class="w-4 h-4 text-[#0071BA]" />
                            <span>Ajustar / Reiniciar</span>
                        </button>

                        <button
                            type="button"
                            @click="openNewBottlesModal"
                            class="h-10 px-4 rounded-xl bg-[#1E1545] hover:bg-[#292059] text-white font-bold text-xs sm:text-sm inline-flex items-center gap-2 shadow-sm transition-colors cursor-pointer"
                        >
                            <Plus class="w-4 h-4" />
                            <span>Sumar nuevos</span>
                        </button>
                    </div>
                </header>

                <!-- Alertas de error globales -->
                <div
                    v-if="Object.keys(page.props.errors || {}).length > 0"
                    class="p-4 rounded-2xl bg-[#FCECEF] border border-[#E82147]/25 text-[#9F1239] flex items-center gap-3"
                    role="alert"
                >
                    <AlertTriangle class="w-5 h-5 text-[#E82147] shrink-0" />
                    <div class="text-xs sm:text-sm font-semibold">
                        <p v-for="(err, key) in page.props.errors" :key="key">{{ err }}</p>
                    </div>
                </div>

                <!-- 2. FRANJA DE 4 KPIS COMPACTOS -->
                <section aria-label="Resumen de cambios" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    <!-- KPI 1: Total en circuito vs Meta -->
                    <div class="rounded-2xl border border-[#E9E7F0] bg-white p-5 flex flex-col justify-between gap-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">Total en circuito</p>
                                <div class="mt-1.5 flex items-baseline gap-1.5 tabular-nums">
                                    <span class="text-2xl sm:text-3xl font-bold text-[#1E1545]">{{ summary.active_total }}</span>
                                    <span class="text-sm font-semibold text-[#747181]">/ {{ summary.target_quantity }}</span>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="openTargetModal"
                                title="Editar meta"
                                class="h-9 w-9 rounded-xl bg-[#F4F3F8] hover:bg-[#EEEAF7] text-[#1E1545] flex items-center justify-center transition-colors cursor-pointer"
                            >
                                <Target class="w-4 h-4 text-[#0071BA]" />
                            </button>
                        </div>

                        <div class="space-y-1.5">
                            <div class="h-1.5 rounded-full bg-[#F4F3F8] overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="summary.diff_status === 'below' ? 'bg-[#D99A27]' : 'bg-[#26845B]'"
                                    :style="{ width: `${targetPct}%` }"
                                ></div>
                            </div>
                            <div class="flex items-center justify-between text-[11px]">
                                <span
                                    class="font-semibold"
                                    :class="summary.diff_status === 'below' ? 'text-[#B45309]' : (summary.diff_status === 'above' ? 'text-[#0071BA]' : 'text-[#26845B]')"
                                >
                                    {{ summary.diff_label }}
                                </span>
                                <span class="text-[#747181] tabular-nums">{{ targetPct }}%</span>
                            </div>
                        </div>
                    </div>

                    <!-- KPI 2: Listos para vender -->
                    <div class="rounded-2xl border border-[#E9E7F0] bg-white p-5 flex flex-col justify-between gap-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">Listos para vender</p>
                                <div class="mt-1.5 flex items-baseline gap-1.5 tabular-nums">
                                    <span class="text-2xl sm:text-3xl font-bold text-[#26845B]">{{ summary.available_full_total }}</span>
                                    <span class="text-xs font-semibold text-[#747181]">llenos</span>
                                </div>
                            </div>
                            <div class="h-9 w-9 rounded-xl bg-[#E6F7EE] text-[#26845B] flex items-center justify-center">
                                <PackageCheck class="w-4 h-4" />
                            </div>
                        </div>
                        <p class="text-xs text-[#747181] pt-2 border-t border-[#F4F3F8] tabular-nums">
                            Planta: <strong class="text-[#1E1545]">{{ summary.plant_full_available }}</strong> ·
                            Camión: <strong class="text-[#1E1545]">{{ summary.truck_full_available }}</strong>
                        </p>
                    </div>

                    <!-- KPI 3: Planta vs Camión -->
                    <div class="rounded-2xl border border-[#E9E7F0] bg-white p-5 flex flex-col justify-between gap-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">En Planta / Camión</p>
                                <div class="mt-1.5 flex items-baseline gap-2 tabular-nums">
                                    <span class="text-2xl sm:text-3xl font-bold text-[#1E1545]">{{ breakdown.plant.subtotal }}</span>
                                    <span class="text-sm font-semibold text-[#747181]">/ {{ breakdown.truck.subtotal }}</span>
                                </div>
                            </div>
                            <div class="h-9 w-9 rounded-xl bg-[#E5F4FD] text-[#0071BA] flex items-center justify-center">
                                <ArrowLeftRight class="w-4 h-4" />
                            </div>
                        </div>
                        <p class="text-xs text-[#747181] pt-2 border-t border-[#F4F3F8] tabular-nums">
                            Vacíos: <strong class="text-[#1E1545]">{{ breakdown.plant.empty_available + breakdown.truck.empty_collected }}</strong> ·
                            Llenos: <strong class="text-[#26845B]">{{ summary.available_full_total }}</strong>
                        </p>
                    </div>

                    <!-- KPI 4: En revisión -->
                    <div class="rounded-2xl border border-[#E9E7F0] bg-white p-5 flex flex-col justify-between gap-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">En revisión</p>
                                <div class="mt-1.5 flex items-baseline gap-1.5 tabular-nums">
                                    <span
                                        class="text-2xl sm:text-3xl font-bold"
                                        :class="summary.in_review_total > 0 ? 'text-[#D99A27]' : 'text-[#1E1545]'"
                                    >
                                        {{ summary.in_review_total }}
                                    </span>
                                    <span class="text-xs font-semibold text-[#747181]">fuera de activos</span>
                                </div>
                            </div>
                            <div
                                class="h-9 w-9 rounded-xl flex items-center justify-center"
                                :class="summary.in_review_total > 0 ? 'bg-[#FEF3C7] text-[#B45309]' : 'bg-[#F4F3F8] text-[#747181]'"
                            >
                                <AlertTriangle v-if="summary.in_review_total > 0" class="w-4 h-4" />
                                <CheckCircle2 v-else class="w-4 h-4 text-[#26845B]" />
                            </div>
                        </div>
                        <p class="text-xs text-[#747181] pt-2 border-t border-[#F4F3F8] tabular-nums">
                            Dañados: <strong class="text-[#1E1545]">{{ summary.damaged_in_review }}</strong> ·
                            Sin localizar: <strong class="text-[#1E1545]">{{ summary.unlocated_in_review }}</strong>
                        </p>
                    </div>
                </section>

                <!-- 3. PESTAÑAS PRINCIPALES -->
                <div class="flex items-center gap-2 border-b border-[#E9E7F0] pb-1 overflow-x-auto">
                    <button
                        type="button"
                        @click="activeTab = 'resumen'"
                        class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-colors cursor-pointer shrink-0"
                        :class="activeTab === 'resumen'
                            ? 'bg-[#1E1545] text-white shadow-sm'
                            : 'text-[#747181] hover:text-[#1E1545] hover:bg-white'"
                    >
                        Resumen
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'movimientos'"
                        class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-colors cursor-pointer shrink-0 inline-flex items-center gap-2"
                        :class="activeTab === 'movimientos'
                            ? 'bg-[#1E1545] text-white shadow-sm'
                            : 'text-[#747181] hover:text-[#1E1545] hover:bg-white'"
                    >
                        <span>Movimientos</span>
                        <span
                            class="px-1.5 py-0.5 rounded-md text-[10px] tabular-nums"
                            :class="activeTab === 'movimientos' ? 'bg-white/20 text-white' : 'bg-[#E9E7F0] text-[#1E1545]'"
                        >
                            {{ movements.length }}
                        </span>
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'incidencias'"
                        class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-colors cursor-pointer shrink-0 inline-flex items-center gap-2"
                        :class="activeTab === 'incidencias'
                            ? 'bg-[#1E1545] text-white shadow-sm'
                            : 'text-[#747181] hover:text-[#1E1545] hover:bg-white'"
                    >
                        <span>Incidencias</span>
                        <span
                            v-if="pendingIssuesCount > 0"
                            class="px-1.5 py-0.5 rounded-md text-[10px] tabular-nums bg-[#D99A27] text-white"
                        >
                            {{ pendingIssuesCount }}
                        </span>
                    </button>
                </div>

                <!-- PESTAÑA 1: RESUMEN (PLANTA VS CAMIÓN + ALERTAS SOLO SI HAY) -->
                <div v-if="activeTab === 'resumen'" class="space-y-5">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                        <!-- Tarjeta Planta -->
                        <section class="rounded-2xl border border-[#E9E7F0] bg-white p-5 sm:p-6 space-y-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-9 w-9 rounded-xl bg-[#E5F4FD] text-[#0071BA] flex items-center justify-center">
                                        <Factory class="w-4 h-4" />
                                    </div>
                                    <div>
                                        <h2 class="text-base font-bold text-[#1E1545]">En Planta</h2>
                                        <p class="text-xs text-[#747181]">{{ breakdown.plant.subtotal }} activos en almacén</p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click="openRecountModal"
                                    class="text-xs font-bold text-[#0071BA] hover:text-[#1E1545] inline-flex items-center gap-1 cursor-pointer"
                                >
                                    <Pencil class="w-3.5 h-3.5" />
                                    <span>Ajustar</span>
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-3 text-center">
                                <div class="rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] p-3.5">
                                    <p class="text-[11px] font-semibold text-[#747181]">Vacíos aptos</p>
                                    <p class="text-2xl font-bold text-[#1E1545] tabular-nums mt-1">{{ breakdown.plant.empty_available }}</p>
                                </div>
                                <div class="rounded-xl bg-[#E6F7EE]/70 border border-[#26845B]/25 p-3.5">
                                    <p class="text-[11px] font-semibold text-[#26845B]">Llenos libres</p>
                                    <p class="text-2xl font-bold text-[#26845B] tabular-nums mt-1">{{ breakdown.plant.full_available }}</p>
                                </div>
                            </div>

                            <div
                                v-if="breakdown.plant.full_reserved > 0"
                                class="px-3.5 py-2 rounded-xl bg-[#FEF3C7]/70 border border-[#D99A27]/30 text-xs font-semibold text-[#92400E] flex items-center justify-between"
                            >
                                <span>Llenos reservados para carga</span>
                                <span class="tabular-nums font-bold">{{ breakdown.plant.full_reserved }} u.</span>
                            </div>
                        </section>

                        <!-- Tarjeta Camión -->
                        <section class="rounded-2xl border border-[#E9E7F0] bg-white p-5 sm:p-6 space-y-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-9 w-9 rounded-xl bg-[#EEEAF7] text-[#1E1545] flex items-center justify-center">
                                        <Truck class="w-4 h-4" />
                                    </div>
                                    <div>
                                        <h2 class="text-base font-bold text-[#1E1545]">En Camión</h2>
                                        <p class="text-xs text-[#747181]">{{ breakdown.truck.subtotal }} activos a bordo</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 text-center">
                                <div class="rounded-xl bg-[#E5F4FD]/60 border border-[#0071BA]/20 p-3.5">
                                    <p class="text-[11px] font-semibold text-[#0071BA]">Llenos disponibles</p>
                                    <p class="text-2xl font-bold text-[#1E1545] tabular-nums mt-1">{{ breakdown.truck.full_available }}</p>
                                </div>
                                <div class="rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] p-3.5">
                                    <p class="text-[11px] font-semibold text-[#747181]">Vacíos recogidos</p>
                                    <p class="text-2xl font-bold text-[#1E1545] tabular-nums mt-1">{{ breakdown.truck.empty_collected }}</p>
                                </div>
                            </div>

                            <div
                                v-if="breakdown.truck.empty_committed_in_unload > 0"
                                class="px-3.5 py-2 rounded-xl bg-[#FEF3C7]/70 border border-[#D99A27]/30 text-xs font-semibold text-[#92400E] flex items-center justify-between"
                            >
                                <span>Vacíos en entrega pendiente a Planta</span>
                                <span class="tabular-nums font-bold">{{ breakdown.truck.empty_committed_in_unload }} u.</span>
                            </div>
                        </section>
                    </div>

                    <!-- Estado de conciliación (compacto si todo está bien; accionable si hay pendientes) -->
                    <div
                        v-if="pendingIssuesCount === 0"
                        class="rounded-2xl border border-[#26845B]/25 bg-[#E6F7EE]/70 px-5 py-3.5 flex items-center justify-between gap-3"
                    >
                        <div class="flex items-center gap-2.5 text-xs sm:text-sm font-semibold text-[#26845B]">
                            <CheckCircle2 class="w-4 h-4 shrink-0" />
                            <span>Todo conciliado · Sin incidencias ni unidades sin localizar.</span>
                        </div>
                        <button
                            v-if="movements.length > 0"
                            type="button"
                            @click="activeTab = 'movimientos'"
                            class="text-xs font-bold text-[#1E1545] hover:underline cursor-pointer shrink-0"
                        >
                            Ver historial →
                        </button>
                    </div>

                    <section v-else class="rounded-2xl border border-[#D99A27]/35 bg-white p-5 space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-bold text-[#1E1545] flex items-center gap-2">
                                <AlertTriangle class="w-4 h-4 text-[#D99A27]" />
                                <span>Requiere atención ({{ pendingIssuesCount }})</span>
                            </h3>
                            <button
                                type="button"
                                @click="activeTab = 'incidencias'"
                                class="text-xs font-bold text-[#0071BA] hover:underline cursor-pointer"
                            >
                                Ir a Incidencias
                            </button>
                        </div>

                        <div
                            v-if="summary.unlocated_in_review > 0"
                            class="p-3.5 rounded-xl bg-[#FEF3C7]/60 border border-[#D99A27]/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                        >
                            <div class="text-xs sm:text-sm">
                                <strong class="text-[#92400E]">{{ summary.unlocated_in_review }} sin localizar</strong>
                                <span class="text-[#92400E]/85"> · Pendientes de aclarar o dar de baja</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="openResolveUnlocatedModal('recuperacion')"
                                    class="px-3 py-1.5 rounded-lg bg-[#26845B] hover:bg-[#1E6B49] text-white text-xs font-bold cursor-pointer"
                                >
                                    Recuperar
                                </button>
                                <button
                                    type="button"
                                    @click="openResolveUnlocatedModal('baja')"
                                    class="px-3 py-1.5 rounded-lg bg-[#E82147] hover:bg-[#C91A3C] text-white text-xs font-bold cursor-pointer"
                                >
                                    Dar de baja
                                </button>
                            </div>
                        </div>

                        <div
                            v-for="inc in pendingIncidents"
                            :key="inc.id"
                            class="p-3.5 rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                        >
                            <div class="text-xs space-y-0.5">
                                <p class="font-bold text-[#1E1545]">
                                    #{{ inc.id }} · {{ inc.quantity }} u. · {{ getDamageLabel(inc.damage_type) }}
                                    <span class="font-normal text-[#747181]">({{ getStageLabel(inc.stage) }})</span>
                                </p>
                                <p class="text-[#747181]">{{ inc.notes }}</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button
                                    type="button"
                                    @click="openResolveIncidentModal(inc, 'recuperacion')"
                                    class="px-3 py-1.5 rounded-lg bg-[#26845B] hover:bg-[#1E6B49] text-white text-xs font-bold cursor-pointer"
                                >
                                    Recuperar
                                </button>
                                <button
                                    type="button"
                                    @click="openResolveIncidentModal(inc, 'baja')"
                                    class="px-3 py-1.5 rounded-lg bg-[#E82147] hover:bg-[#C91A3C] text-white text-xs font-bold cursor-pointer"
                                >
                                    Baja
                                </button>
                            </div>
                        </div>
                        <div
                            v-for="fInc in pendingFixedIncidents"
                            :key="`f-${fInc.id}`"
                            class="p-3.5 rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                        >
                            <div class="text-xs space-y-0.5">
                                <p class="font-bold text-[#1E1545]">
                                    <span class="px-1.5 py-0.5 rounded bg-[#EEEAF7] text-[#1E1545] text-[10px] mr-1">FIJO</span>
                                    #{{ fInc.id }} · {{ fInc.quantity }} u. · {{ fInc.customer_name }}
                                    <span v-if="fInc.order_folio" class="font-mono text-[#0071BA]">({{ fInc.order_folio }})</span>
                                </p>
                                <p class="text-[#747181]">{{ getDamageLabel(fInc.damage_type) }} · {{ fInc.notes }}</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button
                                    type="button"
                                    @click="openResolveIncidentModal(fInc, 'aclarado')"
                                    class="px-3 py-1.5 rounded-lg bg-[#26845B] hover:bg-[#1E6B49] text-white text-xs font-bold cursor-pointer"
                                >
                                    Aclarado
                                </button>
                                <button
                                    type="button"
                                    @click="openResolveIncidentModal(fInc, 'cierre')"
                                    class="px-3 py-1.5 rounded-lg bg-[#1E1545] hover:bg-[#292059] text-white text-xs font-bold cursor-pointer"
                                >
                                    Cerrar
                                </button>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- PESTAÑA 2: MOVIMIENTOS -->
                <section v-else-if="activeTab === 'movimientos'" class="rounded-2xl border border-[#E9E7F0] bg-white p-5 sm:p-6 space-y-4">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pb-3 border-b border-[#F4F3F8]">
                        <div>
                            <h2 class="text-base font-bold text-[#1E1545] flex items-center gap-2">
                                <Clock class="w-4 h-4 text-[#0071BA]" />
                                <span>Movimientos</span>
                            </h2>
                            <p class="text-xs text-[#747181]">Altas, reconteos, bajas, cargas y descargas</p>
                        </div>

                        <form @submit.prevent="applyFilters" class="flex flex-wrap items-center gap-2">
                            <select
                                v-model="filterType"
                                @change="applyFilters"
                                class="h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545] focus:border-[#0071BA] focus:ring-[#0071BA]"
                            >
                                <option value="all">Todos los movimientos</option>
                                <option value="alta">Altas de nuevos</option>
                                <option value="reconteo">Reconteos físicos</option>
                                <option value="baja">Bajas confirmadas</option>
                                <option value="recuperacion">Recuperaciones</option>
                                <option value="incidencia">Incidencias / En revisión</option>
                                <option value="preparacion">Preparación en Planta</option>
                                <option value="carga">Cargas Planta → Camión</option>
                                <option value="descarga">Descargas Camión → Planta</option>
                                <option value="domicilio">Entregas en ruta</option>
                                <option value="meta">Meta actualizada</option>
                            </select>

                            <input
                                v-model="filterDateFrom"
                                type="date"
                                @change="applyFilters"
                                class="h-10 text-xs rounded-xl border-[#E9E7F0] text-[#1E1545]"
                            />

                            <input
                                v-model="filterDateTo"
                                type="date"
                                @change="applyFilters"
                                class="h-10 text-xs rounded-xl border-[#E9E7F0] text-[#1E1545]"
                            />

                            <button
                                v-if="filterType !== 'all' || filterDateFrom || filterDateTo"
                                type="button"
                                @click="clearFilters"
                                class="h-10 px-3 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#1E1545] text-xs font-bold cursor-pointer"
                            >
                                Limpiar
                            </button>
                        </form>
                    </div>

                    <div v-if="movements.length === 0" class="py-10 text-center text-xs text-[#747181]">
                        No se encontraron movimientos con los filtros actuales.
                    </div>

                    <div v-else class="divide-y divide-[#F4F3F8]">
                        <div
                            v-for="mov in movements"
                            :key="mov.id"
                            class="py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 text-xs"
                        >
                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span
                                        class="px-2.5 py-0.5 rounded-lg text-[11px] font-bold border"
                                        :class="getMovementMeta(mov.movement_type).badge"
                                    >
                                        {{ getMovementMeta(mov.movement_type).label }}
                                    </span>
                                    <span class="font-bold text-[#1E1545] tabular-nums">
                                        {{ mov.quantity }} u.
                                    </span>
                                    <span
                                        v-if="mov.status === 'proposed'"
                                        class="px-2 py-0.5 rounded-md bg-[#FEF3C7] text-[#B45309] text-[10px] font-bold"
                                    >
                                        Por confirmar
                                    </span>
                                </div>

                                <p v-if="mov.notes" class="text-[#292638] leading-relaxed">
                                    {{ mov.notes }}
                                </p>
                            </div>

                            <div class="text-[11px] text-[#747181] sm:text-right shrink-0 tabular-nums">
                                <div class="font-semibold text-[#1E1545]">{{ mov.created_at }}</div>
                                <div>{{ mov.user_name }}</div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- PESTAÑA 3: INCIDENCIAS -->
                <section v-else class="rounded-2xl border border-[#E9E7F0] bg-white p-5 sm:p-6 space-y-5">
                    <div v-if="pendingIssuesCount === 0" class="py-8 text-center space-y-1.5">
                        <CheckCircle2 class="w-7 h-7 text-[#26845B] mx-auto" />
                        <p class="text-sm font-bold text-[#1E1545]">Sin incidencias abiertas</p>
                        <p class="text-xs text-[#747181]">Todas las unidades están conciliadas.</p>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-if="summary.unlocated_in_review > 0"
                            class="p-4 rounded-xl bg-[#FEF3C7]/60 border border-[#D99A27]/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                        >
                            <div>
                                <p class="text-sm font-bold text-[#92400E]">
                                    {{ summary.unlocated_in_review }} unidades sin localizar
                                </p>
                                <p class="text-xs text-[#92400E]/85">
                                    Pendientes de confirmar recuperación o baja definitiva.
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="openResolveUnlocatedModal('recuperacion')"
                                    class="h-9 px-3.5 rounded-xl bg-[#26845B] hover:bg-[#1E6B49] text-white text-xs font-bold inline-flex items-center gap-1.5 cursor-pointer"
                                >
                                    <RotateCcw class="w-3.5 h-3.5" />
                                    <span>Recuperar</span>
                                </button>
                                <button
                                    type="button"
                                    @click="openResolveUnlocatedModal('baja')"
                                    class="h-9 px-3.5 rounded-xl bg-[#E82147] hover:bg-[#C91A3C] text-white text-xs font-bold cursor-pointer"
                                >
                                    Confirmar baja
                                </button>
                            </div>
                        </div>

                        <div
                            v-for="inc in pendingIncidents"
                            :key="inc.id"
                            class="p-4 rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                        >
                            <div class="space-y-1 text-xs">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="px-2 py-0.5 rounded-lg bg-[#FEF3C7] text-[#B45309] font-bold">
                                        #{{ inc.id }} · {{ inc.quantity }} u.
                                    </span>
                                    <span class="font-bold text-[#1E1545]">{{ getDamageLabel(inc.damage_type) }}</span>
                                    <span class="text-[#747181]">· {{ getStageLabel(inc.stage) }}</span>
                                </div>
                                <p class="text-[#292638]">{{ inc.notes }}</p>
                                <p class="text-[#747181]">{{ inc.reported_by_name }} · {{ inc.created_at }}</p>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <button
                                    type="button"
                                    @click="openResolveIncidentModal(inc, 'recuperacion')"
                                    class="h-9 px-3.5 rounded-xl bg-[#26845B] hover:bg-[#1E6B49] text-white text-xs font-bold cursor-pointer"
                                >
                                    Recuperar
                                </button>
                                <button
                                    type="button"
                                    @click="openResolveIncidentModal(inc, 'baja')"
                                    class="h-9 px-3.5 rounded-xl bg-[#E82147] hover:bg-[#C91A3C] text-white text-xs font-bold cursor-pointer"
                                >
                                    Confirmar baja
                                </button>
                            </div>
                        </div>

                        <div
                            v-for="fInc in pendingFixedIncidents"
                            :key="`fixed-${fInc.id}`"
                            class="p-4 rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                        >
                            <div class="space-y-1 text-xs">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="px-2 py-0.5 rounded-lg bg-[#EEEAF7] text-[#1E1545] font-bold">
                                        FIJO · #{{ fInc.id }} · {{ fInc.quantity }} u.
                                    </span>
                                    <span class="font-bold text-[#1E1545]">{{ fInc.customer_name }}</span>
                                    <span v-if="fInc.order_folio" class="font-mono text-[#0071BA]">{{ fInc.order_folio }}</span>
                                    <span class="text-[#747181]">· {{ getDamageLabel(fInc.damage_type) }}</span>
                                </div>
                                <p class="text-[#292638]">{{ fInc.notes }}</p>
                                <p class="text-[#747181]">{{ fInc.reported_by_name }} · {{ fInc.created_at }}</p>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <button
                                    type="button"
                                    @click="openResolveIncidentModal(fInc, 'aclarado')"
                                    class="h-9 px-3.5 rounded-xl bg-[#26845B] hover:bg-[#1E6B49] text-white text-xs font-bold cursor-pointer"
                                >
                                    Aclarado / Repuesto
                                </button>
                                <button
                                    type="button"
                                    @click="openResolveIncidentModal(fInc, 'cierre')"
                                    class="h-9 px-3.5 rounded-xl bg-[#1E1545] hover:bg-[#292059] text-white text-xs font-bold cursor-pointer"
                                >
                                    Cerrar daño
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="allResolvedIncidents.length > 0" class="pt-4 border-t border-[#F4F3F8] space-y-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-[#747181]">Resueltas recientemente</h3>
                        <div class="divide-y divide-[#F4F3F8]">
                            <div
                                v-for="rInc in allResolvedIncidents"
                                :key="`${rInc.bottle_type}-${rInc.id}`"
                                class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs"
                            >
                                <div>
                                    <span class="font-bold text-[#1E1545]">
                                        <span class="uppercase text-[10px] px-1.5 py-0.5 rounded bg-[#F4F3F8] mr-1">{{ rInc.bottle_type === 'fijo' ? 'Fijo' : 'Cambio' }}</span>
                                        #{{ rInc.id }} · {{ rInc.quantity }} u. ({{ getDamageLabel(rInc.damage_type) }})
                                    </span>
                                    <p class="text-[#747181]">{{ rInc.resolution_notes }}</p>
                                </div>
                                <span class="text-[11px] text-[#747181] shrink-0">{{ rInc.resolved_by_name }} · {{ rInc.resolved_at }}</span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- MODAL 1: EDITAR META -->
        <div v-if="showTargetModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#1E1545]/45 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-[#E9E7F0] space-y-4">
                <div class="flex items-center justify-between border-b border-[#F4F3F8] pb-3">
                    <div>
                        <h3 class="text-base font-bold text-[#1E1545]">Editar meta</h3>
                        <p class="text-xs text-[#747181]">Activos actuales: {{ summary.active_total }} u.</p>
                    </div>
                    <button @click="showTargetModal = false" type="button" class="text-[#747181] hover:text-[#1E1545] cursor-pointer">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <form @submit.prevent="submitTarget" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Meta de botellones (20 L) *</label>
                        <input
                            v-model.number="targetForm.target_quantity"
                            type="number"
                            min="1"
                            max="10000"
                            required
                            class="w-full h-11 text-lg font-bold text-[#1E1545] rounded-xl border-[#E9E7F0] focus:border-[#0071BA] focus:ring-[#0071BA]"
                        />
                        <p v-if="targetForm.errors.target_quantity" class="text-xs text-[#E82147] mt-1">{{ targetForm.errors.target_quantity }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Nota (opcional)</label>
                        <input v-model="targetForm.notes" type="text" placeholder="Ej: Ajuste de temporada" class="w-full h-10 text-xs rounded-xl border-[#E9E7F0]" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showTargetModal = false" class="h-10 px-4 rounded-xl text-xs font-bold text-[#747181] hover:bg-[#F4F3F8] cursor-pointer">Cancelar</button>
                        <button type="submit" :disabled="targetForm.processing" class="h-10 px-5 rounded-xl bg-[#1E1545] hover:bg-[#292059] text-white text-xs font-bold cursor-pointer">Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL 2: SUMAR BOTELLONES NUEVOS -->
        <div v-if="showNewBottlesModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#1E1545]/45 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-[#E9E7F0] space-y-4">
                <div class="flex items-center justify-between border-b border-[#F4F3F8] pb-3">
                    <div>
                        <h3 class="text-base font-bold text-[#1E1545]">Sumar botellones nuevos</h3>
                        <p class="text-xs text-[#747181]">Ingreso físico de cambios de 20 L en Planta</p>
                    </div>
                    <button @click="showNewBottlesModal = false" type="button" class="text-[#747181] hover:text-[#1E1545] cursor-pointer">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <form @submit.prevent="submitNewBottles" class="space-y-4">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold uppercase text-[#747181]">Cantidad *</label>
                            <div class="flex items-center gap-1">
                                <button
                                    v-for="q in [1, 2, 5, 10]"
                                    :key="q"
                                    type="button"
                                    @click="newBottlesForm.quantity = q"
                                    class="px-2 py-0.5 rounded-md text-[11px] font-bold border transition cursor-pointer"
                                    :class="Number(newBottlesForm.quantity) === q
                                        ? 'bg-[#1E1545] text-white border-[#1E1545]'
                                        : 'bg-[#F4F3F8] text-[#1E1545] border-[#E9E7F0] hover:bg-[#EEEAF7]'"
                                >
                                    +{{ q }}
                                </button>
                            </div>
                        </div>
                        <input
                            v-model.number="newBottlesForm.quantity"
                            type="number"
                            min="1"
                            max="5000"
                            required
                            class="w-full h-11 text-lg font-bold text-[#1E1545] rounded-xl border-[#E9E7F0] focus:border-[#0071BA] focus:ring-[#0071BA]"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Condición de ingreso *</label>
                        <div class="grid grid-cols-2 gap-2.5">
                            <button
                                type="button"
                                @click="newBottlesForm.bottle_condition = 'empty'"
                                class="p-3 rounded-xl border text-left text-xs transition cursor-pointer"
                                :class="newBottlesForm.bottle_condition === 'empty' ? 'bg-[#E5F4FD] border-[#0071BA] text-[#1E1545] font-bold' : 'border-[#E9E7F0] text-[#747181]'"
                            >
                                Vacío apto
                            </button>
                            <button
                                type="button"
                                @click="newBottlesForm.bottle_condition = 'full'"
                                class="p-3 rounded-xl border text-left text-xs transition cursor-pointer"
                                :class="newBottlesForm.bottle_condition === 'full' ? 'bg-[#E6F7EE] border-[#26845B] text-[#26845B] font-bold' : 'border-[#E9E7F0] text-[#747181]'"
                            >
                                Lleno listo
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Motivo *</label>
                        <select v-model="newBottlesForm.reason" class="w-full h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545]">
                            <option value="Compra de lote nuevo">Compra de lote nuevo</option>
                            <option value="Reposición de envase">Reposición de envase</option>
                            <option value="Incorporación al inventario">Incorporación al inventario</option>
                        </select>
                    </div>
                    <label class="flex items-start gap-2.5 p-3 rounded-xl bg-[#F4F3F8] border border-[#E9E7F0] cursor-pointer">
                        <input v-model="newBottlesForm.sync_target" type="checkbox" class="mt-0.5 rounded border-[#E9E7F0] text-[#1E1545]" />
                        <span class="text-xs text-[#292638]">
                            <strong class="block text-[#1E1545]">Sumar también a la Meta</strong>
                            Subirá de {{ summary.target_quantity }} a {{ summary.target_quantity + (Number(newBottlesForm.quantity) || 0) }} u.
                        </span>
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showNewBottlesModal = false" class="h-10 px-4 rounded-xl text-xs font-bold text-[#747181] hover:bg-[#F4F3F8] cursor-pointer">Cancelar</button>
                        <button type="submit" :disabled="newBottlesForm.processing" class="h-10 px-5 rounded-xl bg-[#1E1545] hover:bg-[#292059] text-white text-xs font-bold cursor-pointer">Confirmar alta</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL 3: AJUSTAR / REINICIAR CONTEO COMPLETO (PLANTA Y CAMIÓN) -->
        <div v-if="showRecountModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#1E1545]/45 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-[#E9E7F0] space-y-4">
                <div class="flex items-center justify-between border-b border-[#F4F3F8] pb-3">
                    <div>
                        <h3 class="text-base font-bold text-[#1E1545]">Ajustar o reiniciar conteo</h3>
                        <p class="text-xs text-[#747181]">Sube, baja o reinicia las cantidades reales en Planta y Camión</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="resetRecountToZero"
                            class="px-2.5 py-1 rounded-lg bg-[#FCECEF] hover:bg-[#F9D6DE] text-[#E82147] text-[11px] font-bold cursor-pointer"
                        >
                            Poner en 0
                        </button>
                        <button @click="showRecountModal = false" type="button" class="text-[#747181] hover:text-[#1E1545] cursor-pointer">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <form @submit.prevent="submitRecount" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="p-3 rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] space-y-2.5">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#0071BA]">En Planta</p>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-[#747181] mb-1">Vacíos *</label>
                                    <input v-model.number="recountForm.empty_count" type="number" min="0" max="10000" required class="w-full h-10 text-base font-bold text-[#1E1545] rounded-xl border-[#E9E7F0]" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-[#747181] mb-1">Llenos *</label>
                                    <input v-model.number="recountForm.full_count" type="number" min="0" max="10000" required class="w-full h-10 text-base font-bold text-[#1E1545] rounded-xl border-[#E9E7F0]" />
                                </div>
                            </div>
                        </div>

                        <div class="p-3 rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] space-y-2.5">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#1E1545]">En Camión</p>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-[#747181] mb-1">Llenos *</label>
                                    <input v-model.number="recountForm.truck_full_count" type="number" min="0" max="10000" required class="w-full h-10 text-base font-bold text-[#1E1545] rounded-xl border-[#E9E7F0]" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-[#747181] mb-1">Vacíos *</label>
                                    <input v-model.number="recountForm.truck_empty_count" type="number" min="0" max="10000" required class="w-full h-10 text-base font-bold text-[#1E1545] rounded-xl border-[#E9E7F0]" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-[#F4F3F8] border border-[#E9E7F0] p-3 text-xs space-y-1 tabular-nums">
                        <div class="flex justify-between text-[#747181]">
                            <span>Planta:</span>
                            <strong class="text-[#1E1545]">{{ breakdown.plant.subtotal }} → {{ recountPlantTotal }} u.</strong>
                        </div>
                        <div class="flex justify-between text-[#747181]">
                            <span>Camión:</span>
                            <strong class="text-[#1E1545]">{{ breakdown.truck.subtotal }} → {{ recountTruckTotal }} u.</strong>
                        </div>
                        <div class="pt-1 border-t border-[#E9E7F0] flex justify-between font-bold text-[#1E1545] text-sm">
                            <span>Total circuito:</span>
                            <span>{{ summary.active_total }} → {{ recountActiveTotal }} u.</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Motivo *</label>
                            <select v-model="recountForm.reason" class="w-full h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545]">
                                <option value="Ajuste de inventario físico">Ajuste de inventario físico</option>
                                <option value="Reinicio total de conteo">Reinicio total de conteo</option>
                                <option value="Corrección de inventario">Corrección de inventario</option>
                                <option value="Otro ajuste">Otro ajuste</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Notas (opcional)</label>
                            <input v-model="recountForm.notes" type="text" placeholder="Ej: Conteo al iniciar turno" class="w-full h-10 text-xs rounded-xl border-[#E9E7F0]" />
                        </div>
                    </div>
                    <label v-if="recountActiveTotal > 0" class="flex items-start gap-2.5 p-3 rounded-xl bg-[#F4F3F8] border border-[#E9E7F0] cursor-pointer">
                        <input v-model="recountForm.sync_target" type="checkbox" class="mt-0.5 rounded border-[#E9E7F0] text-[#0071BA]" />
                        <span class="text-xs text-[#292638]">
                            <strong class="block text-[#1E1545]">Actualizar la Meta al nuevo total</strong>
                            Meta pasará de {{ summary.target_quantity }} a {{ recountActiveTotal }} u.
                        </span>
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showRecountModal = false" class="h-10 px-4 rounded-xl text-xs font-bold text-[#747181] hover:bg-[#F4F3F8] cursor-pointer">Cancelar</button>
                        <button type="submit" :disabled="recountForm.processing" class="h-10 px-5 rounded-xl bg-[#1E1545] hover:bg-[#292059] text-white text-xs font-bold cursor-pointer">Guardar ajuste</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL 4: REPORTAR INCONVENIENTE -->
        <div v-if="showInconvenienceModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#1E1545]/45 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-[#E9E7F0] space-y-4">
                <div class="flex items-center justify-between border-b border-[#F4F3F8] pb-3">
                    <div>
                        <h3 class="text-base font-bold text-[#1E1545]">Reportar inconveniente</h3>
                        <p class="text-xs text-[#747181]">Separa botellones dañados o sin localizar</p>
                    </div>
                    <button @click="showInconvenienceModal = false" type="button" class="text-[#747181] hover:text-[#1E1545] cursor-pointer">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <form @submit.prevent="submitInconvenience" class="space-y-3.5">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Cantidad *</label>
                            <input v-model.number="inconvenienceForm.quantity" type="number" min="1" required class="w-full h-10 text-base font-bold text-[#1E1545] rounded-xl border-[#E9E7F0]" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Origen *</label>
                            <select v-model="inconvenienceForm.origin_stage" class="w-full h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545]">
                                <option value="plant_empty">Vacíos Planta ({{ breakdown.plant.empty_available }})</option>
                                <option value="plant_full">Llenos Planta ({{ breakdown.plant.full_available }})</option>
                                <option value="truck_full">Llenos Camión ({{ breakdown.truck.full_available }})</option>
                                <option value="truck_empty">Vacíos Camión ({{ breakdown.truck.empty_collected }})</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Motivo *</label>
                            <select v-model="inconvenienceForm.reason" class="w-full h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545]">
                                <option value="rotura">Roto</option>
                                <option value="fisura">Fisurado</option>
                                <option value="tapa_cuello">Tapa o cuello dañado</option>
                                <option value="contaminacion">Contaminado</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Estado *</label>
                            <select v-model="inconvenienceForm.location_status" class="w-full h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545]">
                                <option value="separado">Separado</option>
                                <option value="sin_localizar">Sin localizar</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Descripción *</label>
                        <input v-model="inconvenienceForm.notes" type="text" required placeholder="Ej: Golpe en carga" class="w-full h-10 text-xs rounded-xl border-[#E9E7F0]" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showInconvenienceModal = false" class="h-10 px-4 rounded-xl text-xs font-bold text-[#747181] hover:bg-[#F4F3F8] cursor-pointer">Cancelar</button>
                        <button type="submit" :disabled="inconvenienceForm.processing" class="h-10 px-5 rounded-xl bg-[#D99A27] hover:bg-[#B45309] text-white text-xs font-bold cursor-pointer">Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL 5: RESOLVER INCIDENCIA -->
        <div v-if="showResolveIncidentModal && selectedIncident" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#1E1545]/45 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-[#E9E7F0] space-y-4">
                <div class="flex items-center justify-between border-b border-[#F4F3F8] pb-3">
                    <h3 class="text-base font-bold text-[#1E1545]">
                        <template v-if="selectedIncident.bottle_type === 'fijo'">
                            {{ resolveIncidentForm.resolution_type === 'aclarado' ? 'Aclarar / reponer fijo' : 'Cerrar incidencia de fijo' }}
                        </template>
                        <template v-else>
                            {{ resolveIncidentForm.resolution_type === 'baja' ? 'Confirmar baja' : 'Registrar recuperación' }}
                        </template>
                    </h3>
                    <button @click="showResolveIncidentModal = false" type="button" class="text-[#747181] hover:text-[#1E1545] cursor-pointer">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <div class="p-3 rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] text-xs">
                    <strong class="text-[#1E1545]">#{{ selectedIncident.id }} · {{ selectedIncident.quantity }} u. ({{ getDamageLabel(selectedIncident.damage_type) }})</strong>
                    <p v-if="selectedIncident.customer_name" class="text-[#0071BA] font-semibold mt-0.5">{{ selectedIncident.customer_name }}</p>
                    <p class="text-[#747181] mt-0.5">{{ selectedIncident.notes }}</p>
                </div>
                <form @submit.prevent="submitResolveIncident" class="space-y-4">
                    <div v-if="selectedIncident.bottle_type !== 'fijo' && resolveIncidentForm.resolution_type === 'recuperacion'">
                        <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Reingresar a *</label>
                        <select v-model="resolveIncidentForm.recovery_location" class="w-full h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545]">
                            <option value="plant_empty">Vacíos en Planta</option>
                            <option value="plant_full">Llenos en Planta</option>
                            <option value="truck_full">Llenos en Camión</option>
                            <option value="truck_empty">Vacíos en Camión</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Notas</label>
                        <input v-model="resolveIncidentForm.notes" type="text" class="w-full h-10 text-xs rounded-xl border-[#E9E7F0]" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showResolveIncidentModal = false" class="h-10 px-4 rounded-xl text-xs font-bold text-[#747181] hover:bg-[#F4F3F8] cursor-pointer">Cancelar</button>
                        <button type="submit" :disabled="resolveIncidentForm.processing" class="h-10 px-5 rounded-xl bg-[#1E1545] text-white text-xs font-bold cursor-pointer">Confirmar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL 6: RESOLVER SIN LOCALIZAR -->
        <div v-if="showResolveUnlocatedModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#1E1545]/45 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-[#E9E7F0] space-y-4">
                <div class="flex items-center justify-between border-b border-[#F4F3F8] pb-3">
                    <h3 class="text-base font-bold text-[#1E1545]">Resolver sin localizar ({{ summary.unlocated_in_review }} u.)</h3>
                    <button @click="showResolveUnlocatedModal = false" type="button" class="text-[#747181] hover:text-[#1E1545] cursor-pointer">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <form @submit.prevent="submitResolveUnlocated" class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Cantidad *</label>
                        <input v-model.number="resolveUnlocatedForm.quantity" type="number" min="1" :max="summary.unlocated_in_review" required class="w-full h-10 text-base font-bold text-[#1E1545] rounded-xl border-[#E9E7F0]" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Resultado *</label>
                        <select v-model="resolveUnlocatedForm.resolution_type" class="w-full h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545]">
                            <option value="recuperacion">Localizado apto (Recuperar)</option>
                            <option value="danado">Localizado dañado</option>
                            <option value="baja">Confirmar baja</option>
                        </select>
                    </div>
                    <div v-if="resolveUnlocatedForm.resolution_type === 'recuperacion'">
                        <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Ubicación *</label>
                        <select v-model="resolveUnlocatedForm.recovery_location" class="w-full h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545]">
                            <option value="plant_empty">Vacíos en Planta</option>
                            <option value="plant_full">Llenos en Planta</option>
                            <option value="truck_full">Llenos en Camión</option>
                            <option value="truck_empty">Vacíos en Camión</option>
                        </select>
                    </div>
                    <div v-if="resolveUnlocatedForm.resolution_type === 'danado'">
                        <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Daño *</label>
                        <select v-model="resolveUnlocatedForm.damage_type" class="w-full h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545]">
                            <option value="rotura">Roto</option>
                            <option value="fisura">Fisurado</option>
                            <option value="contaminacion">Contaminado</option>
                            <option value="deformacion">Deformado</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Notas</label>
                        <input v-model="resolveUnlocatedForm.notes" type="text" class="w-full h-10 text-xs rounded-xl border-[#E9E7F0]" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showResolveUnlocatedModal = false" class="h-10 px-4 rounded-xl text-xs font-bold text-[#747181] hover:bg-[#F4F3F8] cursor-pointer">Cancelar</button>
                        <button type="submit" :disabled="resolveUnlocatedForm.processing" class="h-10 px-5 rounded-xl bg-[#1E1545] text-white text-xs font-bold cursor-pointer">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
