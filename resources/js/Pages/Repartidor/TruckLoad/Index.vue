<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RepartidorBottomNav from '@/Components/RepartidorBottomNav.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

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
const formError = computed(() => page.props.errors?.transfer || page.props.errors?.unload || null);

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
    <Head title="Mi carga" />

    <AuthenticatedLayout wide>
        <div class="py-4 sm:py-6 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5 pb-28 md:pb-16">

            <div
                v-if="formError"
                class="px-4 py-3.5 rounded-2xl bg-[#FCECEF] border border-[#E82147]/25 text-[#292638] text-xs sm:text-sm flex items-center gap-3"
            >
                <div class="h-8 w-8 rounded-lg bg-[#E82147] text-white flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                </div>
                <div class="flex-1 font-semibold text-[#E82147] leading-relaxed">{{ formError }}</div>
            </div>

            <!-- ========================================================================= -->
            <!-- 1. CARGAS DE FIJOS POR CONFIRMAR (SOLO CUANDO EXISTAN)                    -->
            <!-- ========================================================================= -->
            <div
                v-if="pendingTransfers.length > 0"
                class="bg-white rounded-2xl border border-[#A97827]/35 shadow-[0_2px_12px_-6px_rgba(30,21,69,0.06)] overflow-hidden"
            >
                <div class="px-4 sm:px-5 py-3.5 bg-[#F8F0DF] border-b border-[#A97827]/20 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-[#A97827]"></span>
                        <h3 class="font-bold text-[#292638] text-sm">
                            Cargas por confirmar ({{ pendingTransfers.length }})
                        </h3>
                    </div>
                    <span class="text-xs font-medium text-[#A97827]">
                        Confirmación requerida
                    </span>
                </div>

                <div class="divide-y divide-[#E9E7F0]">
                    <div
                        v-for="transfer in pendingTransfers"
                        :key="transfer.id"
                        class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-[#F8F0DF] text-[#A97827]">
                                    {{ transfer.bottle_type === 'cambio' ? 'Cambio lleno' : 'Fijo de cliente' }}
                                </span>
                                <span class="text-xs text-[#747181]">
                                    {{ formatDateTime(transfer.created_at) }}
                                </span>
                                <span v-if="transfer.user" class="text-xs text-[#747181]">
                                    · Autorizó: {{ transfer.user.name }}
                                </span>
                            </div>

                            <div class="flex items-baseline gap-2">
                                <span class="text-xl font-bold text-[#292638] tabular-nums">
                                    {{ transfer.proposed_quantity || transfer.quantity }} u. de 20L
                                </span>
                                <span class="text-xs font-medium text-[#747181]">
                                    enviadas por Planta
                                </span>
                            </div>

                            <div v-if="transfer.customer" class="text-xs text-[#292638] bg-[#F4F3F8] px-2.5 py-1.5 rounded-xl border border-[#E9E7F0] mt-1 inline-block">
                                <span class="font-semibold">Propietario:</span>
                                {{ transfer.customer.nickname ? `"${transfer.customer.nickname}"` : '' }}<template v-if="transfer.customer.nickname && transfer.customer.name"> · </template>{{ transfer.customer.name }}
                            </div>

                            <p v-if="transfer.notes" class="text-xs text-[#747181] italic">
                                Nota: {{ transfer.notes }}
                            </p>
                        </div>

                        <div v-if="canManage" class="flex items-center gap-2 shrink-0 pt-2 sm:pt-0">
                            <button
                                @click="openAcceptModal(transfer)"
                                type="button"
                                class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold bg-[#1E1545] text-white hover:bg-[#2B205E] transition-colors min-h-[44px] cursor-pointer"
                            >
                                <i class="fa-solid fa-boxes-stacked text-xs"></i>
                                <span>Revisar carga</span>
                            </button>
                            <button
                                @click="openRejectModal(transfer)"
                                type="button"
                                class="inline-flex items-center justify-center px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-[#FCECEF] text-[#E82147] hover:bg-[#F9DCE2] transition-colors min-h-[44px] cursor-pointer"
                            >
                                Rechazar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. RESUMEN DE CARGA EN CAMIÓN (CAMBIOS + FIJOS)                           -->
            <!-- ========================================================================= -->
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h1 class="text-lg sm:text-xl font-bold text-[#292638] tracking-[-0.015em]">
                            Mi carga en camión
                        </h1>
                        <p class="text-xs text-[#747181] mt-0.5">
                            Existencias actuales de 20L · {{ driver.name }}
                        </p>
                    </div>

                    <div v-if="canManage" class="flex flex-wrap items-center gap-2">
                        <button
                            @click="openInconvenienceModal"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-[#F8F0DF] hover:bg-[#F3E5C8] text-[#A97827] border border-[#A97827]/20 transition-colors min-h-[42px] cursor-pointer"
                        >
                            <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                            <span>Reportar dañado / perdido</span>
                        </button>

                        <button
                            v-if="truckInventory.fixed_empty_collected_total > 0"
                            @click="openUnloadModal"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-[#1E1545] hover:bg-[#2B205E] text-white transition-colors min-h-[42px] cursor-pointer"
                        >
                            <i class="fa-solid fa-arrow-down text-xs"></i>
                            <span>Entregar Fijos a Planta</span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <!-- BLOQUE 1: CAMBIOS (LECTURA DIRECTA PARA EL REPARTIDOR) -->
                    <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-[0_2px_12px_-6px_rgba(30,21,69,0.06)] p-5 space-y-4">
                        <div class="flex items-center justify-between border-b border-[#E9E7F0] pb-3.5">
                            <div class="flex items-center gap-3">
                                <div class="h-9 w-9 rounded-xl bg-[#E9F3F8] text-[#0071BA] flex items-center justify-center text-sm">
                                    <i class="fa-solid fa-bottle-water"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-[#292638] leading-tight">Cambios (20L)</h4>
                                    <span class="text-xs text-[#747181]">Planta controla subidas y bajadas</span>
                                </div>
                            </div>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-[#E9F3F8] text-[#0071BA] tabular-nums">
                                {{ truckInventory.exchange_full_available + truckInventory.exchange_empty_collected }} en camión
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <!-- Llenos Disponibles -->
                            <div class="bg-[#F4F3F8] p-3.5 rounded-xl border border-[#E9E7F0] space-y-1">
                                <span class="text-xs font-medium text-[#747181] block">
                                    Llenos para entregar
                                </span>
                                <div class="text-2xl sm:text-3xl font-bold text-[#0071BA] tabular-nums">
                                    {{ truckInventory.exchange_full_available }}
                                </div>
                                <span class="text-[11px] font-medium text-[#747181] block">
                                    Listos para clientes
                                </span>
                            </div>

                            <!-- Vacíos Recogidos -->
                            <div class="bg-[#F4F3F8] p-3.5 rounded-xl border border-[#E9E7F0] space-y-1">
                                <span class="text-xs font-medium text-[#747181] block">
                                    Vacíos de regreso
                                </span>
                                <div class="text-2xl sm:text-3xl font-bold text-[#292638] tabular-nums">
                                    {{ truckInventory.exchange_empty_collected }}
                                </div>
                                <span class="text-[11px] font-medium text-[#747181] block">
                                    Para entregar en Planta
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- BLOQUE 2: FIJOS -->
                    <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-[0_2px_12px_-6px_rgba(30,21,69,0.06)] p-5 space-y-4">
                        <div class="flex items-center justify-between border-b border-[#E9E7F0] pb-3.5">
                            <div class="flex items-center gap-3">
                                <div class="h-9 w-9 rounded-xl bg-[#DAD6E8]/70 text-[#1E1545] flex items-center justify-center text-sm">
                                    <i class="fa-solid fa-boxes-stacked"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-[#292638] leading-tight">Fijos (20L)</h4>
                                    <span class="text-xs text-[#747181]">Envases propios de clientes</span>
                                </div>
                            </div>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-[#DAD6E8]/70 text-[#1E1545] tabular-nums">
                                {{ truckInventory.fixed_full_to_deliver_total + truckInventory.fixed_empty_collected_total }} en camión
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <!-- Llenos Para Devolver -->
                            <div class="bg-[#F4F3F8] p-3.5 rounded-xl border border-[#E9E7F0] space-y-1">
                                <span class="text-xs font-medium text-[#747181] block">
                                    Llenos preparados
                                </span>
                                <div class="text-2xl sm:text-3xl font-bold text-[#34785A] tabular-nums">
                                    {{ truckInventory.fixed_full_to_deliver_total }}
                                </div>
                                <span class="text-[11px] font-medium text-[#34785A] block">
                                    Para devolver
                                </span>
                            </div>

                            <!-- Vacíos Para Planta -->
                            <div class="bg-[#F4F3F8] p-3.5 rounded-xl border border-[#E9E7F0] space-y-1">
                                <span class="text-xs font-medium text-[#747181] block">
                                    Vacíos recogidos
                                </span>
                                <div class="text-2xl sm:text-3xl font-bold text-[#292638] tabular-nums">
                                    {{ truckInventory.fixed_empty_collected_total }}
                                </div>
                                <span class="text-[11px] font-medium text-[#747181] block">
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
            <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-[0_2px_12px_-6px_rgba(30,21,69,0.06)] overflow-hidden">
                <div class="px-5 py-3.5 border-b border-[#E9E7F0] flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-[#292638] text-sm flex items-center gap-2">
                            <i class="fa-solid fa-users text-[#1E1545] text-xs"></i> Fijos por cliente en camión
                        </h3>
                        <p class="text-xs text-[#747181] mt-0.5">
                            Envases propios de clientes en custodia
                        </p>
                    </div>
                </div>

                <div v-if="fixedByCustomer.length === 0" class="p-4 sm:p-5 text-center">
                    <p class="text-xs font-medium text-[#747181]">No hay envases fijos en el camión</p>
                </div>

                <div v-else class="divide-y divide-[#E9E7F0]/75">
                    <div
                        v-for="item in fixedByCustomer"
                        :key="item.customer_id"
                        class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 hover:bg-[#F4F3F8]/60 transition-colors"
                    >
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-[#292638] text-sm">
                                    {{ item.customer_nickname || item.customer_name }}
                                </span>
                            </div>
                            <p v-if="item.customer_nickname && item.customer_name && item.customer_name !== item.customer_nickname" class="text-xs text-[#747181]">
                                {{ item.customer_name }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0 flex-wrap">
                            <div
                                v-if="item.fixed_full_to_deliver > 0"
                                class="px-3 py-1.5 rounded-xl bg-[#E8F2EC] border border-[#34785A]/20 text-[#34785A] text-xs font-semibold flex items-center gap-1.5 tabular-nums"
                            >
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Llenos para devolver:</span>
                                <span class="text-sm font-bold">{{ item.fixed_full_to_deliver }}</span>
                            </div>

                            <div
                                v-if="item.fixed_empty_collected > 0"
                                class="px-3 py-1.5 rounded-xl bg-[#F4F3F8] border border-[#E9E7F0] text-[#292638] text-xs font-semibold flex items-center gap-1.5 tabular-nums"
                            >
                                <i class="fa-solid fa-arrow-down text-[#747181]"></i>
                                <span>Vacíos para Planta:</span>
                                <span class="text-sm font-bold">{{ item.fixed_empty_collected }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 4. MOVIMIENTOS RECIENTES                                                  -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-[0_2px_12px_-6px_rgba(30,21,69,0.06)] overflow-hidden">
                <div class="px-5 py-3.5 border-b border-[#E9E7F0] flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-[#292638] text-sm flex items-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left text-[#747181] text-xs"></i> Movimientos recientes
                        </h3>
                        <p class="text-xs text-[#747181] mt-0.5">
                            Historial continuo de custodia en el camión
                        </p>
                    </div>
                </div>

                <div v-if="recentMovements.length === 0" class="p-5 text-center text-xs font-medium text-[#747181]">
                    No se registran movimientos recientes de custodia.
                </div>

                <div v-else class="divide-y divide-[#E9E7F0]/75 max-h-96 overflow-y-auto">
                    <div
                        v-for="mov in recentMovements"
                        :key="mov.id"
                        class="p-4 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2 hover:bg-[#F4F3F8]/60 transition-colors"
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
                                    · <span class="text-slate-500">Cliente: {{ mov.customer.nickname || mov.customer.name }}</span>
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
                                {{ c.customer_nickname ? `"${c.customer_nickname}"` : '' }}<template v-if="c.customer_nickname && c.customer_name"> - </template>{{ c.customer_name }} - Vacíos: {{ c.fixed_empty_collected }}
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

