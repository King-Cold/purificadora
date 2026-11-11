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
        default: 'sin_iniciar',
    },
    journeyStatusLabel: {
        type: String,
        default: 'Sin iniciar',
    },
    truckState: {
        type: Object,
        default: () => ({ exchange_full_available: 0 }),
    },
    activeTab: {
        type: String,
        default: 'por_recoger',
    },
    activeZone: {
        type: String,
        default: 'all',
    },
    tabCounts: {
        type: Object,
        default: () => ({ por_recoger: 0, por_entregar: 0 }),
    },
    zoneCounts: {
        type: Object,
        default: () => ({ all: 0, north: 0, south: 0, east: 0, west: 0, none: 0 }),
    },
    items: {
        type: Array,
        default: () => [],
    },
    lastUpdatedAt: {
        type: String,
        default: '',
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.errors?.route || page.props.errors?.stop || page.props.errors?.quantity || page.props.errors?.collect || page.props.errors?.deliver || page.props.errors?.cancel || page.props.flash?.error || null);

// Pestañas (Por recoger, Por recoger en Planta, Por entregar)
const currentTab = computed(() => {
    if (props.activeTab === 'por_recoger_planta') return 'por_recoger_planta';
    if (props.activeTab === 'por_entregar') return 'por_entregar';
    return 'por_recoger';
});

const changeTab = (tabKey) => {
    router.get(
        route('repartidor.reparto'),
        { tab: tabKey, zone: props.activeZone },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

// Filtro de Zonas (Todas, Norte, Sur, Este, Oeste, Sin zona)
const zoneTabs = [
    { key: 'all', label: 'Todas' },
    { key: 'north', label: 'Norte' },
    { key: 'south', label: 'Sur' },
    { key: 'east', label: 'Este' },
    { key: 'west', label: 'Oeste' },
    { key: 'none', label: 'Sin zona' },
];

const changeZone = (zoneKey) => {
    router.get(
        route('repartidor.reparto'),
        { tab: currentTab.value, zone: zoneKey },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

// =========================================================================
// MODAL 1: DETALLE DEL CLIENTE / PEDIDO
// =========================================================================
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

// =========================================================================
// MODAL 2: CONFIRMAR RECOGIDA (POR RECOGER) - PALOMITA VERDE
// =========================================================================
const showCollectModal = ref(false);
const itemToCollect = ref(null);
const isAdvancePaymentMode = ref(false);
const unitPrice = computed(() => itemToCollect.value?.unit_price || 15);

const collectForm = useForm({
    quantity: 1,
    is_advance_payment: false,
    cash_tendered: 0,
    notes: '',
});

const openCollectModal = (item) => {
    itemToCollect.value = item;
    isAdvancePaymentMode.value = false;
    const initialQty = item.fixed_to_collect || item.fixed_quantity || 1;
    collectForm.quantity = initialQty;
    collectForm.is_advance_payment = false;
    collectForm.cash_tendered = initialQty * unitPrice.value;
    collectForm.notes = '';
    collectForm.clearErrors();
    showCollectModal.value = true;
};

const toggleAdvanceMode = () => {
    isAdvancePaymentMode.value = !isAdvancePaymentMode.value;
    collectForm.is_advance_payment = isAdvancePaymentMode.value;
    if (isAdvancePaymentMode.value) {
        collectForm.cash_tendered = collectForm.quantity * unitPrice.value;
    }
};

const incrementCollectQty = () => {
    const max = itemToCollect.value?.fixed_to_collect || itemToCollect.value?.fixed_quantity || 99;
    if (collectForm.quantity < max) {
        collectForm.quantity++;
        if (isAdvancePaymentMode.value) {
            collectForm.cash_tendered = collectForm.quantity * unitPrice.value;
        }
    }
};

const decrementCollectQty = () => {
    if (collectForm.quantity > 1) {
        collectForm.quantity--;
        if (isAdvancePaymentMode.value) {
            collectForm.cash_tendered = collectForm.quantity * unitPrice.value;
        }
    }
};

const closeCollectModal = () => {
    showCollectModal.value = false;
    itemToCollect.value = null;
};

const submitCollection = () => {
    if (!itemToCollect.value || collectForm.processing) return;

    collectForm.post(route('repartidor.orders.collect-fixed', itemToCollect.value.id), {
        preserveScroll: true,
        onSuccess: () => closeCollectModal(),
    });
};

// =========================================================================
// MODAL 3: CANCELAR PEDIDO (POR RECOGER) - PAPELERA ROJA
// =========================================================================
const showCancelModal = ref(false);
const itemToCancel = ref(null);
const cancelForm = useForm({
    reason: '',
});

const openCancelModal = (item) => {
    itemToCancel.value = item;
    cancelForm.reason = '';
    cancelForm.clearErrors();
    showCancelModal.value = true;
};

const closeCancelModal = () => {
    showCancelModal.value = false;
    itemToCancel.value = null;
};

const submitCancelOrder = () => {
    if (!itemToCancel.value || cancelForm.processing) return;

    if (cancelForm.reason.trim().length < 5) {
        alert('Debe especificar un motivo válido de cancelación (mínimo 5 caracteres).');
        return;
    }

    cancelForm.post(route('repartidor.orders.cancel', itemToCancel.value.id), {
        preserveScroll: true,
        onSuccess: () => closeCancelModal(),
    });
};

// =========================================================================
// MODAL 4: INCIDENCIA EN RECOGIDA O ENTREGA - ALERTA ÁMBAR
// =========================================================================
const showIncidentModal = ref(false);
const itemForIncident = ref(null);
const incidentTabType = ref('por_recoger');
const incidentForm = useForm({
    reason: 'Cliente no entregó envases completos',
    notes: '',
});

const openIncidentModal = (item, tab) => {
    itemForIncident.value = item;
    incidentTabType.value = tab;
    incidentForm.reason = tab === 'por_recoger' ? 'Cliente no entregó envases completos' : 'Cliente ausente en domicilio';
    incidentForm.notes = '';
    incidentForm.clearErrors();
    showIncidentModal.value = true;
};

const closeIncidentModal = () => {
    showIncidentModal.value = false;
    itemForIncident.value = null;
};

const submitIncident = () => {
    if (!itemForIncident.value || incidentForm.processing) return;

    // Si tiene stop_id en ruta activa, registra como visita con incidencia
    if (itemForIncident.value.stop_id) {
        incidentForm.post(route('repartidor.stops.fail', itemForIncident.value.stop_id), {
            preserveScroll: true,
            onSuccess: () => closeIncidentModal(),
        });
    } else {
        // En caso sin parada de ruta, simplemente cierra con notificación
        alert('Incidencia registrada para el pedido ' + itemForIncident.value.folio + ': ' + incidentForm.reason);
        closeIncidentModal();
    }
};

// =========================================================================
// MODAL 5: PAPELERA ROJA EN POR ENTREGAR (EXPLICACIÓN DE CUSTODIA)
// =========================================================================
const showCustodyTrashModal = ref(false);
const itemForCustodyTrash = ref(null);

const openCustodyTrashModal = (item) => {
    itemForCustodyTrash.value = item;
    showCustodyTrashModal.value = true;
};

const closeCustodyTrashModal = () => {
    showCustodyTrashModal.value = false;
    itemForCustodyTrash.value = null;
};

const deriveCustodyTrashToIncident = () => {
    const item = itemForCustodyTrash.value;
    closeCustodyTrashModal();
    openIncidentModal(item, 'por_entregar');
};

// =========================================================================
// MODAL: CONFIRMAR CARGA DESDE PLANTA (POR RECOGER EN PLANTA) - PALOMITA VERDE
// =========================================================================
const showPlantLoadModal = ref(false);
const itemToPlantLoad = ref(null);
const plantLoadForm = useForm({
    order_id: null,
    quantity: 1,
    difference_reason: '',
    notes: '',
});

const maxPlantReady = computed(() => itemToPlantLoad.value?.fixed_ready_to_load || itemToPlantLoad.value?.fixed_quantity || 1);

const openPlantLoadModal = (item) => {
    itemToPlantLoad.value = item;
    const initialQty = item.fixed_ready_to_load || item.fixed_quantity || 1;
    plantLoadForm.order_id = item.id;
    plantLoadForm.quantity = initialQty;
    plantLoadForm.difference_reason = '';
    plantLoadForm.notes = '';
    plantLoadForm.clearErrors();
    showPlantLoadModal.value = true;
};

const closePlantLoadModal = () => {
    showPlantLoadModal.value = false;
    itemToPlantLoad.value = null;
};

const incrementPlantLoadQty = () => {
    if (plantLoadForm.quantity < maxPlantReady.value) {
        plantLoadForm.quantity++;
    }
};

const decrementPlantLoadQty = () => {
    if (plantLoadForm.quantity > 1) {
        plantLoadForm.quantity--;
    }
};

const submitPlantLoad = () => {
    if (!itemToPlantLoad.value || plantLoadForm.processing) return;

    plantLoadForm.post(route('repartidor.ready-to-load.accept'), {
        preserveScroll: true,
        onSuccess: () => closePlantLoadModal(),
    });
};

// =========================================================================
// ACCIÓN MASIVA: ACEPTAR TODOS EN PLANTA
// =========================================================================
const showAcceptAllModal = ref(false);
const confirmAcceptAllPlant = () => {
    router.post(route('repartidor.ready-to-load.accept-all'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showAcceptAllModal.value = false;
        },
    });
};

// =========================================================================
// MODAL: REGISTRAR INCIDENCIA EN PLANTA (POR RECOGER EN PLANTA) - ALERTA ÁMBAR
// =========================================================================
const showPlantIncidentModal = ref(false);
const itemForPlantIncident = ref(null);
const plantIncidentForm = useForm({
    order_id: null,
    quantity: 1,
    damage_type: 'rotura',
    notes: '',
});

const maxPlantIncidentQty = computed(() => itemForPlantIncident.value?.fixed_ready_to_load || itemForPlantIncident.value?.fixed_quantity || 1);

const openPlantLoadIncidentModal = (item) => {
    itemForPlantIncident.value = item;
    const initialQty = 1;
    plantIncidentForm.order_id = item.id;
    plantIncidentForm.quantity = initialQty;
    plantIncidentForm.damage_type = 'rotura';
    plantIncidentForm.notes = '';
    plantIncidentForm.clearErrors();
    showPlantIncidentModal.value = true;
};

const closePlantLoadIncidentModal = () => {
    showPlantIncidentModal.value = false;
    itemForPlantIncident.value = null;
};

const submitPlantIncident = () => {
    if (!itemForPlantIncident.value || plantIncidentForm.processing) return;

    plantIncidentForm.post(route('repartidor.ready-to-load.incident'), {
        preserveScroll: true,
        onSuccess: () => closePlantLoadIncidentModal(),
    });
};

// =========================================================================
// MODAL 6: CONFIRMAR ENTREGA Y COBRO (POR ENTREGAR) - PALOMITA VERDE
// =========================================================================
const showDeliverModal = ref(false);
const itemToDeliver = ref(null);

const deliverForm = useForm({
    quantity_delivered: 1,
    payment_action: 'already_paid', // 'already_paid', 'full_cash', 'partial_cash', 'defer'
    cash_tendered: 0,
    units_paid_cash: 0,
    notes: '',
});

const openDeliverModal = (item) => {
    itemToDeliver.value = item;
    const initialQty = item.fixed_in_truck || item.fixed_to_deliver || 1;
    deliverForm.quantity_delivered = initialQty;

    const advanceCovered = item.advance_paid_bottles || 0;
    if (item.is_advance_paid || advanceCovered >= initialQty) {
        deliverForm.payment_action = 'already_paid';
        deliverForm.units_paid_cash = 0;
        deliverForm.cash_tendered = 0;
    } else {
        deliverForm.payment_action = 'full_cash';
        const unpaid = Math.max(0, initialQty - advanceCovered);
        deliverForm.units_paid_cash = unpaid;
        deliverForm.cash_tendered = unpaid * (item.unit_price || 15);
    }

    deliverForm.notes = '';
    deliverForm.clearErrors();
    showDeliverModal.value = true;
};

const closeDeliverModal = () => {
    showDeliverModal.value = false;
    itemToDeliver.value = null;
};

const maxAvailableInTruck = computed(() => itemToDeliver.value?.fixed_in_truck || 1);
const deliverUnitPrice = computed(() => itemToDeliver.value?.unit_price || 15);

const coveredByAdvance = computed(() => {
    if (!itemToDeliver.value) return 0;
    return Math.min(deliverForm.quantity_delivered, itemToDeliver.value.advance_paid_bottles || 0);
});

const unpaidUnitsToDeliver = computed(() => {
    return Math.max(0, deliverForm.quantity_delivered - coveredByAdvance.value);
});

const pendingAmountTotal = computed(() => {
    return unpaidUnitsToDeliver.value * deliverUnitPrice.value;
});

const onQuantityDeliveredChange = (delta) => {
    const newQty = deliverForm.quantity_delivered + delta;
    if (newQty >= 1 && newQty <= maxAvailableInTruck.value) {
        deliverForm.quantity_delivered = newQty;
        if (unpaidUnitsToDeliver.value === 0) {
            deliverForm.payment_action = 'already_paid';
            deliverForm.units_paid_cash = 0;
            deliverForm.cash_tendered = 0;
        } else {
            if (deliverForm.payment_action === 'already_paid') {
                deliverForm.payment_action = 'full_cash';
            }
            if (deliverForm.payment_action === 'full_cash') {
                deliverForm.units_paid_cash = unpaidUnitsToDeliver.value;
                deliverForm.cash_tendered = pendingAmountTotal.value;
            }
        }
    }
};

const selectPaymentAction = (action) => {
    deliverForm.payment_action = action;
    if (action === 'full_cash') {
        deliverForm.units_paid_cash = unpaidUnitsToDeliver.value;
        deliverForm.cash_tendered = pendingAmountTotal.value;
    } else if (action === 'partial_cash') {
        deliverForm.units_paid_cash = Math.max(1, unpaidUnitsToDeliver.value - 1);
        deliverForm.cash_tendered = deliverForm.units_paid_cash * deliverUnitPrice.value;
    } else if (action === 'defer') {
        deliverForm.units_paid_cash = 0;
        deliverForm.cash_tendered = 0;
    }
};

const changeReturnAmount = computed(() => {
    const required = deliverForm.units_paid_cash * deliverUnitPrice.value;
    return Math.max(0, Number(deliverForm.cash_tendered || 0) - required);
});

const submitDeliver = () => {
    if (!itemToDeliver.value || deliverForm.processing) return;

    deliverForm.post(route('repartidor.orders.deliver-fixed', itemToDeliver.value.id), {
        preserveScroll: true,
        onSuccess: () => closeDeliverModal(),
    });
};
</script>

<template>
    <Head title="Reparto - Puritronic" />

    <AuthenticatedLayout wide>
        <div class="max-w-4xl mx-auto px-2.5 sm:px-6 lg:px-8 py-3 sm:py-5 space-y-3 pb-24 md:pb-12">

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
            <!-- 1. ENCABEZADO Y PESTAÑAS (POR RECOGER / POR ENTREGAR) + PEDIDO AL PASO   -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs p-3.5 sm:p-4 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fa-solid fa-truck text-blue-900"></i>
                        <span>Reparto</span>
                    </h1>

                    <!-- Botón + Pedido al paso -->
                    <Link
                        :href="route('repartidor.direct-service.create')"
                        class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-black bg-blue-900 text-white hover:bg-blue-800 active:scale-95 transition shadow-2xs min-h-[44px]"
                    >
                        <i class="fa-solid fa-plus text-sky-400"></i>
                        <span>Pedido al paso</span>
                    </Link>
                </div>

                <!-- 3 Pestañas Principales en Segmented Control -->
                <div class="flex items-center gap-2 border-t border-slate-100 pt-3 overflow-x-auto">
                    <!-- Tab 1: Por recoger -->
                    <button
                        type="button"
                        @click="changeTab('por_recoger')"
                        class="px-3.5 sm:px-4 py-2.5 rounded-xl text-xs font-black transition min-h-[44px] flex items-center gap-2 border cursor-pointer select-none shrink-0"
                        :class="currentTab === 'por_recoger'
                            ? 'bg-blue-900 text-white border-blue-900 shadow-2xs'
                            : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                    >
                        <i class="fa-solid fa-hand-holding-hand text-xs"></i>
                        <span>Por recoger</span>
                        <span
                            class="px-2 py-0.5 rounded-full text-[10px] font-black"
                            :class="currentTab === 'por_recoger' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'"
                        >
                            {{ tabCounts.por_recoger || 0 }}
                        </span>
                    </button>

                    <!-- Tab 2: Por recoger en Planta -->
                    <button
                        type="button"
                        @click="changeTab('por_recoger_planta')"
                        class="px-3.5 sm:px-4 py-2.5 rounded-xl text-xs font-black transition min-h-[44px] flex items-center gap-2 border cursor-pointer select-none shrink-0"
                        :class="currentTab === 'por_recoger_planta'
                            ? 'bg-blue-900 text-white border-blue-900 shadow-2xs'
                            : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                    >
                        <i class="fa-solid fa-industry text-xs"></i>
                        <span>Por recoger en Planta</span>
                        <span
                            class="px-2 py-0.5 rounded-full text-[10px] font-black"
                            :class="currentTab === 'por_recoger_planta' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'"
                        >
                            {{ tabCounts.por_recoger_planta || 0 }}
                        </span>
                    </button>

                    <!-- Tab 3: Por entregar -->
                    <button
                        type="button"
                        @click="changeTab('por_entregar')"
                        class="px-3.5 sm:px-4 py-2.5 rounded-xl text-xs font-black transition min-h-[44px] flex items-center gap-2 border cursor-pointer select-none shrink-0"
                        :class="currentTab === 'por_entregar'
                            ? 'bg-blue-900 text-white border-blue-900 shadow-2xs'
                            : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                    >
                        <i class="fa-solid fa-dolly text-xs"></i>
                        <span>Por entregar</span>
                        <span
                            class="px-2 py-0.5 rounded-full text-[10px] font-black"
                            :class="currentTab === 'por_entregar' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'"
                        >
                            {{ tabCounts.por_entregar || 0 }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. SELECTOR COMPACTO DE ZONAS (Todas, Norte, Sur, Este, Oeste, Sin zona)   -->
            <!-- ========================================================================= -->
            <div class="flex items-center gap-1.5 overflow-x-auto py-1">
                <span class="text-xs font-bold text-slate-400 px-1 uppercase tracking-wider flex items-center gap-1 shrink-0">
                    <i class="fa-solid fa-location-dot"></i> Zona:
                </span>

                <button
                    v-for="z in zoneTabs"
                    :key="z.key"
                    type="button"
                    @click="changeZone(z.key)"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition min-h-[44px] flex items-center gap-1.5 shrink-0 select-none cursor-pointer border"
                    :class="activeZone === z.key
                        ? 'bg-blue-900 text-white border-blue-900 shadow-2xs'
                        : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
                >
                    <span>{{ z.label }}</span>
                    <span
                        class="px-1.5 py-0.5 rounded-md text-[10px] font-mono font-bold"
                        :class="activeZone === z.key ? 'bg-blue-800 text-white' : 'bg-slate-100 text-slate-600'"
                    >
                        {{ zoneCounts[z.key] || 0 }}
                    </span>
                </button>
            </div>

            <!-- Barra de acción rápida: Aceptar todos (Por recoger en Planta) -->
            <div
                v-if="currentTab === 'por_recoger_planta' && items.length > 0"
                class="p-3 sm:p-3.5 bg-blue-50/90 border border-blue-200/90 rounded-2xl flex items-center justify-between gap-3 shadow-2xs"
            >
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-blue-900 text-white flex items-center justify-center text-sm shrink-0">
                        <i class="fa-solid fa-industry"></i>
                    </div>
                    <div>
                        <span class="text-xs sm:text-sm font-black text-blue-950 block">
                            {{ tabCounts.por_recoger_planta }} {{ tabCounts.por_recoger_planta === 1 ? 'fijo listo' : 'fijos listos' }} en Planta
                        </span>
                        <span class="text-[11px] text-blue-700 block">Listos para subir físicamente al camión</span>
                    </div>
                </div>

                <button
                    type="button"
                    @click="showAcceptAllModal = true"
                    class="px-4 py-2 rounded-xl text-xs sm:text-sm font-black bg-blue-900 hover:bg-blue-800 active:scale-95 text-white transition shadow-sm flex items-center gap-1.5 cursor-pointer shrink-0 min-h-[44px]"
                >
                    <i class="fa-solid fa-check-double text-sky-400"></i>
                    <span>Aceptar todos</span>
                </button>
            </div>

            <!-- ========================================================================= -->
            <!-- 3. TABLA REAL COMPACTA: CLIENTE, FIJOS, ACCIONES                         -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                <div v-if="items.length === 0" class="p-12 text-center space-y-2">
                    <div class="h-12 w-12 rounded-2xl bg-slate-100 text-slate-400 mx-auto flex items-center justify-center text-xl">
                        <i :class="currentTab === 'por_recoger' ? 'fa-solid fa-box-open' : currentTab === 'por_recoger_planta' ? 'fa-solid fa-industry' : 'fa-solid fa-truck-ramp-box'"></i>
                    </div>
                    <p class="text-sm font-bold text-slate-800">
                        {{ currentTab === 'por_recoger' ? 'No hay fijos pendientes por recoger' : currentTab === 'por_recoger_planta' ? 'No hay fijos listos para cargar en Planta' : 'No hay fijos pendientes por entregar' }}
                    </p>
                    <p class="text-xs text-slate-400">
                        {{ currentTab === 'por_recoger'
                            ? 'Los nuevos pedidos registrados desde la plataforma aparecerán aquí directamente.'
                            : currentTab === 'por_recoger_planta'
                            ? 'Cuando el Operador confirme el llenado en Planta, aparecerán aquí listos para subir al camión.'
                            : 'Confirma la carga física desde «Por recoger en Planta» para que aparezcan listos para entregar.' }}
                    </p>
                </div>

                <table v-else class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-[11px] font-black uppercase tracking-wider text-slate-500">
                            <th scope="col" class="py-3 px-3 sm:px-5">Cliente</th>
                            <th scope="col" class="py-3 px-2 sm:px-4 text-right">Fijos</th>
                            <th scope="col" class="py-3 px-3 sm:px-5 text-right w-[156px] sm:w-[168px]">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="item in items"
                            :key="item.id"
                            class="hover:bg-slate-50/60 transition"
                        >
                            <!-- 1. Cliente: Apodo en negrita sin comillas, nombre debajo si existe -->
                            <td class="py-3.5 px-3 sm:px-5 align-middle">
                                <button
                                    type="button"
                                    @click="openDetailModal(item)"
                                    class="text-left group cursor-pointer focus:outline-none"
                                    title="Ver detalle del cliente y pedido"
                                >
                                    <span class="font-bold text-slate-900 group-hover:text-blue-900 text-sm sm:text-base leading-tight block">
                                        {{ item.customer_nickname || item.customer_name || 'Cliente' }}
                                    </span>
                                    <span
                                        v-if="item.customer_nickname && item.customer_name"
                                        class="text-xs text-slate-500 group-hover:text-blue-700 font-normal mt-0.5 block"
                                    >
                                        {{ item.customer_name }}
                                    </span>
                                </button>
                            </td>

                            <!-- 2. Fijos: únicamente el número -->
                            <td class="py-3.5 px-2 sm:px-4 align-middle text-right">
                                <span class="font-bold sm:font-black text-slate-900 text-base sm:text-lg font-mono">
                                    {{ currentTab === 'por_recoger' ? (item.fixed_to_collect || item.fixed_quantity) : currentTab === 'por_recoger_planta' ? (item.fixed_ready_to_load || item.fixed_quantity) : (item.fixed_in_truck || item.fixed_to_deliver || item.total_bottles) }}
                                </span>
                            </td>

                            <!-- 3. Acciones -->
                            <td class="py-3.5 px-3 sm:px-5 align-middle text-right">
                                <div class="flex items-center justify-end gap-1.5 sm:gap-2">
                                    <!-- TAB A: POR RECOGER (3 botones: Papelera roja, Alerta ámbar, Palomita verde) -->
                                    <template v-if="currentTab === 'por_recoger'">
                                        <button
                                            @click="openCancelModal(item)"
                                            type="button"
                                            class="h-11 w-11 min-h-[44px] min-w-[44px] rounded-xl flex items-center justify-center text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200/80 active:scale-95 transition cursor-pointer shadow-2xs"
                                            title="Cancelar pedido pendiente"
                                            aria-label="Cancelar pedido pendiente"
                                        >
                                            <i class="fa-solid fa-trash-can text-sm"></i>
                                        </button>

                                        <button
                                            @click="openIncidentModal(item, 'por_recoger')"
                                            type="button"
                                            class="h-11 w-11 min-h-[44px] min-w-[44px] rounded-xl flex items-center justify-center text-amber-700 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-300 active:scale-95 transition cursor-pointer shadow-2xs"
                                            title="Registrar incidencia de recogida"
                                            aria-label="Registrar incidencia de recogida"
                                        >
                                            <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                                        </button>

                                        <button
                                            @click="openCollectModal(item)"
                                            type="button"
                                            class="h-11 w-11 min-h-[44px] min-w-[44px] rounded-xl flex items-center justify-center text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 active:scale-95 transition cursor-pointer shadow-2xs"
                                            title="Confirmar recogida de fijos"
                                            aria-label="Confirmar recogida de fijos"
                                        >
                                            <i class="fa-solid fa-check text-sm font-black"></i>
                                        </button>
                                    </template>

                                    <!-- TAB B: POR RECOGER EN PLANTA (2 botones: Alerta ámbar, Palomita verde) -->
                                    <template v-else-if="currentTab === 'por_recoger_planta'">
                                        <button
                                            @click="openPlantLoadIncidentModal(item)"
                                            type="button"
                                            class="h-11 w-11 min-h-[44px] min-w-[44px] rounded-xl flex items-center justify-center text-amber-700 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-300 active:scale-95 transition cursor-pointer shadow-2xs"
                                            title="Registrar incidencia en Planta"
                                            aria-label="Registrar incidencia en Planta"
                                        >
                                            <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                                        </button>

                                        <button
                                            @click="openPlantLoadModal(item)"
                                            type="button"
                                            class="h-11 w-11 min-h-[44px] min-w-[44px] rounded-xl flex items-center justify-center text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 active:scale-95 transition cursor-pointer shadow-2xs"
                                            title="Confirmar carga al camión"
                                            aria-label="Confirmar carga al camión"
                                        >
                                            <i class="fa-solid fa-check text-sm font-black"></i>
                                        </button>
                                    </template>

                                    <!-- TAB C: POR ENTREGAR (3 botones: Papelera roja, Alerta ámbar, Palomita verde) -->
                                    <template v-else>
                                        <button
                                            @click="openCustodyTrashModal(item)"
                                            type="button"
                                            class="h-11 w-11 min-h-[44px] min-w-[44px] rounded-xl flex items-center justify-center text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200/80 active:scale-95 transition cursor-pointer shadow-2xs"
                                            title="Retirar registro bajo custodia"
                                            aria-label="Retirar registro bajo custodia"
                                        >
                                            <i class="fa-solid fa-trash-can text-sm"></i>
                                        </button>

                                        <button
                                            @click="openIncidentModal(item, 'por_entregar')"
                                            type="button"
                                            class="h-11 w-11 min-h-[44px] min-w-[44px] rounded-xl flex items-center justify-center text-amber-700 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-300 active:scale-95 transition cursor-pointer shadow-2xs"
                                            title="Registrar incidencia de entrega"
                                            aria-label="Registrar incidencia de entrega"
                                        >
                                            <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                                        </button>

                                        <button
                                            @click="openDeliverModal(item)"
                                            type="button"
                                            class="h-11 w-11 min-h-[44px] min-w-[44px] rounded-xl flex items-center justify-center text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 active:scale-95 transition cursor-pointer shadow-2xs"
                                            title="Confirmar entrega y cobro"
                                            aria-label="Confirmar entrega y cobro"
                                        >
                                            <i class="fa-solid fa-check text-sm font-black"></i>
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: DETALLE DEL CLIENTE Y PEDIDO                                       -->
        <!-- ========================================================================= -->
        <div
            v-if="showDetailModal && selectedDetailItem"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <span class="text-[10px] font-bold text-blue-900 uppercase tracking-wider block">Detalle de atención</span>
                        <h3 class="text-base font-black text-slate-900">
                            {{ selectedDetailItem.customer_nickname || selectedDetailItem.customer_name || 'Cliente' }}
                        </h3>
                        <p v-if="selectedDetailItem.customer_nickname && selectedDetailItem.customer_name" class="text-xs text-slate-500">
                            {{ selectedDetailItem.customer_name }}
                        </p>
                    </div>
                    <button
                        @click="closeDetailModal"
                        type="button"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm cursor-pointer"
                        aria-label="Cerrar modal"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100 space-y-1">
                        <span class="text-slate-400 font-bold block uppercase tracking-wider text-[10px]">Folio del pedido</span>
                        <span class="font-mono font-bold text-slate-900 text-sm">{{ selectedDetailItem.folio }}</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100 space-y-1">
                        <span class="text-slate-400 font-bold block uppercase tracking-wider text-[10px]">Domicilio de entrega</span>
                        <span class="text-slate-800 leading-relaxed block">{{ selectedDetailItem.address_summary || 'Sin domicilio registrado' }}</span>
                        <span v-if="selectedDetailItem.address_reference" class="text-slate-500 italic block mt-1">Ref: {{ selectedDetailItem.address_reference }}</span>
                    </div>

                    <div v-if="selectedDetailItem.customer_phone" class="p-3 bg-slate-50 rounded-2xl border border-slate-100 flex items-center justify-between">
                        <div>
                            <span class="text-slate-400 font-bold block uppercase tracking-wider text-[10px]">Teléfono</span>
                            <span class="font-mono text-slate-800 font-bold">{{ selectedDetailItem.customer_phone }}</span>
                        </div>
                        <a
                            :href="`tel:${selectedDetailItem.customer_phone}`"
                            class="h-10 px-3.5 rounded-xl bg-blue-900 text-white font-bold flex items-center gap-1.5 hover:bg-blue-800 text-xs min-h-[44px]"
                        >
                            <i class="fa-solid fa-phone text-xs"></i>
                            <span>Llamar</span>
                        </a>
                    </div>
                </div>

                <div class="flex items-center justify-end pt-2 border-t border-slate-100">
                    <button
                        @click="closeDetailModal"
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold min-h-[44px] cursor-pointer"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: CONFIRMAR RECOGIDA (POR RECOGER) - PALOMITA VERDE                  -->
        <!-- ========================================================================= -->
        <div
            v-if="showCollectModal && itemToCollect"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <span class="text-[10px] font-bold text-blue-900 uppercase tracking-wider block">Confirmación de recogida física</span>
                        <h3 class="text-base font-black text-slate-900">
                            {{ itemToCollect.customer_nickname || itemToCollect.customer_name || 'Cliente' }}
                        </h3>
                        <p class="text-xs text-slate-500">Pendientes por recoger: {{ itemToCollect.fixed_to_collect || itemToCollect.fixed_quantity }} botellones</p>
                    </div>
                    <button
                        @click="closeCollectModal"
                        type="button"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm cursor-pointer"
                        aria-label="Cerrar modal"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-center space-y-3">
                    <span class="text-xs font-bold text-slate-600 block">¿Cuántos botellones vacíos recibes del cliente?</span>
                    <div class="flex items-center justify-center gap-4">
                        <button
                            @click="decrementCollectQty"
                            :disabled="collectForm.quantity <= 1"
                            type="button"
                            class="h-12 w-12 rounded-2xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 disabled:opacity-40 flex items-center justify-center text-lg font-bold shadow-2xs cursor-pointer"
                            aria-label="Disminuir cantidad"
                        >
                            <i class="fa-solid fa-minus"></i>
                        </button>
                        <span class="text-3xl font-black text-slate-900 font-mono w-16 text-center">
                            {{ collectForm.quantity }}
                        </span>
                        <button
                            @click="incrementCollectQty"
                            :disabled="collectForm.quantity >= (itemToCollect.fixed_to_collect || itemToCollect.fixed_quantity)"
                            type="button"
                            class="h-12 w-12 rounded-2xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 disabled:opacity-40 flex items-center justify-center text-lg font-bold shadow-2xs cursor-pointer"
                            aria-label="Aumentar cantidad"
                        >
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>

                    <p v-if="collectForm.quantity < (itemToCollect.fixed_to_collect || itemToCollect.fixed_quantity)" class="text-[11px] text-amber-700 font-bold bg-amber-50 p-2 rounded-xl border border-amber-200">
                        Se registrará 1 botellón pendiente de recoger en este pedido.
                    </p>
                </div>

                <!-- Toggle Pago Anticipado -->
                <div class="p-3 bg-blue-50/70 rounded-2xl border border-blue-100 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-black text-blue-950 block">Pago anticipado</span>
                        <span class="text-[11px] text-blue-800">El cliente paga el llenado en este momento</span>
                    </div>
                    <button
                        @click="toggleAdvanceMode"
                        type="button"
                        class="h-8 px-3 rounded-xl text-xs font-bold border transition min-h-[44px] flex items-center cursor-pointer"
                        :class="isAdvancePaymentMode ? 'bg-blue-900 text-white border-blue-900' : 'bg-white text-slate-700 border-slate-300'"
                    >
                        {{ isAdvancePaymentMode ? 'Sí, cobra ahora' : 'No, al entregar' }}
                    </button>
                </div>

                <!-- Desglose de cobro en pago anticipado -->
                <div v-if="isAdvancePaymentMode" class="p-3 bg-emerald-50 rounded-2xl border border-emerald-200 text-xs space-y-1">
                    <div class="flex items-center justify-between font-bold text-emerald-950">
                        <span>Total a cobrar ({{ collectForm.quantity }} × ${{ unitPrice }}):</span>
                        <span class="text-base font-black font-mono">${{ collectForm.quantity * unitPrice }} MXN</span>
                    </div>
                    <p class="text-[11px] text-emerald-800">
                        * Quedará registrado como pagado por adelantado y al entregar el saldo será $0.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="closeCollectModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold min-h-[44px] cursor-pointer"
                    >
                        Volver
                    </button>
                    <button
                        @click="submitCollection"
                        :disabled="collectForm.processing"
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-blue-900 hover:bg-blue-800 text-white text-xs font-black min-h-[44px] cursor-pointer shadow-2xs"
                    >
                        {{ collectForm.processing ? 'Guardando...' : `Confirmar recogida (${collectForm.quantity})` }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: CANCELAR PEDIDO (POR RECOGER) - PAPELERA ROJA                      -->
        <!-- ========================================================================= -->
        <div
            v-if="showCancelModal && itemToCancel"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-rose-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-3 border-b border-rose-100 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg shrink-0">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-rose-700 uppercase tracking-wider block">Cancelar pedido</span>
                            <h3 class="text-base font-black text-slate-900">
                                {{ itemToCancel.customer_nickname || itemToCancel.customer_name || 'Cliente' }}
                            </h3>
                            <p class="text-xs text-slate-500">Folio: {{ itemToCancel.folio }}</p>
                        </div>
                    </div>
                    <button
                        @click="closeCancelModal"
                        type="button"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm cursor-pointer"
                        aria-label="Cerrar modal"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <p class="font-bold text-slate-900">
                        ¿Seguro que quieres cancelar este pedido?
                    </p>
                    <p class="text-slate-600 leading-relaxed">
                        El pedido se retirará de la lista de pendientes por recoger. Indica el motivo obligatorio de la cancelación:
                    </p>

                    <div>
                        <textarea
                            v-model="cancelForm.reason"
                            rows="3"
                            placeholder="Ej. Cliente canceló por teléfono / No se encuentra en el domicilio..."
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-rose-500 focus:ring-rose-500"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="closeCancelModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold min-h-[44px] cursor-pointer"
                    >
                        Volver
                    </button>
                    <button
                        @click="submitCancelOrder"
                        :disabled="cancelForm.processing"
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-black min-h-[44px] cursor-pointer shadow-2xs"
                    >
                        {{ cancelForm.processing ? 'Cancelando...' : 'Confirmar cancelación' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: REGISTRAR INCIDENCIA (ALERTA ÁMBAR)                                -->
        <!-- ========================================================================= -->
        <div
            v-if="showIncidentModal && itemForIncident"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-amber-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-3 border-b border-amber-100 pb-3">
                    <div>
                        <span class="text-[10px] font-bold text-amber-700 uppercase tracking-wider block">
                            {{ incidentTabType === 'por_recoger' ? 'Incidencia de recogida' : 'Incidencia de entrega' }}
                        </span>
                        <h3 class="text-base font-black text-slate-900">
                            {{ itemForIncident.customer_nickname || itemForIncident.customer_name || 'Cliente' }}
                        </h3>
                        <p class="text-xs text-slate-500">Folio: {{ itemForIncident.folio }}</p>
                    </div>
                    <button
                        @click="closeIncidentModal"
                        type="button"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm cursor-pointer"
                        aria-label="Cerrar modal"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Motivo de la incidencia:</label>
                        <select v-model="incidentForm.reason" class="w-full text-xs rounded-xl border-slate-300">
                            <option value="Cliente ausente">Cliente ausente en domicilio</option>
                            <option value="Cliente no entregó envases completos">Cliente no entregó envases completos</option>
                            <option value="Envase con daño visible">Envase con daño visible / roto</option>
                            <option value="Dirección inaccesible">Dirección inaccesible</option>
                            <option value="Otro motivo">Otro motivo</option>
                        </select>
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Observaciones adicionales:</label>
                        <textarea
                            v-model="incidentForm.notes"
                            rows="2"
                            placeholder="Detalles sobre lo sucedido..."
                            class="w-full text-xs rounded-xl border-slate-300"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="closeIncidentModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold min-h-[44px] cursor-pointer"
                    >
                        Volver
                    </button>
                    <button
                        @click="submitIncident"
                        :disabled="incidentForm.processing"
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-black min-h-[44px] cursor-pointer shadow-2xs"
                    >
                        {{ incidentForm.processing ? 'Guardando...' : 'Registrar incidencia' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: RETIRAR REGISTRO CON CUSTODIA (PAPELERA ROJA EN POR ENTREGAR)      -->
        <!-- ========================================================================= -->
        <div
            v-if="showCustodyTrashModal && itemForCustodyTrash"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-rose-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-3 border-b border-rose-100 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg shrink-0">
                            <i class="fa-solid fa-trash-can"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-rose-700 uppercase tracking-wider block">Fijos bajo custodia</span>
                            <h3 class="text-base font-black text-slate-900">
                                {{ itemForCustodyTrash.customer_nickname || itemForCustodyTrash.customer_name || 'Cliente' }}
                            </h3>
                            <p class="text-xs text-slate-500">Folio: {{ itemForCustodyTrash.folio }}</p>
                        </div>
                    </div>
                    <button
                        @click="closeCustodyTrashModal"
                        type="button"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm cursor-pointer"
                        aria-label="Cerrar modal"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-3.5 bg-rose-50/70 rounded-2xl border border-rose-100 text-xs text-rose-950 space-y-2">
                    <p class="font-bold text-slate-900">
                        No se puede descartar directamente este registro.
                    </p>
                    <p class="text-slate-600 leading-relaxed">
                        Este pedido cuenta con <strong>{{ itemForCustodyTrash.fixed_in_truck || itemForCustodyTrash.fixed_to_deliver }} botellones fijos llenados</strong> cargados en el camión. Para conservar la trazabilidad de los envases y los cobros, cualquier problema en la entrega debe registrarse como <strong>Incidencia</strong>.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="closeCustodyTrashModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold min-h-[44px] cursor-pointer"
                    >
                        Volver
                    </button>
                    <button
                        @click="deriveCustodyTrashToIncident"
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-black min-h-[44px] flex items-center gap-2 cursor-pointer shadow-2xs"
                    >
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>Ir a Incidencia</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: CONFIRMAR ENTREGA Y COBRO (POR ENTREGAR) - PALOMITA VERDE          -->
        <!-- ========================================================================= -->
        <div
            v-if="showDeliverModal && itemToDeliver"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 animate-in fade-in zoom-in-95 max-h-[92vh] overflow-y-auto">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider block">Confirmación de entrega y cobro</span>
                        <h3 class="text-base font-black text-slate-900">
                            {{ itemToDeliver.customer_nickname || itemToDeliver.customer_name || 'Cliente' }}
                        </h3>
                        <p class="text-xs text-slate-500">En camión: {{ itemToDeliver.fixed_in_truck || itemToDeliver.fixed_to_deliver }} botellones fijos llenos</p>
                    </div>
                    <button
                        @click="closeDeliverModal"
                        type="button"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm cursor-pointer"
                        aria-label="Cerrar modal"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- Stepper de unidades a entregar -->
                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200 text-center space-y-2">
                    <span class="text-xs font-bold text-slate-600 block">Botellones a entregar en esta visita</span>
                    <div class="flex items-center justify-center gap-4">
                        <button
                            @click="onQuantityDeliveredChange(-1)"
                            :disabled="deliverForm.quantity_delivered <= 1"
                            type="button"
                            class="h-10 w-10 rounded-xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 disabled:opacity-40 flex items-center justify-center text-base font-bold shadow-2xs cursor-pointer"
                            aria-label="Disminuir entrega"
                        >
                            <i class="fa-solid fa-minus"></i>
                        </button>
                        <span class="text-2xl font-black text-slate-900 font-mono w-14 text-center">
                            {{ deliverForm.quantity_delivered }}
                        </span>
                        <button
                            @click="onQuantityDeliveredChange(1)"
                            :disabled="deliverForm.quantity_delivered >= maxAvailableInTruck"
                            type="button"
                            class="h-10 w-10 rounded-xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 disabled:opacity-40 flex items-center justify-center text-base font-bold shadow-2xs cursor-pointer"
                            aria-label="Aumentar entrega"
                        >
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>

                <!-- Caso A: Pagado anticipadamente -->
                <div v-if="unpaidUnitsToDeliver === 0" class="p-4 bg-emerald-50 rounded-2xl border border-emerald-200 text-center space-y-2">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-100 text-emerald-900 font-bold text-xs">
                        <i class="fa-solid fa-circle-check text-emerald-600"></i>
                        <span>Pagado anticipadamente · Saldo $0</span>
                    </div>
                    <p class="text-xs text-emerald-800">
                        Las {{ deliverForm.quantity_delivered }} unidades ya fueron liquidadas previamente. No se requiere cobro en efectivo.
                    </p>
                </div>

                <!-- Caso B: Tiene saldo pendiente por cobrar -->
                <div v-else class="space-y-3">
                    <!-- Resumen del importe -->
                    <div class="p-3 bg-blue-50/70 rounded-2xl border border-blue-100 flex items-center justify-between text-xs">
                        <div>
                            <span class="text-slate-600 block">Unidades por cobrar:</span>
                            <span class="font-bold text-slate-900">{{ unpaidUnitsToDeliver }} × ${{ deliverUnitPrice }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-slate-500 text-[10px] uppercase font-bold block">Saldo pendiente</span>
                            <span class="text-lg font-black text-blue-900 font-mono">${{ pendingAmountTotal }} MXN</span>
                        </div>
                    </div>

                    <!-- 3 Opciones de cobro -->
                    <div class="space-y-2 text-xs">
                        <label class="font-bold text-slate-700 block">Forma de pago de la entrega:</label>

                        <!-- Opción 1: Pagó completo -->
                        <button
                            type="button"
                            @click="selectPaymentAction('full_cash')"
                            class="w-full p-3 rounded-2xl border text-left flex items-center justify-between transition min-h-[44px] cursor-pointer"
                            :class="deliverForm.payment_action === 'full_cash' ? 'bg-blue-50/80 border-blue-900 text-blue-950 font-bold shadow-2xs' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'"
                        >
                            <div class="flex items-center gap-2.5">
                                <i class="fa-solid fa-money-bill-wave text-emerald-600 text-sm"></i>
                                <span>Pagado (Efectivo completo: ${{ pendingAmountTotal }})</span>
                            </div>
                            <i v-if="deliverForm.payment_action === 'full_cash'" class="fa-solid fa-circle-check text-blue-900"></i>
                        </button>

                        <!-- Opción 2: Pagó parcialmente -->
                        <button
                            v-if="unpaidUnitsToDeliver > 1"
                            type="button"
                            @click="selectPaymentAction('partial_cash')"
                            class="w-full p-3 rounded-2xl border text-left flex items-center justify-between transition min-h-[44px] cursor-pointer"
                            :class="deliverForm.payment_action === 'partial_cash' ? 'bg-blue-50/80 border-blue-900 text-blue-950 font-bold shadow-2xs' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'"
                        >
                            <div class="flex items-center gap-2.5">
                                <i class="fa-solid fa-coins text-amber-600 text-sm"></i>
                                <span>Pagado parcialmente</span>
                            </div>
                            <i v-if="deliverForm.payment_action === 'partial_cash'" class="fa-solid fa-circle-check text-blue-900"></i>
                        </button>

                        <!-- Si seleccionó pago parcial, stepper de botellones pagados -->
                        <div v-if="deliverForm.payment_action === 'partial_cash'" class="p-3 bg-amber-50/60 rounded-2xl border border-amber-200 space-y-2">
                            <span class="text-[11px] font-bold text-amber-900 block">Botellones completos que paga en efectivo:</span>
                            <div class="flex items-center justify-center gap-3">
                                <button
                                    type="button"
                                    @click="deliverForm.units_paid_cash = Math.max(1, deliverForm.units_paid_cash - 1); deliverForm.cash_tendered = deliverForm.units_paid_cash * deliverUnitPrice"
                                    class="h-8 w-8 rounded-lg bg-white border border-slate-300 font-bold"
                                >-</button>
                                <span class="font-bold font-mono text-sm">{{ deliverForm.units_paid_cash }} botellón(es) = ${{ deliverForm.units_paid_cash * deliverUnitPrice }}</span>
                                <button
                                    type="button"
                                    @click="deliverForm.units_paid_cash = Math.min(unpaidUnitsToDeliver - 1, deliverForm.units_paid_cash + 1); deliverForm.cash_tendered = deliverForm.units_paid_cash * deliverUnitPrice"
                                    class="h-8 w-8 rounded-lg bg-white border border-slate-300 font-bold"
                                >+</button>
                            </div>
                            <p class="text-[10px] text-amber-800 text-center font-semibold">
                                Quedan {{ unpaidUnitsToDeliver - deliverForm.units_paid_cash }} botellones pendientes como fiado.
                            </p>
                        </div>

                        <!-- Opción 3: Dejar fiado (No pagado) -->
                        <button
                            type="button"
                            @click="selectPaymentAction('defer')"
                            class="w-full p-3 rounded-2xl border text-left flex items-center justify-between transition min-h-[44px] cursor-pointer"
                            :class="deliverForm.payment_action === 'defer' ? 'bg-blue-50/80 border-blue-900 text-blue-950 font-bold shadow-2xs' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'"
                        >
                            <div class="flex items-center gap-2.5">
                                <i class="fa-solid fa-clock-rotate-left text-slate-500 text-sm"></i>
                                <span>No pagado (Dejar fiado)</span>
                            </div>
                            <i v-if="deliverForm.payment_action === 'defer'" class="fa-solid fa-circle-check text-blue-900"></i>
                        </button>
                    </div>

                    <!-- Calculadora de cambio si paga en efectivo -->
                    <div v-if="deliverForm.payment_action === 'full_cash' || deliverForm.payment_action === 'partial_cash'" class="p-3 bg-slate-50 rounded-2xl border border-slate-200 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <label class="font-bold text-slate-700">Efectivo recibido:</label>
                            <input
                                v-model.number="deliverForm.cash_tendered"
                                type="number"
                                min="0"
                                step="1"
                                class="w-24 text-right font-black font-mono rounded-xl border-slate-300 py-1"
                            />
                        </div>
                        <div v-if="changeReturnAmount > 0" class="flex items-center justify-between text-emerald-800 font-bold pt-1 border-t border-slate-200">
                            <span>Cambio a devolver:</span>
                            <span class="font-mono text-sm">${{ changeReturnAmount }} MXN</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="closeDeliverModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold min-h-[44px] cursor-pointer"
                    >
                        Volver
                    </button>
                    <button
                        @click="submitDeliver"
                        :disabled="deliverForm.processing"
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-blue-900 hover:bg-blue-800 text-white text-xs font-black min-h-[44px] cursor-pointer shadow-2xs"
                    >
                        {{ deliverForm.processing ? 'Guardando...' : (unpaidUnitsToDeliver === 0 ? 'Finalizar entrega' : 'Confirmar entrega') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: CONFIRMAR CARGA DESDE PLANTA (POR RECOGER EN PLANTA)                -->
        <!-- ========================================================================= -->
        <div
            v-if="showPlantLoadModal && itemToPlantLoad"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider block">Carga física al camión</span>
                        <h3 class="text-base font-black text-slate-900">
                            {{ itemToPlantLoad.customer_nickname || itemToPlantLoad.customer_name || 'Cliente' }}
                        </h3>
                        <p v-if="itemToPlantLoad.customer_name && itemToPlantLoad.customer_nickname" class="text-xs text-slate-500">
                            {{ itemToPlantLoad.customer_name }}
                        </p>
                    </div>
                    <button
                        @click="closePlantLoadModal"
                        type="button"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm cursor-pointer"
                        aria-label="Cerrar modal"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="space-y-3">
                    <div class="p-3 bg-emerald-50/70 rounded-2xl border border-emerald-200 text-xs flex items-center justify-between">
                        <span class="font-bold text-emerald-950">Disponibles listos en Planta:</span>
                        <span class="font-black text-emerald-900 text-sm font-mono">{{ maxPlantReady }} fijos</span>
                    </div>

                    <!-- Stepper de cantidad -->
                    <div class="space-y-1.5 py-1">
                        <label class="block text-center text-xs font-bold text-slate-700">
                            ¿Cuántos fijos recibiste físicamente en el camión?
                        </label>
                        <div class="flex items-center justify-center gap-3">
                            <button
                                type="button"
                                @click="decrementPlantLoadQty"
                                :disabled="plantLoadForm.quantity <= 1"
                                class="w-12 h-12 rounded-2xl bg-slate-100 hover:bg-slate-200 font-black text-xl text-slate-700 active:scale-95 disabled:opacity-30 disabled:pointer-events-none transition flex items-center justify-center cursor-pointer"
                            >
                                <i class="fa-solid fa-minus"></i>
                            </button>
                            <input
                                v-model.number="plantLoadForm.quantity"
                                type="number"
                                min="1"
                                :max="maxPlantReady"
                                class="w-20 text-center text-2xl font-black text-blue-950 bg-slate-50 border-2 border-slate-200 rounded-2xl py-2"
                            />
                            <button
                                type="button"
                                @click="incrementPlantLoadQty"
                                :disabled="plantLoadForm.quantity >= maxPlantReady"
                                class="w-12 h-12 rounded-2xl bg-blue-900 hover:bg-blue-800 font-black text-xl text-white active:scale-95 disabled:opacity-30 disabled:pointer-events-none transition flex items-center justify-center cursor-pointer shadow-xs"
                            >
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Explicación de paso a Por entregar -->
                    <div class="p-3 bg-slate-50 rounded-2xl text-[11px] text-slate-600 leading-relaxed border border-slate-100">
                        <template v-if="plantLoadForm.quantity < maxPlantReady">
                            Cargarás <strong>{{ plantLoadForm.quantity }} fijos</strong>. Pasarán a <strong>Por entregar</strong>. El resto ({{ maxPlantReady - plantLoadForm.quantity }}) permanecerá en Planta.
                        </template>
                        <template v-else>
                            Confirmas recibir <strong>{{ plantLoadForm.quantity }} fijos listos</strong>. Pasarán directamente a <strong>Por entregar</strong>.
                        </template>
                    </div>

                    <div v-if="plantLoadForm.quantity < maxPlantReady">
                        <label class="block font-bold text-slate-700 text-xs mb-1">Motivo de diferencia (opcional):</label>
                        <input
                            v-model="plantLoadForm.difference_reason"
                            type="text"
                            placeholder="Ej. Espacio en camión, el otro sigue en llenado..."
                            class="w-full text-xs py-2 px-3 rounded-xl border border-slate-200"
                        />
                    </div>
                </div>

                <div class="flex gap-2 pt-2 border-t border-slate-100">
                    <button
                        type="button"
                        @click="closePlantLoadModal"
                        class="flex-1 py-3 px-4 rounded-xl font-bold text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 transition cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="submitPlantLoad"
                        :disabled="plantLoadForm.processing"
                        class="flex-1 py-3 px-4 rounded-xl font-black text-xs bg-blue-900 hover:bg-blue-800 text-white transition active:scale-95 shadow-md flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-40"
                    >
                        <i class="fa-solid fa-check"></i>
                        <span>Confirmar {{ plantLoadForm.quantity }} {{ plantLoadForm.quantity === 1 ? 'fijo' : 'fijos' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: REGISTRAR INCIDENCIA EN PLANTA (POR RECOGER EN PLANTA)              -->
        <!-- ========================================================================= -->
        <div
            v-if="showPlantIncidentModal && itemForPlantIncident"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <span class="text-[10px] font-bold text-amber-700 uppercase tracking-wider block">Reportar Incidencia</span>
                        <h3 class="text-base font-black text-slate-900">
                            {{ itemForPlantIncident.customer_nickname || itemForPlantIncident.customer_name || 'Cliente' }}
                        </h3>
                        <p class="text-xs text-slate-500 font-mono">
                            Folio {{ itemForPlantIncident.folio }}
                        </p>
                    </div>
                    <button
                        @click="closePlantLoadIncidentModal"
                        type="button"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm cursor-pointer"
                        aria-label="Cerrar modal"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Cantidad con incidencia:</label>
                        <div class="flex items-center gap-2">
                            <input
                                v-model.number="plantIncidentForm.quantity"
                                type="number"
                                min="1"
                                :max="maxPlantIncidentQty"
                                class="w-24 text-center font-black py-2 rounded-xl border border-slate-200"
                            />
                            <span class="text-slate-400">de {{ maxPlantIncidentQty }} listos</span>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tipo de incidencia:</label>
                        <select
                            v-model="plantIncidentForm.damage_type"
                            class="w-full py-2 px-3 text-xs font-semibold rounded-xl border border-slate-200 bg-white"
                        >
                            <option value="rotura">Rotura / Roto</option>
                            <option value="fisura">Fisura / Gotea</option>
                            <option value="contaminacion">Contaminación / Olor</option>
                            <option value="falta_no_localizado">Falta / No localizado en Planta</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Notas / Observaciones (opcional):</label>
                        <textarea
                            v-model="plantIncidentForm.notes"
                            rows="2"
                            placeholder="Detalla lo sucedido con el envase..."
                            class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200"
                        ></textarea>
                    </div>
                </div>

                <div class="flex gap-2 pt-2 border-t border-slate-100">
                    <button
                        type="button"
                        @click="closePlantLoadIncidentModal"
                        class="flex-1 py-3 px-4 rounded-xl font-bold text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 transition cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="submitPlantIncident"
                        :disabled="plantIncidentForm.processing"
                        class="flex-1 py-3 px-4 rounded-xl font-black text-xs bg-amber-600 hover:bg-amber-700 text-white transition active:scale-95 shadow-md flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-40"
                    >
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>Registrar incidencia</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: ACEPTAR TODOS EN PLANTA                                            -->
        <!-- ========================================================================= -->
        <div
            v-if="showAcceptAllModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <span class="text-[10px] font-bold text-blue-900 uppercase tracking-wider block">Carga física masiva</span>
                        <h3 class="text-base font-black text-slate-900">Aceptar todos los fijos listos</h3>
                    </div>
                    <button
                        @click="showAcceptAllModal = false"
                        type="button"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm cursor-pointer"
                        aria-label="Cerrar modal"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-3.5 bg-blue-50 rounded-2xl border border-blue-200 text-xs space-y-1.5 text-blue-950">
                    <p class="font-bold">
                        ¿Confirmas que recibiste físicamente todos los botellones fijos listos en Planta?
                    </p>
                    <p class="text-blue-800 text-[11px] leading-relaxed">
                        Se cargarán <strong>{{ tabCounts.por_recoger_planta }} botellón(es)</strong> al camión y pasarán inmediatamente a la pestaña <strong>Por entregar</strong>.
                    </p>
                </div>

                <div class="flex gap-2 pt-2 border-t border-slate-100">
                    <button
                        type="button"
                        @click="showAcceptAllModal = false"
                        class="flex-1 py-3 px-4 rounded-xl font-bold text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 transition cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="confirmAcceptAllPlant"
                        class="flex-1 py-3 px-4 rounded-xl font-black text-xs bg-blue-900 hover:bg-blue-800 text-white transition active:scale-95 shadow-md flex items-center justify-center gap-1.5 cursor-pointer"
                    >
                        <i class="fa-solid fa-check-double"></i>
                        <span>Confirmar todos</span>
                    </button>
                </div>
            </div>
        </div>

        <RepartidorBottomNav />
    </AuthenticatedLayout>
</template>
