<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RepartidorBottomNav from '@/Components/RepartidorBottomNav.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { alertWarning, confirmAction } from '@/utils/swal';
import {
    AlertTriangle,
    ArrowUpFromLine,
    Check,
    CheckCheck,
    CheckCircle2,
    Eye,
    HandHelping,
    MapPin,
    PackageCheck,
    Phone,
    Plus,
    SlidersHorizontal,
    Trash2,
    Truck,
    X,
} from 'lucide-vue-next';

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
        default: () => ({ por_recoger: 0, por_entregar: 0, ready_in_plant_orders: 0, ready_in_plant_bottles: 0 }),
    },
    zoneCounts: {
        type: Object,
        default: () => ({ all: 0, none: 0 }),
    },
    zones: {
        type: Array,
        default: () => [],
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
const formError = computed(() =>
    page.props.errors?.route ||
    page.props.errors?.stop ||
    page.props.errors?.quantity ||
    page.props.errors?.collect ||
    page.props.errors?.deliver ||
    page.props.errors?.cancel ||
    page.props.errors?.load ||
    page.props.errors?.incident ||
    null
);

// 2 pestañas claras: 'por_recoger' | 'por_entregar' (cualquier URL legacy por_recoger_planta aterriza aquí)
const currentTab = computed(() => {
    if (props.activeTab === 'por_entregar' || props.activeTab === 'por_recoger_planta') {
        return 'por_entregar';
    }
    return 'por_recoger';
});

const changeTab = (tabKey) => {
    router.get(
        route('repartidor.reparto'),
        { tab: tabKey, zone: props.activeZone },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

const zoneTabs = computed(() => {
    const source = (props.zones && props.zones.length > 0)
        ? props.zones
        : (page.props.deliveryZones || [
            { key: 'north', name: 'Norte' },
            { key: 'south', name: 'Sur' },
            { key: 'east', name: 'Este' },
            { key: 'west', name: 'Oeste' },
        ]);
    return [
        { key: 'all', label: 'Todas' },
        ...source.map((z) => ({ key: z.key, label: z.name })),
        { key: 'none', label: 'Sin zona' },
    ];
});

const changeZone = (zoneKey) => {
    router.get(
        route('repartidor.reparto'),
        { tab: currentTab.value, zone: zoneKey },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

/* =========================================================================
   DETALLE DE PEDIDO Y DOMICILIO
   ========================================================================= */
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

/* =========================================================================
   RECOGIDA DE FIJOS (POR RECOGER)
   ========================================================================= */
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
    const initialQty = item.fixed_to_collect || item.fixed_quantity || 1;
    isAdvancePaymentMode.value = false;
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

/* =========================================================================
   CANCELAR PEDIDO (SOLO EN POR RECOGER)
   ========================================================================= */
const showCancelModal = ref(false);
const itemToCancel = ref(null);
const cancelForm = useForm({ reason: '' });

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
        alertWarning('Indica un motivo válido de cancelación (mínimo 5 caracteres).');
        return;
    }

    cancelForm.post(route('repartidor.orders.cancel', itemToCancel.value.id), {
        preserveScroll: true,
        onSuccess: () => closeCancelModal(),
    });
};

/* =========================================================================
   INCIDENCIA EN RECOGIDA O ENTREGA (SIEMPRE PERSISTE EN BD)
   ========================================================================= */
const showIncidentModal = ref(false);
const itemForIncident = ref(null);
const incidentTabType = ref('por_recoger');
const incidentForm = useForm({
    reason: 'Cliente no entregó envases completos',
    notes: '',
    stage: 'por_recoger',
});

const openIncidentModal = (item, tab) => {
    itemForIncident.value = item;
    incidentTabType.value = tab;
    incidentForm.stage = tab;
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

    incidentForm.post(route('repartidor.orders.incident', itemForIncident.value.id), {
        preserveScroll: true,
        onSuccess: () => closeIncidentModal(),
    });
};

/* =========================================================================
   SUBIR FIJOS LISTOS DE PLANTA AL CAMIÓN (DENTRO DE POR ENTREGAR)
   ========================================================================= */
const uploadingSingle = ref({});
const batchUploading = ref(false);

const handleDirectLoadSingle = (item) => {
    const qty = item.fixed_ready_to_load || item.fixed_quantity || 1;
    uploadingSingle.value[item.id] = true;
    router.post(
        route('repartidor.ready-to-load.accept'),
        { order_id: item.id, quantity: qty },
        {
            preserveScroll: true,
            onFinish: () => {
                uploadingSingle.value[item.id] = false;
            },
        }
    );
};

const handleDirectLoadAll = async () => {
    const bottles = props.tabCounts.ready_in_plant_bottles || props.tabCounts.por_recoger_planta || 0;
    if (bottles <= 0 || batchUploading.value) return;

    const res = await confirmAction({
        title: '¿Subir todos los fijos listos al camión?',
        text: `Se cargarán ${bottles} botellón(es) listos en Planta para salir a entregar.`,
        confirmText: 'Sí, subir al camión',
        tone: 'primary',
    });

    if (!res.isConfirmed) return;

    batchUploading.value = true;
    router.post(
        route('repartidor.ready-to-load.accept-all'),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                batchUploading.value = false;
            },
        }
    );
};

// Modal parcial opcional si sube menos fijos
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

// Incidencia en carga desde Planta
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
    plantIncidentForm.order_id = item.id;
    plantIncidentForm.quantity = 1;
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

/* =========================================================================
   CONFIRMAR ENTREGA Y COBRO (POR ENTREGAR)
   ========================================================================= */
const showDeliverModal = ref(false);
const itemToDeliver = ref(null);

const deliverForm = useForm({
    quantity_delivered: 1,
    payment_action: 'already_paid',
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

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(val || 0));
};

const handleDeliverAction = (item) => {
    if (item.operation_type === 'intercambio' && (item.fixed_in_truck || 0) === 0) {
        router.post(route('repartidor.orders.serve', item.id), {
            tab: currentTab.value,
            zone: props.activeZone,
        });
        return;
    }
    openDeliverModal(item);
};
</script>

<template>
    <Head title="Reparto" />

    <AuthenticatedLayout wide>
        <div class="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6 space-y-4 pb-24 md:pb-12">

            <div
                v-if="formError"
                class="p-3.5 rounded-2xl bg-[#FCECEF] border border-[#F6C8D2] text-[#B91C3C] text-xs sm:text-sm flex items-start gap-2.5"
            >
                <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
                <div class="font-semibold">{{ formError }}</div>
            </div>

            <!-- Encabezado y 2 pestañas claras -->
            <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-[0_1px_2px_rgba(30,21,69,0.04)] p-4 sm:p-5 space-y-3.5">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-[#E9F3F8] text-[#0071BA] flex items-center justify-center shrink-0">
                            <Truck class="w-5 h-5" />
                        </div>
                        <div>
                            <h1 class="text-xl sm:text-2xl font-bold text-[#292638] tracking-tight">
                                Reparto
                            </h1>
                            <p class="text-xs text-[#747181]">
                                Recoge envases vacíos y entrega pedidos listos en ruta.
                            </p>
                        </div>
                    </div>

                    <Link
                        :href="route('repartidor.direct-service.create')"
                        class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-bold bg-[#1E1545] text-white hover:bg-[#2B1F60] active:scale-95 transition min-h-[42px] shrink-0"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Pedido al paso</span>
                    </Link>
                </div>

                <!-- 2 pestañas reales de calle -->
                <div class="flex items-center gap-2 border-t border-[#E9E7F0] pt-3">
                    <button
                        type="button"
                        @click="changeTab('por_recoger')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition min-h-[42px] inline-flex items-center gap-2 border cursor-pointer"
                        :class="currentTab === 'por_recoger'
                            ? 'bg-[#1E1545] text-white border-[#1E1545]'
                            : 'bg-[#F4F3F8] text-[#292638] border-[#E9E7F0] hover:bg-[#EEEAF7]'"
                    >
                        <HandHelping class="w-4 h-4" />
                        <span>Por recoger</span>
                        <span
                            class="px-2 py-0.5 rounded-full text-[11px] font-bold tabular-nums"
                            :class="currentTab === 'por_recoger' ? 'bg-white/20 text-white' : 'bg-white text-[#1E1545]'"
                        >
                            {{ tabCounts.por_recoger || 0 }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="changeTab('por_entregar')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition min-h-[42px] inline-flex items-center gap-2 border cursor-pointer"
                        :class="currentTab === 'por_entregar'
                            ? 'bg-[#0071BA] text-white border-[#0071BA]'
                            : 'bg-[#F4F3F8] text-[#292638] border-[#E9E7F0] hover:bg-[#E9F3F8]'"
                    >
                        <PackageCheck class="w-4 h-4" />
                        <span>Por entregar</span>
                        <span
                            class="px-2 py-0.5 rounded-full text-[11px] font-bold tabular-nums"
                            :class="currentTab === 'por_entregar' ? 'bg-white/20 text-white' : 'bg-white text-[#0071BA]'"
                        >
                            {{ tabCounts.por_entregar || 0 }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Filtro de zonas -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1">
                <span class="text-xs font-semibold text-[#747181] px-1 inline-flex items-center gap-1 shrink-0">
                    <MapPin class="w-3.5 h-3.5" /> Zona:
                </span>

                <button
                    v-for="z in zoneTabs"
                    :key="z.key"
                    type="button"
                    @click="changeZone(z.key)"
                    class="px-3 py-1.5 rounded-xl text-xs font-semibold transition min-h-[38px] inline-flex items-center gap-1.5 shrink-0 cursor-pointer border"
                    :class="activeZone === z.key
                        ? 'bg-[#1E1545] text-white border-[#1E1545]'
                        : 'bg-white text-[#292638] border-[#E9E7F0] hover:bg-[#F4F3F8]'"
                >
                    <span>{{ z.label }}</span>
                    <span
                        class="px-1.5 py-0.5 rounded-md text-[10px] font-bold tabular-nums"
                        :class="activeZone === z.key ? 'bg-white/20 text-white' : 'bg-[#F4F3F8] text-[#747181]'"
                    >
                        {{ zoneCounts[z.key] || 0 }}
                    </span>
                </button>
            </div>

            <!-- Banner rápido en Por entregar: subir todos los fijos listos desde Planta -->
            <div
                v-if="currentTab === 'por_entregar' && (tabCounts.ready_in_plant_bottles || 0) > 0"
                class="p-3.5 sm:p-4 bg-[#EEEAF7] border border-[#D7CEF0] rounded-2xl flex flex-wrap items-center justify-between gap-3"
            >
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-[#1E1545] text-white flex items-center justify-center shrink-0">
                        <ArrowUpFromLine class="w-4 h-4" />
                    </div>
                    <div>
                        <p class="text-xs sm:text-sm font-bold text-[#1E1545]">
                            {{ tabCounts.ready_in_plant_bottles }} botellón(es) fijos listos en Planta
                        </p>
                        <p class="text-[11px] text-[#747181]">
                            Súbelos al camión en un toque para entregarlos en ruta.
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    @click="handleDirectLoadAll"
                    :disabled="batchUploading"
                    class="px-4 py-2 rounded-xl text-xs font-bold bg-[#1E1545] hover:bg-[#2B1F60] text-white transition inline-flex items-center gap-1.5 cursor-pointer min-h-[40px] disabled:opacity-50"
                >
                    <CheckCheck class="w-4 h-4" />
                    <span>{{ batchUploading ? 'Subiendo...' : 'Subir todos al camión' }}</span>
                </button>
            </div>

            <!-- Lista operativa de tarjetas / filas -->
            <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-[0_1px_2px_rgba(30,21,69,0.04)] overflow-hidden">
                <div v-if="items.length === 0" class="p-10 text-center space-y-2">
                    <div class="w-11 h-11 rounded-2xl bg-[#E9F3F8] text-[#0071BA] mx-auto flex items-center justify-center">
                        <Truck class="w-5 h-5" />
                    </div>
                    <p class="text-sm font-bold text-[#292638]">
                        {{ currentTab === 'por_recoger' ? 'No hay fijos pendientes por recoger' : 'No hay pedidos pendientes por entregar' }}
                    </p>
                    <p class="text-xs text-[#747181]">
                        {{ currentTab === 'por_recoger'
                            ? 'Los nuevos pedidos de botellones fijos aparecerán aquí.'
                            : 'Cuando Planta termine el llenado o cargues cambio, aparecerán aquí.' }}
                    </p>
                </div>

                <div v-else class="divide-y divide-[#E9E7F0]">
                    <div
                        v-for="item in items"
                        :key="item.id"
                        class="p-4 sm:px-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3.5 hover:bg-[#F4F3F8]/45 transition"
                    >
                        <!-- Bloque izquierdo: Cliente, dirección visible, teléfono y badges claros -->
                        <div class="min-w-0 space-y-1.5 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-[#292638] text-sm sm:text-base truncate">
                                    {{ item.customer_nickname || item.customer_name || 'Cliente' }}
                                </span>

                                <!-- Cantidad -->
                                <span
                                    class="px-2 py-0.5 rounded-lg text-xs font-bold tabular-nums"
                                    :class="currentTab === 'por_recoger' ? 'bg-[#EEEAF7] text-[#1E1545]' : (item.operation_type === 'intercambio' && (item.fixed_in_truck || 0) === 0 ? 'bg-[#E8F2EC] text-[#26845B]' : 'bg-[#E9F3F8] text-[#0071BA]')"
                                >
                                    <template v-if="currentTab === 'por_recoger'">
                                        {{ item.fixed_to_collect || item.fixed_quantity }} {{ (item.fixed_to_collect || item.fixed_quantity) === 1 ? 'fijo' : 'fijos' }}
                                    </template>
                                    <template v-else-if="item.ready_state === 'in_plant' && (item.fixed_in_truck || 0) === 0">
                                        {{ item.fixed_ready_to_load || item.fixed_quantity }} {{ (item.fixed_ready_to_load || item.fixed_quantity) === 1 ? 'fijo' : 'fijos' }}
                                    </template>
                                    <template v-else-if="item.operation_type === 'intercambio' && (item.fixed_in_truck || 0) === 0">
                                        {{ item.exchange_pending || item.exchange_quantity || item.total_bottles }} de cambio
                                    </template>
                                    <template v-else>
                                        {{ item.fixed_in_truck || item.fixed_to_deliver || item.total_bottles }} {{ (item.fixed_in_truck || item.fixed_to_deliver || item.total_bottles) === 1 ? 'fijo' : 'fijos' }}
                                    </template>
                                </span>

                                <!-- Estado en Planta vs Camión y estado de pago (solo Por entregar) -->
                                <template v-if="currentTab === 'por_entregar'">
                                    <span
                                        v-if="item.ready_state === 'in_plant' && (item.fixed_in_truck || 0) === 0"
                                        class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#EEEAF7] text-[#1E1545]"
                                    >
                                        Listo en Planta
                                    </span>
                                    <template v-else>
                                        <span
                                            v-if="item.is_advance_paid"
                                            class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#E8F2EC] text-[#26845B]"
                                        >
                                            Pagado
                                        </span>
                                        <span
                                            v-else-if="(item.unpaid_amount || 0) > 0"
                                            class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#FEF5E6] text-[#9A670E] tabular-nums"
                                        >
                                            Cobrar {{ formatCurrency(item.unpaid_amount) }}
                                        </span>
                                    </template>
                                </template>
                            </div>

                            <!-- Dirección visible y accesos rápidos -->
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-[#747181]">
                                <span class="inline-flex items-center gap-1 truncate max-w-full">
                                    <MapPin class="w-3.5 h-3.5 text-[#0071BA] shrink-0" />
                                    <span class="truncate">{{ item.address_short || 'Sin domicilio' }}</span>
                                    <span v-if="item.zone_label && item.zone_label !== 'Sin zona'" class="text-[#1E1545] font-medium">
                                        ({{ item.zone_label }})
                                    </span>
                                </span>

                                <a
                                    v-if="item.customer_phone"
                                    :href="`tel:${item.customer_phone}`"
                                    class="inline-flex items-center gap-1 text-[#0071BA] hover:underline font-medium shrink-0"
                                >
                                    <Phone class="w-3 h-3" />
                                    {{ item.customer_phone }}
                                </a>

                                <button
                                    type="button"
                                    @click="openDetailModal(item)"
                                    class="inline-flex items-center gap-1 text-[#747181] hover:text-[#1E1545] font-medium cursor-pointer shrink-0"
                                >
                                    <Eye class="w-3.5 h-3.5" />
                                    Ver detalle
                                </button>
                            </div>
                        </div>

                        <!-- Bloque derecho: Acciones claras y contextuales -->
                        <div class="flex items-center justify-end gap-2 shrink-0">
                            <!-- TAB: POR RECOGER -->
                            <template v-if="currentTab === 'por_recoger'">
                                <button
                                    @click="openCancelModal(item)"
                                    type="button"
                                    class="h-10 w-10 rounded-xl flex items-center justify-center text-[#E82147] bg-[#FCECEF] hover:bg-[#FAD6DF] border border-[#F6C8D2] transition cursor-pointer"
                                    title="Cancelar pedido"
                                    aria-label="Cancelar pedido"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>

                                <button
                                    @click="openIncidentModal(item, 'por_recoger')"
                                    type="button"
                                    class="h-10 px-3 rounded-xl flex items-center gap-1.5 text-xs font-semibold text-[#9A670E] bg-[#FEF5E6] hover:bg-[#FDEBC8] border border-[#F5DFB5] transition cursor-pointer"
                                    title="Reportar incidencia"
                                >
                                    <AlertTriangle class="w-3.5 h-3.5" />
                                    <span class="hidden sm:inline">Incidencia</span>
                                </button>

                                <button
                                    @click="openCollectModal(item)"
                                    type="button"
                                    class="h-10 px-3.5 rounded-xl flex items-center gap-1.5 text-xs font-bold text-white bg-[#26845B] hover:bg-[#1F6D4B] transition cursor-pointer shadow-2xs"
                                >
                                    <Check class="w-4 h-4" />
                                    <span>Recoger ({{ item.fixed_to_collect || item.fixed_quantity }})</span>
                                </button>
                            </template>

                            <!-- TAB: POR ENTREGAR -> LISTO EN PLANTA (subir al camión) -->
                            <template v-else-if="item.ready_state === 'in_plant' && (item.fixed_in_truck || 0) === 0">
                                <button
                                    @click="openPlantLoadIncidentModal(item)"
                                    type="button"
                                    class="h-10 px-3 rounded-xl flex items-center gap-1.5 text-xs font-semibold text-[#9A670E] bg-[#FEF5E6] hover:bg-[#FDEBC8] border border-[#F5DFB5] transition cursor-pointer"
                                    title="Reportar incidencia en Planta"
                                >
                                    <AlertTriangle class="w-3.5 h-3.5" />
                                    <span class="hidden sm:inline">Incidencia</span>
                                </button>

                                <button
                                    v-if="(item.fixed_ready_to_load || item.fixed_quantity) > 1"
                                    @click="openPlantLoadModal(item)"
                                    type="button"
                                    class="h-10 px-3 rounded-xl flex items-center gap-1.5 text-xs font-semibold text-[#292638] bg-[#F4F3F8] hover:bg-[#E9E7F0] border border-[#E9E7F0] transition cursor-pointer"
                                    title="Cargar cantidad parcial"
                                >
                                    <SlidersHorizontal class="w-3.5 h-3.5" />
                                </button>

                                <button
                                    @click="handleDirectLoadSingle(item)"
                                    :disabled="uploadingSingle[item.id]"
                                    type="button"
                                    class="h-10 px-3.5 rounded-xl flex items-center gap-1.5 text-xs font-bold text-white bg-[#1E1545] hover:bg-[#2B1F60] transition cursor-pointer disabled:opacity-50"
                                >
                                    <ArrowUpFromLine class="w-3.5 h-3.5" />
                                    <span>{{ uploadingSingle[item.id] ? 'Subiendo...' : `Subir (${item.fixed_ready_to_load || item.fixed_quantity})` }}</span>
                                </button>
                            </template>

                            <!-- TAB: POR ENTREGAR -> EN CAMIÓN -->
                            <template v-else>
                                <button
                                    @click="openIncidentModal(item, 'por_entregar')"
                                    type="button"
                                    class="h-10 px-3 rounded-xl flex items-center gap-1.5 text-xs font-semibold text-[#9A670E] bg-[#FEF5E6] hover:bg-[#FDEBC8] border border-[#F5DFB5] transition cursor-pointer"
                                    title="Reportar incidencia en entrega"
                                >
                                    <AlertTriangle class="w-3.5 h-3.5" />
                                    <span class="hidden sm:inline">Incidencia</span>
                                </button>

                                <button
                                    @click="handleDeliverAction(item)"
                                    type="button"
                                    class="h-10 px-3.5 rounded-xl flex items-center gap-1.5 text-xs font-bold text-white bg-[#0071BA] hover:bg-[#005E9B] transition cursor-pointer shadow-2xs"
                                >
                                    <CheckCircle2 class="w-4 h-4" />
                                    <span>{{ item.operation_type === 'intercambio' && (item.fixed_in_truck || 0) === 0 ? 'Atender cambio' : 'Entregar' }}</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: DETALLE DEL CLIENTE -->
        <div
            v-if="showDetailModal && selectedDetailItem"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#1E1545]/50 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-[#E9E7F0] space-y-4">
                <div class="flex items-start justify-between gap-3 border-b border-[#E9E7F0] pb-3">
                    <div>
                        <span class="text-[11px] font-bold text-[#0071BA] uppercase tracking-wider block">Detalle de entrega</span>
                        <h3 class="text-base font-bold text-[#292638]">
                            {{ selectedDetailItem.customer_nickname || selectedDetailItem.customer_name || 'Cliente' }}
                        </h3>
                        <p v-if="selectedDetailItem.customer_nickname && selectedDetailItem.customer_name" class="text-xs text-[#747181]">
                            {{ selectedDetailItem.customer_name }}
                        </p>
                    </div>
                    <button
                        @click="closeDetailModal"
                        type="button"
                        class="h-9 w-9 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#747181] flex items-center justify-center cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="p-3 bg-[#F4F3F8] rounded-xl flex items-center justify-between">
                        <span class="text-[#747181] font-semibold">Folio</span>
                        <span class="font-mono font-bold text-[#292638]">{{ selectedDetailItem.folio }}</span>
                    </div>

                    <div class="p-3 bg-[#F4F3F8] rounded-xl space-y-1">
                        <span class="text-[#747181] font-semibold block">Domicilio completo</span>
                        <span class="text-[#292638] leading-relaxed block">{{ selectedDetailItem.address_summary || 'Sin domicilio registrado' }}</span>
                        <span v-if="selectedDetailItem.address_reference" class="text-[#747181] italic block">Ref: {{ selectedDetailItem.address_reference }}</span>
                    </div>

                    <div v-if="selectedDetailItem.customer_phone" class="p-3 bg-[#E9F3F8]/60 rounded-xl flex items-center justify-between">
                        <div>
                            <span class="text-[#747181] font-semibold block">Teléfono</span>
                            <span class="font-mono text-[#292638] font-bold">{{ selectedDetailItem.customer_phone }}</span>
                        </div>
                        <a
                            :href="`tel:${selectedDetailItem.customer_phone}`"
                            class="px-3.5 py-2 rounded-xl bg-[#0071BA] text-white font-bold inline-flex items-center gap-1.5 text-xs"
                        >
                            <Phone class="w-3.5 h-3.5" />
                            <span>Llamar</span>
                        </a>
                    </div>
                </div>

                <div class="flex justify-end pt-2 border-t border-[#E9E7F0]">
                    <button
                        @click="closeDetailModal"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#292638] text-xs font-semibold min-h-[40px] cursor-pointer"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: CONFIRMAR RECOGIDA -->
        <div
            v-if="showCollectModal && itemToCollect"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#1E1545]/50 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-[#E9E7F0] space-y-4">
                <div class="flex items-start justify-between gap-3 border-b border-[#E9E7F0] pb-3">
                    <div>
                        <span class="text-[11px] font-bold text-[#26845B] uppercase tracking-wider block">Recogida de fijos</span>
                        <h3 class="text-base font-bold text-[#292638]">
                            {{ itemToCollect.customer_nickname || itemToCollect.customer_name || 'Cliente' }}
                        </h3>
                        <p class="text-xs text-[#747181]">Pendientes: {{ itemToCollect.fixed_to_collect || itemToCollect.fixed_quantity }} envases</p>
                    </div>
                    <button
                        @click="closeCollectModal"
                        type="button"
                        class="h-9 w-9 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#747181] flex items-center justify-center cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="p-4 bg-[#F4F3F8] rounded-xl text-center space-y-2.5">
                    <span class="text-xs font-semibold text-[#747181] block">Botellones vacíos recogidos</span>
                    <div class="flex items-center justify-center gap-4">
                        <button
                            @click="decrementCollectQty"
                            :disabled="collectForm.quantity <= 1"
                            type="button"
                            class="h-11 w-11 rounded-xl bg-white border border-[#E9E7F0] text-[#292638] font-bold disabled:opacity-40 cursor-pointer"
                        >-</button>
                        <span class="text-3xl font-black text-[#1E1545] tabular-nums w-12 text-center">{{ collectForm.quantity }}</span>
                        <button
                            @click="incrementCollectQty"
                            :disabled="collectForm.quantity >= (itemToCollect.fixed_to_collect || itemToCollect.fixed_quantity)"
                            type="button"
                            class="h-11 w-11 rounded-xl bg-white border border-[#E9E7F0] text-[#292638] font-bold disabled:opacity-40 cursor-pointer"
                        >+</button>
                    </div>
                </div>

                <!-- Pago anticipado opcional -->
                <div class="p-3 bg-[#E9F3F8]/60 rounded-xl border border-[#BDDDF0] flex items-center justify-between gap-3">
                    <div>
                        <span class="text-xs font-bold text-[#292638] block">¿Pagó por adelantado?</span>
                        <span class="text-[11px] text-[#747181]">
                            {{ isAdvancePaymentMode ? `Cobra ${formatCurrency(collectForm.quantity * unitPrice)} ahora` : 'Se cobrará al entregar' }}
                        </span>
                    </div>
                    <button
                        @click="toggleAdvanceMode"
                        type="button"
                        class="px-3 py-2 rounded-xl text-xs font-bold border transition cursor-pointer min-h-[38px]"
                        :class="isAdvancePaymentMode ? 'bg-[#1E1545] text-white border-[#1E1545]' : 'bg-white text-[#292638] border-[#E9E7F0]'"
                    >
                        {{ isAdvancePaymentMode ? 'Sí, pagado' : 'Al entregar' }}
                    </button>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#E9E7F0]">
                    <button
                        @click="closeCollectModal"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#292638] text-xs font-semibold min-h-[40px] cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="submitCollection"
                        :disabled="collectForm.processing"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-[#26845B] hover:bg-[#1F6D4B] text-white text-xs font-bold min-h-[40px] cursor-pointer"
                    >
                        {{ collectForm.processing ? 'Guardando...' : `Confirmar (${collectForm.quantity})` }}
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: CANCELAR PEDIDO (POR RECOGER) -->
        <div
            v-if="showCancelModal && itemToCancel"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#1E1545]/50 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-[#F6C8D2] space-y-4">
                <div class="flex items-start justify-between gap-3 border-b border-[#E9E7F0] pb-3">
                    <div>
                        <span class="text-[11px] font-bold text-[#E82147] uppercase tracking-wider block">Cancelar pedido</span>
                        <h3 class="text-base font-bold text-[#292638]">
                            {{ itemToCancel.customer_nickname || itemToCancel.customer_name || 'Cliente' }}
                        </h3>
                        <p class="text-xs text-[#747181] font-mono">{{ itemToCancel.folio }}</p>
                    </div>
                    <button
                        @click="closeCancelModal"
                        type="button"
                        class="h-9 w-9 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#747181] flex items-center justify-center cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-2 text-xs">
                    <label class="font-semibold text-[#292638] block">Motivo obligatorio:</label>
                    <textarea
                        v-model="cancelForm.reason"
                        rows="3"
                        placeholder="Ej. Cliente canceló por teléfono..."
                        class="w-full text-xs rounded-xl border-[#E9E7F0] focus:border-[#E82147] focus:ring-[#E82147]/20"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#E9E7F0]">
                    <button
                        @click="closeCancelModal"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#292638] text-xs font-semibold min-h-[40px] cursor-pointer"
                    >
                        Volver
                    </button>
                    <button
                        @click="submitCancelOrder"
                        :disabled="cancelForm.processing"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-[#E82147] hover:bg-[#C91A3B] text-white text-xs font-bold min-h-[40px] cursor-pointer"
                    >
                        {{ cancelForm.processing ? 'Cancelando...' : 'Confirmar cancelación' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: INCIDENCIA DE RECOGIDA O ENTREGA (PERSISTIDO EN BD) -->
        <div
            v-if="showIncidentModal && itemForIncident"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#1E1545]/50 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-[#F5DFB5] space-y-4">
                <div class="flex items-start justify-between gap-3 border-b border-[#E9E7F0] pb-3">
                    <div>
                        <span class="text-[11px] font-bold text-[#9A670E] uppercase tracking-wider block">
                            {{ incidentTabType === 'por_recoger' ? 'Incidencia en recogida' : 'Incidencia en entrega' }}
                        </span>
                        <h3 class="text-base font-bold text-[#292638]">
                            {{ itemForIncident.customer_nickname || itemForIncident.customer_name || 'Cliente' }}
                        </h3>
                        <p class="text-xs text-[#747181] font-mono">{{ itemForIncident.folio }}</p>
                    </div>
                    <button
                        @click="closeIncidentModal"
                        type="button"
                        class="h-9 w-9 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#747181] flex items-center justify-center cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="font-semibold text-[#292638] block mb-1">Motivo</label>
                        <select v-model="incidentForm.reason" class="w-full text-xs rounded-xl border-[#E9E7F0]">
                            <option value="Cliente ausente en domicilio">Cliente ausente en domicilio</option>
                            <option value="Cliente no entregó envases completos">Cliente no entregó envases completos</option>
                            <option value="Envase con daño visible">Envase con daño visible</option>
                            <option value="Dirección inaccesible">Dirección inaccesible</option>
                            <option value="Otro motivo">Otro motivo</option>
                        </select>
                    </div>
                    <div>
                        <label class="font-semibold text-[#292638] block mb-1">Observaciones (opcional)</label>
                        <textarea
                            v-model="incidentForm.notes"
                            rows="2"
                            placeholder="Detalles adicionales..."
                            class="w-full text-xs rounded-xl border-[#E9E7F0]"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#E9E7F0]">
                    <button
                        @click="closeIncidentModal"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#292638] text-xs font-semibold min-h-[40px] cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="submitIncident"
                        :disabled="incidentForm.processing"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-[#D99A27] hover:bg-[#C0851B] text-white text-xs font-bold min-h-[40px] cursor-pointer"
                    >
                        {{ incidentForm.processing ? 'Guardando...' : 'Guardar incidencia' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: ENTREGA Y COBRO -->
        <div
            v-if="showDeliverModal && itemToDeliver"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#1E1545]/50 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-[#E9E7F0] space-y-4 max-h-[92vh] overflow-y-auto">
                <div class="flex items-start justify-between gap-3 border-b border-[#E9E7F0] pb-3">
                    <div>
                        <span class="text-[11px] font-bold text-[#0071BA] uppercase tracking-wider block">Entrega y cobro</span>
                        <h3 class="text-base font-bold text-[#292638]">
                            {{ itemToDeliver.customer_nickname || itemToDeliver.customer_name || 'Cliente' }}
                        </h3>
                        <p class="text-xs text-[#747181]">En camión: {{ itemToDeliver.fixed_in_truck || itemToDeliver.fixed_to_deliver }} fijos</p>
                    </div>
                    <button
                        @click="closeDeliverModal"
                        type="button"
                        class="h-9 w-9 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#747181] flex items-center justify-center cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="p-3 bg-[#F4F3F8] rounded-xl text-center space-y-2">
                    <span class="text-xs font-semibold text-[#747181] block">Unidades entregadas</span>
                    <div class="flex items-center justify-center gap-4">
                        <button
                            @click="onQuantityDeliveredChange(-1)"
                            :disabled="deliverForm.quantity_delivered <= 1"
                            type="button"
                            class="h-10 w-10 rounded-xl bg-white border border-[#E9E7F0] font-bold disabled:opacity-40 cursor-pointer"
                        >-</button>
                        <span class="text-2xl font-black text-[#1E1545] tabular-nums w-12 text-center">{{ deliverForm.quantity_delivered }}</span>
                        <button
                            @click="onQuantityDeliveredChange(1)"
                            :disabled="deliverForm.quantity_delivered >= maxAvailableInTruck"
                            type="button"
                            class="h-10 w-10 rounded-xl bg-white border border-[#E9E7F0] font-bold disabled:opacity-40 cursor-pointer"
                        >+</button>
                    </div>
                </div>

                <div v-if="unpaidUnitsToDeliver === 0" class="p-3.5 bg-[#E8F2EC] rounded-xl border border-[#BBE4CE] text-center text-xs text-[#26845B] font-semibold">
                    Pagado por adelantado · Saldo $0.00
                </div>

                <div v-else class="space-y-3 text-xs">
                    <div class="p-3 bg-[#E9F3F8] rounded-xl flex items-center justify-between">
                        <span class="font-semibold text-[#292638]">Total ({{ unpaidUnitsToDeliver }} × ${{ deliverUnitPrice }})</span>
                        <span class="text-base font-black text-[#0071BA] tabular-nums">{{ formatCurrency(pendingAmountTotal) }}</span>
                    </div>

                    <div class="space-y-2">
                        <button
                            type="button"
                            @click="selectPaymentAction('full_cash')"
                            class="w-full p-3 rounded-xl border text-left flex items-center justify-between cursor-pointer"
                            :class="deliverForm.payment_action === 'full_cash' ? 'bg-[#E9F3F8] border-[#0071BA] font-bold text-[#1E1545]' : 'bg-white border-[#E9E7F0] text-[#292638]'"
                        >
                            <span>Efectivo completo ({{ formatCurrency(pendingAmountTotal) }})</span>
                            <CheckCircle2 v-if="deliverForm.payment_action === 'full_cash'" class="w-4 h-4 text-[#0071BA]" />
                        </button>

                        <button
                            v-if="unpaidUnitsToDeliver > 1"
                            type="button"
                            @click="selectPaymentAction('partial_cash')"
                            class="w-full p-3 rounded-xl border text-left flex items-center justify-between cursor-pointer"
                            :class="deliverForm.payment_action === 'partial_cash' ? 'bg-[#E9F3F8] border-[#0071BA] font-bold text-[#1E1545]' : 'bg-white border-[#E9E7F0] text-[#292638]'"
                        >
                            <span>Pago parcial + fiado</span>
                            <CheckCircle2 v-if="deliverForm.payment_action === 'partial_cash'" class="w-4 h-4 text-[#0071BA]" />
                        </button>

                        <div v-if="deliverForm.payment_action === 'partial_cash'" class="p-3 bg-[#FEF5E6] rounded-xl border border-[#F5DFB5] flex items-center justify-between">
                            <span class="font-semibold text-[#9A670E]">Paga hoy:</span>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="deliverForm.units_paid_cash = Math.max(1, deliverForm.units_paid_cash - 1); deliverForm.cash_tendered = deliverForm.units_paid_cash * deliverUnitPrice"
                                    class="h-8 w-8 rounded-lg bg-white border border-[#E9E7F0] font-bold cursor-pointer"
                                >-</button>
                                <span class="font-bold tabular-nums">{{ deliverForm.units_paid_cash }} ({{ formatCurrency(deliverForm.units_paid_cash * deliverUnitPrice) }})</span>
                                <button
                                    type="button"
                                    @click="deliverForm.units_paid_cash = Math.min(unpaidUnitsToDeliver - 1, deliverForm.units_paid_cash + 1); deliverForm.cash_tendered = deliverForm.units_paid_cash * deliverUnitPrice"
                                    class="h-8 w-8 rounded-lg bg-white border border-[#E9E7F0] font-bold cursor-pointer"
                                >+</button>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="selectPaymentAction('defer')"
                            class="w-full p-3 rounded-xl border text-left flex items-center justify-between cursor-pointer"
                            :class="deliverForm.payment_action === 'defer' ? 'bg-[#EEEAF7] border-[#1E1545] font-bold text-[#1E1545]' : 'bg-white border-[#E9E7F0] text-[#292638]'"
                        >
                            <span>Dejar fiado</span>
                            <CheckCircle2 v-if="deliverForm.payment_action === 'defer'" class="w-4 h-4 text-[#1E1545]" />
                        </button>
                    </div>

                    <div v-if="deliverForm.payment_action === 'full_cash' || deliverForm.payment_action === 'partial_cash'" class="p-3 bg-[#F4F3F8] rounded-xl space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="font-semibold text-[#292638]">Efectivo recibido:</label>
                            <input
                                v-model.number="deliverForm.cash_tendered"
                                type="number"
                                min="0"
                                step="1"
                                class="w-24 text-right font-bold tabular-nums rounded-xl border-[#E9E7F0] py-1"
                            />
                        </div>
                        <div v-if="changeReturnAmount > 0" class="flex items-center justify-between text-[#26845B] font-bold pt-1 border-t border-[#E9E7F0]">
                            <span>Cambio:</span>
                            <span class="tabular-nums">{{ formatCurrency(changeReturnAmount) }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#E9E7F0]">
                    <button
                        @click="closeDeliverModal"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#292638] text-xs font-semibold min-h-[40px] cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="submitDeliver"
                        :disabled="deliverForm.processing"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-[#0071BA] hover:bg-[#005E9B] text-white text-xs font-bold min-h-[40px] cursor-pointer"
                    >
                        {{ deliverForm.processing ? 'Guardando...' : 'Confirmar entrega' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: SUBIDA PARCIAL DESDE PLANTA -->
        <div
            v-if="showPlantLoadModal && itemToPlantLoad"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#1E1545]/50 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-2xl max-w-sm w-full p-5 sm:p-6 shadow-2xl border border-[#E9E7F0] space-y-4">
                <div class="flex items-start justify-between gap-3 border-b border-[#E9E7F0] pb-3">
                    <div>
                        <span class="text-[11px] font-bold text-[#1E1545] uppercase tracking-wider block">Subir al camión</span>
                        <h3 class="text-base font-bold text-[#292638]">
                            {{ itemToPlantLoad.customer_nickname || itemToPlantLoad.customer_name || 'Cliente' }}
                        </h3>
                    </div>
                    <button
                        @click="closePlantLoadModal"
                        type="button"
                        class="h-9 w-9 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#747181] flex items-center justify-center cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="p-4 bg-[#F4F3F8] rounded-xl text-center space-y-2">
                    <span class="text-xs font-semibold text-[#747181] block">Listos en Planta: {{ maxPlantReady }}</span>
                    <div class="flex items-center justify-center gap-4">
                        <button
                            type="button"
                            @click="decrementPlantLoadQty"
                            :disabled="plantLoadForm.quantity <= 1"
                            class="w-11 h-11 rounded-xl bg-white border border-[#E9E7F0] font-bold disabled:opacity-40 cursor-pointer"
                        >-</button>
                        <span class="text-3xl font-black text-[#1E1545] tabular-nums w-12 text-center">{{ plantLoadForm.quantity }}</span>
                        <button
                            type="button"
                            @click="incrementPlantLoadQty"
                            :disabled="plantLoadForm.quantity >= maxPlantReady"
                            class="w-11 h-11 rounded-xl bg-white border border-[#E9E7F0] font-bold disabled:opacity-40 cursor-pointer"
                        >+</button>
                    </div>
                </div>

                <div v-if="plantLoadForm.quantity < maxPlantReady" class="text-xs">
                    <label class="block font-semibold text-[#292638] mb-1">Motivo de diferencia (opcional)</label>
                    <input
                        v-model="plantLoadForm.difference_reason"
                        type="text"
                        placeholder="Ej. Espacio en camión..."
                        class="w-full text-xs rounded-xl border-[#E9E7F0]"
                    />
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-[#E9E7F0]">
                    <button
                        type="button"
                        @click="closePlantLoadModal"
                        class="px-4 py-2 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#292638] text-xs font-semibold min-h-[40px] cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="submitPlantLoad"
                        :disabled="plantLoadForm.processing"
                        class="px-4 py-2 rounded-xl bg-[#1E1545] hover:bg-[#2B1F60] text-white text-xs font-bold min-h-[40px] cursor-pointer"
                    >
                        {{ plantLoadForm.processing ? 'Guardando...' : `Subir (${plantLoadForm.quantity})` }}
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: INCIDENCIA DE CARGA EN PLANTA -->
        <div
            v-if="showPlantIncidentModal && itemForPlantIncident"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#1E1545]/50 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-[#F5DFB5] space-y-4">
                <div class="flex items-start justify-between gap-3 border-b border-[#E9E7F0] pb-3">
                    <div>
                        <span class="text-[11px] font-bold text-[#9A670E] uppercase tracking-wider block">Incidencia en Planta</span>
                        <h3 class="text-base font-bold text-[#292638]">
                            {{ itemForPlantIncident.customer_nickname || itemForPlantIncident.customer_name || 'Cliente' }}
                        </h3>
                    </div>
                    <button
                        @click="closePlantLoadIncidentModal"
                        type="button"
                        class="h-9 w-9 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#747181] flex items-center justify-center cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-semibold text-[#292638] mb-1">Cantidad (máx. {{ maxPlantIncidentQty }})</label>
                        <input
                            v-model.number="plantIncidentForm.quantity"
                            type="number"
                            min="1"
                            :max="maxPlantIncidentQty"
                            class="w-24 text-center font-bold rounded-xl border-[#E9E7F0]"
                        />
                    </div>
                    <div>
                        <label class="block font-semibold text-[#292638] mb-1">Tipo</label>
                        <select v-model="plantIncidentForm.damage_type" class="w-full text-xs rounded-xl border-[#E9E7F0]">
                            <option value="rotura">Rotura / Roto</option>
                            <option value="fisura">Fisura / Gotea</option>
                            <option value="contaminacion">Contaminación</option>
                            <option value="falta_no_localizado">No localizado en Planta</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-[#292638] mb-1">Observaciones</label>
                        <textarea
                            v-model="plantIncidentForm.notes"
                            rows="2"
                            placeholder="Detalla lo sucedido..."
                            class="w-full text-xs rounded-xl border-[#E9E7F0]"
                        ></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-[#E9E7F0]">
                    <button
                        type="button"
                        @click="closePlantLoadIncidentModal"
                        class="px-4 py-2 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#292638] text-xs font-semibold min-h-[40px] cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="submitPlantIncident"
                        :disabled="plantIncidentForm.processing"
                        class="px-4 py-2 rounded-xl bg-[#D99A27] hover:bg-[#C0851B] text-white text-xs font-bold min-h-[40px] cursor-pointer"
                    >
                        {{ plantIncidentForm.processing ? 'Guardando...' : 'Registrar incidencia' }}
                    </button>
                </div>
            </div>
        </div>

        <RepartidorBottomNav />
    </AuthenticatedLayout>
</template>
