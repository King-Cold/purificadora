<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RepartidorBottomNav from '@/Components/RepartidorBottomNav.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    deliveries: {
        type: Array,
        default: () => [],
    },
    totalOrders: {
        type: Number,
        default: 0,
    },
    totalBottles: {
        type: Number,
        default: 0,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.errors?.deliver || page.props.flash?.error || null);

// Filtro de Zona
const selectedZone = ref('all');

const zoneTabs = [
    { key: 'all', label: 'Todas' },
    { key: 'north', label: 'Norte' },
    { key: 'south', label: 'Sur' },
    { key: 'east', label: 'Este' },
    { key: 'west', label: 'Oeste' },
    { key: 'none', label: 'Sin zona' },
];

const filteredDeliveries = computed(() => {
    if (selectedZone.value === 'all') {
        return props.deliveries;
    }
    return props.deliveries.filter(d => d.delivery_zone_key === selectedZone.value);
});

// Modal: Confirmar Entrega
const showDeliveryModal = ref(false);
const itemToDeliver = ref(null);

const deliveryForm = useForm({
    quantity_delivered: 1,
    payment_action: 'already_paid', // 'already_paid', 'full_cash', 'partial_cash', 'defer'
    cash_tendered: 0,
    units_paid_cash: 0,
    notes: '',
});

const openDeliveryModal = (item) => {
    itemToDeliver.value = item;
    const initialQty = item.fixed_in_truck || item.quantity_to_deliver || 1;
    deliveryForm.quantity_delivered = initialQty;

    // Determinar si está pagado por adelantado
    const advanceCovered = item.advance_paid_bottles || 0;
    if (advanceCovered >= initialQty) {
        deliveryForm.payment_action = 'already_paid';
        deliveryForm.units_paid_cash = 0;
        deliveryForm.cash_tendered = 0;
    } else {
        deliveryForm.payment_action = 'full_cash';
        const unpaid = initialQty - advanceCovered;
        deliveryForm.units_paid_cash = unpaid;
        deliveryForm.cash_tendered = unpaid * (item.unit_price || 15);
    }

    deliveryForm.notes = '';
    deliveryForm.clearErrors();
    showDeliveryModal.value = true;
};

const closeDeliveryModal = () => {
    showDeliveryModal.value = false;
    itemToDeliver.value = null;
};

// Cálculos dinámicos dentro del modal de entrega
const maxAvailableInTruck = computed(() => itemToDeliver.value?.fixed_in_truck || 1);
const unitPrice = computed(() => itemToDeliver.value?.unit_price || 15);

// Cuántas de las unidades seleccionadas a entregar ya estaban cubiertas por anticipado
const coveredByAdvance = computed(() => {
    if (!itemToDeliver.value) return 0;
    return Math.min(deliveryForm.quantity_delivered, itemToDeliver.value.advance_paid_bottles || 0);
});

// Cuántas unidades seleccionadas quedan pendientes de pago en esta entrega
const unpaidUnitsToDeliver = computed(() => {
    return Math.max(0, deliveryForm.quantity_delivered - coveredByAdvance.value);
});

// Importe pendiente total por las unidades no pagadas por anticipado
const pendingAmountTotal = computed(() => {
    return unpaidUnitsToDeliver.value * unitPrice.value;
});

// Si se cambia la cantidad a entregar
const onQuantityDeliveredChange = (delta) => {
    const newQty = deliveryForm.quantity_delivered + delta;
    if (newQty >= 1 && newQty <= maxAvailableInTruck.value) {
        deliveryForm.quantity_delivered = newQty;
        // Reajustar pago
        if (unpaidUnitsToDeliver.value === 0) {
            deliveryForm.payment_action = 'already_paid';
            deliveryForm.units_paid_cash = 0;
            deliveryForm.cash_tendered = 0;
        } else {
            if (deliveryForm.payment_action === 'already_paid') {
                deliveryForm.payment_action = 'full_cash';
            }
            if (deliveryForm.payment_action === 'full_cash') {
                deliveryForm.units_paid_cash = unpaidUnitsToDeliver.value;
                deliveryForm.cash_tendered = pendingAmountTotal.value;
            }
        }
    }
};

const selectPaymentAction = (action) => {
    deliveryForm.payment_action = action;
    if (action === 'full_cash') {
        deliveryForm.units_paid_cash = unpaidUnitsToDeliver.value;
        deliveryForm.cash_tendered = pendingAmountTotal.value;
    } else if (action === 'partial_cash') {
        deliveryForm.units_paid_cash = Math.max(1, unpaidUnitsToDeliver.value - 1);
        deliveryForm.cash_tendered = deliveryForm.units_paid_cash * unitPrice.value;
    } else if (action === 'defer') {
        deliveryForm.units_paid_cash = 0;
        deliveryForm.cash_tendered = 0;
    }
};

const incrementPartialPaid = () => {
    if (deliveryForm.units_paid_cash < unpaidUnitsToDeliver.value) {
        deliveryForm.units_paid_cash++;
        deliveryForm.cash_tendered = deliveryForm.units_paid_cash * unitPrice.value;
    }
};

const decrementPartialPaid = () => {
    if (deliveryForm.units_paid_cash > 1) {
        deliveryForm.units_paid_cash--;
        deliveryForm.cash_tendered = deliveryForm.units_paid_cash * unitPrice.value;
    }
};

const deferredUnits = computed(() => {
    if (deliveryForm.payment_action === 'defer') return unpaidUnitsToDeliver.value;
    if (deliveryForm.payment_action === 'partial_cash') {
        return Math.max(0, unpaidUnitsToDeliver.value - deliveryForm.units_paid_cash);
    }
    return 0;
});

const submitDelivery = () => {
    if (!itemToDeliver.value || deliveryForm.processing) return;

    deliveryForm.post(route('repartidor.orders.deliver-fixed', itemToDeliver.value.order_id || itemToDeliver.value.id), {
        preserveScroll: true,
        onSuccess: () => closeDeliveryModal(),
    });
};
</script>

<template>
    <Head title="Por entregar · Repartidor Puritronic" />

    <AuthenticatedLayout wide>
        <div class="max-w-6xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6 space-y-4 pb-28 md:pb-16">

            <!-- Mensajes Flash -->
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
            <!-- 1. ENCABEZADO Y RESUMEN                                                    -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs p-4 sm:p-5">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">
                            <Link :href="route('repartidor.reparto')" class="text-blue-900 hover:underline">Reparto</Link>
                            <span>/</span>
                            <span>Entregas de fijos</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
                            <i class="fa-solid fa-dolly text-blue-900"></i> Por entregar
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                            Devolución de botellones fijos llenados a sus propietarios con liquidación o registro de cobro.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 self-start md:self-center">
                        <span class="px-3 py-1.5 rounded-xl bg-blue-50 text-blue-900 border border-blue-200 text-xs font-black">
                            {{ filteredDeliveries.length }} pedido(s)
                        </span>
                    </div>
                </div>

                <!-- Filtro por Zona -->
                <div class="pt-3.5 flex items-center gap-2 overflow-x-auto">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider shrink-0 hidden sm:inline">Zona:</span>
                    <button
                        v-for="tab in zoneTabs"
                        :key="tab.key"
                        @click="selectedZone = tab.key"
                        type="button"
                        class="px-3.5 py-2 rounded-xl text-xs font-extrabold transition shrink-0 inline-flex items-center gap-1.5 border min-h-[44px]"
                        :class="selectedZone === tab.key
                            ? 'bg-blue-900 text-white border-blue-900 shadow-2xs'
                            : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                    >
                        <span>{{ tab.label }}</span>
                    </button>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. TABLA COMPACTA: CLIENTE, CANTIDAD POR ENTREGAR Y CONFIRMAR ENTREGA     -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                <div v-if="filteredDeliveries.length === 0" class="p-12 text-center space-y-2">
                    <div class="h-12 w-12 rounded-2xl bg-slate-100 text-slate-400 mx-auto flex items-center justify-center text-xl">
                        <i class="fa-solid fa-check-double"></i>
                    </div>
                    <p class="text-sm font-bold text-slate-800">No hay entregas pendientes en esta zona</p>
                    <p class="text-xs text-slate-400">Todos los fijos cargados en el camión han sido devueltos a sus clientes.</p>
                </div>

                <div v-else class="divide-y divide-slate-100">
                    <!-- Cabecera -->
                    <div class="hidden md:grid md:grid-cols-12 gap-3 px-5 py-3 bg-slate-50/80 text-[11px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <div class="col-span-5">Cliente</div>
                        <div class="col-span-3 text-center">Cantidad por entregar (20 L)</div>
                        <div class="col-span-4 text-right">Acción</div>
                    </div>

                    <!-- Filas -->
                    <div
                        v-for="item in filteredDeliveries"
                        :key="item.id"
                        class="p-4 sm:px-5 hover:bg-slate-50/60 transition flex flex-col md:grid md:grid-cols-12 gap-3 md:items-center"
                    >
                        <!-- Cliente -->
                        <div class="md:col-span-5 space-y-0.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-black text-slate-900 text-sm sm:text-base">
                                    {{ item.customer.nickname ? `"${item.customer.nickname}"` : item.customer.name }}
                                </span>
                                <span v-if="item.customer.nickname && item.customer.name" class="text-xs text-slate-500 hidden sm:inline">
                                    ({{ item.customer.name }})
                                </span>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-mono bg-slate-100 text-slate-600 border border-slate-200">
                                    {{ item.folio }}
                                </span>
                            </div>

                            <p class="text-xs text-slate-400 line-clamp-1">
                                <i class="fa-solid fa-location-dot mr-1 text-[11px]"></i>
                                {{ item.address_summary }}
                            </p>

                            <!-- Badge de estado de pago previo si aplica -->
                            <div v-if="item.is_advance_paid" class="pt-0.5">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-black bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    <i class="fa-solid fa-circle-check text-emerald-600"></i> Pagado anticipadamente · Saldo $0
                                </span>
                            </div>
                        </div>

                        <!-- Cantidad por entregar -->
                        <div class="md:col-span-3 flex items-center justify-between md:justify-center gap-2 bg-blue-50/40 md:bg-transparent p-2 md:p-0 rounded-xl">
                            <span class="text-xs font-bold text-slate-500 md:hidden">Por entregar:</span>
                            <div class="text-center">
                                <span class="text-base sm:text-lg font-black text-slate-900 font-mono">
                                    {{ item.fixed_in_truck || item.quantity_to_deliver }}
                                </span>
                                <span class="text-xs text-slate-500 font-semibold ml-1">en camión</span>
                            </div>
                        </div>

                        <!-- Acción: Confirmar entrega -->
                        <div class="md:col-span-4 flex items-center justify-end gap-2 pt-2 md:pt-0 border-t md:border-t-0 border-slate-100">
                            <button
                                @click="openDeliveryModal(item)"
                                type="button"
                                class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl text-xs font-black bg-blue-900 hover:bg-blue-800 text-white active:scale-95 transition shadow-2xs min-h-[44px]"
                            >
                                <i class="fa-solid fa-check text-sky-400 text-xs"></i>
                                <span>Confirmar entrega</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: CONFIRMAR ENTREGA Y ESTADO DEL PAGO                                 -->
        <!-- ========================================================================= -->
        <div
            v-if="showDeliveryModal && itemToDeliver"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 animate-in fade-in zoom-in-95 max-h-[90vh] overflow-y-auto">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <span class="text-[10px] font-bold text-blue-900 uppercase tracking-wider block">Entrega física de fijos 20L</span>
                        <h3 class="text-base font-black text-slate-900">
                            {{ itemToDeliver.customer.nickname ? `"${itemToDeliver.customer.nickname}"` : itemToDeliver.customer.name }}
                        </h3>
                        <p class="text-xs text-slate-500">Folio: {{ itemToDeliver.folio }} · En camión: {{ maxAvailableInTruck }} fijos</p>
                    </div>
                    <button
                        @click="closeDeliveryModal"
                        type="button"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="space-y-4 text-xs">
                    <!-- Selector de unidades devueltas -->
                    <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 text-center space-y-2">
                        <span class="text-xs font-bold text-slate-600 block">Botellones llenos que se entregan físicamente</span>
                        <div class="flex items-center justify-center gap-4">
                            <button
                                @click="onQuantityDeliveredChange(-1)"
                                :disabled="deliveryForm.quantity_delivered <= 1"
                                type="button"
                                class="h-12 w-12 rounded-2xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 disabled:opacity-40 flex items-center justify-center text-lg font-bold shadow-2xs"
                            >
                                <i class="fa-solid fa-minus"></i>
                            </button>
                            <span class="text-3xl font-black text-slate-900 font-mono w-16 text-center">
                                {{ deliveryForm.quantity_delivered }}
                            </span>
                            <button
                                @click="onQuantityDeliveredChange(1)"
                                :disabled="deliveryForm.quantity_delivered >= maxAvailableInTruck"
                                type="button"
                                class="h-12 w-12 rounded-2xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 disabled:opacity-40 flex items-center justify-center text-lg font-bold shadow-2xs"
                            >
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                        <p v-if="deliveryForm.quantity_delivered < maxAvailableInTruck" class="text-[11px] text-amber-700 font-bold bg-amber-50 p-1.5 rounded-lg border border-amber-200">
                            Se registrará la entrega de {{ deliveryForm.quantity_delivered }} y el pedido permanecerá abierto mientras queden unidades por devolver.
                        </p>
                    </div>

                    <!-- ESTADO DEL PAGO -->
                    <!-- Caso 1: Pagado anticipadamente -->
                    <div
                        v-if="unpaidUnitsToDeliver === 0"
                        class="p-4 bg-emerald-50 rounded-2xl border border-emerald-200 space-y-1 text-emerald-950"
                    >
                        <div class="flex items-center gap-2 text-emerald-800 font-black text-sm">
                            <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                            <span>Pagado anticipadamente · Saldo $0</span>
                        </div>
                        <p class="text-[11px] text-emerald-800 leading-relaxed">
                            Estas {{ deliveryForm.quantity_delivered }} unidades fueron liquidadas en efectivo al momento de la recogida en domicilio. No se requiere cobro adicional.
                        </p>
                    </div>

                    <!-- Caso 2: Queda saldo pendiente (Opciones: Pagó completo, Pagó parcialmente, Dejar fiado) -->
                    <div v-else class="space-y-3">
                        <div class="p-3 bg-blue-50/60 rounded-2xl border border-blue-200/80 flex items-center justify-between">
                            <div>
                                <span class="text-[11px] text-blue-900 font-bold block">Importe pendiente de cobro:</span>
                                <span class="text-xs text-slate-600">
                                    {{ unpaidUnitsToDeliver }} botellones × ${{ unitPrice }} MXN
                                </span>
                            </div>
                            <span class="text-xl font-black text-blue-950 font-mono">${{ pendingAmountTotal }} MXN</span>
                        </div>

                        <!-- Segmented control de las 3 opciones -->
                        <div class="space-y-1.5">
                            <label class="font-bold text-slate-700 block text-xs">Forma de liquidación:</label>
                            <div class="grid grid-cols-3 gap-1.5 p-1 bg-slate-100 rounded-2xl border border-slate-200">
                                <button
                                    type="button"
                                    @click="selectPaymentAction('full_cash')"
                                    class="py-2 px-1 rounded-xl text-xs font-black transition min-h-[44px] flex flex-col items-center justify-center leading-tight"
                                    :class="deliveryForm.payment_action === 'full_cash' ? 'bg-white text-blue-950 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                >
                                    <span>Pagó completo</span>
                                    <span class="text-[10px] font-normal font-mono text-emerald-700 font-bold">${{ pendingAmountTotal }}</span>
                                </button>

                                <button
                                    type="button"
                                    @click="selectPaymentAction('partial_cash')"
                                    class="py-2 px-1 rounded-xl text-xs font-black transition min-h-[44px] flex flex-col items-center justify-center leading-tight"
                                    :class="deliveryForm.payment_action === 'partial_cash' ? 'bg-white text-blue-950 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                >
                                    <span>Pagó parcial</span>
                                    <span class="text-[10px] font-normal text-amber-700 font-bold">Dividir</span>
                                </button>

                                <button
                                    type="button"
                                    @click="selectPaymentAction('defer')"
                                    class="py-2 px-1 rounded-xl text-xs font-black transition min-h-[44px] flex flex-col items-center justify-center leading-tight"
                                    :class="deliveryForm.payment_action === 'defer' ? 'bg-white text-blue-950 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                >
                                    <span>Dejar fiado</span>
                                    <span class="text-[10px] font-normal text-rose-700 font-bold">Sin cobro</span>
                                </button>
                            </div>
                        </div>

                        <!-- Detalle según opción seleccionada -->
                        <div v-if="deliveryForm.payment_action === 'partial_cash'" class="p-3 bg-amber-50/70 rounded-2xl border border-amber-200 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-amber-950">Botellones pagados en efectivo:</span>
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="decrementPartialPaid"
                                        :disabled="deliveryForm.units_paid_cash <= 1"
                                        type="button"
                                        class="h-8 w-8 rounded-lg bg-white border border-amber-300 text-amber-900 flex items-center justify-center disabled:opacity-40"
                                    >
                                        <i class="fa-solid fa-minus text-xs"></i>
                                    </button>
                                    <span class="text-lg font-black font-mono text-amber-950 w-8 text-center">
                                        {{ deliveryForm.units_paid_cash }}
                                    </span>
                                    <button
                                        @click="incrementPartialPaid"
                                        :disabled="deliveryForm.units_paid_cash >= unpaidUnitsToDeliver"
                                        type="button"
                                        class="h-8 w-8 rounded-lg bg-white border border-amber-300 text-amber-900 flex items-center justify-center disabled:opacity-40"
                                    >
                                        <i class="fa-solid fa-plus text-xs"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-1 border-t border-amber-200/60 text-xs">
                                <span class="text-amber-900 font-semibold">Efectivo a recibir:</span>
                                <span class="font-black text-emerald-700 font-mono text-sm">${{ deliveryForm.cash_tendered }} MXN</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-amber-900 font-semibold">Unidades que quedan fiadas:</span>
                                <span class="font-black text-rose-700 font-mono text-sm">{{ deferredUnits }} botellones (${{ deferredUnits * unitPrice }} MXN)</span>
                            </div>
                        </div>

                        <!-- Advertencia de fiado si aplica -->
                        <div v-if="deferredUnits > 0" class="p-2.5 bg-slate-50 rounded-xl border border-slate-200 text-[11px] text-slate-600">
                            <i class="fa-solid fa-circle-info text-blue-900 mr-1"></i>
                            El límite de fiado del cliente se validará al confirmar la entrega.
                        </div>
                    </div>

                    <!-- Notas opcionales -->
                    <div>
                        <label class="text-[11px] font-bold text-slate-600 block mb-1">Notas de entrega (opcional):</label>
                        <input
                            v-model="deliveryForm.notes"
                            type="text"
                            placeholder="Ej. Recibió encargado en puerta..."
                            class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-900 focus:ring-blue-900"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="closeDeliveryModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold min-h-[44px]"
                    >
                        Volver
                    </button>
                    <button
                        @click="submitDelivery"
                        :disabled="deliveryForm.processing"
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-blue-900 hover:bg-blue-800 text-white text-xs font-black min-h-[44px] shadow-2xs active:scale-95 transition"
                    >
                        {{ deliveryForm.processing ? 'Guardando...' : (unpaidUnitsToDeliver === 0 ? 'Finalizar entrega' : 'Confirmar entrega y cobro') }}
                    </button>
                </div>
            </div>
        </div>

        <RepartidorBottomNav />
    </AuthenticatedLayout>
</template>
