<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ArrowRight,
    Boxes,
    Calendar,
    CheckCircle2,
    Clock,
    Droplets,
    Inbox,
    Package,
    RefreshCw,
    Store,
    Tag,
    Truck,
    Zap,
} from 'lucide-vue-next';

const props = defineProps({
    meta: { type: Object, required: true },
    quickRefill: { type: Object, required: true },
    fixedPipeline: { type: Object, required: true },
    exchange: { type: Object, required: true },
    alerts: { type: Object, required: true },
    recentMovements: { type: Array, default: () => [] },
});

const formatCurrency = (val) =>
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
    }).format(Number(val || 0));

const formatNumber = (val) => new Intl.NumberFormat('es-MX').format(Number(val || 0));

const timeGreeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Buenos días';
    if (hour < 19) return 'Buenas tardes';
    return 'Buenas noches';
});

const totalFixedActive = computed(() => {
    return (
        Number(props.fixedPipeline.pending_reception_bottles || 0) +
        Number(props.fixedPipeline.pending_fill_bottles || 0) +
        Number(props.fixedPipeline.ready_for_truck_bottles || 0)
    );
});

const totalExchangeInPlant = computed(() => {
    return (
        Number(props.exchange.plant_empty_available || 0) +
        Number(props.exchange.plant_washing_filling || 0) +
        Number(props.exchange.plant_full_ready || 0) +
        Number(props.exchange.plant_full_reserved || 0)
    );
});

const totalExchangeInTruck = computed(() => {
    return (
        Number(props.exchange.truck_full_available || 0) +
        Number(props.exchange.truck_empty_collected || 0)
    );
});

const exchangeCoveragePct = computed(() => {
    const target = Number(props.exchange.target_quantity || 0);
    const active = Number(props.exchange.active_total || 0);
    if (target <= 0) return 0;
    return Math.min(100, Math.round((active / target) * 100));
});

const quickActions = [
    {
        label: 'Llenado rápido',
        sub: `Mostrador · ${formatCurrency(props.quickRefill.unit_price)} c/u`,
        href: route('planta.quick-refill.index'),
        icon: Zap,
        iconBg: 'bg-[#0071BA] text-white',
        hoverBorder: 'hover:border-[#0071BA]/45',
    },
    {
        label: 'Fijos · Por recibir',
        sub: `${props.fixedPipeline.pending_reception_orders} pedidos por cotejar`,
        href: route('planta.fijos.index', { tab: 'por-recibir' }),
        icon: Inbox,
        iconBg: 'bg-[#D99A27] text-white',
        hoverBorder: 'hover:border-[#D99A27]/45',
    },
    {
        label: 'Fijos · Por llenar',
        sub: `${props.fixedPipeline.pending_fill_bottles} botellones en turno`,
        href: route('planta.fijos.index', { tab: 'por-llenar' }),
        icon: Droplets,
        iconBg: 'bg-[#26845B] text-white',
        hoverBorder: 'hover:border-[#26845B]/45',
    },
    {
        label: 'Cambios 20L',
        sub: 'Planta, lavado y camión',
        href: route('planta.cambios.index'),
        icon: RefreshCw,
        iconBg: 'bg-[#1E1545] text-white',
        hoverBorder: 'hover:border-[#1E1545]/45',
    },
    {
        label: 'Existencias',
        sub: 'Inventario e incidencias',
        href: route('planta.inventory.index'),
        icon: Boxes,
        iconBg: 'bg-[#0284C7] text-white',
        hoverBorder: 'hover:border-[#0284C7]/45',
    },
];
</script>

<template>
    <Head title="Panel de Planta - Puritronic" />

    <AuthenticatedLayout :wide="true">
        <div class="bg-[#F5F4F8] min-h-full py-5 sm:py-7">
            <div class="mx-auto w-full max-w-[98%] 2xl:max-w-[1750px] px-4 sm:px-6 lg:px-8 space-y-5 sm:space-y-6">

                <!-- 1. HERO DE BIENVENIDA -->
                <section
                    aria-label="Bienvenida Operador de Planta"
                    class="relative overflow-hidden rounded-2xl bg-[#0275B8] shadow-sm p-6 sm:p-8"
                >
                    <svg
                        viewBox="0 0 1200 220"
                        preserveAspectRatio="none"
                        class="absolute inset-0 w-full h-full pointer-events-none select-none"
                        aria-hidden="true"
                    >
                        <path
                            d="M0,170 C240,130 460,205 720,160 C920,125 1060,175 1200,135 L1200,220 L0,220 Z"
                            fill="#0064A0"
                            fill-opacity="0.45"
                        />
                        <circle cx="960" cy="40" r="76" fill="#FFFFFF" fill-opacity="0.06" />
                    </svg>

                    <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                        <div class="space-y-3.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[11px] font-bold shadow-sm"
                                    :class="meta.is_store_open
                                        ? 'bg-[#E6F7EE] text-[#26845B]'
                                        : 'bg-[#FEF3C7] text-[#92400E]'"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full shrink-0"
                                        :class="meta.is_store_open ? 'bg-[#26845B]' : 'bg-[#D99A27]'"
                                    ></span>
                                    <span>{{ meta.is_store_open ? 'Planta en horario' : 'Fuera de horario' }}</span>
                                </span>

                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[11px] font-semibold bg-[#1E1545] text-white tabular-nums shadow-sm">
                                    <Clock class="w-3.5 h-3.5 text-[#7DD3FC]" :stroke-width="2" />
                                    <span>{{ meta.schedule_hours }}</span>
                                </span>

                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[11px] font-semibold bg-[#EEEAF7] text-[#1E1545] shadow-sm">
                                    <Calendar class="w-3.5 h-3.5 text-[#0071BA]" :stroke-width="2" />
                                    <span>{{ meta.date_label }}</span>
                                </span>
                            </div>

                            <div class="pl-3.5 border-l-4 border-[#1E1545]">
                                <p class="text-xs sm:text-sm font-semibold uppercase tracking-wider text-[#BAE6FD]">
                                    {{ timeGreeting }} · Operación Planta
                                </p>
                                <h1 class="text-2xl sm:text-[30px] font-bold tracking-tight text-white leading-tight mt-0.5">
                                    {{ $page.props.auth.user.name }}
                                </h1>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2.5">
                            <Link
                                :href="route('planta.quick-refill.index')"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white text-[#1E1545] hover:bg-[#F4F3F8] font-bold text-xs sm:text-sm shadow-sm transition-colors"
                            >
                                <Zap class="w-4 h-4 text-[#0071BA]" :stroke-width="2.2" />
                                <span>Llenado rápido</span>
                            </Link>
                            <Link
                                :href="route('planta.fijos.index')"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#1E1545] text-white hover:bg-[#292059] font-bold text-xs sm:text-sm shadow-sm transition-colors"
                            >
                                <Tag class="w-4 h-4 text-[#7DD3FC]" :stroke-width="2" />
                                <span>Botellones Fijos</span>
                            </Link>
                            <Link
                                :href="route('planta.cambios.index')"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#005E9B] text-white hover:bg-[#004E82] border border-white/20 font-bold text-xs sm:text-sm shadow-sm transition-colors"
                            >
                                <RefreshCw class="w-4 h-4" :stroke-width="2" />
                                <span>Cambios</span>
                            </Link>
                        </div>
                    </div>
                </section>

                <!-- 2. KPI CARDS -->
                <section aria-label="Indicadores de Planta" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    <!-- KPI 1: Mostrador Hoy -->
                    <Link
                        :href="route('planta.quick-refill.index')"
                        class="group rounded-2xl border border-[#E9E7F0] bg-white p-5 hover:border-[#0071BA]/40 hover:shadow-sm transition-all"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">Mostrador hoy</p>
                                <p class="mt-2 text-2xl font-bold text-[#1E1545] tabular-nums">
                                    {{ formatNumber(quickRefill.bottles_today) }}
                                    <span class="text-sm font-semibold text-[#747181]">bot.</span>
                                </p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-[#E5F4FD] text-[#0071BA] flex items-center justify-center shrink-0">
                                <Store class="w-5 h-5" :stroke-width="2" />
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#F4F3F8] flex items-center justify-between text-xs">
                            <span class="font-bold text-[#26845B] tabular-nums">{{ formatCurrency(quickRefill.amount_today) }}</span>
                            <span class="text-[#747181] tabular-nums">{{ quickRefill.services_today }} servicios</span>
                        </div>
                    </Link>

                    <!-- KPI 2: Fijos en Planta -->
                    <Link
                        :href="route('planta.fijos.index')"
                        class="group rounded-2xl border border-[#E9E7F0] bg-white p-5 hover:border-[#26845B]/40 hover:shadow-sm transition-all"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">Fijos en planta</p>
                                <p class="mt-2 text-2xl font-bold text-[#1E1545] tabular-nums">
                                    {{ formatNumber(totalFixedActive) }}
                                    <span class="text-sm font-semibold text-[#747181]">bot.</span>
                                </p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-[#E6F7EE] text-[#26845B] flex items-center justify-center shrink-0">
                                <Tag class="w-5 h-5" :stroke-width="2" />
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#F4F3F8] flex items-center justify-between text-xs">
                            <span class="font-semibold text-[#B45309] tabular-nums">
                                {{ fixedPipeline.pending_reception_bottles }} por recibir
                            </span>
                            <span class="font-semibold text-[#0071BA] tabular-nums">
                                {{ fixedPipeline.pending_fill_bottles }} por llenar
                            </span>
                        </div>
                    </Link>

                    <!-- KPI 3: Cambios disponibles en Planta -->
                    <Link
                        :href="route('planta.cambios.index')"
                        class="group rounded-2xl border border-[#E9E7F0] bg-white p-5 hover:border-[#1E1545]/40 hover:shadow-sm transition-all"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">Cambios listos en planta</p>
                                <p class="mt-2 text-2xl font-bold text-[#1E1545] tabular-nums">
                                    {{ formatNumber(exchange.plant_full_ready) }}
                                    <span class="text-sm font-semibold text-[#747181]">llenos</span>
                                </p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-[#EEEAF7] text-[#1E1545] flex items-center justify-center shrink-0">
                                <RefreshCw class="w-5 h-5" :stroke-width="2" />
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#F4F3F8] flex items-center justify-between text-xs">
                            <span class="text-[#747181] tabular-nums">{{ exchange.plant_empty_available }} vacíos</span>
                            <span class="font-semibold text-[#1E1545] tabular-nums">{{ exchange.truck_full_available }} en camión</span>
                        </div>
                    </Link>

                    <!-- KPI 4: Incidencias / Alertas -->
                    <Link
                        :href="route('planta.incidents.index')"
                        class="group rounded-2xl border border-[#E9E7F0] bg-white p-5 hover:border-[#D99A27]/45 hover:shadow-sm transition-all"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">Incidencias abiertas</p>
                                <p class="mt-2 text-2xl font-bold text-[#1E1545] tabular-nums">
                                    {{ formatNumber(alerts.pending_incidents) }}
                                </p>
                            </div>
                            <div
                                class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0"
                                :class="alerts.pending_incidents > 0 ? 'bg-[#FCECEF] text-[#E82147]' : 'bg-[#E6F7EE] text-[#26845B]'"
                            >
                                <AlertTriangle v-if="alerts.pending_incidents > 0" class="w-5 h-5" :stroke-width="2" />
                                <CheckCircle2 v-else class="w-5 h-5" :stroke-width="2" />
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#F4F3F8] flex items-center justify-between text-xs">
                            <span class="text-[#747181]">No ubicados: <strong class="text-[#1E1545] tabular-nums">{{ exchange.plant_unlocated }}</strong></span>
                            <span class="text-[#747181]">Dañados: <strong class="text-[#1E1545] tabular-nums">{{ exchange.plant_damaged_total }}</strong></span>
                        </div>
                    </Link>
                </section>

                <!-- 3. PANEL PRINCIPAL EN 2 COLUMNAS -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                    <!-- Columna izquierda: Ciclo de Fijos y Circuito de Cambios -->
                    <div class="lg:col-span-7 space-y-5">
                        <!-- Pipeline Botellones Fijos -->
                        <section class="rounded-2xl border border-[#E9E7F0] bg-white p-5 sm:p-6">
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <div>
                                    <h2 class="text-base font-bold text-[#1E1545]">Ciclo de Botellones Fijos</h2>
                                    <p class="text-xs text-[#747181] mt-0.5">Flujo de botellones exclusivos de clientes en planta</p>
                                </div>
                                <Link
                                    :href="route('planta.fijos.index')"
                                    class="inline-flex items-center gap-1 text-xs font-bold text-[#0071BA] hover:text-[#1E1545] transition-colors"
                                >
                                    <span>Abrir Fijos</span>
                                    <ArrowRight class="w-3.5 h-3.5" />
                                </Link>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <Link
                                    :href="route('planta.fijos.index', { tab: 'por-recibir' })"
                                    class="rounded-xl border border-[#D99A27]/30 bg-[#FEF3C7]/45 p-4 hover:border-[#D99A27] transition-colors"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-bold uppercase tracking-wide text-[#B45309]">1. Por recibir</span>
                                        <Inbox class="w-4 h-4 text-[#D99A27]" />
                                    </div>
                                    <p class="mt-2 text-2xl font-bold text-[#1E1545] tabular-nums">
                                        {{ fixedPipeline.pending_reception_bottles }}
                                    </p>
                                    <p class="text-xs text-[#747181] mt-0.5 tabular-nums">
                                        {{ fixedPipeline.pending_reception_orders }} pedidos en espera
                                    </p>
                                </Link>

                                <Link
                                    :href="route('planta.fijos.index', { tab: 'por-llenar' })"
                                    class="rounded-xl border border-[#0071BA]/25 bg-[#E5F4FD]/60 p-4 hover:border-[#0071BA] transition-colors"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-bold uppercase tracking-wide text-[#0071BA]">2. Por llenar</span>
                                        <Droplets class="w-4 h-4 text-[#0071BA]" />
                                    </div>
                                    <p class="mt-2 text-2xl font-bold text-[#1E1545] tabular-nums">
                                        {{ fixedPipeline.pending_fill_bottles }}
                                    </p>
                                    <p class="text-xs text-[#747181] mt-0.5 tabular-nums">
                                        {{ fixedPipeline.pending_fill_orders }} pedidos recibidos
                                    </p>
                                </Link>

                                <div class="rounded-xl border border-[#26845B]/25 bg-[#E6F7EE]/65 p-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-bold uppercase tracking-wide text-[#26845B]">3. Listos p/ camión</span>
                                        <CheckCircle2 class="w-4 h-4 text-[#26845B]" />
                                    </div>
                                    <p class="mt-2 text-2xl font-bold text-[#1E1545] tabular-nums">
                                        {{ fixedPipeline.ready_for_truck_bottles }}
                                    </p>
                                    <p class="text-xs text-[#747181] mt-0.5 tabular-nums">
                                        {{ fixedPipeline.ready_for_truck_orders }} pedidos por subir
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- Circuito Cambios Planta vs Camión -->
                        <section class="rounded-2xl border border-[#E9E7F0] bg-white p-5 sm:p-6">
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <div>
                                    <h2 class="text-base font-bold text-[#1E1545]">Circuito de Cambios 20L</h2>
                                    <p class="text-xs text-[#747181] mt-0.5">Balance físico entre Planta y camión ({{ exchange.driver_name }})</p>
                                </div>
                                <Link
                                    :href="route('planta.cambios.index')"
                                    class="inline-flex items-center gap-1 text-xs font-bold text-[#0071BA] hover:text-[#1E1545] transition-colors"
                                >
                                    <span>Gestionar Cambios</span>
                                    <ArrowRight class="w-3.5 h-3.5" />
                                </Link>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] p-4 space-y-2.5">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold text-[#1E1545] flex items-center gap-1.5">
                                            <Package class="w-4 h-4 text-[#0071BA]" />
                                            En Planta
                                        </span>
                                        <span class="text-xs font-bold text-[#0071BA] tabular-nums">{{ totalExchangeInPlant }} total</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-center pt-1">
                                        <div class="rounded-lg bg-white border border-[#E9E7F0] py-2 px-1">
                                            <p class="text-[10px] font-semibold text-[#747181]">Llenos</p>
                                            <p class="text-base font-bold text-[#26845B] tabular-nums">{{ exchange.plant_full_ready }}</p>
                                        </div>
                                        <div class="rounded-lg bg-white border border-[#E9E7F0] py-2 px-1">
                                            <p class="text-[10px] font-semibold text-[#747181]">Vacíos</p>
                                            <p class="text-base font-bold text-[#1E1545] tabular-nums">{{ exchange.plant_empty_available }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] p-4 space-y-2.5">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold text-[#1E1545] flex items-center gap-1.5">
                                            <Truck class="w-4 h-4 text-[#1E1545]" />
                                            En Camión
                                        </span>
                                        <span class="text-xs font-bold text-[#1E1545] tabular-nums">{{ totalExchangeInTruck }} total</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-center pt-1">
                                        <div class="rounded-lg bg-white border border-[#E9E7F0] py-2 px-1">
                                            <p class="text-[10px] font-semibold text-[#747181]">Llenos disponibles</p>
                                            <p class="text-base font-bold text-[#26845B] tabular-nums">{{ exchange.truck_full_available }}</p>
                                        </div>
                                        <div class="rounded-lg bg-white border border-[#E9E7F0] py-2 px-1">
                                            <p class="text-[10px] font-semibold text-[#747181]">Vacíos recogidos</p>
                                            <p class="text-base font-bold text-[#B45309] tabular-nums">{{ exchange.truck_empty_collected }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="exchange.can_view_target && exchange.target_quantity > 0" class="mt-4 pt-4 border-t border-[#F4F3F8]">
                                <div class="flex items-center justify-between text-xs mb-1.5">
                                    <span class="font-semibold text-[#747181]">Cobertura contra meta de flota</span>
                                    <span class="font-bold text-[#1E1545] tabular-nums">
                                        {{ exchange.active_total }} / {{ exchange.target_quantity }} ({{ exchangeCoveragePct }}%)
                                    </span>
                                </div>
                                <div class="h-2 rounded-full bg-[#F4F3F8] overflow-hidden">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-[#0071BA] to-[#26845B] transition-all"
                                        :style="{ width: `${exchangeCoveragePct}%` }"
                                    ></div>
                                </div>
                            </div>
                        </section>

                        <!-- Movimientos recientes -->
                        <section class="rounded-2xl border border-[#E9E7F0] bg-white p-5 sm:p-6">
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <div class="flex items-center gap-2">
                                    <Activity class="w-4 h-4 text-[#0071BA]" />
                                    <h2 class="text-base font-bold text-[#1E1545]">Movimientos recientes</h2>
                                </div>
                                <Link
                                    :href="route('planta.history.index')"
                                    class="text-xs font-bold text-[#0071BA] hover:text-[#1E1545] transition-colors"
                                >
                                    Ver detalle
                                </Link>
                            </div>

                            <div v-if="recentMovements.length === 0" class="py-8 text-center text-xs text-[#747181]">
                                Aún no hay movimientos de custodia registrados.
                            </div>

                            <ul v-else class="divide-y divide-[#F4F3F8]">
                                <li
                                    v-for="mov in recentMovements"
                                    :key="mov.id"
                                    class="py-3 flex items-center justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <p class="text-xs sm:text-sm font-semibold text-[#1E1545] truncate">
                                            {{ mov.type_label }}
                                        </p>
                                        <p class="text-[11px] text-[#747181] truncate">
                                            {{ mov.bottle_type }} · {{ mov.actor_name }} · {{ mov.time_label }}
                                        </p>
                                    </div>
                                    <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-lg bg-[#F4F3F8] text-xs font-bold text-[#1E1545] tabular-nums">
                                        {{ mov.quantity }} bot.
                                    </span>
                                </li>
                            </ul>
                        </section>
                    </div>

                    <!-- Columna derecha: Accesos rápidos + Últimos llenados mostrador -->
                    <div class="lg:col-span-5 space-y-5">
                        <section class="rounded-2xl border border-[#E9E7F0] bg-white p-5 sm:p-6">
                            <h2 class="text-base font-bold text-[#1E1545] mb-3.5">Accesos rápidos</h2>
                            <div class="space-y-2.5">
                                <Link
                                    v-for="act in quickActions"
                                    :key="act.label"
                                    :href="act.href"
                                    class="flex items-center justify-between gap-3 p-3.5 rounded-xl border border-[#E9E7F0] bg-[#FDFCFE] transition-all"
                                    :class="act.hoverBorder"
                                >
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0 shadow-sm" :class="act.iconBg">
                                            <component :is="act.icon" class="w-4 h-4" :stroke-width="2" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-[#1E1545] truncate">{{ act.label }}</p>
                                            <p class="text-xs text-[#747181] truncate">{{ act.sub }}</p>
                                        </div>
                                    </div>
                                    <ArrowRight class="w-4 h-4 text-[#747181] shrink-0" />
                                </Link>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-[#E9E7F0] bg-white p-5 sm:p-6">
                            <div class="flex items-center justify-between gap-3 mb-3.5">
                                <div>
                                    <h2 class="text-base font-bold text-[#1E1545]">Llenados en mostrador hoy</h2>
                                    <p class="text-xs text-[#747181]">Últimos registros directos en planta</p>
                                </div>
                                <Link
                                    :href="route('planta.quick-refill.index')"
                                    class="text-xs font-bold text-[#0071BA] hover:text-[#1E1545]"
                                >
                                    Registrar
                                </Link>
                            </div>

                            <div v-if="quickRefill.recent_refills.length === 0" class="py-7 text-center text-xs text-[#747181]">
                                No se han registrado llenados en mostrador el día de hoy.
                            </div>

                            <ul v-else class="divide-y divide-[#F4F3F8]">
                                <li
                                    v-for="item in quickRefill.recent_refills"
                                    :key="item.id"
                                    class="py-3 flex items-center justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <p class="text-xs sm:text-sm font-semibold text-[#1E1545] tabular-nums">
                                            {{ item.quantity }} {{ item.quantity === 1 ? 'botellón' : 'botellones' }}
                                        </p>
                                        <p class="text-[11px] text-[#747181] truncate">
                                            {{ item.time }} · {{ item.operator_name }}
                                            <span v-if="item.notes"> · {{ item.notes }}</span>
                                        </p>
                                    </div>
                                    <span class="shrink-0 text-sm font-bold text-[#26845B] tabular-nums">
                                        {{ formatCurrency(item.total_amount) }}
                                    </span>
                                </li>
                            </ul>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
