<script setup>
import { ref, computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    stop: {
        type: Object,
        required: true,
    },
    route: {
        type: Object,
        required: true,
    },
    order: {
        type: Object,
        required: true,
    },
    customer: {
        type: Object,
        required: true,
    },
    traffic_light: {
        type: Object,
        required: true,
    },
    pending_debt: {
        type: Object,
        required: true,
    },
    weekly_promo: {
        type: Object,
        required: true,
    },
    truck: {
        type: Object,
        required: true,
    },
    unit_price: {
        type: Number,
        default: 15.00,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.errors?.serve || page.props.errors?.debt || page.props.errors?.stop || null);

// -------------------------------------------------------------------------
// GESTIÓN DE SALDOS ANTERIORES
// -------------------------------------------------------------------------
const showDebtSettlement = ref(false);
const debtBottlesCount = ref(props.pending_debt.bottles > 0 ? 1 : 0);
const debtExactAmount = computed(() => round(debtBottlesCount.value * props.unit_price));
const debtCashTendered = ref(debtExactAmount.value);

const debtChangeReturned = computed(() => {
    const diff = Number(debtCashTendered.value || 0) - debtExactAmount.value;
    return diff > 0 ? round(diff) : 0;
});

const debtForm = useForm({
    bottles_count: debtBottlesCount.value,
    cash_tendered: debtCashTendered.value,
    notes: '',
});

const submitSettleDebt = () => {
    debtForm.bottles_count = debtBottlesCount.value;
    debtForm.cash_tendered = debtCashTendered.value;
    debtForm.post(route('repartidor.customers.settle-debt', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDebtSettlement.value = false;
        },
    });
};

// -------------------------------------------------------------------------
// FORMULARIO PRINCIPAL DE ATENCIÓN DE PARADA
// -------------------------------------------------------------------------
const maxExchangeAvailable = computed(() => {
    return Math.min(props.order.exchange_requested, props.truck.exchange_full_available);
});

// Valores de cambio
const exchangeEmptyReceived = ref(props.order.exchange_requested > 0 ? Math.min(props.order.exchange_requested, props.truck.exchange_full_available) : 0);
const exchangeDelivered = ref(props.order.exchange_requested > 0 ? Math.min(exchangeEmptyReceived.value, maxExchangeAvailable.value) : 0);
const cancellationReason = ref('Falta de envase vacío');

watch(exchangeEmptyReceived, (newVal) => {
    const validVal = Math.max(0, parseInt(newVal) || 0);
    // No puede entregar más de lo que recibió de vacíos, ni más de lo solicitado, ni más de lo disponible en camión
    if (exchangeDelivered.value > validVal) {
        exchangeDelivered.value = Math.min(validVal, maxExchangeAvailable.value);
    }
});

const exchangeCancelled = computed(() => {
    if (props.order.exchange_requested <= 0) return 0;
    return Math.max(0, props.order.exchange_requested - exchangeDelivered.value);
});

// Valores de fijos (recolección)
const fixedCollected = ref(props.order.fixed_requested > 0 ? props.order.fixed_requested : 0);
const fixedCancellationReason = ref('Cliente no entregó los botellones fijos');

const fixedCancelled = computed(() => {
    if (props.order.fixed_requested <= 0) return 0;
    return Math.max(0, props.order.fixed_requested - fixedCollected.value);
});

// Valores de fijos (devolución si aplica)
const fixedDelivered = ref(props.truck.fixed_available_for_customer > 0 ? props.truck.fixed_available_for_customer : 0);

// Unidades que generan cobro en esta visita
const totalDeliveredBottles = computed(() => {
    return Number(exchangeDelivered.value || 0) + Number(fixedDelivered.value || 0);
});

// Cálculo de promoción semanal
const promoApplied = computed(() => {
    if (totalDeliveredBottles.value <= 0) return false;
    // Si ya tiene disponible el 10º gratis
    if (props.weekly_promo.can_redeem) return true;
    // O si al pagar en efectivo completa los 9 pagados de la semana
    const paidInWeek = props.weekly_promo.paid_count || 0;
    const paidNow = unitsPaidCash.value || 0;
    if (paidInWeek + paidNow >= props.weekly_promo.required && (totalDeliveredBottles.value - paidNow) > 0) {
        return true;
    }
    return false;
});

const unitsToCharge = computed(() => {
    const total = totalDeliveredBottles.value;
    if (total <= 0) return 0;
    return promoApplied.value ? Math.max(0, total - 1) : total;
});

// Cobro: contado vs fiado
const unitsPaidCash = ref(unitsToCharge.value);
watch(unitsToCharge, (newVal) => {
    unitsPaidCash.value = newVal;
});

const unitsToCredit = computed(() => {
    return Math.max(0, unitsToCharge.value - unitsPaidCash.value);
});

const currentDebtBottles = computed(() => props.pending_debt.bottles || 0);
const creditLimit = computed(() => Number(props.traffic_light?.limit ?? 0));
const availableCreditMargin = computed(() => Math.max(0, creditLimit.value - currentDebtBottles.value));

const isCreditExceeded = computed(() => {
    if (unitsToCredit.value <= 0) return false;
    return unitsToCredit.value > availableCreditMargin.value;
});

const cashAmountToPay = computed(() => {
    return round(unitsPaidCash.value * props.unit_price);
});

const cashTendered = ref(cashAmountToPay.value);
watch(cashAmountToPay, (newVal) => {
    cashTendered.value = newVal;
});

const changeReturned = computed(() => {
    const diff = Number(cashTendered.value || 0) - cashAmountToPay.value;
    return diff > 0 ? round(diff) : 0;
});

const setQuickCash = (val) => {
    if (val === 'exact') {
        cashTendered.value = cashAmountToPay.value;
    } else {
        cashTendered.value = val;
    }
};

function round(val) {
    return Math.round((Number(val) + Number.EPSILON) * 100) / 100;
}

// Envío del formulario
const serveForm = useForm({
    exchange_delivered: 0,
    exchange_empty_received: 0,
    cancellation_reason: '',
    fixed_collected: 0,
    fixed_uncollected_cancelled_reason: '',
    fixed_delivered: 0,
    units_paid_cash: 0,
    cash_tendered: 0,
});

const submitServe = () => {
    if (isCreditExceeded.value) {
        alert(`No es posible fiar ${unitsToCredit.value} botellones. El margen disponible de fiado es de ${availableCreditMargin.value} botellones.`);
        return;
    }

    if (unitsPaidCash.value > 0 && Number(cashTendered.value || 0) < cashAmountToPay.value) {
        alert(`El efectivo recibido ($${cashTendered.value}) es insuficiente para cubrir los $${cashAmountToPay.value} a cobrar.`);
        return;
    }

    serveForm.exchange_delivered = exchangeDelivered.value;
    serveForm.exchange_empty_received = exchangeEmptyReceived.value;
    serveForm.cancellation_reason = exchangeCancelled.value > 0 ? cancellationReason.value : null;

    serveForm.fixed_collected = fixedCollected.value;
    serveForm.fixed_uncollected_cancelled_reason = fixedCancelled.value > 0 ? fixedCancellationReason.value : null;
    serveForm.fixed_delivered = fixedDelivered.value;

    serveForm.units_paid_cash = unitsPaidCash.value;
    serveForm.cash_tendered = cashTendered.value;

    serveForm.post(route('repartidor.stops.process-serve', props.stop.id), {
        preserveScroll: true,
    });
};

const getTrafficLightColor = (tl) => {
    if (!tl) return 'bg-slate-100 text-slate-700 border-slate-200';
    if (tl.color === 'red') return 'bg-rose-100 text-rose-800 border-rose-300';
    if (tl.color === 'yellow') return 'bg-amber-100 text-amber-800 border-amber-300';
    return 'bg-emerald-100 text-emerald-800 border-emerald-300';
};
</script>

<template>
    <Head :title="`Atender Parada #${stop.stop_number} · ${customer.nickname}`" />

    <AuthenticatedLayout>
        <div class="max-w-2xl mx-auto space-y-4 pb-16">

            <!-- ========================================================================= -->
            <!-- 1. ENCABEZADO Y NAVEGACIÓN RÁPIDA                                          -->
            <!-- ========================================================================= -->
            <div class="flex items-center justify-between gap-3">
                <Link
                    :href="route('repartidor.route.index')"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold bg-white text-slate-700 border border-slate-200 shadow-2xs hover:bg-slate-50 active:scale-95 transition"
                >
                    <i class="fa-solid fa-arrow-left"></i> Volver a Mi Ruta
                </Link>

                <div class="text-right">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Ruta {{ route.folio }}</span>
                    <span class="text-xs font-extrabold text-blue-900">Parada #{{ stop.stop_number }}</span>
                </div>
            </div>

            <!-- Alertas de éxito o error -->
            <div v-if="flashSuccess" class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-bold">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs font-bold">
                {{ flashError }}
            </div>

            <!-- ========================================================================= -->
            <!-- 2. FICHA DEL CLIENTE Y SEMÁFORO                                           -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <span class="text-xs font-mono text-slate-400 font-bold tracking-wider">{{ customer.code }}</span>
                        <h2 class="text-xl sm:text-2xl font-black text-slate-900 leading-tight">
                            {{ customer.nickname }}
                        </h2>
                        <p v-if="customer.name" class="text-xs text-slate-600 font-medium mt-0.5">
                            {{ customer.name }}
                        </p>
                    </div>

                    <!-- Badge Semáforo -->
                    <span
                        class="px-2.5 py-1 rounded-xl text-xs font-black border flex items-center gap-1.5 shrink-0"
                        :class="getTrafficLightColor(traffic_light)"
                    >
                        <i class="fa-solid fa-circle text-[10px]" :class="traffic_light.color === 'red' ? 'text-rose-600' : (traffic_light.color === 'yellow' ? 'text-amber-500' : 'text-emerald-600')"></i>
                        <span v-if="traffic_light.limit === 0">
                            Fiado deshabilitado<template v-if="traffic_light.pending_bottles > 0"> ({{ traffic_light.pending_bottles }} pend.)</template>
                        </span>
                        <span v-else>
                            {{ traffic_light.pending_bottles }}/{{ traffic_light.limit }} pend.
                        </span>
                    </span>
                </div>

                <!-- Ubicación y Teléfono Real -->
                <div class="pt-2 border-t border-slate-100 text-xs text-slate-600 space-y-1.5">
                    <div class="flex items-start gap-2">
                        <i class="fa-solid fa-location-dot text-slate-400 text-sm shrink-0 mt-0.5"></i>
                        <div>
                            <p class="font-medium text-slate-800">{{ order.address_summary }}</p>
                            <p class="text-[11px] text-slate-400">Zona: {{ order.delivery_zone }}</p>
                            <p v-if="order.reference" class="text-[11px] text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md mt-1 inline-block">
                                Ref: {{ order.reference }}
                            </p>
                        </div>
                    </div>

                    <div v-if="customer.has_phone" class="pt-1 flex items-center gap-2">
                        <a
                            :href="`tel:${customer.phone}`"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold hover:bg-emerald-100 active:scale-95 transition"
                        >
                            <i class="fa-solid fa-phone"></i> Llamar: {{ customer.phone }}
                        </a>
                    </div>
                </div>

                <!-- Promoción Semanal 9+1 -->
                <div class="p-3 rounded-xl bg-blue-50/70 border border-blue-200/60 text-xs space-y-1">
                    <div class="flex items-center justify-between font-bold text-blue-950">
                        <span class="flex items-center gap-1.5">
                            <i class="fa-solid fa-gift text-blue-700"></i> Promoción Semanal (9+1)
                        </span>
                        <span class="text-[11px] text-blue-700 font-mono">{{ weekly_promo.paid_count }}/{{ weekly_promo.required }} pagados</span>
                    </div>
                    <p class="text-blue-800 text-[11px] leading-relaxed">
                        {{ weekly_promo.label }}
                    </p>
                </div>

                <!-- Banner de Deuda Anterior si existe -->
                <div
                    v-if="pending_debt.bottles > 0"
                    class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs flex items-center justify-between gap-3"
                >
                    <div class="space-y-0.5">
                        <span class="font-bold text-amber-900 block flex items-center gap-1.5">
                            <i class="fa-solid fa-triangle-exclamation"></i> Saldo anterior pendiente
                        </span>
                        <span class="text-amber-800">
                            Debe <strong>{{ pending_debt.bottles }} botellones</strong> (${{ pending_debt.amount }})
                        </span>
                    </div>
                    <button
                        @click="showDebtSettlement = !showDebtSettlement"
                        type="button"
                        class="px-3 py-2 rounded-xl text-xs font-extrabold bg-amber-600 text-white hover:bg-amber-700 active:scale-95 transition shrink-0"
                    >
                        {{ showDebtSettlement ? 'Ocultar' : 'Cobrar Deuda' }}
                    </button>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 3. MODAL / CAJA DE LIQUIDACIÓN DE SALDO ANTERIOR                          -->
            <!-- ========================================================================= -->
            <div
                v-if="showDebtSettlement && pending_debt.bottles > 0"
                class="bg-amber-50/80 rounded-2xl p-4 sm:p-5 border border-amber-300 shadow-xs space-y-3"
            >
                <div class="flex items-center justify-between border-b border-amber-200 pb-2">
                    <h3 class="font-black text-amber-950 text-sm flex items-center gap-1.5">
                        <i class="fa-solid fa-money-bill-wave text-amber-800"></i> Liquidar Botellones Anteriores
                    </h3>
                    <span class="text-xs text-amber-800 font-semibold">Máx: {{ pending_debt.bottles }} u.</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <!-- Cantidad a liquidar -->
                    <div>
                        <label class="block font-bold text-amber-900 mb-1">Botellones a liquidar:</label>
                        <div class="flex items-center gap-2">
                            <button
                                @click="debtBottlesCount = Math.max(1, debtBottlesCount - 1)"
                                type="button"
                                class="w-10 h-10 rounded-xl bg-white border border-amber-300 font-bold text-lg active:scale-95 transition"
                            >-</button>
                            <input
                                v-model.number="debtBottlesCount"
                                type="number"
                                :min="1"
                                :max="pending_debt.bottles"
                                class="w-20 text-center font-black text-base py-2 rounded-xl border border-amber-300 bg-white"
                            />
                            <button
                                @click="debtBottlesCount = Math.min(pending_debt.bottles, debtBottlesCount + 1)"
                                type="button"
                                class="w-10 h-10 rounded-xl bg-white border border-amber-300 font-bold text-lg active:scale-95 transition"
                            >+</button>
                        </div>
                    </div>

                    <!-- Importe exacto -->
                    <div>
                        <label class="block font-bold text-amber-900 mb-1">Importe exacto a liquidar:</label>
                        <p class="text-2xl font-black text-amber-950">${{ debtExactAmount }}</p>
                    </div>

                    <!-- Efectivo recibido -->
                    <div class="sm:col-span-2 space-y-1">
                        <label class="block font-bold text-amber-900">Efectivo recibido del cliente ($):</label>
                        <input
                            v-model.number="debtCashTendered"
                            type="number"
                            step="0.5"
                            class="w-full text-lg font-black py-2.5 px-3 rounded-xl border border-amber-300 bg-white"
                        />
                        <p v-if="debtChangeReturned > 0" class="text-emerald-700 font-bold text-xs pt-1">
                            Cambio a devolver al cliente: ${{ debtChangeReturned }}
                        </p>
                    </div>
                </div>

                <button
                    @click="submitSettleDebt"
                    :disabled="debtForm.processing || debtCashTendered < debtExactAmount"
                    type="button"
                    class="w-full py-3 rounded-xl text-xs sm:text-sm font-extrabold bg-amber-600 text-white hover:bg-amber-700 active:scale-95 transition shadow-2xs disabled:opacity-50"
                >
                    Confirmar Cobro de ${{ debtExactAmount }}
                </button>
            </div>

            <!-- ========================================================================= -->
            <!-- 4. OPERACIÓN: BOTELLONES DE CAMBIO (INTERCAMBIO)                          -->
            <!-- ========================================================================= -->
            <div
                v-if="order.exchange_requested > 0"
                class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs space-y-4"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-arrows-rotate text-blue-900 text-xl"></i>
                        <div>
                            <h3 class="font-extrabold text-blue-950 text-sm sm:text-base">Botellones de Cambio (20L)</h3>
                            <p class="text-[11px] text-slate-500">Intercambio lleno por vacío en domicilio</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded-xl text-xs font-extrabold bg-blue-50 text-blue-800 border border-blue-200">
                        {{ order.exchange_requested }} solicitados
                    </span>
                </div>

                <!-- Disponibilidad en camión -->
                <div class="p-2.5 rounded-xl bg-slate-50 text-xs flex items-center justify-between text-slate-600">
                    <span>Llenos de cambio disponibles en tu camión:</span>
                    <span class="font-black text-blue-900 text-sm">{{ truck.exchange_full_available }} garrafones</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Vacíos recibidos -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-extrabold text-slate-700">
                            Vacíos recibidos del cliente:
                        </label>
                        <div class="flex items-center gap-2">
                            <button
                                @click="exchangeEmptyReceived = Math.max(0, exchangeEmptyReceived - 1)"
                                type="button"
                                class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 font-black text-lg active:scale-95 transition"
                            >-</button>
                            <input
                                v-model.number="exchangeEmptyReceived"
                                type="number"
                                min="0"
                                class="w-full text-center text-xl font-black py-2 rounded-xl border border-slate-200"
                            />
                            <button
                                @click="exchangeEmptyReceived = exchangeEmptyReceived + 1"
                                type="button"
                                class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 font-black text-lg active:scale-95 transition"
                            >+</button>
                        </div>
                        <p class="text-[11px] text-slate-400">Entrega únicamente respaldada por vacíos.</p>
                    </div>

                    <!-- Llenos a entregar -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-extrabold text-slate-700">
                            Llenos a entregar al cliente:
                        </label>
                        <div class="flex items-center gap-2">
                            <button
                                @click="exchangeDelivered = Math.max(0, exchangeDelivered - 1)"
                                type="button"
                                class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 font-black text-lg active:scale-95 transition"
                            >-</button>
                            <input
                                v-model.number="exchangeDelivered"
                                type="number"
                                min="0"
                                :max="Math.min(exchangeEmptyReceived, maxExchangeAvailable)"
                                class="w-full text-center text-xl font-black py-2 rounded-xl border border-slate-200 text-blue-950"
                            />
                            <button
                                @click="exchangeDelivered = Math.min(exchangeEmptyReceived, maxExchangeAvailable, exchangeDelivered + 1)"
                                type="button"
                                class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 font-black text-lg active:scale-95 transition"
                            >+</button>
                        </div>
                        <p class="text-[11px] text-slate-400">Máximo permitido: {{ Math.min(exchangeEmptyReceived, maxExchangeAvailable) }}</p>
                    </div>
                </div>

                <!-- Motivo de cancelación parcial si aplica -->
                <div v-if="exchangeCancelled > 0" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs space-y-2">
                    <div class="flex items-center justify-between font-bold text-rose-900">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-triangle-exclamation text-rose-600"></i> Cancelación parcial ({{ exchangeCancelled }} unidades no entregadas)</span>
                    </div>
                    <label class="block text-[11px] font-bold text-rose-800">Motivo de cancelación:</label>
                    <select
                        v-model="cancellationReason"
                        class="w-full rounded-xl border border-rose-300 text-xs py-2 px-3 bg-white"
                    >
                        <option value="Falta de envase vacío">Falta de envase vacío</option>
                        <option value="Cancelación por el cliente (aceptó menos)">Cancelación por el cliente (aceptó menos)</option>
                        <option value="Sin efectivo suficiente">Sin efectivo suficiente</option>
                        <option value="Envase en malas condiciones / roto">Envase en malas condiciones / roto</option>
                    </select>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 5. OPERACIÓN: BOTELLONES FIJOS (RECOLECCIÓN / DEVOLUCIÓN)                  -->
            <!-- ========================================================================= -->
            <div
                v-if="order.fixed_requested > 0 || truck.fixed_available_for_customer > 0"
                class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs space-y-4"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-tag text-purple-900 text-xl"></i>
                        <div>
                            <h3 class="font-extrabold text-purple-950 text-sm sm:text-base">Botellones Fijos (Identificados)</h3>
                            <p class="text-[11px] text-slate-500">Pertenecen a {{ customer.nickname }}. No se mezclan.</p>
                        </div>
                    </div>
                </div>

                <!-- Recolección de Fijos -->
                <div v-if="order.fixed_requested > 0" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-extrabold text-slate-700">Fijos vacíos a recoger (Llevando a planta):</label>
                        <span class="text-xs text-purple-900 font-bold bg-purple-50 px-2 py-0.5 rounded-lg border border-purple-200">
                            {{ order.fixed_requested }} solicitados
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            @click="fixedCollected = Math.max(0, fixedCollected - 1)"
                            type="button"
                            class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 font-black text-lg active:scale-95 transition"
                        >-</button>
                        <input
                            v-model.number="fixedCollected"
                            type="number"
                            min="0"
                            :max="order.fixed_requested"
                            class="w-full text-center text-xl font-black py-2 rounded-xl border border-slate-200 text-purple-950"
                        />
                        <button
                            @click="fixedCollected = Math.min(order.fixed_requested, fixedCollected + 1)"
                            type="button"
                            class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 font-black text-lg active:scale-95 transition"
                        >+</button>
                    </div>

                    <p class="text-[11px] text-slate-500 italic">
                        * La recolección NO genera cobro ahora. El cobro del refil se realizará al devolverlos llenos.
                    </p>

                    <!-- Si no se recogen todos los fijos -->
                    <div v-if="fixedCancelled > 0" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs space-y-2">
                        <span class="font-bold text-rose-900 block flex items-center gap-1.5"><i class="fa-solid fa-triangle-exclamation"></i> {{ fixedCancelled }} botellones fijos no recogidos</span>
                        <input
                            v-model="fixedCancellationReason"
                            type="text"
                            placeholder="Motivo de fijos no recogidos"
                            class="w-full rounded-xl border border-rose-300 text-xs py-2 px-3 bg-white"
                        />
                    </div>
                </div>

                <!-- Devolución de Fijos Llenados desde Camión -->
                <div v-if="truck.fixed_available_for_customer > 0" class="pt-3 border-t border-slate-100 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-800">Fijos llenos disponibles en tu camión:</span>
                        <span class="font-black text-emerald-800">{{ truck.fixed_available_for_customer }} listos</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="fixedDelivered = Math.max(0, fixedDelivered - 1)"
                            type="button"
                            class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 font-black text-lg active:scale-95 transition"
                        >-</button>
                        <input
                            v-model.number="fixedDelivered"
                            type="number"
                            min="0"
                            :max="truck.fixed_available_for_customer"
                            class="w-full text-center text-xl font-black py-2 rounded-xl border border-slate-200 text-emerald-950"
                        />
                        <button
                            @click="fixedDelivered = Math.min(truck.fixed_available_for_customer, fixedDelivered + 1)"
                            type="button"
                            class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 font-black text-lg active:scale-95 transition"
                        >+</button>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 6. COBRO Y LIQUIDACIÓN DE LA ENTREGA                                      -->
            <!-- ========================================================================= -->
            <div
                v-if="totalDeliveredBottles > 0"
                class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs space-y-4"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <h3 class="font-black text-slate-900 text-sm sm:text-base flex items-center gap-2">
                        <i class="fa-solid fa-cash-register text-slate-800"></i> Cobro de la Entrega Actual
                    </h3>
                    <span class="text-xs font-mono font-bold text-slate-500">${{ unit_price }} c/u</span>
                </div>

                <!-- Detalle de unidades entregadas -->
                <div class="p-3 rounded-xl bg-slate-50 space-y-1.5 text-xs">
                    <div class="flex justify-between text-slate-700">
                        <span>Total entregados en esta visita:</span>
                        <span class="font-bold">{{ totalDeliveredBottles }} garrafones</span>
                    </div>
                    <div v-if="promoApplied" class="flex justify-between text-blue-800 font-bold">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-gift text-blue-700"></i> 10.º botellón gratuito por promoción:</span>
                        <span>-1 garrafón ($0.00)</span>
                    </div>
                    <div class="flex justify-between text-slate-900 font-black pt-1 border-t border-slate-200 text-sm">
                        <span>Garrafones a liquidar/cargar:</span>
                        <span>{{ unitsToCharge }} u.</span>
                    </div>
                </div>

                <!-- Selección de Unidades de Contado vs Fiado -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <label class="font-extrabold text-slate-800">¿Cuántos paga en efectivo de contado?</label>
                        <span class="font-black text-blue-900 text-sm">{{ unitsPaidCash }} de {{ unitsToCharge }}</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            @click="unitsPaidCash = Math.max(0, unitsPaidCash - 1)"
                            type="button"
                            class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 font-black text-lg active:scale-95 transition"
                        >-</button>
                        <input
                            v-model.number="unitsPaidCash"
                            type="number"
                            min="0"
                            :max="unitsToCharge"
                            class="w-full text-center text-xl font-black py-2 rounded-xl border border-slate-200 text-emerald-950"
                        />
                        <button
                            @click="unitsPaidCash = Math.min(unitsToCharge, unitsPaidCash + 1)"
                            type="button"
                            class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 font-black text-lg active:scale-95 transition"
                        >+</button>
                    </div>

                    <!-- Botellones fiados y validación de límite -->
                    <div class="p-2.5 rounded-xl text-xs flex items-center justify-between" :class="isCreditExceeded ? 'bg-rose-50 border border-rose-200 text-rose-900' : 'bg-slate-50 text-slate-600'">
                        <span>Botellones que quedan fiados:</span>
                        <span class="font-black">
                            <template v-if="creditLimit === 0">Fiado deshabilitado (Límite: 0)</template>
                            <template v-else>{{ unitsToCredit }} botellones (Margen disponible: {{ availableCreditMargin }})</template>
                        </span>
                    </div>

                    <div v-if="isCreditExceeded" class="p-2.5 rounded-xl bg-rose-100 text-rose-900 text-xs font-bold flex items-center gap-1.5">
                        <i class="fa-solid fa-triangle-exclamation text-rose-700 shrink-0"></i>
                        <span v-if="creditLimit === 0">El fiado se encuentra deshabilitado (límite configurable: 0). Todo pedido debe liquidarse de contado en efectivo.</span>
                        <span v-else>No se pueden fiar {{ unitsToCredit }} botellones. El cliente solo tiene margen para fiar {{ availableCreditMargin }} botellones. Debe pagar de contado.</span>
                    </div>
                </div>

                <!-- Sección de Cobro en Efectivo -->
                <div v-if="unitsPaidCash > 0" class="space-y-3 pt-2 border-t border-slate-100">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-600">Importe exacto a cobrar:</span>
                        <span class="text-2xl font-black text-emerald-950">${{ cashAmountToPay }}</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Efectivo recibido ($):</label>
                        <input
                            v-model.number="cashTendered"
                            type="number"
                            step="0.5"
                            class="w-full text-xl font-black py-2.5 px-3 rounded-xl border border-slate-200 bg-white"
                        />
                    </div>

                    <!-- Botones rápidos de efectivo -->
                    <div class="flex gap-2 flex-wrap">
                        <button
                            @click="setQuickCash('exact')"
                            type="button"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 active:scale-95 transition"
                        >
                            Exacto (${{ cashAmountToPay }})
                        </button>
                        <button
                            v-for="amt in [50, 100, 200, 500]"
                            :key="amt"
                            v-show="amt >= cashAmountToPay"
                            @click="setQuickCash(amt)"
                            type="button"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 active:scale-95 transition"
                        >
                            ${{ amt }}
                        </button>
                    </div>

                    <!-- Cambio devuelto -->
                    <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs flex items-center justify-between">
                        <span class="font-bold text-emerald-900">Cambio a devolver al cliente:</span>
                        <span class="text-xl font-black text-emerald-950">${{ changeReturned }}</span>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 7. RESUMEN FINAL Y BOTÓN DE CONFIRMACIÓN                                  -->
            <!-- ========================================================================= -->
            <div class="pt-2">
                <button
                    @click="submitServe"
                    :disabled="serveForm.processing || isCreditExceeded || (unitsPaidCash > 0 && cashTendered < cashAmountToPay)"
                    type="button"
                    class="w-full py-4 rounded-2xl text-base font-black bg-emerald-600 text-white hover:bg-emerald-700 active:scale-98 transition shadow-lg flex items-center justify-center gap-2 disabled:opacity-50"
                >
                    <i class="fa-solid fa-check"></i> Confirmar Atención de Parada
                </button>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

