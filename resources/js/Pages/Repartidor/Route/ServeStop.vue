<script setup>
import { ref, computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { alertWarning } from '@/utils/swal';
import {
    ArrowLeft,
    MapPin,
    Phone,
    Gift,
    AlertTriangle,
    RefreshCw,
    Package,
    Truck,
    Wallet,
    Check,
    Minus,
    Plus,
    ChevronDown,
    SlidersHorizontal,
} from 'lucide-vue-next';

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
    return_tab: {
        type: String,
        default: 'pendientes',
    },
    return_zone: {
        type: String,
        default: 'all',
    },
});

const page = usePage();
const formError = computed(() => page.props.errors?.serve || page.props.errors?.debt || page.props.errors?.stop || null);
const backToRepartoUrl = computed(() =>
    window.route('repartidor.reparto', { tab: props.return_tab, zone: props.return_zone })
);

function round(val) {
    return Math.round((Number(val) + Number.EPSILON) * 100) / 100;
}

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(val || 0));
};

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
// FORMULARIO PRINCIPAL DE ATENCIÓN (UNIFICADO CON AL PASO PARA CAMBIOS)
// -------------------------------------------------------------------------
const maxExchangeAvailable = computed(() => {
    return Math.min(props.order.exchange_requested, props.truck.exchange_full_available);
});

const hasFixedSection = computed(() => {
    return Number(props.order.fixed_requested || 0) > 0 || Number(props.truck.fixed_available_for_customer || 0) > 0;
});

const isPureExchange = computed(() => {
    return props.order.exchange_requested > 0 && !hasFixedSection.value;
});

// Valores de cambio (1:1 sincronizado por defecto)
const initialExchange = props.order.exchange_requested > 0
    ? Math.min(props.order.exchange_requested, props.truck.exchange_full_available)
    : 0;

const exchangeDelivered = ref(initialExchange);
const exchangeEmptyReceived = ref(initialExchange);
const cancellationReason = ref('Falta de envase vacío');
const showAdvancedOptions = ref(false);
const emptiesCustomized = ref(false);

const decrementExchange = () => {
    if (exchangeDelivered.value > 0) {
        exchangeDelivered.value--;
        if (!emptiesCustomized.value) {
            exchangeEmptyReceived.value = exchangeDelivered.value;
        }
    }
};

const incrementExchange = () => {
    const cap = emptiesCustomized.value
        ? Math.min(exchangeEmptyReceived.value, maxExchangeAvailable.value)
        : maxExchangeAvailable.value;

    if (exchangeDelivered.value < cap) {
        exchangeDelivered.value++;
        if (!emptiesCustomized.value) {
            exchangeEmptyReceived.value = exchangeDelivered.value;
        }
    }
};

const setExchangeValue = (rawVal) => {
    let num = parseInt(rawVal, 10);
    if (isNaN(num) || num < 0) num = 0;

    const cap = emptiesCustomized.value
        ? Math.min(exchangeEmptyReceived.value, maxExchangeAvailable.value)
        : maxExchangeAvailable.value;

    exchangeDelivered.value = Math.min(num, cap);
    if (!emptiesCustomized.value) {
        exchangeEmptyReceived.value = exchangeDelivered.value;
    }
};

watch(exchangeEmptyReceived, (newVal) => {
    const validVal = Math.max(0, parseInt(newVal, 10) || 0);
    if (validVal !== exchangeDelivered.value) {
        emptiesCustomized.value = true;
    }
    if (exchangeDelivered.value > validVal) {
        exchangeDelivered.value = Math.min(validVal, maxExchangeAvailable.value);
    }
});

const exchangeCancelled = computed(() => {
    if (props.order.exchange_requested <= 0) return 0;
    return Math.max(0, props.order.exchange_requested - exchangeDelivered.value);
});

// Abrir automáticamente opciones avanzadas si la entrega ya viene con faltante vs lo solicitado
if (props.order.exchange_requested > 0 && exchangeDelivered.value < props.order.exchange_requested) {
    showAdvancedOptions.value = true;
}

// Valores de fijos (recolección / devolución si aplica)
const fixedCollected = ref(props.order.fixed_requested > 0 ? props.order.fixed_requested : 0);
const fixedCancellationReason = ref('Cliente no entregó los botellones fijos');

const fixedCancelled = computed(() => {
    if (props.order.fixed_requested <= 0) return 0;
    return Math.max(0, props.order.fixed_requested - fixedCollected.value);
});

const fixedDelivered = ref(props.truck.fixed_available_for_customer > 0 ? props.truck.fixed_available_for_customer : 0);

const totalDeliveredBottles = computed(() => {
    return Number(exchangeDelivered.value || 0) + Number(fixedDelivered.value || 0);
});

// Cobro: contado vs fiado
const unitsPaidCash = ref(0);

const promoApplied = computed(() => {
    if (totalDeliveredBottles.value <= 0) return false;
    if (props.weekly_promo.can_redeem) return true;
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

unitsPaidCash.value = unitsToCharge.value;
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
    cashTendered.value = val === 'exact' ? cashAmountToPay.value : val;
};

const serveForm = useForm({
    exchange_delivered: 0,
    exchange_empty_received: 0,
    cancellation_reason: '',
    fixed_collected: 0,
    fixed_uncollected_cancelled_reason: '',
    fixed_delivered: 0,
    units_paid_cash: 0,
    cash_tendered: 0,
    return_tab: props.return_tab,
    return_zone: props.return_zone,
});

const submitServe = () => {
    if (isCreditExceeded.value) {
        alertWarning(`No es posible fiar ${unitsToCredit.value} botellones. El margen disponible de fiado es de ${availableCreditMargin.value} botellones.`);
        return;
    }

    if (unitsPaidCash.value > 0 && Number(cashTendered.value || 0) < cashAmountToPay.value) {
        alertWarning(`El efectivo recibido ($${cashTendered.value}) es insuficiente para cubrir los $${cashAmountToPay.value} a cobrar.`);
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
    serveForm.return_tab = props.return_tab;
    serveForm.return_zone = props.return_zone;

    serveForm.post(route('repartidor.stops.process-serve', props.stop.id), {
        preserveScroll: true,
    });
};

const primaryCtaLabel = computed(() => {
    if (serveForm.processing) return 'Guardando...';
    if (isPureExchange.value) {
        if (exchangeDelivered.value === 0) return 'Registrar entrega (0 cambios)';
        return `Confirmar ${exchangeDelivered.value} ${exchangeDelivered.value === 1 ? 'cambio' : 'cambios'}`;
    }
    return totalDeliveredBottles.value > 0 ? 'Confirmar entrega' : 'Confirmar atención';
});
</script>

<template>
    <Head :title="`Entrega ${order.folio}`" />

    <AuthenticatedLayout>
        <div class="bg-[#F4F3F8] py-4 sm:py-6 pb-24">
            <div class="max-w-2xl mx-auto px-3.5 sm:px-6 space-y-4">

                <!-- ========================================================================= -->
                <!-- 1. ENCABEZADO Y NAVEGACIÓN UNIFICADA CON AL PASO                          -->
                <!-- ========================================================================= -->
                <div class="flex items-center justify-between gap-3">
                    <Link
                        :href="backToRepartoUrl"
                        class="inline-flex items-center gap-2 px-3.5 py-2.5 min-h-[44px] rounded-xl text-xs font-bold bg-white text-[#1E1545] border border-[#E9E7F0] shadow-sm hover:bg-[#F4F3F8] active:scale-95 transition"
                    >
                        <ArrowLeft class="w-4 h-4 text-[#747181]" aria-hidden="true" />
                        <span>Volver a Reparto</span>
                    </Link>

                    <div class="text-right">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#747181] block">
                            {{ order.folio }} · Pedido App
                        </span>
                        <span class="text-xs sm:text-sm font-extrabold text-[#1E1545]">
                            {{ order.delivery_zone }}
                        </span>
                    </div>
                </div>

                <!-- Error banner -->
                <div
                    v-if="formError"
                    role="alert"
                    class="p-3.5 rounded-2xl bg-[#FCECEF] border border-[#F6C8D2] text-[#E82147] text-xs font-bold flex items-center gap-2"
                >
                    <AlertTriangle class="w-4 h-4 shrink-0" aria-hidden="true" />
                    <span>{{ formError }}</span>
                </div>

                <!-- ========================================================================= -->
                <!-- 2. TARJETA CLIENTE (COMPACTA E IDÉNTICA A AL PASO)                        -->
                <!-- ========================================================================= -->
                <section class="bg-white rounded-2xl p-4 sm:p-5 border border-[#E9E7F0] shadow-sm space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-[#747181] block">
                                Cliente
                            </span>
                            <h1 class="text-lg sm:text-xl font-extrabold text-[#1E1545] leading-tight break-words">
                                {{ customer.nickname }}
                            </h1>
                            <p v-if="customer.name && customer.name !== customer.nickname" class="text-xs text-[#747181] font-medium mt-0.5 truncate">
                                {{ customer.name }}
                            </p>
                        </div>

                        <a
                            v-if="customer.has_phone"
                            :href="`tel:${customer.phone}`"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 min-h-[36px] rounded-xl bg-[#E8F2EC] text-[#26845B] border border-[#C5E4D3] text-xs font-bold hover:bg-[#D8ECDF] transition shrink-0"
                        >
                            <Phone class="w-3.5 h-3.5" aria-hidden="true" />
                            <span>Llamar</span>
                        </a>
                    </div>

                    <!-- Dirección + Badges en una sola banda limpia -->
                    <div class="p-3 rounded-xl bg-[#F4F3F8]/75 border border-[#E9E7F0] text-xs space-y-2">
                        <div class="flex items-start gap-2">
                            <MapPin class="w-4 h-4 text-[#0071BA] shrink-0 mt-0.5" aria-hidden="true" />
                            <div class="min-w-0 space-y-1">
                                <p class="font-semibold text-[#1E1545] break-words">{{ order.address_summary }}</p>
                                <p v-if="order.reference" class="text-[11px] text-[#9A670E] bg-[#FEF5E6] px-2 py-0.5 rounded-md inline-block font-medium">
                                    Ref.: {{ order.reference }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5 pt-1">
                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-[#0071BA] bg-[#E9F3F8] px-2.5 py-0.5 rounded-full">
                                <MapPin class="w-3 h-3" aria-hidden="true" />
                                {{ order.delivery_zone }}
                            </span>

                            <span
                                v-if="order.exchange_requested > 0"
                                class="inline-flex items-center gap-1 text-[11px] font-bold text-[#26845B] bg-[#E8F2EC] px-2.5 py-0.5 rounded-full"
                            >
                                <RefreshCw class="w-3 h-3" aria-hidden="true" />
                                Cambios ({{ order.exchange_requested }})
                            </span>

                            <span
                                class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full"
                                :class="promoApplied ? 'bg-[#E8F2EC] text-[#26845B]' : 'bg-white border border-[#E9E7F0] text-[#747181]'"
                            >
                                <Gift class="w-3 h-3 text-[#0071BA]" aria-hidden="true" />
                                <template v-if="promoApplied">10.º gratis 9+1</template>
                                <template v-else>Promo 9+1: {{ weekly_promo.paid_count }}/{{ weekly_promo.required }}</template>
                            </span>
                        </div>
                    </div>

                    <!-- Banner de deuda anterior solo si el cliente debe algo -->
                    <div
                        v-if="pending_debt.bottles > 0"
                        class="p-3 rounded-xl bg-[#FEF5E6] border border-[#F5DFB5] text-xs flex items-center justify-between gap-3"
                    >
                        <div class="min-w-0">
                            <span class="font-bold text-[#9A670E] inline-flex items-center gap-1.5">
                                <AlertTriangle class="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                                <span>Saldo anterior: {{ pending_debt.bottles }} u. ({{ formatCurrency(pending_debt.amount) }})</span>
                            </span>
                        </div>
                        <button
                            @click="showDebtSettlement = !showDebtSettlement"
                            type="button"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold bg-[#D99A27] text-white hover:bg-[#C0851B] transition shrink-0 inline-flex items-center gap-1 cursor-pointer"
                        >
                            <span>{{ showDebtSettlement ? 'Ocultar' : 'Cobrar deuda' }}</span>
                            <ChevronDown class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': showDebtSettlement }" />
                        </button>
                    </div>
                </section>

                <!-- LIQUIDACIÓN DE ADEUDO ANTERIOR (EXPANDIBLE) -->
                <section
                    v-if="showDebtSettlement && pending_debt.bottles > 0"
                    class="bg-white rounded-2xl p-4 sm:p-5 border border-[#F5DFB5] shadow-sm space-y-3.5"
                >
                    <div class="flex items-center justify-between border-b border-[#E9E7F0] pb-2.5">
                        <h2 class="font-extrabold text-[#1E1545] text-sm flex items-center gap-2">
                            <Wallet class="w-4 h-4 text-[#9A670E]" aria-hidden="true" />
                            <span>Liquidar adeudo anterior</span>
                        </h2>
                        <span class="text-xs text-[#9A670E] font-bold">Máx: {{ pending_debt.bottles }} u.</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <label class="block font-bold text-[#292638] mb-1.5">Botellones a liquidar:</label>
                            <div class="flex items-center gap-2">
                                <button
                                    @click="debtBottlesCount = Math.max(1, debtBottlesCount - 1)"
                                    type="button"
                                    class="w-10 h-10 rounded-xl bg-[#F4F3F8] border border-[#E9E7F0] font-bold flex items-center justify-center cursor-pointer"
                                >
                                    <Minus class="w-4 h-4" />
                                </button>
                                <input
                                    v-model.number="debtBottlesCount"
                                    type="number"
                                    :min="1"
                                    :max="pending_debt.bottles"
                                    class="w-20 text-center font-black text-base py-2 rounded-xl border border-[#E9E7F0] bg-[#F4F3F8]/60 text-[#1E1545] tabular-nums"
                                />
                                <button
                                    @click="debtBottlesCount = Math.min(pending_debt.bottles, debtBottlesCount + 1)"
                                    type="button"
                                    class="w-10 h-10 rounded-xl bg-[#1E1545] text-white font-bold flex items-center justify-center cursor-pointer"
                                >
                                    <Plus class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-[#292638] mb-1">Importe exacto:</label>
                            <p class="text-2xl font-black text-[#1E1545] tabular-nums">{{ formatCurrency(debtExactAmount) }}</p>
                        </div>

                        <div class="sm:col-span-2 space-y-1">
                            <label class="block font-bold text-[#292638]">Efectivo recibido ($):</label>
                            <input
                                v-model.number="debtCashTendered"
                                type="number"
                                step="0.5"
                                class="w-full text-base sm:text-lg font-black py-2.5 px-3 rounded-xl border border-[#E9E7F0] bg-white text-[#1E1545] tabular-nums"
                            />
                            <p v-if="debtChangeReturned > 0" class="text-[#26845B] font-bold text-xs pt-1">
                                Cambio a devolver: {{ formatCurrency(debtChangeReturned) }}
                            </p>
                        </div>
                    </div>

                    <button
                        @click="submitSettleDebt"
                        :disabled="debtForm.processing || debtCashTendered < debtExactAmount"
                        type="button"
                        class="w-full py-3 min-h-[46px] rounded-xl text-xs sm:text-sm font-extrabold bg-[#D99A27] text-white hover:bg-[#C0851B] transition disabled:opacity-50 cursor-pointer"
                    >
                        Confirmar cobro de {{ formatCurrency(debtExactAmount) }}
                    </button>
                </section>

                <!-- ========================================================================= -->
                <!-- 3. BOTELLONES DE CAMBIO (ESTRUCTURA UNIFICADA 1:1 CON AL PASO)            -->
                <!-- ========================================================================= -->
                <section
                    v-if="order.exchange_requested > 0"
                    class="bg-white rounded-2xl p-4 sm:p-5 border border-[#E9E7F0] shadow-sm space-y-4"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2.5 border-b border-[#E9E7F0] pb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-[#E9F3F8] text-[#0071BA] flex items-center justify-center shrink-0">
                                <RefreshCw class="w-5 h-5" aria-hidden="true" />
                            </div>
                            <div>
                                <h2 class="font-extrabold text-[#1E1545] text-sm sm:text-base">
                                    Botellones de Cambio (20 L)
                                </h2>
                                <p class="text-[11px] text-[#747181]">
                                    Intercambio lleno por vacío en domicilio
                                </p>
                            </div>
                        </div>

                        <span
                            class="px-3 py-1 rounded-full text-[11px] font-bold inline-flex items-center gap-1 tabular-nums"
                            :class="truck.exchange_full_available > 0 ? 'bg-[#E8F2EC] text-[#26845B]' : 'bg-[#FCECEF] text-[#E82147]'"
                        >
                            <Truck class="w-3.5 h-3.5" aria-hidden="true" />
                            <span>{{ truck.exchange_full_available }} llenos en camión</span>
                        </span>
                    </div>

                    <!-- Aviso si camión tiene 0 cambios llenos -->
                    <div
                        v-if="truck.exchange_full_available <= 0"
                        class="p-4 rounded-xl bg-[#FEF5E6] border border-[#F5DFB5] text-[#9A670E] text-xs space-y-1.5"
                    >
                        <div class="flex items-center gap-2 font-extrabold text-sm">
                            <AlertTriangle class="w-4 h-4 shrink-0" aria-hidden="true" />
                            <span>No tienes cambios llenos disponibles</span>
                        </div>
                        <p class="leading-relaxed">
                            Tu camión no cuenta con botellones de cambio llenos para entregar en este momento.
                        </p>
                    </div>

                    <!-- Stepper único centrado idéntico a Al paso -->
                    <div v-else class="space-y-4">
                        <div class="p-4 rounded-xl bg-[#F4F3F8]/70 border border-[#E9E7F0] space-y-2.5">
                            <label class="block text-center text-xs font-bold text-[#292638]">
                                Cantidad de cambios a entregar
                            </label>

                            <div class="flex items-center justify-center gap-3.5">
                                <button
                                    type="button"
                                    @click="decrementExchange"
                                    :disabled="exchangeDelivered <= 0"
                                    class="w-12 h-12 rounded-xl bg-white hover:bg-[#F4F3F8] border border-[#E9E7F0] text-[#1E1545] font-black disabled:opacity-35 transition flex items-center justify-center cursor-pointer"
                                    aria-label="Restar cambio"
                                >
                                    <Minus class="w-5 h-5" />
                                </button>

                                <div class="text-center">
                                    <input
                                        :value="exchangeDelivered"
                                        @input="setExchangeValue($event.target.value)"
                                        type="number"
                                        min="0"
                                        :max="emptiesCustomized ? Math.min(exchangeEmptyReceived, maxExchangeAvailable) : maxExchangeAvailable"
                                        class="w-24 text-center text-2xl font-black text-[#1E1545] bg-white border border-[#E9E7F0] rounded-xl py-2 tabular-nums focus:border-[#1E1545]"
                                    />
                                    <span class="block text-[11px] font-bold text-[#747181] mt-1">
                                        {{ exchangeDelivered === 1 ? 'Cambio' : 'Cambios' }}
                                    </span>
                                </div>

                                <button
                                    type="button"
                                    @click="incrementExchange"
                                    :disabled="exchangeDelivered >= (emptiesCustomized ? Math.min(exchangeEmptyReceived, maxExchangeAvailable) : maxExchangeAvailable)"
                                    class="w-12 h-12 rounded-xl bg-[#1E1545] hover:bg-[#2B1F60] text-white font-black disabled:opacity-35 transition flex items-center justify-center cursor-pointer"
                                    aria-label="Sumar cambio"
                                >
                                    <Plus class="w-5 h-5" />
                                </button>
                            </div>

                            <p class="text-[11px] text-center text-[#747181]">
                                Solicitados por el cliente: {{ order.exchange_requested }} · Máximo en camión: {{ truck.exchange_full_available }}
                            </p>
                        </div>

                        <!-- Resumen directo idéntico a Al paso -->
                        <div class="p-3.5 rounded-xl bg-[#E9F3F8]/70 border border-[#CFE4F2] space-y-1.5 text-xs">
                            <div class="font-bold text-[#1E1545] text-center sm:text-left">
                                Entregas {{ exchangeDelivered }} {{ exchangeDelivered === 1 ? 'lleno' : 'llenos' }} · Recibes {{ exchangeEmptyReceived }} {{ exchangeEmptyReceived === 1 ? 'vacío' : 'vacíos' }}
                            </div>
                            <div v-if="promoApplied" class="flex items-center justify-between text-[#26845B] font-bold">
                                <span>Promoción semanal 9+1 (10.º gratis)</span>
                                <span>-1 u. ($0.00)</span>
                            </div>
                            <div class="flex items-center justify-between pt-1.5 border-t border-[#CFE4F2] text-sm font-extrabold text-[#1E1545]">
                                <span>
                                    <template v-if="unitsToCredit > 0">
                                        Efectivo ({{ unitsPaidCash }} u.) · Fiado ({{ unitsToCredit }} u.)
                                    </template>
                                    <template v-else>
                                        Total ({{ unitsPaidCash }} × {{ formatCurrency(unit_price) }})
                                    </template>
                                </span>
                                <span class="text-base font-black text-[#0071BA] tabular-nums">{{ formatCurrency(cashAmountToPay) }} MXN</span>
                            </div>
                        </div>

                        <!-- Motivo si entrega parcial vs lo pedido -->
                        <div v-if="exchangeCancelled > 0" class="p-3.5 rounded-xl bg-[#FCECEF] border border-[#F6C8D2] text-xs space-y-2">
                            <div class="flex items-center gap-1.5 font-bold text-[#E82147]">
                                <AlertTriangle class="w-4 h-4 shrink-0" aria-hidden="true" />
                                <span>Diferencia sin entregar ({{ exchangeCancelled }} {{ exchangeCancelled === 1 ? 'unidad' : 'unidades' }})</span>
                            </div>
                            <label class="block text-[11px] font-bold text-[#292638]">Motivo:</label>
                            <select
                                v-model="cancellationReason"
                                class="w-full rounded-xl border border-[#F6C8D2] text-base sm:text-xs py-2 px-3 bg-white text-[#292638]"
                            >
                                <option value="Falta de envase vacío">Falta de envase vacío</option>
                                <option value="Cancelación por el cliente (aceptó menos)">Cancelación por el cliente (aceptó menos)</option>
                                <option value="Sin efectivo suficiente">Sin efectivo suficiente</option>
                                <option value="Envase en malas condiciones / roto">Envase en malas condiciones / roto</option>
                            </select>
                        </div>

                        <!-- Acordeón discreto para casos especiales (vacíos distintos, fiado o cálculo de cambio) -->
                        <div>
                            <button
                                type="button"
                                @click="showAdvancedOptions = !showAdvancedOptions"
                                class="w-full py-2 px-3 rounded-xl text-xs font-bold text-[#747181] hover:text-[#1E1545] hover:bg-[#F4F3F8] transition flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <SlidersHorizontal class="w-3.5 h-3.5" aria-hidden="true" />
                                <span>{{ showAdvancedOptions ? 'Ocultar ajustes de envases y pago' : 'Ajustar vacíos recibidos, fiado o cambio' }}</span>
                                <ChevronDown class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': showAdvancedOptions }" />
                            </button>

                            <div v-if="showAdvancedOptions" class="mt-3 p-4 rounded-xl bg-[#F4F3F8]/70 border border-[#E9E7F0] space-y-4 text-xs">
                                <!-- Vacíos recibidos por separado -->
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <label class="font-bold text-[#292638]">Vacíos recibidos del cliente:</label>
                                        <span class="font-bold text-[#747181] tabular-nums">{{ exchangeEmptyReceived }} u.</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            @click="exchangeEmptyReceived = Math.max(0, exchangeEmptyReceived - 1)"
                                            type="button"
                                            class="w-10 h-10 rounded-xl bg-white border border-[#E9E7F0] text-[#1E1545] font-bold flex items-center justify-center cursor-pointer"
                                        >
                                            <Minus class="w-4 h-4" />
                                        </button>
                                        <input
                                            v-model.number="exchangeEmptyReceived"
                                            type="number"
                                            min="0"
                                            class="w-full text-center text-lg font-black py-1.5 rounded-xl border border-[#E9E7F0] bg-white text-[#1E1545] tabular-nums"
                                        />
                                        <button
                                            @click="exchangeEmptyReceived = exchangeEmptyReceived + 1"
                                            type="button"
                                            class="w-10 h-10 rounded-xl bg-[#1E1545] text-white font-bold flex items-center justify-center cursor-pointer"
                                        >
                                            <Plus class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Contado vs Fiado (solo si entrega algo) -->
                                <div v-if="totalDeliveredBottles > 0 && !hasFixedSection" class="space-y-3 pt-3 border-t border-[#E9E7F0]">
                                    <div class="flex items-center justify-between">
                                        <label class="font-bold text-[#292638]">Unidades pagadas de contado:</label>
                                        <span class="font-extrabold text-[#1E1545] tabular-nums">{{ unitsPaidCash }} de {{ unitsToCharge }}</span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <button
                                            @click="unitsPaidCash = Math.max(0, unitsPaidCash - 1)"
                                            type="button"
                                            class="w-10 h-10 rounded-xl bg-white border border-[#E9E7F0] text-[#1E1545] font-bold flex items-center justify-center cursor-pointer"
                                        >
                                            <Minus class="w-4 h-4" />
                                        </button>
                                        <input
                                            v-model.number="unitsPaidCash"
                                            type="number"
                                            min="0"
                                            :max="unitsToCharge"
                                            class="w-full text-center text-lg font-black py-1.5 rounded-xl border border-[#E9E7F0] bg-white text-[#26845B] tabular-nums"
                                        />
                                        <button
                                            @click="unitsPaidCash = Math.min(unitsToCharge, unitsPaidCash + 1)"
                                            type="button"
                                            class="w-10 h-10 rounded-xl bg-[#1E1545] text-white font-bold flex items-center justify-center cursor-pointer"
                                        >
                                            <Plus class="w-4 h-4" />
                                        </button>
                                    </div>

                                    <div class="flex items-center justify-between text-[11px] text-[#747181]">
                                        <span>Quedan fiados: <strong class="text-[#292638]">{{ unitsToCredit }} u.</strong></span>
                                        <span>
                                            <template v-if="creditLimit === 0">Fiado deshabilitado</template>
                                            <template v-else>Margen: {{ availableCreditMargin }} u.</template>
                                        </span>
                                    </div>

                                    <div v-if="isCreditExceeded" class="p-2.5 rounded-xl bg-[#FCECEF] text-[#E82147] font-bold">
                                        <template v-if="creditLimit === 0">El fiado está deshabilitado. Cobra todas las unidades en efectivo.</template>
                                        <template v-else>Solo puedes fiar hasta {{ availableCreditMargin }} botellones.</template>
                                    </div>

                                    <!-- Efectivo recibido y cambio -->
                                    <div v-if="unitsPaidCash > 0" class="space-y-2 pt-2 border-t border-[#E9E7F0]">
                                        <label class="block font-bold text-[#292638]">Efectivo recibido ($):</label>
                                        <input
                                            v-model.number="cashTendered"
                                            type="number"
                                            step="0.5"
                                            class="w-full text-base font-black py-2 px-3 rounded-xl border border-[#E9E7F0] bg-white text-[#1E1545] tabular-nums"
                                        />
                                        <div class="flex gap-1.5 flex-wrap">
                                            <button
                                                @click="setQuickCash('exact')"
                                                type="button"
                                                class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-white border border-[#E9E7F0] text-[#1E1545] cursor-pointer"
                                            >
                                                Exacto ({{ formatCurrency(cashAmountToPay) }})
                                            </button>
                                            <button
                                                v-for="amt in [50, 100, 200, 500]"
                                                :key="amt"
                                                v-show="amt >= cashAmountToPay"
                                                @click="setQuickCash(amt)"
                                                type="button"
                                                class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-white border border-[#E9E7F0] text-[#1E1545] cursor-pointer"
                                            >
                                                ${{ amt }}
                                            </button>
                                        </div>
                                        <div v-if="changeReturned > 0" class="p-2.5 rounded-xl bg-[#E8F2EC] text-[#26845B] font-bold flex items-center justify-between">
                                            <span>Cambio a devolver:</span>
                                            <span class="tabular-nums">{{ formatCurrency(changeReturned) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botón de confirmación unificado (esmeralda Puritronic idéntico a Al paso) -->
                        <button
                            v-if="isPureExchange"
                            @click="submitServe"
                            :disabled="serveForm.processing || isCreditExceeded || (unitsPaidCash > 0 && cashTendered < cashAmountToPay)"
                            type="button"
                            class="w-full py-4 min-h-[54px] rounded-2xl text-base font-extrabold bg-[#26845B] hover:bg-[#1F6D4B] text-white active:scale-[0.99] transition shadow-sm flex items-center justify-center gap-2 disabled:opacity-45 cursor-pointer"
                        >
                            <Check class="w-5 h-5" aria-hidden="true" />
                            <span>{{ primaryCtaLabel }}</span>
                        </button>
                    </div>
                </section>

                <!-- ========================================================================= -->
                <!-- 4. OPERACIÓN FIJOS + COBRO (SOLO SI EL PEDIDO INCLUYE FIJOS)              -->
                <!-- ========================================================================= -->
                <template v-if="hasFixedSection">
                    <section class="bg-white rounded-2xl p-4 sm:p-5 border border-[#E9E7F0] shadow-sm space-y-4">
                        <div class="flex items-center gap-3 border-b border-[#E9E7F0] pb-3">
                            <div class="w-10 h-10 rounded-xl bg-[#EEEAF7] text-[#1E1545] flex items-center justify-center shrink-0">
                                <Package class="w-5 h-5" aria-hidden="true" />
                            </div>
                            <div>
                                <h2 class="font-extrabold text-[#1E1545] text-sm sm:text-base">Botellones Fijos (Identificados)</h2>
                                <p class="text-[11px] text-[#747181]">Pertenecen a {{ customer.nickname }}.</p>
                            </div>
                        </div>

                        <div v-if="order.fixed_requested > 0" class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-bold text-[#292638]">Fijos vacíos a recoger:</label>
                                <span class="text-xs text-[#1E1545] font-bold bg-[#EEEAF7] px-2.5 py-0.5 rounded-full">
                                    {{ order.fixed_requested }} solicitados
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    @click="fixedCollected = Math.max(0, fixedCollected - 1)"
                                    type="button"
                                    class="w-11 h-11 rounded-xl bg-[#F4F3F8] border border-[#E9E7F0] text-[#1E1545] font-black flex items-center justify-center cursor-pointer"
                                >
                                    <Minus class="w-4 h-4" />
                                </button>
                                <input
                                    v-model.number="fixedCollected"
                                    type="number"
                                    min="0"
                                    :max="order.fixed_requested"
                                    class="w-full text-center text-xl font-black py-2 rounded-xl border border-[#E9E7F0] bg-[#F4F3F8]/60 text-[#1E1545] tabular-nums"
                                />
                                <button
                                    @click="fixedCollected = Math.min(order.fixed_requested, fixedCollected + 1)"
                                    type="button"
                                    class="w-11 h-11 rounded-xl bg-[#1E1545] text-white font-black flex items-center justify-center cursor-pointer"
                                >
                                    <Plus class="w-4 h-4" />
                                </button>
                            </div>

                            <div v-if="fixedCancelled > 0" class="p-3 rounded-xl bg-[#FCECEF] border border-[#F6C8D2] text-xs space-y-2">
                                <span class="font-bold text-[#E82147]">{{ fixedCancelled }} fijos no recogidos</span>
                                <input
                                    v-model="fixedCancellationReason"
                                    type="text"
                                    placeholder="Motivo de fijos no recogidos"
                                    class="w-full rounded-xl border border-[#F6C8D2] text-base sm:text-xs py-2 px-3 bg-white"
                                />
                            </div>
                        </div>

                        <div v-if="truck.fixed_available_for_customer > 0" class="pt-3 border-t border-[#E9E7F0] space-y-2.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-[#292638]">Fijos llenos disponibles en camión:</span>
                                <span class="font-bold text-[#26845B]">{{ truck.fixed_available_for_customer }} listos</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    @click="fixedDelivered = Math.max(0, fixedDelivered - 1)"
                                    type="button"
                                    class="w-11 h-11 rounded-xl bg-[#F4F3F8] border border-[#E9E7F0] text-[#1E1545] font-black flex items-center justify-center cursor-pointer"
                                >
                                    <Minus class="w-4 h-4" />
                                </button>
                                <input
                                    v-model.number="fixedDelivered"
                                    type="number"
                                    min="0"
                                    :max="truck.fixed_available_for_customer"
                                    class="w-full text-center text-xl font-black py-2 rounded-xl border border-[#E9E7F0] bg-[#F4F3F8]/60 text-[#1E1545] tabular-nums"
                                />
                                <button
                                    @click="fixedDelivered = Math.min(truck.fixed_available_for_customer, fixedDelivered + 1)"
                                    type="button"
                                    class="w-11 h-11 rounded-xl bg-[#1E1545] text-white font-black flex items-center justify-center cursor-pointer"
                                >
                                    <Plus class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </section>

                    <section v-if="totalDeliveredBottles > 0" class="bg-white rounded-2xl p-4 sm:p-5 border border-[#E9E7F0] shadow-sm space-y-3 text-xs">
                        <div class="flex items-center justify-between border-b border-[#E9E7F0] pb-2">
                            <span class="font-extrabold text-[#1E1545] text-sm">Cobro ({{ unitsToCharge }} u.)</span>
                            <span class="font-black text-[#0071BA] text-base tabular-nums">{{ formatCurrency(cashAmountToPay) }} MXN</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-[#292638]">Unidades pagadas de contado:</span>
                            <div class="flex items-center gap-2">
                                <button @click="unitsPaidCash = Math.max(0, unitsPaidCash - 1)" type="button" class="w-8 h-8 rounded-lg bg-[#F4F3F8] font-bold cursor-pointer">-</button>
                                <span class="font-black tabular-nums">{{ unitsPaidCash }} / {{ unitsToCharge }}</span>
                                <button @click="unitsPaidCash = Math.min(unitsToCharge, unitsPaidCash + 1)" type="button" class="w-8 h-8 rounded-lg bg-[#1E1545] text-white font-bold cursor-pointer">+</button>
                            </div>
                        </div>
                        <div v-if="isCreditExceeded" class="p-2.5 rounded-xl bg-[#FCECEF] text-[#E82147] font-bold">
                            Límite de fiado excedido (Margen: {{ availableCreditMargin }} u.).
                        </div>
                    </section>

                    <button
                        @click="submitServe"
                        :disabled="serveForm.processing || isCreditExceeded || (unitsPaidCash > 0 && cashTendered < cashAmountToPay)"
                        type="button"
                        class="w-full py-4 min-h-[54px] rounded-2xl text-base font-extrabold bg-[#26845B] hover:bg-[#1F6D4B] text-white active:scale-[0.99] transition shadow-sm flex items-center justify-center gap-2 disabled:opacity-45 cursor-pointer"
                    >
                        <Check class="w-5 h-5" aria-hidden="true" />
                        <span>{{ primaryCtaLabel }}</span>
                    </button>
                </template>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
