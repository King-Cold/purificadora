<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RepartidorBottomNav from '@/Components/RepartidorBottomNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowDownToLine,
    ArrowRight,
    ArrowUpFromLine,
    Boxes,
    Calendar,
    CheckCircle2,
    Clock,
    DollarSign,
    MapPin,
    PackageCheck,
    PlusCircle,
    RefreshCw,
    Route as RouteIcon,
    Tag,
    Truck,
    Users,
    Wallet,
} from 'lucide-vue-next';

const props = defineProps({
    driver: { type: Object, required: true },
    formattedDate: { type: String, required: true },
    meta: {
        type: Object,
        default: () => ({ is_store_open: true, schedule_hours: '07:00 – 19:00' }),
    },
    activeRoute: { type: Object, default: null },
    availableRoutes: { type: Array, default: () => [] },
    counters: {
        type: Object,
        default: () => ({
            por_recoger: 0,
            por_subir: 0,
            por_entregar: 0,
            cobrar_pendientes: 0,
            transferencias_pendientes: 0,
        }),
    },
    todaySummary: {
        type: Object,
        default: () => ({
            delivered_orders: 0,
            collected_amount: 0,
            pending_receivable_amount: 0,
        }),
    },
    truckInventory: { type: Object, required: true },
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

const totalOperationalPending = computed(() => {
    return (
        Number(props.counters.por_recoger || 0) +
        Number(props.counters.por_subir || 0) +
        Number(props.counters.por_entregar || 0)
    );
});

const routeProgressPct = computed(() => {
    if (!props.activeRoute || !props.activeRoute.total_stops) return 0;
    const done = Number(props.activeRoute.completed_stops_count || 0) + Number(props.activeRoute.failed_stops_count || 0);
    return Math.min(100, Math.round((done / props.activeRoute.total_stops) * 100));
});

const routeStatusBadge = computed(() => {
    if (!props.activeRoute) {
        return {
            label: 'Sin ruta activa',
            classes: 'bg-[#EEEAF7] text-[#1E1545]',
            dot: 'bg-[#747181]',
        };
    }
    if (props.activeRoute.is_in_progress) {
        return {
            label: 'Ruta en curso',
            classes: 'bg-[#E6F7EE] text-[#26845B]',
            dot: 'bg-[#26845B]',
        };
    }
    if (props.activeRoute.is_incident) {
        return {
            label: 'Ruta en pausa',
            classes: 'bg-[#FCECEF] text-[#E82147]',
            dot: 'bg-[#E82147]',
        };
    }
    if (props.activeRoute.is_completed) {
        return {
            label: 'Jornada completada',
            classes: 'bg-[#E5F4FD] text-[#0071BA]',
            dot: 'bg-[#0071BA]',
        };
    }
    return {
        label: props.activeRoute.status_label || 'Asignada',
        classes: 'bg-[#FEF3C7] text-[#92400E]',
        dot: 'bg-[#D99A27]',
    };
});

const quickActions = [
    {
        label: 'Ir a Reparto',
        sub: `${totalOperationalPending.value} tareas operativas activas`,
        href: route('repartidor.reparto'),
        icon: Truck,
        iconBg: 'bg-[#0071BA] text-white',
        hoverBorder: 'hover:border-[#0071BA]/45',
    },
    {
        label: 'Pedido al paso',
        sub: 'Registrar venta directa en ruta',
        href: route('repartidor.direct-service.create'),
        icon: PlusCircle,
        iconBg: 'bg-[#26845B] text-white',
        hoverBorder: 'hover:border-[#26845B]/45',
    },
    {
        label: 'Mi carga',
        sub: `${props.truckInventory.exchange_full_available} cambios llenos · ${props.truckInventory.total_fixed_in_truck} fijos`,
        href: route('repartidor.truck-load.index'),
        icon: Boxes,
        iconBg: 'bg-[#1E1545] text-white',
        hoverBorder: 'hover:border-[#1E1545]/45',
    },
    {
        label: 'Cobros pendientes',
        sub: `${props.counters.cobrar_pendientes} clientes con saldo`,
        href: route('repartidor.debt.index'),
        icon: Wallet,
        iconBg: 'bg-[#D99A27] text-white',
        hoverBorder: 'hover:border-[#D99A27]/45',
    },
    {
        label: 'Clientes',
        sub: 'Directorio y domicilios en zona',
        href: route('repartidor.customers.index'),
        icon: Users,
        iconBg: 'bg-[#0284C7] text-white',
        hoverBorder: 'hover:border-[#0284C7]/45',
    },
];
</script>

<template>
    <Head title="Panel de Reparto - Puritronic" />

    <AuthenticatedLayout :wide="true">
        <div class="bg-[#F5F4F8] min-h-full py-5 sm:py-7 pb-24 lg:pb-8">
            <div class="mx-auto w-full max-w-[98%] 2xl:max-w-[1750px] px-4 sm:px-6 lg:px-8 space-y-5 sm:space-y-6">

                <!-- 1. HERO DE BIENVENIDA -->
                <section
                    aria-label="Bienvenida Repartidor"
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
                                    :class="routeStatusBadge.classes"
                                >
                                    <span class="h-2 w-2 rounded-full shrink-0" :class="routeStatusBadge.dot"></span>
                                    <span>{{ routeStatusBadge.label }}</span>
                                </span>

                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[11px] font-semibold bg-[#1E1545] text-white tabular-nums shadow-sm">
                                    <Clock class="w-3.5 h-3.5 text-[#7DD3FC]" :stroke-width="2" />
                                    <span>{{ meta.schedule_hours }}</span>
                                </span>

                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[11px] font-semibold bg-[#EEEAF7] text-[#1E1545] shadow-sm">
                                    <Calendar class="w-3.5 h-3.5 text-[#0071BA]" :stroke-width="2" />
                                    <span>{{ formattedDate }}</span>
                                </span>
                            </div>

                            <div class="pl-3.5 border-l-4 border-[#1E1545]">
                                <p class="text-xs sm:text-sm font-semibold uppercase tracking-wider text-[#BAE6FD]">
                                    {{ timeGreeting }} · Operación en Ruta
                                </p>
                                <h1 class="text-2xl sm:text-[30px] font-bold tracking-tight text-white leading-tight mt-0.5">
                                    {{ driver.name }}
                                </h1>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2.5">
                            <Link
                                :href="route('repartidor.reparto')"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white text-[#1E1545] hover:bg-[#F4F3F8] font-bold text-xs sm:text-sm shadow-sm transition-colors"
                            >
                                <Truck class="w-4 h-4 text-[#0071BA]" :stroke-width="2.2" />
                                <span>Abrir Reparto</span>
                            </Link>
                            <Link
                                :href="route('repartidor.direct-service.create')"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#1E1545] text-white hover:bg-[#292059] font-bold text-xs sm:text-sm shadow-sm transition-colors"
                            >
                                <PlusCircle class="w-4 h-4 text-[#7DD3FC]" :stroke-width="2" />
                                <span>Pedido al paso</span>
                            </Link>
                            <Link
                                :href="route('repartidor.truck-load.index')"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#005E9B] text-white hover:bg-[#004E82] border border-white/20 font-bold text-xs sm:text-sm shadow-sm transition-colors"
                            >
                                <Boxes class="w-4 h-4" :stroke-width="2" />
                                <span>Mi carga</span>
                            </Link>
                        </div>
                    </div>
                </section>

                <!-- 2. KPI CARDS DE LA JORNADA -->
                <section aria-label="Indicadores de Reparto" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    <!-- KPI 1: Por recoger (Fijos cliente) -->
                    <Link
                        :href="route('repartidor.reparto', { tab: 'por_recoger' })"
                        class="group rounded-2xl border border-[#E9E7F0] bg-white p-5 hover:border-[#D99A27]/45 hover:shadow-sm transition-all"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">Por recoger</p>
                                <p class="mt-2 text-2xl font-bold text-[#1E1545] tabular-nums">
                                    {{ formatNumber(counters.por_recoger) }}
                                    <span class="text-sm font-semibold text-[#747181]">ped.</span>
                                </p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-[#FEF3C7] text-[#B45309] flex items-center justify-center shrink-0">
                                <ArrowDownToLine class="w-5 h-5" :stroke-width="2" />
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#F4F3F8] flex items-center justify-between text-xs">
                            <span class="text-[#747181]">Fijos en domicilio</span>
                            <span class="font-bold text-[#0071BA] group-hover:translate-x-0.5 transition-transform">Ir a pestaña →</span>
                        </div>
                    </Link>

                    <!-- KPI 2: Por subir al camión (Listos en planta) -->
                    <Link
                        :href="route('repartidor.reparto', { tab: 'por_recoger_planta' })"
                        class="group rounded-2xl border border-[#E9E7F0] bg-white p-5 hover:border-[#0071BA]/40 hover:shadow-sm transition-all"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">Por subir en planta</p>
                                <p class="mt-2 text-2xl font-bold text-[#1E1545] tabular-nums">
                                    {{ formatNumber(counters.por_subir) }}
                                    <span class="text-sm font-semibold text-[#747181]">ped.</span>
                                </p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-[#E5F4FD] text-[#0071BA] flex items-center justify-center shrink-0">
                                <ArrowUpFromLine class="w-5 h-5" :stroke-width="2" />
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#F4F3F8] flex items-center justify-between text-xs">
                            <span class="text-[#747181]">Fijos llenos listos</span>
                            <span class="font-bold text-[#0071BA] group-hover:translate-x-0.5 transition-transform">Subir carga →</span>
                        </div>
                    </Link>

                    <!-- KPI 3: Por entregar -->
                    <Link
                        :href="route('repartidor.reparto', { tab: 'por_entregar' })"
                        class="group rounded-2xl border border-[#E9E7F0] bg-white p-5 hover:border-[#26845B]/40 hover:shadow-sm transition-all"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">Por entregar</p>
                                <p class="mt-2 text-2xl font-bold text-[#1E1545] tabular-nums">
                                    {{ formatNumber(counters.por_entregar) }}
                                    <span class="text-sm font-semibold text-[#747181]">ped.</span>
                                </p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-[#E6F7EE] text-[#26845B] flex items-center justify-center shrink-0">
                                <PackageCheck class="w-5 h-5" :stroke-width="2" />
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#F4F3F8] flex items-center justify-between text-xs">
                            <span class="text-[#747181]">Entregados hoy: <strong class="text-[#26845B] tabular-nums">{{ todaySummary.delivered_orders }}</strong></span>
                            <span class="font-bold text-[#0071BA] group-hover:translate-x-0.5 transition-transform">Entregar →</span>
                        </div>
                    </Link>

                    <!-- KPI 4: Cobranza / Recaudado hoy -->
                    <Link
                        :href="route('repartidor.debt.index')"
                        class="group rounded-2xl border border-[#E9E7F0] bg-white p-5 hover:border-[#1E1545]/40 hover:shadow-sm transition-all"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">Cobrado hoy en ruta</p>
                                <p class="mt-2 text-2xl font-bold text-[#1E1545] tabular-nums">
                                    {{ formatCurrency(todaySummary.collected_amount) }}
                                </p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-[#EEEAF7] text-[#1E1545] flex items-center justify-center shrink-0">
                                <DollarSign class="w-5 h-5" :stroke-width="2" />
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#F4F3F8] flex items-center justify-between text-xs">
                            <span class="text-[#747181]">Adeudo: <strong class="text-[#B45309] tabular-nums">{{ formatCurrency(todaySummary.pending_receivable_amount) }}</strong></span>
                            <span class="text-[#747181] tabular-nums">{{ counters.cobrar_pendientes }} clientes</span>
                        </div>
                    </Link>
                </section>

                <!-- 3. PANEL EN 2 COLUMNAS -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                    <!-- Columna izquierda: Carga del camión + Estado de ruta -->
                    <div class="lg:col-span-7 space-y-5">
                        <!-- Estado del camión -->
                        <section class="rounded-2xl border border-[#E9E7F0] bg-white p-5 sm:p-6">
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <div>
                                    <h2 class="text-base font-bold text-[#1E1545]">Inventario a bordo del camión</h2>
                                    <p class="text-xs text-[#747181] mt-0.5">Resumen en tiempo real de cambios y fijos en unidad</p>
                                </div>
                                <Link
                                    :href="route('repartidor.truck-load.index')"
                                    class="inline-flex items-center gap-1 text-xs font-bold text-[#0071BA] hover:text-[#1E1545] transition-colors"
                                >
                                    <span>Ver Mi carga</span>
                                    <ArrowRight class="w-3.5 h-3.5" />
                                </Link>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="rounded-xl border border-[#26845B]/25 bg-[#E6F7EE]/60 p-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-bold uppercase tracking-wide text-[#26845B]">Cambios llenos</span>
                                        <RefreshCw class="w-4 h-4 text-[#26845B]" />
                                    </div>
                                    <p class="mt-2 text-2xl font-bold text-[#1E1545] tabular-nums">
                                        {{ truckInventory.exchange_full_available }}
                                    </p>
                                    <p class="text-xs text-[#747181] mt-0.5">Disponibles para venta</p>
                                </div>

                                <div class="rounded-xl border border-[#D99A27]/30 bg-[#FEF3C7]/45 p-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-bold uppercase tracking-wide text-[#B45309]">Cambios vacíos</span>
                                        <RefreshCw class="w-4 h-4 text-[#D99A27]" />
                                    </div>
                                    <p class="mt-2 text-2xl font-bold text-[#1E1545] tabular-nums">
                                        {{ truckInventory.exchange_empty_collected }}
                                    </p>
                                    <p class="text-xs text-[#747181] mt-0.5">Por bajar en planta</p>
                                </div>

                                <div class="rounded-xl border border-[#0071BA]/25 bg-[#E5F4FD]/60 p-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-bold uppercase tracking-wide text-[#0071BA]">Fijos en camión</span>
                                        <Tag class="w-4 h-4 text-[#0071BA]" />
                                    </div>
                                    <p class="mt-2 text-2xl font-bold text-[#1E1545] tabular-nums">
                                        {{ truckInventory.total_fixed_in_truck }}
                                    </p>
                                    <p class="text-xs text-[#747181] mt-0.5">
                                        {{ (truckInventory.fixed_custody || []).length }} clientes en custodia
                                    </p>
                                </div>
                            </div>

                            <!-- Desglose rápido de fijos en camión -->
                            <div v-if="(truckInventory.fixed_custody || []).length > 0" class="mt-4 pt-4 border-t border-[#F4F3F8]">
                                <p class="text-xs font-bold uppercase tracking-wider text-[#747181] mb-2.5">
                                    Fijos actualmente en unidad
                                </p>
                                <div class="divide-y divide-[#F4F3F8] rounded-xl border border-[#E9E7F0] bg-[#FDFCFE]">
                                    <div
                                        v-for="fc in truckInventory.fixed_custody.slice(0, 5)"
                                        :key="fc.customer_id"
                                        class="px-3.5 py-2.5 flex items-center justify-between gap-3 text-xs"
                                    >
                                        <span class="font-semibold text-[#1E1545] truncate">
                                            {{ fc.customer_nickname || fc.customer_name }}
                                        </span>
                                        <div class="flex items-center gap-2 shrink-0 tabular-nums">
                                            <span v-if="fc.full_to_deliver > 0" class="px-2 py-0.5 rounded-md bg-[#E6F7EE] text-[#26845B] font-bold">
                                                {{ fc.full_to_deliver }} llenos
                                            </span>
                                            <span v-if="fc.empty_collected > 0" class="px-2 py-0.5 rounded-md bg-[#FEF3C7] text-[#B45309] font-bold">
                                                {{ fc.empty_collected }} vacíos
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Resumen de Ruta Activa y Zonas -->
                        <section class="rounded-2xl border border-[#E9E7F0] bg-white p-5 sm:p-6">
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <div class="flex items-center gap-2">
                                    <RouteIcon class="w-4 h-4 text-[#0071BA]" />
                                    <h2 class="text-base font-bold text-[#1E1545]">
                                        {{ activeRoute ? `Ruta ${activeRoute.folio}` : 'Ruta del día' }}
                                    </h2>
                                </div>
                                <Link
                                    :href="route('repartidor.reparto')"
                                    class="text-xs font-bold text-[#0071BA] hover:text-[#1E1545] transition-colors"
                                >
                                    Ver en Reparto
                                </Link>
                            </div>

                            <div v-if="!activeRoute" class="py-7 text-center space-y-2">
                                <p class="text-sm font-semibold text-[#1E1545]">No hay una ruta formal asignada en este momento</p>
                                <p class="text-xs text-[#747181]">
                                    Puedes atender pedidos confirmados y pedidos al paso directamente desde Reparto.
                                </p>
                            </div>

                            <div v-else class="space-y-4">
                                <div class="grid grid-cols-3 gap-3 text-center">
                                    <div class="rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] p-3">
                                        <p class="text-[11px] font-semibold text-[#747181]">Paradas totales</p>
                                        <p class="text-xl font-bold text-[#1E1545] tabular-nums mt-0.5">{{ activeRoute.total_stops }}</p>
                                    </div>
                                    <div class="rounded-xl bg-[#FEF3C7]/45 border border-[#D99A27]/25 p-3">
                                        <p class="text-[11px] font-semibold text-[#B45309]">Pendientes</p>
                                        <p class="text-xl font-bold text-[#1E1545] tabular-nums mt-0.5">{{ activeRoute.pending_stops_count }}</p>
                                    </div>
                                    <div class="rounded-xl bg-[#E6F7EE]/65 border border-[#26845B]/25 p-3">
                                        <p class="text-[11px] font-semibold text-[#26845B]">Completadas</p>
                                        <p class="text-xl font-bold text-[#1E1545] tabular-nums mt-0.5">{{ activeRoute.completed_stops_count }}</p>
                                    </div>
                                </div>

                                <div v-if="activeRoute.total_stops > 0">
                                    <div class="flex items-center justify-between text-xs mb-1.5">
                                        <span class="font-semibold text-[#747181]">Avance de paradas</span>
                                        <span class="font-bold text-[#1E1545] tabular-nums">{{ routeProgressPct }}%</span>
                                    </div>
                                    <div class="h-2 rounded-full bg-[#F4F3F8] overflow-hidden">
                                        <div
                                            class="h-full rounded-full bg-gradient-to-r from-[#0071BA] to-[#26845B] transition-all"
                                            :style="{ width: `${routeProgressPct}%` }"
                                        ></div>
                                    </div>
                                </div>

                                <div v-if="(activeRoute.zones || []).length > 0" class="flex flex-wrap gap-2 pt-1">
                                    <span
                                        v-for="zone in activeRoute.zones"
                                        :key="zone.zone_key"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-[#F4F3F8] text-xs font-semibold text-[#1E1545]"
                                    >
                                        <MapPin class="w-3.5 h-3.5 text-[#0071BA]" />
                                        <span>{{ zone.zone_label }}</span>
                                        <span class="text-[#747181] tabular-nums">({{ zone.pending }} pend.)</span>
                                    </span>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- Columna derecha: Accesos rápidos -->
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

                        <section v-if="counters.transferencias_pendientes > 0" class="rounded-2xl border border-[#D99A27]/40 bg-[#FEF3C7]/60 p-5">
                            <div class="flex items-start gap-3">
                                <div class="h-10 w-10 rounded-xl bg-[#D99A27] text-white flex items-center justify-center shrink-0">
                                    <CheckCircle2 class="w-5 h-5" />
                                </div>
                                <div class="space-y-2">
                                    <h3 class="text-sm font-bold text-[#92400E]">
                                        {{ counters.transferencias_pendientes }} transferencia(s) pendiente(s) de confirmar
                                    </h3>
                                    <p class="text-xs text-[#92400E]/90">
                                        Revisa las cargas enviadas desde Planta para reflejarlas en tu camión.
                                    </p>
                                    <Link
                                        :href="route('repartidor.reparto', { tab: 'por_recoger_planta' })"
                                        class="inline-flex items-center gap-1.5 text-xs font-bold text-[#1E1545] underline"
                                    >
                                        <span>Revisar ahora</span>
                                        <ArrowRight class="w-3.5 h-3.5" />
                                    </Link>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>

        <RepartidorBottomNav />
    </AuthenticatedLayout>
</template>
