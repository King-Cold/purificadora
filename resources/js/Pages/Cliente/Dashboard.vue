<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Droplets,
    ClipboardList,
    RefreshCw,
    CheckCircle2,
    AlertCircle,
    Gift,
    MapPin,
    ChevronRight,
    ChevronDown,
    Truck,
    Receipt,
    Wallet,
    ArrowRight,
    Package,
    UserCog,
    Sparkles,
    Star,
} from 'lucide-vue-next';

const props = defineProps({
    customer: {
        type: Object,
        default: null,
    },
    activeOrder: {
        type: Object,
        default: null,
    },
    debt: {
        type: Object,
        default: () => ({
            pending_bottles: 0,
            pending_amount: 0,
            clarification_note: 'Son botellones pendientes de pago, no envases por devolver.',
        }),
    },
    weekly_promo: {
        type: Object,
        default: null,
    },
    payment_history: {
        type: Array,
        default: () => [],
    },
    free_deliveries: {
        type: Array,
        default: () => [],
    },
    pendingReviewOrder: {
        type: Object,
        default: null,
    },
    last_updated_at: {
        type: String,
        default: '',
    },
});

const page = usePage();
const isRefreshing = ref(false);
const activeDrawer = ref(null); // null | 'account' | 'promo'

const toggleDrawer = (drawer) => {
    activeDrawer.value = activeDrawer.value === drawer ? null : drawer;
};

const refreshDashboard = () => {
    isRefreshing.value = true;
    router.reload({
        preserveScroll: true,
        onFinish: () => {
            isRefreshing.value = false;
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

const hasDebt = computed(() => (props.debt?.pending_bottles ?? 0) > 0);
const missingAddress = computed(() => !props.customer?.has_address);
const missingBottles = computed(() => (props.customer?.total_bottles ?? 0) <= 0);
const needsSetup = computed(() => !props.customer || missingAddress.value || missingBottles.value);

const greetingName = computed(() => {
    return (
        props.customer?.name ||
        page.props.auth?.user?.name ||
        props.customer?.nickname ||
        'Cliente'
    );
});

const promoPaid = computed(() => Math.min(props.weekly_promo?.paid_count ?? 0, props.weekly_promo?.required ?? 9));
const promoRequired = computed(() => props.weekly_promo?.required ?? 9);
const promoPercent = computed(() => {
    if (!props.weekly_promo) return 0;
    if (props.weekly_promo.status === 'available' || props.weekly_promo.status === 'redeemed') return 100;
    return Math.min(100, Math.round((promoPaid.value / Math.max(1, promoRequired.value)) * 100));
});

const activeOrderIsFixed = computed(() => Number(props.activeOrder?.fixed_quantity || 0) > 0);

const activeOrderTotalSteps = computed(() => (activeOrderIsFixed.value ? 4 : 2));

const activeOrderStepIndex = computed(() => {
    const key = props.activeOrder?.operational_progress?.overall_stage_key || props.activeOrder?.status;
    if (!activeOrderIsFixed.value) {
        return key === 'entregado' ? 2 : 1;
    }
    if (key === 'entregado') return 4;
    if (key === 'lavando' || key === 'listo_para_entregar' || key === 'en_reparticion') {
        return 3;
    }
    if (key === 'llevando_a_planta') return 2;
    return 1;
});

const activeOrderBadge = computed(() => {
    const key = props.activeOrder?.operational_progress?.overall_stage_key || props.activeOrder?.status;
    if (key === 'entregado') {
        return 'bg-emerald-50 text-emerald-700';
    }
    if (key === 'en_reparticion' || key === 'asignado_a_ruta' || key === 'in_route') {
        return 'bg-sky-50 text-sky-800';
    }
    if (key === 'confirmado' || key === 'confirmed' || key === 'listo_para_entregar' || key === 'lavando' || key === 'llevando_a_planta') {
        return 'bg-violet-50 text-[#6D28D9]';
    }
    return 'bg-amber-50 text-amber-800';
});
</script>

<template>
    <Head title="Inicio" />

    <AuthenticatedLayout>
        <div class="bg-[#F5F4F8] py-4 sm:py-7">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4 sm:space-y-5">
                <!-- ========================================== -->
                <!-- 1. HERO MORADO PURITRONIC + ACCIÓN RÁPIDA  -->
                <!-- ========================================== -->
                <section
                    aria-label="Bienvenida y acción principal"
                    class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#1E1545] via-[#2A1E5E] to-[#3B297D] text-white shadow-md p-5 sm:p-7 border border-[#34266E]"
                >
                    <div
                        aria-hidden="true"
                        class="pointer-events-none absolute -right-12 -top-12 w-56 h-56 rounded-full bg-violet-400/15 blur-2xl"
                    ></div>
                    <svg
                        aria-hidden="true"
                        class="pointer-events-none absolute -right-8 -bottom-10 w-52 h-52 text-white/[0.06]"
                        viewBox="0 0 200 200"
                        fill="currentColor"
                    >
                        <path d="M44.7,-64.2C57.6,-55.8,67.5,-42.3,73.1,-27.2C78.7,-12.1,80,4.5,75.4,19.4C70.8,34.3,60.3,47.5,47.1,57.4C33.9,67.3,18,73.9,1.1,72.4C-15.9,70.9,-31.8,61.3,-45.7,50.1C-59.6,38.9,-71.5,26.1,-75.9,10.7C-80.3,-4.7,-77.2,-22.7,-67.6,-36.8C-58,-50.9,-41.9,-61.1,-26.1,-68.1C-10.3,-75.1,5.2,-78.9,19.9,-75.5C34.6,-72.1,31.8,-72.6,44.7,-64.2Z" transform="translate(100 100)" />
                    </svg>

                    <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 sm:gap-6">
                        <div class="space-y-0.5 min-w-0">
                            <p class="text-xs sm:text-sm font-medium text-violet-200">
                                Hola, bienvenido
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white break-words">
                                {{ greetingName }}
                            </h1>
                        </div>

                        <div class="flex items-center gap-2.5 shrink-0">
                            <Link
                                v-if="!activeOrder"
                                :href="route('cliente.orders.create')"
                                class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 px-5 py-3 min-h-[48px] rounded-2xl text-sm font-extrabold text-[#1E1545] bg-white hover:bg-violet-50 shadow-sm transition active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#1E1545]"
                            >
                                <Droplets class="w-[18px] h-[18px] text-[#6D28D9] shrink-0" aria-hidden="true" />
                                <span>Pedir botellones</span>
                            </Link>

                            <Link
                                v-else
                                :href="route('cliente.orders.show', activeOrder.id)"
                                class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 px-5 py-3 min-h-[48px] rounded-2xl text-sm font-extrabold text-[#1E1545] bg-white hover:bg-violet-50 shadow-sm transition active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#1E1545]"
                            >
                                <Truck class="w-[18px] h-[18px] text-[#6D28D9] shrink-0" aria-hidden="true" />
                                <span>Ver pedido activo</span>
                            </Link>

                            <Link
                                :href="route('cliente.orders.index')"
                                class="hidden sm:inline-flex items-center justify-center gap-2 px-4 py-3 min-h-[48px] rounded-2xl text-xs font-bold text-white bg-white/15 hover:bg-white/25 border border-white/20 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                            >
                                <ClipboardList class="w-4 h-4 shrink-0" aria-hidden="true" />
                                <span>Mis pedidos</span>
                            </Link>

                            <button
                                type="button"
                                @click="refreshDashboard"
                                :disabled="isRefreshing"
                                aria-label="Actualizar panel"
                                title="Actualizar"
                                class="inline-flex items-center justify-center h-12 w-12 rounded-2xl text-white bg-white/15 hover:bg-white/25 border border-white/20 transition cursor-pointer disabled:opacity-60 shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                            >
                                <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isRefreshing }" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                </section>

                <!-- ========================================== -->
                <!-- 2. CHECKLIST COMPACTO DE CONFIGURACIÓN     -->
                <!-- ========================================== -->
                <section
                    v-if="needsSetup"
                    aria-label="Pasos rápidos para completar tu perfil"
                    class="rounded-2xl bg-amber-50/90 border border-amber-200 px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                >
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="h-8 w-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center shrink-0">
                            <Sparkles class="w-4 h-4" aria-hidden="true" />
                        </span>
                        <span class="text-xs sm:text-sm font-bold text-amber-950">
                            Completa tu cuenta para pedir más rápido:
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            v-if="missingAddress"
                            :href="route('cliente.addresses.create')"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 min-h-[40px] rounded-xl text-xs font-extrabold text-white bg-amber-700 hover:bg-amber-800 transition"
                        >
                            <MapPin class="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                            <span>Registrar domicilio</span>
                        </Link>
                        <Link
                            v-if="missingBottles"
                            :href="route('profile.edit')"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 min-h-[40px] rounded-xl text-xs font-extrabold text-amber-950 bg-white border border-amber-300 hover:bg-amber-100 transition"
                        >
                            <UserCog class="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                            <span>Definir botellones</span>
                        </Link>
                    </div>
                </section>

                <!-- ========================================== -->
                <!-- 3. PEDIDO ACTIVO CON TRACKER EN 4 PASOS    -->
                <!-- ========================================== -->
                <section
                    v-if="activeOrder"
                    aria-labelledby="active-order-heading"
                    class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm p-5 space-y-4"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-2 min-w-0">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold" :class="activeOrderBadge">
                                {{ activeOrder.operational_progress?.overall_stage_label || activeOrder.status_label }}
                            </span>
                            <span class="text-xs font-bold font-mono text-[#1E1545] tabular-nums">
                                {{ activeOrder.folio }}
                            </span>
                        </div>

                        <Link
                            :href="route('cliente.orders.show', activeOrder.id)"
                            class="inline-flex items-center gap-1 text-xs font-extrabold text-[#6D28D9] hover:text-[#1E1545] transition min-h-[36px] px-2 shrink-0"
                        >
                            <span>Detalle</span>
                            <ArrowRight class="w-3.5 h-3.5" aria-hidden="true" />
                        </Link>
                    </div>

                    <div>
                        <h2 id="active-order-heading" class="text-base font-extrabold text-[#1E1545]">
                            {{ activeOrder.total_bottles }} {{ activeOrder.total_bottles === 1 ? 'botellón' : 'botellones' }} en curso
                        </h2>
                        <p v-if="activeOrder.operational_progress?.explanation" class="text-xs sm:text-sm text-slate-600 mt-0.5 leading-relaxed">
                            {{ activeOrder.operational_progress.explanation }}
                        </p>
                    </div>

                    <!-- Barra de progreso tipo delivery acorde al flujo real -->
                    <div
                        class="grid gap-1.5 pt-1"
                        :class="activeOrderTotalSteps === 2 ? 'grid-cols-2' : 'grid-cols-4'"
                        aria-hidden="true"
                    >
                        <div
                            v-for="step in activeOrderTotalSteps"
                            :key="step"
                            class="h-2 rounded-full transition-colors"
                            :class="step <= activeOrderStepIndex ? 'bg-[#6D28D9]' : 'bg-[#ECEAEF]'"
                        ></div>
                    </div>
                </section>

                <!-- Banner discreto para calificar el último pedido si aún no tiene reseña -->
                <section
                    v-if="!activeOrder && pendingReviewOrder"
                    aria-label="Calificar pedido reciente"
                    class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                >
                    <div class="flex items-start gap-3">
                        <div class="h-10 w-10 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center shrink-0">
                            <Star class="w-5 h-5 fill-amber-400 text-amber-500" aria-hidden="true" />
                        </div>
                        <div>
                            <h2 class="text-sm font-extrabold text-[#1E1545]">
                                ¿Cómo estuvo tu pedido {{ pendingReviewOrder.folio }}?
                            </h2>
                            <p class="text-xs text-slate-600 mt-0.5">
                                Puntúalo con 5 estrellas y cuéntanos tu experiencia.
                            </p>
                        </div>
                    </div>

                    <Link
                        :href="`${route('cliente.orders.show', pendingReviewOrder.id)}#calificar`"
                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 min-h-[42px] rounded-xl text-xs font-extrabold text-white bg-[#1E1545] hover:bg-[#2E2363] transition shrink-0"
                    >
                        <Star class="w-3.5 h-3.5 fill-amber-400 text-amber-400" aria-hidden="true" />
                        <span>Calificar pedido</span>
                    </Link>
                </section>

                <!-- ========================================== -->
                <!-- 4. RESUMEN RÁPIDO COMPACTO (2 COLUMNAS)    -->
                <!-- ========================================== -->
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3 sm:gap-4">
                        <!-- Tile 1: Saldo -->
                        <button
                            type="button"
                            @click="toggleDrawer('account')"
                            :aria-expanded="activeDrawer === 'account'"
                            class="min-w-0 text-left rounded-2xl bg-white border p-3.5 sm:p-5 shadow-sm transition cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9]"
                            :class="activeDrawer === 'account'
                                ? 'border-[#6D28D9] ring-1 ring-[#6D28D9]/25'
                                : 'border-[#E9E7F0] hover:border-slate-300'"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span
                                    class="h-9 w-9 rounded-xl flex items-center justify-center shrink-0"
                                    :class="hasDebt ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'"
                                >
                                    <AlertCircle v-if="hasDebt" class="w-[18px] h-[18px]" aria-hidden="true" />
                                    <CheckCircle2 v-else class="w-[18px] h-[18px]" aria-hidden="true" />
                                </span>

                                <ChevronDown
                                    class="w-4 h-4 text-slate-400 transition-transform shrink-0"
                                    :class="{ 'rotate-180 text-[#6D28D9]': activeDrawer === 'account' }"
                                    aria-hidden="true"
                                />
                            </div>

                            <div class="mt-3 min-w-0">
                                <span class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-slate-500 block truncate">
                                    Estado de cuenta
                                </span>
                                <span
                                    class="text-lg sm:text-2xl font-black tabular-nums block mt-0.5 truncate"
                                    :class="hasDebt ? 'text-rose-700' : 'text-[#1E1545]'"
                                >
                                    ${{ formatCurrency(debt.pending_amount) }}
                                </span>
                                <span
                                    class="inline-flex mt-1.5 px-2 py-0.5 rounded-md text-[11px] font-semibold max-w-full truncate"
                                    :class="hasDebt ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'"
                                >
                                    {{ hasDebt ? `${debt.pending_bottles} fiado(s)` : 'Al corriente' }}
                                </span>
                            </div>
                        </button>

                        <!-- Tile 2: Promoción 9+1 -->
                        <button
                            type="button"
                            @click="toggleDrawer('promo')"
                            :aria-expanded="activeDrawer === 'promo'"
                            class="min-w-0 text-left rounded-2xl bg-white border p-3.5 sm:p-5 shadow-sm transition cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9]"
                            :class="activeDrawer === 'promo'
                                ? 'border-[#6D28D9] ring-1 ring-[#6D28D9]/25'
                                : 'border-[#E9E7F0] hover:border-slate-300'"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="h-9 w-9 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                                    <Gift class="w-[18px] h-[18px]" aria-hidden="true" />
                                </span>

                                <ChevronDown
                                    class="w-4 h-4 text-slate-400 transition-transform shrink-0"
                                    :class="{ 'rotate-180 text-[#6D28D9]': activeDrawer === 'promo' }"
                                    aria-hidden="true"
                                />
                            </div>

                            <div class="mt-3 min-w-0">
                                <span class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-slate-500 block truncate">
                                    Promo 9 + 1
                                </span>
                                <span class="text-lg sm:text-2xl font-black text-[#1E1545] tabular-nums block mt-0.5 truncate">
                                    {{ promoPaid }} <span class="text-xs sm:text-sm font-bold text-slate-400">/ {{ promoRequired }}</span>
                                </span>

                                <div
                                    class="mt-2 h-2 w-full rounded-full bg-slate-100 overflow-hidden"
                                    role="progressbar"
                                    :aria-valuenow="promoPaid"
                                    aria-valuemin="0"
                                    :aria-valuemax="promoRequired"
                                    :aria-label="`Progreso de promoción semanal: ${promoPaid} de ${promoRequired}`"
                                >
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="weekly_promo?.can_redeem ? 'bg-emerald-500' : 'bg-gradient-to-r from-[#6D28D9] to-amber-500'"
                                        :style="{ width: `${promoPercent}%` }"
                                    ></div>
                                </div>
                            </div>
                        </button>
                    </div>

                    <!-- Panel expandible: Detalle Estado de Cuenta -->
                    <section
                        v-if="activeDrawer === 'account'"
                        aria-label="Detalle de pagos y adeudos"
                        class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm p-4 sm:p-5 space-y-3.5"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5">
                            <h2 class="text-sm font-extrabold text-[#1E1545] flex items-center gap-2">
                                <Wallet class="w-4 h-4 text-[#6D28D9] shrink-0" aria-hidden="true" />
                                <span>Movimientos recientes</span>
                            </h2>
                            <p v-if="debt?.clarification_note" class="text-[11px] text-slate-500">
                                {{ debt.clarification_note }}
                            </p>
                        </div>

                        <div v-if="payment_history.length > 0" class="space-y-2">
                            <div
                                v-for="p in payment_history"
                                :key="p.id"
                                class="flex items-center justify-between gap-2 text-xs py-2.5 px-3 rounded-xl bg-[#F5F4F8] border border-[#E9E7F0]"
                            >
                                <div class="min-w-0">
                                    <span class="font-extrabold text-[#1E1545] tabular-nums block truncate">
                                        ${{ formatCurrency(p.amount) }}
                                        <span v-if="p.order_folio" class="font-mono text-slate-500 font-medium">• {{ p.order_folio }}</span>
                                    </span>
                                    <span class="text-[11px] text-slate-500">{{ p.date }}</span>
                                </div>
                                <span class="font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md tabular-nums shrink-0">
                                    {{ p.bottles_count }} u.
                                </span>
                            </div>
                        </div>
                        <p v-else class="text-xs text-slate-500">
                            Aún no hay pagos registrados recientemente.
                        </p>

                        <div v-if="free_deliveries.length > 0" class="pt-3 border-t border-[#E9E7F0] space-y-2">
                            <span class="text-xs font-bold text-amber-900 flex items-center gap-1.5">
                                <Receipt class="w-3.5 h-3.5 text-amber-600" aria-hidden="true" />
                                Canjes gratuitos ({{ free_deliveries.length }})
                            </span>
                            <div
                                v-for="item in free_deliveries"
                                :key="item.id"
                                class="flex items-center justify-between gap-2 text-xs py-2 px-3 rounded-xl bg-amber-50 border border-amber-200/80"
                            >
                                <span class="font-semibold text-amber-950 truncate">
                                    {{ item.order_folio || 'Promoción semanal' }} • {{ item.date }}
                                </span>
                                <span class="font-extrabold text-amber-900 tabular-nums shrink-0">{{ item.bottles_count }} gratis</span>
                            </div>
                        </div>
                    </section>

                    <!-- Panel expandible: Sellos Promo 9+1 -->
                    <section
                        v-if="activeDrawer === 'promo'"
                        aria-label="Detalle de promoción semanal 9+1"
                        class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm p-4 sm:p-5 space-y-3.5"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="text-sm font-extrabold text-[#1E1545]">
                                Sellos semanales (9 pagados + 1 gratis)
                            </h2>
                            <span
                                v-if="weekly_promo"
                                class="px-2 py-0.5 rounded-md text-[11px] font-bold"
                                :class="weekly_promo.can_redeem
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-amber-50 text-amber-800'"
                            >
                                {{ weekly_promo.status_label }}
                            </span>
                        </div>

                        <div class="grid grid-cols-5 sm:grid-cols-10 gap-1.5" aria-hidden="true">
                            <div
                                v-for="step in 9"
                                :key="step"
                                class="h-9 rounded-xl flex items-center justify-center text-xs font-extrabold border"
                                :class="step <= promoPaid
                                    ? 'bg-[#1E1545] text-white border-[#1E1545]'
                                    : 'bg-[#F5F4F8] text-slate-400 border-[#E9E7F0]'"
                            >
                                {{ step }}
                            </div>
                            <div
                                class="h-9 rounded-xl flex items-center justify-center text-[11px] font-black border"
                                :class="weekly_promo?.status === 'available' || weekly_promo?.status === 'redeemed'
                                    ? 'bg-emerald-600 text-white border-emerald-600'
                                    : 'bg-amber-50 text-amber-800 border-amber-200'"
                            >
                                +1
                            </div>
                        </div>

                        <p v-if="weekly_promo" class="text-xs text-slate-600">
                            {{ weekly_promo.label }}
                            <span v-if="weekly_promo.expires_at_label" class="block text-[11px] text-slate-400 mt-1 tabular-nums">
                                Vigencia hasta {{ weekly_promo.expires_at_label }}
                            </span>
                        </p>
                    </section>
                </div>

                <!-- ========================================== -->
                <!-- 5. ACCESOS RÁPIDOS DE MI CUENTA            -->
                <!-- ========================================== -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 sm:gap-4">
                    <!-- Botellones -->
                    <Link
                        :href="route('profile.edit')"
                        class="group rounded-2xl bg-white border border-[#E9E7F0] hover:border-[#6D28D9]/40 shadow-sm p-4 sm:p-5 flex items-center justify-between gap-4 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9]"
                    >
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="h-11 w-11 rounded-2xl bg-violet-100 text-[#6D28D9] flex items-center justify-center shrink-0">
                                <Package v-if="(customer?.fixed_bottles_count ?? 0) > 0" class="w-5 h-5" aria-hidden="true" />
                                <Droplets v-else class="w-5 h-5" aria-hidden="true" />
                            </div>
                            <div class="min-w-0">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">
                                    Mis botellones (20 L)
                                </span>
                                <span class="text-sm sm:text-base font-extrabold text-[#1E1545] block mt-0.5 tabular-nums">
                                    {{ customer?.total_bottles ?? 0 }} en tu cuenta
                                </span>
                                <span class="text-xs text-slate-500 block truncate">
                                    {{ (customer?.fixed_bottles_count ?? 0) > 0 ? 'Modalidad Fijos' : (customer?.exchange_bottles_count ?? 0) > 0 ? 'Modalidad De cambio' : 'Sin configurar aún' }}
                                </span>
                            </div>
                        </div>

                        <ChevronRight class="w-5 h-5 text-slate-400 group-hover:text-[#6D28D9] shrink-0 transition" aria-hidden="true" />
                    </Link>

                    <!-- Domicilio -->
                    <Link
                        :href="route('cliente.addresses.index')"
                        class="group rounded-2xl bg-white border border-[#E9E7F0] hover:border-emerald-400 shadow-sm p-4 sm:p-5 flex items-center justify-between gap-4 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
                    >
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="h-11 w-11 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                <MapPin class="w-5 h-5" aria-hidden="true" />
                            </div>
                            <div class="min-w-0">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">
                                    Domicilio de entrega
                                </span>
                                <span class="text-sm sm:text-base font-extrabold text-[#1E1545] block truncate mt-0.5">
                                    {{ customer?.has_address ? customer.address_summary : 'Sin domicilio registrado' }}
                                </span>
                                <span class="text-xs text-slate-500 block truncate">
                                    {{ customer?.zone_label || 'Toca para registrar dirección' }}
                                </span>
                            </div>
                        </div>

                        <ChevronRight class="w-5 h-5 text-slate-400 group-hover:text-emerald-700 shrink-0 transition" aria-hidden="true" />
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
