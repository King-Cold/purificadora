<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RepartidorBottomNav from '@/Components/RepartidorBottomNav.vue';
import { Head, useForm, usePage, Link } from '@inertiajs/vue3';

const props = defineProps({
    driver: {
        type: Object,
        required: true,
    },
    truckInventory: {
        type: Object,
        required: true,
    },
    fixedByCustomer: {
        type: Array,
        default: () => [],
    },
    pendingTransfers: {
        type: Array,
        default: () => [],
    },
    pendingUnloads: {
        type: Array,
        default: () => [],
    },
    recentMovements: {
        type: Array,
        default: () => [],
    },
    canManage: {
        type: Boolean,
        default: true,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.errors?.transfer || page.props.errors?.unload || null);

// Modal para aceptar transferencia
const selectedTransfer = ref(null);
const showAcceptModal = ref(false);
const acceptForm = useForm({
    confirmed_quantity: 0,
    difference_reason: '',
    retained_in_plant_quantity: 0,
    damaged_quantity: 0,
    damage_type: 'fuga_cuerpo',
    damage_notes: '',
    unlocated_quantity: 0,
});

const damageTypes = [
    { value: 'fuga_cuerpo', label: 'Fuga en cuerpo / fisura' },
    { value: 'golpe_fondo', label: 'Golpe severo en fondo' },
    { value: 'cuello_roto', label: 'Cuello / boquilla rota' },
    { value: 'contaminacion_quimica', label: 'Contaminación u olor penetrante' },
    { value: 'desgaste_extremo', label: 'Desgaste extremo no apto' },
];

const transferDifference = computed(() => {
    if (!selectedTransfer.value) return 0;
    const proposed = selectedTransfer.value.proposed_quantity || selectedTransfer.value.quantity || 0;
    return Math.max(0, proposed - (acceptForm.confirmed_quantity || 0));
});

const acceptBreakdownTotal = computed(() => {
    return (acceptForm.retained_in_plant_quantity || 0) +
        (acceptForm.damaged_quantity || 0) +
        (acceptForm.unlocated_quantity || 0);
});

const isAcceptBreakdownValid = computed(() => {
    if (transferDifference.value === 0) return true;
    return acceptBreakdownTotal.value === transferDifference.value;
});

const openAcceptModal = (transfer) => {
    selectedTransfer.value = transfer;
    const qty = transfer.proposed_quantity || transfer.quantity;
    acceptForm.confirmed_quantity = qty;
    acceptForm.difference_reason = '';
    acceptForm.retained_in_plant_quantity = 0;
    acceptForm.damaged_quantity = 0;
    acceptForm.damage_type = 'fuga_cuerpo';
    acceptForm.damage_notes = '';
    acceptForm.unlocated_quantity = 0;
    acceptForm.clearErrors();
    showAcceptModal.value = true;
};

const closeAcceptModal = () => {
    showAcceptModal.value = false;
    selectedTransfer.value = null;
};

const submitAcceptTransfer = () => {
    if (!selectedTransfer.value || !isAcceptBreakdownValid.value) return;

    acceptForm.post(route('repartidor.truck-load.accept', selectedTransfer.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeAcceptModal();
        },
    });
};

// Modal para rechazar transferencia
const showRejectModal = ref(false);
const rejectForm = useForm({
    reason: '',
    retained_in_plant_quantity: 0,
    damaged_quantity: 0,
    damage_type: 'fuga_cuerpo',
    damage_notes: '',
    unlocated_quantity: 0,
});

const rejectBreakdownTotal = computed(() => {
    return (rejectForm.retained_in_plant_quantity || 0) +
        (rejectForm.damaged_quantity || 0) +
        (rejectForm.unlocated_quantity || 0);
});

const isRejectBreakdownValid = computed(() => {
    if (!selectedTransfer.value) return true;
    const proposed = selectedTransfer.value.proposed_quantity || selectedTransfer.value.quantity || 0;
    return rejectBreakdownTotal.value === proposed;
});

const openRejectModal = (transfer) => {
    selectedTransfer.value = transfer;
    const qty = transfer.proposed_quantity || transfer.quantity;
    rejectForm.reason = '';
    rejectForm.retained_in_plant_quantity = qty;
    rejectForm.damaged_quantity = 0;
    rejectForm.damage_type = 'fuga_cuerpo';
    rejectForm.damage_notes = '';
    rejectForm.unlocated_quantity = 0;
    rejectForm.clearErrors();
    showRejectModal.value = true;
};

const closeRejectModal = () => {
    showRejectModal.value = false;
    selectedTransfer.value = null;
};

const submitRejectTransfer = () => {
    if (!selectedTransfer.value || !isRejectBreakdownValid.value) return;

    rejectForm.post(route('repartidor.truck-load.reject', selectedTransfer.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeRejectModal();
        },
    });
};

// Modal para proponer descarga a planta
const showUnloadModal = ref(false);
const unloadForm = useForm({
    bottle_type: 'cambio',
    bottle_condition: 'empty',
    quantity: 1,
    customer_id: null,
    notes: '',
});

const openUnloadModal = () => {
    unloadForm.bottle_type = 'cambio';
    unloadForm.bottle_condition = 'empty';
    const availableEmpty = props.truckInventory.exchange_empty_uncommitted ?? props.truckInventory.exchange_empty_collected ?? 1;
    unloadForm.quantity = Math.max(1, availableEmpty);
    unloadForm.customer_id = null;
    unloadForm.notes = '';
    unloadForm.clearErrors();
    showUnloadModal.value = true;
};

const closeUnloadModal = () => {
    showUnloadModal.value = false;
};

const submitUnload = () => {
    unloadForm.post(route('repartidor.truck-load.propose-unload'), {
        preserveScroll: true,
        onSuccess: () => {
            closeUnloadModal();
        },
    });
};

// Modal para reportar inconveniente (botón ámbar)
const showInconvenienceModal = ref(false);
const inconvenienceForm = useForm({
    quantity: 1,
    origin_stage: 'truck_full',
    reason: 'rotura',
    location_status: 'separado',
    notes: '',
});

const openInconvenienceModal = () => {
    inconvenienceForm.quantity = 1;
    inconvenienceForm.origin_stage = (props.truckInventory.exchange_full_available > 0) ? 'truck_full' : 'truck_empty';
    inconvenienceForm.reason = 'rotura';
    inconvenienceForm.location_status = 'separado';
    inconvenienceForm.notes = '';
    inconvenienceForm.clearErrors();
    showInconvenienceModal.value = true;
};

const submitInconvenience = () => {
    if (inconvenienceForm.processing) return;
    inconvenienceForm.post(route('repartidor.truck-load.report-inconvenience'), {
        preserveScroll: true,
        onSuccess: () => {
            showInconvenienceModal.value = false;
        },
    });
};

const showHistoryMobile = ref(false);

const formatDateTime = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleString('es-MX', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

const getMovementBadgeClass = (type) => {
    switch (type) {
        case 'truck_load_exchange':
        case 'truck_load_fixed':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'delivery_refill_to_customer':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'collection_exchange_empty':
        case 'collection_fixed_from_customer':
            return 'bg-amber-100 text-amber-800 border-amber-200';
        case 'truck_unload_exchange':
        case 'plant_reception_fixed':
            return 'bg-purple-100 text-purple-800 border-purple-200';
        case 'return_empty_fixed_to_customer':
            return 'bg-rose-100 text-rose-800 border-rose-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};

const getMovementLabel = (type) => {
    const labels = {
        'truck_load_exchange': 'Carga de Cambio Lleno',
        'truck_load_fixed': 'Carga de Fijo Lleno',
        'delivery_refill_to_customer': 'Entrega en Domicilio',
        'collection_exchange_empty': 'Recolección Vacío Cambio',
        'collection_fixed_from_customer': 'Recolección Fijo Vacío',
        'truck_unload_exchange': 'Descarga Vacíos a Planta',
        'plant_reception_fixed': 'Entrega de Fijos a Planta',
        'return_empty_fixed_to_customer': 'Devolución de Vacíos (Incidencia)',
    };
    return labels[type] || type;
};

// Mapeo amigable para origen y destino (Camión, Planta, Cliente)
const getLocationLabel = (loc) => {
    const map = {
        'truck': 'Camión',
        'plant': 'Planta',
        'customer': 'Cliente',
        'storage': 'Planta',
    };
    return map[loc] || loc;
};
</script>

<template>
    <Head title="Mi Carga - Puritronic" />

    <AuthenticatedLayout wide>
        <div class="py-4 sm:py-6 max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 space-y-4 pb-28 md:pb-16">

            <!-- Mensajes Flash de Éxito / Error -->
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
            <!-- 1. CARGAS POR CONFIRMAR (PRIMER LUGAR CUANDO EXISTAN)                     -->
            <!-- ========================================================================= -->
            <div
                v-if="pendingTransfers.length > 0"
                class="bg-amber-50/70 rounded-3xl border border-amber-200 shadow-2xs overflow-hidden"
            >
                <div class="px-4 sm:px-5 py-3.5 bg-amber-100/60 border-b border-amber-200/80 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                        <h3 class="font-black text-amber-950 text-sm sm:text-base">
                            Cargas por confirmar ({{ pendingTransfers.length }})
                        </h3>
                    </div>
                    <span class="text-xs font-semibold text-amber-800">
                        Confirmación física requerida
                    </span>
                </div>

                <div class="divide-y divide-amber-200/60">
                    <div
                        v-for="transfer in pendingTransfers"
                        :key="transfer.id"
                        class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white hover:bg-amber-50/30 transition"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold uppercase tracking-wider bg-amber-100 text-amber-900 border border-amber-200">
                                    {{ transfer.bottle_type === 'cambio' ? 'Cambio lleno' : 'Fijo de cliente' }}
                                </span>
                                <span class="text-xs text-slate-500">
                                    {{ formatDateTime(transfer.created_at) }}
                                </span>
                                <span v-if="transfer.user" class="text-xs text-slate-500">
                                    · Autorizó: {{ transfer.user.name }}
                                </span>
                            </div>

                            <div class="flex items-baseline gap-2">
                                <span class="text-xl sm:text-2xl font-black text-slate-900">
                                    {{ transfer.proposed_quantity || transfer.quantity }} u. de 20L
                                </span>
                                <span class="text-xs font-semibold text-slate-500">
                                    propuestas por Planta
                                </span>
                            </div>

                            <div v-if="transfer.customer" class="text-xs text-slate-700 bg-slate-50 px-2.5 py-1.5 rounded-xl border border-slate-200 mt-1 inline-block">
                                <span class="font-bold text-slate-900">Propietario:</span>
                                {{ transfer.customer.nickname ? `"${transfer.customer.nickname}" · ` : '' }}{{ transfer.customer.name }} ({{ transfer.customer.code }})
                            </div>

                            <p v-if="transfer.notes" class="text-xs text-slate-500 italic">
                                Nota: {{ transfer.notes }}
                            </p>
                        </div>

                        <div v-if="canManage" class="flex items-center gap-2 shrink-0 pt-2 sm:pt-0">
                            <button
                                @click="openAcceptModal(transfer)"
                                type="button"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-black bg-blue-900 text-white hover:bg-blue-800 active:scale-95 transition shadow-2xs min-h-[44px]"
                            >
                                <i class="fa-solid fa-boxes-stacked text-sky-400"></i>
                                <span>Revisar carga</span>
                            </button>
                            <button
                                @click="openRejectModal(transfer)"
                                type="button"
                                class="inline-flex items-center justify-center px-3 py-2.5 rounded-xl text-xs font-bold bg-slate-100 text-rose-700 hover:bg-rose-50 border border-slate-200 hover:border-rose-200 transition min-h-[44px]"
                            >
                                Rechazar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estado breve si no hay cargas por confirmar -->
            <div
                v-else
                class="bg-white rounded-2xl border border-slate-200/80 p-3 sm:p-3.5 shadow-2xs flex items-center justify-between text-xs text-slate-500"
            >
                <div class="flex items-center gap-2 font-medium">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                    <span>No hay cargas por confirmar</span>
                </div>
                <span class="text-[11px] text-slate-400">Planta al día</span>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. RESUMEN DE CARGA (DOS BLOQUES: CAMBIOS Y FIJOS + ACCIÓN PLANTA)        -->
            <!-- ========================================================================= -->
            <div class="space-y-3">
                <!-- Cabecera de Resumen con Acción Directa: Entregar botellones a Planta -->
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <h3 class="text-base sm:text-lg font-black text-slate-900">
                            Resumen de carga en camión
                        </h3>
                        <p class="text-xs text-slate-500">
                            Inventario continuo de botellones de 20L
                        </p>
                    </div>

                    <!-- Acciones accesibles junto al resumen -->
                    <div v-if="canManage" class="flex flex-wrap items-center gap-2">
                        <button
                            @click="openInconvenienceModal"
                            type="button"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-amber-500 hover:bg-amber-600 text-white shadow-2xs active:scale-95 transition min-h-[44px] cursor-pointer"
                        >
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>Reportar inconveniente</span>
                        </button>

                        <button
                            @click="openUnloadModal"
                            type="button"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-[#0E1E42] hover:bg-blue-900 text-white shadow-2xs active:scale-95 transition min-h-[44px] cursor-pointer"
                        >
                            <i class="fa-solid fa-arrow-down text-sky-400"></i>
                            <span>Entregar botellones a Planta</span>
                        </button>
                    </div>
                </div>

                <!-- Cuadrícula de 2 Bloques: Cambios y Fijos -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <!-- BLOQUE 1: CAMBIOS -->
                    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-4 sm:p-5 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                            <div class="flex items-center gap-2">
                                <div class="h-8 w-8 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center text-sm font-black">
                                    <i class="fa-solid fa-bottle-water"></i>
                                </div>
                                <div>
                                    <h4 class="text-base font-black text-slate-900 leading-tight">Cambios</h4>
                                    <span class="text-xs text-slate-500 font-medium">Fondo genérico de 20L</span>
                                </div>
                            </div>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-md bg-blue-50 text-blue-900">
                                {{ truckInventory.exchange_full_available + truckInventory.exchange_empty_collected }} total en camión
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-1">
                            <!-- Llenos Disponibles -->
                            <div class="bg-slate-50/70 p-3 rounded-2xl border border-slate-100 space-y-1">
                                <span class="text-xs font-bold text-slate-500 block uppercase tracking-wider text-[11px]">
                                    Llenos disponibles
                                </span>
                                <div class="text-2xl sm:text-3xl font-black text-blue-900">
                                    {{ truckInventory.exchange_full_available }}
                                </div>
                                <span class="text-[11px] font-semibold text-slate-600 block">
                                    Para entregar en ruta
                                </span>
                            </div>

                            <!-- Vacíos Recogidos -->
                            <div class="bg-slate-50/70 p-3 rounded-2xl border border-slate-100 space-y-1">
                                <span class="text-xs font-bold text-slate-500 block uppercase tracking-wider text-[11px]">
                                    Vacíos recogidos
                                </span>
                                <div class="text-2xl sm:text-3xl font-black text-slate-800">
                                    {{ truckInventory.exchange_empty_collected }}
                                </div>
                                <span v-if="truckInventory.exchange_empty_committed_in_unload > 0" class="text-[11px] font-bold text-amber-800 block">
                                    {{ truckInventory.exchange_empty_committed_in_unload }} pendientes de confirmar en Planta ({{ truckInventory.exchange_empty_uncommitted }} libres)
                                </span>
                                <span v-else class="text-[11px] font-semibold text-slate-600 block">
                                    Listos para Planta
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- BLOQUE 2: FIJOS -->
                    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-4 sm:p-5 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                            <div class="flex items-center gap-2">
                                <div class="h-8 w-8 rounded-xl bg-purple-50 text-purple-900 flex items-center justify-center text-sm font-black">
                                    <i class="fa-solid fa-boxes-stacked"></i>
                                </div>
                                <div>
                                    <h4 class="text-base font-black text-slate-900 leading-tight">Fijos</h4>
                                    <span class="text-xs text-slate-500 font-medium">Propiedad de clientes</span>
                                </div>
                            </div>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-md bg-purple-50 text-purple-900">
                                {{ truckInventory.fixed_full_to_deliver_total + truckInventory.fixed_empty_collected_total }} total
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-1">
                            <!-- Llenos Para Devolver -->
                            <div class="bg-slate-50/70 p-3 rounded-2xl border border-slate-100 space-y-1">
                                <span class="text-xs font-bold text-slate-500 block uppercase tracking-wider text-[11px]">
                                    Llenos preparados
                                </span>
                                <div class="text-2xl sm:text-3xl font-black text-emerald-900">
                                    {{ truckInventory.fixed_full_to_deliver_total }}
                                </div>
                                <span class="text-[11px] font-semibold text-emerald-700 block">
                                    Para devolver
                                </span>
                            </div>

                            <!-- Vacíos Para Planta -->
                            <div class="bg-slate-50/70 p-3 rounded-2xl border border-slate-100 space-y-1">
                                <span class="text-xs font-bold text-slate-500 block uppercase tracking-wider text-[11px]">
                                    Vacíos recogidos
                                </span>
                                <div class="text-2xl sm:text-3xl font-black text-slate-800">
                                    {{ truckInventory.fixed_empty_collected_total }}
                                </div>
                                <span class="text-[11px] font-semibold text-slate-600 block">
                                    Llevando a Planta
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 3. FIJOS POR CLIENTE (LISTA COMPACTA)                                     -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs overflow-hidden">
                <div class="px-4 sm:px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-black text-slate-900 text-sm sm:text-base flex items-center gap-2">
                            <i class="fa-solid fa-users text-blue-900"></i> Fijos por cliente en camión
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Identificación de envases asignados en custodia
                        </p>
                    </div>
                </div>

                <div v-if="fixedByCustomer.length === 0" class="p-4 sm:p-5 text-center">
                    <p class="text-xs font-semibold text-slate-500">No hay envases fijos en el camión</p>
                </div>

                <div v-else class="divide-y divide-slate-100">
                    <div
                        v-for="item in fixedByCustomer"
                        :key="item.customer_id"
                        class="p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 hover:bg-slate-50/50 transition"
                    >
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-black text-slate-900 text-sm sm:text-base">
                                    {{ item.customer_nickname || item.customer_name }}
                                </span>
                                <span class="px-2 py-0.5 rounded-md text-[11px] font-mono font-bold bg-slate-100 text-slate-600">
                                    {{ item.customer_code }}
                                </span>
                            </div>
                            <p v-if="item.customer_nickname && item.customer_name && item.customer_name !== item.customer_nickname" class="text-xs text-slate-500">
                                {{ item.customer_name }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <div
                                v-if="item.fixed_full_to_deliver > 0"
                                class="px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-bold flex items-center gap-1.5"
                            >
                                <i class="fa-solid fa-circle-check text-emerald-600"></i>
                                <span>Llenos para devolver:</span>
                                <span class="text-sm font-black">{{ item.fixed_full_to_deliver }}</span>
                            </div>

                            <div
                                v-if="item.fixed_empty_collected > 0"
                                class="px-3 py-1.5 rounded-xl bg-slate-100 border border-slate-200 text-slate-800 text-xs font-bold flex items-center gap-1.5"
                            >
                                <i class="fa-solid fa-arrow-down text-slate-500"></i>
                                <span>Vacíos para Planta:</span>
                                <span class="text-sm font-black">{{ item.fixed_empty_collected }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 4. MOVIMIENTOS RECIENTES (CON ETIQUETAS CAMIÓN → PLANTA, CLIENTE → CAMIÓN)-->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs overflow-hidden">
                <div class="px-4 sm:px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-black text-slate-900 text-sm sm:text-base flex items-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left text-slate-700"></i> Movimientos recientes
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Historial continuo de custodia en el camión
                        </p>
                    </div>
                </div>

                <div v-if="recentMovements.length === 0" class="p-4 sm:p-5 text-center text-xs font-semibold text-slate-400">
                    No se registran movimientos recientes de custodia.
                </div>

                <div v-else class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
                    <div
                        v-for="mov in recentMovements"
                        :key="mov.id"
                        class="p-3.5 sm:p-4 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2 hover:bg-slate-50 transition"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span
                                    class="px-2 py-0.5 rounded-md font-bold uppercase tracking-wider text-[10px] border"
                                    :class="getMovementBadgeClass(mov.movement_type)"
                                >
                                    {{ getMovementLabel(mov.movement_type) }}
                                </span>
                                <span class="font-black text-slate-900 text-sm">
                                    {{ mov.quantity }} u.
                                </span>
                                <span class="text-slate-500 font-medium">
                                    ({{ mov.bottle_type }} {{ mov.bottle_condition === 'full' ? 'lleno' : 'vacío' }})
                                </span>
                                <span
                                    v-if="mov.status !== 'accepted'"
                                    class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800"
                                >
                                    {{ mov.status }}
                                </span>
                            </div>

                            <p class="text-slate-700 font-medium">
                                <span class="text-slate-400 font-normal">Flujo:</span>
                                <span class="font-bold text-slate-900 mx-1">{{ getLocationLabel(mov.origin) }} → {{ getLocationLabel(mov.destination) }}</span>
                                <template v-if="mov.customer">
                                    · <span class="text-slate-500">Cliente: {{ mov.customer.nickname || mov.customer.name }} ({{ mov.customer.code }})</span>
                                </template>
                            </p>

                            <p v-if="mov.difference_reason" class="text-amber-800 bg-amber-50 px-2 py-1 rounded-lg border border-amber-200 flex items-center gap-1.5 font-medium">
                                <i class="fa-solid fa-triangle-exclamation text-amber-600"></i> Motivo: {{ mov.difference_reason }}
                            </p>
                        </div>

                        <div class="text-right text-slate-400 shrink-0">
                            <div class="font-medium text-slate-600">{{ formatDateTime(mov.created_at) }}</div>
                            <div v-if="mov.confirmed_by_user" class="text-[11px] text-slate-500">
                                Conf: {{ mov.confirmed_by_user.name }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: ACEPTAR TRANSFERENCIA                                              -->
        <!-- ========================================================================= -->
        <div
            v-if="showAcceptModal && selectedTransfer"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-check text-emerald-600"></i> Confirmar Recepción Física
                    </h3>
                    <button @click="closeAcceptModal" type="button" class="text-slate-400 hover:text-slate-700">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="text-xs text-slate-600 bg-blue-50 p-3 rounded-xl border border-blue-100 space-y-1">
                    <p><span class="font-bold text-blue-900">Tipo:</span> {{ selectedTransfer.bottle_type === 'cambio' ? 'Botellones de Cambio Llenos' : 'Botellones Fijos de Cliente' }}</p>
                    <p><span class="font-bold text-blue-900">Cantidad propuesta por planta:</span> {{ selectedTransfer.proposed_quantity || selectedTransfer.quantity }} unidades</p>
                </div>

                <form @submit.prevent="submitAcceptTransfer" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Cantidad real recibida en el camión *
                        </label>
                        <input
                            v-model.number="acceptForm.confirmed_quantity"
                            type="number"
                            min="0"
                            :max="selectedTransfer.proposed_quantity || selectedTransfer.quantity"
                            class="w-full text-base font-bold text-slate-900 rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                            required
                        />
                        <p v-if="acceptForm.errors.confirmed_quantity" class="text-xs text-rose-600 mt-1">
                            {{ acceptForm.errors.confirmed_quantity }}
                        </p>
                    </div>

                    <!-- Si la cantidad confirmada difiere de la propuesta, capturar desglose estructurado y motivo -->
                    <div v-if="transferDifference > 0" class="space-y-3 p-3.5 bg-amber-50/60 rounded-xl border border-amber-200">
                        <div class="flex items-center justify-between text-xs font-bold text-amber-900">
                            <span>Diferencia no cargada al camión:</span>
                            <span class="text-sm font-extrabold text-amber-800">{{ transferDifference }} unidades</span>
                        </div>
                        <p class="text-[11px] text-amber-800">
                            Indica qué ocurrió físicamente con las unidades no recibidas para actualizar existencias y custodia de forma transparente:
                        </p>

                        <!-- Controles estructurados de desglose -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                            <div class="bg-white p-2.5 rounded-lg border border-slate-200">
                                <label class="block text-[11px] font-bold text-slate-700 mb-1">
                                    Retenidos en Planta
                                </label>
                                <input
                                    v-model.number="acceptForm.retained_in_plant_quantity"
                                    type="number"
                                    min="0"
                                    :max="transferDifference"
                                    class="w-full text-xs font-bold rounded-lg border-slate-300 focus:border-blue-700"
                                />
                                <span class="text-[10px] text-slate-400 mt-0.5 block">Quedan aptos en almacén</span>
                            </div>

                            <div class="bg-white p-2.5 rounded-lg border border-slate-200">
                                <label class="block text-[11px] font-bold text-rose-700 mb-1">
                                    Envases Dañados
                                </label>
                                <input
                                    v-model.number="acceptForm.damaged_quantity"
                                    type="number"
                                    min="0"
                                    :max="transferDifference"
                                    class="w-full text-xs font-bold rounded-lg border-slate-300 focus:border-rose-500"
                                />
                                <span class="text-[10px] text-rose-500 mt-0.5 block">Genera incidencia</span>
                            </div>

                            <div class="bg-white p-2.5 rounded-lg border border-slate-200">
                                <label class="block text-[11px] font-bold text-slate-700 mb-1">
                                    Sin Localizar / Faltante
                                </label>
                                <input
                                    v-model.number="acceptForm.unlocated_quantity"
                                    type="number"
                                    min="0"
                                    :max="transferDifference"
                                    class="w-full text-xs font-bold rounded-lg border-slate-300 focus:border-amber-600"
                                />
                                <span class="text-[10px] text-slate-400 mt-0.5 block">Pendiente de aclaración</span>
                            </div>
                        </div>

                        <!-- Detalle de daño si aplica -->
                        <div v-if="acceptForm.damaged_quantity > 0" class="space-y-2 p-2.5 bg-rose-50 rounded-lg border border-rose-200">
                            <label class="block text-[11px] font-bold text-rose-800 uppercase">
                                Tipo de daño detectado
                            </label>
                            <select
                                v-model="acceptForm.damage_type"
                                class="w-full text-xs rounded-lg border-rose-300 focus:border-rose-500"
                            >
                                <option v-for="dt in damageTypes" :key="dt.value" :value="dt.value">
                                    {{ dt.label }}
                                </option>
                            </select>
                            <input
                                v-model="acceptForm.damage_notes"
                                type="text"
                                placeholder="Detalles de rotura / fuga..."
                                class="w-full text-xs rounded-lg border-rose-300 focus:border-rose-500 mt-1"
                            />
                        </div>

                        <!-- Indicador de cuadratura -->
                        <div
                            :class="[
                                'p-2 rounded-lg text-xs font-semibold flex items-center justify-between',
                                isAcceptBreakdownValid
                                    ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                                    : 'bg-rose-50 text-rose-800 border border-rose-200'
                            ]"
                        >
                            <span>Total desglosado: {{ acceptBreakdownTotal }} de {{ transferDifference }}</span>
                            <span v-if="isAcceptBreakdownValid"><i class="fa-solid fa-circle-check text-emerald-600"></i> Cuadrado</span>
                            <span v-else><i class="fa-solid fa-triangle-exclamation text-rose-600"></i> Descuadre</span>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-amber-800 mb-1">
                                Motivo de la diferencia * (Obligatorio)
                            </label>
                            <textarea
                                v-model="acceptForm.difference_reason"
                                rows="2"
                                class="w-full text-xs rounded-xl border-amber-300 focus:border-amber-500 focus:ring-amber-500"
                                placeholder="Ej. Faltaron 2 garrafones por envase fisurado y 1 retenido en planta"
                                required
                            ></textarea>
                            <p v-if="acceptForm.errors.difference_reason" class="text-xs text-rose-600 mt-1">
                                {{ acceptForm.errors.difference_reason }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            @click="closeAcceptModal"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-medium text-slate-600 hover:bg-slate-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="acceptForm.processing || !isAcceptBreakdownValid"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold bg-blue-900 text-white hover:bg-blue-800 active:scale-95 disabled:opacity-50 transition cursor-pointer"
                        >
                            {{ acceptForm.processing ? 'Confirmando...' : 'Aceptar y Sumar a Carga' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: RECHAZAR TRANSFERENCIA                                             -->
        <!-- ========================================================================= -->
        <div
            v-if="showRejectModal && selectedTransfer"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-lg font-bold text-rose-900 flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> Rechazar Transferencia
                    </h3>
                    <button @click="closeRejectModal" type="button" class="text-slate-400 hover:text-slate-700">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="text-xs text-slate-600 bg-rose-50 p-3 rounded-xl border border-rose-100 space-y-1">
                    <p><span class="font-bold text-rose-900">Propuesta rechazada:</span> {{ selectedTransfer.proposed_quantity || selectedTransfer.quantity }} unidades</p>
                    <p class="text-[11px] text-rose-700">Por defecto, las unidades permanecen en Planta y sus reservas se liberan de forma segura.</p>
                </div>

                <form @submit.prevent="submitRejectTransfer" class="space-y-4">
                    <!-- Desglose físico de las unidades rechazadas -->
                    <div class="space-y-2 p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <label class="block text-[11px] font-bold text-slate-700 uppercase">
                            Situación física de los envases (Total: {{ selectedTransfer.proposed_quantity || selectedTransfer.quantity }})
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                            <div class="bg-white p-2 rounded-lg border border-slate-200">
                                <span class="text-[10px] font-bold text-slate-600 block">En Planta (Aptos)</span>
                                <input
                                    v-model.number="rejectForm.retained_in_plant_quantity"
                                    type="number"
                                    min="0"
                                    class="w-full text-xs font-bold rounded border-slate-300 mt-1"
                                />
                            </div>
                            <div class="bg-white p-2 rounded-lg border border-slate-200">
                                <span class="text-[10px] font-bold text-rose-700 block">Dañados</span>
                                <input
                                    v-model.number="rejectForm.damaged_quantity"
                                    type="number"
                                    min="0"
                                    class="w-full text-xs font-bold rounded border-slate-300 mt-1"
                                />
                            </div>
                            <div class="bg-white p-2 rounded-lg border border-slate-200">
                                <span class="text-[10px] font-bold text-slate-600 block">Sin localizar</span>
                                <input
                                    v-model.number="rejectForm.unlocated_quantity"
                                    type="number"
                                    min="0"
                                    class="w-full text-xs font-bold rounded border-slate-300 mt-1"
                                />
                            </div>
                        </div>

                        <!-- Detalle de daño si aplica en rechazo -->
                        <div v-if="rejectForm.damaged_quantity > 0" class="space-y-2 pt-2 border-t border-slate-200">
                            <select
                                v-model="rejectForm.damage_type"
                                class="w-full text-xs rounded border-rose-300 focus:border-rose-500"
                            >
                                <option v-for="dt in damageTypes" :key="dt.value" :value="dt.value">
                                    {{ dt.label }}
                                </option>
                            </select>
                            <input
                                v-model="rejectForm.damage_notes"
                                type="text"
                                placeholder="Detalles de envase dañado..."
                                class="w-full text-xs rounded border-rose-300 focus:border-rose-500"
                            />
                        </div>

                        <div
                            :class="[
                                'p-1.5 rounded text-[11px] font-semibold flex justify-between',
                                isRejectBreakdownValid ? 'text-emerald-700 bg-emerald-50' : 'text-rose-700 bg-rose-50'
                            ]"
                        >
                            <span>Desglosado: {{ rejectBreakdownTotal }} u.</span>
                            <span v-if="!isRejectBreakdownValid">Debe sumar {{ selectedTransfer.proposed_quantity || selectedTransfer.quantity }} u.</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Motivo de rechazo * (Obligatorio)
                        </label>
                        <textarea
                            v-model="rejectForm.reason"
                            rows="2"
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-rose-500 focus:ring-rose-500"
                            placeholder="Ej. Carga no solicitada, capacidad del camión llena o envases no listos"
                            required
                        ></textarea>
                        <p v-if="rejectForm.errors.reason" class="text-xs text-rose-600 mt-1">
                            {{ rejectForm.errors.reason }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            @click="closeRejectModal"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-medium text-slate-600 hover:bg-slate-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="rejectForm.processing || !isRejectBreakdownValid"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold bg-rose-600 text-white hover:bg-rose-700 active:scale-95 disabled:opacity-50 transition cursor-pointer"
                        >
                            {{ rejectForm.processing ? 'Rechazando...' : 'Confirmar Rechazo' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: REGISTRAR DESCARGA A PLANTA                                        -->
        <!-- ========================================================================= -->
        <div
            v-if="showUnloadModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-arrow-down text-slate-700"></i> Entregar botellones a planta
                    </h3>
                    <button @click="closeUnloadModal" type="button" class="text-slate-400 hover:text-slate-700">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form @submit.prevent="submitUnload" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Modalidad de envase *</label>
                        <select
                            v-model="unloadForm.bottle_type"
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                        >
                            <option value="cambio">Cambio</option>
                            <option value="fijo">Fijo de Cliente Propietario</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Condición *</label>
                        <select
                            v-model="unloadForm.bottle_condition"
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                        >
                            <option value="empty">Vacío (recogido en ruta)</option>
                            <option value="full">Lleno (sobrante no entregado)</option>
                        </select>
                    </div>

                    <!-- Si es fijo, seleccionar cliente propietario disponible -->
                    <div v-if="unloadForm.bottle_type === 'fijo'">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Cliente Propietario *</label>
                        <select
                            v-model="unloadForm.customer_id"
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                            required
                        >
                            <option :value="null" disabled>Seleccionar cliente con fijos en camión</option>
                            <option
                                v-for="c in fixedByCustomer"
                                :key="c.customer_id"
                                :value="c.customer_id"
                            >
                                {{ c.customer_nickname ? `"${c.customer_nickname}" - ` : '' }}{{ c.customer_name }} ({{ c.customer_code }}) - Vacíos: {{ c.fixed_empty_collected }}
                            </option>
                        </select>
                        <p v-if="unloadForm.errors.customer_id" class="text-xs text-rose-600 mt-1">
                            {{ unloadForm.errors.customer_id }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Cantidad a entregar *</label>
                        <input
                            v-model.number="unloadForm.quantity"
                            type="number"
                            min="1"
                            class="w-full text-base font-bold text-slate-900 rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                            required
                        />
                        <p v-if="unloadForm.errors.quantity" class="text-xs text-rose-600 mt-1">
                            {{ unloadForm.errors.quantity }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Notas u observaciones</label>
                        <input
                            v-model="unloadForm.notes"
                            type="text"
                            placeholder="Ej. Retorno al cierre de ruta"
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            @click="closeUnloadModal"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-medium text-slate-600 hover:bg-slate-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="unloadForm.processing"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold bg-blue-900 text-white hover:bg-blue-800 active:scale-95 disabled:opacity-50 transition"
                        >
                            {{ unloadForm.processing ? 'Registrando...' : 'Registrar entrega a planta' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: REPORTAR INCONVENIENTE EN CAMIÓN (BOTÓN ÁMBAR)                     -->
        <!-- ========================================================================= -->
        <div
            v-if="showInconvenienceModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="h-10 w-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">Reportar inconveniente</h3>
                            <p class="text-xs text-slate-500">Botellón de cambio roto, dañado o sin localizar en camión</p>
                        </div>
                    </div>
                    <button @click="showInconvenienceModal = false" type="button" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form @submit.prevent="submitInconvenience" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Cantidad *</label>
                            <input
                                v-model.number="inconvenienceForm.quantity"
                                type="number"
                                min="1"
                                required
                                class="w-full h-11 text-base font-bold text-slate-900 rounded-xl border-slate-300"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Existencia afectada *</label>
                            <select
                                v-model="inconvenienceForm.origin_stage"
                                class="w-full h-11 text-xs font-semibold rounded-xl border-slate-300 text-slate-800"
                            >
                                <option value="truck_full">Llenos disponibles ({{ truckInventory.exchange_full_available }})</option>
                                <option value="truck_empty">Vacíos recogidos ({{ truckInventory.exchange_empty_collected }})</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Motivo *</label>
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
                        <label class="block text-xs font-bold text-slate-700 mb-1">Ubicación física *</label>
                        <select
                            v-model="inconvenienceForm.location_status"
                            class="w-full h-11 text-xs font-semibold rounded-xl border-slate-300 text-slate-800"
                        >
                            <option value="separado">Separado físicamente en el camión</option>
                            <option value="sin_localizar">Pendiente de aclarar / Sin localizar</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Notas *</label>
                        <input
                            v-model="inconvenienceForm.notes"
                            type="text"
                            required
                            placeholder="Ej: Se fisuró en traslado de ruta"
                            class="w-full h-11 text-xs rounded-xl border-slate-300"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            @click="showInconvenienceModal = false"
                            type="button"
                            class="min-h-[44px] px-4 py-2 rounded-xl text-xs font-medium text-slate-600 hover:bg-slate-100 cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="inconvenienceForm.processing"
                            class="min-h-[44px] px-5 py-2.5 rounded-xl text-xs font-bold bg-amber-600 text-white hover:bg-amber-700 transition cursor-pointer"
                        >
                            Guardar reporte
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Barra inferior de navegación móvil para Repartidor -->
        <RepartidorBottomNav />
    </AuthenticatedLayout>
</template>

