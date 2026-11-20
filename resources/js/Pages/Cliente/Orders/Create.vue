<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Droplets,
    Package,
    MapPin,
    Clock,
    AlertTriangle,
    CheckCircle2,
    Minus,
    Plus,
    Truck,
    UserCog,
} from 'lucide-vue-next';

const props = defineProps({
    customer: {
        type: Object,
        default: null,
    },
    address: {
        type: Object,
        default: null,
    },
    activeOrder: {
        type: Object,
        default: null,
    },
    scheduleStatus: {
        type: Object,
        required: true,
    },
    scheduleSummary: {
        type: [Object, String],
        default: '',
    },
    blocks: {
        type: Object,
        required: true,
    },
    canOrder: {
        type: Boolean,
        default: false,
    },
    pricing: {
        type: Object,
        default: () => ({
            unit_price: 15,
            has_free_bottle_promo: false,
        }),
    },
});

const registeredFixed = computed(() => props.customer?.fixed_bottles_count ?? 0);
const registeredExchange = computed(() => props.customer?.exchange_bottles_count ?? 0);

const activeModality = computed(() => {
    if (registeredFixed.value > 0) return 'fixed';
    if (registeredExchange.value > 0) return 'exchange';
    return null;
});

const maxQuantity = computed(() => {
    if (activeModality.value === 'fixed') return registeredFixed.value;
    if (activeModality.value === 'exchange') return registeredExchange.value;
    return 0;
});

const quickQuantities = computed(() => {
    const max = maxQuantity.value;
    if (max <= 0) return [];
    const presets = [1, 2, 3, 5].filter((n) => n < max);
    if (!presets.includes(max)) {
        presets.push(max);
    }
    return presets;
});

const formattedSchedule = computed(() => {
    const raw = props.scheduleSummary;
    if (!raw) return null;

    if (typeof raw === 'string') {
        return {
            days: 'Horario de recepción',
            intervals: raw,
            breakLabel: null,
        };
    }

    const days = raw.days_applied || 'Lunes a Domingo';
    const intervalsList = Array.isArray(raw.order_intervals) && raw.order_intervals.length > 0
        ? raw.order_intervals
        : [raw.morning_attention, raw.afternoon_attention].filter(Boolean);

    return {
        days,
        intervals: intervalsList.join(' y '),
        breakLabel: raw.break_period
            ? `Descanso presencial: ${raw.break_period} (puedes pedir en línea)`
            : null,
    };
});

const formattedScheduleInline = computed(() => {
    if (!formattedSchedule.value) return '';
    return [formattedSchedule.value.days, formattedSchedule.value.intervals].filter(Boolean).join(' • ');
});

const form = useForm({
    fixed_quantity: registeredFixed.value > 0 ? 1 : 0,
    exchange_quantity: registeredFixed.value === 0 && registeredExchange.value > 0 ? 1 : 0,
    notes: '',
});

const currentQuantity = computed(() => {
    return activeModality.value === 'fixed' ? form.fixed_quantity : form.exchange_quantity;
});

const totalSelectedBottles = computed(() => {
    return (Number(form.fixed_quantity) || 0) + (Number(form.exchange_quantity) || 0);
});

const unitPrice = computed(() => Number(props.pricing?.unit_price ?? 15));
const freePromoBottles = computed(() => {
    return props.pricing?.has_free_bottle_promo && totalSelectedBottles.value >= 1 ? 1 : 0;
});
const subtotalAmount = computed(() => totalSelectedBottles.value * unitPrice.value);
const discountAmount = computed(() => freePromoBottles.value * unitPrice.value);
const totalAmountToPay = computed(() => Math.max(0, subtotalAmount.value - discountAmount.value));

const formatCurrency = (val) => {
    const num = Number(val) || 0;
    return num.toLocaleString('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const isSubmitDisabled = computed(() => {
    return !props.canOrder || form.processing || totalSelectedBottles.value <= 0;
});

const setQuantity = (qty) => {
    if (activeModality.value === 'fixed') {
        form.fixed_quantity = Math.min(Math.max(1, qty), registeredFixed.value);
        form.exchange_quantity = 0;
    } else if (activeModality.value === 'exchange') {
        form.exchange_quantity = Math.min(Math.max(1, qty), registeredExchange.value);
        form.fixed_quantity = 0;
    }
};

const incrementFixed = () => {
    if (form.fixed_quantity < registeredFixed.value) form.fixed_quantity++;
};

const decrementFixed = () => {
    if (form.fixed_quantity > 1) form.fixed_quantity--;
};

const incrementExchange = () => {
    if (form.exchange_quantity < registeredExchange.value) form.exchange_quantity++;
};

const decrementExchange = () => {
    if (form.exchange_quantity > 1) form.exchange_quantity--;
};

const clampFixed = () => {
    let val = parseInt(form.fixed_quantity, 10);
    if (isNaN(val) || val < 1) val = 1;
    if (val > registeredFixed.value) val = registeredFixed.value;
    form.fixed_quantity = val;
};

const clampExchange = () => {
    let val = parseInt(form.exchange_quantity, 10);
    if (isNaN(val) || val < 1) val = 1;
    if (val > registeredExchange.value) val = registeredExchange.value;
    form.exchange_quantity = val;
};

const submitOrder = () => {
    if (isSubmitDisabled.value) return;
    form.post(route('cliente.orders.store'), {
        preserveScroll: true,
    });
};

const activeErrorList = computed(() => {
    return Object.entries(form.errors).map(([key, msg]) => ({ key, msg }));
});
</script>

<template>
    <Head title="Pedir botellones" />

    <AuthenticatedLayout>
        <div class="bg-[#F5F4F8] py-4 sm:py-7 pb-24 lg:pb-8">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
                <!-- ========================================== -->
                <!-- 1. HERO MORADO LIMPIO (SIN FLECHA)         -->
                <!-- ========================================== -->
                <header class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#1E1545] via-[#2A1E5E] to-[#3B297D] text-white shadow-md p-5 sm:p-7 border border-[#34266E]">
                    <div
                        aria-hidden="true"
                        class="pointer-events-none absolute -right-10 -top-10 w-44 h-44 rounded-full bg-violet-400/15 blur-2xl"
                    ></div>

                    <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3.5">
                        <div class="space-y-1">
                            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">
                                Pedir botellones
                            </h1>
                            <p class="text-xs sm:text-sm text-violet-200 leading-relaxed">
                                Agua purificada de 20 L directo a tu domicilio
                            </p>
                        </div>

                        <div
                            class="inline-flex items-center gap-1.5 text-xs font-semibold self-start sm:self-auto shrink-0"
                            :class="scheduleStatus.can_receive ? 'text-emerald-300' : 'text-rose-300'"
                        >
                            <Clock class="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                            <span>{{ scheduleStatus.can_receive ? 'Recepción abierta' : 'Fuera de horario' }}</span>
                        </div>
                    </div>
                </header>

                <!-- ========================================== -->
                <!-- 2. BLOQUEOS COMERCIALES                    -->
                <!-- ========================================== -->
                <section v-if="!canOrder" aria-label="Requisitos para realizar pedido" class="space-y-3">
                    <div
                        v-if="blocks.has_active_order && activeOrder"
                        class="rounded-2xl bg-white border border-violet-200 p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-sm"
                    >
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 rounded-xl bg-violet-100 text-[#6D28D9] flex items-center justify-center shrink-0">
                                <Truck class="w-5 h-5" aria-hidden="true" />
                            </div>
                            <div>
                                <h2 class="text-sm font-extrabold text-[#1E1545]">
                                    Ya tienes un pedido activo ({{ activeOrder.folio }})
                                </h2>
                                <p class="text-xs sm:text-sm text-slate-600 mt-0.5">
                                    Estado actual: <strong>{{ activeOrder.status_label }}</strong>.
                                </p>
                            </div>
                        </div>

                        <Link
                            :href="route('cliente.orders.show', activeOrder.id)"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 min-h-[44px] rounded-xl text-xs sm:text-sm font-bold text-white bg-[#1E1545] hover:bg-[#2E2363] transition shrink-0"
                        >
                            <span>Seguir pedido</span>
                        </Link>
                    </div>

                    <div
                        v-if="blocks.outside_schedule"
                        class="rounded-2xl bg-amber-50 border border-amber-200 p-5 flex items-start gap-3"
                    >
                        <div class="h-10 w-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center shrink-0">
                            <Clock class="w-5 h-5" aria-hidden="true" />
                        </div>
                        <div>
                            <h2 class="text-sm font-extrabold text-amber-950">Horario cerrado</h2>
                            <p class="text-xs sm:text-sm text-amber-900 mt-0.5">{{ scheduleStatus.message }}</p>
                            <p v-if="formattedScheduleInline" class="text-xs text-amber-800 mt-1 font-semibold">
                                Recepción: {{ formattedScheduleInline }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="blocks.no_address"
                        class="rounded-2xl bg-white border border-rose-200 p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-sm"
                    >
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center shrink-0">
                                <MapPin class="w-5 h-5" aria-hidden="true" />
                            </div>
                            <div>
                                <h2 class="text-sm font-extrabold text-[#1E1545]">Registra tu domicilio</h2>
                                <p class="text-xs sm:text-sm text-slate-600 mt-0.5">
                                    Necesitamos tu dirección para enviar tus botellones.
                                </p>
                            </div>
                        </div>

                        <Link
                            :href="route('cliente.addresses.create')"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 min-h-[44px] rounded-xl text-xs sm:text-sm font-bold text-white bg-rose-600 hover:bg-rose-700 transition shrink-0"
                        >
                            <MapPin class="w-4 h-4" aria-hidden="true" />
                            <span>Registrar domicilio</span>
                        </Link>
                    </div>

                    <div
                        v-if="blocks.no_bottles"
                        class="rounded-2xl bg-white border border-indigo-200 p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-sm"
                    >
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0">
                                <Droplets class="w-5 h-5" aria-hidden="true" />
                            </div>
                            <div>
                                <h2 class="text-sm font-extrabold text-[#1E1545]">Configura tus botellones</h2>
                                <p class="text-xs sm:text-sm text-slate-600 mt-0.5">
                                    Indica en tu cuenta cuántos botellones Fijos o de Cambio manejas.
                                </p>
                            </div>
                        </div>

                        <Link
                            :href="route('profile.edit')"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 min-h-[44px] rounded-xl text-xs sm:text-sm font-bold text-white bg-[#1E1545] hover:bg-[#2E2363] transition shrink-0"
                        >
                            <UserCog class="w-4 h-4" aria-hidden="true" />
                            <span>Configurar envases</span>
                        </Link>
                    </div>

                    <div
                        v-if="blocks.no_customer"
                        class="rounded-2xl bg-rose-50 border border-rose-200 p-5 flex items-start gap-3"
                    >
                        <AlertTriangle class="w-5 h-5 text-rose-700 shrink-0 mt-0.5" aria-hidden="true" />
                        <p class="text-sm font-semibold text-rose-900">
                            No encontramos un perfil de cliente activo vinculado a tu usuario.
                        </p>
                    </div>
                </section>

                <!-- Errores de validación -->
                <div
                    v-if="activeErrorList.length > 0"
                    role="alert"
                    class="rounded-2xl bg-rose-50 border border-rose-200 p-4 space-y-1"
                >
                    <p class="text-xs font-extrabold text-rose-900 uppercase tracking-wide flex items-center gap-1.5">
                        <AlertTriangle class="w-4 h-4" aria-hidden="true" />
                        <span>Revisa tu pedido</span>
                    </p>
                    <ul class="list-disc list-inside text-xs sm:text-sm text-rose-800 space-y-0.5">
                        <li v-for="err in activeErrorList" :key="err.key">{{ err.msg }}</li>
                    </ul>
                </div>

                <!-- ========================================== -->
                <!-- 3. SELECTOR LIMPIO Y RESUMEN               -->
                <!-- ========================================== -->
                <form @submit.prevent="submitOrder" class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                    <!-- Columna principal -->
                    <div class="lg:col-span-7 space-y-5">
                        <!-- Tarjeta única de cantidad -->
                        <section class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm p-5 sm:p-6 space-y-5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2.5">
                                    <span
                                        v-if="activeModality === 'fixed'"
                                        class="h-10 w-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0"
                                    >
                                        <Package class="w-5 h-5" aria-hidden="true" />
                                    </span>
                                    <span
                                        v-else
                                        class="h-10 w-10 rounded-xl bg-sky-100 text-[#0275B8] flex items-center justify-center shrink-0"
                                    >
                                        <Droplets class="w-5 h-5" aria-hidden="true" />
                                    </span>

                                    <div>
                                        <h2 class="text-base font-extrabold text-[#1E1545]">
                                            {{ activeModality === 'fixed' ? 'Botellones Fijos' : activeModality === 'exchange' ? 'Botellones de Cambio' : 'Elige tu cantidad' }}
                                        </h2>
                                        <p class="text-xs text-slate-500">
                                            {{ activeModality === 'fixed'
                                                ? 'Se lavan, llenan y regresan tus propios envases'
                                                : activeModality === 'exchange'
                                                    ? 'Entregas envase vacío y recibes lleno al instante'
                                                    : 'Configura tus botellones en Mi cuenta' }}
                                        </p>
                                    </div>
                                </div>

                                <span
                                    v-if="maxQuantity > 0"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold bg-[#F5F4F8] text-slate-600 tabular-nums"
                                >
                                    Máx. {{ maxQuantity }}
                                </span>
                            </div>

                            <!-- Selector protagonista -->
                            <div
                                v-if="activeModality === 'fixed' || activeModality === 'exchange'"
                                class="rounded-2xl p-5 border flex flex-col items-center gap-4"
                                :class="activeModality === 'fixed'
                                    ? 'bg-indigo-50/50 border-indigo-200/80'
                                    : 'bg-sky-50/60 border-sky-200/80'"
                            >
                                <label
                                    :for="activeModality === 'fixed' ? 'fixed-qty-input' : 'exchange-qty-input'"
                                    class="text-xs font-bold uppercase tracking-wider text-slate-500 text-center"
                                >
                                    Cantidad a solicitar (20 L) • <span class="text-[#1E1545] tabular-nums">${{ formatCurrency(unitPrice) }} c/u</span>
                                </label>

                                <div class="flex items-center justify-center gap-3">
                                    <button
                                        type="button"
                                        @click="activeModality === 'fixed' ? decrementFixed() : decrementExchange()"
                                        :disabled="!canOrder || currentQuantity <= 1"
                                        :aria-label="activeModality === 'fixed' ? 'Disminuir botellones fijos' : 'Disminuir botellones de cambio'"
                                        class="h-12 w-12 rounded-2xl bg-white border border-[#E9E7F0] text-[#1E1545] hover:bg-[#F5F4F8] shadow-sm disabled:opacity-40 flex items-center justify-center transition cursor-pointer"
                                    >
                                        <Minus class="w-5 h-5" aria-hidden="true" />
                                    </button>

                                    <input
                                        v-if="activeModality === 'fixed'"
                                        id="fixed-qty-input"
                                        type="number"
                                        min="1"
                                        :max="registeredFixed"
                                        v-model.number="form.fixed_quantity"
                                        @blur="clampFixed"
                                        :disabled="!canOrder"
                                        class="w-24 h-12 text-center font-mono font-black text-2xl rounded-2xl border border-indigo-200 bg-white text-[#1E1545] tabular-nums shadow-sm"
                                    />
                                    <input
                                        v-else
                                        id="exchange-qty-input"
                                        type="number"
                                        min="1"
                                        :max="registeredExchange"
                                        v-model.number="form.exchange_quantity"
                                        @blur="clampExchange"
                                        :disabled="!canOrder"
                                        class="w-24 h-12 text-center font-mono font-black text-2xl rounded-2xl border border-sky-200 bg-white text-[#1E1545] tabular-nums shadow-sm"
                                    />

                                    <button
                                        type="button"
                                        @click="activeModality === 'fixed' ? incrementFixed() : incrementExchange()"
                                        :disabled="!canOrder || currentQuantity >= maxQuantity"
                                        :aria-label="activeModality === 'fixed' ? 'Aumentar botellones fijos' : 'Aumentar botellones de cambio'"
                                        class="h-12 w-12 rounded-2xl bg-white border border-[#E9E7F0] text-[#1E1545] hover:bg-[#F5F4F8] shadow-sm disabled:opacity-40 flex items-center justify-center transition cursor-pointer"
                                    >
                                        <Plus class="w-5 h-5" aria-hidden="true" />
                                    </button>
                                </div>

                                <!-- Chips de selección rápida -->
                                <div v-if="quickQuantities.length > 0 && canOrder" class="flex flex-wrap justify-center gap-2 pt-1" role="group" aria-label="Cantidades rápidas">
                                    <button
                                        v-for="qty in quickQuantities"
                                        :key="qty"
                                        type="button"
                                        @click="setQuantity(qty)"
                                        class="px-4 py-2 min-h-[40px] rounded-xl text-xs font-extrabold border transition cursor-pointer tabular-nums focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9]"
                                        :class="currentQuantity === qty
                                            ? 'bg-[#1E1545] text-white border-[#1E1545] shadow-sm'
                                            : 'bg-white text-[#1E1545] border-[#E9E7F0] hover:bg-[#ECE8F8]'"
                                    >
                                        {{ qty === maxQuantity ? `Todos (${qty})` : `${qty}` }}
                                    </button>
                                </div>
                            </div>

                            <div v-else class="rounded-2xl bg-slate-50 border border-dashed border-slate-300 p-6 text-center text-xs text-slate-600">
                                No hay botellones configurados en tu cuenta todavía.
                            </div>

                            <!-- Indicaciones dentro de la misma tarjeta para menos saltos visuales -->
                            <div class="pt-3 border-t border-[#E9E7F0] space-y-2">
                                <div class="flex items-baseline justify-between gap-3">
                                    <label for="order-notes" class="text-sm font-extrabold text-[#1E1545]">
                                        Indicaciones (opcional)
                                    </label>
                                    <span class="text-xs font-semibold text-slate-400 tabular-nums shrink-0">
                                        {{ form.notes.length }} / 500
                                    </span>
                                </div>

                                <textarea
                                    id="order-notes"
                                    v-model="form.notes"
                                    rows="2"
                                    maxlength="500"
                                    :disabled="!canOrder"
                                    placeholder="Ej. Tocar el portón azul o dejar junto a la puerta"
                                    class="w-full rounded-xl border-[#E9E7F0] bg-[#F5F4F8]/60 text-base sm:text-sm text-[#1E1545] placeholder:text-slate-400 focus:border-[#6D28D9] focus:ring-[#6D28D9] disabled:opacity-60"
                                ></textarea>
                            </div>
                        </section>
                    </div>

                    <!-- Columna derecha: Dirección + Horario legible + Confirmación escritorio -->
                    <aside class="lg:col-span-5 lg:sticky lg:top-20 space-y-4">
                        <div class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm overflow-hidden">
                            <div class="bg-[#1E1545] text-white px-5 py-3.5 flex items-center justify-between">
                                <h2 class="text-xs font-extrabold uppercase tracking-wider">
                                    Resumen de entrega
                                </h2>
                                <span class="px-2 py-0.5 rounded-md text-xs font-bold bg-white/15 text-violet-100 tabular-nums">
                                    {{ totalSelectedBottles }} u.
                                </span>
                            </div>

                            <div class="p-5 space-y-4">
                                <div v-if="address" class="space-y-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1">
                                            <MapPin class="w-3.5 h-3.5 text-emerald-600" aria-hidden="true" />
                                            <span>Entregar en</span>
                                        </span>
                                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800">
                                            {{ address.zone_label }}
                                        </span>
                                    </div>
                                    <p class="text-sm font-extrabold text-[#1E1545] break-words">
                                        {{ address.full_address }}
                                    </p>
                                    <p v-if="address.between_streets" class="text-xs text-slate-600 break-words">
                                        {{ address.between_streets }}
                                    </p>
                                    <p v-if="address.reference" class="text-xs text-slate-500 break-words">
                                        Ref.: {{ address.reference }}
                                    </p>
                                </div>

                                <!-- Horario legible -->
                                <div v-if="formattedSchedule" class="rounded-xl bg-[#F5F4F8] border border-[#E9E7F0] p-3.5 flex items-start gap-2.5">
                                    <Clock class="w-4 h-4 text-[#6D28D9] shrink-0 mt-0.5" aria-hidden="true" />
                                    <div class="min-w-0 text-xs space-y-0.5">
                                        <p class="font-extrabold text-[#1E1545]">
                                            {{ formattedSchedule.days }}
                                        </p>
                                        <p class="text-slate-600 font-medium">
                                            Recepción de pedidos: {{ formattedSchedule.intervals }}
                                        </p>
                                        <p v-if="formattedSchedule.breakLabel" class="text-[11px] text-slate-500">
                                            {{ formattedSchedule.breakLabel }}
                                        </p>
                                    </div>
                                </div>

                                <div class="border-t border-[#E9E7F0] pt-3.5 space-y-2 text-sm">
                                    <div class="flex items-center justify-between text-xs text-slate-600">
                                        <span>
                                            {{ totalSelectedBottles }} {{ totalSelectedBottles === 1 ? 'botellón' : 'botellones' }}
                                            ({{ activeModality === 'fixed' ? 'Fijos' : 'De cambio' }}) × ${{ formatCurrency(unitPrice) }}
                                        </span>
                                        <span class="font-bold text-[#1E1545] tabular-nums">
                                            ${{ formatCurrency(subtotalAmount) }}
                                        </span>
                                    </div>

                                    <div v-if="freePromoBottles > 0" class="flex items-center justify-between text-xs text-emerald-700 font-semibold">
                                        <span>Promoción semanal 9+1 (1 gratis)</span>
                                        <span class="tabular-nums">-${{ formatCurrency(discountAmount) }}</span>
                                    </div>

                                    <div class="flex items-baseline justify-between pt-2 border-t border-[#E9E7F0]">
                                        <span class="font-extrabold text-[#1E1545]">Total a pagar</span>
                                        <span class="text-2xl font-black text-[#1E1545] tabular-nums">
                                            ${{ formatCurrency(totalAmountToPay) }}
                                        </span>
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    :disabled="isSubmitDisabled"
                                    class="hidden lg:inline-flex w-full items-center justify-center gap-2 px-5 py-3.5 min-h-[48px] rounded-xl text-sm font-extrabold text-white bg-[#1E1545] hover:bg-[#2E2363] shadow-sm transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9]"
                                >
                                    <CheckCircle2 class="w-5 h-5 text-emerald-400" aria-hidden="true" />
                                    <span>{{ form.processing ? 'Registrando pedido...' : 'Confirmar pedido' }}</span>
                                </button>
                            </div>
                        </div>
                    </aside>

                    <!-- Barra fija móvil de confirmación (encima del bottom nav) -->
                    <div
                        v-if="canOrder"
                        class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 lg:hidden bg-white/95 backdrop-blur-md border-t border-[#E9E7F0] px-4 py-3 shadow-[0_-6px_20px_rgba(30,21,69,0.10)]"
                    >
                        <div class="max-w-lg mx-auto flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <span class="text-[11px] font-semibold text-slate-500 block truncate">
                                    {{ totalSelectedBottles }} {{ totalSelectedBottles === 1 ? 'botellón' : 'botellones' }}
                                    <template v-if="freePromoBottles > 0"> • 1 gratis (9+1)</template>
                                </span>
                                <span class="text-lg font-black text-[#1E1545] tabular-nums block leading-tight">
                                    ${{ formatCurrency(totalAmountToPay) }}
                                </span>
                            </div>

                            <button
                                type="submit"
                                :disabled="isSubmitDisabled"
                                class="inline-flex items-center justify-center gap-2 px-5 py-3 min-h-[48px] rounded-xl text-sm font-extrabold text-white bg-[#1E1545] hover:bg-[#2E2363] shadow-sm transition cursor-pointer disabled:opacity-50 shrink-0"
                            >
                                <CheckCircle2 class="w-[18px] h-[18px] text-emerald-400 shrink-0" aria-hidden="true" />
                                <span>{{ form.processing ? 'Enviando...' : 'Confirmar pedido' }}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
