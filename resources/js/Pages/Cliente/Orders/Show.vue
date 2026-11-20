<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    RefreshCw,
    Droplets,
    Package,
    MapPin,
    Receipt,
    Wallet,
    Clock,
    CheckCircle2,
    XCircle,
    AlertTriangle,
    FileText,
    Truck,
    Check,
    ChevronDown,
    Star,
    MessageSquareQuote,
    Send,
} from 'lucide-vue-next';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    last_updated_at: {
        type: String,
        default: '',
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const isRefreshing = ref(false);
const showCancelModal = ref(false);
const showHistory = ref(false);

const cancelForm = useForm({
    reason: '',
});

const hoverRating = ref(0);
const reviewForm = useForm({
    rating: 5,
    comment: '',
});

const ratingLabels = ['', 'Malo', 'Regular', 'Bueno', 'Muy bueno', 'Excelente'];
const displayedRating = computed(() => hoverRating.value || reviewForm.rating);

const submitReview = () => {
    if (reviewForm.processing) return;
    reviewForm.post(route('cliente.orders.review.store', props.order.id), {
        preserveScroll: true,
    });
};

const refreshOrder = () => {
    isRefreshing.value = true;
    router.reload({
        preserveScroll: true,
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
};

const openCancelModal = () => {
    cancelForm.reset();
    cancelForm.clearErrors();
    showCancelModal.value = true;
};

const closeCancelModal = () => {
    showCancelModal.value = false;
};

const submitCancellation = () => {
    cancelForm.post(route('cliente.orders.cancel', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            showCancelModal.value = false;
        },
    });
};

const formatCurrency = (val) => {
    const num = Number(val) || 0;
    return num.toLocaleString('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const exchangeProgress = computed(() => props.order.operational_progress?.exchange || null);
const fixedProgress = computed(() => props.order.operational_progress?.fixed || null);

// Modalidad exclusiva actual (con soporte limpio si un pedido histórico tuviera ambos)
const orderModality = computed(() => {
    const fixedQty = Number(props.order.fixed_quantity || 0);
    const exchangeQty = Number(props.order.exchange_quantity || 0);
    const total = Number(props.order.total_bottles || 0);

    if (fixedQty > 0 && exchangeQty > 0) {
        return {
            type: 'mixed',
            badge: `${fixedQty} Fijos + ${exchangeQty} Cambio (20 L)`,
            pillClass: 'bg-white/15 text-white border-white/20',
            isFixed: false,
        };
    }
    if (fixedQty > 0) {
        return {
            type: 'fixed',
            badge: `${fixedQty} ${fixedQty === 1 ? 'botellón Fijo' : 'botellones Fijos'} (20 L)`,
            pillClass: 'bg-indigo-400/20 text-indigo-100 border-indigo-300/30',
            isFixed: true,
        };
    }
    return {
        type: 'exchange',
        badge: `${exchangeQty || total} ${(exchangeQty || total) === 1 ? 'botellón de Cambio' : 'botellones de Cambio'} (20 L)`,
        pillClass: 'bg-sky-400/20 text-sky-100 border-sky-300/30',
        isFixed: false,
    };
});

// Solo mostrar métricas operativas parciales cuando hay avance real o incidencia/custodia (no ceros innecesarios)
const activeStageHighlights = computed(() => {
    const list = [];

    if (exchangeProgress.value) {
        const e = exchangeProgress.value;
        const hasPartialOrDone = e.delivered_quantity > 0 || e.cancelled_quantity > 0;
        if (hasPartialOrDone && !props.order.is_cancelled) {
            list.push({
                id: 'exchange',
                title: e.label,
                summary: `${e.delivered_quantity} de ${e.quantity_requested} entregados`,
                cancelled: e.cancelled_quantity > 0 ? `${e.cancelled_quantity} cancelado(s)` : null,
                tone: 'sky',
            });
        }
    }

    if (fixedProgress.value) {
        const f = fixedProgress.value;
        const hasCustodyOrDelivery =
            f.collected_quantity > 0 || f.delivered_quantity > 0 || f.cancelled_quantity > 0;
        if (hasCustodyOrDelivery && !props.order.is_cancelled) {
            list.push({
                id: 'fixed',
                title: f.label,
                summary:
                    f.delivered_quantity > 0
                        ? `${f.delivered_quantity} de ${f.quantity_requested} devueltos`
                        : `${f.collected_quantity} de ${f.quantity_requested} recolectados`,
                cancelled: f.cancelled_quantity > 0 ? `${f.cancelled_quantity} cancelado(s)` : null,
                tone: 'indigo',
            });
        }
    }

    return list;
});

const statusStyle = computed(() => {
    const key = props.order.operational_progress?.overall_stage_key || props.order.status;
    if (key === 'cancelado' || props.order.is_cancelled) {
        return {
            pill: 'bg-rose-100 text-rose-900',
            label: props.order.operational_progress?.overall_stage_label || props.order.status_label,
        };
    }
    if (key === 'entregado') {
        return {
            pill: 'bg-emerald-100 text-emerald-900',
            label: props.order.operational_progress?.overall_stage_label || props.order.status_label,
        };
    }
    if (key === 'en_reparticion' || key === 'asignado_a_ruta') {
        return {
            pill: 'bg-sky-100 text-sky-900',
            label: props.order.operational_progress?.overall_stage_label || props.order.status_label,
        };
    }
    return {
        pill: 'bg-violet-100 text-violet-950',
        label: props.order.operational_progress?.overall_stage_label || props.order.status_label,
    };
});

const trackerSteps = computed(() => {
    const isFixedFlow = orderModality.value.isFixed;
    const key = props.order.operational_progress?.overall_stage_key || props.order.status;

    if (!isFixedFlow) {
        const isDelivered = key === 'entregado';
        const labels = ['Pendiente', 'Entregado'];
        return labels.map((label, idx) => ({
            number: idx + 1,
            label,
            done: isDelivered || idx < 0,
            active: !isDelivered && idx === 0,
        }));
    }

    let currentStep = 1;
    if (key === 'entregado') {
        currentStep = 4;
    } else if (key === 'lavando' || key === 'listo_para_entregar' || key === 'en_reparticion') {
        currentStep = 3;
    } else if (key === 'llevando_a_planta') {
        currentStep = 2;
    }

    const labels = ['Pendiente', 'Recolección', 'En planta', 'Devuelto'];

    return labels.map((label, idx) => ({
        number: idx + 1,
        label,
        done: currentStep > idx + 1 || key === 'entregado',
        active: currentStep === idx + 1 && key !== 'entregado',
    }));
});

const hasBilling = computed(() => {
    return (props.order.charges && props.order.charges.length > 0) ||
           (props.order.payments && props.order.payments.length > 0);
});
</script>

<template>
    <Head :title="`Pedido ${order.folio}`" />

    <AuthenticatedLayout>
        <div class="bg-[#F5F4F8] py-4 sm:py-7">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4 sm:space-y-5">
                <!-- ========================================== -->
                <!-- 1. HERO COMPACTO DEL PEDIDO                -->
                <!-- ========================================== -->
                <header
                    class="rounded-3xl bg-[#1E1545] text-white shadow-md p-5 sm:p-6 border border-[#2E2363]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-2 min-w-0">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <span class="px-2.5 py-0.5 rounded-md text-xs font-extrabold" :class="statusStyle.pill">
                                    {{ statusStyle.label }}
                                </span>
                                <span class="text-xs font-medium text-white/80 tabular-nums">
                                    {{ order.created_at }}
                                </span>
                            </div>

                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight font-mono tabular-nums break-all">
                                {{ order.folio }}
                            </h1>

                            <div class="pt-0.5">
                                <span
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-violet-100"
                                >
                                    <Package v-if="orderModality.isFixed" class="w-3.5 h-3.5 shrink-0 text-violet-200" aria-hidden="true" />
                                    <Droplets v-else class="w-3.5 h-3.5 shrink-0 text-sky-300" aria-hidden="true" />
                                    <span>{{ orderModality.badge }}</span>
                                </span>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="refreshOrder"
                            :disabled="isRefreshing"
                            aria-label="Actualizar estado del pedido"
                            title="Actualizar estado"
                            class="inline-flex items-center justify-center h-11 w-11 rounded-2xl text-white bg-white/15 hover:bg-white/25 border border-white/25 transition cursor-pointer disabled:opacity-60 shrink-0"
                        >
                            <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isRefreshing }" aria-hidden="true" />
                        </button>
                    </div>
                </header>

                <!-- Alertas Flash -->
                <div
                    v-if="flashSuccess"
                    role="status"
                    class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 flex items-center gap-3 text-sm font-semibold text-emerald-950"
                >
                    <CheckCircle2 class="w-5 h-5 text-emerald-600 shrink-0" aria-hidden="true" />
                    <span>{{ flashSuccess }}</span>
                </div>

                <div
                    v-if="flashError"
                    role="alert"
                    class="rounded-2xl bg-rose-50 border border-rose-200 p-4 flex items-center gap-3 text-sm font-semibold text-rose-950"
                >
                    <AlertTriangle class="w-5 h-5 text-rose-600 shrink-0" aria-hidden="true" />
                    <span>{{ flashError }}</span>
                </div>

                <!-- Motivo de cancelación -->
                <div
                    v-if="order.is_cancelled"
                    class="rounded-2xl bg-rose-50 border border-rose-200 p-4 sm:p-5 flex items-start gap-3"
                >
                    <div class="h-9 w-9 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center shrink-0">
                        <XCircle class="w-5 h-5" aria-hidden="true" />
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-sm font-extrabold text-rose-950">Pedido cancelado</h2>
                        <p class="text-xs sm:text-sm text-rose-900 mt-0.5 break-words">
                            {{ order.cancellation_reason || 'El pedido fue cancelado.' }}
                        </p>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- 2. SEGUIMIENTO EN VIVO + HISTORIAL         -->
                <!-- ========================================== -->
                <section
                    v-if="order.operational_progress"
                    aria-labelledby="tracking-heading"
                    class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm p-5 space-y-4"
                >
                    <div class="flex items-start gap-3">
                        <div class="h-10 w-10 rounded-xl bg-violet-100 text-[#6D28D9] flex items-center justify-center shrink-0">
                            <Truck class="w-5 h-5" aria-hidden="true" />
                        </div>
                        <div class="min-w-0">
                            <h2 id="tracking-heading" class="text-base font-extrabold text-[#1E1545]">
                                Seguimiento de entrega
                            </h2>
                            <p class="text-xs sm:text-sm text-slate-600 mt-0.5 leading-relaxed">
                                {{ order.operational_progress.explanation }}
                            </p>
                        </div>
                    </div>

                    <!-- Stepper limpio acorde al flujo real (2 pasos para Cambio, 4 para Fijos) -->
                    <ol
                        v-if="!order.is_cancelled"
                        class="grid gap-2 pt-1"
                        :class="trackerSteps.length === 2 ? 'grid-cols-2 max-w-xs mx-auto' : 'grid-cols-4 gap-1.5 sm:gap-2'"
                        aria-label="Etapas del pedido"
                    >
                        <li
                            v-for="step in trackerSteps"
                            :key="step.number"
                            class="min-w-0 flex flex-col items-center text-center gap-1.5"
                        >
                            <div
                                class="h-8 w-8 rounded-full flex items-center justify-center text-xs font-extrabold border transition"
                                :class="step.done
                                    ? 'bg-emerald-600 text-white border-emerald-600'
                                    : step.active
                                        ? 'bg-[#1E1545] text-white border-[#1E1545] ring-4 ring-violet-200'
                                        : 'bg-[#F5F4F8] text-slate-400 border-[#E9E7F0]'"
                            >
                                <Check v-if="step.done" class="w-4 h-4" aria-hidden="true" />
                                <span v-else>{{ step.number }}</span>
                            </div>
                            <span
                                class="text-[10px] sm:text-[11px] leading-tight w-full truncate"
                                :class="step.done || step.active ? 'font-bold text-[#1E1545]' : 'font-medium text-slate-400'"
                            >
                                {{ step.label }}
                            </span>
                        </li>
                    </ol>

                    <!-- Avance real solo cuando ya existe progreso parcial/custodia -->
                    <div v-if="activeStageHighlights.length > 0" class="space-y-2 pt-1">
                        <div
                            v-for="hl in activeStageHighlights"
                            :key="hl.id"
                            class="rounded-xl px-3.5 py-2.5 border flex flex-wrap items-center justify-between gap-2 text-xs"
                            :class="hl.tone === 'indigo'
                                ? 'bg-indigo-50/75 border-indigo-200 text-indigo-950'
                                : 'bg-sky-50/75 border-sky-200 text-sky-950'"
                        >
                            <span class="font-bold">{{ hl.summary }}</span>
                            <span v-if="hl.cancelled" class="font-bold text-rose-700">{{ hl.cancelled }}</span>
                        </div>
                    </div>

                    <!-- Acordeón discreto para historial de estados -->
                    <div v-if="order.history && order.history.length > 0" class="pt-2 border-t border-[#E9E7F0]">
                        <button
                            type="button"
                            @click="showHistory = !showHistory"
                            :aria-expanded="showHistory"
                            class="w-full flex items-center justify-between px-3 py-2 min-h-[40px] rounded-xl text-xs font-bold text-[#1E1545] bg-[#F5F4F8] hover:bg-[#ECE8F8] transition cursor-pointer"
                        >
                            <span class="flex items-center gap-2">
                                <Clock class="w-3.5 h-3.5 text-[#6D28D9]" aria-hidden="true" />
                                <span>Historial de movimientos ({{ order.history.length }})</span>
                            </span>
                            <ChevronDown
                                class="w-4 h-4 text-slate-500 transition-transform"
                                :class="{ 'rotate-180': showHistory }"
                                aria-hidden="true"
                            />
                        </button>

                        <ol v-show="showHistory" class="mt-3 relative border-l-2 border-[#E9E7F0] ml-3 space-y-4 pl-5 pb-1">
                            <li v-for="item in order.history" :key="item.id" class="relative">
                                <span
                                    class="absolute -left-[27px] top-1 h-3 w-3 rounded-full border-2 border-white shadow-sm"
                                    :class="item.new_status === 'cancelled'
                                        ? 'bg-rose-600'
                                        : item.new_status === 'delivered'
                                            ? 'bg-emerald-600'
                                            : 'bg-[#6D28D9]'"
                                    aria-hidden="true"
                                ></span>
                                <div class="flex flex-wrap items-center justify-between gap-1">
                                    <span class="text-xs sm:text-sm font-bold text-[#1E1545]">
                                        {{ item.new_status_label }}
                                    </span>
                                    <span class="text-[11px] text-slate-500 tabular-nums">
                                        {{ item.created_at }}
                                    </span>
                                </div>
                                <p v-if="item.reason" class="text-xs text-slate-600 mt-0.5 break-words">
                                    {{ item.reason }}
                                </p>
                            </li>
                        </ol>
                    </div>
                </section>

                <!-- ========================================== -->
                <!-- 3. ENTREGA, NOTAS Y PAGOS EN UNA TARJETA   -->
                <!-- ========================================== -->
                <section class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm p-5 space-y-4">
                    <!-- Dirección -->
                    <div v-if="order.address" class="space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="text-sm font-extrabold text-[#1E1545] flex items-center gap-2">
                                <MapPin class="w-4 h-4 text-emerald-600 shrink-0" aria-hidden="true" />
                                <span>Dirección de entrega</span>
                            </h2>
                            <span v-if="order.address.zone_label" class="px-2 py-0.5 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-800">
                                {{ order.address.zone_label }}
                            </span>
                        </div>

                        <div class="rounded-xl bg-[#F5F4F8] border border-[#E9E7F0] p-3.5 space-y-1">
                            <p class="text-sm font-extrabold text-[#1E1545] break-words">
                                {{ order.address.full_address }}
                            </p>
                            <p v-if="order.address.between_streets" class="text-xs text-slate-600 break-words">
                                {{ order.address.between_streets }}
                            </p>
                            <p v-if="order.address.reference" class="text-xs text-slate-500 break-words">
                                Ref.: {{ order.address.reference }}
                            </p>
                            <p v-if="order.address.recipient_name" class="text-xs text-slate-500 pt-1.5 border-t border-[#E9E7F0]/80 break-words">
                                Recibe: <strong class="text-slate-800">{{ order.address.recipient_name }}</strong>
                                <span v-if="order.address.recipient_phone"> • {{ order.address.recipient_phone }}</span>
                            </p>
                        </div>
                    </div>

                    <!-- Notas del pedido -->
                    <div v-if="order.notes" class="pt-3 border-t border-[#E9E7F0] space-y-1.5">
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                            <FileText class="w-3.5 h-3.5 text-amber-600" aria-hidden="true" />
                            <span>Indicaciones</span>
                        </h3>
                        <p class="text-xs sm:text-sm text-slate-700 leading-relaxed break-words">
                            {{ order.notes }}
                        </p>
                    </div>

                    <!-- Importes y pagos (solo si ya existen) -->
                    <div v-if="hasBilling" class="pt-3 border-t border-[#E9E7F0] space-y-3">
                        <h3 class="text-sm font-extrabold text-[#1E1545] flex items-center gap-2">
                            <Receipt class="w-4 h-4 text-[#6D28D9]" aria-hidden="true" />
                            <span>Importes y pagos</span>
                        </h3>

                        <div v-if="order.charges && order.charges.length > 0" class="space-y-2">
                            <div
                                v-for="charge in order.charges"
                                :key="charge.id"
                                class="rounded-xl bg-[#F5F4F8] border border-[#E9E7F0] p-3 flex flex-wrap items-center justify-between gap-2"
                            >
                                <div class="min-w-0">
                                    <span class="text-xs sm:text-sm font-bold text-[#1E1545] block">
                                        {{ charge.bottle_type_label }} × {{ charge.quantity_delivered }}
                                    </span>
                                    <span
                                        class="inline-flex mt-1 px-2 py-0.5 rounded-md text-[11px] font-bold"
                                        :class="charge.is_waived_promotion
                                            ? 'bg-amber-100 text-amber-900'
                                            : charge.status === 'paid'
                                                ? 'bg-emerald-100 text-emerald-900'
                                                : 'bg-rose-100 text-rose-900'"
                                    >
                                        {{ charge.status_label }}
                                    </span>
                                </div>
                                <span class="text-base font-black text-[#1E1545] tabular-nums shrink-0">
                                    {{ charge.is_waived_promotion ? '$0.00' : `$${formatCurrency(charge.total_amount)}` }}
                                </span>
                            </div>
                        </div>

                        <div v-if="order.payments && order.payments.length > 0" class="space-y-2">
                            <div
                                v-for="payment in order.payments"
                                :key="payment.id"
                                class="rounded-xl bg-emerald-50/70 border border-emerald-200/80 p-3 flex flex-wrap items-center justify-between gap-2"
                            >
                                <div class="flex items-center gap-2">
                                    <Wallet class="w-4 h-4 text-emerald-600 shrink-0" aria-hidden="true" />
                                    <div>
                                        <span class="text-xs sm:text-sm font-bold text-emerald-950 block tabular-nums">
                                            Pago ${{ formatCurrency(payment.amount) }} ({{ payment.bottles_count }} u.)
                                        </span>
                                        <span class="text-[11px] text-emerald-800">{{ payment.date }}</span>
                                    </div>
                                </div>
                                <span v-if="payment.change_returned > 0" class="text-xs font-semibold text-emerald-900 tabular-nums">
                                    Cambio: ${{ formatCurrency(payment.change_returned) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ========================================== -->
                <!-- 4. CALIFICACIÓN Y COMENTARIO (5 ESTRELLAS) -->
                <!-- ========================================== -->
                <section
                    v-if="order.can_review || order.review"
                    id="calificar"
                    aria-labelledby="review-heading"
                    class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm p-5 space-y-4 scroll-mt-20"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center shrink-0">
                                <Star class="w-5 h-5 fill-amber-400 text-amber-500" aria-hidden="true" />
                            </div>
                            <div>
                                <h2 id="review-heading" class="text-base font-extrabold text-[#1E1545]">
                                    {{ order.review ? 'Tu calificación del pedido' : 'Califica tu experiencia' }}
                                </h2>
                                <p class="text-xs sm:text-sm text-slate-600 mt-0.5">
                                    {{ order.review
                                        ? 'Gracias por compartir tu opinión con Puritronic.'
                                        : '¿Qué te pareció nuestro servicio? Puntúa con estrellas y deja un comentario.' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario cuando aún no se califica -->
                    <form v-if="order.can_review && !order.review" @submit.prevent="submitReview" class="space-y-4">
                        <div class="rounded-xl bg-[#F5F4F8] border border-[#E9E7F0] p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex items-center gap-1.5" role="radiogroup" aria-label="Calificación de 1 a 5 estrellas">
                                <button
                                    v-for="star in 5"
                                    :key="star"
                                    type="button"
                                    @click="reviewForm.rating = star"
                                    @mouseenter="hoverRating = star"
                                    @mouseleave="hoverRating = 0"
                                    :aria-label="`${star} ${star === 1 ? 'estrella' : 'estrellas'}`"
                                    :aria-pressed="reviewForm.rating === star"
                                    class="p-1.5 rounded-xl transition active:scale-95 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9]"
                                >
                                    <Star
                                        class="w-7 h-7 transition-colors"
                                        :class="star <= displayedRating
                                            ? 'fill-amber-400 text-amber-400'
                                            : 'text-slate-300 fill-transparent'"
                                    />
                                </button>
                            </div>

                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-white border border-[#E9E7F0] text-[#1E1545] self-start sm:self-auto">
                                <span class="tabular-nums">{{ displayedRating }}/5</span>
                                <span>· {{ ratingLabels[displayedRating] }}</span>
                            </span>
                        </div>

                        <p v-if="reviewForm.errors.rating" class="text-xs text-rose-600 font-semibold">
                            {{ reviewForm.errors.rating }}
                        </p>

                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="order-review-comment" class="text-xs font-bold text-[#1E1545]">
                                    Tu comentario *
                                </label>
                                <span class="text-[11px] font-semibold text-slate-400 tabular-nums">
                                    {{ reviewForm.comment.length }}/500
                                </span>
                            </div>
                            <textarea
                                id="order-review-comment"
                                v-model="reviewForm.comment"
                                rows="3"
                                maxlength="500"
                                required
                                placeholder="Cuéntanos sobre la calidad del agua, puntualidad o atención del repartidor..."
                                class="w-full rounded-xl border border-[#E9E7F0] bg-[#F5F4F8]/50 focus:bg-white text-base sm:text-sm text-[#1E1545] placeholder:text-slate-400 focus:border-[#1E1545] focus:ring-[#1E1545]"
                            ></textarea>
                            <p v-if="reviewForm.errors.comment" class="text-xs text-rose-600 mt-1 font-semibold">
                                {{ reviewForm.errors.comment }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="reviewForm.processing || reviewForm.comment.trim().length < 3"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 min-h-[46px] rounded-xl text-xs sm:text-sm font-extrabold text-white bg-[#1E1545] hover:bg-[#2E2363] disabled:opacity-50 transition cursor-pointer"
                        >
                            <Send class="w-4 h-4" aria-hidden="true" />
                            <span>{{ reviewForm.processing ? 'Enviando calificación...' : 'Enviar calificación' }}</span>
                        </button>
                    </form>

                    <!-- Reseña ya enviada -->
                    <div v-else-if="order.review" class="rounded-xl bg-[#F5F4F8] border border-[#E9E7F0] p-4 space-y-2.5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-1">
                                <Star
                                    v-for="s in 5"
                                    :key="s"
                                    class="w-4 h-4"
                                    :class="s <= order.review.rating ? 'fill-amber-400 text-amber-400' : 'text-slate-300'"
                                />
                                <span class="ml-1.5 text-xs font-extrabold text-[#1E1545] tabular-nums">
                                    {{ order.review.rating }}/5
                                </span>
                            </div>

                            <span
                                class="px-2.5 py-0.5 rounded-full text-[11px] font-bold"
                                :class="order.review.show_on_landing
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : 'bg-white border border-[#E9E7F0] text-slate-600'"
                            >
                                {{ order.review.show_on_landing ? 'Destacado en portada' : 'Comentario registrado' }}
                            </span>
                        </div>

                        <p class="text-xs sm:text-sm text-[#1E1545] leading-relaxed break-words">
                            “{{ order.review.comment }}”
                        </p>

                        <p class="text-[11px] text-slate-500 tabular-nums">
                            Enviado el {{ order.review.created_at }}
                        </p>
                    </div>
                </section>

                <!-- ========================================== -->
                <!-- 5. ACCIÓN DISCRETA DE CANCELACIÓN          -->
                <!-- ========================================== -->
                <div v-if="order.can_cancel" class="pt-1">
                    <button
                        type="button"
                        @click="openCancelModal"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 min-h-[48px] rounded-2xl text-xs sm:text-sm font-extrabold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"
                    >
                        <XCircle class="w-4 h-4 shrink-0" aria-hidden="true" />
                        <span>Cancelar este pedido</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal accesible de cancelación -->
        <div
            v-if="showCancelModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="cancel-modal-title"
            class="fixed inset-0 z-50 overflow-y-auto bg-[#1E1545]/50 backdrop-blur-sm flex items-center justify-center p-4"
            @keydown.escape="closeCancelModal"
        >
            <div class="w-full max-w-md max-h-[90dvh] overflow-y-auto rounded-2xl bg-white border border-[#E9E7F0] shadow-xl p-6 space-y-4">
                <div class="flex items-start gap-3">
                    <div class="h-10 w-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center shrink-0">
                        <AlertTriangle class="w-5 h-5" aria-hidden="true" />
                    </div>
                    <div>
                        <h3 id="cancel-modal-title" class="text-base font-extrabold text-[#1E1545]">
                            Cancelar pedido {{ order.folio }}
                        </h3>
                        <p class="text-xs text-slate-600 mt-0.5">
                            Esta acción no se puede deshacer. Cuéntanos brevemente el motivo.
                        </p>
                    </div>
                </div>

                <form @submit.prevent="submitCancellation" class="space-y-4">
                    <div>
                        <label for="cancel-reason" class="text-xs font-bold text-[#1E1545] block mb-1.5">
                            Motivo de cancelación *
                        </label>
                        <textarea
                            id="cancel-reason"
                            v-model="cancelForm.reason"
                            rows="3"
                            maxlength="500"
                            required
                            placeholder="Ej. Ya no habrá nadie en casa hoy"
                            class="w-full rounded-xl border-[#E9E7F0] text-base sm:text-sm text-[#1E1545] focus:border-rose-500 focus:ring-rose-500"
                        ></textarea>
                        <p v-if="cancelForm.errors.reason" class="text-xs text-rose-600 mt-1 font-medium">
                            {{ cancelForm.errors.reason }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2.5 pt-2">
                        <button
                            type="button"
                            @click="closeCancelModal"
                            class="px-4 py-2.5 min-h-[44px] rounded-xl text-xs sm:text-sm font-bold text-slate-700 bg-[#F5F4F8] hover:bg-[#ECEAEF] transition cursor-pointer"
                        >
                            Volver
                        </button>
                        <button
                            type="submit"
                            :disabled="cancelForm.processing || !cancelForm.reason.trim()"
                            class="px-4 py-2.5 min-h-[44px] rounded-xl text-xs sm:text-sm font-bold text-white bg-rose-600 hover:bg-rose-700 disabled:opacity-50 transition cursor-pointer"
                        >
                            {{ cancelForm.processing ? 'Cancelando...' : 'Sí, cancelar pedido' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
